<?php
/**
 * Copyright 2025 Kustom AB (Originally developed by Klarna Bank AB)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Plugin\ShippingMethodGateway\Calculation;

use Klarna\Kco\Model\ShippingMethodGateway\ShippingTax;
use Magento\Tax\Api\Data\QuoteDetailsItemInterface;
use Magento\Tax\Api\Data\TaxDetailsItemInterface;
use Magento\Tax\Model\Calculation\TotalBaseCalculator;

/**
 * Recalculating/setting the shipping values when the calculation is based
 * on the total base when we use a shipping gateway api
 *
 * @internal
 */
class TotalBaseCalculatorPlugin
{
    /**
     * @var ShippingTax
     */
    private $shippingTax;

    /**
     * @param ShippingTax $shippingTax
     * @codeCoverageIgnore
     */
    public function __construct(ShippingTax $shippingTax)
    {
        $this->shippingTax = $shippingTax;
    }

    /**
     * Recalculating/setting the shipping and tax values
     *
     * @param TotalBaseCalculator $subject
     * @param TaxDetailsItemInterface $result
     * @param QuoteDetailsItemInterface $item
     * @param float|int|string $quantity
     * @param bool $round
     * @return TaxDetailsItemInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @codeCoverageIgnore
     */
    public function afterCalculate(
        TotalBaseCalculator $subject,
        TaxDetailsItemInterface $result,
        QuoteDetailsItemInterface $item,
        $quantity,
        $round = true
    ): TaxDetailsItemInterface {
        return $this->shippingTax->apply($result, $item);
    }
}
