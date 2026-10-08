<?php
/**
 * Copyright 2025 Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);


namespace Klarna\Kco\Test\Unit\Plugin\ShippingMethodGateway\Calculation;

use Klarna\Kco\Model\ShippingMethodGateway\GatewayResolver;
use Klarna\Kco\Plugin\ShippingMethodGateway\Calculation\ShippingDataObjectPlugin;
use Klarna\Kss\Api\ShippingMethodGatewayInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Tax\Api\Data\QuoteDetailsItemExtensionFactory;
use Magento\Tax\Api\Data\QuoteDetailsItemExtensionInterface;
use Magento\Tax\Api\Data\QuoteDetailsItemInterface;
use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Plugin\ShippingMethodGateway\Calculation\ShippingDataObjectPlugin
 */
class ShippingDataObjectPluginTest extends TestCase
{
    /**
     * @var ShippingDataObjectPlugin
     */
    private $plugin;
    /**
     * @var GatewayResolver|MockObject
     */
    private $gatewayResolver;
    /**
     * @var QuoteDetailsItemExtensionFactory|MockObject
     */
    private $extensionFactory;
    /**
     * @var CommonTaxCollector|MockObject
     */
    private $subject;
    /**
     * @var ShippingAssignmentInterface|MockObject
     */
    private $shippingAssignment;
    /**
     * @var Total|MockObject
     */
    private $total;
    /**
     * @var Quote|MockObject
     */
    private $quote;

    protected function setUp(): void
    {
        $this->gatewayResolver  = $this->createMock(GatewayResolver::class);
        $this->extensionFactory = $this->createMock(QuoteDetailsItemExtensionFactory::class);
        $this->subject          = $this->createMock(CommonTaxCollector::class);
        $this->total            = $this->createMock(Total::class);
        $this->quote            = $this->createMock(Quote::class);

        $address = $this->createMock(Address::class);
        $address->method('getQuote')->willReturn($this->quote);
        $shipping = $this->createMock(ShippingInterface::class);
        $shipping->method('getAddress')->willReturn($address);
        $this->shippingAssignment = $this->createMock(ShippingAssignmentInterface::class);
        $this->shippingAssignment->method('getShipping')->willReturn($shipping);

        $this->plugin = new ShippingDataObjectPlugin($this->gatewayResolver, $this->extensionFactory);
    }

    /**
     * @covers ::afterGetShippingDataObject
     */
    public function testReturnsNullResultUntouched(): void
    {
        $this->gatewayResolver->expects(static::never())->method('getActiveGateway');

        static::assertNull(
            $this->plugin->afterGetShippingDataObject($this->subject, null, $this->shippingAssignment, $this->total, false)
        );
    }

    /**
     * @covers ::afterGetShippingDataObject
     */
    public function testDoesNotTouchItemWithoutActiveGateway(): void
    {
        $item = $this->createMock(QuoteDetailsItemInterface::class);
        $this->gatewayResolver->method('getActiveGateway')->with($this->quote)->willReturn(null);
        $item->expects(static::never())->method('setExtensionAttributes');

        static::assertSame(
            $item,
            $this->plugin->afterGetShippingDataObject($this->subject, $item, $this->shippingAssignment, $this->total, false)
        );
    }

    /**
     * @covers ::afterGetShippingDataObject
     */
    public function testAttachesGatewayAndQuoteFromShippingAssignment(): void
    {
        $item      = $this->createMock(QuoteDetailsItemInterface::class);
        $gateway   = $this->createMock(ShippingMethodGatewayInterface::class);
        $extension = $this->createExtensionAttributes();
        $this->gatewayResolver->method('getActiveGateway')->with($this->quote)->willReturn($gateway);
        $item->method('getExtensionAttributes')->willReturn(null);
        $this->extensionFactory->method('create')->willReturn($extension);

        $item->expects(static::once())->method('setExtensionAttributes')->with($extension);

        static::assertSame(
            $item,
            $this->plugin->afterGetShippingDataObject($this->subject, $item, $this->shippingAssignment, $this->total, true)
        );
        static::assertSame($gateway, $extension->getKssShippingGateway());
        static::assertSame($this->quote, $extension->getKssQuote());
    }

    /**
     * Extension attributes stub. Unit tests run without setup:di:compile, so the generated interface can be
     * empty there and its methods cannot be mocked; this stub works with both the empty and generated interface.
     *
     * @return QuoteDetailsItemExtensionInterface
     */
    private function createExtensionAttributes(): QuoteDetailsItemExtensionInterface
    {
        return new class implements QuoteDetailsItemExtensionInterface {
            /**
             * @var mixed
             */
            private $priceForTaxCalculation;
            /**
             * @var ShippingMethodGatewayInterface|null
             */
            private $kssShippingGateway;
            /**
             * @var CartInterface|null
             */
            private $kssQuote;

            public function getPriceForTaxCalculation()
            {
                return $this->priceForTaxCalculation;
            }

            public function setPriceForTaxCalculation($priceForTaxCalculation)
            {
                $this->priceForTaxCalculation = $priceForTaxCalculation;
                return $this;
            }

            public function getKssShippingGateway()
            {
                return $this->kssShippingGateway;
            }

            public function setKssShippingGateway(ShippingMethodGatewayInterface $kssShippingGateway)
            {
                $this->kssShippingGateway = $kssShippingGateway;
                return $this;
            }

            public function getKssQuote()
            {
                return $this->kssQuote;
            }

            public function setKssQuote(CartInterface $kssQuote)
            {
                $this->kssQuote = $kssQuote;
                return $this;
            }
        };
    }
}
