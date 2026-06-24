<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

namespace Klarna\Kco\Test\Unit\Model;

use Klarna\Backend\Model\Api\OrderManagement;
use Klarna\Kco\Model\PaymentStatus;
use Klarna\Base\Test\Unit\Mock\MockFactory;
use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use Magento\Framework\DataObject;
use Klarna\Base\Model\Order;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\PaymentStatus
 */
class PaymentStatusTest extends TestCase
{
    /**
     * @var PaymentStatus
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
    /**
     * @var Order
     */
    private $klarnaOrder;

    /**
     * @covers ::getStatusUpdate()
     */
    public function testGetStatusUpdateThrowException()
    {
        $this->dependencyMocks['orderRepository']->method('get')
            ->willThrowException(new \Exception());

        $dataObject = $this->mockFactory->create(DataObject::class);
        $this->dependencyMocks['dataObjectFactory']
            ->expects(static::once())
            ->method('create')
            ->with([
                'data' => [
                    'status'  => 'ERROR'
                ]
            ])
            ->willReturn($dataObject);
        $this->model->getStatusUpdate($this->klarnaOrder);
    }

    /**
     * @covers ::getStatusUpdate()
     */
    public function testGetStatusUpdateReturnOrder()
    {
        $getPlacedKlarnaOrderCalled = false;

        $mageOrder = $this->mockFactory->create(Order::class, [], ['getStore', 'getOrderCurrencyCode']);
        $store     = $this->mockFactory->create(Store::class);

        $mageOrder->method('getOrderCurrencyCode')
            ->willReturn('EUR');

        $mageOrder->method('getStore')
            ->willReturn($store);
        $om = $this->mockFactory->create(OrderManagement::class, ['getPlacedKlarnaOrder']);
        // ::never() and ::once() doesn't work well here so we're making our own called check
        $om->method('getPlacedKlarnaOrder')
            ->willReturnCallback(function () use (&$getPlacedKlarnaOrderCalled) {
                $getPlacedKlarnaOrderCalled = true;
                return new DataObject();
            });

        $this->dependencyMocks['orderRepository']->method('get')
            ->willReturn($mageOrder);
        $this->dependencyMocks['omFactory']->method('createOmApi')
            ->willReturn($om);

        $this->model->getStatusUpdate($this->klarnaOrder);
        static::assertTrue($getPlacedKlarnaOrderCalled);
    }

    protected function setUp(): void
    {
        $this->mockFactory     = new MockFactory($this);
        $objectFactory         = new TestObjectFactory('');
        $this->model           = $objectFactory->create(PaymentStatus::class);
        $this->dependencyMocks = $objectFactory->getDependencyMocks();
        $this->klarnaOrder     = $this->mockFactory->create(Order::class);
    }
}
