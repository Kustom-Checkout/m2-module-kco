<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Recurring;

use Klarna\Kco\Api\RecurringResolverInterface;
use Klarna\Kco\Model\Recurring\CompositeResolver;
use Magento\Quote\Api\Data\CartInterface;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Recurring\CompositeResolver
 */
class CompositeResolverTest extends TestCase
{
    /**
     * @covers ::isRecurring
     */
    public function testEmptyPoolReturnsFalse(): void
    {
        $model = new CompositeResolver();
        static::assertFalse($model->isRecurring($this->createStub(CartInterface::class)));
    }

    /**
     * @covers ::isRecurring
     */
    public function testAnyTrueReturnsTrueAndShortCircuits(): void
    {
        $quote = $this->createStub(CartInterface::class);
        $first = $this->createMock(RecurringResolverInterface::class);
        $first->expects($this->once())->method('isRecurring')->with($quote)->willReturn(false);
        $second = $this->createMock(RecurringResolverInterface::class);
        $second->expects($this->once())->method('isRecurring')->with($quote)->willReturn(true);
        $third = $this->createMock(RecurringResolverInterface::class);
        $third->expects($this->never())->method('isRecurring');

        $model = new CompositeResolver(['a' => $first, 'b' => $second, 'c' => $third]);
        static::assertTrue($model->isRecurring($quote));
    }

    /**
     * @covers ::isRecurring
     */
    public function testAllFalseReturnsFalse(): void
    {
        $quote = $this->createStub(CartInterface::class);
        $first = $this->createStub(RecurringResolverInterface::class);
        $first->method('isRecurring')->willReturn(false);
        $second = $this->createStub(RecurringResolverInterface::class);
        $second->method('isRecurring')->willReturn(false);

        $model = new CompositeResolver([$first, $second]);
        static::assertFalse($model->isRecurring($quote));
    }

    /**
     * @covers ::__construct
     */
    public function testInvalidEntryThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CompositeResolver(['bad' => new \stdClass()]);
    }
}
