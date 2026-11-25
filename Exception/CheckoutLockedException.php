<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Exception;

use Klarna\Base\Exception;
use Magento\Framework\Phrase;

/**
 * Thrown when the quote must not be changed because the payment is being processed or the order was created
 *
 * @internal
 */
class CheckoutLockedException extends Exception
{
    /**
     * @param Phrase|null $phrase
     * @param \Exception|null $cause
     * @param int $code
     */
    public function __construct(?Phrase $phrase = null, ?\Exception $cause = null, $code = 0)
    {
        parent::__construct(
            $phrase ?: __(
                'Your payment is being processed, so the order can\'t be changed right now. '
                . 'Please wait a moment and reload the page.'
            ),
            $cause,
            $code
        );
    }
}
