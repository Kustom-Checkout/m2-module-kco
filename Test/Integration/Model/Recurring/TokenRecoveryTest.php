<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Model\Recurring;

use Klarna\Kco\Api\CustomerTokenRepositoryInterface;
use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Klarna\Kco\Model\Payment\Kco;
use Klarna\Kco\Model\Recurring\TokenRecovery;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The recovery service with real DI and tables. Only the checkout GET ({@see Checkout}) is stubbed.
 * The fixture order is switched to the Kustom Checkout payment method (the recovery skips other methods).
 *
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/klarna_order_setup1_single_simple_product.php
 */
class TokenRecoveryTest extends TestCase
{
    private const KUSTOM_ORDER_ID = '123456-1234-1234-1234-1234567890';
    private const INCREMENT_ID = '100000001';
    private const PLAIN_TOKEN = 'recovered-token-4711';

    /**
     * @var Checkout|MockObject
     */
    private $checkout;
    /**
     * @var CustomerTokenRepositoryInterface
     */
    private $repository;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->repository = $objectManager->get(CustomerTokenRepositoryInterface::class);
        $this->checkout = $this->createMock(Checkout::class);

        $resource = $objectManager->get(ResourceConnection::class);
        $resource->getConnection()->update(
            $resource->getTableName('sales_order_payment'),
            ['method' => Kco::METHOD_CODE]
        );
    }

    public function testRecurringOrderIsPersistedAndASecondRunSkipsTheApi(): void
    {
        $this->checkout->expects($this->once())->method('getOrder')->willReturn([
            'is_successful' => true,
            'order_id' => self::KUSTOM_ORDER_ID,
            'recurring' => true,
            'recurring_token' => self::PLAIN_TOKEN,
        ]);
        $recovery = $this->createRecovery();

        static::assertSame(
            [self::INCREMENT_ID => TokenRecovery::OUTCOME_PERSISTED],
            $recovery->recoverByIncrementId(self::INCREMENT_ID)
        );
        static::assertSame(self::PLAIN_TOKEN, $this->repository->getByKustomOrderId(self::KUSTOM_ORDER_ID)->getToken());

        static::assertSame(
            [self::INCREMENT_ID => TokenRecovery::OUTCOME_ALREADY_PRESENT],
            $recovery->recoverByIncrementId(self::INCREMENT_ID)
        );
        $this->assertSame(1, $this->getTokenRowCount());
    }

    public function testDateRangeJoinsPaymentsAndPagesByKeyset(): void
    {
        $this->checkout->expects($this->once())->method('getOrder')->willReturn([
            'is_successful' => true,
            'order_id' => self::KUSTOM_ORDER_ID,
            'recurring' => true,
            'recurring_token' => self::PLAIN_TOKEN,
        ]);
        $recovery = $this->createRecovery();

        $from = gmdate('Y-m-d H:i:s', time() - 86400);
        $to = gmdate('Y-m-d H:i:s', time() + 86400);
        static::assertSame(
            [self::INCREMENT_ID => TokenRecovery::OUTCOME_PERSISTED],
            $recovery->recoverByDateRange($from, $to)
        );
        static::assertSame(1, $this->getTokenRowCount());

        // Second run in the same range: the order is skipped without calling the API again.
        static::assertSame(
            [self::INCREMENT_ID => TokenRecovery::OUTCOME_ALREADY_PRESENT],
            $recovery->recoverByDateRange($from, null)
        );
    }

    public function testDateRangeOutsideTheOrderCreationTimeFindsNothing(): void
    {
        $this->checkout->expects($this->never())->method('getOrder');

        static::assertSame([], $this->createRecovery()->recoverByDateRange('2000-01-01 00:00:00', '2000-01-02 00:00:00'));
    }

    public function testOrderThatIsNoLongerReadableIsReportedAndStoresNothing(): void
    {
        $this->checkout->method('getOrder')->willReturn([
            'is_successful' => false,
            'response_status_code' => 404,
        ]);

        static::assertSame(
            [self::INCREMENT_ID => TokenRecovery::OUTCOME_NOT_READABLE],
            $this->createRecovery()->recoverByIncrementId(self::INCREMENT_ID)
        );
        static::assertSame(0, $this->getTokenRowCount());
    }

    public function testNonRecurringOrderStoresNothing(): void
    {
        $this->checkout->method('getOrder')->willReturn([
            'is_successful' => true,
            'order_id' => self::KUSTOM_ORDER_ID,
        ]);

        static::assertSame(
            [self::INCREMENT_ID => TokenRecovery::OUTCOME_NOT_RECURRING],
            $this->createRecovery()->recoverByIncrementId(self::INCREMENT_ID)
        );
        static::assertSame(0, $this->getTokenRowCount());
    }

    public function testUnknownIncrementIdIsReported(): void
    {
        $this->checkout->expects($this->never())->method('getOrder');

        static::assertSame(
            ['999999999' => TokenRecovery::OUTCOME_NOT_FOUND],
            $this->createRecovery()->recoverByIncrementId('999999999')
        );
        $this->expectException(NoSuchEntityException::class);
        $this->repository->getByKustomOrderId(self::KUSTOM_ORDER_ID);
    }

    private function createRecovery(): TokenRecovery
    {
        return Bootstrap::getObjectManager()->create(TokenRecovery::class, ['checkoutApi' => $this->checkout]);
    }

    private function getTokenRowCount(): int
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        return (int)$connection->fetchOne(
            $connection->select()->from($resource->getTableName('kustom_customer_token'), 'COUNT(*)')
        );
    }
}
