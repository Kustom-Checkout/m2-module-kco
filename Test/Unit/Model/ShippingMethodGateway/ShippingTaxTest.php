<?php
/**
 * Copyright 2025 Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);


namespace Klarna\Kco\Test\Unit\Model\ShippingMethodGateway;

use Klarna\Kco\Model\ShippingMethodGateway\ShippingTax;
use Klarna\Kss\Api\ShippingMethodGatewayInterface;
use Klarna\Kss\Model\Assignment\Tax as KssTaxAssignment;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Tax\Api\Data\QuoteDetailsItemExtensionInterface;
use Magento\Tax\Api\Data\QuoteDetailsItemInterface;
use Magento\Tax\Api\Data\TaxDetailsItemInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\ShippingMethodGateway\ShippingTax
 */
class ShippingTaxTest extends TestCase
{
    /**
     * @var ShippingTax
     */
    private $model;
    /**
     * @var KssTaxAssignment|MockObject
     */
    private $assignment;
    /**
     * @var TaxDetailsItemInterface|MockObject
     */
    private $result;
    /**
     * @var QuoteDetailsItemInterface|MockObject
     */
    private $item;

    protected function setUp(): void
    {
        $this->assignment = $this->createMock(KssTaxAssignment::class);
        $this->result     = $this->createMock(TaxDetailsItemInterface::class);
        $this->item       = $this->createMock(QuoteDetailsItemInterface::class);
        $this->model      = new ShippingTax($this->assignment);
    }

    private function givenExtensionAttributes(?ShippingMethodGatewayInterface $gateway, ?CartInterface $quote): void
    {
        $extension = $this->createMock(QuoteDetailsItemExtensionInterface::class);
        $extension->method('getKssShippingGateway')->willReturn($gateway);
        $extension->method('getKssQuote')->willReturn($quote);
        $this->item->method('getExtensionAttributes')->willReturn($extension);
    }

    /**
     * @covers ::apply
     */
    public function testApplyReturnsResultWithoutExtensionAttributes(): void
    {
        $this->assignment->expects(static::never())->method('assignToTaxInstance');

        static::assertSame($this->result, $this->model->apply($this->result, $this->item));
    }

    /**
     * @covers ::apply
     */
    public function testApplyReturnsResultWithoutGateway(): void
    {
        $this->givenExtensionAttributes(null, $this->createMock(CartInterface::class));
        $this->assignment->expects(static::never())->method('assignToTaxInstance');

        static::assertSame($this->result, $this->model->apply($this->result, $this->item));
    }

    /**
     * @covers ::apply
     */
    public function testApplyReturnsResultWhenValuesCannotBeUpdated(): void
    {
        $this->givenExtensionAttributes(
            $this->createMock(ShippingMethodGatewayInterface::class),
            $this->createMock(CartInterface::class)
        );
        $this->assignment->method('canUpdateValues')->willReturn(false);
        $this->assignment->expects(static::never())->method('assignToTaxInstance');

        static::assertSame($this->result, $this->model->apply($this->result, $this->item));
    }

    /**
     * @covers ::apply
     */
    public function testApplyAssignsGatewayValues(): void
    {
        $gateway = $this->createMock(ShippingMethodGatewayInterface::class);
        $quote   = $this->createMock(CartInterface::class);
        $updated = $this->createMock(TaxDetailsItemInterface::class);
        $this->givenExtensionAttributes($gateway, $quote);
        $this->assignment->method('canUpdateValues')->with($this->result, $quote)->willReturn(true);
        $this->assignment->expects(static::once())
            ->method('assignToTaxInstance')
            ->with($this->result, $gateway, $quote)
            ->willReturn($updated);

        static::assertSame($updated, $this->model->apply($this->result, $this->item));
    }
}
