<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model;

use Klarna\Kco\Api\QuoteInterface;
use Klarna\Kco\Api\QuoteRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface as MageQuoteInterface;

/**
 * @internal
 */
class CacheQuoteRepository implements QuoteRepositoryInterface
{
    /**
     * @var QuoteRepositoryInterface
     */
    private QuoteRepositoryInterface $quoteRepository;
    /**
     * @var array
     */
    private array $instancesByKlarnaCheckoutId = [];

    /**
     * @param QuoteRepositoryInterface $quoteRepository
     * @codeCoverageIgnore
     */
    public function __construct(
        QuoteRepositoryInterface $quoteRepository
    ) {
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @inheritdoc
     */
    public function getByCheckoutId($checkoutId, $forceReload = false): QuoteInterface
    {
        if (isset($this->instancesByKlarnaCheckoutId[$checkoutId])) {
            return $this->instancesByKlarnaCheckoutId[$checkoutId];
        }

        $quote = $this->quoteRepository->getByCheckoutId($checkoutId, $forceReload);
        $this->instancesByKlarnaCheckoutId[$checkoutId] = $quote;

        return $quote;
    }

    /**
     * @inheritdoc
     */
    public function save(QuoteInterface $quote)
    {
        $result = $this->quoteRepository->save($quote);
        // Never serve a stale instance (e.g. validated_at) after a save
        unset($this->instancesByKlarnaCheckoutId[$quote->getKlarnaCheckoutId()]);

        return $result;
    }

    /**
     * Drop the cached instance of a checkout id
     *
     * @param string $checkoutId
     * @return void
     */
    public function invalidate(string $checkoutId): void
    {
        unset($this->instancesByKlarnaCheckoutId[$checkoutId]);
    }

    /**
     * @inheritdoc
     */
    public function getActiveByQuote(MageQuoteInterface $mageQuote): QuoteInterface
    {
        return $this->quoteRepository->getActiveByQuote($mageQuote);
    }
}
