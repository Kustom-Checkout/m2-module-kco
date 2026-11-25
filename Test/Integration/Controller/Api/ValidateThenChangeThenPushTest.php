<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Controller\Api;

use Klarna\Backend\Model\Api\Rest\Service\Ordermanagement;
use Klarna\Base\Model\OrderFactory as KlarnaOrderFactory;
use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Sales\Model\OrderFactory as MagentoOrderFactory;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use Magento\TestFramework\TestCase\AbstractController;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * KUSTOM-128 regression: validate (200), a refused shipping method change, then the push
 * creates the order with the shipping method accepted at validate.
 *
 * @covers \Klarna\Kco\Controller\Api\Validate
 * @covers \Klarna\Kco\Controller\Klarna\SaveShippingMethod
 * @covers \Klarna\Kco\Controller\Api\Push
 */
class ValidateThenChangeThenPushTest extends AbstractController
{
    private const KLARNA_ORDER_ID = '123456-1234-1234-1234-1234567890';
    private const VALIDATED_METHOD = 'flatrate_flatrate';
    private const OTHER_METHOD = 'freeshipping_freeshipping';

    /**
     * @var Checkout|MockObject
     */
    private $checkoutMock;

    /**
     * @var Ordermanagement|MockObject
     */
    private $orderManagementMock;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->checkoutMock = $this->createMock(Checkout::class);
        $this->_objectManager->addSharedInstance($this->checkoutMock, Checkout::class);
        $this->orderManagementMock = $this->createMock(Ordermanagement::class);
        $this->_objectManager->addSharedInstance($this->orderManagementMock, Ordermanagement::class);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store carriers/flatrate/price 5
     * @magentoConfigFixture current_store carriers/freeshipping/active 1
     * @magentoConfigFixture current_store carriers/freeshipping/free_shipping_subtotal 0
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testPushCreatesOrderWithShippingMethodAcceptedAtValidate(): void
    {
        $quote = $this->_objectManager->get(GetQuoteByReservedOrderId::class)->execute('100000001');
        $quoteId = (int) $quote->getId();
        $this->_objectManager->get(CheckoutSession::class)->replaceQuote($quote);

        // 1. validate answers 200 and sets the marker
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->getRequest()->setContent(json_encode($this->buildCheckoutOrder()));
        $this->dispatch('kco/api/validate/id/' . self::KLARNA_ORDER_ID);
        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());

        // 2. the customer changes the shipping method inside the block window: refused, quote unchanged
        $this->resetRequestAndResponse('checkout/klarna/saveShippingMethod');
        $this->getRequest()->setMethod(Http::METHOD_POST);
        [$carrier, $method] = explode('_', self::OTHER_METHOD);
        $this->getRequest()->setContent(json_encode(['carrier_code' => $carrier, 'method_code' => $method]));
        $this->dispatch('checkout/klarna/saveShippingMethod');
        $body = json_decode($this->getResponse()->getBody(), true);
        $this->assertTrue($body['locked'] ?? false);
        $this->assertEquals(self::VALIDATED_METHOD, $this->loadShippingMethod($quoteId));

        // 3. the push creates the order with the validated shipping method and amount
        $this->setUpPushStubs();
        $this->resetRequestAndResponse('kco/api/push/id/' . self::KLARNA_ORDER_ID);
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('kco/api/push/id/' . self::KLARNA_ORDER_ID);
        $this->assertEquals('[]', $this->getResponse()->getBody());

        $kOrder = $this->_objectManager->create(KlarnaOrderFactory::class)->create()
            ->load(self::KLARNA_ORDER_ID, 'klarna_order_id');
        $this->assertNotEmpty($kOrder->getOrderId());
        $order = $this->_objectManager->create(MagentoOrderFactory::class)->create()
            ->load($kOrder->getOrderId());
        $this->assertEquals(self::VALIDATED_METHOD, $order->getShippingMethod());
        $this->assertEquals(5, (float) $order->getShippingAmount());
        $this->assertEquals(15, (float) $order->getGrandTotal());
    }

    /**
     * Several dispatches in one test: the app (Http, FrontController) keeps the request and response objects it was
     * built with, so they cannot be swapped. Instead the route, the body and the response of the previous dispatch
     * are cleared on the same objects before every further dispatch (the path info is cached by the request, so
     * it is set explicitly).
     *
     * @param string $path
     * @return void
     */
    private function resetRequestAndResponse(string $path): void
    {
        $this->getRequest()->setRouteName(null)
            ->setModuleName(null)
            ->setControllerName(null)
            ->setActionName(null)
            ->setDispatched(false)
            ->setPathInfo('/' . $path)
            ->setContent('');
        $this->getResponse()->clearBody();
        $this->getResponse()->clearHeaders();
        $this->getResponse()->setHttpResponseCode(200);
    }

    /**
     * @return void
     */
    private function setUpPushStubs(): void
    {
        $this->checkoutMock->method('getOrder')->willReturn(
            array_merge($this->buildCheckoutOrder(), ['is_successful' => true, 'status' => 'checkout_complete'])
        );
        $this->orderManagementMock->method('getOrder')->willReturn([
            'order_id' => self::KLARNA_ORDER_ID,
            'klarna_reference' => '12345',
        ]);
        $this->orderManagementMock->method('updateMerchantReferences')->willReturn([]);
        $this->orderManagementMock->method('acknowledgeOrder')->willReturn(['is_successful' => true]);
    }

    /**
     * Read straight from the database
     *
     * @param int $quoteId
     * @return string|null
     */
    private function loadShippingMethod(int $quoteId): ?string
    {
        $quote = $this->_objectManager->create(QuoteFactory::class)->create();
        $this->_objectManager->get(QuoteResource::class)->load($quote, $quoteId);

        return $quote->getShippingAddress()->getShippingMethod();
    }

    /**
     * @return array
     */
    private function buildCheckoutOrder(): array
    {
        $address = [
            'city' => 'City',
            'country' => 'US',
            'email' => 'customer@example.com',
            'family_name' => 'Lastname',
            'given_name' => 'Firstname',
            'phone' => '040123456',
            'postal_code' => '12345',
            'street_address' => 'Street',
            'region' => 'California',
        ];

        return [
            'order_id' => self::KLARNA_ORDER_ID,
            'status' => 'checkout_incomplete',
            'billing_address' => $address,
            'shipping_address' => $address,
            'order_lines' => [[
                'image_url' => '',
                'name' => 'Simple Product',
                'product_url' => 'http://localhost/index.php/simple-product.html',
                'quantity' => 1,
                'reference' => 'simple',
                'tax_rate' => 0,
                'total_amount' => 1000,
                'total_discount_amount' => 0,
                'total_tax_amount' => 0,
                'type' => 'physical',
                'unit_price' => 1000,
            ]],
            'selected_shipping_option' => [
                'id' => self::VALIDATED_METHOD,
                'price' => 500,
                'tax_amount' => 0,
                'tax_rate' => 0,
            ],
            'order_amount' => 1500,
        ];
    }
}
