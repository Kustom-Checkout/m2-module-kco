<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Controller\Api;

use Klarna\Base\Api\ServiceInterface;
use Klarna\Kco\Api\ApiInterface;
use Klarna\Kco\Model\Cart\ShippingMethod\KlarnaRequestQuoteTransformer;
use Klarna\Logger\Model\Api\Logger;
use Klarna\Logger\Model\Api\Container;
use Klarna\Base\Model\Responder\Result;
use Klarna\Kco\Model\Responder\Klarna;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Klarna\Logger\Api\LoggerInterface;
use Klarna\Kco\Model\Checkout\Kco\Initializer as kcoInitializer;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\App\RequestInterface;
use Klarna\Base\Controller\CsrfAbstract;

/**
 * API call to set shipping method on a customers quote via callback from Klarna
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @api
 */
class ShippingMethodUpdate extends CsrfAbstract implements HttpPostActionInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var kcoInitializer
     */
    private $kcoInitializer;
    /**
     * @var Result
     */
    private $result;
    /**
     * @var Klarna
     */
    private $klarna;
    /**
     * @var Logger
     */
    private $apiLogger;
    /**
     * @var Container
     */
    private $container;
    /**
     * @var KlarnaRequestQuoteTransformer
     */
    private KlarnaRequestQuoteTransformer $klarnaRequestQuoteTransformer;
    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @param LoggerInterface $logger
     * @param kcoInitializer $kcoInitializer
     * @param Result $result
     * @param Klarna $klarna
     * @param Logger $apiLogger
     * @param Container $container
     * @param KlarnaRequestQuoteTransformer $klarnaRequestQuoteTransformer
     * @param RequestInterface $request
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @codeCoverageIgnore
     */
    public function __construct(
        LoggerInterface $logger,
        kcoInitializer $kcoInitializer,
        Result $result,
        Klarna $klarna,
        Logger $apiLogger,
        Container $container,
        KlarnaRequestQuoteTransformer $klarnaRequestQuoteTransformer,
        RequestInterface $request
    ) {
        $this->logger           = $logger;
        $this->kcoInitializer   = $kcoInitializer;
        $this->result           = $result;
        $this->klarna           = $klarna;
        $this->apiLogger        = $apiLogger;
        $this->container        = $container;
        $this->klarnaRequestQuoteTransformer = $klarnaRequestQuoteTransformer;
        $this->request = $request;
    }

    /**
     * Updating the selected shipping method
     *
     * @return Json
     * @throws LocalizedException
     */
    public function execute()
    {
        $this->logger->info('ShippingMethodUpdate: start');

        $klarnaOrderId = $this->request->getParam('id');
        $this->logger->info('ShippingMethodUpdate: Kustom order id: ' . $klarnaOrderId);

        try {
            $this->klarnaRequestQuoteTransformer->updateQuoteShippingMethod(
                $this->klarna->getKlarnaRequestBody($this->request),
                $klarnaOrderId
            );
        } catch (LocalizedException $e) {
            $this->logCallbackException($e);
            return $this->getFailureResult($e->getMessage(), 400);
        }

        try {
            $response = $this->kcoInitializer->generatedUpdateRequest(
                $this->kcoInitializer->getKcoSession()->getQuote()->getStore()
            );
        } catch (LocalizedException $e) {
            $this->logCallbackException($e);
            return $this->getFailureResult($e->getMessage(), 500);
        }

        $this->logger->info('ShippingMethodUpdate: success');

        $this->container->setService(ServiceInterface::SERVICE_KCO);
        $this->apiLogger->logCallback(
            $this->container,
            ApiInterface::ACTIONS['shipping_method_update'],
            $this->request,
            $response
        );

        return $this->result->getJsonResult(200, $response);
    }

    /**
     * Getting back the failure result
     *
     * @param string $message
     * @param int $httpCode
     * @return Json
     */
    private function getFailureResult(string $message, int $httpCode): Json
    {
        $this->logger->error('ShippingMethodUpdate error: ' . $message);
        return $this->result->getJsonResult($httpCode, ['error' => $message]);
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
            ApiInterface::ACTIONS['shipping_method_update'],
            $this->request,
            $exception
        );
    }
}
