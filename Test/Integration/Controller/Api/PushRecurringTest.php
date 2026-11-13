<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Controller\Api;

use Klarna\Backend\Model\Api\Rest\Service\Ordermanagement;
use Klarna\Kco\Api\CustomerTokenRepositoryInterface;
use Klarna\Kco\Controller\Klarna\Confirmation;
use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Klarna\Kco\Model\Recurring\TokenPersister;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\TestFramework\TestCase\AbstractController;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Push and confirmation end to end (real DI, real tables). Only the checkout GET (Base HTTP behind
 * {@see Checkout}) and the Order Management calls are stubbed, like in PushTest and ConfirmationTest.
 *
 * The annotations sit on each test (class-level ones are not applied in the integration runner).
 */
class PushRecurringTest extends AbstractController
{
    private const KUSTOM_ORDER_ID = '123456-1234-1234-1234-1234567890';
    private const PLAIN_TOKEN = 'plain-recurring-token-4711';

    /**
     * @var Checkout|MockObject
     */
    private $checkoutMock;
    /**
     * @var Ordermanagement|MockObject
     */
    private $orderManagementMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checkoutMock = $this->createMock(Checkout::class);
        $this->_objectManager->addSharedInstance($this->checkoutMock, Checkout::class);

        $orderManagementMock = $this->createMock(Ordermanagement::class);
        $this->orderManagementMock = $orderManagementMock;
        $orderManagementMock->method('getOrder')->willReturn([
            'captured_amount' => 0,
            'captures' => [],
            'klarna_reference' => '12345',
        ]);
        $orderManagementMock->method('updateMerchantReferences')->willReturn([]);
        $orderManagementMock->method('acknowledgeOrder')->willReturn(['is_successful' => true]);
        $this->_objectManager->addSharedInstance($orderManagementMock, Ordermanagement::class);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testPushTwiceStoresOneEncryptedTokenAndCreatesTheOrderOnce(): void
    {
        $checkoutCalls = static::any();
        $this->checkoutMock->expects($checkoutCalls)->method('getOrder')
            ->willReturn($this->getCheckoutResponse(true));

        $this->orderManagementMock->expects(static::atLeastOnce())->method('acknowledgeOrder');

        $this->push();
        static::assertGreaterThan(0, $checkoutCalls->numberOfInvocations());
        static::assertSame(200, $this->getResponse()->getHttpResponseCode());
        static::assertSame(1, $this->getOrderCount());
        static::assertSame(1, $this->getTokenRowCount());

        // The second push runs the action itself (a second dispatch() in the same app instance 404s).
        $checkoutCallsAfterFirstPush = $checkoutCalls->numberOfInvocations();
        $this->resetRequest();
        $this->getRequest()->setParam('id', self::KUSTOM_ORDER_ID);
        $second = $this->_objectManager->create(\Klarna\Kco\Controller\Api\Push::class)->execute();
        static::assertInstanceOf(\Magento\Framework\Controller\Result\Json::class, $second);
        // The existing order is not created or persisted again: no second token write path is reached.
        static::assertSame($checkoutCallsAfterFirstPush, $checkoutCalls->numberOfInvocations());

        static::assertSame(1, $this->getOrderCount());
        static::assertSame(1, $this->getTokenRowCount());

        $raw = $this->getRawTokenColumn();
        static::assertNotSame('', $raw);
        static::assertNotSame(self::PLAIN_TOKEN, $raw);
        static::assertStringNotContainsString(self::PLAIN_TOKEN, $raw);

        $repository = $this->_objectManager->get(CustomerTokenRepositoryInterface::class);
        $stored = $repository->getByKustomOrderId(self::KUSTOM_ORDER_ID);
        static::assertSame(self::PLAIN_TOKEN, $stored->getToken());
        static::assertSame('100000001', $stored->getIncrementId());
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testConfirmationAfterPushDoesNotStoreASecondToken(): void
    {
        $this->checkoutMock->method('getOrder')->willReturn($this->getCheckoutResponse(true));

        $this->push();

        // A second dispatch() in the same app instance 404s (stale routers), so run the action itself.
        $this->resetRequest();
        $this->getRequest()->setParam('id', self::KUSTOM_ORDER_ID);
        $confirmation = $this->_objectManager->create(Confirmation::class);
        $result = $confirmation->execute();

        static::assertInstanceOf(Redirect::class, $result);
        static::assertSame(1, $this->getOrderCount());
        static::assertSame(1, $this->getTokenRowCount());
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testNonRecurringCheckoutStoresNoTokenAndStillCreatesTheOrder(): void
    {
        $this->checkoutMock->method('getOrder')->willReturn($this->getCheckoutResponse(false));
        $this->orderManagementMock->expects(static::atLeastOnce())->method('acknowledgeOrder');

        $this->push();

        static::assertSame(200, $this->getResponse()->getHttpResponseCode());
        static::assertSame(1, $this->getOrderCount());
        static::assertSame(0, $this->getTokenRowCount());
    }

    /**
     * AC6: a failing token persister never blocks the order, the response or the acknowledge.
     *
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testThrowingTokenPersisterStillCreatesTheOrderAndAcknowledges(): void
    {
        $this->checkoutMock->method('getOrder')->willReturn($this->getCheckoutResponse(true));
        $persister = $this->createMock(TokenPersister::class);
        $persister->expects(static::atLeastOnce())->method('persist')
            ->willThrowException(new \RuntimeException('token storage failed'));
        $this->_objectManager->addSharedInstance($persister, TokenPersister::class);
        $this->orderManagementMock->expects(static::atLeastOnce())->method('acknowledgeOrder');

        $this->push();

        static::assertSame(200, $this->getResponse()->getHttpResponseCode());
        static::assertSame(1, $this->getOrderCount());
        static::assertSame(0, $this->getTokenRowCount());
    }

    private function push(): void
    {
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('kco/api/push/id/' . self::KUSTOM_ORDER_ID);
    }

    private function getOrderCount(): int
    {
        return (int)$this->_objectManager->get(OrderCollectionFactory::class)->create()->getSize();
    }

    private function getTokenRowCount(): int
    {
        $resource = $this->_objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        return (int)$connection->fetchOne(
            $connection->select()->from($resource->getTableName('kustom_customer_token'), 'COUNT(*)')
        );
    }

    private function getRawTokenColumn(): string
    {
        $resource = $this->_objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        return (string)$connection->fetchOne(
            $connection->select()
                ->from($resource->getTableName('kustom_customer_token'), 'token')
                ->where('kustom_order_id = ?', self::KUSTOM_ORDER_ID)
        );
    }

    private function getCheckoutResponse(bool $recurring): array
    {
        $address = [
            'city' => 'City',
            'country' => 'US',
            'email' => 'customer@example.com',
            'family_name' => 'Lastname',
            'given_name' => 'Firstname',
            'phone' => '040123456',
            'postal_code' => '12345',
            'street_address' => 'Street',
            'region' => 'California',
        ];
        $response = [
            'billing_address' => $address,
            'shipping_address' => $address,
            'order_id' => self::KUSTOM_ORDER_ID,
            'is_successful' => true,
            'order_lines' => [
                [
                    'image_url' => '',
                    'name' => 'Simple Product',
                    'product_url' => 'http://localhost/index.php/simple-product.html',
                    'quantity' => 1,
                    'reference' => 'simple',
                    'tax_rate' => 0,
                    'total_amount' => 1000,
                    'total_discount_amount' => 0,
                    'total_tax_amount' => 0,
                    'type' => 'physical',
                    'unit_price' => 1000,
                ],
            ],
            'selected_shipping_option' => [
                'id' => 'flatrate_flatrate',
                'price' => 500,
                'tax_amount' => 0,
                'tax_rate' => 0,
            ],
            'order_amount' => 1500,
            'status' => 'checkout_complete',
        ];
        if ($recurring) {
            $response['recurring'] = true;
            $response['recurring_token'] = self::PLAIN_TOKEN;
        }
        return $response;
    }
}
