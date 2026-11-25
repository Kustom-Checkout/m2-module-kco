<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model;

use Klarna\Kco\Api\Data\CustomerTokenInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * @internal
 */
class CustomerToken extends AbstractModel implements CustomerTokenInterface
{
    /**
     * Constructor
     *
     * @codeCoverageIgnore
     * @codingStandardsIgnoreLine
     */
    protected function _construct(): void
    {
        $this->_init(\Klarna\Kco\Model\ResourceModel\CustomerToken::class);
    }

    /**
     * @inheritdoc
     */
    public function getEntityId(): ?int
    {
        return $this->getNullableInt(self::ENTITY_ID);
    }

    /**
     * @inheritdoc
     */
    public function getToken(): ?string
    {
        return $this->getNullableString(self::TOKEN);
    }

    /**
     * @inheritdoc
     */
    public function setToken(string $token): CustomerTokenInterface
    {
        return $this->setData(self::TOKEN, $token);
    }

    /**
     * @inheritdoc
     */
    public function getKustomOrderId(): ?string
    {
        return $this->getNullableString(self::KUSTOM_ORDER_ID);
    }

    /**
     * @inheritdoc
     */
    public function setKustomOrderId(string $kustomOrderId): CustomerTokenInterface
    {
        return $this->setData(self::KUSTOM_ORDER_ID, $kustomOrderId);
    }

    /**
     * @inheritdoc
     */
    public function getOrderId(): ?int
    {
        return $this->getNullableInt(self::ORDER_ID);
    }

    /**
     * @inheritdoc
     */
    public function setOrderId(int $orderId): CustomerTokenInterface
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @inheritdoc
     */
    public function getIncrementId(): ?string
    {
        return $this->getNullableString(self::INCREMENT_ID);
    }

    /**
     * @inheritdoc
     */
    public function setIncrementId(string $incrementId): CustomerTokenInterface
    {
        return $this->setData(self::INCREMENT_ID, $incrementId);
    }

    /**
     * @inheritdoc
     */
    public function getCustomerId(): ?int
    {
        return $this->getNullableInt(self::CUSTOMER_ID);
    }

    /**
     * @inheritdoc
     */
    public function setCustomerId(?int $customerId): CustomerTokenInterface
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    /**
     * @inheritdoc
     */
    public function getStoreId(): ?int
    {
        return $this->getNullableInt(self::STORE_ID);
    }

    /**
     * @inheritdoc
     */
    public function setStoreId(?int $storeId): CustomerTokenInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritdoc
     */
    public function getMerchantId(): ?string
    {
        return $this->getNullableString(self::MERCHANT_ID);
    }

    /**
     * @inheritdoc
     */
    public function setMerchantId(?string $merchantId): CustomerTokenInterface
    {
        return $this->setData(self::MERCHANT_ID, $merchantId);
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): ?string
    {
        return $this->getNullableString(self::STATUS);
    }

    /**
     * @inheritdoc
     */
    public function setStatus(string $status): CustomerTokenInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritdoc
     */
    public function getPaymentMethodType(): ?string
    {
        return $this->getNullableString(self::PAYMENT_METHOD_TYPE);
    }

    /**
     * @inheritdoc
     */
    public function setPaymentMethodType(?string $paymentMethodType): CustomerTokenInterface
    {
        return $this->setData(self::PAYMENT_METHOD_TYPE, $paymentMethodType);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getNullableString(self::CREATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function getCancelledAt(): ?string
    {
        return $this->getNullableString(self::CANCELLED_AT);
    }

    /**
     * @inheritdoc
     */
    public function setCancelledAt(?string $cancelledAt): CustomerTokenInterface
    {
        return $this->setData(self::CANCELLED_AT, $cancelledAt);
    }

    /**
     * Get a value as nullable int
     *
     * @param string $key
     * @return int|null
     */
    private function getNullableInt(string $key): ?int
    {
        $value = $this->_getData($key);
        return $value === null || $value === '' ? null : (int)$value;
    }

    /**
     * Get a value as nullable string
     *
     * @param string $key
     * @return string|null
     */
    private function getNullableString(string $key): ?string
    {
        $value = $this->_getData($key);
        return $value === null ? null : (string)$value;
    }
}
