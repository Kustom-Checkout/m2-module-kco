<?php

/**
 * Copyright 2025 Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Order;

use Klarna\Base\Api\OrderInterface as KlarnaOrderInterface;
use Klarna\Base\Exception as KlarnaException;
use Klarna\Base\Model\Order as KlarnaOrderModel;
use Klarna\Base\Test\Unit\Mock\MockFactory;
use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use Klarna\Kco\Api\QuoteInterface;
use Klarna\Kco\Model\Order\Order;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Quote\Model\Quote as MagentoQuote;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covering the concurrency protection of the order creation (KUSTOM-102)
 *
 * @coversDefaultClass \Klarna\Kco\Model\Order\Order
 */
class OrderTest extends TestCase
{
    private const KLARNA_ORDER_ID = 'c5909ea7-78d0-2042-9d49-8e20f9622aa1';
    private const LOCK_NAME = 'kustom_order_create_' . self::KLARNA_ORDER_ID;

    /**
     * @var Order
     */
    private $model;

    /**
     * @var MockObject[]
     */
    private $dependencyMocks;

    /**
     * @var MockFactory
     */
    private $mockFactory;

    protected function setUp(): void
    {
        $this->mockFactory = new MockFactory($this);
        $objectFactory = new TestObjectFactory('');
        $this->model = $objectFactory->create(Order::class);
        $this->dependencyMocks = $objectFactory->getDependencyMocks();
    }

    /**
     * The order creation must not even start when another request is already creating the order
     *
     * @covers ::createMagentoOrder
     */
    public function testCreateMagentoOrderDoesNothingWhenLockCanNotBeAcquired(): void
    {
        $this->dependencyMocks['lockManager']
            ->expects(static::once())
            ->method('lock')
            ->with(self::LOCK_NAME, 15)
            ->willReturn(false);
        $this->dependencyMocks['lockManager']
            ->expects(static::never())
            ->method('unlock');
        $this->dependencyMocks['workflowProvider']
            ->expects(static::never())
            ->method('setKlarnaOrderId');

        $this->expectException(AlreadyExistsException::class);

        $this->model->createMagentoOrder(self::KLARNA_ORDER_ID);
    }

    /**
     * The lock has to be released when the Kustom order entry already exists
     *
     * @covers ::createMagentoOrder
     */
    public function testCreateMagentoOrderReleasesTheLockWhenTheOrderAlreadyExists(): void
    {
        $this->expectLockAcquired();

        $klarnaOrder = $this->mockFactory->create(KlarnaOrderInterface::class);
        $klarnaOrder->method('getId')->willReturn(5);

        $this->dependencyMocks['workflowProvider']
            ->expects(static::once())
            ->method('setKlarnaOrderId')
            ->with(self::KLARNA_ORDER_ID);
        $this->dependencyMocks['workflowProvider']
            ->method('getKlarnaOrder')
            ->willReturn($klarnaOrder);
        $this->dependencyMocks['action']
            ->expects(static::never())
            ->method('createOrder');

        $this->expectException(AlreadyExistsException::class);

        $this->model->createMagentoOrder(self::KLARNA_ORDER_ID);
    }

    /**
     * A request which acquires the lock after a concurrent one finished has to return the already
     * created Magento order instead of placing a second one
     *
     * @covers ::createMagentoOrder
     */
    public function testCreateMagentoOrderReturnsTheOrderCreatedByTheConcurrentRequest(): void
    {
        $this->expectLockAcquired();

        $emptyKlarnaOrder = $this->mockFactory->create(KlarnaOrderModel::class);
        $emptyKlarnaOrder->method('getId')->willReturn(null);

        $this->dependencyMocks['workflowProvider']
            ->method('getKlarnaOrder')
            ->willThrowException(new KlarnaException(__('No Kustom order entry could be found')));
        $this->dependencyMocks['orderRepository']
            ->method('getEmptyInstance')
            ->willReturn($emptyKlarnaOrder);
        $this->dependencyMocks['workflowProvider']
            ->method('getKcoQuote')
            ->willReturn($this->mockFactory->create(QuoteInterface::class));

        $magentoQuote = $this->mockFactory->create(MagentoQuote::class);
        $magentoQuote->method('getReservedOrderId')->willReturn('100000001');
        $this->dependencyMocks['workflowProvider']
            ->method('getMagentoQuote')
            ->willReturn($magentoQuote);

        $existingOrder = $this->mockFactory->create(MagentoOrder::class);
        $this->dependencyMocks['searchCriteriaBuilder']
            ->method('addFilter')
            ->willReturnSelf();
        $this->dependencyMocks['searchCriteriaBuilder']
            ->method('create')
            ->willReturn($this->mockFactory->create(SearchCriteria::class));

        $searchResult = $this->mockFactory->create(
            OrderCollection::class,
            ['getTotalCount', 'getFirstItem']
        );
        $searchResult->method('getTotalCount')->willReturn(1);
        $searchResult->method('getFirstItem')->willReturn($existingOrder);

        $this->dependencyMocks['mageOrderRepository']
            ->method('getList')
            ->willReturn($searchResult);

        $this->dependencyMocks['action']
            ->expects(static::never())
            ->method('createOrder');

        static::assertSame($existingOrder, $this->model->createMagentoOrder(self::KLARNA_ORDER_ID));
        static::assertSame($existingOrder, $this->model->getMagentoOrder());
    }

    /**
     * Setting up the lock manager so that the lock is acquired and has to be released again
     */
    private function expectLockAcquired(): void
    {
        $this->dependencyMocks['lockManager']
            ->expects(static::once())
            ->method('lock')
            ->with(self::LOCK_NAME, 15)
            ->willReturn(true);
        $this->dependencyMocks['lockManager']
            ->expects(static::once())
            ->method('unlock')
            ->with(self::LOCK_NAME)
            ->willReturn(true);
    }
}

