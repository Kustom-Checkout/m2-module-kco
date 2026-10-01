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
use Magento\Quote\Model\Quote as MagentoQuote;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covering the duplicate protection of the order creation
 *
 * @coversDefaultClass \Klarna\Kco\Model\Order\Order
 */
class OrderTest extends TestCase
{
    private const KLARNA_ORDER_ID = 'c5909ea7-78d0-2042-9d49-8e20f9622aa1';

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
     * When a Magento order with the reserved increment id already exists it has to be returned instead of
     * placing a second one
     *
     * @covers ::createMagentoOrder
     */
    public function testCreateMagentoOrderReturnsTheAlreadyExistingMagentoOrder(): void
    {
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
}
