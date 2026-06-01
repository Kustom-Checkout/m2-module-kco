<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

namespace Klarna\Kco\Test\Unit\Block;

use Klarna\Base\Test\Unit\Mock\MockFactory;
use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use Klarna\Kco\Model\Api\Kasper;
use PHPUnit\Framework\TestCase;
use Klarna\Kco\Block\Success;
use Magento\Sales\Model\Order;
use Klarna\Base\Model\Order as KlarnaOrder;
use Magento\Framework\Phrase;
use Magento\Framework\Exception\LocalizedException;

/**
 * @coversDefaultClass Klarna\Kco\Block\Success
 */
class SuccessTest extends TestCase
{
    /**
     * @var Success
     */
    private $success;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject[]
     */
    private $dependencyMocks;
    /**
     * @var Order|\PHPUnit_Framework_MockObject_MockObject
     */
    private $order;
    /**
     * @var KlarnaOrder|\PHPUnit_Framework_MockObject_MockObject
     */
    private $klarnaOrder;
    /**
     * @var Kasper|\PHPUnit_Framework_MockObject_MockObject
     */
    private $apiInterface;
    /**
     * @var Phrase|\PHPUnit_Framework_MockObject_MockObject
     */
    private $phrase;

    /**
     * No Klarna order id set, so the klarna success html value is unchanged/null.
     *
     * @covers ::prepareBlockData
     */
    public function testNoKlarnaOrderId()
    {
        $this->dependencyMocks['checkoutSession']->method('getLastRealOrder')->willReturn($this->order);
        $this->dependencyMocks['kcoOrderRepository']->method('getByOrder')->willReturn($this->klarnaOrder);

        $this->success->prepareBlockData();
        static::assertNull($this->success->getKlarnaSuccessHtml());
    }

    /**
     * Klarna order id set and exception is triggered, so the klarna success html value contains the exception.
     *
     * @covers            ::prepareBlockData
     */
    public function testExceptionWhenInitCheckout()
    {
        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->success->getKlarnaSuccessHtml();

        $this->dependencyMocks['checkoutSession']->method('getLastRealOrder')->willReturn($this->order);
        $this->dependencyMocks['kcoOrderRepository']->method('getByOrder')->willReturn($this->klarnaOrder);
        $this->klarnaOrder->method('getId')->willReturn(1);
        $this->dependencyMocks['factory']->method('createApiInstance')->willReturn($this->apiInterface);
        $this->apiInterface->method('retrieveOrder')->willThrowException(
            new LocalizedException(__('LocalizedException'))
        );

        $this->success->prepareBlockData();
    }

    /**
     * Klarna order id set, so we get the Klarna checkout html snippets.
     *
     * @covers ::prepareBlockData
     */
    public function testAddedSuccessText()
    {
        $expected = 'test';

        $this->dependencyMocks['checkoutSession']->method('getLastRealOrder')->willReturn($this->order);
        $this->dependencyMocks['kcoOrderRepository']->method('getByOrder')->willReturn($this->klarnaOrder);
        $this->klarnaOrder->method('getId')->willReturn(1);
        $this->dependencyMocks['factory']->method('createApiInstance')->willReturn($this->apiInterface);
        $this->apiInterface->method('getKlarnaCheckoutGui')->willReturn($expected);

        $this->success->prepareBlockData();
        static::assertEquals($expected, $this->success->getKlarnaSuccessHtml());
    }

    /**
     * Basic setup for test
     */
    protected function setUp(): void
    {
        $mockFactory           = new MockFactory($this);
        $objectFactory         = new TestObjectFactory('');
        $this->success         = $objectFactory->create(Success::class);
        $this->dependencyMocks = $objectFactory->getDependencyMocks();
        $this->order           = $mockFactory->create(Order::class);
        $this->klarnaOrder     = $mockFactory->create(KlarnaOrder::class);
        $this->apiInterface    = $mockFactory->create(Kasper::class);
        $this->phrase          = $mockFactory->create(Phrase::class);

        $this->order->method('getOrderCurrencyCode')
            ->willReturn('EUR');
    }
}
