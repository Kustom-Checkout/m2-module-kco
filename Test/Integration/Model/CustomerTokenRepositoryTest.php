<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Model;

use Klarna\Kco\Api\CustomerTokenRepositoryInterface;
use Klarna\Kco\Api\Data\CustomerTokenInterface;
use Klarna\Kco\Model\CustomerTokenFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/order_setup1_single_simple_product.php
 */
class CustomerTokenRepositoryTest extends TestCase
{
    private const PLAIN_TOKEN = 'plain-token-value-4711';

    /**
     * @var CustomerTokenRepositoryInterface
     */
    private $repository;
    /**
     * @var CustomerTokenFactory
     */
    private $factory;
    /**
     * @var ResourceConnection
     */
    private $resource;
    /**
     * @var \Magento\Sales\Model\Order
     */
    private $order;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->repository = $objectManager->get(CustomerTokenRepositoryInterface::class);
        $this->factory = $objectManager->get(CustomerTokenFactory::class);
        $this->resource = $objectManager->get(ResourceConnection::class);
        $this->order = $objectManager->get(OrderCollectionFactory::class)->create()->getFirstItem();
    }

    public function testSaveAndLoadRoundTripAndTokenIsEncryptedInTheDatabase(): void
    {
        $saved = $this->repository->save($this->createToken('kustom-order-1', 1));
        static::assertNotNull($saved->getEntityId());
        static::assertSame(self::PLAIN_TOKEN, $saved->getToken());

        $loaded = $this->repository->getById((int)$saved->getEntityId());
        static::assertSame(self::PLAIN_TOKEN, $loaded->getToken());
        static::assertSame('kustom-order-1', $loaded->getKustomOrderId());
        static::assertSame((int)$this->order->getId(), $loaded->getOrderId());
        static::assertSame($this->order->getIncrementId(), $loaded->getIncrementId());
        static::assertSame('mid-1', $loaded->getMerchantId());
        static::assertSame(CustomerTokenInterface::STATUS_ACTIVE, $loaded->getStatus());
        static::assertNotNull($loaded->getCreatedAt());
        static::assertNull($loaded->getPaymentMethodType());
        static::assertNull($loaded->getCancelledAt());

        static::assertSame(self::PLAIN_TOKEN, $this->repository->getByKustomOrderId('kustom-order-1')->getToken());
        static::assertSame(
            self::PLAIN_TOKEN,
            $this->repository->getByOrderIncrementId((string)$this->order->getIncrementId())->getToken()
        );
        static::assertSame(
            self::PLAIN_TOKEN,
            $this->repository->getByOrderId((int)$this->order->getId())->getToken()
        );

        $connection = $this->resource->getConnection();
        $raw = (string)$connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('kustom_customer_token'), 'token')
                ->where('entity_id = ?', $saved->getEntityId())
        );
        static::assertNotSame('', $raw);
        static::assertNotSame(self::PLAIN_TOKEN, $raw);
        static::assertStringNotContainsString(self::PLAIN_TOKEN, $raw);
    }

    public function testUpdateKeepsTokenEncrypted(): void
    {
        $saved = $this->repository->save($this->createToken('kustom-order-2', 1));
        $loaded = $this->repository->getById((int)$saved->getEntityId());
        $loaded->setStatus(CustomerTokenInterface::STATUS_CANCELLED);
        $this->repository->save($loaded);

        $reloaded = $this->repository->getById((int)$saved->getEntityId());
        static::assertSame(self::PLAIN_TOKEN, $reloaded->getToken());
        static::assertSame(CustomerTokenInterface::STATUS_CANCELLED, $reloaded->getStatus());
    }

    public function testGuestWithoutCustomerIsAccepted(): void
    {
        $token = $this->createToken('kustom-order-3', null);
        $this->repository->save($token);

        $loaded = $this->repository->getByKustomOrderId('kustom-order-3');
        static::assertNull($loaded->getCustomerId());
    }

    public function testDuplicateKustomOrderIdThrows(): void
    {
        $this->repository->save($this->createToken('kustom-order-4', 1));

        $this->expectException(CouldNotSaveException::class);
        try {
            $this->repository->save($this->createToken('kustom-order-4', 1));
        } catch (CouldNotSaveException $e) {
            static::assertStringNotContainsString(self::PLAIN_TOKEN, $e->getMessage());
            throw $e;
        }
    }

    public function testSaveIfNotExistsIsIdempotent(): void
    {
        $first = $this->repository->saveIfNotExists($this->createToken('kustom-order-5', 1));
        $second = $this->repository->saveIfNotExists($this->createToken('kustom-order-5', 1));

        static::assertSame($first->getEntityId(), $second->getEntityId());
        static::assertSame(self::PLAIN_TOKEN, $second->getToken());

        $connection = $this->resource->getConnection();
        $count = (int)$connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('kustom_customer_token'), 'COUNT(*)')
                ->where('kustom_order_id = ?', 'kustom-order-5')
        );
        static::assertSame(1, $count);
    }

    public function testGetListByOrderIdsReturnsDecryptedTokensKeyedByOrderId(): void
    {
        $this->repository->save($this->createToken('kustom-order-6', 1));

        $list = $this->repository->getListByOrderIds([(int)$this->order->getId(), 999999]);

        static::assertSame([(int)$this->order->getId()], array_keys($list));
        static::assertSame(self::PLAIN_TOKEN, $list[(int)$this->order->getId()]->getToken());
        static::assertSame([], $this->repository->getListByOrderIds([]));
    }

    public function testDeletingTheOrderKeepsTheTokenWithANullOrderId(): void
    {
        $this->repository->save($this->createToken('kustom-order-7', 1));
        $orderId = (int)$this->order->getId();
        $incrementId = (string)$this->order->getIncrementId();

        $this->resource->getConnection()->delete(
            $this->resource->getTableName('sales_order'),
            ['entity_id = ?' => $orderId]
        );

        $loaded = $this->repository->getByKustomOrderId('kustom-order-7');
        static::assertSame(self::PLAIN_TOKEN, $loaded->getToken());
        static::assertNull($loaded->getOrderId());
        static::assertSame($incrementId, $loaded->getIncrementId());
        static::assertSame([], $this->repository->getListByOrderIds([$orderId]));
        $this->expectException(NoSuchEntityException::class);
        $this->repository->getByOrderId($orderId);
    }

    public function testUnknownTokenThrowsNoSuchEntity(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->repository->getByKustomOrderId('does-not-exist');
    }

    private function createToken(string $kustomOrderId, ?int $customerId): CustomerTokenInterface
    {
        /** @var CustomerTokenInterface $token */
        $token = $this->factory->create();
        $token->setToken(self::PLAIN_TOKEN)
            ->setKustomOrderId($kustomOrderId)
            ->setOrderId((int)$this->order->getId())
            ->setIncrementId((string)$this->order->getIncrementId())
            ->setCustomerId($customerId === null ? null : (int)$this->order->getCustomerId())
            ->setStoreId((int)$this->order->getStoreId())
            ->setMerchantId('mid-1');
        return $token;
    }
}
