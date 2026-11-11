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
use Klarna\Kco\Api\RecurringResolverInterface;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\CartInterface;
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
     * @var RecurringResolverInterface
     */
    private RecurringResolverInterface $resolver;

    /**
     * @param CustomerTokenRepositoryInterface $repository
     * @param CustomerTokenInterfaceFactory    $tokenFactory
     * @param LoggerInterface                  $logger
     * @param RecurringResolverInterface       $resolver
     */
    public function __construct(
        CustomerTokenRepositoryInterface $repository,
        CustomerTokenInterfaceFactory $tokenFactory,
        LoggerInterface $logger,
        RecurringResolverInterface $resolver
    ) {
        $this->repository = $repository;
        $this->tokenFactory = $tokenFactory;
        $this->logger = $logger;
        $this->resolver = $resolver;
    }

    /**
     * Stores the recurring token of the checkout order, if there is one
     *
     * @param DataObject         $checkout The Kustom checkout order (response of GET /checkout/v3/orders/{id})
     * @param OrderInterface     $order    The Magento order created for it
     * @param string|null        $mid      Merchant id used for the order (klarna_core_order.used_mid)
     * @param CartInterface|null $quote    If given, the resolver result is compared with the Kustom order (warning)
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function persist(
        DataObject $checkout,
        OrderInterface $order,
        ?string $mid = null,
        ?CartInterface $quote = null
    ): void {
        $recurring = $checkout->getData('recurring') === true;
        $token = $checkout->getData('recurring_token');
        $hasToken = is_string($token) && $token !== '';
        $kustomOrderId = (string)$checkout->getData('order_id');

        $this->warnOnResolverMismatch($quote, $recurring, $kustomOrderId, $order);

        if (!$recurring) {
            return;
        }

        if (!$hasToken) {
            $this->logger->warning(sprintf(
                'The Kustom order %s is recurring but has no recurring token. Increment id: %s',
                $kustomOrderId,
                (string)$order->getIncrementId()
            ));
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
            ->setStatus('active')
            ->setPaymentMethodType(null);

        $this->repository->saveIfNotExists($customerToken);
    }

    /**
     * Warns when the resolver says recurring but the Kustom order is not recurring. A recurring order without a
     * token is warned about in persist(), so it is skipped here (one warning per case). Isolated: a throwing
     * merchant resolver must never prevent the token from being saved.
     *
     * @param CartInterface|null $quote
     * @param bool               $kustomOrderIsRecurring
     * @param string             $kustomOrderId
     * @param OrderInterface     $order
     * @return void
     */
    private function warnOnResolverMismatch(
        ?CartInterface $quote,
        bool $kustomOrderIsRecurring,
        string $kustomOrderId,
        OrderInterface $order
    ): void {
        if ($quote === null || $kustomOrderIsRecurring) {
            return;
        }

        try {
            if (!$this->resolver->isRecurring($quote)) {
                return;
            }

            $this->logger->warning(sprintf(
                'The quote %s is recurring but the Kustom order %s is not recurring. Increment id: %s',
                (string)$quote->getId(),
                $kustomOrderId,
                (string)$order->getIncrementId()
            ));
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf(
                'The recurring resolver check failed for Kustom order %s: %s',
                $kustomOrderId,
                get_class($e)
            ));
        }
    }
}
