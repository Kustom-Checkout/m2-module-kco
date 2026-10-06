<?php

/**
 * Copyright 2025 Kustom AB (Originally developed by Klarna Bank AB)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Controller\Api;

use Klarna\Kco\Api\ApiInterface;
use Klarna\Kco\Model\WorkflowProvider;
use Klarna\Logger\Api\LoggerInterface;
use Klarna\Base\Api\ServiceInterface;
use Klarna\Base\Exception as KlarnaException;
use Klarna\Logger\Model\Api\Logger;
use Klarna\Logger\Model\Api\Container;
use Klarna\Base\Model\Responder\Result;
use Klarna\Kco\Model\Order\CreationLock;
use Klarna\Kco\Model\Order\Order as CheckoutOrder;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Klarna\Base\Controller\CsrfAbstract;
use Magento\Quote\Model\CartLockedException;

/**
 * API call to notify Magento that the order is now ready to receive order management calls
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @api
 */
class Push extends CsrfAbstract implements HttpPostActionInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CheckoutOrder
     */
    private $checkoutOrder;

    /**
     * @var Result
     */
    private $result;

    /**
     * @var Logger
     */
    private $apiLogger;

    /**
     * @var Container
     */
    private $container;

    /**
     * @var WorkflowProvider
     */
    private WorkflowProvider $workflowProvider;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var CreationLock
     */
    private CreationLock $creationLock;

    /**
     * @param LoggerInterface $logger
     * @param CheckoutOrder $checkoutOrder
     * @param DataObjectFactory $dataObjectFactory
     * @param Result $result
     * @param Logger $apiLogger
     * @param Container $container
     * @param WorkflowProvider $workflowProvider
     * @param RequestInterface $request
     * @param CreationLock|null $creationLock
     */
    public function __construct(
        LoggerInterface $logger,
        CheckoutOrder $checkoutOrder,
        DataObjectFactory $dataObjectFactory, // To be removed as unused in next major release!
        Result $result,
        Logger $apiLogger,
        Container $container,
        WorkflowProvider $workflowProvider,
        RequestInterface $request,
        ?CreationLock $creationLock = null
    ) {
        $this->logger = $logger;
        $this->checkoutOrder = $checkoutOrder;
        $this->result = $result;
        $this->apiLogger = $apiLogger;
        $this->container = $container;
        $this->workflowProvider = $workflowProvider;
        $this->request = $request;
        $this->creationLock = $creationLock ?: ObjectManager::getInstance()->get(CreationLock::class);
    }

    /**
     * Performing the push action logic
     *
     * @inheritDoc
     */
    public function execute()
    {
        $klarnaOrderId = $this->request->getParam('id');
        $this->workflowProvider->setKlarnaOrderId($klarnaOrderId);
        $this->logger->debug('Push: klarna order id: ' . $klarnaOrderId);

        $errorResponse = $this->canCreateOrder() ? $this->createOrder($klarnaOrderId) : null;
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        return $this->updateOrderState($klarnaOrderId);
    }

    /**
     * @return bool
     */
    private function canCreateOrder(): bool
    {
        // TODO: We shouldn't need to rely on exception + it can result in false positives, let's eventually add
        // possibility to figure out existence of these instances by something more simplified

        try {
            $this->workflowProvider->getMagentoOrder();
            $this->workflowProvider->getKlarnaOrder();

            return false;
        } catch (KlarnaException $exception) {
            return true;
        }
    }

    /**
     * Creating the order. Returns an error response when the push has to stop, null when it can continue.
     *
     * @param string $klarnaOrderId
     *
     * @return Json|null
     */
    private function createOrder(string $klarnaOrderId): ?Json
    {
        $this->logger->debug('Push: Attempting to create order by id ' . $klarnaOrderId);

        if (!$this->creationLock->acquire($klarnaOrderId)) {
            $this->logger->debug('Push: Order creation in progress by concurrent request: ' . $klarnaOrderId);

            return $this->result->getJsonResult(
                503,
                ['error' => 'Order creation is in progress. Please try again later.']
            );
        }

        try {
            if ($this->checkoutOrder->isMagentoOrderExists($klarnaOrderId)) {
                $this->logger->debug('Push: Order already created by concurrent request: ' . $klarnaOrderId);

                return null;
            }

            $this->checkoutOrder->createMagentoOrder($klarnaOrderId);
        } catch (AlreadyExistsException $exception) {
            $this->logger->debug('Push: Order already exists for this Klarna order id: ' . $klarnaOrderId);

            return null;
        } catch (CartLockedException $exception) {
            $this->logger->debug('Push: Retry order ' . $klarnaOrderId . ' - Exception: ' . $exception->getMessage());

            return $this->result->getJsonResult(
                503,
                ['error' => $exception->getMessage()]
            );
        } catch (LocalizedException $e) {
            if ($this->checkoutOrder->isMagentoOrderExists($klarnaOrderId)) {
                $this->logger->debug('Push: Order already created by concurrent request: ' . $klarnaOrderId);

                return null;
            }

            $this->logger->debug('Push: Order creation failed: ' . $e->getMessage());

            $this->apiLogger->logCallbackException(
                $this->container,
                ApiInterface::ACTIONS['push'],
                $this->request,
                $e
            );

            return $this->result->getJsonResult(
                500,
                ['error' => 'Failed to create order']
            );
        } finally {
            $this->creationLock->release($klarnaOrderId);
        }

        $this->checkoutOrder->sendCustomerMail();
        $this->logger->debug('Push: Order created successfully by id ' . $klarnaOrderId);

        return null;
    }

    /**
     * @param string $klarnaOrderId
     *
     * @return Json
     */
    private function updateOrderState(string $klarnaOrderId): Json
    {
        try {
            $this->checkoutOrder->updateOrderState($klarnaOrderId);
        } catch (LocalizedException $e) {
            $this->apiLogger->logCallbackException(
                $this->container,
                ApiInterface::ACTIONS['push'],
                $this->request,
                $e
            );

            return $this->result->getJsonResult(
                500,
                ['error' => 'Failed to update order state']
            );
        }

        $magentoOrder = $this->checkoutOrder->getMagentoOrder();
        $this->container->setIncrementId($magentoOrder->getIncrementId());
        $this->container->setService(ServiceInterface::SERVICE_KCO);
        $this->apiLogger->logCallback($this->container, ApiInterface::ACTIONS['push'], $this->request, []);

        $this->logger->debug('Push: success');

        return $this->result->getJsonResult(200);
    }
}
