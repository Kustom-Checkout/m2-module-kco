<?php
/**
 * Copyright 2025 Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);


namespace Klarna\Kco\Model\ShippingMethodGateway;

use Klarna\Kss\Model\Assignment\Tax as KssTaxAssignment;
use Magento\Tax\Api\Data\QuoteDetailsItemInterface;
use Magento\Tax\Api\Data\TaxDetailsItemInterface;

/**
 * Applying the Klarna shipping gateway tax values using only the calculated item data
 *
 * @internal
 */
class ShippingTax
{
    /**
     * @var KssTaxAssignment
     */
    private $assignment;

    /**
     * @param KssTaxAssignment $assignment
     * @codeCoverageIgnore
     */
    public function __construct(KssTaxAssignment $assignment)
    {
        $this->assignment = $assignment;
    }

    /**
     * Updating the tax result when the item carries an active Klarna shipping gateway
     *
     * @param TaxDetailsItemInterface   $result
     * @param QuoteDetailsItemInterface $item
     * @return TaxDetailsItemInterface
     */
    public function apply(TaxDetailsItemInterface $result, QuoteDetailsItemInterface $item): TaxDetailsItemInterface
    {
        $extensionAttributes = $item->getExtensionAttributes();
        if ($extensionAttributes === null) {
            return $result;
        }

        $gateway = $extensionAttributes->getKssShippingGateway();
        $quote   = $extensionAttributes->getKssQuote();
        if ($gateway === null || $quote === null || !$this->assignment->canUpdateValues($result, $quote)) {
            return $result;
        }

        return $this->assignment->assignToTaxInstance($result, $gateway, $quote);
    }
}
