<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Recurring;

use Klarna\Base\Api\OrderRepositoryInterface as KlarnaOrderRepositoryInterface;
use Klarna\Base\Model\Order as KlarnaOrder;
use Klarna\Kco\Api\CustomerTokenRepositoryInterface;
use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Klarna\Kco\Model\Recurring\TokenPersister;
use Klarna\Kco\Model\Recurring\TokenRecovery;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Recurring\TokenRecovery
 */
class TokenRecoveryTest extends TestCase
{
    private const SECRET = 'tok_secret_value_123';
    private const INCREMENT_ID = '000000042';

    /**
     * @var CollectionFactory|MockObject
     */
    private $collectionFactory;
    /**
     * @var Collection|MockObject
     */
    private $collection;
    /**
     * @var KlarnaOrderRepositoryInterface|MockObject
     */
    private $klarnaOrderRepository;
    /**
     * @var CustomerTokenRepositoryInterface|MockObject
     */
    private $tokenRepository;
    /**
     * @var Checkout|MockObject
     */
    private $checkoutApi;
    /**
     * @var TokenPersister|MockObject
     */
    private $persister;
    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;
    /**
     * @var TokenRecovery
     */
    private TokenRecovery $model;

    protected function setUp(): void
    {
        $this->collection = $this->createMock(Collection::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->collectionFactory->method('create')->willReturn($this->collection);
        $this->klarnaOrderRepository = $this->createMock(KlarnaOrderRepositoryInterface::class);
        $this->tokenRepository = $this->createMock(CustomerTokenRepositoryInterface::class);
        $this->checkoutApi = $this->createMock(Checkout::class);
        $this->persister = $this->createMock(TokenPersister::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($store);
        $dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $dataObjectFactory->method('create')->willReturnCallback(
            static fn(array $args): DataObject => new DataObject($args['data'])
        );

        $this->model = new TokenRecovery(
            $this->collectionFactory,
            $this->klarnaOrderRepository,
            $this->tokenRepository,
            $this->checkoutApi,
            $this->persister,
            $this->storeManager,
            $dataObjectFactory
        );
    }

    private function order(string $method = 'klarna_kco', int $id = 7, ?string $incrementId = null): Order
    {
        $payment = $this->createMock(OrderPaymentInterface::class);
        $payment->method('getMethod')->willReturn($method);
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn($id);
        $order->method('getEntityId')->willReturn($id);
        $order->method('getIncrementId')->willReturn($incrementId ?? self::INCREMENT_ID);
        $order->method('getStoreId')->willReturn(3);
        $order->method('getBaseCurrencyCode')->willReturn('SEK');
        $order->method('getPayment')->willReturn($payment);

        return $order;
    }

    private function klarnaOrder(): KlarnaOrder
    {
        $klarnaOrder = $this->createMock(KlarnaOrder::class);
        $klarnaOrder->method('getKlarnaOrderId')->willReturn('kustom-1');
        $klarnaOrder->method('getUsedMid')->willReturn('mid-9');

        return $klarnaOrder;
    }

    private function givenOrder(Order $order): void
    {
        $this->collection->method('getFirstItem')->willReturn($order);
    }

    /**
     * @covers ::recoverByIncrementId
     */
    public function testPersisted(): void
    {
        $order = $this->order();
        $this->givenOrder($order);
        $this->klarnaOrderRepository->method('getByOrder')->willReturn($this->klarnaOrder());
        $this->tokenRepository->method('getByOrderId')->willReturnOnConsecutiveCalls(
            $this->throwException(new NoSuchEntityException()),
            $this->createMock(\Klarna\Kco\Api\Data\CustomerTokenInterface::class)
        );
        $this->storeManager->expects($this->exactly(2))->method('setCurrentStore')
            ->willReturnCallback(static function ($storeId): void {
                static $calls = [];
                $calls[] = $storeId;
            });
        $this->checkoutApi->expects($this->once())->method('getOrder')->with('kustom-1', 'SEK')->willReturn([
            'is_successful' => true,
            'order_id' => 'kustom-1',
            'recurring' => true,
            'recurring_token' => self::SECRET,
        ]);
        $this->persister->expects($this->once())->method('persist')->with(
            $this->callback(static fn(DataObject $c): bool => $c->getData('recurring_token') === self::SECRET),
            $order,
            'mid-9',
            null
        );

        $result = $this->model->recoverByIncrementId(self::INCREMENT_ID);

        $this->assertSame([self::INCREMENT_ID => TokenRecovery::OUTCOME_PERSISTED], $result);
        $this->assertStringNotContainsString(self::SECRET, json_encode($result));
    }

    /**
     * @covers ::recoverByIncrementId
     */
    public function testAlreadyPresentSkipsApi(): void
    {
        $this->givenOrder($this->order());
        $this->klarnaOrderRepository->method('getByOrder')->willReturn($this->klarnaOrder());
        $this->tokenRepository->method('getByOrderId')
            ->willReturn($this->createMock(\Klarna\Kco\Api\Data\CustomerTokenInterface::class));
        $this->checkoutApi->expects($this->never())->method('getOrder');
        $this->persister->expects($this->never())->method('persist');

        $this->assertSame(
            [self::INCREMENT_ID => TokenRecovery::OUTCOME_ALREADY_PRESENT],
            $this->model->recoverByIncrementId(self::INCREMENT_ID)
        );
    }

    /**
     * @covers ::recoverByIncrementId
     */
    public function testNotRecurring(): void
    {
        $this->givenOrder($this->order());
        $this->klarnaOrderRepository->method('getByOrder')->willReturn($this->klarnaOrder());
        $this->tokenRepository->method('getByOrderId')->willThrowException(new NoSuchEntityException());
        $this->checkoutApi->method('getOrder')->willReturn([
            'is_successful' => true,
            'order_id' => 'kustom-1',
            'recurring' => false,
        ]);
        $this->persister->expects($this->never())->method('persist');

        $this->assertSame(
            [self::INCREMENT_ID => TokenRecovery::OUTCOME_NOT_RECURRING],
            $this->model->recoverByIncrementId(self::INCREMENT_ID)
        );
    }

    /**
     * @covers ::recoverByIncrementId
     */
    public function testRecurringWithoutTokenIsReported(): void
    {
        $this->givenOrder($this->order());
        $this->klarnaOrderRepository->method('getByOrder')->willReturn($this->klarnaOrder());
        $this->tokenRepository->method('getByOrderId')->willThrowException(new NoSuchEntityException());
        $this->checkoutApi->method('getOrder')->willReturn([
            'is_successful' => true,
            'order_id' => 'kustom-1',
            'recurring' => true,
        ]);

        $this->assertSame(
            [self::INCREMENT_ID => TokenRecovery::OUTCOME_NO_TOKEN],
            $this->model->recoverByIncrementId(self::INCREMENT_ID)
        );
    }

    /**
     * @covers ::recoverByIncrementId
     */
    #[DataProvider('notReadableProvider')]
    public function testNotReadable(array $response): void
    {
        $this->givenOrder($this->order());
        $this->klarnaOrderRepository->method('getByOrder')->willReturn($this->klarnaOrder());
        $this->tokenRepository->method('getByOrderId')->willThrowException(new NoSuchEntityException());
        $this->checkoutApi->method('getOrder')->willReturn($response);
        $this->persister->expects($this->never())->method('persist');

        $this->assertSame(
            [self::INCREMENT_ID => TokenRecovery::OUTCOME_NOT_READABLE],
            $this->model->recoverByIncrementId(self::INCREMENT_ID)
        );
    }

    public static function notReadableProvider(): array
    {
        return [
            '404' => [['is_successful' => false, 'response_status_code' => 404]],
            '410' => [['is_successful' => false, 'response_status_code' => 410]],
            'exception code 404' => [['is_successful' => false, 'exception_code' => 404]],
        ];
    }

    /**
     * @covers ::recoverByIncrementId
     */
    public function testHttpErrorIsAnError(): void
    {
        $this->givenOrder($this->order());
        $this->klarnaOrderRepository->method('getByOrder')->willReturn($this->klarnaOrder());
        $this->tokenRepository->method('getByOrderId')->willThrowException(new NoSuchEntityException());
        $this->checkoutApi->method('getOrder')->willReturn(['is_successful' => false, 'response_status_code' => 401]);

        $this->assertSame(
            [self::INCREMENT_ID => TokenRecovery::OUTCOME_ERROR],
            $this->model->recoverByIncrementId(self::INCREMENT_ID)
        );
    }

    /**
     * @covers ::recoverByIncrementId
     */
    public function testExceptionIsAnErrorAndNeverExposesToken(): void
    {
        $this->givenOrder($this->order());
        $this->klarnaOrderRepository->method('getByOrder')->willReturn($this->klarnaOrder());
        $this->tokenRepository->method('getByOrderId')->willThrowException(new NoSuchEntityException());
        $this->checkoutApi->method('getOrder')->willReturn([
            'is_successful' => true,
            'order_id' => 'kustom-1',
            'recurring' => true,
            'recurring_token' => self::SECRET,
        ]);
        $this->persister->method('persist')->willThrowException(new \RuntimeException(self::SECRET));

        $result = $this->model->recoverByIncrementId(self::INCREMENT_ID);

        $this->assertSame([self::INCREMENT_ID => TokenRecovery::OUTCOME_ERROR], $result);
        $this->assertStringNotContainsString(self::SECRET, json_encode($result));
    }

    /**
     * @covers ::recoverByIncrementId
     */
    public function testNotKustomOrderByPaymentMethod(): void
    {
        $this->givenOrder($this->order('checkmo'));
        $this->checkoutApi->expects($this->never())->method('getOrder');

        $this->assertSame(
            [self::INCREMENT_ID => TokenRecovery::OUTCOME_NOT_KUSTOM],
            $this->model->recoverByIncrementId(self::INCREMENT_ID)
        );
    }

    /**
     * @covers ::recoverByIncrementId
     */
    public function testNotKustomOrderWithoutKlarnaOrderRow(): void
    {
        $this->givenOrder($this->order());
        $this->klarnaOrderRepository->method('getByOrder')->willThrowException(new NoSuchEntityException());
        $this->checkoutApi->expects($this->never())->method('getOrder');

        $this->assertSame(
            [self::INCREMENT_ID => TokenRecovery::OUTCOME_NOT_KUSTOM],
            $this->model->recoverByIncrementId(self::INCREMENT_ID)
        );
    }

    /**
     * @covers ::recoverByIncrementId
     */
    public function testUnknownIncrementId(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(null);
        $this->givenOrder($order);

        $this->assertSame(
            [self::INCREMENT_ID => TokenRecovery::OUTCOME_NOT_FOUND],
            $this->model->recoverByIncrementId(self::INCREMENT_ID)
        );
    }

    /**
     * @covers ::recoverByDateRange
     */
    public function testDateRangeSkipsOrdersWithToken(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $this->collection->method('getSelect')->willReturn($select);
        $this->collection->method('getTable')->willReturn('sales_order_payment');
        $this->collection->method('addFieldToFilter')->willReturnSelf();
        $with = $this->order('klarna_kco', 7, '000000007');
        $without = $this->order('klarna_kco', 8, '000000008');
        $this->collection->method('getItems')->willReturn([$with, $without]);
        $this->tokenRepository->method('getListByOrderIds')
            ->with([7, 8])
            ->willReturn([7 => $this->createMock(\Klarna\Kco\Api\Data\CustomerTokenInterface::class)]);
        $this->klarnaOrderRepository->method('getByOrder')->willReturn($this->klarnaOrder());
        $this->tokenRepository->method('getByOrderId')->willThrowException(new NoSuchEntityException());
        $this->checkoutApi->method('getOrder')->willReturn(['is_successful' => true, 'recurring' => false]);

        $result = $this->model->recoverByDateRange('2026-01-01 00:00:00', '2026-01-31 23:59:59');

        $this->assertSame([
            '000000007' => TokenRecovery::OUTCOME_ALREADY_PRESENT,
            '000000008' => TokenRecovery::OUTCOME_NOT_RECURRING,
        ], $result);
    }

    /**
     * @covers ::recoverByDateRange
     */
    public function testDateRangePagingEndsWithExactMultipleOfPageSize(): void
    {
        $pageSize = 200;
        $select = $this->createMock(Select::class);
        $select->method('join')->willReturnSelf();
        $lastIds = [];
        $select->method('where')->willReturnCallback(
            function ($condition, $value = null) use (&$lastIds, $select) {
                if ($condition === 'main_table.entity_id > ?') {
                    $lastIds[] = $value;
                }
                return $select;
            }
        );
        $this->collection->method('getSelect')->willReturn($select);
        $this->collection->method('getTable')->willReturn('sales_order_payment');
        $this->collection->method('addFieldToFilter')->willReturnSelf();
        $this->collection->method('setOrder')->willReturnSelf();

        $first = [];
        for ($id = 1; $id <= $pageSize; $id++) {
            $first[] = $this->order('klarna_kco', $id, 'inc' . $id);
        }
        $this->collection->method('getItems')->willReturnOnConsecutiveCalls($first, []);
        $this->tokenRepository->method('getListByOrderIds')->willReturnCallback(
            static fn(array $ids): array => array_fill_keys($ids, true)
        );

        $result = $this->model->recoverByDateRange(null, null);

        $this->assertCount($pageSize, $result);
        $this->assertSame([0, $pageSize], $lastIds);
    }
}
