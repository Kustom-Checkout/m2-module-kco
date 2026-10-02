<?php
/**
 * Copyright 2025 Kustom AB (Originally developed by Klarna Bank AB)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model;

use Klarna\Kco\Model\Checkout\Configuration\SettingsProvider;
use Klarna\Kco\Model\Checkout\Kco\Session;
use Klarna\Kss\Model\Assignment\Tax as KssTaxAssignment;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Api\Data\TaxDetailsItemInterface;

/**
 * Performing checks and updates on the Magento Tax values
 *
 * @api
 */
class Tax
{
    /**
     * @var KssTaxAssignment
     */
    private $assignment;
    /**
     * @var Session
     */
    private $session;
    /**
     * @var SettingsProvider
     */
    private $config;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * Re-entrancy guard: loading the quote can trigger a nested totals collection
     * (e.g. Magento_NegotiableQuote plugins), which would call this method again.
     *
     * @var bool
     */
    private $isProcessing = false;

    /**
     * @param KssTaxAssignment      $assignment
     * @param Session               $session
     * @param SettingsProvider      $config
     * @param StoreManagerInterface $storeManager
     * @codeCoverageIgnore
     */
    public function __construct(
        KssTaxAssignment $assignment,
        Session $session,
        SettingsProvider $config,
        StoreManagerInterface $storeManager
    ) {
        $this->assignment   = $assignment;
        $this->session      = $session;
        $this->config       = $config;
        $this->storeManager = $storeManager;
    }

    /**
     * Checking if KSS is used and if the Magento tax values can be adjusted.
     *
     * If that is the case these values will be updated.
     *
     * @param TaxDetailsItemInterface $taxDetailsItem
     * @return TaxDetailsItemInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function updateMagentoTax(TaxDetailsItemInterface $taxDetailsItem): TaxDetailsItemInterface
    {
        if ($this->isProcessing || !$this->config->isKcoEnabled($this->storeManager->getStore())) {
            return $taxDetailsItem;
        }

        $this->isProcessing = true;
        try {
            if (!$this->session->hasActiveKlarnaShippingGatewayInformation()) {
                return $taxDetailsItem;
            }

            $quote = $this->session->getQuote();
            if ($quote !== null && $this->assignment->canUpdateValues($taxDetailsItem, $quote)) {
                return $this->assignment->assignToTaxInstance(
                    $taxDetailsItem,
                    $this->session->getKlarnaShippingGateway(),
                    $quote
                );
            }

            return $taxDetailsItem;
        } finally {
            $this->isProcessing = false;
        }
    }
}
