<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Controller\Klarna;

use Klarna\Kco\Model\Checkout\Url;
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
     * @param Url $url
     * @param CheckoutOrder $checkoutOrder
     * @param LoggerInterface $logger
     * @param RequestInterface $request
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Url $url,
        CheckoutOrder $checkoutOrder,
        LoggerInterface $logger,
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager
    ) {
        $this->url = $url;
        $this->checkoutOrder = $checkoutOrder;
        $this->logger = $logger;
        $this->request = $request;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
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

        try {
            $this->checkoutOrder->createMagentoOrder($klarnaOrderId);
            $this->checkoutOrder->sendCustomerMail();
        } catch (CartLockedException $e) {
            $this->logger->debug('Confirmation: push running concurrently: ' . $klarnaOrderId . ' - Exception: ' . $e->getMessage());

            return $this->getSuccessResponse();
        } catch (AlreadyExistsException $e) {
            $this->logger->debug('Confirmation: push running concurrently: ' . $klarnaOrderId . ' - Exception: ' . $e->getMessage());

            return $this->getSuccessResponse();
        } catch (LocalizedException $e) {
            if ($this->checkoutOrder->isMagentoOrderExists($klarnaOrderId)) {
                $this->logger->debug('Confirmation: Order already created by concurrent request: ' . $klarnaOrderId);

                return $this->getSuccessResponse();
            }

            return $this->getErrorResponse($e);
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
