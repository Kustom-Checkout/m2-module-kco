<?php

/**
 * Copyright 2025 Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Model\Order;

use Klarna\Kco\Model\Order\CreationLock;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\Backend\Database;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The test framework replaces the database locker with a dummy which always grants the lock, so the real database
 * locker is created explicitly here. Every locker gets its own resource connection and therefore its own database
 * connection, which is how two concurrent requests behave.
 *
 * @magentoAppIsolation enabled
 */
class CreationLockTest extends TestCase
{
    private const KLARNA_ORDER_ID = 'creation-lock-test-order-id';
    private const OTHER_KLARNA_ORDER_ID = 'creation-lock-test-other-order-id';

    /**
     * @var CreationLock
     */
    private $firstRequestLock;

    /**
     * @var CreationLock
     */
    private $secondRequestLock;

    protected function setUp(): void
    {
        $this->firstRequestLock = $this->createLockWithOwnConnection();
        $this->secondRequestLock = $this->createLockWithOwnConnection();
    }

    protected function tearDown(): void
    {
        $this->firstRequestLock->release(self::KLARNA_ORDER_ID);
        $this->secondRequestLock->release(self::KLARNA_ORDER_ID);
        $this->secondRequestLock->release(self::OTHER_KLARNA_ORDER_ID);
    }

    public function testConcurrentRequestCanNotAcquireTheLockOfTheSameKustomOrder(): void
    {
        static::assertTrue($this->firstRequestLock->acquire(self::KLARNA_ORDER_ID, 0));
        static::assertFalse($this->secondRequestLock->acquire(self::KLARNA_ORDER_ID, 0));
    }

    public function testConcurrentRequestCanAcquireTheLockAfterItWasReleased(): void
    {
        static::assertTrue($this->firstRequestLock->acquire(self::KLARNA_ORDER_ID, 0));
        $this->firstRequestLock->release(self::KLARNA_ORDER_ID);

        static::assertTrue($this->secondRequestLock->acquire(self::KLARNA_ORDER_ID, 0));
    }

    public function testLockDoesNotBlockOtherKustomOrders(): void
    {
        static::assertTrue($this->firstRequestLock->acquire(self::KLARNA_ORDER_ID, 0));
        static::assertTrue($this->secondRequestLock->acquire(self::OTHER_KLARNA_ORDER_ID, 0));
    }

    public function testCreationLockIsConfiguredToUseTheDatabaseLocker(): void
    {
        $arguments = Bootstrap::getObjectManager()->get(ConfigInterface::class)->getArguments(CreationLock::class);

        static::assertSame(Database::class, $arguments['lockManager']['instance'] ?? null);
    }

    /**
     * Creating a creation lock which uses the real database locker with its own database connection
     *
     * @return CreationLock
     */
    private function createLockWithOwnConnection(): CreationLock
    {
        $objectManager = Bootstrap::getObjectManager();
        $locker = new Database(
            $objectManager->create(ResourceConnection::class),
            $objectManager->get(DeploymentConfig::class)
        );

        return new CreationLock($locker);
    }
}
