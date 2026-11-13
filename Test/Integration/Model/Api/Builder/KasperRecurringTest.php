<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Model\Api\Builder;

use Klarna\Kco\Api\QuoteRepositoryInterface;
use Klarna\Kco\Api\RecurringResolverInterface;
use Klarna\Kco\Model\Api\Builder\Kasper;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The create and update payloads for a real quote: a recurring resolver adds exactly `recurring` and
 * `options.subscription_buy_button`, a non recurring one leaves the payload unchanged.
 *
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
 */
class KasperRecurringTest extends TestCase
{
    #[DataProvider('methodProvider')]
    public function testRecurringAddsOnlyTheSubscriptionFields(string $method): void
    {
        $baseline = $this->generate($method, false);
        $recurring = $this->generate($method, true);

        static::assertArrayNotHasKey('recurring', $baseline);
        static::assertArrayNotHasKey('subscription_buy_button', $baseline['options'] ?? []);

        static::assertTrue($recurring['recurring']);
        static::assertTrue($recurring['options']['subscription_buy_button']);

        $expected = $baseline;
        $expected['recurring'] = true;
        $expected['options']['subscription_buy_button'] = true;
        static::assertEquals($expected, $recurring);
    }

    /**
     * No resolver argument: the default DI resolver must not change the payload (non-regression for merchants).
     */
    #[DataProvider('methodProvider')]
    public function testDefaultDiResolverLeavesThePayloadWithoutSubscriptionFields(string $method): void
    {
        $payload = $this->generate($method, null);

        static::assertArrayNotHasKey('recurring', $payload);
        static::assertArrayNotHasKey('subscription_buy_button', $payload['options'] ?? []);
        static::assertEquals($this->generate($method, false), $payload);
    }

    public static function methodProvider(): array
    {
        return [
            'create' => ['generateCreateRequest'],
            'update' => ['generateUpdateRequest'],
        ];
    }

    private function generate(string $method, ?bool $recurring): array
    {
        $objectManager = Bootstrap::getObjectManager();
        $quoteId = (int)$objectManager->get(QuoteRepositoryInterface::class)
            ->getByCheckoutId('123456-1234-1234-1234-1234567890')->getQuoteId();
        $quote = $objectManager->get(CartRepositoryInterface::class)->get($quoteId);

        $arguments = [];
        if ($recurring !== null) {
            $resolver = $this->createMock(RecurringResolverInterface::class);
            $resolver->method('isRecurring')->willReturn($recurring);
            $arguments['recurringResolver'] = $resolver;
        }

        $builder = $objectManager->create(Kasper::class, $arguments);
        $builder->$method($quote);

        return $builder->getRequest();
    }
}
