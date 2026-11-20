<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Model\Checkout;

use Klarna\Kco\Api\QuoteRepositoryInterface;
use Klarna\Kco\Model\Checkout\ValidatedMarker;
use Klarna\Kco\Exception\CheckoutLockedException;
use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Klarna\Kco\Model\Checkout\MutationGuard;
use Klarna\Kco\Model\Order\CreationLock;
use Klarna\Kco\Model\Order\Order as CheckoutOrder;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\Backend\Database;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * KUSTOM-128: the guard which protects the quote between validate and order creation
 *
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
 */
class MutationGuardTest extends TestCase
{
    private const CHECKOUT_ID = '123456-1234-1234-1234-1234567890';

    /**
     * @var Checkout|MockObject
     */
    private $checkoutApi;

    /**
     * @var CheckoutOrder|MockObject
     */
    private $checkoutOrder;

    /**
     * @var QuoteRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var CreationLock
     */
    private $guardLock;

    /**
     * @var CreationLock
     */
    private $otherRequestLock;

    protected function setUp(): void
    {
        $this->checkoutApi = $this->createMock(Checkout::class);
        $this->checkoutOrder = $this->createMock(CheckoutOrder::class);
        $this->checkoutOrder->method('isMagentoOrderExists')->willReturn(false);
        $this->quoteRepository = Bootstrap::getObjectManager()->get(QuoteRepositoryInterface::class);
        $this->guardLock = $this->createLockWithOwnConnection();
        $this->otherRequestLock = $this->createLockWithOwnConnection();
    }

    protected function tearDown(): void
    {
        $this->otherRequestLock->release(self::CHECKOUT_ID);
        $this->guardLock->release(self::CHECKOUT_ID);
    }

    public function testFrontendAllowedWithoutMarkerAndWithoutApiCall(): void
    {
        $this->checkoutApi->expects($this->never())->method('getOrder');

        $this->createGuard()->guardFrontend(self::CHECKOUT_ID);
        $this->assertNull($this->quoteRepository->getByCheckoutId(self::CHECKOUT_ID)->getValidatedAt());
    }

    public function testFrontendRefusedInsideWindowWithoutApiCall(): void
    {
        $this->setMarker(gmdate('Y-m-d H:i:s', time() - 10));
        $this->checkoutApi->expects($this->never())->method('getOrder');

        $this->expectException(CheckoutLockedException::class);
        $this->expectExceptionMessage('Your payment is being processed');
        $this->createGuard()->guardFrontend(self::CHECKOUT_ID);
    }

    public function testFrontendAllowedAndMarkerClearedWhenOlderThanWindowAndIncomplete(): void
    {
        $this->setMarker(gmdate('Y-m-d H:i:s', time() - 400));
        $this->checkoutApi->expects($this->once())->method('getOrder')
            ->willReturn(['status' => 'checkout_incomplete']);

        $this->createGuard()->guardFrontend(self::CHECKOUT_ID);
        $this->assertNull($this->quoteRepository->getByCheckoutId(self::CHECKOUT_ID)->getValidatedAt());
    }

    public function testFrontendRefusedWhenOlderThanWindowAndComplete(): void
    {
        $this->setMarker(gmdate('Y-m-d H:i:s', time() - 400));
        $this->checkoutApi->expects($this->once())->method('getOrder')
            ->willReturn(['status' => 'checkout_complete']);

        $this->expectException(CheckoutLockedException::class);
        $this->createGuard()->guardFrontend(self::CHECKOUT_ID);
    }

    public function testFrontendRefusedWhenStatusCheckFails(): void
    {
        $this->setMarker(gmdate('Y-m-d H:i:s', time() - 400));
        $this->checkoutApi->method('getOrder')->willThrowException(new \Exception('timeout'));

        $this->expectException(CheckoutLockedException::class);
        $this->createGuard()->guardFrontend(self::CHECKOUT_ID);
    }

    public function testRefusedWithoutApiCallWhenOrderExists(): void
    {
        $order = $this->createMock(CheckoutOrder::class);
        $order->method('isMagentoOrderExists')->willReturn(true);
        $this->checkoutApi->expects($this->never())->method('getOrder');

        foreach (['guardFrontend', 'guardKustomCallback'] as $method) {
            try {
                $this->createGuard($order)->$method(self::CHECKOUT_ID);
                $this->fail($method . ' must refuse');
            } catch (CheckoutLockedException $e) {
                $this->assertStringContainsString('Your payment is being processed', $e->getMessage());
            }
        }
    }

    public function testRefusedWhenOrderCreationHoldsTheLock(): void
    {
        $this->assertTrue($this->otherRequestLock->acquire(self::CHECKOUT_ID, 0));
        $this->checkoutApi->expects($this->never())->method('getOrder');

        $this->expectException(CheckoutLockedException::class);
        $this->createGuard()->guardKustomCallback(self::CHECKOUT_ID);
    }

    public function testLockIsHeldAfterAnAllowedCheckUntilReleased(): void
    {
        $guard = $this->createGuard();
        $guard->guardFrontend(self::CHECKOUT_ID);

        $this->assertFalse($this->otherRequestLock->acquire(self::CHECKOUT_ID, 0));

        $guard->release(self::CHECKOUT_ID);
        $guard->release(self::CHECKOUT_ID);
        $this->assertTrue($this->otherRequestLock->acquire(self::CHECKOUT_ID, 0));
    }

    public function testReleaseAllReleasesEveryLockTheGuardHolds(): void
    {
        $otherId = '654321-4321-4321-4321-0987654321';
        $guard = $this->createGuard();
        $guard->guardFrontend(self::CHECKOUT_ID);
        $guard->guardFrontend($otherId);
        $this->assertFalse($this->otherRequestLock->acquire(self::CHECKOUT_ID, 0));
        $this->assertFalse($this->otherRequestLock->acquire($otherId, 0));

        $guard->releaseAll();
        $guard->releaseAll();

        $this->assertTrue($this->otherRequestLock->acquire(self::CHECKOUT_ID, 0));
        $this->assertTrue($this->otherRequestLock->acquire($otherId, 0));
        $this->otherRequestLock->release($otherId);
    }

    public function testLockIsReleasedOnRefusal(): void
    {
        $this->setMarker(gmdate('Y-m-d H:i:s', time() - 10));

        try {
            $this->createGuard()->guardFrontend(self::CHECKOUT_ID);
            $this->fail('must refuse');
        } catch (CheckoutLockedException $e) {
            $this->assertTrue($this->otherRequestLock->acquire(self::CHECKOUT_ID, 0));
        }
    }

    public function testReleaseDoesNotReleaseALockOfAnotherRequest(): void
    {
        $this->assertTrue($this->otherRequestLock->acquire(self::CHECKOUT_ID, 0));

        $this->createGuard()->release(self::CHECKOUT_ID);

        $this->assertFalse($this->guardLock->acquire(self::CHECKOUT_ID, 0));
    }

    public function testClearMarkerFailureDoesNotRefuseTheKustomCallback(): void
    {
        $this->setMarker(gmdate('Y-m-d H:i:s', time() - 10));
        $marker = $this->createMock(ValidatedMarker::class);
        $marker->method('update')->willThrowException(new \RuntimeException('db down'));

        Bootstrap::getObjectManager()->create(MutationGuard::class, [
            'checkoutOrder' => $this->checkoutOrder,
            'checkoutApi' => $this->checkoutApi,
            'creationLock' => $this->guardLock,
            'validatedMarker' => $marker,
        ])->guardKustomCallback(self::CHECKOUT_ID);

        $this->addToAssertionCount(1);
    }

    public function testKustomCallbackClearsMarkerInsideWindow(): void
    {
        $this->setMarker(gmdate('Y-m-d H:i:s', time() - 10));
        $this->checkoutApi->expects($this->never())->method('getOrder');

        $this->createGuard()->guardKustomCallback(self::CHECKOUT_ID);
        $this->assertNull($this->quoteRepository->getByCheckoutId(self::CHECKOUT_ID)->getValidatedAt());
    }

    private function createGuard(?CheckoutOrder $order = null): MutationGuard
    {
        return Bootstrap::getObjectManager()->create(MutationGuard::class, [
            'checkoutOrder' => $order ?: $this->checkoutOrder,
            'checkoutApi' => $this->checkoutApi,
            'creationLock' => $this->guardLock,
        ]);
    }

    private function setMarker(string $validatedAt): void
    {
        Bootstrap::getObjectManager()->get(ValidatedMarker::class)->update(self::CHECKOUT_ID, $validatedAt);
    }

    /**
     * The test framework replaces the locker by a dummy, so a real database locker with its own connection is used
     */
    private function createLockWithOwnConnection(): CreationLock
    {
        $objectManager = Bootstrap::getObjectManager();
        $locker = new Database(
            $objectManager->create(ResourceConnection::class),
            $objectManager->get(DeploymentConfig::class)
        );

        return new CreationLock($locker);
    }
}
