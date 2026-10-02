<?php
/**
 * Copyright 2025 Kustom AB (Originally developed by Klarna Bank AB)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Checkout\Kco;

use Klarna\Kco\Api\QuoteRepositoryInterface as KlarnaQuoteRepositoryInterface;
use Klarna\Kco\Model\Checkout\Kco\Session;
use Klarna\Kco\Model\QuoteFactory as KlarnaQuoteFactory;
use Klarna\Kss\Model\ShippingMethodGatewayRepository;
use Klarna\Kss\Model\Validator;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteRepository as MageQuoteRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Checkout\Kco\Session
 */
class SessionTest extends TestCase
{
    /**
     * @var Session
     */
    private $model;
    /**
     * @var CheckoutSession|MockObject
     */
    private $checkoutSession;
    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $cartRepository;
    /**
     * @var Validator|MockObject
     */
    private $validator;

    protected function setUp(): void
    {
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->cartRepository  = $this->createMock(CartRepositoryInterface::class);
        $this->validator       = $this->createMock(Validator::class);
        $this->validator->method('getKssUsedFlag')->willReturn(Validator::CHECK_NOT_SET);

        $this->model = new Session(
            $this->checkoutSession,
            $this->createMock(CustomerSession::class),
            $this->createMock(KlarnaQuoteRepositoryInterface::class),
            $this->createMock(MageQuoteRepository::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(ShippingMethodGatewayRepository::class),
            $this->validator,
            $this->createMock(KlarnaQuoteFactory::class),
            $this->cartRepository
        );
    }

    /**
     * Simulates Magento_NegotiableQuote: getQuoteId() loads the quote and collects totals,
     * which re-enters the KSS check while the quote is still loading.
     *
     * @covers ::getQuote
     * @covers ::hasActiveKlarnaShippingGatewayInformation
     */
    public function testNestedCallsDuringQuoteLoadDoNotRecurseOrCacheResult(): void
    {
        $quote  = $this->createMock(CartInterface::class);
        $nested = [];
        $this->checkoutSession->expects(static::once())
            ->method('getQuoteId')
            ->willReturnCallback(function () use (&$nested) {
                $nested['quote']     = $this->model->getQuote();
                $nested['hasActive'] = $this->model->hasActiveKlarnaShippingGatewayInformation();
                return 1;
            });
        $this->cartRepository->expects(static::once())->method('get')->with(1)->willReturn($quote);
        $this->validator->expects(static::never())->method('setKssUsed');

        static::assertSame($quote, $this->model->getQuote());
        static::assertNull($nested['quote']);
        static::assertFalse($nested['hasActive']);
        static::assertSame($quote, $this->model->getQuote());
    }

    /**
     * @covers ::getQuote
     */
    public function testGetQuoteResetsGuardAfterLogicException(): void
    {
        $quote = $this->createMock(CartInterface::class);
        $this->checkoutSession->method('getQuoteId')
            ->willReturnOnConsecutiveCalls(static::throwException(new \LogicException()), 1);
        $this->cartRepository->method('get')->willReturn($quote);

        static::assertNull($this->model->getQuote());
        static::assertSame($quote, $this->model->getQuote());
    }
}
