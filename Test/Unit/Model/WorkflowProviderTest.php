<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

namespace Klarna\Kco\Test\Unit\Model\Checkout;

use Klarna\Kco\Model\WorkflowProvider;
use Klarna\Base\Test\Unit\Mock\MockFactory;
use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote as MagentoQuote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Klarna\Kco\Model\Quote as KcoQuote;
use Klarna\Base\Model\Order as KlarnaOrder;
use Magento\Sales\Model\Order as MagentoOrder;
use Klarna\Base\Exception as BaseException;

/**
 * @coversDefaultClass \Klarna\Kco\Model\WorkflowProvider
 */
class WorkflowProviderTest extends TestCase
{
    /**
     * @var WorkflowProvider
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
        $objectFactory = new TestObjectFactory($this->mockFactory);
        $this->model = $objectFactory->create(WorkflowProvider::class);
        $this->dependencyMocks = $objectFactory->getDependencyMocks();

        $this->model->setKlarnaOrderId('123');
    }

    /**
     * @covers ::setKlarnaOrderId
     */
    public function testSetKlarnaOrderIdInputIsEmpty(): void
    {
        $this->expectException(\Klarna\Base\Exception::class);
        $this->model->setKlarnaOrderId('');
    }

    /**
     * @doesNotPerformAssertions
     * @covers ::setKlarnaOrderId
     */
    public function testSetKlarnaOrderIdInputIsNotEmpty(): void
    {
        $this->model->setKlarnaOrderId('123');
    }

    /**
     * @covers ::getKcoQuote
     */
    public function testGetKcoQuoteReturnsInstanceWithCache(): void
    {
        $instance = $this->mockFactory->create(KcoQuote::class);

        $this->dependencyMocks['kcoQuoteRepository']->expects($this->once())->method('getByCheckoutId')
            ->with('123')
            ->willReturn($instance);

        $this->assertSame($instance, $this->model->getKcoQuote());
        $this->assertSame($instance, $this->model->getKcoQuote(), 'Assert that cache works');
    }

    /**
     * @covers ::getKcoQuote
     */
    public function testGetKcoQuoteReturnsDifferentInstanceAfterSetKlarnaOrder(): void
    {
        $instance1 = $this->mockFactory->create(KcoQuote::class);
        $instance2 = $this->mockFactory->create(KcoQuote::class);

        $this->dependencyMocks['kcoQuoteRepository']->expects($this->exactly(2))
            ->method('getByCheckoutId')
            ->willReturnOnConsecutiveCalls($instance1, $instance2);

        $this->assertSame($instance1, $this->model->getKcoQuote());
        $this->assertSame($instance1, $this->model->getKcoQuote(), 'Assert that cache works');
        $this->model->setKlarnaOrderId('234');
        $this->assertSame($instance2, $this->model->getKcoQuote(), 'Assert that we get different instance');
    }

    /**
     * @covers ::getKcoQuote
     */
    public function testGetKcoQuoteNoQuoteFound(): void
    {
        $this->expectException(\Klarna\Base\Exception::class);
        $this->dependencyMocks['kcoQuoteRepository']->method('getByCheckoutId')
            ->with('123')
            ->willThrowException(new NoSuchEntityException());

        $this->model->getKcoQuote();
    }

    /**
     * @covers ::getMagentoQuote
     */
    public function testGetMagentoQuoteReturnsInstanceWithCache(): void
    {
        $kcoQuote = $this->mockFactory->create(KcoQuote::class);
        $kcoQuote->method('getQuoteId')
            ->willReturn('456');
        $this->dependencyMocks['kcoQuoteRepository']->expects($this->once())->method('getByCheckoutId')
            ->with('123')
            ->willReturn($kcoQuote);

        $magentoQuote = $this->mockFactory->create(MagentoQuote::class);
        $this->dependencyMocks['magentoQuoteRepository']->expects($this->once())->method('get')
            ->with('456')
            ->willReturn($magentoQuote);

        $this->assertSame($magentoQuote, $this->model->getMagentoQuote());
        $this->assertSame($magentoQuote, $this->model->getMagentoQuote(), 'Assert that cache works');
    }

    /**
     * @covers ::getMagentoQuote
     */
    public function testGetMagentoQuoteReturnsDifferentInstanceAfterSetKlarnaOrder(): void
    {
        $kcoQuote1 = $this->mockFactory->create(KcoQuote::class);
        $kcoQuote1->method('getQuoteId')
            ->willReturn('456');
        $kcoQuote2 = $this->mockFactory->create(KcoQuote::class);
        $kcoQuote2->method('getQuoteId')
            ->willReturn('567');

        $this->dependencyMocks['kcoQuoteRepository']->expects($this->exactly(2))->method('getByCheckoutId')
            ->willReturnOnConsecutiveCalls($kcoQuote1, $kcoQuote2);

        $magentoQuote1 = $this->mockFactory->create(MagentoQuote::class);
        $magentoQuote2 = $this->mockFactory->create(MagentoQuote::class);
        $this->dependencyMocks['magentoQuoteRepository']->expects($this->exactly(2))->method('get')
            ->willReturnOnConsecutiveCalls($magentoQuote1, $magentoQuote2);

        $this->assertSame($magentoQuote1, $this->model->getMagentoQuote());
        $this->assertSame($magentoQuote1, $this->model->getMagentoQuote(), 'Assert that cache works');
        $this->model->setKlarnaOrderId('234');
        $this->assertSame($magentoQuote2, $this->model->getMagentoQuote(), 'Assert that we get different instance');
    }

    /**
     * @covers ::getMagentoQuote
     */
    public function testGetMagentoQuoteNoQuoteFound(): void
    {
        $this->expectException(\Klarna\Base\Exception::class);
        $kcoQuote = $this->mockFactory->create(KcoQuote::class);
        $kcoQuote->method('getQuoteId')
            ->willReturn('456');
        $this->dependencyMocks['kcoQuoteRepository']->method('getByCheckoutId')
            ->with('123')
            ->willReturn($kcoQuote);

        $this->dependencyMocks['magentoQuoteRepository']->method('get')
            ->with('456')
            ->willThrowException(new NoSuchEntityException());

        $this->model->getMagentoQuote();
    }

    /**
     * @covers ::getKlarnaOrder
     */
    public function testGetKlarnaOrderReturnsInstanceWithCache(): void
    {
        $instance = $this->mockFactory->create(KlarnaOrder::class);

        $this->dependencyMocks['klarnaOrderRepository']->expects($this->once())->method('getByKlarnaOrderId')
            ->with('123')
            ->willReturn($instance);

        $this->assertSame($instance, $this->model->getKlarnaOrder());
        $this->assertSame($instance, $this->model->getKlarnaOrder(), 'Assert that cache works');
    }

    /**
     * @covers ::getKlarnaOrder
     */
    public function testGetKlarnaOrderReturnsDifferentInstanceAfterSetKlarnaOrder(): void
    {
        $instance1 = $this->mockFactory->create(KlarnaOrder::class);
        $instance2 = $this->mockFactory->create(KlarnaOrder::class);

        $this->dependencyMocks['klarnaOrderRepository']->expects($this->exactly(2))->method('getByKlarnaOrderId')
            ->willReturnOnConsecutiveCalls($instance1, $instance2);

        $this->assertSame($instance1, $this->model->getKlarnaOrder());
        $this->assertSame($instance1, $this->model->getKlarnaOrder(), 'Assert that cache works');
        $this->model->setKlarnaOrderId('234');
        $this->assertSame($instance2, $this->model->getKlarnaOrder(), 'Assert that we get different instance');
    }

    /**
     * @covers ::getKlarnaOrder
     */
    public function testGetKlarnaOrderNoOrderFound(): void
    {
        $this->expectException(\Klarna\Base\Exception::class);
        $this->dependencyMocks['klarnaOrderRepository']->method('getByKlarnaOrderId')
            ->with('123')
            ->willThrowException(new NoSuchEntityException());

        $this->model->getKlarnaOrder();
    }

    /**
     * @covers ::getMagentoOrder
     */
    public function testGetMagentoOrderReturnsInstanceWithCache(): void
    {
        $magentoInstance = $this->mockFactory->create(MagentoOrder::class);

        $this->dependencyMocks['magentoOrderRepository']->expects($this->once())->method('get')
            ->with('456')
            ->willReturn($magentoInstance);

        $klarnaInstance = $this->mockFactory->create(KlarnaOrder::class);
        $klarnaInstance->method('getOrderId')
            ->willReturn('456');

        $this->dependencyMocks['klarnaOrderRepository']->expects($this->once())->method('getByKlarnaOrderId')
            ->with('123')
            ->willReturn($klarnaInstance);

        $this->assertSame($magentoInstance, $this->model->getMagentoOrder());
        $this->assertSame($magentoInstance, $this->model->getMagentoOrder(), 'Assert that cache works');
    }

    /**
     * @covers ::getMagentoOrder
     */
    public function testGetMagentoOrderReturnsDifferentInstanceAfterSetKlarnaOrder(): void
    {
        $magentoInstance1 = $this->mockFactory->create(MagentoOrder::class);
        $magentoInstance2 = $this->mockFactory->create(MagentoOrder::class);

        $this->dependencyMocks['magentoOrderRepository']->expects($this->exactly(2))->method('get')
            ->willReturn($magentoInstance1, $magentoInstance2);

        $klarnaInstance1 = $this->mockFactory->create(KlarnaOrder::class);
        $klarnaInstance1->method('getOrderId')
            ->willReturn('456');
        $klarnaInstance2 = $this->mockFactory->create(KlarnaOrder::class);
        $klarnaInstance2->method('getOrderId')
            ->willReturn('567');

        $this->dependencyMocks['klarnaOrderRepository']->expects($this->exactly(2))->method('getByKlarnaOrderId')
            ->willReturn($klarnaInstance1, $klarnaInstance2);

        $this->assertSame($magentoInstance1, $this->model->getMagentoOrder());
        $this->assertSame($magentoInstance1, $this->model->getMagentoOrder(), 'Assert that cache works');
        $this->model->setKlarnaOrderId('234');
        $this->assertSame($magentoInstance2, $this->model->getMagentoOrder(), 'Assert that we get different instance');
    }

    /**
     * @covers ::getMagentoOrder
     */
    public function testGetMagentoOrderNoOrderFound(): void
    {
        $this->expectException(\Klarna\Base\Exception::class);
        $this->dependencyMocks['magentoOrderRepository']->method('get')
            ->with('456')
            ->willThrowException(new BaseException(__()));

        $klarnaInstance = $this->mockFactory->create(KlarnaOrder::class);
        $klarnaInstance->method('getOrderId')
            ->willReturn('456');

        $this->dependencyMocks['klarnaOrderRepository']->method('getByKlarnaOrderId')
            ->with('123')
            ->willReturn($klarnaInstance);

        $this->model->getMagentoOrder();
    }
}
