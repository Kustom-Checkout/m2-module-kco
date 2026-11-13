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
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Framework\Reflection\ExtensionAttributesProcessor;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Serialises a real order from the real order repository with a permission-checked DataObjectProcessor like the
 * REST layer uses to build the response, with the ACL decision of the caller swapped (app isolation resets it).
 *
 * This is not a Web API functional test: no HTTP request, no integration token and no real role/rule rows are
 * involved. The repository plugin populates the attribute for every PHP caller; the `<resources>` check of the
 * extension attribute in `etc/extension_attributes.xml` is the only gate. It proves that the permission-checked
 * processor omits `kustom_customer_token` when the decision for `Klarna_Kco::customer_token` is "denied", and
 * includes it when it is "allowed".
 *
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/order_setup1_single_simple_product.php
 */
class OrderTokenSerializationTest extends TestCase
{
    private const PLAIN_TOKEN = 'plain-token-value-4711';

    /**
     * @var int
     */
    private $orderId;

    /**
     * @var DataObjectProcessor
     */
    private $processor;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $resource->getConnection()->update(
            $resource->getTableName('sales_order_payment'),
            ['method' => Kco::METHOD_CODE]
        );
        $order = $objectManager->get(OrderCollectionFactory::class)->create()->getFirstItem();
        $this->orderId = (int)$order->getId();

        $token = $objectManager->get(CustomerTokenFactory::class)->create();
        $token->setToken(self::PLAIN_TOKEN)
            ->setKustomOrderId('kustom-order-serialization')
            ->setOrderId($this->orderId)
            ->setIncrementId((string)$order->getIncrementId())
            ->setStoreId((int)$order->getStoreId())
            ->setMerchantId('mid-1');
        $objectManager->get(CustomerTokenRepositoryInterface::class)->save($token);
    }

    public function testTokenIsNotSerialisedWhenTheCallerLacksTheResource(): void
    {
        $this->setProcessorDecision(false);

        static::assertArrayNotHasKey('kustom_customer_token', $this->serialisedExtensionAttributes());
    }

    public function testTokenIsSerialisedWhenTheCallerHoldsTheResource(): void
    {
        $this->setProcessorDecision(true);

        $attributes = $this->serialisedExtensionAttributes();

        static::assertArrayHasKey('kustom_customer_token', $attributes);
        static::assertSame(self::PLAIN_TOKEN, $attributes['kustom_customer_token']['token']);
    }

    /**
     * @return array
     */
    private function serialisedExtensionAttributes(): array
    {
        $objectManager = Bootstrap::getObjectManager();
        $order = $objectManager->create(OrderRepositoryInterface::class)->get($this->orderId);
        $data = $this->processor->buildOutputDataArray($order, OrderInterface::class);

        return $data['extension_attributes'] ?? [];
    }

    /**
     * The default processor does not check `<resources>` (only the webapi_rest/webapi_soap areas switch
     * `isPermissionChecked` on), so a permission-checked one is built here, like the REST layer's
     * `DataObjectProcessorPermissionChecked`, with the ACL decision of the caller as given.
     *
     * @param bool $allowed
     * @return void
     */
    private function setProcessorDecision(bool $allowed): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $extensionAttributesProcessor = $objectManager->create(
            ExtensionAttributesProcessor::class,
            [
                'authorization' => $this->authorization($allowed),
                'isPermissionChecked' => true
            ]
        );
        $this->processor = $objectManager->create(
            DataObjectProcessor::class,
            ['extensionAttributesProcessor' => $extensionAttributesProcessor]
        );
    }

    /**
     * @param bool $allowed
     * @return AuthorizationInterface
     */
    private function authorization(bool $allowed): AuthorizationInterface
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnCallback(
            fn (string $resource): bool => $resource === OrderRepositoryTokenPlugin::ACL_RESOURCE && $allowed
        );

        return $authorization;
    }
}
