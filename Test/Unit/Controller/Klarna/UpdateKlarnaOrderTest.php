<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Controller\Klarna;

use Klarna\Kco\Controller\Klarna\UpdateKlarnaOrder;
use Klarna\Kco\Model\Checkout\Kco\Initializer;
use Klarna\Kco\Model\Checkout\Kco\Session;
use Klarna\Kco\Model\Quote;
use Klarna\Kco\Model\Responder\Ajax;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Controller\Klarna\UpdateKlarnaOrder
 */
class UpdateKlarnaOrderTest extends TestCase
{
    /**
     * @var Ajax|MockObject
     */
    private $ajax;
    /**
     * @var Initializer|MockObject
     */
    private $initializer;
    /**
     * @var array
     */
    private array $checkoutIds = [];
    /**
     * @var array
     */
    private array $recurring = [];
    /**
     * @var UpdateKlarnaOrder
     */
    private UpdateKlarnaOrder $controller;

    protected function setUp(): void
    {
        $klarnaQuote = $this->createMock(Quote::class);
        $klarnaQuote->method('getKlarnaCheckoutId')->willReturnCallback(
            fn () => array_shift($this->checkoutIds)
        );
        $klarnaQuote->method('getData')->willReturnCallback(
            fn () => array_shift($this->recurring)
        );
        $session = $this->createMock(Session::class);
        $session->method('getKlarnaQuote')->willReturn($klarnaQuote);

        $this->initializer = $this->createMock(Initializer::class);
        $this->initializer->method('getKcoSession')->willReturn($session);
        $this->ajax = $this->createMock(Ajax::class);

        $this->controller = new UpdateKlarnaOrder(
            $this->createMock(LoggerInterface::class),
            $this->initializer,
            $this->createMock(ManagerInterface::class),
            $this->ajax
        );
    }

    /**
     * @covers ::execute
     */
    public function testUnchangedCheckoutIdKeepsTheResponseIdentical(): void
    {
        $this->checkoutIds = ['order-1', 'order-1'];
        $this->recurring = [0, 0];
        $this->initializer->expects($this->once())->method('updateKlarnaTotals');
        $this->initializer->expects($this->never())->method('getLatestKlarnaCheckout');
        $result = $this->createMock(ResultInterface::class);
        $this->ajax->expects($this->once())->method('getSummaryResponse')->with([])->willReturn($result);

        $this->assertSame($result, $this->controller->execute());
    }

    /**
     * @covers ::execute
     */
    public function testRecurringFlipReturnsTheHtmlSnippet(): void
    {
        $this->checkoutIds = ['order-1', 'order-2'];
        $this->recurring = [0, 1];
        $this->initializer->expects($this->once())->method('updateKlarnaTotals');
        $this->initializer->method('getLatestKlarnaCheckout')
            ->willReturn(new DataObject(['html_snippet' => '<div>new iframe</div>']));
        $result = $this->createMock(ResultInterface::class);
        $this->ajax->expects($this->once())->method('getSummaryResponse')
            ->with(['html_snippet' => '<div>new iframe</div>'])
            ->willReturn($result);

        $this->assertSame($result, $this->controller->execute());
    }

    /**
     * @covers ::execute
     */
    public function testExpiredOrderRecreateWithSameRecurringStateKeepsTheResponseIdentical(): void
    {
        $this->checkoutIds = ['order-1', 'order-2'];
        $this->recurring = [1, 1];
        $this->initializer->expects($this->once())->method('updateKlarnaTotals');
        $this->initializer->expects($this->never())->method('getLatestKlarnaCheckout');
        $result = $this->createMock(ResultInterface::class);
        $this->ajax->expects($this->once())->method('getSummaryResponse')->with([])->willReturn($result);

        $this->assertSame($result, $this->controller->execute());
    }
}
