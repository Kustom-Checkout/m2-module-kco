<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

namespace Klarna\Kco\Test\Unit\Model\Payment;

use Klarna\Kco\Model\Payment\Kco;
use Klarna\Base\Test\Unit\Mock\MockFactory;
use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use Magento\Sales\Model\Order;
use Magento\Store\Model\Store;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Magento\Sales\Model\Order\Payment\Info;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Payment\Kco
 */
class KcoTest extends TestCase
{
    /**
     * @var Kco
     */
    private $model;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject[]
     */
    private $dependencyMocks;

    /**
     * @var Info|\PHPUnit_Framework_MockObject_MockObject
     */
    private $infoInstanceMock;

    /**
     * @var Order|\PHPUnit_Framework_MockObject_MockObject
     */
    private $orderMock;

    /**
     * @var Store|\PHPUnit_Framework_MockObject_MockObject
     */
    private $storeMock;

    /**
     * @var Quote|\PHPUnit_Framework_MockObject_MockObject
     */
    private $quoteMock;

    /**
     * @covers ::canCapturePartial
     * @testdox Verifies that the process reaches the last return in the method
     */
    public function testCanCapturePartial()
    {
        $this->dependencyMocks['adapter']->expects(static::once())
            ->method('canCapturePartial');

        $this->model->canCapturePartial();
    }

    /**
     * @covers ::canRefundPartialPerInvoice
     * @testdox Verifies that the process reaches the last return in the method
     */
    public function testCanRefundPartialPerInvoice()
    {
        $this->dependencyMocks['adapter']->expects(static::once())
            ->method('canRefundPartialPerInvoice');

        $this->model->canRefundPartialPerInvoice();
    }

    /**
     * @covers ::isAvailable
     */
    public function testIsAvailableKcoDisabled()
    {
        $this->dependencyMocks['adapter']->expects(static::once())
            ->method('isAvailable')
            ->willReturn(false);
        $this->dependencyMocks['config']->expects(static::once())
            ->method('isKcoEnabled')
            ->willReturn(false);

        static::assertFalse(
            $this->model->isAvailable($this->quoteMock),
            'Expected ::isAvailable to return the same value as $this->adapter->isAvailable()'
        );
    }

    /**
     * @covers ::isAvailable
     */
    public function testIsAvailableKcoEnabled()
    {
        $this->dependencyMocks['config']->expects(static::once())
            ->method('isKcoEnabled')
            ->willReturn(true);

        static::assertTrue($this->model->isAvailable($this->quoteMock));
    }

    /**
     * @covers ::isAvailable
     */
    public function testIsAvailableFromQuote()
    {
        $this->quoteMock->expects(static::once())
            ->method('getStore')
            ->willReturn($this->storeMock);
        $this->dependencyMocks['config']->expects(static::once())
            ->method('isKcoEnabled')
            ->with($this->storeMock);
        $this->dependencyMocks['adapter']->expects(static::once())
            ->method('isAvailable')
            ->willReturn(true);

        static::assertTrue($this->model->isAvailable($this->quoteMock));
    }

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $mockFactory = new MockFactory($this);
        $objectFactory = new TestObjectFactory('');

        $this->infoInstanceMock = $mockFactory->create(
            Info::class,
            [
                'encrypt',
                'decrypt',
                'setAdditionalInformation',
                'hasAdditionalInformation',
                'unsAdditionalInformation',
                'getAdditionalInformation',
                'getMethodInstance'
            ],
            [
                'getOrder',
            ]
        );
        $this->orderMock = $mockFactory->create(Order::class, ['getId']);
        $this->storeMock = $mockFactory->create(Store::class);
        $this->quoteMock = $mockFactory->create(Quote::class);
        $this->quoteMock->method('getStore')
            ->willReturn($this->storeMock);

        $this->model = $objectFactory->create(Kco::class);
        $this->dependencyMocks = $objectFactory->getDependencyMocks();
        $this->dependencyMocks['adapter']->method('getInfoInstance')
            ->willReturn($this->infoInstanceMock);
    }
}
