<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Plugin\Checkout\Observer;

use Klarna\Kco\Model\Checkout\Configuration\SettingsProvider;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Quote\Observer\SubmitObserver;

/**
 * Plugin for Submit Observer to force disable it if KCO is enabled
 *
 * @internal
 */
class SubmitObserverPlugin
{

    /**
     * @var SettingsProvider
     */
    private $config;

    /**
     * SubmitObserverPlugin constructor.
     *
     * @param SettingsProvider $config
     * @codeCoverageIgnore
     */
    public function __construct(SettingsProvider $config)
    {
        $this->config = $config;
    }

    /**
     * Wrap SubmitObserver execute method to disable it when KCO is active and enabled
     *
     * @param SubmitObserver $subject
     * @param callable       $proceed
     * @param EventObserver  $observer
     * @return bool
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        SubmitObserver $subject,
        callable $proceed,
        EventObserver $observer
    ) {
        /** @var  \Magento\Quote\Model\Quote $quote */
        $quote = $observer->getEvent()->getQuote();

        if ($this->config->isKcoEnabled($quote->getStore())) {
            return true;
        }
        return $proceed($observer);
    }
}
