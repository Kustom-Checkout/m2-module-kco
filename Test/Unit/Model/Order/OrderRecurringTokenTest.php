<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Order;

use Klarna\AdminSettings\Model\Configurations\Kco\Checkout;
use Klarna\Backend\Model\Api\Factory;
use Klarna\Base\Model\Order as KustomOrder;
use Klarna\Base\Model\OrderRepository;
use Klarna\Kco\Api\QuoteInterface;
use Klarna\Kco\Model\Cart\Validations\Handler;
use Klarna\Kco\Model\Checkout\Kco\Initializer;
use Klarna\Kco\Model\Checkout\Kco\Session as KcoSession;
use Klarna\Kco\Model\Order\Order;
use Klarna\Kco\Model\Order\Shop\Action;
use Klarna\Kco\Model\PaymentStatus;
use Klarna\Kco\Model\Recurring\TokenPersister;
use Klarna\Kco\Model\WorkflowProvider;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Model\Order as SalesOrder;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderRepository as MageOrderRepository;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Order\Order
 */
class OrderRecurringTokenTest extends TestCase
{
    /**
     * A failing token persister must not affect the created orders (AC6)
     *
     * @covers ::createMagentoOrder
     * @covers ::persistRecurringToken
     */
    public function testThrowingTokenPersisterLeavesTheOrderIntact(): void
    {
        $mageOrder = $this->createMock(SalesOrder::class);
        $mageOrder->method('getIncrementId')->willReturn('000000042');

        $klarnaOrder = $this->createMock(KustomOrder::class);
        $emptyKlarnaOrder = $this->createMock(KustomOrder::class);
        $emptyKlarnaOrder->method('getId')->willReturn(null);

        $quote = $this->createMock(Quote::class);
        $quote->method('getReservedOrderId')->willReturn('000000042');

        $workflowProvider = $this->createMock(WorkflowProvider::class);
        $workflowProvider->method('getKlarnaOrder')->willReturnOnConsecutiveCalls($emptyKlarnaOrder, $klarnaOrder);
        $workflowProvider->method('getMagentoQuote')->willReturn($quote);
        $workflowProvider->expects($this->once())->method('clearKlarnaOrder');

        $searchResult = $this->createMock(OrderSearchResultInterface::class);
        $searchResult->method('getTotalCount')->willReturn(0);
        $mageOrderRepository = $this->createMock(MageOrderRepository::class);
        $mageOrderRepository->method('getList')->willReturn($searchResult);
        $criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $criteriaBuilder->method('addFilter')->willReturnSelf();
        $criteriaBuilder->method('create')->willReturn($this->createMock(SearchCriteria::class));

        $checkout = new DataObject(['status' => 'checkout_complete', 'order_id' => 'kustom-1']);
        $initializer = $this->createMock(Initializer::class);
        $initializer->method('getKlarnaCheckout')->willReturn($checkout);
        $initializer->method('getReservationId')->willReturn('reservation-1');

        $action = $this->createMock(Action::class);
        $action->method('createOrder')->willReturn($mageOrder);

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->expects($this->once())->method('save')->with($klarnaOrder);

        $persister = $this->createMock(TokenPersister::class);
        $persister->expects($this->once())->method('persist')
            ->willThrowException(new \RuntimeException('secret-token-value'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            $this->logicalAnd(
                $this->stringContains('RuntimeException'),
                $this->logicalNot($this->stringContains('secret-token-value'))
            )
        );

        $kcoSession = $this->createMock(KcoSession::class);
        $kcoSession->method('getKlarnaQuote')->willReturn($this->createMock(QuoteInterface::class));

        $model = new Order(
            $kcoSession,
            $initializer,
            $logger,
            $orderRepository,
            $this->createMock(OrderSender::class),
            $workflowProvider,
            $this->createMock(Factory::class),
            $mageOrderRepository,
            $this->createMock(PaymentStatus::class),
            $this->createMock(Handler::class),
            $action,
            $this->createMock(Checkout::class),
            $criteriaBuilder,
            $this->createMock(InvoiceOrderInterface::class),
            $persister
        );

        $this->assertSame($mageOrder, $model->createMagentoOrder('kustom-1'));
    }
}
