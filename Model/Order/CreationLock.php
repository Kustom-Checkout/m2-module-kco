<?php

/**
 * Copyright 2025 Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Model\Order;

use Magento\Framework\Lock\LockManagerInterface;

/**
 * Serialising the Magento order creation per Kustom order id
 *
 * The confirmation controller and the push controller can be executed concurrently for the same checkout.
 * The duplicate checks during the order creation are simple reads whose data is only written after the order
 * has been placed, so without a lock both requests can pass them and place two Magento orders.
 * Magento\Quote\Model\CartMutex only exists from Magento 2.4.7 onwards, so we cannot rely on it.
 */
class CreationLock
{
    /**
     * Prefix of the lock name
     */
    private const LOCK_PREFIX = 'kustom_order_create_';

    /**
     * Default amount of seconds we wait for a concurrent request to finish the order creation
     */
    public const DEFAULT_TIMEOUT = 15;

    /**
     * @var LockManagerInterface
     */
    private LockManagerInterface $lockManager;

    /**
     * @param LockManagerInterface $lockManager
     */
    public function __construct(LockManagerInterface $lockManager)
    {
        $this->lockManager = $lockManager;
    }

    /**
     * Acquiring the order creation lock for the given Kustom order id
     *
     * Returns false when the lock could not be acquired within the given timeout, which means that a
     * concurrent request is still creating the order.
     *
     * @param string $klarnaOrderId
     * @param int $timeout
     * @return bool
     */
    public function acquire(string $klarnaOrderId, int $timeout = self::DEFAULT_TIMEOUT): bool
    {
        return $this->lockManager->lock($this->getLockName($klarnaOrderId), $timeout);
    }

    /**
     * Releasing the order creation lock for the given Kustom order id
     *
     * @param string $klarnaOrderId
     */
    public function release(string $klarnaOrderId): void
    {
        $this->lockManager->unlock($this->getLockName($klarnaOrderId));
    }

    /**
     * Getting back the lock name for the given Kustom order id
     *
     * @param string $klarnaOrderId
     * @return string
     */
    private function getLockName(string $klarnaOrderId): string
    {
        return self::LOCK_PREFIX . $klarnaOrderId;
    }
}
