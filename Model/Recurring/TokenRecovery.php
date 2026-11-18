<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Recurring;

use Klarna\Base\Api\OrderRepositoryInterface as KlarnaOrderRepositoryInterface;
use Klarna\Kco\Api\CustomerTokenRepositoryInterface;
use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Klarna\Kco\Model\Payment\Kco;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Recovers the recurring token of already created orders by re-reading the Kustom checkout order
 * (GET /checkout/v3/orders/{id}) and running the {@see TokenPersister}.
 *
 * Idempotent. Never returns, logs or prints the token: callers only get an outcome constant.
 *
 * @internal
 */
class TokenRecovery
{
    public const OUTCOME_PERSISTED = 'persisted';
    public const OUTCOME_ALREADY_PRESENT = 'already present';
    public const OUTCOME_NOT_RECURRING = 'not recurring';
    public const OUTCOME_NO_TOKEN = 'recurring but no token returned';
    public const OUTCOME_NOT_READABLE = 'order no longer readable';
    public const OUTCOME_NOT_KUSTOM = 'not a Kustom order';
    public const OUTCOME_NOT_FOUND = 'order not found';
    public const OUTCOME_ERROR = 'error';

    private const PAGE_SIZE = 200;
    private const NOT_READABLE_CODES = [404, 410];

    /**
     * @var OrderCollectionFactory
     */
    private OrderCollectionFactory $orderCollectionFactory;
    /**
     * @var KlarnaOrderRepositoryInterface
     */
    private KlarnaOrderRepositoryInterface $klarnaOrderRepository;
    /**
     * @var CustomerTokenRepositoryInterface
     */
    private CustomerTokenRepositoryInterface $tokenRepository;
    /**
     * @var Checkout
     */
    private Checkout $checkoutApi;
    /**
     * @var TokenPersister
     */
    private TokenPersister $tokenPersister;
    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;
    /**
     * @var DataObjectFactory
     */
    private DataObjectFactory $dataObjectFactory;

    /**
     * @param OrderCollectionFactory           $orderCollectionFactory
     * @param KlarnaOrderRepositoryInterface   $klarnaOrderRepository
     * @param CustomerTokenRepositoryInterface $tokenRepository
     * @param Checkout                         $checkoutApi
     * @param TokenPersister                   $tokenPersister
     * @param StoreManagerInterface            $storeManager
     * @param DataObjectFactory                $dataObjectFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        OrderCollectionFactory $orderCollectionFactory,
        KlarnaOrderRepositoryInterface $klarnaOrderRepository,
        CustomerTokenRepositoryInterface $tokenRepository,
        Checkout $checkoutApi,
        TokenPersister $tokenPersister,
        StoreManagerInterface $storeManager,
        DataObjectFactory $dataObjectFactory
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->klarnaOrderRepository = $klarnaOrderRepository;
        $this->tokenRepository = $tokenRepository;
        $this->checkoutApi = $checkoutApi;
        $this->tokenPersister = $tokenPersister;
        $this->storeManager = $storeManager;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * Recovers the token of the order with the given increment id
     *
     * @param string $incrementId
     * @return array<string, string> Increment id => outcome
     */
    public function recoverByIncrementId(string $incrementId): array
    {
        try {
            $collection = $this->orderCollectionFactory->create();
            $collection->addFieldToFilter('increment_id', $incrementId);
            $order = $collection->getFirstItem();
        } catch (\Throwable $e) {
            return [$incrementId => self::OUTCOME_ERROR];
        }

        if (!$order || !$order->getId()) {
            return [$incrementId => self::OUTCOME_NOT_FOUND];
        }

        return [$incrementId => $this->recover($order)];
    }

    /**
     * Recovers the tokens of all Kustom Checkout orders created in the range that have no token row yet
     *
     * @param string|null $from Y-m-d H:i:s (UTC), inclusive
     * @param string|null $to   Y-m-d H:i:s (UTC), inclusive
     * @return array<string, string> Increment id => outcome
     */
    public function recoverByDateRange(?string $from, ?string $to): array
    {
        $results = [];
        $lastId = 0;

        try {
            do {
                $collection = $this->orderCollectionFactory->create();
                $collection->getSelect()->join(
                    ['kustom_payment' => $collection->getTable('sales_order_payment')],
                    'kustom_payment.parent_id = main_table.entity_id',
                    []
                )->where('kustom_payment.method = ?', Kco::METHOD_CODE)
                    ->where('main_table.entity_id > ?', $lastId);
                if ($from !== null) {
                    $collection->addFieldToFilter('created_at', ['gteq' => $from]);
                }
                if ($to !== null) {
                    $collection->addFieldToFilter('created_at', ['lteq' => $to]);
                }
                $collection->setOrder('entity_id', 'ASC');
                $collection->setPageSize(self::PAGE_SIZE);

                $orders = $collection->getItems();
                $previousLastId = $lastId;
                $existing = $this->getOrderIdsWithToken($orders);

                foreach ($orders as $order) {
                    $incrementId = (string)$order->getIncrementId();
                    if (isset($existing[(int)$order->getEntityId()])) {
                        $results[$incrementId] = self::OUTCOME_ALREADY_PRESENT;
                        continue;
                    }
                    $results[$incrementId] = $this->recover($order);
                }
                foreach ($orders as $order) {
                    $lastId = max($lastId, (int)$order->getEntityId());
                }
                // Keyset paging: stop when the page is not full or the key did not advance.
            } while (count($orders) === self::PAGE_SIZE && $lastId > $previousLastId);
        } catch (\Throwable $e) {
            $results['(order search)'] = self::OUTCOME_ERROR;
        }

        return $results;
    }

    /**
     * Recovers the token of one order
     *
     * @param OrderInterface $order
     * @return string One of the OUTCOME_ constants
     */
    private function recover(OrderInterface $order): string
    {
        $previousStore = null;
        try {
            $payment = $order->getPayment();
            if ($payment === null || $payment->getMethod() !== Kco::METHOD_CODE) {
                return self::OUTCOME_NOT_KUSTOM;
            }

            try {
                $klarnaOrder = $this->klarnaOrderRepository->getByOrder($order);
            } catch (NoSuchEntityException $e) {
                return self::OUTCOME_NOT_KUSTOM;
            }

            if ($this->hasToken($order)) {
                return self::OUTCOME_ALREADY_PRESENT;
            }

            $kustomOrderId = (string)$klarnaOrder->getKlarnaOrderId();
            if ($kustomOrderId === '') {
                return self::OUTCOME_NOT_KUSTOM;
            }

            // The checkout service reads credentials and API url of the current store
            $previousStore = $this->storeManager->getStore()->getId();
            $this->storeManager->setCurrentStore((int)$order->getStoreId());

            $response = $this->checkoutApi->getOrder($kustomOrderId, (string)$order->getBaseCurrencyCode());
            if (empty($response['is_successful'])) {
                return $this->failedReadOutcome($response);
            }

            $checkout = $this->dataObjectFactory->create(['data' => $response]);
            if ($checkout->getData('recurring') !== true) {
                return self::OUTCOME_NOT_RECURRING;
            }
            if ($checkout->getData('order_id') === null || $checkout->getData('order_id') === '') {
                $checkout->setData('order_id', $kustomOrderId);
            }

            $this->tokenPersister->persist($checkout, $order, $klarnaOrder->getUsedMid());

            return $this->hasToken($order) ? self::OUTCOME_PERSISTED : self::OUTCOME_NO_TOKEN;
        } catch (\Throwable $e) {
            // Neither the message nor the response are used: they could contain the token
            return self::OUTCOME_ERROR;
        } finally {
            if ($previousStore !== null) {
                $this->restoreStore($previousStore);
            }
        }
    }

    /**
     * Maps a failed read of the checkout order to an outcome
     *
     * @param array $response
     * @return string
     */
    private function failedReadOutcome(array $response): string
    {
        $code = $response['response_status_code'] ?? $response['exception_code'] ?? null;
        if (is_numeric($code) && in_array((int)$code, self::NOT_READABLE_CODES, true)) {
            return self::OUTCOME_NOT_READABLE;
        }

        return self::OUTCOME_ERROR;
    }

    /**
     * Whether a token row exists for the order
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function hasToken(OrderInterface $order): bool
    {
        try {
            $this->tokenRepository->getByOrderId((int)$order->getEntityId());
            return true;
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }

    /**
     * Order ids (of the given orders) that already have a token row
     *
     * @param OrderInterface[] $orders
     * @return array<int, true> Order id => true
     */
    private function getOrderIdsWithToken(array $orders): array
    {
        $ids = [];
        foreach ($orders as $order) {
            $ids[] = (int)$order->getEntityId();
        }
        if ($ids === []) {
            return [];
        }

        $result = [];
        foreach (array_keys($this->tokenRepository->getListByOrderIds($ids)) as $orderId) {
            $result[(int)$orderId] = true;
        }

        return $result;
    }

    /**
     * Restores the previous current store, ignoring failures
     *
     * @param int|string $storeId
     * @return void
     */
    private function restoreStore($storeId): void
    {
        try {
            $this->storeManager->setCurrentStore($storeId);
        } catch (\Throwable $e) {
            unset($e);
        }
    }
}
