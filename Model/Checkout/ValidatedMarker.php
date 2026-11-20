<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Checkout;

use Klarna\Kco\Model\CacheQuoteRepository;
use Klarna\Kco\Model\ResourceModel\Quote as QuoteResource;

/**
 * KUSTOM-128: the only writer of klarna_kco_quote.validated_at (single-column UPDATE)
 *
 * Whole-row saves of the Kustom quote never write the column, so a stale copy can neither resurrect
 * nor erase the marker.
 *
 * @internal
 */
class ValidatedMarker
{
    /**
     * @var CacheQuoteRepository
     */
    private CacheQuoteRepository $quoteRepository;
    /**
     * @var QuoteResource
     */
    private QuoteResource $resource;

    /**
     * @param CacheQuoteRepository $quoteRepository
     * @param QuoteResource $resource
     */
    public function __construct(CacheQuoteRepository $quoteRepository, QuoteResource $resource)
    {
        $this->quoteRepository = $quoteRepository;
        $this->resource = $resource;
    }

    /**
     * Set (UTC Y-m-d H:i:s) or clear (null) the marker of the Kustom quote with the given checkout id
     *
     * @param string $checkoutId
     * @param string|null $value
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function update(string $checkoutId, ?string $value): void
    {
        $quote = $this->quoteRepository->getByCheckoutId($checkoutId);
        $this->resource->updateValidatedAt($quote->getId(), $value);
        // keep the in-memory copy in sync, then drop it from the request cache
        $quote->setData(QuoteResource::COLUMN_VALIDATED_AT, $value);
        $this->quoteRepository->invalidate($checkoutId);
    }
}
