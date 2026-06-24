<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

namespace Klarna\Kco\Test\Unit\Model\Api\Builder;

use Klarna\Base\Model\Api\OrderLineProcessor;
use Klarna\Kco\Model\Api\Builder\Kasper;
use Klarna\Base\Test\Unit\Mock\MockFactory;
use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magento\Quote\Model\Cart\ShippingMethod;
use Magento\Framework\DataObjectFactory;
use Magento\Customer\Model\Data\Customer;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Api\Builder\Kasper
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class KasperTest extends TestCase
{
    /**
     * @var MockFactory
     */
    private $mockFactory;
    /**
     * @var Kasper
     */
    private $model;

    /**
     * @var MockObject[]
     */
    private $dependencyMocks;

    /**
     * @var Store|MockObject
     */
    private $storeMock;

    /**
     * @var Quote|MockObject
     */
    private $quoteMock;

    /**
     * @var Rate|MockObject
     */
    private $shippingMethodMock;

    /**
     * @var Session|MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var array
     */
    private $request;

    /**
     * @covers ::getGuiOptions
     */
    public function testGetGuiOptionValueIsNull(): void
    {
        $this->dependencyMocks['checkoutConfiguration']->expects(static::once())
            ->method('isAutoFocusEnabled')
            ->willReturn(true);

        static::assertNull($this->model->getGuiOptions($this->storeMock));
    }

    /**
     * @covers ::getGuiOptions
     */
    public function testGetGuiOptionValueWithKeyValue(): void
    {
        $guiOptions = ['disable_autofocus'];

        $this->dependencyMocks['checkoutConfiguration']->expects(static::once())
            ->method('isAutoFocusEnabled')
            ->willReturn(false);

        static::assertEquals($guiOptions, $this->model->getGuiOptions($this->storeMock));
    }

    /**
     * @covers ::getExternalMethods
     */
    public function testGetExternalMethodsFromEmptyString(): void
    {
        static::assertNull($this->model->getExternalMethods([], $this->storeMock));
    }

    /**
     * @covers ::getExternalMethods
     * @covers ::getExternalPaymentList
     */
    public function testGetExternalMethodsFromInvalidString(): void
    {
        $dataObject = $this->mockFactory->create(DataObject::class);
        $dataObject->method('isEmpty')
            ->willReturn(true);
        $this->dependencyMocks['dataObjectFactory']
            ->method('create')
            ->willReturn($dataObject);
        static::assertNull($this->model->getExternalMethods(['not a method'], $this->storeMock));
    }

    /**
     * @covers ::getExternalMethods
     * @covers ::getExternalPaymentList
     */
    public function testGetExternalMethodsFromValidStructure(): void
    {
        $method                  = ['PayPal'];
        $externalPaymentOptions  = [
            'label'        => 'PayPal Express',
            'name'         => 'PayPal',
            'redirect_url' => 'https://',
            'image_url'    => 'https://',
            'fee'          => 0
        ];
        $externalPaymentOptionsDataObject = [
            'name' => $externalPaymentOptions['name'],
            'redirect_url' => $externalPaymentOptions['redirect_url'],
            'image_url' => $externalPaymentOptions['image_url']
        ];
        $expectedExternalMethods = [
            [
                'name'         => 'PayPal',
                'redirect_url' => 'https://',
                'image_url'    => 'https://'
            ]
        ];

        $this->dependencyMocks['kcoKlarnaConfig']->expects(static::once())
            ->method('getExternalPaymentOptions')
            ->with($method[0])
            ->willReturn($externalPaymentOptions);
        $dataObject = $this->mockFactory->create(DataObject::class);
        $dataObject->method('isEmpty')
            ->willReturn(false);
        $dataObject->method('toArray')
            ->willReturn($externalPaymentOptionsDataObject);
        $this->dependencyMocks['dataObjectFactory']
            ->method('create')
            ->willReturn($dataObject);

        static::assertEquals($expectedExternalMethods, $this->model->getExternalMethods($method, $this->storeMock));
    }

    /**
     * @covers ::getOptions
     */
    public function testGetOptionsWithTitleMandatory(): void
    {
        $this->dependencyMocks['mandatoryFieldsConfiguration']->method('isTitleMandatory')
            ->willReturn(true);

        $options = $this->model->getOptions($this->quoteMock);
        static::assertTrue($options['title_mandatory']);
    }

    /**
     * @covers ::getOptions
     */
    public function testGetOptionsWithPhoneMandatory(): void
    {
        $this->dependencyMocks['mandatoryFieldsConfiguration']->method('isPhoneMandatory')
            ->willReturn(true);

        $options = $this->model->getOptions($this->quoteMock);
        static::assertTrue($options['phone_mandatory']);
    }

    /**
     * @covers ::getOptions
     */
    public function testGetOptionsWithAllowedCustomerTypes(): void
    {
        $this->dependencyMocks['checkoutConfiguration']->expects(static::once())
            ->method('isB2bEnabled')
            ->willReturn(true);

        $options = $this->model->getOptions($this->quoteMock);
        static::assertEquals(['person', 'organization'], $options['allowed_customer_types']);
    }

    /**
     * @covers ::getOptions
     */
    public function testGetOptionsWithAdditionalCheckboxes(): void
    {
        $checkboxes = [
            [
                'id'       => 'foo',
                'text'     => 'bar',
                'checked'  => false,
                'required' => false
            ]
        ];

        $this->dependencyMocks['dataProvider']->expects(static::once())
            ->method('getAdditionalCheckboxes')
            ->willReturn($checkboxes);

        $options = $this->model->getOptions($this->quoteMock);
        static::assertEquals($checkboxes, $options['additional_checkboxes']);
    }

    /**
     * @covers ::getMerchantCheckbox
     */
    public function testGetMerchantCheckboxButNotSupported(): void
    {
        static::assertNull($this->model->getMerchantCheckbox($this->storeMock, $this->quoteMock));
    }

    /**
     * @covers ::getMerchantCheckbox
     */
    public function testGetMerchantCheckboxButDisabledInConfiguration(): void
    {
        $this->dependencyMocks['checkboxConfiguration']->expects(static::once())
            ->method('getOptions')
            ->willReturn('-1');

        static::assertNull($this->model->getMerchantCheckbox($this->storeMock, $this->quoteMock));
    }

    /**
     * @covers ::getMerchantCheckbox
     */
    public function testGetMerchantCheckboxButDisabledForMethod(): void
    {
        $this->dependencyMocks['checkboxConfiguration']->expects(static::once())
            ->method('getOptions')
            ->willReturn('1');
        $this->dependencyMocks['validator']->expects(static::once())
            ->method('isMerchantCheckboxEnabled')
            ->willReturn(false);

        static::assertNull($this->model->getMerchantCheckbox($this->storeMock, $this->quoteMock));
    }

    /**
     * @covers ::getShippingMethods
     */
    public function testGetShippingMethodsFromVirtualQuote(): void
    {
        $this->quoteMock->expects(static::once())
            ->method('isVirtual')
            ->willReturn(true);
        $this->quoteMock->expects(static::once())
            ->method('getStore')
            ->willReturn($this->storeMock);

        static::assertEquals([], $this->model->getShippingMethods($this->quoteMock));
    }

    /**
     * @covers ::getShippingMethods
     */
    public function testGetShippingMethodsFromVirtualQuoteWithKssEnabled(): void
    {
        $this->quoteMock->expects(static::once())
            ->method('isVirtual')
            ->willReturn(true);
        $this->quoteMock->expects(static::once())
            ->method('getStore')
            ->willReturn($this->storeMock);
        $this->dependencyMocks['kssConfigProvider']->expects(static::once())
            ->method('isKssEnabled')
            ->willReturn(true);

        static::assertEquals('Digital', $this->model->getShippingMethods($this->quoteMock)[0]['shipping_method']);
    }

    /**
     * @covers ::getShippingMethods
     */
    public function testGetShippingMethodsWithNoRatesInAddress(): void
    {
        $this->dependencyMocks['shippingMethodManagement']->method('estimateByExtendedAddress')
            ->willReturn([]);

        static::assertEquals([], $this->model->getShippingMethods($this->quoteMock));
    }

    /**
     * @covers ::getShippingMethods
     */
    public function testGetShippingMethodsWithInvalidRates(): void
    {
        $rates = [
            $this->mockFactory->create(ShippingMethod::class)
        ];
        $this->dependencyMocks['shippingMethodManagement']->method('estimateByExtendedAddress')
            ->willReturn($rates);

        static::assertEquals([], $this->model->getShippingMethods($this->quoteMock));
    }

    /**
     * @covers ::getShippingMethods
     */
    public function testGetShippingMethodsWithValidRates(): void
    {
        $addressMock = $this->mockFactory->create(Address::class);
        $this->quoteMock->method('getShippingAddress')->willReturn($addressMock);

        $shippingMethod = $this->mockFactory->create(ShippingMethod::class);
        $shippingMethod->method('getMethodCode')->willReturn('method');
        $shippingMethod->method('getMethodTitle')->willReturn('Fixed');
        $shippingMethod->method('getCarrierCode')->willReturn('carrier');

        $rates = [
            $shippingMethod
        ];
        $this->dependencyMocks['shippingMethodManagement']->method('estimateByExtendedAddress')
            ->willReturn($rates);

        static::assertEquals('carrier_method', $this->model->getShippingMethods($this->quoteMock)[0]['id']);
    }

    /**
     * @covers ::generateCreateRequest
     */
    public function testGenerateCreateRequestHandlePrefillNoticeAddressNotRemoved()
    {
        $this->checkHandlePrefillNoticeAddressNotRemovedFromRequest('generateCreateRequest');
    }

    /**
     * @covers ::generateCreateRequest
     */
    public function testGenerateCreateRequestHandlePrefillNoticeAddressRemoved()
    {
        $this->checkHandlePrefillNoticeAddressRemovedFromRequest('generateCreateRequest');
    }

    /**
     * @covers ::generateCreateRequest
     */
    public function testAfterGenerateCreateRequestPackstationSettingsNotAdded()
    {
        $this->setUpGenerateMethodMocks();

        $this->dependencyMocks['config']
            ->method('isPackstationEnabled')
            ->willReturn(false);

        $this->model->generateCreateRequest($this->quoteMock);

        $request = $this->model->getParameter()->getRequest();
        $this->assertArrayNotHasKey('packstation_enabled', $request['options']);
    }

    /**
     * @covers ::generateUpdateRequest
     */
    public function testGenerateUpdateRequestHandlePrefillNoticeAddressNotRemoved()
    {
        $this->checkHandlePrefillNoticeAddressNotRemovedFromRequest('generateUpdateRequest');
    }

    /**
     * @covers ::generateUpdateRequest
     */
    public function testGenerateUpdateRequestHandlePrefillNoticeAddressRemoved()
    {
        $this->checkHandlePrefillNoticeAddressRemovedFromRequest('generateUpdateRequest');
    }

    /**
     * @covers ::generatePlaceOrderRequest
     */
    public function testGeneratePlaceOrderRequestHandlePrefillNoticeAddressNotRemoved()
    {
        $this->checkHandlePrefillNoticeAddressNotRemovedFromRequest('generatePlaceOrderRequest');
    }

    /**
     * @covers ::generatePlaceOrderRequest
     */
    public function testGeneratePlaceOrderRequestHandlePrefillNoticeAddressRemoved()
    {
        $this->checkHandlePrefillNoticeAddressRemovedFromRequest('generatePlaceOrderRequest');
    }

    private function checkHandlePrefillNoticeAddressNotRemovedFromRequest($method)
    {
        $this->setUpGenerateMethodMocks();

        $this->checkoutSessionMock
            ->method('getKlarnaFillNoticeTerms')
            ->willReturn('accept');

        $this->dependencyMocks['prefillConfiguration']->method('isPrefillingCustomerDetailsEnabled')
            ->willReturn(true);
        $this->dependencyMocks['shippingOptionsConfiguration']->method('isSeparateShippingAddressAllowed')
            ->willReturn(true);
        $this->model->$method($this->quoteMock);

        $request = $this->model->getParameter()->getRequest();
        $this->assertArrayHasKey('customer', $request);
        $this->assertArrayHasKey('shipping_address', $request);
        $this->assertArrayHasKey('billing_address', $request);
    }

    private function checkHandlePrefillNoticeAddressRemovedFromRequest($method)
    {
        $this->setUpGenerateMethodMocks();

        $this->checkoutSessionMock
            ->method('getKlarnaFillNoticeTerms')
            ->willReturn('not_accept');

        $this->dependencyMocks['config']
            ->method('isPrefillNoticeEnabled')
            ->willReturn(true);

        $this->model->$method($this->quoteMock);

        $request = $this->model->getParameter()->getRequest();
        $this->assertArrayNotHasKey('customer', $request);
        $this->assertArrayNotHasKey('shipping_address', $request);
        $this->assertArrayNotHasKey('billing_address', $request);
    }

    private function setUpGenerateMethodMocks()
    {
        $orderLineProcessorMock = $this->mockFactory->create(OrderLineProcessor::class);
        $addressMock = $this->mockFactory->create(Address::class);
        $merchantReferencesMock = $this->mockFactory->create(DataObject::class);
        $urlParamsMock = $this->mockFactory->create(DataObject::class);

        $this->quoteMock
            ->method('getStore')
            ->willReturn($this->storeMock);

        $this->quoteMock
            ->method('getShippingAddress')
            ->willReturn($addressMock);

        $this->dependencyMocks['parameter']
            ->method('getOrderLineProcessor')
            ->willReturn($orderLineProcessorMock);

        $this->dependencyMocks['parameter']
            ->method('getMerchantReferences')
            ->willReturn($merchantReferencesMock);

        $this->dependencyMocks['dataObjectFactory']
            ->method('create')
            ->willReturn($urlParamsMock);

        $this->dependencyMocks['kcoSession']
            ->method('getCheckout')
            ->willReturn($this->checkoutSessionMock);

        $this->dependencyMocks['parameter']
            ->method('setRequest')
            ->willReturnCallback(function (array $request) {
                $this->request = $request;
            });

        $this->dependencyMocks['parameter']
            ->method('getRequest')
            ->willReturnCallback(function () {
                return $this->request;
            });

        $this->dependencyMocks['parameter']
            ->method('getAddressData')
            ->willReturn([]);
    }

    /**
     * @return Session|MockObject
     */
    private function createCheckoutSessionMock()
    {
        $session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->addMethods(['getKlarnaFillNoticeTerms'])
            ->onlyMethods(['getQuote'])
            ->getMock();

        $session->method('getQuote')->willReturnCallback(function () {
            return $this->quoteMock;
        });

        return $session;
    }

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $this->mockFactory = new MockFactory($this);
        $objectFactory     = new TestObjectFactory('');

        $this->storeMock          = $this->mockFactory->create(Store::class);
        $this->quoteMock          = $this->mockFactory->create(Quote::class);
        $this->quoteMock->method('getStore')
            ->willReturn($this->storeMock);

        $customer = $this->mockFactory->create(Customer::class);
        $customer->method('getId')
            ->willReturn('1');
        $this->quoteMock->method('getCustomer')
            ->willReturn($customer);

        $this->shippingMethodMock = $this->mockFactory->create(Rate::class, [], [
            'getCode'
        ]);
        $this->checkoutSessionMock = $this->createCheckoutSessionMock();

        $ShippingMethodManagement = $this->mockFactory->create(
            ShippingMethodManagementInterface::class,
            [
                'estimateByAddress',
                'estimateByAddressId',
                'getList'
            ],
            [
                'estimateByExtendedAddress',
            ]
        );

        $this->model = $objectFactory->create(
            Kasper::class,
            [
                DataObjectFactory::class => [
                    'create'
                ]
            ],
            [
                ShippingMethodManagementInterface::class => $ShippingMethodManagement
            ]
        );
        $this->dependencyMocks = $objectFactory->getDependencyMocks();
        $this->dependencyMocks['checkoutConfiguration']->method('getDesign')
            ->willReturn([]);
    }
}
