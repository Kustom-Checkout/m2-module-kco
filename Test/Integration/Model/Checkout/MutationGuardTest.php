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
use Klarna\Kco\Model\Order\Order as CheckoutOrder;
use Klarna\Kco\Model\Quote as KustomQuote;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * KUSTOM-128: the guard which protects the quote between validate and order creation (page changes only)
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

    protected function setUp(): void
    {
        $this->checkoutApi = $this->createMock(Checkout::class);
        $this->checkoutOrder = $this->createMock(CheckoutOrder::class);
        $this->checkoutOrder->method('isMagentoOrderExists')->willReturn(false);
        $this->quoteRepository = Bootstrap::getObjectManager()->get(QuoteRepositoryInterface::class);
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

        $this->expectException(CheckoutLockedException::class);
        $this->expectExceptionMessage('Your payment is being processed');
        $this->createGuard($order)->guardFrontend(self::CHECKOUT_ID);
    }

    public function testRefusedWhenStatusIsUnexpected(): void
    {
        $this->setMarker(gmdate('Y-m-d H:i:s', time() - 400));
        $this->checkoutApi->method('getOrder')->willReturn(['status' => 'something_else']);

        $this->expectException(CheckoutLockedException::class);
        $this->createGuard()->guardFrontend(self::CHECKOUT_ID);
    }

    public function testUnexpectedErrorInsideTheGuardFailsOpen(): void
    {
        $this->setMarker(gmdate('Y-m-d H:i:s', time() - 10));
        $order = $this->createMock(CheckoutOrder::class);
        $order->method('isMagentoOrderExists')->willThrowException(new \RuntimeException('db down'));

        $this->createGuard($order)->guardFrontend(self::CHECKOUT_ID);

        $this->addToAssertionCount(1);
    }

    public function testUnreadableMarkerTimestampFailsOpen(): void
    {
        $quote = $this->createMock(KustomQuote::class);
        $quote->method('getValidatedAt')->willReturn('not-a-date');
        $repository = $this->createMock(QuoteRepositoryInterface::class);
        $repository->method('getByCheckoutId')->willReturn($quote);
        $this->checkoutApi->expects($this->never())->method('getOrder');

        Bootstrap::getObjectManager()->create(MutationGuard::class, [
            'quoteRepository' => $repository,
            'checkoutOrder' => $this->checkoutOrder,
            'checkoutApi' => $this->checkoutApi,
        ])->guardFrontend(self::CHECKOUT_ID);

        $this->addToAssertionCount(1);
    }

    public function testClearMarkerFailureFailsOpen(): void
    {
        $this->setMarker(gmdate('Y-m-d H:i:s', time() - 400));
        $this->checkoutApi->method('getOrder')->willReturn(['status' => 'checkout_incomplete']);
        $marker = $this->createMock(ValidatedMarker::class);
        $marker->method('update')->willThrowException(new \RuntimeException('db down'));

        Bootstrap::getObjectManager()->create(MutationGuard::class, [
            'checkoutOrder' => $this->checkoutOrder,
            'checkoutApi' => $this->checkoutApi,
            'validatedMarker' => $marker,
        ])->guardFrontend(self::CHECKOUT_ID);

        $this->addToAssertionCount(1);
    }

    private function createGuard(?CheckoutOrder $order = null): MutationGuard
    {
        return Bootstrap::getObjectManager()->create(MutationGuard::class, [
            'checkoutOrder' => $order ?: $this->checkoutOrder,
            'checkoutApi' => $this->checkoutApi,
        ]);
    }

    private function setMarker(string $validatedAt): void
    {
        Bootstrap::getObjectManager()->get(ValidatedMarker::class)->update(self::CHECKOUT_ID, $validatedAt);
    }
}
