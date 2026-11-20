<?php

/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Model\Cart\Validations;

use Klarna\Base\Exception;
use Klarna\Kco\Model\Cart\Validations\Handler;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use PHPUnit\Framework\TestCase;

/**
 * KUSTOM-127: a completed Kustom order must reuse the shipping rate stored at validate
 */
class HandlerTest extends TestCase
{
    private const FLATRATE_PRICE_PATH = 'carriers/flatrate/price';

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var Handler
     */
    private $handler;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->handler = $this->objectManager->create(Handler::class);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store carriers/flatrate/price 5
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testCompletedOrderReusesRateStoredAtValidate(): void
    {
        $this->runValidateAndRaiseCarrierPrice();

        $quote = $this->reloadQuote();
        $this->handler->validateRequestObject($this->buildRequest('checkout_complete', 500), $quote);

        $address = $quote->getShippingAddress();
        $this->assertEquals(5, $address->getBaseShippingAmount());
        $this->assertSame('flatrate_flatrate', $address->getShippingMethod());
    }

    /**
     * Proves the price change takes effect: validate (incomplete) still uses live rates
     *
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store carriers/flatrate/price 5
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testIncompleteOrderStillUsesLiveRates(): void
    {
        $this->runValidateAndRaiseCarrierPrice();

        $quote = $this->reloadQuote();
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Shipping amount does not match|Order total does not match/');
        $this->handler->validateRequestObject($this->buildRequest('checkout_incomplete', 500), $quote);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store carriers/flatrate/price 5
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testCompletedOrderWithDifferentKustomShippingAmountIsRejected(): void
    {
        $this->runValidateAndRaiseCarrierPrice();

        $quote = $this->reloadQuote();
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Shipping amount does not match/');
        $this->handler->validateRequestObject($this->buildRequest('checkout_complete', 700, 1700), $quote);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store carriers/flatrate/price 5
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testCompletedOrderWithoutStoredRateFallsBackToLiveRates(): void
    {
        $this->runValidateAndRaiseCarrierPrice();

        $quote = $this->reloadQuote();
        $connection = $this->objectManager->get(ResourceConnection::class)->getConnection();
        $connection->delete(
            $connection->getTableName('quote_shipping_rate'),
            ['address_id = ?' => (int)$quote->getShippingAddress()->getId()]
        );

        $quote = $this->reloadQuote();
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Shipping amount does not match|Order total does not match/');
        $this->handler->validateRequestObject($this->buildRequest('checkout_complete', 500), $quote);
    }

    /**
     * Simulates the validate callback at flatrate 5, then a carrier price change to 10
     */
    private function runValidateAndRaiseCarrierPrice(): void
    {
        $quote = $this->objectManager->create(GetQuoteByReservedOrderId::class)->execute('100000001');
        $this->handler->validateRequestObject($this->buildRequest('checkout_incomplete', 500), $quote);
        $this->objectManager->get(CartRepositoryInterface::class)->save($quote);

        $this->objectManager->get(MutableScopeConfigInterface::class)
            ->setValue(self::FLATRATE_PRICE_PATH, 10, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return CartInterface
     */
    private function reloadQuote(): CartInterface
    {
        $id = $this->objectManager->create(GetQuoteByReservedOrderId::class)->execute('100000001')->getId();
        $quote = $this->objectManager->create(QuoteFactory::class)->create();
        $quote->setStoreId(1)->load($id);

        return $quote;
    }

    /**
     * @param string $status
     * @param int $shippingPrice minor units
     * @param int $orderAmount minor units
     * @return DataObject
     */
    private function buildRequest(string $status, int $shippingPrice, int $orderAmount = 1500): DataObject
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
            'order_id' => '123456-1234-1234-1234-1234567890',
            'status' => $status,
            'billing_address' => $address,
            'shipping_address' => $address,
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
                ],
            ],
            'selected_shipping_option' => [
                'id' => 'flatrate_flatrate',
                'price' => $shippingPrice,
                'tax_amount' => 0,
                'tax_rate' => 0,
            ],
            'order_amount' => $orderAmount,
        ]);
    }
}
