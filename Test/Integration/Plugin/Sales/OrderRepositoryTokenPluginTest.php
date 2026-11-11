<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Plugin\Sales;

use Klarna\Kco\Api\CustomerTokenRepositoryInterface;
use Klarna\Kco\Model\CustomerTokenFactory;
use Klarna\Kco\Model\Payment\Kco;
use Klarna\Kco\Plugin\Sales\OrderRepositoryTokenPlugin;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\AuthorizationInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The plugin is called directly with a mocked ACL, with real tokens in the real table.
 * Orders are loaded through the factory so the global plugin (real ACL) does not interfere.
 *
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/order_setup1_single_simple_product.php
 */
class OrderRepositoryTokenPluginTest extends TestCase
{
    private const PLAIN_TOKEN = 'plain-token-value-4711';

    /**
     * @var CustomerTokenRepositoryInterface
     */
    private $tokenRepository;
    /**
     * @var int
     */
    private $orderId;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        // Only orders paid with Kustom Checkout are looked up
        $resource = $objectManager->get(ResourceConnection::class);
        $resource->getConnection()->update(
            $resource->getTableName('sales_order_payment'),
            ['method' => Kco::METHOD_CODE]
        );
        $this->tokenRepository = $objectManager->get(CustomerTokenRepositoryInterface::class);
        $order = $objectManager->get(OrderCollectionFactory::class)->create()->getFirstItem();
        $this->orderId = (int)$order->getId();

        $token = $objectManager->get(CustomerTokenFactory::class)->create();
        $token->setToken(self::PLAIN_TOKEN)
            ->setKustomOrderId('kustom-order-plugin')
            ->setOrderId($this->orderId)
            ->setIncrementId((string)$order->getIncrementId())
            ->setStoreId((int)$order->getStoreId())
            ->setMerchantId('mid-1');
        $this->tokenRepository->save($token);
    }

    public function testAfterGetLeavesTheAttributeNullWithoutAcl(): void
    {
        $plugin = $this->createPlugin($this->tokenRepository, false);

        $order = $plugin->afterGet($this->getRepository(), $this->loadOrder($this->orderId));

        static::assertNull($order->getExtensionAttributes()->getKustomCustomerToken());
    }

    public function testAfterGetPopulatesTheDecryptedTokenWithAcl(): void
    {
        $plugin = $this->createPlugin($this->tokenRepository, true);

        $order = $plugin->afterGet($this->getRepository(), $this->loadOrder($this->orderId));

        $token = $order->getExtensionAttributes()->getKustomCustomerToken();
        static::assertNotNull($token);
        static::assertSame(self::PLAIN_TOKEN, $token->getToken());
    }

    public function testAfterGetListDeniedMakesNoQueryAndLeavesNull(): void
    {
        /** @var CustomerTokenRepositoryInterface|MockObject $spy */
        $spy = $this->createMock(CustomerTokenRepositoryInterface::class);
        $spy->expects($this->never())->method('getListByOrderIds');
        $plugin = $this->createPlugin($spy, false);

        $result = $plugin->afterGetList($this->getRepository(), $this->createSearchResult([
            $this->loadOrder($this->orderId),
        ]));

        foreach ($result->getItems() as $order) {
            static::assertNull($order->getExtensionAttributes()->getKustomCustomerToken());
        }
    }

    public function testAfterGetListBatchesOneQueryAndPopulatesOnlyOrdersWithAToken(): void
    {
        /** @var CustomerTokenRepositoryInterface|MockObject $spy */
        $spy = $this->createMock(CustomerTokenRepositoryInterface::class);
        $spy->expects($this->once())->method('getListByOrderIds')
            ->willReturnCallback(fn (array $ids) => $this->tokenRepository->getListByOrderIds($ids));
        $plugin = $this->createPlugin($spy, true);

        $withToken = $this->loadOrder($this->orderId);
        $withoutToken = Bootstrap::getObjectManager()->create(Order::class);
        $withoutToken->setEntityId(999999);
        $withoutToken->setPayment(Bootstrap::getObjectManager()->create(\Magento\Sales\Model\Order\Payment::class)
            ->setMethod(Kco::METHOD_CODE));

        $result = $plugin->afterGetList(
            $this->getRepository(),
            $this->createSearchResult([$withToken, $withoutToken])
        );

        $items = array_values($result->getItems());
        static::assertSame(
            self::PLAIN_TOKEN,
            $items[0]->getExtensionAttributes()->getKustomCustomerToken()->getToken()
        );
        static::assertNull($items[1]->getExtensionAttributes()->getKustomCustomerToken());
    }

    public function testRealRepositoryGetListPopulatesTheTokenWithAclAllowed(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->with(OrderRepositoryTokenPlugin::ACL_RESOURCE)->willReturn(true);
        // The interception layer uses the shared plugin instance: give that one the allowing ACL (app isolation
        // resets the object manager after the test).
        $plugin = $objectManager->get(OrderRepositoryTokenPlugin::class);
        $property = new \ReflectionProperty(OrderRepositoryTokenPlugin::class, 'authorization');
        $property->setValue($plugin, $authorization);
        $repository = $objectManager->create(OrderRepositoryInterface::class);
        $criteria = $objectManager->create(\Magento\Framework\Api\SearchCriteriaBuilder::class)
            ->addFilter('entity_id', $this->orderId)
            ->create();

        $items = $repository->getList($criteria)->getItems();

        static::assertCount(1, $items);
        $token = reset($items)->getExtensionAttributes()->getKustomCustomerToken();
        static::assertNotNull($token);
        static::assertSame(self::PLAIN_TOKEN, $token->getToken());
    }

    public function testNonKustomOrderMakesNoTokenQuery(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $resource->getConnection()->update(
            $resource->getTableName('sales_order_payment'),
            ['method' => 'checkmo']
        );
        /** @var CustomerTokenRepositoryInterface|MockObject $spy */
        $spy = $this->createMock(CustomerTokenRepositoryInterface::class);
        $spy->expects($this->never())->method('getListByOrderIds');
        $plugin = $this->createPlugin($spy, true);

        $order = $plugin->afterGet($this->getRepository(), $this->loadOrder($this->orderId));

        static::assertNull($order->getExtensionAttributes()->getKustomCustomerToken());
    }

    private function createPlugin(
        CustomerTokenRepositoryInterface $repository,
        bool $allowed
    ): OrderRepositoryTokenPlugin {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')
            ->with(OrderRepositoryTokenPlugin::ACL_RESOURCE)
            ->willReturn($allowed);

        return Bootstrap::getObjectManager()->create(OrderRepositoryTokenPlugin::class, [
            'tokenRepository' => $repository,
            'authorization' => $authorization,
        ]);
    }

    private function getRepository(): OrderRepositoryInterface
    {
        return Bootstrap::getObjectManager()->get(OrderRepositoryInterface::class);
    }

    private function loadOrder(int $orderId): OrderInterface
    {
        return Bootstrap::getObjectManager()->get(OrderFactory::class)->create()->load($orderId);
    }

    /**
     * @param OrderInterface[] $orders
     * @return \Magento\Sales\Api\Data\OrderSearchResultInterface
     */
    private function createSearchResult(array $orders)
    {
        // The real collection would load all orders from the database on getItems() and clash with the set items.
        $result = $this->createMock(OrderSearchResultInterface::class);
        $result->method('getItems')->willReturn($orders);
        return $result;
    }
}
