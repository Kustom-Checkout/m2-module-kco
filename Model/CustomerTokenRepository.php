<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model;

use Klarna\Kco\Api\CustomerTokenRepositoryInterface;
use Klarna\Kco\Api\Data\CustomerTokenInterface;
use Klarna\Kco\Model\ResourceModel\CustomerToken as CustomerTokenResource;
use Klarna\Kco\Model\ResourceModel\CustomerToken\CollectionFactory;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Exception messages never contain the token or the driver message (which can echo column values).
 *
 * @internal
 */
class CustomerTokenRepository implements CustomerTokenRepositoryInterface
{
    /**
     * @var CustomerTokenFactory
     */
    private CustomerTokenFactory $customerTokenFactory;
    /**
     * @var CustomerTokenResource
     */
    private CustomerTokenResource $resourceModel;
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @param CustomerTokenFactory $customerTokenFactory
     * @param CustomerTokenResource $resourceModel
     * @param CollectionFactory $collectionFactory
     * @codeCoverageIgnore
     */
    public function __construct(
        CustomerTokenFactory $customerTokenFactory,
        CustomerTokenResource $resourceModel,
        CollectionFactory $collectionFactory
    ) {
        $this->customerTokenFactory = $customerTokenFactory;
        $this->resourceModel = $resourceModel;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function save(CustomerTokenInterface $customerToken): CustomerTokenInterface
    {
        try {
            $this->resourceModel->save($customerToken);
        } catch (DuplicateException | AlreadyExistsException $e) {
            throw new CouldNotSaveException(
                __('A customer token for Kustom order "%1" already exists.', $customerToken->getKustomOrderId())
            );
        } catch (\Throwable $e) {
            throw new CouldNotSaveException(__('The customer token could not be saved.'));
        }
        return $customerToken;
    }

    /**
     * @inheritdoc
     */
    public function saveIfNotExists(CustomerTokenInterface $customerToken): CustomerTokenInterface
    {
        $kustomOrderId = (string)$customerToken->getKustomOrderId();
        try {
            return $this->getByKustomOrderId($kustomOrderId);
        } catch (NoSuchEntityException $e) {
            // Not stored yet, continue.
            unset($e);
        }

        try {
            $this->resourceModel->save($customerToken);
        } catch (DuplicateException | AlreadyExistsException $e) {
            // Lost a race against a parallel request: the stored row wins.
            try {
                return $this->getByKustomOrderId($kustomOrderId);
            } catch (NoSuchEntityException $notFound) {
                throw new CouldNotSaveException(__('The customer token could not be saved.'));
            }
        } catch (\Throwable $e) {
            throw new CouldNotSaveException(__('The customer token could not be saved.'));
        }
        return $customerToken;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $entityId): CustomerTokenInterface
    {
        return $this->loadBy(CustomerTokenInterface::ENTITY_ID, $entityId);
    }

    /**
     * @inheritdoc
     */
    public function getByKustomOrderId(string $kustomOrderId): CustomerTokenInterface
    {
        return $this->loadBy(CustomerTokenInterface::KUSTOM_ORDER_ID, $kustomOrderId);
    }

    /**
     * @inheritdoc
     */
    public function getByOrderId(int $orderId): CustomerTokenInterface
    {
        return $this->loadBy(CustomerTokenInterface::ORDER_ID, $orderId);
    }

    /**
     * @inheritdoc
     */
    public function getByOrderIncrementId(string $incrementId): CustomerTokenInterface
    {
        return $this->loadBy(CustomerTokenInterface::INCREMENT_ID, $incrementId);
    }

    /**
     * @inheritdoc
     */
    public function getListByOrderIds(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_map('intval', $orderIds)));
        if ($orderIds === []) {
            return [];
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(CustomerTokenInterface::ORDER_ID, ['in' => $orderIds]);

        $result = [];
        foreach ($collection->getItems() as $item) {
            $result[(int)$item->getData(CustomerTokenInterface::ORDER_ID)] = $item;
        }
        return $result;
    }

    /**
     * Load one token by a column
     *
     * @param string $field
     * @param string|int $value
     * @return CustomerTokenInterface
     * @throws NoSuchEntityException
     */
    private function loadBy(string $field, $value): CustomerTokenInterface
    {
        /** @var CustomerToken $customerToken */
        $customerToken = $this->customerTokenFactory->create();
        $this->resourceModel->load($customerToken, $value, $field);
        if (!$customerToken->getId()) {
            throw new NoSuchEntityException(__('The customer token does not exist.'));
        }
        return $customerToken;
    }
}
