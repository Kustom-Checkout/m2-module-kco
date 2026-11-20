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
     * Add payment specific information to the quote
     *
     * @param CartInterface $quote
     * @param bool $collectShippingRates False keeps the stored shipping rates (no re-collection on import)
     */
    public function addToQuote(CartInterface $quote, bool $collectShippingRates = true): void
    {
        if ($quote->isVirtual()) {
            $address = $quote->getBillingAddress();
        } else {
            $address = $quote->getShippingAddress();
            if ($collectShippingRates) {
                $address->setCollectShippingRates(true);
            }
        }

        $data = ['method' => Kco::METHOD_CODE];
        $payment = $quote->getPayment();

        $address->setPaymentMethod($data['method']);
        $payment->importData($data);
    }
}
