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
use Klarna\Kco\Model\Cart\FullUpdate;
use Klarna\Kco\Model\WorkflowProvider;
use Klarna\Logger\Model\Api\Container;
use Klarna\Logger\Api\LoggerInterface;
use Klarna\Base\Model\Responder\Result;
use Klarna\Base\Api\ServiceInterface;
use Klarna\Kco\Model\Checkout\Kco\Initializer as KcoInitializer;
use Klarna\Kco\Model\Responder\Klarna;
use Klarna\Logger\Model\Api\Logger;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Exception\LocalizedException;
use Klarna\Base\Controller\CsrfAbstract;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @api
 */
class AddressUpdate extends CsrfAbstract implements HttpPostActionInterface
{
    /**
     * @var KcoInitializer
     */
    private $kcoInitializer;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Klarna
     */
    private $klarna;
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
     * @var FullUpdate
     */
    private FullUpdate $fullUpdate;
    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @param LoggerInterface $logger
     * @param Klarna $klarna
     * @param KcoInitializer $kcoInitializer
     * @param Result $result
     * @param Logger $apiLogger
     * @param Container $container
     * @param WorkflowProvider $workflowProvider
     * @param FullUpdate $fullUpdate
     * @param RequestInterface $request
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @codeCoverageIgnore
     */
    public function __construct(
        LoggerInterface $logger,
        Klarna $klarna,
        KcoInitializer $kcoInitializer,
        Result $result,
        Logger $apiLogger,
        Container $container,
        WorkflowProvider $workflowProvider,
        FullUpdate $fullUpdate,
        RequestInterface $request
    ) {
        $this->kcoInitializer = $kcoInitializer;
        $this->logger = $logger;
        $this->klarna = $klarna;
        $this->result = $result;
        $this->apiLogger = $apiLogger;
        $this->container = $container;
        $this->workflowProvider = $workflowProvider;
        $this->fullUpdate = $fullUpdate;
        $this->request = $request;
    }

    /**
     * API call to update address details on a customers quote via callback from Klarna
     *
     * @return Json
     */
    public function execute()
    {
        $this->logger->info('AddressUpdate: start');
        $this->container->setService(ServiceInterface::SERVICE_KCO);

        try {
            $this->logger->info('AddressUpdate: updated the address');
            $this->workflowProvider->setKlarnaOrderId($this->getKlarnaOrderId());

            $mageQuote = $this->fullUpdate->updateByKlarnaRequestObject(
                $this->klarna->getKlarnaRequestBody($this->request),
                $this->getKlarnaOrderId(),
                $this->workflowProvider->getMagentoQuote()
            );
        } catch (\Exception $e) {
            $this->logCallbackException($e);
            return $this->getErrorResponseWithStatusCode($e, 400);
        }

        try {
            $response = $this->kcoInitializer->generatedUpdateRequest($mageQuote->getStore());
        } catch (LocalizedException $e) {
            $this->logCallbackException($e);
            return $this->getErrorResponseWithStatusCode($e, 400);
        }

        $this->apiLogger->logCallback(
            $this->container,
            ApiInterface::ACTIONS['address_update'],
            $this->request,
            $response
        );
        return $this->getSuccessfulResult($response);
    }

    /**
     * Returning the success result
     *
     * @param array $response
     * @return Json
     */
    private function getSuccessfulResult(array $response): Json
    {
        $this->logger->info('AddressUpdate: success');
        return $this->result->getJsonResult(200, $response);
    }

    /**
     * Gets the Klarna order ID from the request
     *
     * @return string
     */
    private function getKlarnaOrderId(): string
    {
        $klarnaOrderId = $this->request->getParam('id', '');
        $this->logger->info('AddressUpdate: Kustom checkout id: ' . $klarnaOrderId);

        return $klarnaOrderId;
    }

    /**
     * Logs exception and returns an erroneous JSON result with a given status code
     *
     * @param \Exception $e
     * @param int        $statusCode
     * @return Json
     */
    private function getErrorResponseWithStatusCode($e, int $statusCode): Json
    {
        $this->logger->error('AddressUpdate: exception occurred');
        $this->logger->critical($e);

        return $this->klarna->getAddressErrorResponse($e->getMessage(), $statusCode);
    }

    /**
     * Logging the callback
     *
     * @param \Exception $exception
     */
    private function logCallbackException(\Exception $exception): void
    {
        $this->apiLogger->logCallbackException(
            $this->container,
            ApiInterface::ACTIONS['address_update'],
            $this->request,
            $exception
        );
    }
}
