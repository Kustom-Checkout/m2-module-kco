<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Checkout\Kco;

use Klarna\Kco\Api\ApiInterface;
use Klarna\Kco\Api\QuoteRepositoryInterface;
use Klarna\Kco\Api\RecurringResolverInterface;
use Klarna\Kco\Model\Api\Factory;
use Klarna\Kco\Model\Checkout\Kco\Initializer;
use Klarna\Kco\Model\Checkout\Kco\Session;
use Klarna\Kco\Model\Quote;
use Klarna\Kss\Model\Checkout\Update as KssUpdate;
use Klarna\Kss\Model\Factory as KssFactory;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote as MagentoQuote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Checkout\Kco\Initializer
 */
class InitializerRecurringTest extends TestCase
{
    private const OLD_ID = 'old-order-id';
    private const NEW_ID = 'new-order-id';

    /**
     * @var ApiInterface|MockObject
     */
    private $api;
    /**
     * @var Quote|MockObject
     */
    private $klarnaQuote;
    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;
    /**
     * @var RecurringResolverInterface|MockObject
     */
    private $resolver;
    /**
     * @var array
     */
    private array $stored = [];
    /**
     * @var Initializer
     */
    private Initializer $model;

    protected function setUp(): void
    {
        $this->api = $this->createMock(ApiInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->resolver = $this->createMock(RecurringResolverInterface::class);

        $cart = $this->createMock(MagentoQuote::class);
        $cart->method('getId')->willReturn(7);

        $this->klarnaQuote = $this->createMock(Quote::class);
        $this->klarnaQuote->method('getKlarnaCheckoutId')->willReturn(self::OLD_ID);
        $this->klarnaQuote->method('getData')->willReturnCallback(
            fn ($key) => $this->stored[$key] ?? null
        );
        $this->klarnaQuote->method('setData')->willReturnCallback(
            function ($key, $value) {
                $this->stored[$key] = $value;
                return $this->klarnaQuote;
            }
        );

        $session = $this->createMock(Session::class);
        $session->method('getQuote')->willReturn($cart);
        $session->method('getKlarnaQuote')->willReturn($this->klarnaQuote);

        $factory = $this->createMock(Factory::class);
        $factory->method('createApiInstance')->willReturn($this->api);

        $this->model = new Initializer(
            $session,
            $factory,
            $this->createMock(QuoteRepositoryInterface::class),
            $this->createMock(KssUpdate::class),
            $this->createMock(KssFactory::class),
            $this->resolver,
            $this->logger
        );
    }

    /**
     * @param bool $stored
     * @param bool $current
     */
    private function prepare(bool $stored, bool $current): void
    {
        $this->stored['is_recurring'] = (int)$stored;
        $this->resolver->method('isRecurring')->willReturn($current);
    }

    /**
     * @covers ::updateKlarnaCheckout
     */
    public function testFlipFalseToTrueCreatesNewOrder(): void
    {
        $this->prepare(false, true);
        $this->api->expects($this->never())->method('updateOrder');
        $this->api->expects($this->once())->method('createOrder')
            ->willReturn(new DataObject(['order_id' => self::NEW_ID]));
        $this->logger->expects($this->once())->method('info')
            ->with($this->stringContains(self::OLD_ID));

        $result = $this->model->updateKlarnaCheckout(self::OLD_ID);

        $this->assertSame(self::NEW_ID, $result->getOrderId());
        $this->assertSame(1, $this->stored['is_recurring']);
    }

    /**
     * @covers ::updateKlarnaCheckout
     */
    public function testRecreateEvaluatesTheResolverOnce(): void
    {
        $this->stored['is_recurring'] = 0;
        $this->resolver->expects($this->once())->method('isRecurring')->willReturn(true);
        $this->api->expects($this->once())->method('createOrder')
            ->willReturn(new DataObject(['order_id' => self::NEW_ID]));

        $this->model->updateKlarnaCheckout(self::OLD_ID);

        $this->assertSame(1, $this->stored['is_recurring']);
    }

    /**
     * @covers ::updateKlarnaCheckout
     */
    public function testFlipTrueToFalseCreatesNewOrder(): void
    {
        $this->prepare(true, false);
        $this->api->expects($this->never())->method('updateOrder');
        $this->api->expects($this->once())->method('createOrder')
            ->willReturn(new DataObject(['order_id' => self::NEW_ID]));
        $this->logger->expects($this->once())->method('info');

        $this->model->updateKlarnaCheckout(self::OLD_ID);

        $this->assertSame(0, $this->stored['is_recurring']);
    }

    /**
     * @covers ::updateKlarnaCheckout
     */
    public function testNoFlipUpdatesExistingOrder(): void
    {
        $this->prepare(true, true);
        $this->api->expects($this->never())->method('createOrder');
        $this->api->expects($this->once())->method('updateOrder')->with(self::OLD_ID)
            ->willReturn(new DataObject(['order_id' => self::OLD_ID]));
        $this->logger->expects($this->never())->method('info');

        $this->model->updateKlarnaCheckout(self::OLD_ID);

        $this->assertSame(1, $this->stored['is_recurring']);
    }

    /**
     * @covers ::updateKlarnaCheckout
     */
    public function testResolverFalseThroughoutKeepsUpdatePath(): void
    {
        // Existing rows have no value set at all (column default 0 => loaded as null/0 here).
        $this->resolver->method('isRecurring')->willReturn(false);
        $this->api->expects($this->never())->method('createOrder');
        $this->api->expects($this->once())->method('updateOrder')
            ->willReturn(new DataObject(['order_id' => self::OLD_ID]));
        $this->logger->expects($this->never())->method('info');

        $this->model->updateKlarnaCheckout(self::OLD_ID);

        $this->assertSame(0, $this->stored['is_recurring']);
    }

    /**
     * @covers ::createKlarnaCheckout
     */
    public function testCreateStoresResolverResult(): void
    {
        $this->resolver->method('isRecurring')->willReturn(true);
        $this->api->expects($this->once())->method('createOrder')
            ->willReturn(new DataObject(['order_id' => self::NEW_ID]));

        $this->model->createKlarnaCheckout();

        $this->assertSame(1, $this->stored['is_recurring']);
    }
}
