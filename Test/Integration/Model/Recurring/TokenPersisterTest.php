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
use Klarna\Kco\Model\Recurring\TokenPersister;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Real DI and tables: the persister is what the push and the confirmation both end up calling, so running it twice
 * for the same Kustom order must give exactly one row.
 *
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/order_setup1_single_simple_product.php
 */
class TokenPersisterTest extends TestCase
{
    /**
     * @var TokenPersister
     */
    private $persister;
    /**
     * @var CustomerTokenRepositoryInterface
     */
    private $repository;
    /**
     * @var \Magento\Sales\Model\Order
     */
    private $order;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->persister = $objectManager->get(TokenPersister::class);
        $this->repository = $objectManager->get(CustomerTokenRepositoryInterface::class);
        $this->order = $objectManager->get(OrderCollectionFactory::class)->create()->getFirstItem();
    }

    public function testRecurringOrderStoresOneRowEvenWhenPersistedTwice(): void
    {
        $checkout = new DataObject(['order_id' => 'k-1', 'recurring' => true, 'recurring_token' => 'tok-1']);

        $this->persister->persist($checkout, $this->order, 'mid-1');
        $this->persister->persist($checkout, $this->order, 'mid-1');

        $stored = $this->repository->getByKustomOrderId('k-1');
        static::assertSame('tok-1', $stored->getToken());
        static::assertSame((int)$this->order->getId(), $stored->getOrderId());
        static::assertSame($this->order->getIncrementId(), $stored->getIncrementId());
        static::assertSame('mid-1', $stored->getMerchantId());
        static::assertCount(1, $this->repository->getListByOrderIds([(int)$this->order->getId()]));
    }

    public function testNonRecurringOrderStoresNothing(): void
    {
        $checkout = new DataObject(['order_id' => 'k-2', 'recurring' => false]);

        $this->persister->persist($checkout, $this->order, 'mid-1');

        $this->expectException(NoSuchEntityException::class);
        $this->repository->getByKustomOrderId('k-2');
    }
}
