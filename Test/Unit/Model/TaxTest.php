<?php
/**
 * Copyright 2025 Kustom AB (Originally developed by Klarna Bank AB)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model;

use Klarna\Kco\Model\Checkout\Configuration\SettingsProvider;
use Klarna\Kco\Model\Checkout\Kco\Session;
use Klarna\Kco\Model\Tax;
use Klarna\Kss\Model\ShippingMethodGateway;
use Klarna\Kss\Model\Assignment\Tax as KssTaxAssignment;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Api\Data\TaxDetailsItemInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Tax
 */
class TaxTest extends TestCase
{
    /**
     * @var Tax
     */
    private $model;
    /**
     * @var KssTaxAssignment|MockObject
     */
    private $assignment;
    /**
     * @var Session|MockObject
     */
    private $session;
    /**
     * @var SettingsProvider|MockObject
     */
    private $config;
    /**
     * @var TaxDetailsItemInterface|MockObject
     */
    private $item;

    protected function setUp(): void
    {
        $this->assignment = $this->createMock(KssTaxAssignment::class);
        $this->session    = $this->createMock(Session::class);
        $this->config     = $this->createMock(SettingsProvider::class);
        $this->item       = $this->createMock(TaxDetailsItemInterface::class);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($this->createMock(StoreInterface::class));

        $this->model = new Tax($this->assignment, $this->session, $this->config, $storeManager);
    }

    /**
     * @covers ::updateMagentoTax
     */
    public function testUpdateMagentoTaxSkipsSessionWhenKcoDisabled(): void
    {
        $this->config->method('isKcoEnabled')->willReturn(false);
        $this->session->expects(static::never())->method('hasActiveKlarnaShippingGatewayInformation');
        $this->session->expects(static::never())->method('getQuote');

        static::assertSame($this->item, $this->model->updateMagentoTax($this->item));
    }

    /**
     * @covers ::updateMagentoTax
     */
    public function testUpdateMagentoTaxDoesNotRecurseOnNestedCall(): void
    {
        $this->config->method('isKcoEnabled')->willReturn(true);
        $nestedResult = null;
        $this->session->expects(static::once())
            ->method('hasActiveKlarnaShippingGatewayInformation')
            ->willReturnCallback(function () use (&$nestedResult) {
                // Simulates quote load -> collectTotals -> tax calculator -> plugin
                $nestedResult = $this->model->updateMagentoTax($this->item);
                return false;
            });

        static::assertSame($this->item, $this->model->updateMagentoTax($this->item));
        static::assertSame($this->item, $nestedResult);
    }

    /**
     * @covers ::updateMagentoTax
     */
    public function testUpdateMagentoTaxResetsGuardAfterException(): void
    {
        $this->config->method('isKcoEnabled')->willReturn(true);
        $this->session->expects(static::exactly(2))
            ->method('hasActiveKlarnaShippingGatewayInformation')
            ->willReturnOnConsecutiveCalls(static::throwException(new \RuntimeException()), false);

        try {
            $this->model->updateMagentoTax($this->item);
        } catch (\RuntimeException $e) {
            // expected
        }
        static::assertSame($this->item, $this->model->updateMagentoTax($this->item));
    }

    /**
     * @covers ::updateMagentoTax
     */
    public function testUpdateMagentoTaxAssignsKssValues(): void
    {
        $updated = $this->createMock(TaxDetailsItemInterface::class);
        $this->config->method('isKcoEnabled')->willReturn(true);
        $this->session->method('hasActiveKlarnaShippingGatewayInformation')->willReturn(true);
        $this->session->method('getQuote')->willReturn($this->createMock(CartInterface::class));
        $this->session->method('getKlarnaShippingGateway')
            ->willReturn($this->createMock(ShippingMethodGateway::class));
        $this->assignment->method('canUpdateValues')->willReturn(true);
        $this->assignment->expects(static::once())->method('assignToTaxInstance')->willReturn($updated);

        static::assertSame($updated, $this->model->updateMagentoTax($this->item));
    }
}
