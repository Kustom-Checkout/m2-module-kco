<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Base\Test\Integration\Controller;

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

class PushTest extends AbstractController
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
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/klarna_order_setup1_single_simple_product.php
     */
    public function testExecuteShouldSuccessfullyAcknowledgeUnacknowledgedOrder(): void
    {
        $expectedResponse = '[]';
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

        $this->orderManagementMock->expects($this->any())->method('getOrder')
            ->willReturn([
                'captured_amount' => 0,
                'captures' => [],
                'klarna_reference' => '12345',
            ]);
        $this->orderManagementMock->expects($this->any())->method('updateMerchantReferences')
            ->willReturn([]);
        $this->orderManagementMock->expects($this->any())->method('acknowledgeOrder')
            ->willReturn(['is_successful' => true]);

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('kco/api/push/id/' . $klarnaOrderId);
        $this->assertEquals($expectedResponse, $this->getResponse()->getBody());

        $this->assertOrderData(
            $klarnaOrderId,
            [
                'klarna_order_id' => $klarnaOrderId,
                'is_acknowledged' => '1',
            ],
            [
                'state' => 'new',
                'status' => 'pending',
                'increment_id' => '100000001',
            ],
            [
                'additional_information' => [
                    'method_title' => 'Check / Money order',
                    'klarna_reference' => '12345',
                ],
            ]
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/klarna_order_setup1_single_simple_product.php
     */
    public function testExecuteShouldSuccessfullyCancelOrderByCancelStatusInOrderData(): void
    {
        $expectedResponse = '[]';
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

        $this->orderManagementMock->expects($this->any())->method('getOrder')
            ->willReturn([
                'captured_amount' => 0,
                'captures' => [],
                'klarna_reference' => '12345',
                'status' => 'CANCELLED',
            ]);
        $this->orderManagementMock->expects($this->any())->method('updateMerchantReferences')
            ->willReturn([]);
        $this->orderManagementMock->expects($this->any())->method('acknowledgeOrder')
            ->willReturn(['is_successful' => true]);

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('kco/api/push/id/' . $klarnaOrderId);
        $this->assertEquals($expectedResponse, $this->getResponse()->getBody());

        $this->assertOrderData(
            $klarnaOrderId,
            [
                'klarna_order_id' => $klarnaOrderId,
                'is_acknowledged' => '0',
            ],
            [
                'state' => 'canceled',
                'status' => 'canceled',
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
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testExecuteShouldSuccessfullyCreateOrderByCheckoutApiResponse(): void
    {
        $expectedResponse = '[]';
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

        $this->orderManagementMock->expects($this->any())->method('getOrder')
            ->willReturn([
                'order_id' => $klarnaOrderId,
                'klarna_reference' => '12345',
            ]);
        $this->orderManagementMock->expects($this->any())->method('updateMerchantReferences')
            ->willReturn([]);
        $this->orderManagementMock->expects($this->any())->method('acknowledgeOrder')
            ->willReturn(['is_successful' => true]);

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('kco/api/push/id/' . $klarnaOrderId);
        $this->assertEquals($expectedResponse, $this->getResponse()->getBody());

        $this->assertOrderData(
            $klarnaOrderId,
            [
                'klarna_order_id' => $klarnaOrderId,
                'is_acknowledged' => '1',
            ],
            [
                'state' => 'processing',
                'status' => 'processing',
                'increment_id' => '100000001',
            ],
            [
                'additional_information' => [
                    'method_title' => 'Klarna Checkout',
                    'klarna_reference' => '12345',
                ],
            ]
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testExecuteShouldNotCreateOrderWhenCartIsLocked(): void
    {
        $expectedResponse = '{"error":"The cart is locked for processing. Please try again later."}';
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

        $this->orderManagementMock->expects($this->any())->method('getOrder')
            ->willReturn([
                'order_id' => $klarnaOrderId,
                'klarna_reference' => '12345',
            ]);
        $this->orderManagementMock->expects($this->any())->method('updateMerchantReferences')
            ->willReturn([]);
        $this->orderManagementMock->expects($this->any())->method('acknowledgeOrder')
            ->willReturn(['is_successful' => true]);

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('kco/api/push/id/' . $klarnaOrderId);
        $this->assertEquals($expectedResponse, $this->getResponse()->getBody());

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
     */
    public function testExecuteShouldThrowAnErrorWhenIdMatchesNothing(): void
    {
        $klarnaOrderId = '123456-1234-1234-1234-1234567890';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'No Klarna Kco quote could be found with the provided Klarna order id: ' . $klarnaOrderId
        );

        $this->assertOrderData(
            $klarnaOrderId,
            [],
            [],
            []
        );

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('kco/api/push/id/' . $klarnaOrderId);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/klarna_order_setup1_single_simple_product.php
     */
    public function testExecuteShouldNotCancelOrderDueToLocalizedException(): void
    {
        $expectedResponse = '{"error":"Failed to update order state"}';
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

        $this->orderManagementMock->expects($this->any())->method('getOrder')
            ->willReturn([
                'captured_amount' => 0,
                'captures' => [],
                'klarna_reference' => '12345',
            ]);
        $this->orderManagementMock->expects($this->any())->method('updateMerchantReferences')
            ->willThrowException(new LocalizedException(__('Test error')));
        $this->orderManagementMock->expects($this->never())->method('cancelOrder');

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('kco/api/push/id/' . $klarnaOrderId);
        $this->assertEquals($expectedResponse, $this->getResponse()->getBody());

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
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testExecuteShouldNotCancelOrderDueToLocalizedExceptionWhenCreatingOneWithCheckoutApiDetails(): void
    {
        $expectedResponse = '{"error":"Failed to create order"}';
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

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('kco/api/push/id/' . $klarnaOrderId);
        $this->assertEquals($expectedResponse, $this->getResponse()->getBody());

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
