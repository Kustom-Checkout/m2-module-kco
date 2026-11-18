<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Api;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Decides whether the Kustom checkout for a quote must be created as a recurring (subscription) checkout.
 *
 * Implementations must be cheap, deterministic and idempotent: the result is requested on every checkout create,
 * update and totals refresh, and a changed result for the same quote recreates the Kustom order.
 *
 * @api
 */
interface RecurringResolverInterface
{
    /**
     * Returns true if the given quote is a recurring purchase
     *
     * @param CartInterface $quote
     * @return bool
     */
    public function isRecurring(CartInterface $quote): bool;
}
