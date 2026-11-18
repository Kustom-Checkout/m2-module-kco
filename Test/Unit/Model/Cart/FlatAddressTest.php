<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Cart;

use Klarna\Kco\Model\Cart\FlatAddress;
use Magento\Directory\Model\Region;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Cart\FlatAddress
 */
class FlatAddressTest extends TestCase
{
    /**
     * @covers ::getBillingAddress
     */
    public function testBillingAddressWithoutRegionIdDoesNotLoadRegionModel(): void
    {
        $address = $this->createMock(Address::class);
        $address->method('getRegionId')->willReturn(null);
        $address->expects($this->never())->method('getRegionModel');

        $result = (new FlatAddress())->getBillingAddress($address);

        static::assertNull($result['region']);
        static::assertNull($result['regionId']);
    }

    /**
     * @covers ::getFullShippingAddress
     */
    public function testShippingAddressWithoutRegionIdDoesNotLoadRegionModel(): void
    {
        $address = $this->createMock(Address::class);
        $address->method('getRegionId')->willReturn(null);
        $address->expects($this->never())->method('getRegionModel');

        $result = (new FlatAddress())->getFullShippingAddress($address);

        static::assertNull($result['region']);
    }

    /**
     * @covers ::getBillingAddress
     * @covers ::getFullShippingAddress
     */
    public function testRegionModelIsReturnedWhenRegionIdExists(): void
    {
        $region = $this->createMock(Region::class);
        $address = $this->createMock(Address::class);
        $address->method('getRegionId')->willReturn('5');
        $address->expects($this->exactly(2))->method('getRegionModel')->with(5)->willReturn($region);

        $flatAddress = new FlatAddress();

        static::assertSame($region, $flatAddress->getBillingAddress($address)['region']);
        static::assertSame($region, $flatAddress->getFullShippingAddress($address)['region']);
    }
}
