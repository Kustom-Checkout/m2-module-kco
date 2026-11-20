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
use Klarna\Kco\Model\Cart\Validations\Handler as ValidationHandler;
use Klarna\Kss\Api\ShippingMethodGatewayInterface;
use Klarna\Kss\Api\ShippingMethodGatewayRepositoryInterface;
use Klarna\Kss\Model\Carrier;
use Klarna\Kss\Model\ShippingMethodGatewayFactory;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\DataObject;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\OrderFactory as MagentoOrderFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use Magento\TestFramework\TestCase\AbstractController;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * KUSTOM-127: push with Kustom Shipping Service (KSS) active must still create the order with the amount
 * selected in Kustom, so the reuse of the shipping rate stored at validate is no regression for KSS
 */
class PushKssTest extends AbstractController
{
    private const KLARNA_ORDER_ID = '123456-1234-1234-1234-1234567890';
    private const KSS_METHOD_ID = 'kss-delivery-1';

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

        if (!$this->_objectManager->get(ModuleManager::class)->isEnabled('Klarna_Kss')) {
            $this->markTestSkipped('Klarna_Kss is not enabled');
        }

        $this->checkoutMock = $this->createMock(Checkout::class);
        $this->_objectManager->addSharedInstance($this->checkoutMock, Checkout::class);
        $this->orderManagementMock = $this->createMock(Ordermanagement::class);
        $this->_objectManager->addSharedInstance($this->orderManagementMock, Ordermanagement::class);
    }

    /**
     * Validate with KSS price 5 (flatrate also 5), then the live flatrate price changes to 10, then the push comes
     * in with the KSS price the customer selected (5). The KSS amount is read from the gateway row written from the
     * Kustom request, never from a live carrier, so the order must still get 5 whichever path FullUpdate takes
     *
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store payment/klarna_kss/enabled 1
     * @magentoConfigFixture current_store carriers/flatrate/price 5
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testExecuteShouldCreateOrderWithKssAmountSelectedInKustomWhenLiveCarrierPriceChanged(): void
    {
        $this->createActiveGateway(5.0);

        $quote = $this->_objectManager->create(GetQuoteByReservedOrderId::class)->execute('100000001');
        $quote->getShippingAddress()->setShippingMethod(Carrier::GATEWAY_KEY);
        $this->_objectManager->create(ValidationHandler::class)
            ->validateRequestObject($this->buildOrder('checkout_incomplete'), $quote);
        $this->_objectManager->get(CartRepositoryInterface::class)->save($quote);

        // The live carrier price changes after validate; KSS must not be affected, its amount comes from the gateway
        $this->_objectManager->get(MutableScopeConfigInterface::class)
            ->setValue('carriers/flatrate/price', 10, ScopeInterface::SCOPE_STORE);

        $this->checkoutMock->expects($this->any())->method('getOrder')
            ->willReturn($this->buildOrder('checkout_complete')->getData());
        $this->orderManagementMock->expects($this->any())->method('getOrder')
            ->willReturn(['order_id' => self::KLARNA_ORDER_ID, 'klarna_reference' => '12345']);
        $this->orderManagementMock->expects($this->any())->method('updateMerchantReferences')
            ->willReturn([]);
        $this->orderManagementMock->expects($this->any())->method('acknowledgeOrder')
            ->willReturn(['is_successful' => true]);

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('kco/api/push/id/' . self::KLARNA_ORDER_ID);
        $this->assertEquals('[]', $this->getResponse()->getBody());

        $klarnaOrder = $this->_objectManager->create(KlarnaOrderFactory::class)->create()
            ->load(self::KLARNA_ORDER_ID, 'klarna_order_id');
        $this->assertNotNull($klarnaOrder->getId(), 'Kustom order row exists');
        $this->assertEquals('1', $klarnaOrder->getIsAcknowledged());

        $order = $this->_objectManager->create(MagentoOrderFactory::class)->create()
            ->load($klarnaOrder->getOrderId());
        $this->assertSame('100000001', $order->getIncrementId());
        $this->assertEquals(5, $order->getShippingAmount());
        $this->assertEquals(15, $order->getGrandTotal());
        $this->assertSame(Carrier::GATEWAY_KEY, $order->getShippingMethod());
    }

    /**
     * @param float $amount
     * @return ShippingMethodGatewayInterface
     */
    private function createActiveGateway(float $amount): ShippingMethodGatewayInterface
    {
        $gateway = $this->_objectManager->create(ShippingMethodGatewayFactory::class)->create();
        $gateway->setKlarnaSessionId(self::KLARNA_ORDER_ID);
        $gateway->setIsActive(true);
        $gateway->setShippingMethodId(self::KSS_METHOD_ID);
        $gateway->setName('KSS delivery');
        $gateway->setShippingAmount($amount);
        $gateway->setTaxAmount(0);
        $gateway->setTaxRate(0);
        $gateway->setPickUpPointFlag(false);
        $gateway->setPickUpPointName('');
        $this->_objectManager->get(ShippingMethodGatewayRepositoryInterface::class)->save($gateway);

        return $gateway;
    }

    /**
     * @param string $status
     * @return DataObject
     */
    private function buildOrder(string $status): DataObject
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

        return new DataObject([
            'order_id' => self::KLARNA_ORDER_ID,
            'is_successful' => true,
            'status' => $status,
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
            ], [
                'name' => 'KSS delivery',
                'quantity' => 1,
                'reference' => self::KSS_METHOD_ID,
                'tax_rate' => 0,
                'total_amount' => 500,
                'total_discount_amount' => 0,
                'total_tax_amount' => 0,
                'type' => 'shipping_fee',
                'unit_price' => 500,
            ]],
            'selected_shipping_option' => [
                'id' => self::KSS_METHOD_ID,
                'name' => 'KSS delivery',
                'shipping_method' => 'Home',
                'price' => 500,
                'tax_amount' => 0,
                'tax_rate' => 0,
                'delivery_details' => [],
            ],
            'order_amount' => 1500,
        ]);
    }
}
