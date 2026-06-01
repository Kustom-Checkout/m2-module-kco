<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

namespace Klarna\Kco\Test\Unit\Model\Checkout\Validations;

use Klarna\Kco\Model\Cart\Validations\ShippingMethod;
use Klarna\Base\Test\Unit\Mock\MockFactory;
use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Cart\Validations\ShippingMethod
 */
class ShippingMethodTest extends TestCase
{
    /**
     * @var MockObject|Address
     */
    private $address;
    /**
     * @var ShippingMethod
     */
    private $model;
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
    public function testValidateWithMatchingShippingMethods(): void
    {
        $this->quote->expects(static::once())
            ->method('isVirtual')
            ->willReturn(false);
        $this->address
            ->expects(static::once())
            ->method('getShippingMethod')
            ->willReturn('some-method');
        $this->request
            ->method('getOrderId')
            ->willReturn('3823342b-7f23-6580-975f-b448f942085c');
        $this->request
            ->method('getSelectedShippingOption')
            ->willReturn(['id' => 'some-method']);

        $this->model->validate($this->request, $this->quote);
    }

    /**
     * @covers ::validate
     */
    public function testValidateThrowsExceptionWithNotMatchingShippingMethods(): void
    {
        $this->expectException(\Klarna\Base\Exception::class);
        $this->expectExceptionMessage(
            "Shipping method does not match for order #AN-ORDER-ID. " .
            "Klarna method is some-other-method vs Magento method is some-method"
        );
        $this->quote->expects(static::once())
            ->method('isVirtual')
            ->willReturn(false);
        $this->address
            ->expects(static::once())
            ->method('getShippingMethod')
            ->willReturn('some-method');
        $this->request
            ->method('getOrderId')
            ->willReturn('3823342b-7f23-6580-975f-b448f942085c');
        $this->request
            ->expects(static::exactly(2))
            ->method('getSelectedShippingOption')
            ->willReturn(['id' => 'some-other-method']);

        $this->model->validate($this->request, $this->quote);
    }

    protected function setUp(): void
    {
        $mockFactory   = new MockFactory($this);
        $objectFactory = new TestObjectFactory('');
        $this->model   = $objectFactory->create(ShippingMethod::class);
        $this->request = $mockFactory->create(DataObject::class, [], [
            'getOrderId',
            'getSelectedShippingOption'
        ]);
        $this->quote   = $mockFactory->create(
            Quote::class,
            [
                'getShippingAddress',
                'isVirtual',
                'getReservedOrderId'
            ]
        );

        $this->address = $mockFactory->create(Address::class);

        $this->quote->method('getShippingAddress')->willReturn($this->address);
        $this->quote->method('getReservedOrderId')->willReturn('AN-ORDER-ID');
    }
}
