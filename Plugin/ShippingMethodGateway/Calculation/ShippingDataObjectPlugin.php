<?php
/**
 * Copyright 2025 Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);


namespace Klarna\Kco\Plugin\ShippingMethodGateway\Calculation;

use Klarna\Kco\Model\ShippingMethodGateway\GatewayResolver;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Tax\Api\Data\QuoteDetailsItemExtensionFactory;
use Magento\Tax\Api\Data\QuoteDetailsItemInterface;
use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;

/**
 * Attaching the active Klarna shipping gateway and its quote to the shipping tax item,
 * so the tax calculators can apply the gateway values without reading the checkout session
 *
 * @internal
 */
class ShippingDataObjectPlugin
{
    /**
     * @var GatewayResolver
     */
    private $gatewayResolver;
    /**
     * @var QuoteDetailsItemExtensionFactory
     */
    private $extensionFactory;

    /**
     * @param GatewayResolver                  $gatewayResolver
     * @param QuoteDetailsItemExtensionFactory $extensionFactory
     * @codeCoverageIgnore
     */
    public function __construct(
        GatewayResolver $gatewayResolver,
        QuoteDetailsItemExtensionFactory $extensionFactory
    ) {
        $this->gatewayResolver  = $gatewayResolver;
        $this->extensionFactory = $extensionFactory;
    }

    /**
     * Adding the Klarna shipping gateway information to the shipping item
     *
     * @param CommonTaxCollector               $subject
     * @param QuoteDetailsItemInterface|null   $result
     * @param ShippingAssignmentInterface      $shippingAssignment
     * @param Total                            $total
     * @param bool                             $useBaseCurrency
     * @return QuoteDetailsItemInterface|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetShippingDataObject(
        CommonTaxCollector $subject,
        $result,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total,
        $useBaseCurrency
    ) {
        if (!$result instanceof QuoteDetailsItemInterface) {
            return $result;
        }

        $quote = $shippingAssignment->getShipping()->getAddress()->getQuote();
        if ($quote === null) {
            return $result;
        }

        $gateway = $this->gatewayResolver->getActiveGateway($quote);
        if ($gateway === null) {
            return $result;
        }

        $extensionAttributes = $result->getExtensionAttributes() ?: $this->extensionFactory->create();
        $extensionAttributes->setKssShippingGateway($gateway);
        $extensionAttributes->setKssQuote($quote);
        $result->setExtensionAttributes($extensionAttributes);

        return $result;
    }
}
