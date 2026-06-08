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
use Klarna\Logger\Api\LoggerInterface;
use Klarna\Base\Api\ServiceInterface;
use Klarna\Base\Exception as KlarnaException;
use Klarna\Logger\Model\Api\Logger;
use Klarna\Logger\Model\Api\Container;
use Klarna\Base\Model\Responder\Result;
use Klarna\Kco\Model\Checkout\Url;
use Klarna\Kco\Model\Cart\Validations\Handler;
use Klarna\Kco\Model\WorkflowProvider;
use Klarna\Kco\Model\Responder\Klarna;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Klarna\Base\Controller\CsrfAbstract;

/**
 * Validating the status of the Magento quote and Klarna order to each other
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @api
 */
class Validate extends CsrfAbstract implements HttpPostActionInterface
{
    /**
     * @var Handler
     */
    private $validation;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var WorkflowProvider
     */
    private $workflowProvider;
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
     * @var RequestInterface
     */
    private RequestInterface $request;
    /**
     * @var ManagerInterface
     */
    private ManagerInterface $messageManager;
    /**
     * @var RedirectFactory
     */
    private RedirectFactory $redirectFactory;

    /**
     * @param LoggerInterface $logger
     * @param Handler $validation
     * @param WorkflowProvider $workflowProvider
     * @param Result $result
     * @param Klarna $klarna
     * @param Logger $apiLogger
     * @param Container $container
     * @param RequestInterface $request
     * @param ManagerInterface $messageManager
     * @param RedirectFactory $redirectFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @codeCoverageIgnore
     */
    public function __construct(
        LoggerInterface $logger,
        Handler $validation,
        WorkflowProvider $workflowProvider,
        Result $result,
        Klarna $klarna,
        Logger $apiLogger,
        Container $container,
        RequestInterface $request,
        ManagerInterface $messageManager,
        RedirectFactory $redirectFactory
    ) {
        $this->validation = $validation;
        $this->logger = $logger;
        $this->workflowProvider = $workflowProvider;
        $this->result = $result;
        $this->klarna = $klarna;
        $this->apiLogger = $apiLogger;
        $this->container = $container;
        $this->request = $request;
        $this->messageManager = $messageManager;
        $this->redirectFactory = $redirectFactory;
    }

    /**
     * Validation action
     *
     * @return ResultInterface|Json
     */
    public function execute()
    {
        $this->logger->info('Validate: start');

        $checkoutId = $this->request->getParam('id');
        $this->logger->info('Validate: Kustom checkout id: ' . $checkoutId);

        try {
            $this->workflowProvider->setKlarnaOrderId($checkoutId);

            $this->validation->validateRequestObject(
                $this->klarna->getKlarnaRequestBody($this->request),
                $this->workflowProvider->getMagentoQuote()
            );
        } catch (KlarnaException $e) {
            return $this->setValidateFailedResponse($checkoutId, $e);
        }

        $this->container->setService(ServiceInterface::SERVICE_KCO);
        $this->apiLogger->logCallback($this->container, ApiInterface::ACTIONS['validate'], $this->request, []);

        return $this->getSuccessResponse();
    }

    /**
     * Getting back the success response
     *
     * @return Json
     */
    private function getSuccessResponse(): Json
    {
        $this->logger->info('Validate: success');
        return $this->result->getJsonResult(200);
    }

    /**
     * Set the response that validation has failed
     *
     * @param string $checkoutId
     * @param KlarnaException $e
     * @return Redirect
     */
    private function setValidateFailedResponse($checkoutId, KlarnaException $e): Redirect
    {
        $message = $e->getMessage();
        $this->logger->warning('Validate: Magento quote does not match Kustom order: ' . $message);
        $this->messageManager->addErrorMessage($message);

        return $this->redirectFactory->create()
            ->setHttpResponseCode(303)
            ->setStatusHeader(303, null, $message)
            ->setPath(
                Url::CHECKOUT_ACTION_PREFIX . '/validateFailed',
                [
                    '_nosid'  => true,
                    '_escape' => false,
                    '_query'  => ['id' => $checkoutId, 'message' => $message]
                ]
            );
    }
}
