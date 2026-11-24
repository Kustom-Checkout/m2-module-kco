<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Cart;

use Klarna\Kco\Model\Payment\Kco;
use Magento\Quote\Api\Data\CartInterface;

/**
 * @internal
 */
class Payment
{
    /**
     * Transient address flag: when set, the stored shipping rates are kept (no re-collection on import)
     */
    public const KEEP_SHIPPING_RATES_FLAG = 'kustom_keep_shipping_rates';

    /**
     * Add payment specific information to the quote
     *
     * @param CartInterface $quote
     */
    public function addToQuote(CartInterface $quote): void
    {
        if ($quote->isVirtual()) {
            $address = $quote->getBillingAddress();
        } else {
            $address = $quote->getShippingAddress();
            if (!$address->getData(self::KEEP_SHIPPING_RATES_FLAG)) {
                $address->setCollectShippingRates(true);
            }
        }

        $data = ['method' => Kco::METHOD_CODE];
        $payment = $quote->getPayment();

        $address->setPaymentMethod($data['method']);
        $payment->importData($data);
    }
}
