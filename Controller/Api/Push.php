<?php
/**
 * Copyright © Klarna Bank AB (publ)
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
use Klarna\Kco\Model\Order\Order as CheckoutOrder;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Klarna\Base\Controller\CsrfAbstract;
use Magento\Framework\DataObject;
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
     * @var DataObjectFactory
     */
    private $dataObjectFactory;
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
     * @param LoggerInterface $logger
     * @param CheckoutOrder $checkoutOrder
     * @param DataObjectFactory $dataObjectFactory
     * @param Result $result
     * @param Logger $apiLogger
     * @param Container $container
     * @param WorkflowProvider $workflowProvider
     * @param RequestInterface $request
     * @codeCoverageIgnore
     */
    public function __construct(
        LoggerInterface $logger,
        CheckoutOrder $checkoutOrder,
        DataObjectFactory $dataObjectFactory,
        Result $result,
        Logger $apiLogger,
        Container $container,
        WorkflowProvider $workflowProvider,
        RequestInterface $request
    ) {
        $this->logger = $logger;
        $this->checkoutOrder = $checkoutOrder;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->result = $result;
        $this->apiLogger = $apiLogger;
        $this->container = $container;
        $this->workflowProvider = $workflowProvider;
        $this->request = $request;
    }

    /**
     * Performing the push action logic
     *
     * @return Json|ResultInterface
     * @throws KlarnaException
     * @throws LocalizedException
     * phpcs:disable Commenting.EmptyCatchComment
     */
    public function execute()
    {
        $klarnaOrderId = $this->request->getParam('id');
        $this->workflowProvider->setKlarnaOrderId($klarnaOrderId);

        try {
            $magentoOrder = $this->workflowProvider->getMagentoOrder();
            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
        } catch (KlarnaException $e) {
            $this->logger->debug(
                'No order is created because a payment method is selected with an external redirect.'
            );
            // We do nothing since when using for example the IDEAL payment no order is created at this point
        }

        $this->logger->debug('Push: klarna order id: ' . $klarnaOrderId);

        try {
            $this->checkoutOrder->updateOrderState($klarnaOrderId);
        } catch (KlarnaException $e) {
            return $this->createOrder($klarnaOrderId);
        } catch (LocalizedException $e) {
            $this->apiLogger->logCallbackException(
                $this->container,
                ApiInterface::ACTIONS['push'],
                $this->request,
                $e
            );
            return $this->cancelKlarnaOrder($klarnaOrderId, $e);
        }

        $magentoOrder = $this->checkoutOrder->getMagentoOrder();
        $this->container->setIncrementId($magentoOrder->getIncrementId());
        $this->container->setService(ServiceInterface::SERVICE_KCO);
        $this->apiLogger->logCallback($this->container, ApiInterface::ACTIONS['push'], $this->request, []);

        return $this->getSuccessResponse();
    }

    /**
     * Canceling the Klarna order
     *
     * @param string             $klarnaOrderId
     * @param LocalizedException $e
     * @return Json
     * @throws KlarnaException
     */
    private function cancelKlarnaOrder(string $klarnaOrderId, LocalizedException $e): Json
    {
        $this->logger->critical('Push: Cancelling order. Error occured: ' . $e->getMessage());
        $responseCodeObject = $this->getFailureResponseObject(500);
        $this->checkoutOrder->cancelKlarnaOrder($klarnaOrderId, $e->getMessage());

        return $this->result->getJsonResult(
            (int)$responseCodeObject->getResponseCode(),
            ['error' => $e->getMessage()]
        );
    }

    /**
     * Getting back the success response
     *
     * @return Json
     */
    private function getSuccessResponse(): Json
    {
        $this->logger->debug('Push: success');
        return $this->result->getJsonResult(200);
    }

    /**
     * Getting back the failure response object with the given response code
     *
     * @param int $responseCode
     * @return DataObject
     */
    private function getFailureResponseObject(int $responseCode): DataObject
    {
        $object = $this->dataObjectFactory->create();
        $object->setResponseCode($responseCode);

        return $object;
    }

    /**
     * @param string $klarnaOrderId
     * @param CartLockedException $e
     *
     * @return Json
     */
    private function getCartLockedResponse(string $klarnaOrderId, CartLockedException $e): Json
    {
        $this->logger->debug(
            'Push: Retry order ' . $klarnaOrderId . ' - Exception: ' . $e->getMessage()
        );
        return $this->result->getJsonResult(
            503,
            ['error' => $e->getMessage()]
        );
    }

    /**
     * Create order in Magento if it doesn't currently exist.
     *
     * This is the case when the customer selected a payment gateway method (for example "iDeal").
     *
     * @param string $klarnaOrderId
     * @return Json
     * @throws KlarnaException
     */
    private function createOrder(string $klarnaOrderId): Json
    {
        try {
            $this->checkoutOrder->createMagentoOrder($klarnaOrderId);
            $this->checkoutOrder->sendCustomerMail();
            $this->checkoutOrder->updateOrderState($klarnaOrderId);
        } catch (AlreadyExistsException $e) {
            $this->logger->debug('Push: Order already exists for this Klarna order id: ' . $klarnaOrderId);
        } catch (CartLockedException $e) {
            return $this->getCartLockedResponse($klarnaOrderId, $e);
        } catch (LocalizedException $e) {
            // Before cancelling, check if a concurrent push already created the order successfully.
            // If the Magento order exists, return success to avoid voiding a valid Klarna authorization.
            $magentoOrder = $this->checkoutOrder->getMagentoOrder();
            if ($magentoOrder !== null && $magentoOrder->getId()) {
                $this->logger->debug('Push: Order already created by concurrent request: ' . $klarnaOrderId);
                return $this->getSuccessResponse();
            }
            return $this->cancelKlarnaOrder($klarnaOrderId, $e);
        }
        return $this->getSuccessResponse();
    }
}
