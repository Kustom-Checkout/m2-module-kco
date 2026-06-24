<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

namespace Klarna\Kco\Test\Unit\Model\Checkout\Validations;

use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use Klarna\Base\Test\Unit\Mock\TestCase;
use Klarna\Kco\Model\Cart\Validations\OrderTotal;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Cart\Validations\OrderTotal
 */
class OrderTotalTest extends TestCase
{
    /**
     * @var OrderTotal
     */
    private $model;
    /**
     * @var MockObject|DataObject
     */
    private $request;
    /**
     * @var MockObject|Quote
     */
    private $quote;

    /**
     * @covers ::getKlarnaTotal
     */
    public function testGetKlarnaTotalUsingOrderAmount(): void
    {
        $this->request->expects(static::once())
            ->method('__call')
            ->with('getOrderAmount')
            ->willReturn('123');

        $this->assertEquals($this->model->getKlarnaTotal($this->request), 123);
    }

    /**
     * @covers ::getKlarnaTotal
     */
    public function testGetKlarnaTotalUsingGetData(): void
    {
        $this->request->expects(static::once())
            ->method('__call')
            ->with('getOrderAmount')
            ->willReturn(null);
        $this->request->expects(static::once())
            ->method('getData')
            ->willReturn('123');

        $this->assertEquals($this->model->getKlarnaTotal($this->request), 123);
    }

    /**
     * @covers ::getQuoteTotal
     */
    public function testGetQuoteTotal(): void
    {
        $this->dependencyMocks['dataConverter']
            ->method('toApiFloat')
            ->willReturnCallback(fn($float) =>
                match($float) {
                    1.35 => 135,
                    0.12 => 12
                }
            );

        $this->quote->expects(static::once())
            ->method('__call')
            ->with('getGrandTotal')
            ->willReturn(1.35);
        $this->dependencyMocks['giftWrapping']
            ->method('getAdditionalGwTax')
            ->willReturn(0);

        $this->assertEquals($this->model->getQuoteTotal($this->request, $this->quote), 135);
    }

    /**
     * @covers ::validate
     */
    public function testValidateThrowsExceptionWithNotMatchingOrderTotals(): void
    {
        $this->expectException(\Klarna\Base\Exception::class);
        $this->expectExceptionMessage(
            "Order total does not match for order #AN-ORDER-ID. Kustom total is 123 vs Magento total 135"
        );
        // Klarna Total
        $this->request->expects(static::once())
            ->method('__call')
            ->with('getOrderAmount')
            ->willReturn('123');

        // Magento Total
        $this->dependencyMocks['dataConverter']
            ->method('toApiFloat')
            ->willReturnCallback(fn($float) =>
                match($float) {
                    1.35 => 135,
                    0.12 => 12
                }
            );

        $this->quote->expects(static::once())
            ->method('__call')
            ->with('getGrandTotal')
            ->willReturn(1.35);
        $this->dependencyMocks['giftWrapping']
            ->method('getAdditionalGwTax')
            ->willReturn(0);

        $this->model->validate($this->request, $this->quote);
    }

    /**
     * @covers ::validate
     */
    public function testValidateWithMatchingOrderTotals(): void
    {
        // Klarna Total
        $this->request->expects(static::once())
            ->method('__call')
            ->with('getOrderAmount')
            ->willReturn('135');

        // Magento Total
        $this->dependencyMocks['dataConverter']
            ->method('toApiFloat')
            ->willReturnCallback(fn($float) =>
                match($float) {
                    1.35 => 135,
                    0.12 => 12
                }
            );

        $this->quote->expects(static::once())
            ->method('__call')
            ->with('getGrandTotal')
            ->willReturn(1.35);
        $this->dependencyMocks['giftWrapping']
            ->method('getAdditionalGwTax')
            ->willReturn(0);

        $this->model->validate($this->request, $this->quote);
    }

    protected function setUp(): void
    {
        $this->model           = parent::setUpMocks(OrderTotal::class);
        $this->request         = $this->mockFactory->create(DataObject::class, [
            '__call',
            'getData'
        ]);
        $this->quote           = $this->mockFactory->create(Quote::class, [
            '__call',
            'getShippingAddress',
            'isVirtual',
            'getReservedOrderId'
        ]);

        $this->quote->method('getReservedOrderId')->willReturn('AN-ORDER-ID');
    }
}
