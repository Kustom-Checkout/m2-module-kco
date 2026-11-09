<?php
/**
 * Copyright © Kustom AB (Originally developed by Klarna Bank AB)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Checkout\Validations;

use Klarna\Base\Exception as KlarnaException;
use Klarna\Kco\Model\Cart\Validations\ShippingMethod;
use Klarna\Kco\Model\Carrier\Klarna;
use Klarna\Kss\Api\ShippingMethodGatewayInterface;
use Klarna\Kss\Model\KssConfigProvider;
use Klarna\Kss\Model\ShippingMethodGatewayRepository;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\Store;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Cart\Validations\ShippingMethod
 */
#[AllowMockObjectsWithoutExpectations]
class ShippingMethodTest extends TestCase
{
    private const KUSTOM_ORDER_ID = '3823342b-7f23-6580-975f-b448f942085c';

    /**
     * @var MockObject|Address
     */
    private $address;
    /**
     * @var ShippingMethod
     */
    private $model;
    /**
     * @var DataObject
     */
    private $request;
    /**
     * @var MockObject|Quote
     */
    private $quote;
    /**
     * @var MockObject|KssConfigProvider
     */
    private $kssConfigProvider;
    /**
     * @var MockObject|ShippingMethodGatewayRepository
     */
    private $shippingMethodGatewayRepository;

    /**
     * @covers ::validate
     */
    public function testValidateWithVirtualQuote(): void
    {
        $this->quote->expects(static::once())
            ->method('isVirtual')
            ->willReturn(true);
        $this->address->expects(static::never())
            ->method('getShippingMethod');

        $this->model->validate($this->request, $this->quote);
    }

    /**
     * @covers ::validate
     */
    public function testValidateWithMatchingShippingMethods(): void
    {
        $this->quote->method('isVirtual')->willReturn(false);
        $this->address->expects(static::once())
            ->method('getShippingMethod')
            ->willReturn('some-method');
        $this->request->setSelectedShippingOption(['id' => 'some-method']);

        $this->model->validate($this->request, $this->quote);
    }

    /**
     * @covers ::validate
     */
    public function testValidateThrowsExceptionWithNotMatchingShippingMethods(): void
    {
        $this->expectException(KlarnaException::class);
        $this->expectExceptionMessage(
            "Shipping method does not match for order #AN-ORDER-ID. " .
            "Kustom method is some-other-method vs Magento method is some-method"
        );
        $this->quote->method('isVirtual')->willReturn(false);
        $this->address->method('getShippingMethod')->willReturn('some-method');
        $this->request->setSelectedShippingOption(['id' => 'some-other-method']);

        $this->model->validate($this->request, $this->quote);
    }

    /**
     * @covers ::validate
     */
    public function testValidateUsesShippingFeeLineReferenceWithoutSelectedShippingOption(): void
    {
        $this->quote->method('isVirtual')->willReturn(false);
        $this->address->method('getShippingMethod')->willReturn('flatrate_flatrate');
        $this->request->setOrderLines([
            ['type' => 'physical', 'reference' => 'SKU-1'],
            ['type' => 'shipping_fee', 'reference' => 'flatrate_flatrate'],
        ]);

        $this->model->validate($this->request, $this->quote);
    }

    /**
     * An active KSS entry left from a session that started before KSS was disabled must be ignored.
     *
     * @covers ::validate
     */
    public function testValidateIgnoresActiveKssEntryWhenKssIsDisabled(): void
    {
        $this->quote->method('isVirtual')->willReturn(false);
        $this->address->method('getShippingMethod')->willReturn('flatrate_flatrate');
        $this->request->setSelectedShippingOption(['id' => 'flatrate_flatrate']);
        $this->kssConfigProvider->method('isKssEnabled')->willReturn(false);
        $this->shippingMethodGatewayRepository->expects(static::never())
            ->method('loadShipping');

        $this->model->validate($this->request, $this->quote);
    }

    /**
     * @covers ::validate
     */
    public function testValidateExpectsGatewayMethodWhenKssIsEnabledAndEntryActive(): void
    {
        $this->quote->method('isVirtual')->willReturn(false);
        $this->address->method('getShippingMethod')->willReturn(Klarna::GATEWAY_KEY);
        $this->request->setSelectedShippingOption(['id' => 'kss-option-id']);
        $this->mockKssEntry(true);

        $this->model->validate($this->request, $this->quote);
    }

    /**
     * @covers ::validate
     */
    public function testValidateThrowsExceptionWhenKssIsEnabledAndEntryActiveButQuoteHasOtherMethod(): void
    {
        $this->expectException(KlarnaException::class);
        $this->expectExceptionMessage(
            "Shipping method does not match for order #AN-ORDER-ID. " .
            "Kustom method is " . Klarna::GATEWAY_KEY . " vs Magento method is flatrate_flatrate"
        );
        $this->quote->method('isVirtual')->willReturn(false);
        $this->address->method('getShippingMethod')->willReturn('flatrate_flatrate');
        $this->request->setSelectedShippingOption(['id' => 'flatrate_flatrate']);
        $this->mockKssEntry(true);

        $this->model->validate($this->request, $this->quote);
    }

    /**
     * @covers ::validate
     */
    public function testValidateUsesRequestMethodWhenKssIsEnabledAndEntryInactive(): void
    {
        $this->quote->method('isVirtual')->willReturn(false);
        $this->address->method('getShippingMethod')->willReturn('flatrate_flatrate');
        $this->request->setSelectedShippingOption(['id' => 'flatrate_flatrate']);
        $this->mockKssEntry(false);

        $this->model->validate($this->request, $this->quote);
    }

    /**
     * Mocking KSS as enabled with a gateway entry for the Kustom order
     *
     * @param bool $isActive
     */
    private function mockKssEntry(bool $isActive): void
    {
        $entry = $this->createStub(ShippingMethodGatewayInterface::class);
        $entry->method('isActive')->willReturn($isActive);
        $this->kssConfigProvider->method('isKssEnabled')->willReturn(true);
        $this->shippingMethodGatewayRepository->expects(static::once())
            ->method('loadShipping')
            ->with(self::KUSTOM_ORDER_ID, 'klarna_session_id')
            ->willReturn($entry);
    }

    protected function setUp(): void
    {
        $this->shippingMethodGatewayRepository = $this->createMock(ShippingMethodGatewayRepository::class);
        $this->kssConfigProvider               = $this->createMock(KssConfigProvider::class);
        $this->model                           = new ShippingMethod(
            $this->shippingMethodGatewayRepository,
            $this->kssConfigProvider
        );

        $this->request = new DataObject([
            'order_id'    => self::KUSTOM_ORDER_ID,
            'order_lines' => [],
        ]);

        $this->address = $this->createMock(Address::class);
        $this->quote   = $this->createMock(Quote::class);
        $this->quote->method('getShippingAddress')->willReturn($this->address);
        $this->quote->method('getReservedOrderId')->willReturn('AN-ORDER-ID');
        $this->quote->method('getStore')->willReturn($this->createStub(Store::class));
    }
}
