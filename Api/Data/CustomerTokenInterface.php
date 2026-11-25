<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Api\Data;

/**
 * Recurring customer token. getToken() always returns the plain value; it is stored encrypted.
 *
 * @api
 */
interface CustomerTokenInterface
{
    public const ENTITY_ID = 'entity_id';
    public const TOKEN = 'token';
    public const KUSTOM_ORDER_ID = 'kustom_order_id';
    public const ORDER_ID = 'order_id';
    public const INCREMENT_ID = 'increment_id';
    public const CUSTOMER_ID = 'customer_id';
    public const STORE_ID = 'store_id';
    public const MERCHANT_ID = 'merchant_id';
    public const STATUS = 'status';
    public const PAYMENT_METHOD_TYPE = 'payment_method_type';
    public const CREATED_AT = 'created_at';
    public const CANCELLED_AT = 'cancelled_at';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the entity id
     *
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * Get the decrypted token
     *
     * @return string|null
     */
    public function getToken(): ?string;

    /**
     * Set the plain token (it gets encrypted when saved)
     *
     * @param string $token
     * @return $this
     */
    public function setToken(string $token): self;

    /**
     * Get the Kustom checkout order id
     *
     * @return string|null
     */
    public function getKustomOrderId(): ?string;

    /**
     * Set the Kustom checkout order id
     *
     * @param string $kustomOrderId
     * @return $this
     */
    public function setKustomOrderId(string $kustomOrderId): self;

    /**
     * Get the Magento order entity id
     *
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * Set the Magento order entity id
     *
     * @param int $orderId
     * @return $this
     */
    public function setOrderId(int $orderId): self;

    /**
     * Get the increment id of the original Magento order
     *
     * @return string|null
     */
    public function getIncrementId(): ?string;

    /**
     * Set the increment id of the original Magento order
     *
     * @param string $incrementId
     * @return $this
     */
    public function setIncrementId(string $incrementId): self;

    /**
     * Get the customer id (null for guests)
     *
     * @return int|null
     */
    public function getCustomerId(): ?int;

    /**
     * Set the customer id (null for guests)
     *
     * @param int|null $customerId
     * @return $this
     */
    public function setCustomerId(?int $customerId): self;

    /**
     * Get the store id
     *
     * @return int|null
     */
    public function getStoreId(): ?int;

    /**
     * Set the store id
     *
     * @param int|null $storeId
     * @return $this
     */
    public function setStoreId(?int $storeId): self;

    /**
     * Get the merchant id (MID, the API username used for the order)
     *
     * @return string|null
     */
    public function getMerchantId(): ?string;

    /**
     * Set the merchant id (MID, the API username used for the order)
     *
     * @param string|null $merchantId
     * @return $this
     */
    public function setMerchantId(?string $merchantId): self;

    /**
     * Get the status (STATUS_ACTIVE or STATUS_CANCELLED)
     *
     * @return string|null
     */
    public function getStatus(): ?string;

    /**
     * Set the status
     *
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * Get the payment method type
     *
     * @return string|null
     */
    public function getPaymentMethodType(): ?string;

    /**
     * Set the payment method type
     *
     * @param string|null $paymentMethodType
     * @return $this
     */
    public function setPaymentMethodType(?string $paymentMethodType): self;

    /**
     * Get the creation time
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Get the cancellation time
     *
     * @return string|null
     */
    public function getCancelledAt(): ?string;

    /**
     * Set the cancellation time
     *
     * @param string|null $cancelledAt
     * @return $this
     */
    public function setCancelledAt(?string $cancelledAt): self;
}
