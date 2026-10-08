<?php

/**
 * Copyright 2025 Kustom AB (Originally developed by Klarna Bank AB)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Controller\Klarna;

use Klarna\Kco\Model\Checkout\Url;
use Klarna\Kco\Model\Order\CreationLock;
use Klarna\Kco\Model\WorkflowProvider;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Klarna\Kco\Model\Order\Order as CheckoutOrder;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Klarna\Base\Exception as KlarnaException;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\CartLockedException;

/**
 * The Klarna confirmation controller
 *
 * @api
 */
class Confirmation implements HttpGetActionInterface
{
    /**
     * @var Url
     */
    private $url;

    /**
     * @var CheckoutOrder
     */
    private $checkoutOrder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var RedirectFactory
     */
    private RedirectFactory $redirectFactory;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $messageManager;

    /**
     * @var CreationLock
     */
    private CreationLock $creationLock;

    /**
     * @var WorkflowProvider
     */
    private WorkflowProvider $workflowProvider;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @param Url $url
     * @param CheckoutOrder $checkoutOrder
     * @param LoggerInterface $logger
     * @param RequestInterface $request
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     * @param CreationLock|null $creationLock
     * @param WorkflowProvider|null $workflowProvider
     * @param CheckoutSession|null $checkoutSession
     */
    public function __construct(
        Url $url,
        CheckoutOrder $checkoutOrder,
        LoggerInterface $logger,
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager,
        ?CreationLock $creationLock = null,
        ?WorkflowProvider $workflowProvider = null,
        ?CheckoutSession $checkoutSession = null
    ) {
        $this->url = $url;
        $this->checkoutOrder = $checkoutOrder;
        $this->logger = $logger;
        $this->request = $request;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
        $this->creationLock = $creationLock ?: ObjectManager::getInstance()->get(CreationLock::class);
        $this->workflowProvider = $workflowProvider ?: ObjectManager::getInstance()->get(WorkflowProvider::class);
        $this->checkoutSession = $checkoutSession ?: ObjectManager::getInstance()->get(CheckoutSession::class);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $klarnaOrderId = $this->request->getParam('id');
        $this->logger->debug('Kustom order id: ' . $klarnaOrderId);

        if (!$klarnaOrderId) {
            return $this->getInvalidOrderIdResponse();
        }

        if (!$this->creationLock->acquire($klarnaOrderId)) {
            $this->logger->debug('Confirmation: Order creation in progress by concurrent request: ' . $klarnaOrderId);

            if ($this->checkoutOrder->isMagentoOrderExists($klarnaOrderId)) {
                return $this->getExistingOrderResponse($klarnaOrderId);
            }

            return $this->getSuccessResponse();
        }

        try {
            if ($this->checkoutOrder->isMagentoOrderExists($klarnaOrderId)) {
                $this->logger->debug('Confirmation: Order already created by concurrent request: ' . $klarnaOrderId);

                return $this->getExistingOrderResponse($klarnaOrderId);
            }

            $this->checkoutOrder->createMagentoOrder($klarnaOrderId);
        } catch (CartLockedException $e) {
            $this->logger->debug(
                'Confirmation: push running concurrently: ' . $klarnaOrderId . ' - Exception: ' . $e->getMessage()
            );

            return $this->getSuccessResponse();
        } catch (AlreadyExistsException $e) {
            $this->logger->debug(
                'Confirmation: push running concurrently: ' . $klarnaOrderId . ' - Exception: ' . $e->getMessage()
            );

            return $this->getExistingOrderResponse($klarnaOrderId);
        } catch (LocalizedException $e) {
            if ($this->checkoutOrder->isMagentoOrderExists($klarnaOrderId)) {
                $this->logger->debug('Confirmation: Order already created by concurrent request: ' . $klarnaOrderId);

                return $this->getExistingOrderResponse($klarnaOrderId);
            }

            return $this->getErrorResponse($e);
        } finally {
            $this->creationLock->release($klarnaOrderId);
        }

        $this->checkoutOrder->sendCustomerMail();

        return $this->getSuccessResponse();
    }

    /**
     * Returning the success response for an order which was created by a concurrent request
     *
     * The concurrent request (push or a second confirmation call) filled its own checkout session with the order
     * data. Without that data in the session of this customer the success page validation fails and the customer
     * would see an error page although the order was placed, so we fill it here.
     *
     * @param string $klarnaOrderId
     * @return Redirect
     */
    private function getExistingOrderResponse(string $klarnaOrderId): Redirect
    {
        try {
            $this->workflowProvider->setKlarnaOrderId($klarnaOrderId);
            $magentoOrder = $this->workflowProvider->getMagentoOrder();

            $this->checkoutSession->setLastQuoteId($magentoOrder->getQuoteId());
            $this->checkoutSession->setLastSuccessQuoteId($magentoOrder->getQuoteId());
            $this->checkoutSession->setLastOrderId($magentoOrder->getId());
            $this->checkoutSession->setLastRealOrderId($magentoOrder->getIncrementId());
            $this->checkoutSession->setLastOrderStatus($magentoOrder->getStatus());
        } catch (KlarnaException $e) {
            $this->logger->debug(
                'Confirmation: Could not fill the checkout session for the Kustom order id: ' . $klarnaOrderId
            );
        }

        return $this->getSuccessResponse();
    }

    /**
     * Returning the success response
     *
     * @return Redirect
     */
    private function getSuccessResponse(): Redirect
    {
        $this->logger->debug('Confirmation: Success');

        return $this->redirectFactory->create()->setPath(Url::CHECKOUT_ACTION_PREFIX . '/success');
    }

    /**
     * Returning a general error response
     *
     * @param KlarnaException|NoSuchEntityException|CouldNotSaveException|LocalizedException $e
     *
     * @return Redirect
     */
    private function getErrorResponse($e): Redirect
    {
        $this->logger->critical($e);
        $this->messageManager->addErrorMessage($e->getMessage());

        return $this->redirectFactory->create()->setUrl($this->url->getFailureUrl());
    }

    /**
     * Returning a invalid order id response
     *
     * @return Redirect
     */
    private function getInvalidOrderIdResponse(): Redirect
    {
        $this->messageManager->addErrorMessage(__('Unable to process order. Please try again'));

        return $this->redirectFactory->create()->setUrl($this->url->getFailureUrl());
    }
}
