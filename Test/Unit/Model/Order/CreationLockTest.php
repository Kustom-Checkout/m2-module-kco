<?php

/**
 * Copyright 2025 Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Order;

use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use Klarna\Kco\Model\Order\CreationLock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Order\CreationLock
 */
class CreationLockTest extends TestCase
{
    private const KLARNA_ORDER_ID = 'c5909ea7-78d0-2042-9d49-8e20f9622aa1';
    private const LOCK_NAME = 'kustom_order_create_' . self::KLARNA_ORDER_ID;

    /**
     * @var CreationLock
     */
    private $model;

    /**
     * @var MockObject[]
     */
    private $dependencyMocks;

    protected function setUp(): void
    {
        $objectFactory = new TestObjectFactory('');
        $this->model = $objectFactory->create(CreationLock::class);
        $this->dependencyMocks = $objectFactory->getDependencyMocks();
    }

    /**
     * @covers ::acquire
     */
    public function testAcquireReturnsTrueWhenLockIsAcquiredWithDefaultTimeout(): void
    {
        $this->dependencyMocks['lockManager']
            ->expects(static::once())
            ->method('lock')
            ->with(self::LOCK_NAME, CreationLock::DEFAULT_TIMEOUT)
            ->willReturn(true);

        static::assertTrue($this->model->acquire(self::KLARNA_ORDER_ID));
    }

    /**
     * @covers ::acquire
     */
    public function testAcquireReturnsFalseWhenLockIsHeldByConcurrentRequest(): void
    {
        $this->dependencyMocks['lockManager']
            ->expects(static::once())
            ->method('lock')
            ->with(self::LOCK_NAME, 3)
            ->willReturn(false);

        static::assertFalse($this->model->acquire(self::KLARNA_ORDER_ID, 3));
    }

    /**
     * @covers ::release
     */
    public function testReleaseUnlocksTheLockOfTheGivenKustomOrderId(): void
    {
        $this->dependencyMocks['lockManager']
            ->expects(static::once())
            ->method('unlock')
            ->with(self::LOCK_NAME)
            ->willReturn(true);

        $this->model->release(self::KLARNA_ORDER_ID);
    }
}
