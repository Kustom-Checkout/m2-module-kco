<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Gateway\Handler;

use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Magento\Payment\Model\InfoInterface;

use function str_replace;

/**
 * @internal
 */
class TitleHandler implements ValueHandlerInterface
{
    public const DEFAULT_TITLE  = 'Kustom Checkout';

    /**
     * Retrieve method configured value
     *
     * @param array    $subject
     * @param int|null $storeId
     *
     * @return mixed
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function handle(array $subject, $storeId = null)
    {
        if (!isset($subject['payment'])) {
            return self::DEFAULT_TITLE;
        }
        /** @var InfoInterface $payment */
        $payment = $subject['payment']->getPayment();
        $title = $this->getTitle($payment);
        return $title;
    }

    /**
     * Get title for specified payment method
     *
     * @param InfoInterface $payment
     * @return string
     */
    public function getTitle($payment)
    {
        if ($payment->hasAdditionalInformation('method_title')) {
            $methodTitle = (string) $payment->getAdditionalInformation('method_title');
            $methodTitle = str_replace('Klarna', 'Kustom', $methodTitle);

            return self::DEFAULT_TITLE . " - " . $methodTitle;
        }

        return self::DEFAULT_TITLE;
    }
}
