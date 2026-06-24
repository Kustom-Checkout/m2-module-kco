<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Cart\Validations;

use Klarna\Base\Exception as KlarnaException;
use Klarna\Base\Helper\DataConverter;
use Klarna\Orderlines\Model\Calculator\GiftWrap;
use Klarna\Kco\Api\CheckoutValidationInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Validating the order total between the Klarna order and the shop quote
 *
 * @internal
 */
class OrderTotal implements CheckoutValidationInterface
{
    /**
     * @var GiftWrap
     */
    private GiftWrap $giftWrap;
    /**
     * @var DataConverter
     */
    private DataConverter $dataConverter;

    /**
     * @param GiftWrap      $giftWrapping
     * @param DataConverter $dataConverter
     * @codeCoverageIgnore
     */
    public function __construct(GiftWrap $giftWrapping, DataConverter $dataConverter)
    {
        $this->giftWrap      = $giftWrapping;
        $this->dataConverter = $dataConverter;
    }

    /**
     * @inheritDoc
     */
    public function validate(DataObject $request, CartInterface $quote): void
    {
        $klarnaTotal = $this->getKlarnaTotal($request);
        $quoteTotal  = $this->getQuoteTotal($request, $quote);

        if ($klarnaTotal !== $quoteTotal) {
            $exceptionMessage = __(
                'Order total does not match for order #%1. Kustom total is %2 vs Magento total %3',
                $quote->getReservedOrderId(),
                $klarnaTotal,
                $quoteTotal
            );
            throw new KlarnaException($exceptionMessage);
        }
    }

    /**
     * Get cart total from quote (updates total with gift-wrapping tax amounts).
     *
     * This method is public so that this functionality can be extended via a plugin from other developers.
     *
     * @param DataObject    $request
     * @param CartInterface $quote
     * @return float|int
     */
    public function getQuoteTotal(DataObject $request, CartInterface $quote)
    {
        $quoteTotal  = (int)$this->dataConverter->toApiFloat($quote->getGrandTotal());
        $quoteTotal += $this->giftWrap->getAdditionalGwTax($request, $quote);

        return $quoteTotal;
    }

    /**
     * Get cart total from Klarna request.
     *
     * This method is public so that this functionality can be extended via a plugin from other developers.
     *
     * @param DataObject $request
     * @return int
     */
    public function getKlarnaTotal(DataObject $request): int
    {
        return (int)($request->getOrderAmount() ?: $request->getData('cart/total_price_including_tax'));
    }
}
