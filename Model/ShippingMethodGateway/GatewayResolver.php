<?php
/**
 * Copyright 2025 Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);


namespace Klarna\Kco\Model\ShippingMethodGateway;

use Klarna\Kco\Api\QuoteRepositoryInterface as KlarnaQuoteRepositoryInterface;
use Klarna\Kco\Model\Checkout\Configuration\SettingsProvider;
use Klarna\Kss\Api\ShippingMethodGatewayInterface;
use Klarna\Kss\Api\ShippingMethodGatewayRepositoryInterface;
use Klarna\Kss\Model\KssConfigProvider;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Resolving the active Klarna shipping gateway for a given quote without using the checkout session
 *
 * @internal
 */
class GatewayResolver
{
    /**
     * @var SettingsProvider
     */
    private $settingsProvider;
    /**
     * @var KssConfigProvider
     */
    private $kssConfigProvider;
    /**
     * @var KlarnaQuoteRepositoryInterface
     */
    private $klarnaQuoteRepository;
    /**
     * @var ShippingMethodGatewayRepositoryInterface
     */
    private $gatewayRepository;

    /**
     * @param SettingsProvider                         $settingsProvider
     * @param KssConfigProvider                        $kssConfigProvider
     * @param KlarnaQuoteRepositoryInterface           $klarnaQuoteRepository
     * @param ShippingMethodGatewayRepositoryInterface $gatewayRepository
     * @codeCoverageIgnore
     */
    public function __construct(
        SettingsProvider $settingsProvider,
        KssConfigProvider $kssConfigProvider,
        KlarnaQuoteRepositoryInterface $klarnaQuoteRepository,
        ShippingMethodGatewayRepositoryInterface $gatewayRepository
    ) {
        $this->settingsProvider      = $settingsProvider;
        $this->kssConfigProvider     = $kssConfigProvider;
        $this->klarnaQuoteRepository = $klarnaQuoteRepository;
        $this->gatewayRepository     = $gatewayRepository;
    }

    /**
     * Returns the active shipping gateway of the quote or null when KSS is not used for it
     *
     * @param CartInterface $quote
     * @return ShippingMethodGatewayInterface|null
     */
    public function getActiveGateway(CartInterface $quote): ?ShippingMethodGatewayInterface
    {
        if ($quote->getId() === null || $quote->isVirtual()) {
            return null;
        }

        $store = $quote->getStore();
        if (!$this->settingsProvider->isKlarnaCheckoutPaymentEnabled($store)
            || !$this->kssConfigProvider->isKssEnabled($store)
        ) {
            return null;
        }

        try {
            $klarnaQuote = $this->klarnaQuoteRepository->getActiveByQuote($quote);
        } catch (NoSuchEntityException $e) {
            return null;
        }

        $checkoutId = $klarnaQuote->getKlarnaCheckoutId();
        if (!$klarnaQuote->getIsActive() || $checkoutId === null) {
            return null;
        }

        try {
            $gateway = $this->gatewayRepository->loadShipping((string)$checkoutId, 'klarna_session_id');
        } catch (NoSuchEntityException $e) {
            return null;
        }

        return $gateway->isActive() ? $gateway : null;
    }
}
