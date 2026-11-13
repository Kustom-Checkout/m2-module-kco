<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Model\Checkout\Kco;

use Klarna\Kco\Api\ApiInterface;
use Klarna\Kco\Api\QuoteRepositoryInterface;
use Klarna\Kco\Api\RecurringResolverInterface;
use Klarna\Kco\Model\Api\Builder\Kasper as KasperBuilder;
use Klarna\Kco\Model\Api\Factory;
use Klarna\Kco\Model\Api\Kasper;
use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Klarna\Kco\Model\Checkout\Kco\Initializer;
use Klarna\Kco\Model\Checkout\Kco\Session;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Recreate-on-flip with a real kustom quote row (klarna_kco_quote.is_recurring). The resolver and the API layer are
 * stubbed, everything else (session, repository, table) is real.
 *
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
 */
class InitializerRecurringTest extends TestCase
{
    private const OLD_ID = '123456-1234-1234-1234-1234567890';
    private const NEW_ID = 'new-kustom-order-id';

    /**
     * @var ApiInterface|MockObject
     */
    private $api;
    /**
     * @var RecurringResolverInterface|MockObject
     */
    private $resolver;
    /**
     * @var QuoteRepositoryInterface
     */
    private $klarnaQuoteRepository;
    /**
     * @var Session
     */
    private $session;
    /**
     * @var Initializer
     */
    private $initializer;
    /**
     * @var CartInterface
     */
    private $quote;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->klarnaQuoteRepository = $objectManager->get(QuoteRepositoryInterface::class);

        // The fixture quote is not necessarily the first quote of the table, take it from its kustom quote row.
        $quoteId = (int)$this->klarnaQuoteRepository->getByCheckoutId(self::OLD_ID)->getQuoteId();
        $this->quote = $objectManager->get(CartRepositoryInterface::class)->get($quoteId);

        $this->session = $objectManager->create(Session::class);
        $this->session->setQuote($this->quote);

        $this->api = $this->createMock(ApiInterface::class);
        $factory = $this->createMock(Factory::class);
        $factory->method('createApiInstance')->willReturn($this->api);
        $this->resolver = $this->createMock(RecurringResolverInterface::class);

        $this->initializer = $objectManager->create(Initializer::class, [
            'session' => $this->session,
            'factory' => $factory,
            'recurringResolver' => $this->resolver,
        ]);
    }

    public function testNoFlipUpdatesTheExistingKustomOrder(): void
    {
        $this->storeRecurringState(0);
        $this->resolver->method('isRecurring')->willReturn(false);
        $this->api->expects($this->never())->method('createOrder');
        $this->api->expects($this->once())->method('updateOrder')
            ->willReturn(new DataObject(['order_id' => self::OLD_ID]));

        $this->initializer->updateKlarnaCheckout(self::OLD_ID);

        static::assertSame(self::OLD_ID, $this->getActiveKlarnaQuote()->getKlarnaCheckoutId());
        static::assertSame(0, (int)$this->getActiveKlarnaQuote()->getData('is_recurring'));
        static::assertSame(1, $this->getKlarnaQuoteRowCount());
    }

    public function testFlipToRecurringCreatesANewKustomOrderAndDeactivatesTheOldRow(): void
    {
        $this->storeRecurringState(0);
        $this->resolver->method('isRecurring')->willReturn(true);
        $this->api->expects($this->never())->method('updateOrder');
        $this->api->expects($this->once())->method('createOrder')
            ->willReturn(new DataObject(['order_id' => self::NEW_ID]));

        $this->initializer->updateKlarnaCheckout(self::OLD_ID);

        $active = $this->getActiveKlarnaQuote();
        static::assertSame(self::NEW_ID, $active->getKlarnaCheckoutId());
        static::assertSame(1, (int)$active->getData('is_recurring'));
        static::assertSame(2, $this->getKlarnaQuoteRowCount());
    }

    public function testFlipToNonRecurringCreatesANewKustomOrder(): void
    {
        $this->storeRecurringState(1);
        $this->resolver->method('isRecurring')->willReturn(false);
        $this->api->expects($this->never())->method('updateOrder');
        $this->api->expects($this->once())->method('createOrder')
            ->willReturn(new DataObject(['order_id' => self::NEW_ID]));

        $this->initializer->updateKlarnaCheckout(self::OLD_ID);

        $active = $this->getActiveKlarnaQuote();
        static::assertSame(self::NEW_ID, $active->getKlarnaCheckoutId());
        static::assertSame(0, (int)$active->getData('is_recurring'));
    }

    /**
     * Kasper::updateOrder falls back to createOrder on a 404 (expired Kustom order). The new active row must carry
     * the recurring state, so that the next update does not recreate the order again.
     */
    public function testExpiredOrderFallbackStoresTheRecurringStateOnTheNewRow(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->storeRecurringState(1);

        $checkout = $this->createMock(Checkout::class);
        $checkout->method('updateOrder')->willReturn(['response_status_code' => 404, 'is_successful' => false]);
        $checkout->expects($this->once())->method('createOrder')
            ->willReturn(['is_successful' => true, 'order_id' => self::NEW_ID]);
        $builder = $this->createMock(KasperBuilder::class);
        $builder->method('generateCreateRequest')->willReturnSelf();
        $builder->method('generateUpdateRequest')->willReturnSelf();
        $builder->method('getRequest')->willReturn([]);
        $kasper = $objectManager->create(Kasper::class, [
            'kcoSession' => $this->session,
            'checkout' => $checkout,
            'kasperBuilder' => $builder,
        ]);
        $factory = $this->createMock(Factory::class);
        $factory->method('createApiInstance')->willReturn($kasper);
        $resolver = $this->createMock(RecurringResolverInterface::class);
        $resolver->method('isRecurring')->willReturn(true);
        $initializer = $objectManager->create(Initializer::class, [
            'session' => $this->session,
            'factory' => $factory,
            'recurringResolver' => $resolver,
        ]);

        $initializer->updateKlarnaCheckout(self::OLD_ID);

        $active = $this->getActiveKlarnaQuote();
        static::assertSame(self::NEW_ID, $active->getKlarnaCheckoutId());
        static::assertSame(1, (int)$active->getData('is_recurring'));
        static::assertSame(2, $this->getKlarnaQuoteRowCount());
    }

    private function storeRecurringState(int $state): void
    {
        $klarnaQuote = $this->klarnaQuoteRepository->getActiveByQuote($this->quote);
        $klarnaQuote->setData('is_recurring', $state);
        $this->klarnaQuoteRepository->save($klarnaQuote);
        $this->session->setKlarnaQuote($this->klarnaQuoteRepository->getActiveByQuote($this->quote));
    }

    private function getActiveKlarnaQuote()
    {
        return $this->klarnaQuoteRepository->getActiveByQuote($this->quote);
    }

    private function getKlarnaQuoteRowCount(): int
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        return (int)$connection->fetchOne(
            $connection->select()
                ->from($resource->getTableName('klarna_kco_quote'), 'COUNT(*)')
                ->where('quote_id = ?', $this->quote->getId())
        );
    }
}
