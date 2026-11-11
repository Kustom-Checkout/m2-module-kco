<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Api;

use Klarna\Kco\Api\Data\CustomerTokenInterface;

/**
 * Stores recurring customer tokens. Callers only ever handle plain tokens; encryption is internal.
 *
 * @api
 */
interface CustomerTokenRepositoryInterface
{
    /**
     * Save a token. Fails if the Kustom order id is already stored.
     *
     * @param CustomerTokenInterface $customerToken
     * @return CustomerTokenInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(CustomerTokenInterface $customerToken): CustomerTokenInterface;

    /**
     * Save a token unless one exists for its Kustom order id. Duplicates (also races) are not an error.
     *
     * @param CustomerTokenInterface $customerToken
     * @return CustomerTokenInterface The stored token: the existing one or the newly saved one
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function saveIfNotExists(CustomerTokenInterface $customerToken): CustomerTokenInterface;

    /**
     * Load by entity id
     *
     * @param int $entityId
     * @return CustomerTokenInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $entityId): CustomerTokenInterface;

    /**
     * Load by Kustom checkout order id
     *
     * @param string $kustomOrderId
     * @return CustomerTokenInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByKustomOrderId(string $kustomOrderId): CustomerTokenInterface;

    /**
     * Load by Magento order entity id
     *
     * @param int $orderId
     * @return CustomerTokenInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByOrderId(int $orderId): CustomerTokenInterface;

    /**
     * Load by Magento order increment id
     *
     * @param string $incrementId
     * @return CustomerTokenInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByOrderIncrementId(string $incrementId): CustomerTokenInterface;

    /**
     * Load the tokens of several orders with one query
     *
     * @param int[] $orderIds
     * @return CustomerTokenInterface[] Keyed by Magento order entity id
     */
    public function getListByOrderIds(array $orderIds): array;
}
