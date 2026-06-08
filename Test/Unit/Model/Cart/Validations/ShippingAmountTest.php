<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

namespace Klarna\Kco\Test\Unit\Model\Checkout\Validations;

use Klarna\Kco\Model\Cart\Validations\ShippingAmount;
use Klarna\Base\Test\Unit\Mock\MockFactory;
use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Cart\Validations\ShippingAmount
 */
class ShippingAmountTest extends TestCase
{
    /**
     * @var MockObject|Address
     */
    private $address;
    /**
     * @var ShippingAmount
     */
    private $model;
    /**
     * @var MockObject[]
     */
    private $dependencyMocks;
    /**
     * @var MockObject|DataObject
     */
    private $request;
    /**
     * @var MockObject|Quote
     */
    private $quote;

    /**
     * @covers ::validate
     */
    public function testValidateWithVirtualQuote(): void
    {
        $this->quote->expects(static::once())
            ->method('isVirtual')
            ->willReturn(true);

        $this->model->validate($this->request, $this->quote);
    }

    /**
     * @covers ::validate
     */
    public function testValidateWithMatchingShippingAmounts(): void
    {
        $this->quote->expects(static::once())
            ->method('isVirtual')
            ->willReturn(false);
        $this->address
            ->expects(static::once())
            ->method('getBaseShippingInclTax')
            ->willReturn('1.23');
        $this->dependencyMocks['dataConverter']
            ->method('toApiFloat')
            ->with(1.23)
            ->willReturn('123');
        $this->dependencyMocks['taxConfig']
            ->method('shippingPriceIncludesTax')
            ->willReturn(true);

        $orderLines[] = [
            'type' => 'shipping_fee',
            'total_amount' => 123
        ];
        $this->request
            ->expects(static::exactly(1))
            ->method('getOrderLines')
            ->willReturn($orderLines);

        $this->model->validate($this->request, $this->quote);
    }

    /**
     * @covers ::validate
     */
    public function testValidateThrowsExceptionWithNotMatchingShippingAmounts(): void
    {
        $this->expectException(\Klarna\Base\Exception::class);
        $this->expectExceptionMessage(
            "Shipping amount does not match for order AN-ORDER-ID. Kustom amount is 124 vs Magento amount is 123"
        );
        $this->quote->expects(static::once())
            ->method('isVirtual')
            ->willReturn(false);
        $this->address
            ->expects(static::once())
            ->method('getBaseShippingInclTax')
            ->willReturn('1.23');
        $this->dependencyMocks['dataConverter']
            ->method('toApiFloat')
            ->with(1.23)
            ->willReturn('123');
        $this->dependencyMocks['taxConfig']
            ->method('shippingPriceIncludesTax')
            ->willReturn(true);

        $orderLines[] = [
            'type' => 'shipping_fee',
            'total_amount' => 124
        ];
        $this->request
            ->expects(static::exactly(1))
            ->method('getOrderLines')
            ->willReturn($orderLines);

        $this->model->validate($this->request, $this->quote);
    }

    /**
     * @covers ::validate
     */
    public function testValidateThrowsExceptionWithNotMatchingShippingAmountsWithTaxExcluded(): void
    {
        $this->expectException(\Klarna\Base\Exception::class);
        $this->expectExceptionMessage(
            "Shipping amount does not match for order AN-ORDER-ID. Kustom amount is 124 vs Magento amount is 123"
        );
        $this->performBasePriceMocking();

        $orderLines[] = [
            'type' => 'shipping_fee',
            'total_amount' => 124
        ];
        $this->request
            ->expects(static::exactly(1))
            ->method('getOrderLines')
            ->willReturn($orderLines);

        $this->model->validate($this->request, $this->quote);
    }

    /**
     * @covers ::validate
     */
    public function testValidateRequestSelectedShippingOptionPriceAccessed(): void
    {
        $this->performBasePriceMocking();

        $this->request
            ->method('getOrderLines')
            ->willReturn([]);

        $this->request
            ->expects(static::exactly(2))
            ->method('getSelectedShippingOption')
            ->willReturn([
                'id' => 'id1',
                'price' => 123
            ]);

        $this->model->validate($this->request, $this->quote);
    }

    private function performBasePriceMocking()
    {
        $this->quote->expects(static::once())
            ->method('isVirtual')
            ->willReturn(false);
        $this->address
            ->expects(static::atMost(2))
            ->method('getBaseShippingAmount')
            ->willReturn('1');
        $this->address
            ->expects(static::once())
            ->method('getBaseShippingTaxAmount')
            ->willReturn('0.23');
        $this->dependencyMocks['dataConverter']
            ->method('toApiFloat')
            ->with(1.23)
            ->willReturn('123');
        $this->dependencyMocks['taxConfig']
            ->method('shippingPriceIncludesTax')
            ->willReturn(false);
    }

    protected function setUp(): void
    {
        $mockFactory           = new MockFactory($this);
        $objectFactory         = new TestObjectFactory($mockFactory);
        $this->model           = $objectFactory->create(ShippingAmount::class);
        $this->dependencyMocks = $objectFactory->getDependencyMocks();
        $this->request         = $mockFactory->create(DataObject::class, [], [
            'getOrderLines',
            'getSelectedShippingOption'
        ]);
        $this->quote           = $mockFactory->create(
            Quote::class,
            [
                'getShippingAddress',
                'isVirtual',
                'getReservedOrderId',
                'getStore'
            ]
        );

        $store = $mockFactory->create(Store::class);
        $this->quote->method('getStore')
            ->willReturn($store);

        $this->address = $mockFactory->create(Address::class, [], [
            'getBaseShippingInclTax',
            'getBaseShippingAmount',
            'getBaseShippingTaxAmount'
        ]);

        $this->quote->method('getShippingAddress')->willReturn($this->address);
        $this->quote->method('getReservedOrderId')->willReturn('AN-ORDER-ID');
    }
}
