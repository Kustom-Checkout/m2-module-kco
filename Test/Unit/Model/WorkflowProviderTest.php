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
    public function testGetKcoQuoteReturnsInstance(): void
    {
        $instance = $this->mockFactory->create(KcoQuote::class);

        $this->dependencyMocks['kcoQuoteRepository']->method('getByCheckoutId')
            ->with('123')
            ->willReturn($instance);

        self::assertSame($instance, $this->model->getKcoQuote());
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
    public function testGetMagentoQuoteReturnsInstance(): void
    {
        $kcoQuote = $this->mockFactory->create(KcoQuote::class);
        $kcoQuote->method('getQuoteId')
            ->willReturn('456');
        $this->dependencyMocks['kcoQuoteRepository']->method('getByCheckoutId')
            ->with('123')
            ->willReturn($kcoQuote);

        $magentoQuote = $this->mockFactory->create(MagentoQuote::class);
        $this->dependencyMocks['magentoQuoteRepository']->method('get')
            ->with('456')
            ->willReturn($magentoQuote);

        self::assertSame($magentoQuote, $this->model->getMagentoQuote());
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
    public function testGetKlarnaOrderReturnsInstance(): void
    {
        $instance = $this->mockFactory->create(KlarnaOrder::class);

        $this->dependencyMocks['klarnaOrderRepository']->method('getByKlarnaOrderId')
            ->with('123')
            ->willReturn($instance);

        self::assertSame($instance, $this->model->getKlarnaOrder());
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
    public function testGetMagentoOrderReturnsInstance(): void
    {
        $magentoInstance = $this->mockFactory->create(MagentoOrder::class);

        $this->dependencyMocks['magentoOrderRepository']->method('get')
            ->with('456')
            ->willReturn($magentoInstance);

        $klarnaInstance = $this->mockFactory->create(KlarnaOrder::class);
        $klarnaInstance->method('getOrderId')
            ->willReturn('456');

        $this->dependencyMocks['klarnaOrderRepository']->method('getByKlarnaOrderId')
            ->with('123')
            ->willReturn($klarnaInstance);

        self::assertSame($magentoInstance, $this->model->getMagentoOrder());
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

    protected function setUp(): void
    {
        $this->mockFactory     = new MockFactory($this);
        $objectFactory         = new TestObjectFactory('');
        $this->model           = $objectFactory->create(WorkflowProvider::class);
        $this->dependencyMocks = $objectFactory->getDependencyMocks();

        $this->model->setKlarnaOrderId('123');
    }
}
