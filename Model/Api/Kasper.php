<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Api;

use Klarna\AdminSettings\Model\Configurations\Api as ApiConfiguration;
use Klarna\Base\Exception as KlarnaException;
use Klarna\Base\Model\Api\Rest\Service;
use Klarna\Kco\Api\ApiInterface;
use Klarna\Kco\Model\Api\Builder\Kasper as KasperBuilder;
use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Klarna\Kco\Model\Checkout\Kco\Session as KcoSession;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Klarna\Base\Model\Api\Exception as KlarnaApiException;

/**
 * Api request to the Klarna Kasper platform
 *
 * @SuppressWarnings(PHPMD.NPathComplexity)
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @api
 */
class Kasper extends DataObject implements ApiInterface
{
    private const HTTP_BAD_REQUEST = 400;

    /**
     * @var ?DataObject
     */
    private ?DataObject $klarnaOrder = null;
    /**
     * @var KcoSession
     */
    private KcoSession $kcoSession;
    /**
     * @var Checkout
     */
    private Checkout $checkout;
    /**
     * @var DataObjectFactory
     */
    private DataObjectFactory $dataObjectFactory;
    /**
     * @var KasperBuilder
     */
    private KasperBuilder $kasperBuilder;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * @var ApiConfiguration
     */
    private ApiConfiguration $apiConfiguration;

    /**
     * @param KcoSession $kcoSession
     * @param Checkout $checkout
     * @param DataObjectFactory $dataObjectFactory
     * @param KasperBuilder $kasperBuilder
     * @param array $data
     * @param LoggerInterface|null $logger
     * @param ApiConfiguration|null $apiConfiguration
     * @codeCoverageIgnore
     */
    public function __construct(
        KcoSession $kcoSession,
        Checkout $checkout,
        DataObjectFactory $dataObjectFactory,
        KasperBuilder $kasperBuilder,
        array $data = [],
        ?LoggerInterface $logger = null,
        ?ApiConfiguration $apiConfiguration = null
    ) {
        parent::__construct($data);
        $this->kcoSession = $kcoSession;
        $this->checkout = $checkout;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->kasperBuilder = $kasperBuilder;
        $this->logger = $logger ?: ObjectManager::getInstance()->get(LoggerInterface::class);
        $this->apiConfiguration = $apiConfiguration ?: ObjectManager::getInstance()->get(ApiConfiguration::class);
    }

    /**
     * Create new order from API
     *
     * @return array|DataObject
     * @throws KlarnaApiException
     * @throws KlarnaException
     */
    public function createOrder()
    {
        $api  = $this->getCheckoutApi();
        $data = $this->getGeneratedCreateRequest();

        $klarnaOrder = $api->createOrder($data, $this->getQuote()->getBaseCurrencyCode());
        if (is_array($klarnaOrder)) {
            $klarnaOrder = $this->dataObjectFactory->create(['data' => $klarnaOrder]);
        }

        $this->logRecurringNotEnabled($data, $klarnaOrder);
        $this->checkOrderStatus($klarnaOrder);
        $this->setKlarnaOrder($klarnaOrder);

        return $klarnaOrder;
    }

    /**
     * Logs an explicit error if Kustom rejected a recurring create request because recurring is not enabled
     *
     * Only logs. The checkout fails exactly as for any other error. Never logs credentials.
     *
     * @param array $request
     * @param DataObject $klarnaOrder
     * @return void
     */
    private function logRecurringNotEnabled(array $request, DataObject $klarnaOrder): void
    {
        if ($klarnaOrder->getIsSuccessful() || ($request['recurring'] ?? false) !== true) {
            return;
        }
        if ((int)$klarnaOrder->getResponseStatusCode() !== self::HTTP_BAD_REQUEST
            || $klarnaOrder->getErrorCode() !== 'BAD_VALUE'
        ) {
            return;
        }
        $messages = $klarnaOrder->getErrorMessages();
        $messages = is_array($messages) ? $messages : [(string)$messages];
        if (stripos(implode(' ', array_map('strval', $messages)), 'recurring') === false) {
            return;
        }

        try {
            $quote = $this->getQuote();
            $store = $quote->getStore();
            $mid = $this->apiConfiguration->getUserName($store, $quote->getBaseCurrencyCode());
            $this->logger->error(sprintf(
                'Recurring orders are not enabled for this merchant id. Kustom merchant support must enable '
                . 'recurring for it. Store id: %s, quote id: %s, merchant id: %s, error_code: %s, '
                . 'error_messages: %s, correlation_id: %s',
                (string)$quote->getStoreId(),
                (string)$quote->getId(),
                (string)$mid,
                (string)$klarnaOrder->getErrorCode(),
                implode(' | ', array_map('strval', $messages)),
                (string)$klarnaOrder->getCorrelationId()
            ));
        } catch (\Throwable $e) {
            // The diagnostic line must never change how the checkout fails.
            unset($e);
        }
    }

    /**
     * Get order from API
     *
     * @param string $currency
     * @param string|null $checkoutId
     * @return array|DataObject
     * @throws KlarnaApiException
     * @throws KlarnaException
     */
    public function retrieveOrder(string $currency, $checkoutId = null)
    {
        if (!$checkoutId) {
            $message = __('Unable to initialize Kustom checkout order');
            throw new KlarnaException($message);
        }
        $api         = $this->getCheckoutApi();
        $klarnaOrder = $api->getOrder($checkoutId, $currency);

        if (is_array($klarnaOrder)) {
            $klarnaOrder = $this->dataObjectFactory->create(['data' => $klarnaOrder]);
        }

        $this->checkOrderStatus($klarnaOrder);
        $this->setKlarnaOrder($klarnaOrder);

        return $klarnaOrder;
    }

    /**
     * Update exiting order from API
     *
     * @param string|null $checkoutId
     * @return array|DataObject
     * @throws KlarnaApiException
     * @throws KlarnaException
     */
    public function updateOrder($checkoutId = null)
    {
        $api  = $this->getCheckoutApi();
        $data = $this->getGeneratedUpdateRequest();

        $klarnaOrder = $api->updateOrder($checkoutId, $data, $this->getQuote()->getBaseCurrencyCode());

        if (is_array($klarnaOrder)) {
            $klarnaOrder = $this->dataObjectFactory->create(['data' => $klarnaOrder]);
        }

        // Order expired
        if ($klarnaOrder->getResponseStatusCode() === Service::HTTP_NOT_FOUND) {
            $klarnaOrder = $this->createOrder();
        }

        $this->checkOrderStatus($klarnaOrder);
        $this->setKlarnaOrder($klarnaOrder);

        return $klarnaOrder;
    }

    /**
     * Check the status of order and throw exception when error
     *
     * @param DataObject $klarnaOrder
     * @throws KlarnaApiException
     * @throws KlarnaException
     */
    private function checkOrderStatus($klarnaOrder)
    {
        if ($klarnaOrder->getIsSuccessful()) {
            return;
        }
        // If we still get an error, give up
        if ($klarnaOrder->getResponseStatusCode() === Service::HTTP_UNAUTHORIZED) {
            throw new KlarnaApiException(
                __(
                    $klarnaOrder->getResponseStatusMessage() .
                    '. Please check credentials and API version selected'
                )
            );
        }
        $message = __('Unable to initialize Kustom checkout order');

        $klarnaErrorMessages = $klarnaOrder->getErrorMessages();
        if ($klarnaErrorMessages === null) {
            $klarnaErrorMessages = [];
        }

        $apiMessages = implode('<br/>', $klarnaErrorMessages);
        if (!empty($apiMessages)) {
            $message = __('Unable to initialize Kustom checkout order. Kustom api error: %1', $apiMessages);
        }

        throw new KlarnaException($message);
    }

    /**
     * Get the api for checkout api
     *
     * @return Checkout
     */
    private function getCheckoutApi()
    {
        return $this->checkout;
    }

    /**
     * Get the html snippet for an order
     *
     * @return string
     */
    public function getKlarnaCheckoutGui()
    {
        return $this->getKlarnaOrder()->getHtmlSnippet();
    }

    /**
     * Get Klarna Checkout Reservation Id
     *
     * @return string
     */
    public function getReservationId()
    {
        return $this->getKlarnaOrder()->getOrderId();
    }

    /**
     * Get Klarna checkout order details
     *
     * @return DataObject
     */
    public function getKlarnaOrder()
    {
        if ($this->klarnaOrder === null) {
            $this->klarnaOrder = $this->dataObjectFactory->create();
        }

        return $this->klarnaOrder;
    }

    /**
     * Set Klarna checkout order details
     *
     * @param DataObject $klarnaOrder
     *
     * @return $this
     */
    public function setKlarnaOrder(DataObject $klarnaOrder)
    {
        $this->klarnaOrder = $klarnaOrder;

        return $this;
    }

    /**
     * Get generated create request
     *
     * @return array
     * @throws KlarnaException
     */
    public function getGeneratedCreateRequest()
    {
        return $this->kasperBuilder
            ->generateCreateRequest($this->getQuote())
            ->getRequest();
    }

    /**
     * Get current quote
     *
     * @return \Magento\Quote\Model\Quote
     */
    public function getQuote()
    {
        if ($this->hasData('quote')) {
            return $this->getData('quote');
        }

        $this->setData('quote', $this->getKcoSession()->getQuote());
        return $this->getData('quote');
    }

    /**
     * Get one page checkout model
     *
     * @return KcoSession
     */
    public function getKcoSession(): KcoSession
    {
        return $this->kcoSession;
    }

    /**
     * Get generated update request
     *
     * @return array
     * @throws KlarnaException
     */
    public function getGeneratedUpdateRequest()
    {
        return $this->kasperBuilder
            ->generateUpdateRequest($this->getQuote())
            ->getRequest();
    }
}
