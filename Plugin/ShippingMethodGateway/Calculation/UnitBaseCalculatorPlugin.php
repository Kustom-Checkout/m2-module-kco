<?php
/**
 * Copyright 2025 Kustom AB (Originally developed by Klarna Bank AB)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Plugin\ShippingMethodGateway\Calculation;

use Klarna\Kco\Model\Tax;
use Magento\Tax\Api\Data\QuoteDetailsItemInterface;
use Magento\Tax\Api\Data\TaxDetailsItemInterface;
use Magento\Tax\Model\Calculation\UnitBaseCalculator;

/**
 * Recalculating/setting the shipping values when the calculation is based
 * on the unit base when we use a shipping gateway api
 *
 * @internal
 */
class UnitBaseCalculatorPlugin
{
    /**
     * @var Tax
     */
    private $tax;

    /**
     * @param Tax $tax
     * @codeCoverageIgnore
     */
    public function __construct(Tax $tax)
    {
        $this->tax = $tax;
    }

    /**
     * Recalculating/setting the shipping and tax values
     *
     * @param UnitBaseCalculator $subject
     * @param TaxDetailsItemInterface $result
     * @param QuoteDetailsItemInterface $item
     * @param float|int|string $quantity
     * @param bool $round
     * @return TaxDetailsItemInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @codeCoverageIgnore
     */
    public function afterCalculate(
        UnitBaseCalculator $subject,
        TaxDetailsItemInterface $result,
        QuoteDetailsItemInterface $item,
        $quantity,
        $round = true
    ): TaxDetailsItemInterface {
        return $this->tax->updateMagentoTax($result);
    }
}
