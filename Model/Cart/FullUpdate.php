<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Cart;

use Klarna\Base\Model\Quote\Address\Handler;
use Klarna\Base\Model\Quote\Customer;
use Klarna\Kco\Model\Checkout\Company\Update;
use Klarna\Base\Model\Quote\ShippingMethod\SelectionAssurance;
use Magento\Quote\Model\QuoteRepository;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\DataObject;

/**
 * @internal
 */
class FullUpdate
{
    /**
     * Kustom order statuses (lowercase) after which the order is read-only, same set as in Order::validate
     */
    private const COMPLETED_STATUSES = ['checkout_complete', 'created'];

    /**
     * @var Customer
     */
    private Customer $customer;
    /**
     * @var Handler
     */
    private Handler $handler;
    /**
     * @var SelectionAssurance
     */
    private SelectionAssurance $selectionAssurance;
    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;
    /**
     * @var Update
     */
    private Update $companyIdUpdate;
    /**
     * @var Payment
     */
    private Payment $payment;

    /**
     * @param Customer $customer
     * @param Handler $handler
     * @param SelectionAssurance $selectionAssurance
     * @param CartRepositoryInterface $cartRepository
     * @param Update $companyIdUpdate
     * @param Payment $payment
     * @codeCoverageIgnore
     */
    public function __construct(
        Customer $customer,
        Handler $handler,
        SelectionAssurance $selectionAssurance,
        CartRepositoryInterface $cartRepository,
        Update $companyIdUpdate,
        Payment $payment
    ) {
        $this->customer = $customer;
        $this->handler = $handler;
        $this->selectionAssurance = $selectionAssurance;
        $this->cartRepository = $cartRepository;
        $this->companyIdUpdate = $companyIdUpdate;
        $this->payment = $payment;
    }

    /**
     * Updating the whole cart based on the klarna request object
     *
     * @param DataObject $klarnaRequest
     * @param string $klarnaId Used for hooks
     * @param CartInterface $quote
     * @return CartInterface
     * @throws \Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function updateByKlarnaRequestObject(
        DataObject $klarnaRequest,
        string $klarnaId,
        CartInterface $quote
    ): CartInterface {
        if (count($klarnaRequest->getBillingAddress()) === 1 || count($klarnaRequest->getShippingAddress()) === 1) {
            return $quote;
        }

        // Decided before addToQuote: importing the payment collects totals, which would delete the stored rates.
        $reuseStoredRate = $this->isKustomOrderCompleted($klarnaRequest)
            && $this->hasStoredRateForSelectedMethod($quote);
        if ($reuseStoredRate) {
            $quote->getShippingAddress()->setCollectShippingRates(false);
        }
        $this->addPaymentToQuote($quote, $reuseStoredRate);
        $this->companyIdUpdate->updateCustomerBusinessIdFromRequest($klarnaRequest, $quote);
        $this->handler->setBillingAddressDataFromRequest($klarnaRequest, $quote);
        $this->handler->setShippingAddressDataFromRequest($klarnaRequest, $quote);
        $this->customer->setCustomerDataFromRequest($klarnaRequest, $quote);

        if ($reuseStoredRate) {
            // The order was already validated and paid: reuse the rate stored at validate, don't ask carriers again.
            $quote->getShippingAddress()->setCollectShippingRates(false);
            $this->selectionAssurance->ensureShippingMethodSelected($quote);
        } else {
            $this->selectionAssurance->ensureShippingMethodSelectedWithPreCollect($quote);
        }

        if (!$quote->getReservedOrderId()) {
            $quote->reserveOrderId();
        }

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        if ($reuseStoredRate) {
            // Saving the quote runs core ShippingAddressManagement::assign(), which persists
            // collect_shipping_rates = 1; placing the order would then re-collect live rates. Reset it in the DB.
            $address = $quote->getShippingAddress();
            $address->setCollectShippingRates(false);
            $address->save();
        }

        return $quote;
    }

    /**
     * Returns true if Kustom reports the order as completed (compared case-insensitively)
     *
     * @param DataObject $klarnaRequest
     * @return bool
     */
    private function isKustomOrderCompleted(DataObject $klarnaRequest): bool
    {
        $status = $klarnaRequest->getStatus();
        if (!is_string($status)) {
            return false;
        }

        return in_array(strtolower($status), self::COMPLETED_STATUSES, true);
    }

    /**
     * Checks whether a stored rate exists for the selected shipping method
     *
     * True if the quote needs shipping and the shipping address holds a non-deleted stored rate
     * for its selected shipping method.
     *
     * @param CartInterface $quote
     * @return bool
     */
    private function hasStoredRateForSelectedMethod(CartInterface $quote): bool
    {
        if ($quote->isVirtual()) {
            return false;
        }

        $address = $quote->getShippingAddress();
        $method = $address->getShippingMethod();
        if (!$method) {
            return false;
        }

        $rate = $address->getShippingRateByCode($method);

        return $rate && !$rate->isDeleted();
    }

    /**
     * Imports the payment; a transient address flag keeps the stored rates while reusing them
     *
     * @param CartInterface $quote
     * @param bool $keepShippingRates
     */
    private function addPaymentToQuote(CartInterface $quote, bool $keepShippingRates): void
    {
        $address = $quote->getShippingAddress();
        if ($keepShippingRates) {
            $address->setData(Payment::KEEP_SHIPPING_RATES_FLAG, true);
        }
        try {
            $this->payment->addToQuote($quote);
        } finally {
            if ($keepShippingRates) {
                $address->unsetData(Payment::KEEP_SHIPPING_RATES_FLAG);
            }
        }
    }
}
