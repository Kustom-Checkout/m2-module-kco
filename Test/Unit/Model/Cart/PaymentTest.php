<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Cart;

use Klarna\Kco\Model\Cart\Payment;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Cart\Payment
 */
class PaymentTest extends TestCase
{
    /**
     * @var Payment
     */
    private Payment $model;
    /**
     * @var Quote|MockObject
     */
    private $quote;
    /**
     * @var Address|MockObject
     */
    private $address;
    /**
     * @var array
     */
    private array $flagCalls = [];

    /**
     * @covers ::addToQuote
     */
    public function testAddToQuoteCollectsShippingRatesByDefault(): void
    {
        $this->model->addToQuote($this->quote);

        static::assertSame([[true]], $this->flagCalls);
    }

    /**
     * @covers ::addToQuote
     */
    public function testAddToQuoteCanSkipShippingRateCollection(): void
    {
        $this->model->addToQuote($this->quote, false);

        static::assertSame([], $this->flagCalls);
    }

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $this->flagCalls = [];
        $this->address = $this->createMock(Address::class);
        $this->address->method('__call')->willReturnCallback(function (string $name, array $args) {
            if ($name === 'setCollectShippingRates') {
                $this->flagCalls[] = $args;
            }
            return $this->address;
        });
        $payment = $this->createMock(Quote\Payment::class);
        $this->quote = $this->createMock(Quote::class);
        $this->quote->method('isVirtual')->willReturn(false);
        $this->quote->method('getShippingAddress')->willReturn($this->address);
        $this->quote->method('getPayment')->willReturn($payment);

        $this->model = new Payment();
    }
}
