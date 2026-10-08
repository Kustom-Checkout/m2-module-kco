<?php

/**
 * Copyright 2025 Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Model\Order;

use Klarna\Kco\Model\Order\Order;
use Klarna\Kco\Model\QuoteFactory as KustomQuoteFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\OrderFactory as MagentoOrderFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Covering the duplicate checks of the order creation. Both checks return before any Kustom API call.
 */
class OrderTest extends TestCase
{
    private const KLARNA_ORDER_ID = '123456-1234-1234-1234-1234567890';

    /**
     * @var Order
     */
    private $model;

    protected function setUp(): void
    {
        $this->model = Bootstrap::getObjectManager()->create(Order::class);
    }

    /**
     * An order which uses the reserved increment id of the quote already exists, but no Kustom order entry was saved
     * for it (for example because the request which placed it failed afterwards).
     *
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/order_setup1_single_simple_product.php
     */
    public function testCreateMagentoOrderReturnsTheExistingOrderOfTheReservedIncrementId(): void
    {
        $this->createKustomQuoteForTheQuoteOfOrder('100000001');
        $orderCount = $this->getOrderCount();

        $order = $this->model->createMagentoOrder(self::KLARNA_ORDER_ID);

        static::assertSame('100000001', $order->getIncrementId());
        static::assertSame($orderCount, $this->getOrderCount());
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/klarna_order_setup1_single_simple_product.php
     */
    public function testCreateMagentoOrderThrowsExceptionWhenKustomOrderEntryAlreadyExists(): void
    {
        $orderCount = $this->getOrderCount();

        try {
            $this->model->createMagentoOrder(self::KLARNA_ORDER_ID);
            static::fail('Expected ' . AlreadyExistsException::class . ' to be thrown');
        } catch (AlreadyExistsException $e) {
            static::assertSame($orderCount, $this->getOrderCount());
        }
    }

    /**
     * Creating the Kustom quote for the quote of the given order, like a checkout which was started for it
     *
     * The order fixture and the quote fixture can't be combined, because both apply the same tax rule fixture.
     *
     * @param string $incrementId
     */
    private function createKustomQuoteForTheQuoteOfOrder(string $incrementId): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $magentoOrder = $objectManager->create(MagentoOrderFactory::class)->create()->loadByIncrementId($incrementId);

        $kustomQuote = $objectManager->create(KustomQuoteFactory::class)->create();
        $kustomQuote->setQuoteId($magentoOrder->getQuoteId());
        $kustomQuote->setKlarnaCheckoutId(self::KLARNA_ORDER_ID);
        $kustomQuote->setIsActive(true);
        $kustomQuote->save();
    }

    /**
     * Getting back the amount of Magento orders
     *
     * @return int
     */
    private function getOrderCount(): int
    {
        return (int) Bootstrap::getObjectManager()->get(OrderCollectionFactory::class)->create()->getSize();
    }
}
