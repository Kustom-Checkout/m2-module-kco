<?php
/**
 * Copyright 2025 Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);


namespace Klarna\Kco\Test\Unit\Model\ShippingMethodGateway;

use Klarna\Kco\Api\QuoteInterface as KlarnaQuoteInterface;
use Klarna\Kco\Api\QuoteRepositoryInterface as KlarnaQuoteRepositoryInterface;
use Klarna\Kco\Model\Checkout\Configuration\SettingsProvider;
use Klarna\Kco\Model\ShippingMethodGateway\GatewayResolver;
use Klarna\Kss\Api\ShippingMethodGatewayInterface;
use Klarna\Kss\Api\ShippingMethodGatewayRepositoryInterface;
use Klarna\Kss\Model\KssConfigProvider;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\ShippingMethodGateway\GatewayResolver
 */
class GatewayResolverTest extends TestCase
{
    /**
     * @var GatewayResolver
     */
    private $model;
    /**
     * @var SettingsProvider|MockObject
     */
    private $settingsProvider;
    /**
     * @var KssConfigProvider|MockObject
     */
    private $kssConfigProvider;
    /**
     * @var KlarnaQuoteRepositoryInterface|MockObject
     */
    private $klarnaQuoteRepository;
    /**
     * @var ShippingMethodGatewayRepositoryInterface|MockObject
     */
    private $gatewayRepository;
    /**
     * @var Quote|MockObject
     */
    private $quote;
    /**
     * @var KlarnaQuoteInterface|MockObject
     */
    private $klarnaQuote;
    /**
     * @var ShippingMethodGatewayInterface|MockObject
     */
    private $gateway;

    protected function setUp(): void
    {
        $this->settingsProvider      = $this->createMock(SettingsProvider::class);
        $this->kssConfigProvider     = $this->createMock(KssConfigProvider::class);
        $this->klarnaQuoteRepository = $this->createMock(KlarnaQuoteRepositoryInterface::class);
        $this->gatewayRepository     = $this->createMock(ShippingMethodGatewayRepositoryInterface::class);
        $this->klarnaQuote           = $this->createMock(KlarnaQuoteInterface::class);
        $this->gateway               = $this->createMock(ShippingMethodGatewayInterface::class);

        $this->quote = $this->createMock(Quote::class);
        $this->quote->method('getId')->willReturn(1);
        $this->quote->method('getStore')->willReturn($this->createMock(Store::class));

        $this->model = new GatewayResolver(
            $this->settingsProvider,
            $this->kssConfigProvider,
            $this->klarnaQuoteRepository,
            $this->gatewayRepository
        );
    }

    private function givenActiveSetup(): void
    {
        $this->settingsProvider->method('isKlarnaCheckoutPaymentEnabled')->willReturn(true);
        $this->kssConfigProvider->method('isKssEnabled')->willReturn(true);
        $this->klarnaQuote->method('getIsActive')->willReturn(1);
        $this->klarnaQuote->method('getKlarnaCheckoutId')->willReturn('abc');
        $this->klarnaQuoteRepository->method('getActiveByQuote')->willReturn($this->klarnaQuote);
        $this->gatewayRepository->method('loadShipping')->with('abc', 'klarna_session_id')->willReturn($this->gateway);
    }

    /**
     * @covers ::getActiveGateway
     */
    public function testReturnsActiveGateway(): void
    {
        $this->givenActiveSetup();
        $this->gateway->method('isActive')->willReturn(true);

        static::assertSame($this->gateway, $this->model->getActiveGateway($this->quote));
    }

    /**
     * @covers ::getActiveGateway
     */
    public function testReturnsNullWhenKcoDisabledWithoutLookups(): void
    {
        $this->settingsProvider->method('isKlarnaCheckoutPaymentEnabled')->willReturn(false);
        $this->kssConfigProvider->method('isKssEnabled')->willReturn(true);
        $this->klarnaQuoteRepository->expects(static::never())->method('getActiveByQuote');

        static::assertNull($this->model->getActiveGateway($this->quote));
    }

    /**
     * @covers ::getActiveGateway
     */
    public function testReturnsNullWhenKssDisabled(): void
    {
        $this->settingsProvider->method('isKlarnaCheckoutPaymentEnabled')->willReturn(true);
        $this->kssConfigProvider->method('isKssEnabled')->willReturn(false);
        $this->klarnaQuoteRepository->expects(static::never())->method('getActiveByQuote');

        static::assertNull($this->model->getActiveGateway($this->quote));
    }

    /**
     * @covers ::getActiveGateway
     */
    public function testReturnsNullForVirtualQuote(): void
    {
        $this->quote->method('isVirtual')->willReturn(true);
        $this->settingsProvider->expects(static::never())->method('isKlarnaCheckoutPaymentEnabled');

        static::assertNull($this->model->getActiveGateway($this->quote));
    }

    /**
     * @covers ::getActiveGateway
     */
    public function testReturnsNullWithoutKlarnaQuote(): void
    {
        $this->settingsProvider->method('isKlarnaCheckoutPaymentEnabled')->willReturn(true);
        $this->kssConfigProvider->method('isKssEnabled')->willReturn(true);
        $this->klarnaQuoteRepository->method('getActiveByQuote')
            ->willThrowException(new NoSuchEntityException());

        static::assertNull($this->model->getActiveGateway($this->quote));
    }

    /**
     * @covers ::getActiveGateway
     */
    public function testReturnsNullForInactiveGateway(): void
    {
        $this->givenActiveSetup();
        $this->gateway->method('isActive')->willReturn(false);

        static::assertNull($this->model->getActiveGateway($this->quote));
    }
}
