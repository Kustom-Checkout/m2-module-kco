<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Controller\Klarna;

use Klarna\Backend\Model\Api\Rest\Service\Ordermanagement;
use Klarna\Base\Exception;
use Klarna\Base\Model\OrderFactory as KlarnaOrderFactory;
use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Quote\Model\CartMutex;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Model\OrderFactory as MagentoOrderFactory;
use Magento\TestFramework\TestCase\AbstractController;
use PHPUnit\Framework\MockObject\MockObject;

class ConfirmationTest extends AbstractController
{
    /**
     * @var KlarnaOrderFactory
     */
    private $kOrderFactory;

    /**
     * @var MagentoOrderFactory
     */
    private $mOrderFactory;

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

        $this->kOrderFactory = $this->_objectManager->create(KlarnaOrderFactory::class);
        $this->mOrderFactory = $this->_objectManager->create(MagentoOrderFactory::class);
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
     * @magentoConfigFixture current_store klarna/api/debug 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testExecuteShouldSuccessfullyCreateOrderByCheckoutApiResponse(): void
    {
        $expectedRedirect = 'checkout/klarna/success';
        $expectedMessages = [];
        $klarnaOrderId = '123456-1234-1234-1234-1234567890';

        $this->assertOrderData(
            $klarnaOrderId,
            [],
            [],
            []
        );

        $this->checkoutMock->expects($this->any())->method('getOrder')
            ->willReturn([
                'billing_address' => [
                    'city' => 'City',
                    'country' => 'US',
                    'email' => 'customer@example.com',
                    'family_name' => 'Lastname',
                    'given_name' => 'Firstname',
                    'phone' => '040123456',
                    'postal_code' => '12345',
                    'street_address' => 'Street',
                    'region' => 'California',
                ],
                'shipping_address' => [
                    'city' => 'City',
                    'country' => 'US',
                    'email' => 'customer@example.com',
                    'family_name' => 'Lastname',
                    'given_name' => 'Firstname',
                    'phone' => '040123456',
                    'postal_code' => '12345',
                    'street_address' => 'Street',
                    'region' => 'California',
                ],
                'order_id' => $klarnaOrderId,
                'is_successful' => true,
                'order_lines' => [
                    [
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
                    ]
                ],
                'selected_shipping_option' => [
                    'id' => 'flatrate_flatrate',
                    'price' => 500,
                    'tax_amount' => 0,
                    'tax_rate' => 0,
                ],
                'order_amount' => 1500,
                'status' => 'checkout_complete',
            ]);

        $this->getRequest()->setMethod(Http::METHOD_GET);
        $this->dispatch('checkout/klarna/confirmation/id/' . $klarnaOrderId);
        $this->assertRedirect($this->stringContains($expectedRedirect));
        $this->assertSessionMessages($this->equalTo($expectedMessages));

        $this->assertOrderData(
            $klarnaOrderId,
            [
                'klarna_order_id' => $klarnaOrderId,
                'is_acknowledged' => '0',
            ],
            [
                'state' => 'processing',
                'status' => 'processing',
                'increment_id' => '100000001',
            ],
            [
                'additional_information' => [
                    'method_title' => 'Kustom Checkout',
                ],
            ]
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testExecuteShouldJustRedirectUserToSuccessPageWhenCartIsLocked(): void
    {
        $expectedRedirect = 'checkout/klarna/success';
        $expectedMessages = [];
        $klarnaOrderId = '123456-1234-1234-1234-1234567890';

        $this->assertOrderData(
            $klarnaOrderId,
            [],
            [],
            []
        );

        // MTF overrides lockers with a dummy so this is one way to trigger lock error
        $lockManagerMock = $this->createMock(LockManagerInterface::class);
        $cartMutex = $this->_objectManager->create(CartMutex::class, ['lockManager' => $lockManagerMock]);
        $this->_objectManager->addSharedInstance($cartMutex, CartMutex::class);
        $quoteManagement = $this->_objectManager->create(QuoteManagement::class, [
            'cartMutex' => $cartMutex,
        ]);
        $this->_objectManager->addSharedInstance($quoteManagement, QuoteManagement::class);
        $lockManagerMock->expects($this->any())->method('lock')->willReturn(false);

        $this->checkoutMock->expects($this->any())->method('getOrder')
            ->willReturn([
                'billing_address' => [
                    'city' => 'City',
                    'country' => 'US',
                    'email' => 'customer@example.com',
                    'family_name' => 'Lastname',
                    'given_name' => 'Firstname',
                    'phone' => '040123456',
                    'postal_code' => '12345',
                    'street_address' => 'Street',
                    'region' => 'California',
                ],
                'shipping_address' => [
                    'city' => 'City',
                    'country' => 'US',
                    'email' => 'customer@example.com',
                    'family_name' => 'Lastname',
                    'given_name' => 'Firstname',
                    'phone' => '040123456',
                    'postal_code' => '12345',
                    'street_address' => 'Street',
                    'region' => 'California',
                ],
                'order_id' => $klarnaOrderId,
                'is_successful' => true,
                'order_lines' => [
                    [
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
                    ]
                ],
                'selected_shipping_option' => [
                    'id' => 'flatrate_flatrate',
                    'price' => 500,
                    'tax_amount' => 0,
                    'tax_rate' => 0,
                ],
                'order_amount' => 1500,
                'status' => 'checkout_complete',
            ]);

        $this->getRequest()->setMethod(Http::METHOD_GET);
        $this->dispatch('checkout/klarna/confirmation/id/' . $klarnaOrderId);
        $this->assertRedirect($this->stringContains($expectedRedirect));
        $this->assertSessionMessages($this->equalTo($expectedMessages));

        $this->assertOrderData(
            $klarnaOrderId,
            [],
            [],
            []
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/klarna_order_setup1_single_simple_product.php
     */
    public function testExecuteShouldJustRedirectUserToSuccessPageWhenOrderAlreadyExists(): void
    {
        $expectedRedirect = 'checkout/klarna/success';
        $expectedMessages = [];
        $klarnaOrderId = '123456-1234-1234-1234-1234567890';

        $this->assertOrderData(
            $klarnaOrderId,
            [
                'klarna_order_id' => $klarnaOrderId,
                'is_acknowledged' => '0',
            ],
            [
                'state' => 'new',
                'status' => 'pending',
                'increment_id' => '100000001',
            ],
            [
                'additional_information' => [
                    'method_title' => 'Check / Money order',
                ],
            ]
        );

        $this->checkoutMock->expects($this->any())->method('getOrder')
            ->willReturn([
                'billing_address' => [
                    'city' => 'City',
                    'country' => 'US',
                    'email' => 'customer@example.com',
                    'family_name' => 'Lastname',
                    'given_name' => 'Firstname',
                    'phone' => '040123456',
                    'postal_code' => '12345',
                    'street_address' => 'Street',
                    'region' => 'California',
                ],
                'shipping_address' => [
                    'city' => 'City',
                    'country' => 'US',
                    'email' => 'customer@example.com',
                    'family_name' => 'Lastname',
                    'given_name' => 'Firstname',
                    'phone' => '040123456',
                    'postal_code' => '12345',
                    'street_address' => 'Street',
                    'region' => 'California',
                ],
                'order_id' => $klarnaOrderId,
                'is_successful' => true,
                'order_lines' => [
                    [
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
                    ]
                ],
                'selected_shipping_option' => [
                    'id' => 'flatrate_flatrate',
                    'price' => 500,
                    'tax_amount' => 0,
                    'tax_rate' => 0,
                ],
                'order_amount' => 1500,
                'status' => 'checkout_complete',
            ]);

        $this->getRequest()->setMethod(Http::METHOD_GET);
        $this->dispatch('checkout/klarna/confirmation/id/' . $klarnaOrderId);
        $this->assertRedirect($this->stringContains($expectedRedirect));
        $this->assertSessionMessages($this->equalTo($expectedMessages));

        $this->assertOrderData(
            $klarnaOrderId,
            [
                'klarna_order_id' => $klarnaOrderId,
                'is_acknowledged' => '0',
            ],
            [
                'state' => 'new',
                'status' => 'pending',
                'increment_id' => '100000001',
            ],
            [
                'additional_information' => [
                    'method_title' => 'Check / Money order',
                ],
            ]
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testExecuteShouldThrowAnErrorWhenIdMatchesNothing(): void
    {
        $expectedRedirect = 'checkout/cart';
        $expectedMessages = ['No Kustom Kco quote could be found with the provided Kustom order id: 123456-1234-1234-1234-1234567890'];
        $klarnaOrderId = '123456-1234-1234-1234-1234567890';

        $this->assertOrderData(
            $klarnaOrderId,
            [],
            [],
            []
        );

        $this->getRequest()->setMethod(Http::METHOD_GET);
        $this->dispatch('checkout/klarna/confirmation/id/' . $klarnaOrderId);
        $this->assertRedirect($this->stringContains($expectedRedirect));
        $this->assertSessionMessages($this->equalTo($expectedMessages));
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testExecuteShouldThrowAnErrorWhenNoIdIsGiven(): void
    {
        $expectedRedirect = 'checkout/cart';
        $expectedMessages = ['Unable to process order. Please try again'];

        $this->getRequest()->setMethod(Http::METHOD_GET);
        $this->dispatch('checkout/klarna/confirmation/id/');
        $this->assertRedirect($this->stringContains($expectedRedirect));
        $this->assertSessionMessages($this->equalTo($expectedMessages));
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testExecuteShouldNotCancelOrderDueToLocalizedException(): void
    {
        $expectedRedirect = 'checkout/cart';
        $expectedMessages = ['Test error'];
        $klarnaOrderId = '123456-1234-1234-1234-1234567890';

        $this->assertOrderData(
            $klarnaOrderId,
            [],
            [],
            []
        );

        $this->checkoutMock->expects($this->any())->method('getOrder')
            ->willThrowException(new LocalizedException(__('Test error')));
        $this->orderManagementMock->expects($this->any())->method('getOrder')
            ->willReturn([
                'captured_amount' => 0,
                'captures' => [],
                'klarna_reference' => '12345',
            ]);
        $this->orderManagementMock->expects($this->never())->method('cancelOrder');

        $this->getRequest()->setMethod(Http::METHOD_GET);
        $this->dispatch('checkout/klarna/confirmation/id/' . $klarnaOrderId);
        $this->assertRedirect($this->stringContains($expectedRedirect));
        $this->assertSessionMessages($this->equalTo($expectedMessages));

        $this->assertOrderData(
            $klarnaOrderId,
            [],
            [],
            []
        );
    }

    /**
     * @param string $klarnaOrderId
     * @param mixed[] $expectedKlarnaOrder
     * @param mixed[] $expectedOrder
     * @param mixed[] $expectedPayment
     *
     * @return void
     * @throws LocalizedException
     */
    private function assertOrderData(
        string $klarnaOrderId,
        array $expectedKlarnaOrder,
        array $expectedOrder,
        array $expectedPayment
    ): void {
        $klarnaOrder = $this->kOrderFactory->create()->load($klarnaOrderId, 'klarna_order_id');
        if (!$expectedKlarnaOrder) {
            $this->assertNull($klarnaOrder->getId(), 'Assert that order does not exist');

            return;
        }

        $klarnaOrderData = array_intersect_key($klarnaOrder->getData(), $expectedKlarnaOrder);
        $this->assertEquals($expectedKlarnaOrder, $klarnaOrderData);

        $magentoOrder = $this->mOrderFactory->create()->load($klarnaOrder->getOrderId());
        $magentoOrderData = array_intersect_key($magentoOrder->getData(), $expectedOrder);
        $this->assertEquals($expectedOrder, $magentoOrderData);

        $paymentData = $magentoOrder->getId() ? $magentoOrder->getPayment()->getData() : [];
        $paymentData = array_intersect_key($paymentData, $expectedPayment);
        $this->assertEquals($expectedPayment, $paymentData);
    }
}
