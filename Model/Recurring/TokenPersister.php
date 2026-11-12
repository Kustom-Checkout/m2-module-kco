<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Recurring;

use Klarna\Kco\Api\CustomerTokenRepositoryInterface;
use Klarna\Kco\Api\Data\CustomerTokenInterface;
use Klarna\Kco\Api\Data\CustomerTokenInterfaceFactory;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Framework\DataObject;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Stores the recurring token of a completed Kustom checkout order. Idempotent: an already stored token is kept.
 *
 * Never logs the token. Exceptions are NOT caught here: the caller decides (order creation must not fail).
 *
 * @internal
 */
class TokenPersister
{
    /**
     * @var CustomerTokenRepositoryInterface
     */
    private CustomerTokenRepositoryInterface $repository;
    /**
     * @var CustomerTokenInterfaceFactory
     */
    private CustomerTokenInterfaceFactory $tokenFactory;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param CustomerTokenRepositoryInterface $repository
     * @param CustomerTokenInterfaceFactory    $tokenFactory
     * @param LoggerInterface                  $logger
     */
    public function __construct(
        CustomerTokenRepositoryInterface $repository,
        CustomerTokenInterfaceFactory $tokenFactory,
        LoggerInterface $logger
    ) {
        $this->repository = $repository;
        $this->tokenFactory = $tokenFactory;
        $this->logger = $logger;
    }

    /**
     * Stores the recurring token of the checkout order, if there is one
     *
     * @param DataObject     $checkout           The Kustom checkout order (GET /checkout/v3/orders/{id})
     * @param OrderInterface $order              The Magento order created for it
     * @param string|null    $mid                Merchant id used (klarna_core_order.used_mid)
     * @param bool           $requestedRecurring Stored klarna_kco_quote.is_recurring (diagnostic only)
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function persist(
        DataObject $checkout,
        OrderInterface $order,
        ?string $mid = null,
        bool $requestedRecurring = false
    ): void {
        $recurring = $checkout->getData('recurring') === true;
        $token = $checkout->getData('recurring_token');
        $hasToken = is_string($token) && $token !== '';
        $kustomOrderId = (string)$checkout->getData('order_id');

        $warned = false;
        if ($requestedRecurring && (!$recurring || !$hasToken)) {
            $this->warnRequestedButNoToken($kustomOrderId, $order);
            $warned = true;
        }

        if (!$recurring) {
            return;
        }

        if (!$hasToken) {
            if (!$warned) {
                $this->logger->warning(sprintf(
                    'The Kustom order %s is recurring but has no recurring token. Increment id: %s',
                    $kustomOrderId,
                    (string)$order->getIncrementId()
                ));
            }
            return;
        }

        if ($kustomOrderId === '') {
            $this->logger->warning(
                'The recurring token could not be stored: the Kustom order id is missing. Increment id: '
                . (string)$order->getIncrementId()
            );
            return;
        }

        /** @var CustomerTokenInterface $customerToken */
        $customerToken = $this->tokenFactory->create();
        $customerId = $order->getCustomerId();
        $customerToken->setToken($token)
            ->setKustomOrderId($kustomOrderId)
            ->setOrderId((int)$order->getEntityId())
            ->setIncrementId((string)$order->getIncrementId())
            ->setCustomerId($customerId ? (int)$customerId : null)
            ->setStoreId($order->getStoreId() !== null ? (int)$order->getStoreId() : null)
            ->setMerchantId($mid)
            ->setStatus(CustomerTokenInterface::STATUS_ACTIVE)
            ->setPaymentMethodType(null);

        $this->repository->saveIfNotExists($customerToken);
    }

    /**
     * Warns that a recurring checkout was requested but Kustom returned no token
     *
     * Ids only, never the token.
     *
     * @param string         $kustomOrderId
     * @param OrderInterface $order
     * @return void
     */
    private function warnRequestedButNoToken(string $kustomOrderId, OrderInterface $order): void
    {
        try {
            $this->logger->warning(sprintf(
                'The Kustom order %s was requested as recurring but has no recurring token. '
                . 'Increment id: %s, store id: %s',
                $kustomOrderId,
                (string)$order->getIncrementId(),
                (string)$order->getStoreId()
            ));
        } catch (\Throwable $e) {
            return;
        }
    }
}
