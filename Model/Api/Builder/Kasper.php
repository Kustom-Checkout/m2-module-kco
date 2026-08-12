<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Api\Builder;

use Klarna\Base\Api\BuilderInterface;
use Klarna\Base\Exception;
use Klarna\Base\Helper\DataConverter;
use Klarna\Base\Helper\KlarnaConfig;
use Klarna\Kco\Helper\KlarnaConfig as KcoKlarnaConfig;
use Klarna\Base\Model\Api\MagentoToKlarnaLocaleMapper;
use Klarna\Kco\Model\Checkout\B2b;
use Klarna\AdminSettings\Model\Configurations\Kco\Checkbox;
use Klarna\AdminSettings\Model\Configurations\Kco\Checkout;
use Klarna\AdminSettings\Model\Configurations\Kco\MandatoryFields;
use Klarna\AdminSettings\Model\Configurations\Kco\Prefill;
use Klarna\AdminSettings\Model\Configurations\Kco\ShippingOptions;
use Klarna\Orderlines\Model\Container\Parameter;
use Klarna\Kco\Model\Carrier\Klarna;
use Klarna\Kco\Model\Checkout\Kco\Session;
use Klarna\Kco\Model\Checkout\Kco\Session as KcoSession;
use Klarna\Kco\Model\Checkout\Url as CheckoutUrl;
use Klarna\Kco\Model\Payment\Kco;
use Klarna\Kco\Model\Checkout\Configuration\SettingsProvider;
use Klarna\Kss\Model\KssConfigProvider;
use Klarna\Orderlines\Model\Fpt\Calculator;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Url;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote as MageQuote;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Klarna\Kco\Model\Checkout\Checkbox\Validator;
use Klarna\Kco\Model\Checkout\Checkbox\DataProvider;
use Klarna\Orderlines\Model\Fpt\Validator as FptValidator;
use Klarna\AdminSettings\Model\Configurations\Kco\Url as KcoUrl;
use Magento\Framework\App\ObjectManager;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 * @api
 */
class Kasper implements BuilderInterface
{
    /**
     * @var DataConverter
     */
    private $dataConverter;
    /**
     * @var Calculator $calculator
     */
    private $calculator;
    /**
     * @var SettingsProvider $config
     */
    private $config;
    /**
     * @var CheckoutUrl
     */
    private $checkoutUrl;
    /**
     * @var Parameter $parameter
     */
    private $parameter;
    /**
     * @var DirectoryHelper $directoryHelper
     */
    private $directoryHelper;
    /**
     * @var KlarnaConfig $klarnaConfig
     */
    private $klarnaConfig;
    /**
     * @var Url $url
     */
    private $url;
    /**
     * @var DataObjectFactory $dataObjectFactory
     */
    private $dataObjectFactory;
    /**
     * @var DateTime $coreDate
     */
    private $coreDate;

    /** @var KcoSession $kcoSession */
    private $kcoSession;
    /**
     * @var KssConfigProvider
     */
    private $kssConfigProvider;
    /**
     * @var ShippingMethodManagementInterface
     */
    private $shippingMethodManagement;
    /**
     * @var Validator
     */
    private Validator $validator;
    /**
     * @var DataProvider
     */
    private DataProvider $dataProvider;
    /**
     * @var Checkout
     */
    private Checkout $checkoutConfiguration;
    /**
     * @var Prefill
     */
    private Prefill $prefillConfiguration;
    /**
     * @var Checkbox
     */
    private Checkbox $checkboxConfiguration;
    /**
     * @var ShippingOptions
     */
    private ShippingOptions $shippingOptionsConfiguration;
    /**
     * @var MandatoryFields
     */
    private MandatoryFields $mandatoryFieldsConfiguration;
    /**
     * @var KcoUrl
     */
    private KcoUrl $urlConfiguration;
    /**
     * @var FptValidator
     */
    private FptValidator $fptValidator;
    /**
     * @var B2b
     */
    private B2b $b2b;
    /**
     * @var MagentoToKlarnaLocaleMapper
     */
    private MagentoToKlarnaLocaleMapper $magentoToKlarnaLocaleMapper;
    /**
     * @var KcoKlarnaConfig
     */
    private KcoKlarnaConfig $kcoKlarnaConfig;

    /**
     * @param Url                                         $url
     * @param Calculator                                  $calculator
     * @param DirectoryHelper                             $directoryHelper
     * @param DateTime                                    $coreDate
     * @param KlarnaConfig                                $klarnaConfig
     * @param DataObjectFactory                           $dataObjectFactory
     * @param DataConverter                               $dataConverter
     * @param SettingsProvider                            $config
     * @param CheckoutUrl                                 $checkoutUrl
     * @param Parameter                                   $parameter
     * @param Session                                     $kcoSession
     * @param KssConfigProvider                           $kssConfigProvider
     * @param ShippingMethodManagementInterface           $shippingMethodManagement
     * @param Validator                                   $validator
     * @param DataProvider                                $dataProvider
     * @param Checkout                                    $checkoutConfiguration
     * @param Prefill                                     $prefillConfiguration
     * @param Checkbox                                    $checkboxConfiguration
     * @param ShippingOptions                             $shippingOptionsConfiguration
     * @param MandatoryFields                             $mandatoryFieldsConfiguration
     * @param KcoUrl                                      $urlConfiguration
     * @param FptValidator                                $fptValidator
     * @param B2b                                         $b2b
     * @param MagentoToKlarnaLocaleMapper                 $magentoToKlarnaLocaleMapper
     * @param KcoKlarnaConfig|null                        $kcoKlarnaConfig
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @codeCoverageIgnore
     */
    public function __construct(
        Url $url,
        Calculator $calculator,
        DirectoryHelper $directoryHelper,
        DateTime $coreDate,
        KlarnaConfig $klarnaConfig,
        DataObjectFactory $dataObjectFactory,
        DataConverter $dataConverter,
        SettingsProvider $config,
        CheckoutUrl $checkoutUrl,
        Parameter $parameter,
        Session $kcoSession,
        KssConfigProvider $kssConfigProvider,
        ShippingMethodManagementInterface $shippingMethodManagement,
        Validator $validator,
        DataProvider $dataProvider,
        Checkout $checkoutConfiguration,
        Prefill $prefillConfiguration,
        Checkbox $checkboxConfiguration,
        ShippingOptions $shippingOptionsConfiguration,
        MandatoryFields $mandatoryFieldsConfiguration,
        KcoUrl $urlConfiguration,
        FptValidator $fptValidator,
        B2b $b2b,
        MagentoToKlarnaLocaleMapper $magentoToKlarnaLocaleMapper,
        ?KcoKlarnaConfig $kcoKlarnaConfig = null
    ) {
        $this->dataConverter            = $dataConverter;
        $this->calculator               = $calculator;
        $this->config                   = $config;
        $this->checkoutUrl              = $checkoutUrl;
        $this->parameter                = $parameter;
        $this->directoryHelper          = $directoryHelper;
        $this->klarnaConfig             = $klarnaConfig;
        $this->url                      = $url;
        $this->dataObjectFactory        = $dataObjectFactory;
        $this->coreDate                 = $coreDate;
        $this->kcoSession               = $kcoSession;
        $this->kssConfigProvider        = $kssConfigProvider;
        $this->shippingMethodManagement = $shippingMethodManagement;
        $this->validator                = $validator;
        $this->dataProvider             = $dataProvider;
        $this->checkoutConfiguration = $checkoutConfiguration;
        $this->prefillConfiguration = $prefillConfiguration;
        $this->checkboxConfiguration = $checkboxConfiguration;
        $this->shippingOptionsConfiguration = $shippingOptionsConfiguration;
        $this->mandatoryFieldsConfiguration = $mandatoryFieldsConfiguration;
        $this->urlConfiguration = $urlConfiguration;
        $this->fptValidator = $fptValidator;
        $this->b2b = $b2b;
        $this->magentoToKlarnaLocaleMapper = $magentoToKlarnaLocaleMapper;
        $this->kcoKlarnaConfig = $kcoKlarnaConfig ?: ObjectManager::getInstance()->get(
            KcoKlarnaConfig::class
        );
    }

    /**
     * @inheritdoc
     */
    public function getParameter()
    {
        return $this->parameter;
    }

    /**
     * @inheritdoc
     */
    public function generateCreateRequest(CartInterface $quote)
    {
        $request = $this->generateRequest($quote);
        $this->parameter->setRequest($request);

        $this->handlePrefillNotice();
        $this->handlePackstationSettings();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function generateUpdateRequest(CartInterface $quote)
    {
        $request = $this->generateRequest($quote);
        $this->parameter->setRequest($request);

        $this->handlePrefillNotice();
        $this->handlePackstationSettings();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function generatePlaceOrderRequest(CartInterface $quote)
    {
        $request = $this->generateRequest($quote);
        $this->parameter->setRequest($request);

        $this->handlePrefillNotice();

        return $this;
    }

    /**
     * Generate KCO request
     *
     * @param CartInterface $quote
     * @return array
     * @throws Exception
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function generateRequest(CartInterface $quote)
    {
        $this->parameter->resetOrderLines();
        $this->parameter->getOrderLineProcessor()
            ->processByQuote($this->parameter, $quote);

        $store  = $quote->getStore();
        $create = [
            'purchase_country'  => $this->directoryHelper->getDefaultCountry($store),
            'purchase_currency' => $quote->getBaseCurrencyCode(),
            'locale'            => $this->magentoToKlarnaLocaleMapper->getLocale($store)
        ];

        /**
         * Pre-fill customer details
         */
        if ($this->prefillConfiguration->isPrefillingCustomerDetailsEnabled($store)) {
            $create = $this->prefill($create, $quote, $store);
        }

        /**
         * GUI
         */
        $create['gui']['options'] = $this->getGuiOptions($store);

        /**
         * External payment methods
         */
        $create['external_payment_methods'] = $this->getExternalMethods(
            $this->checkoutConfiguration->getExternalPaymentMethodList($store),
            $store
        );

        /**
         * Options
         */
        $create['options'] = $this->getOptions($quote);

        /**
         * Merchant checkbox
         */
        $create['options']['additional_checkbox'] = $this->getMerchantCheckbox($store, $quote);

        /**
         * Shipping methods drop down
         */
        if ($this->shippingOptionsConfiguration->isShippingInIframe($store)) {
            $create['shipping_options'] = $this->getShippingMethods($quote);
        }

        /**
         * Totals
         */
        $address                    = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $create['order_amount']     = $this->dataConverter->toApiFloat($address->getBaseGrandTotal());
        $create['order_lines']      = $this->parameter->getOrderLines();
        $create['order_tax_amount'] = $this->dataConverter->toApiFloat($address->getBaseTaxAmount());

        if ($this->fptValidator->isFptUsable($store)) {
            $fptResult                  = $this->calculator->getFptData($quote);
            $create['order_tax_amount'] += $this->dataConverter->toApiFloat($fptResult['tax']);
        }

        $shippingCountries = $this->checkoutConfiguration->getShippingCountries($store);
        if (!empty($shippingCountries)) {
            $create['shipping_countries'] = $shippingCountries;
        }

        $isShippingBillingCountriesSame = $this->checkoutConfiguration->isSameBillingShippingCountry($store);
        if (!$isShippingBillingCountriesSame) {
            $billingCountries = $this->checkoutConfiguration->getBillingCountries($store);

            if (!empty($billingCountries)) {
                $create['billing_countries'] = $billingCountries;
            }
        }

        /**
         * Merchant reference
         */
        $merchantReferences = $this->parameter->getMerchantReferences($quote);

        if ($merchantReferences->getData('merchant_reference_1')) {
            $create['merchant_reference1'] = $merchantReferences->getData('merchant_reference_1');
        }

        if (!empty($merchantReferences['merchant_reference_2'])) {
            $create['merchant_reference2'] = $merchantReferences->getData('merchant_reference_2');
        }

        /**
         * Urls
         */
        $urlParams = [
            '_nosid'         => true,
            '_forced_secure' => true
        ];

        $create['merchant_urls'] = $this->processMerchantUrls($store, $urlParams);
        return $create;
    }

    /**
     * Populate prefill values
     *
     * @param array $create
     * @param CartInterface $quote
     * @param StoreInterface $store
     * @return mixed
     */
    public function prefill(array $create, CartInterface $quote, StoreInterface $store)
    {
        /**
         * Customer
         */
        $create['customer'] = $this->getCustomerData($quote);

        /**
         * Billing Address
         */
        $billingAddress = $this->parameter->getAddressData($quote, Address::TYPE_BILLING);
        if (!isset($billingAddress['errors'])) {
            $create['billing_address'] = $billingAddress;
        }

        /**
         * Shipping Address
         */
        if (isset($create['billing_address'])
            && $this->shippingOptionsConfiguration->isSeparateShippingAddressAllowed($store)
        ) {
            $shippingAddress = $this->parameter->getAddressData($quote, Address::TYPE_SHIPPING);
            if (!isset($shippingAddress['errors'])) {
                $create['shipping_address'] = $shippingAddress;
            }
        }
        return $create;
    }

    /**
     * Get customer details
     *
     * @param CartInterface $quote
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCustomerData(CartInterface $quote)
    {
        $store = $quote->getStore();
        $customerData = [];
        if (!$quote->getCustomerIsGuest()) {
            $customer = $quote->getCustomer();
            if ($this->b2b->isB2bCustomer($customer->getId(), $store)) {
                $customerData['type'] = 'organization';
                $organizationId = $this->b2b->getBusinessIdAttributeValue($customer->getId(), $store);
                if (!empty($organizationId)) {
                    $customerData['organization_registration_id'] = $organizationId;
                }
            }
        }
        if ($quote->getCustomerDob()) {
            $customerData = [
                'date_of_birth' => $this->coreDate->date('Y-m-d', $quote->getCustomerDob())
            ];
        }
        return $customerData;
    }

    /**
     * Get GUI options
     *
     * @param StoreInterface $store
     * @return array
     */
    public function getGuiOptions(StoreInterface $store)
    {
        if (!$this->checkoutConfiguration->isAutoFocusEnabled($store)) {
            return ['disable_autofocus'];
        }
        return null;
    }

    /**
     * Populate external payment methods array
     *
     * @param array $methods
     * @param StoreInterface $store
     * @return array
     */
    public function getExternalMethods(array $methods, $store)
    {
        if (empty($methods)) {
            return null;
        }
        $externalMethods = [];
        foreach ($methods as $externalMethod) {
            $methodDetails = $this->getExternalPaymentList($externalMethod, $store);
            if (!$methodDetails->isEmpty()) {
                $externalMethods[] = $methodDetails->toArray();
            }
        }
        if (count($externalMethods)) {
            return $externalMethods;
        }
        return null;
    }

    /**
     * Get list of external payments
     *
     * @param string         $code
     * @param StoreInterface $store
     *
     * @return DataObject
     */
    private function getExternalPaymentList($code, $store)
    {
        $options = $this->kcoKlarnaConfig->getExternalPaymentOptions($code);
        if ($options === null) {
            $options = [];
        }
        unset($options['label']);

        foreach ($options as $option => $value) {
            if (false !== stripos($option, 'url')) {
                $options[$option] = $this->checkoutUrl->getProcessedUrl($value, $store);
            }
            if (false !== stripos($option, 'description')) {
                $options[$option] = __($value);
            }
        }

        $options = array_filter($options);
        return $this->dataObjectFactory->create(
            [
                'data' => $options
            ]
        );
    }

    /**
     * Getting back the options
     *
     * @param CartInterface $quote
     * @return array
     * @throws Exception
     */
    public function getOptions(CartInterface $quote)
    {
        $store = $quote->getStore();
        $options = $this->checkoutConfiguration->getDesign($store);
        $options = array_merge($options, [
            'allow_separate_shipping_address'
                => $this->shippingOptionsConfiguration->isSeparateShippingAddressAllowed($store),
            'phone_mandatory'
                => $this->mandatoryFieldsConfiguration->isPhoneMandatory($store),
            'national_identification_number_mandatory'
                => $this->mandatoryFieldsConfiguration->isNationalIdentificationNumberMandatory($store),
            'date_of_birth_mandatory' => $this->mandatoryFieldsConfiguration->isDateOfBirthMandatory($store)
        ]);

        if ($quote->isVirtual() && $this->kssConfigProvider->isKssEnabled($quote->getStore())) {
            $options['allow_separate_shipping_address'] = false;
        }

        $options['require_validate_callback_success'] = true;
        $options['title_mandatory'] = $this->mandatoryFieldsConfiguration->isTitleMandatory($store);

        $options['shipping_in_iframe'] = $this->shippingOptionsConfiguration->isShippingInIframe($store);
        if ($this->checkoutConfiguration->isB2bEnabled($store)) {
            $options['allowed_customer_types'] = ['person', 'organization'];
        }

        if ($this->shippingOptionsConfiguration->isTmsConfigurationOverrideDisabled($store)) {
            $options['tms_configuration_override'] = ['disabled' => true];
        }

        $additionalCheckboxes = $this->dataProvider->getAdditionalCheckboxes($quote);
        if ($additionalCheckboxes && count($additionalCheckboxes) > 0) {
            $options['additional_checkboxes'] = $additionalCheckboxes;
        }

        return $options;
    }

    /**
     * Returns merchant checkbox if set
     *
     * NOTE: Use a plugin on this method to override.  In M1 this fired off kco_merchant_checkbox event
     *
     * @param StoreInterface $store
     * @param CartInterface $quote
     * @return array|null
     * @throws Exception
     */
    public function getMerchantCheckbox(StoreInterface $store, CartInterface $quote)
    {
        $checkboxMethod = $this->checkboxConfiguration->getOptions($store);
        if ($checkboxMethod === '-1') {
            return null;
        }
        if (!$this->validator->isMerchantCheckboxEnabled($checkboxMethod, ['quote' => $quote])) {
            return null;
        }
        $checkboxObj = $this->dataObjectFactory->create([
            'data' => [
                'text'     => $this->checkboxConfiguration->getText($store)
                    ?: $this->dataProvider->getMerchantCheckboxText($checkboxMethod),
                'checked'  => $this->checkboxConfiguration->isMerchantCheckboxCheckedByDefault($store),
                'required' => $this->checkboxConfiguration->isMerchantCheckboxRequiredToBeChecked($store)
            ]
        ]);

        if ($checkboxObj->getText()) {
            return $checkboxObj->toArray();
        }
        return null;
    }

    /**
     * Get available shipping methods for a quote for the api init
     *
     * @param MageQuote $quote
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getShippingMethods(MageQuote $quote): array
    {
        if ($quote->isVirtual()) {
            if ($this->kssConfigProvider->isKssEnabled($quote->getStore())) {
                $item = $this->getShippingMethodItem('id', 'name', 0);
                $item['shipping_method'] = 'Digital';
                return [$item];
            }

            return [];
        }

        $rates = $this->getRates($quote);

        $this->kcoSession->setQuote($quote);
        if ($this->kcoSession->hasActiveKlarnaShippingGatewayInformation()) {
            $shippingGateway = $this->kcoSession->getKlarnaShippingGateway();

            // $shippingGateway won't be null as it is checked for in hasActiveKlarnaShippingGatewayInformation
            /** @noinspection NullPointerExceptionInspection */
            $rates[] = $this->getShippingMethodItem(
                $shippingGateway->getShippingMethodId(),
                Klarna::GATEWAY_KEY,
                $this->dataConverter->toApiFloat($shippingGateway->getShippingAmount())
            );
        }

        return $rates;
    }

    /**
     * Getting back the rates of the quote. Default implementation.
     *
     * @param CartInterface $quote
     * @return array
     */
    private function getRates(CartInterface $quote): array
    {
        $shippingRates = $this->shippingMethodManagement->estimateByExtendedAddress(
            $quote->getId(),
            $quote->getShippingAddress()
        );

        $rates = [];
        /** @var \Magento\Quote\Model\Cart\ShippingMethod $rate */
        foreach ($shippingRates as $rate) {
            if (!$rate->getMethodCode() || !$rate->getMethodTitle()) {
                continue;
            }

            $methodCode = $rate->getCarrierCode() . '_' . $rate->getMethodCode();
            $method = $this->getShippingMethodItem(
                $methodCode,
                $rate->getMethodTitle(),
                $this->dataConverter->toApiFloat($rate->getPriceInclTax()),
                $rate->getCarrierTitle()
            );
            $method['preselected'] = $methodCode === $quote->getShippingAddress()->getShippingMethod();
            $rates[] = $method;
        }

        return $rates;
    }

    /**
     * Getting back the shipping method item
     *
     * @param string $id
     * @param string $name
     * @param int    $price
     * @param string $description
     * @return array
     */
    private function getShippingMethodItem($id, $name, $price, $description = ''): array
    {
        return [
            'id'          => $id,
            'name'        => $name,
            'price'       => $price,
            'promo'       => '',
            'tax_amount'  => 0,
            'tax_rate'    => 0,
            'description' => $description
        ];
    }

    /**
     * Pre-process Merchant URLs
     *
     * @param StoreInterface $store
     * @param array $urlParams
     * @return mixed
     */
    public function processMerchantUrls(StoreInterface $store, array $urlParams)
    {
        $merchant_urls = $this->dataObjectFactory->create([
            'data' => [
                'terms'              => $this->parameter->getTermsUrl($this->urlConfiguration->getTermsUrl($store)),
                'checkout'           => $this->url->getDirectUrl(CheckoutUrl::CHECKOUT_ACTION_PREFIX, $urlParams),
                'confirmation'       => $this->url->getDirectUrl(
                    CheckoutUrl::CHECKOUT_ACTION_PREFIX . '/confirmation/id/{checkout.order.id}',
                    $urlParams
                ),
                'push'               => $this->url->getDirectUrl(
                    CheckoutUrl::API_ACTION_PREFIX . '/push/id/{checkout.order.id}',
                    $urlParams
                ),
                'address_update'     => $this->url->getDirectUrl(
                    CheckoutUrl::API_ACTION_PREFIX . '/addressUpdate/id/{checkout.order.id}',
                    $urlParams
                ),
                'validation'         => $this->url->getDirectUrl(
                    CheckoutUrl::API_ACTION_PREFIX . '/validate/id/{checkout.order.id}',
                    $urlParams
                ),
                'notification'       => $this->url->getDirectUrl(
                    CheckoutUrl::API_ACTION_PREFIX . '/notification/id/{checkout.order.id}',
                    $urlParams
                ),
                'cancellation_terms' =>
                    $this->parameter->getTermsUrl($this->urlConfiguration->getCancellationTermsUrl($store))
            ]
        ]);

        $url_params = $this->dataObjectFactory->create(['data' => $urlParams]);

        $url_params = $url_params->toArray();
        if ($this->shippingOptionsConfiguration->isShippingInIframe($store)) {
            $merchant_urls->setShippingOptionUpdate($this->url->getDirectUrl(
                CheckoutUrl::API_ACTION_PREFIX . '/shippingMethodUpdate/id/{checkout.order.id}',
                $url_params
            ));
        }

        return $merchant_urls->toArray();
    }

    /**
     * Get request
     *
     * @return array
     */
    public function getRequest()
    {
        return $this->parameter->getRequest();
    }

    /**
     * Handle if a user accepts pre-fill terms
     *
     * @return void
     */
    private function handlePrefillNotice()
    {
        $checkoutSession = $this->kcoSession->getCheckout();

        /** @var Parameter $parameter */
        $parameter = $this->getParameter();
        $request = $parameter->getRequest();

        /** @var MageQuote $quote */
        $quote = $checkoutSession->getQuote();

        /** @var Store $store */
        $store = $quote->getStore();

        if ('accept' !== $checkoutSession->getKlarnaFillNoticeTerms()
            && $this->config->isPrefillNoticeEnabled($store)
        ) {
            unset($request['customer']);
            unset($request['shipping_address']);
            unset($request['billing_address']);
            $parameter->setRequest($request);
        }
    }

    /**
     * Update merchant options to enable Packstation in the request
     *
     * @return void
     */
    private function handlePackstationSettings()
    {
        $checkoutSession = $this->kcoSession->getCheckout();

        /** @var Parameter $parameter */
        $parameter = $this->getParameter();
        $request = $parameter->getRequest();

        /** @var MageQuote $quote */
        $quote = $checkoutSession->getQuote();

        /** @var Store $store */
        $store = $quote->getStore();

        if ($this->config->isPackstationEnabled($store)) {
            $request['options']['packstation_enabled'] = true;
            $request['options']['allow_separate_shipping_address'] = true;
            $parameter->setRequest($request);
        }
    }
}
