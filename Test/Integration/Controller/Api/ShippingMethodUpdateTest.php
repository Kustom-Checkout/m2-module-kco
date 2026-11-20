<?php

/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Controller\Api;

use Klarna\Kco\Api\QuoteRepositoryInterface;
use Klarna\Kco\Model\Checkout\ValidatedMarker;
use Klarna\Kco\Model\Api\Rest\Service\Checkout as CheckoutApi;
use Klarna\Kco\Model\Order\CreationLock;
use Klarna\Kco\Model\Order\Order as CheckoutOrder;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\Backend\Database;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use Magento\TestFramework\TestCase\AbstractController;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \Klarna\Kco\Controller\Api\ShippingMethodUpdate
 */
class ShippingMethodUpdateTest extends AbstractController
{
    /**
     * @var GetQuoteByReservedOrderId
     */
    private $quoteGetter;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CheckoutApi|MockObject
     */
    private $checkoutApiMock;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->checkoutApiMock = $this->createMock(CheckoutApi::class);
        $this->_objectManager->addSharedInstance($this->checkoutApiMock, CheckoutApi::class);

        $this->quoteGetter = $this->_objectManager->get(GetQuoteByReservedOrderId::class);
        $this->checkoutSession = $this->_objectManager->get(CheckoutSession::class);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store payment/klarna_kss/enabled 0
     * @magentoConfigFixture current_store klarna/api/debug 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testShippingMethodUpdateWithoutKss(): void
    {
        $quoteId = '100000001';
        $klarnaOrderId = '123456-1234-1234-1234-1234567890';
        $expectedShippingOptions = [
            [
                'id' => 'flatrate_flatrate',
                'name' => 'Fixed',
                'price' => 500,
                'promo' => '',
                'tax_amount' => 0,
                'tax_rate' => 0,
                'description' => 'Flat Rate',
                'preselected' => true,
            ],
        ];

        $this->checkoutApiMock->expects($this->never())->method('getOrder');

        $quote = $this->quoteGetter->execute($quoteId);
        $this->checkoutSession->replaceQuote($quote);

        $this->getRequest()->setContent(json_encode([
            'selected_shipping_option' => [
                'id' => 'flatrate_flatrate',
            ],
        ]));
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('kco/api/shippingMethodUpdate/id/' . $klarnaOrderId);
        $body = json_decode($this->getResponse()->getBody(), true);
        $this->assertEquals($expectedShippingOptions, $body['shipping_options'] ?? []);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store payment/klarna_kss/enabled 1
     * @magentoConfigFixture current_store klarna/api/debug 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testShippingMethodUpdateWithKss(): void
    {
        $quoteId = '100000001';
        $klarnaOrderId = '123456-1234-1234-1234-1234567890';
        $expectedShippingOptions = [
            [
                'id' => 'flatrate_flatrate',
                'name' => 'Fixed',
                'price' => 500,
                'promo' => '',
                'tax_amount' => 0,
                'tax_rate' => 0,
                'description' => 'Flat Rate',
                'preselected' => false,
            ],
            [
                'id' => 'klarna_shipping_method_gateway',
                'name' => 'DHL Express',
                'price' => 999,
                'promo' => '',
                'tax_amount' => 0,
                'tax_rate' => 0,
                'description' => 'Kustom',
                'preselected' => false,
            ],
            [
                'id' => '019f46f5-4769-7b29-bbe9-a2b179d21424',
                'name' => 'klarna_shipping_method_gateway',
                'price' => 999,
                'promo' => '',
                'tax_amount' => 0,
                'tax_rate' => 0,
                'description' => '',
            ],
        ];

        $selectedShipping = [
            'id' => '019f46f5-4769-7b29-bbe9-a2b179d21424',
            'name' => 'DHL Express',
            'price' => 999,
            'tax_amount' => 200,
            'tax_rate' => 2500,
            'shipping_method' => 'Home',
            'delivery_details' => [
                'carrier' => 'dhl-express',
                'class' => 'standard',
            ],
        ];

        $this->checkoutApiMock->expects($this->atLeastOnce())->method('getOrder')
            ->willReturn([
                'id' => $klarnaOrderId,
                'is_successful' => true,
                'order_id' => $klarnaOrderId,
                'selected_shipping_option' => $selectedShipping,
            ]);

        $quote = $this->quoteGetter->execute($quoteId);
        $this->checkoutSession->replaceQuote($quote);

        $this->getRequest()->setContent(json_encode([
            'selected_shipping_option' => $selectedShipping,
        ]));
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('kco/api/shippingMethodUpdate/id/' . $klarnaOrderId);
        $body = json_decode($this->getResponse()->getBody(), true);
        $this->assertEquals($expectedShippingOptions, $body['shipping_options'] ?? []);
    }

    /**
     * KUSTOM-128: Kustom reopened the checkout, so the callback is applied and the validated marker is cleared
     *
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store payment/klarna_kss/enabled 0
     * @magentoConfigFixture current_store klarna/api/debug 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testMarkerIsClearedAndCallbackStillApplied(): void
    {
        $klarnaOrderId = '123456-1234-1234-1234-1234567890';
        $repository = $this->_objectManager->get(QuoteRepositoryInterface::class);
        $this->_objectManager->get(ValidatedMarker::class)->update($klarnaOrderId, gmdate('Y-m-d H:i:s'));

        $this->checkoutSession->replaceQuote($this->quoteGetter->execute('100000001'));
        $this->dispatchShippingMethodUpdate($klarnaOrderId);

        $body = json_decode($this->getResponse()->getBody(), true);
        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
        $this->assertNotEmpty($body['shipping_options'] ?? []);
        $this->assertNull($repository->getByCheckoutId($klarnaOrderId)->getValidatedAt());
    }

    /**
     * KUSTOM-128: the Magento order exists, so the quote must not change
     *
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store payment/klarna_kss/enabled 0
     * @magentoConfigFixture current_store klarna/api/debug 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testQuoteUnchangedWhenMagentoOrderExists(): void
    {
        $klarnaOrderId = '123456-1234-1234-1234-1234567890';
        $orderMock = $this->createMock(CheckoutOrder::class);
        $orderMock->method('isMagentoOrderExists')->willReturn(true);
        $this->_objectManager->addSharedInstance($orderMock, CheckoutOrder::class);

        $quote = $this->quoteGetter->execute('100000001');
        $this->checkoutSession->replaceQuote($quote);
        $methodBefore = $quote->getShippingAddress()->getShippingMethod();

        $this->dispatchShippingMethodUpdate($klarnaOrderId, 'freeshipping_freeshipping');

        $this->assertEquals(400, $this->getResponse()->getHttpResponseCode());
        $this->assertStringContainsString('Your payment is being processed', $this->getResponse()->getBody());
        $this->assertEquals($methodBefore, $this->loadShippingMethod((int) $quote->getId()));
    }

    /**
     * KUSTOM-128: an order creation holds the lock (second connection), so the callback is refused
     *
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store payment/klarna_kss/enabled 0
     * @magentoConfigFixture current_store klarna/api/debug 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testRefusedWhileCreationLockIsHeldByAnotherConnection(): void
    {
        $klarnaOrderId = '123456-1234-1234-1234-1234567890';
        $creationRequestLock = $this->createLockWithOwnConnection();
        $this->_objectManager->addSharedInstance($this->createLockWithOwnConnection(), CreationLock::class);
        $this->assertTrue($creationRequestLock->acquire($klarnaOrderId, 0));

        try {
            $quote = $this->quoteGetter->execute('100000001');
            $this->checkoutSession->replaceQuote($quote);
            $methodBefore = $quote->getShippingAddress()->getShippingMethod();

            $this->dispatchShippingMethodUpdate($klarnaOrderId, 'freeshipping_freeshipping');

            $this->assertEquals(400, $this->getResponse()->getHttpResponseCode());
            $this->assertEquals($methodBefore, $this->loadShippingMethod((int) $quote->getId()));
        } finally {
            $creationRequestLock->release($klarnaOrderId);
        }
    }

    /**
     * KUSTOM-128: the lock held by the guard is released when the request is done (push can acquire it)
     *
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store payment/klarna_kss/enabled 0
     * @magentoConfigFixture current_store klarna/api/debug 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testLockIsReleasedAfterGuardedRequest(): void
    {
        $klarnaOrderId = '123456-1234-1234-1234-1234567890';
        $this->_objectManager->addSharedInstance($this->createLockWithOwnConnection(), CreationLock::class);

        $this->checkoutSession->replaceQuote($this->quoteGetter->execute('100000001'));
        $this->dispatchShippingMethodUpdate($klarnaOrderId);
        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());

        $pushLock = $this->createLockWithOwnConnection();
        try {
            $this->assertTrue($pushLock->acquire($klarnaOrderId, 0));
        } finally {
            $pushLock->release($klarnaOrderId);
        }
    }

    /**
     * The test framework replaces the locker by a dummy, so a real database locker with its own connection is used
     *
     * @return CreationLock
     */
    private function createLockWithOwnConnection(): CreationLock
    {
        return new CreationLock(new Database(
            $this->_objectManager->create(ResourceConnection::class),
            $this->_objectManager->get(DeploymentConfig::class)
        ));
    }

    /**
     * @param string $klarnaOrderId
     * @param string $optionId
     * @return void
     */
    private function dispatchShippingMethodUpdate(string $klarnaOrderId, string $optionId = 'flatrate_flatrate'): void
    {
        $this->getRequest()->setContent(json_encode(['selected_shipping_option' => ['id' => $optionId]]));
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('kco/api/shippingMethodUpdate/id/' . $klarnaOrderId);
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
}
