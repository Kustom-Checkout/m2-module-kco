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
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * @internal
 */
class Quote extends AbstractModel implements QuoteInterface, IdentityInterface
{
    public const CACHE_TAG = 'klarna_kco_quote';

    /**
     * Get Identities
     *
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * @inheritdoc
     */
    public function setIsActive($active)
    {
        $this->setData('is_active', $active);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getIsActive()
    {
        return $this->_getData('is_active');
    }

    /**
     * @inheritdoc
     */
    public function setKlarnaCheckoutId($checkoutId)
    {
        $this->setData('klarna_checkout_id', $checkoutId);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getKlarnaCheckoutId()
    {
        return $this->_getData('klarna_checkout_id');
    }

    /**
     * @inheritdoc
     */
    public function getQuoteId()
    {
        return $this->_getData('quote_id');
    }

    /**
     * @inheritdoc
     */
    public function setQuoteId($quoteId)
    {
        $this->setData('quote_id', $quoteId);
        return $this;
    }

    /**
     * Set the time (UTC, Y-m-d H:i:s) of the last successful validate callback, null clears it
     *
     * Not part of the @api QuoteInterface on purpose (adding methods to it would break implementors).
     * Whole-row saves ignore validated_at (the column is written only by the dedicated update), so setting it
     * here and saving the quote does not persist it: use Klarna\Kco\Model\Checkout\ValidatedMarker.
     *
     * @internal
     * @param string|null $validatedAt
     * @return $this
     */
    public function setValidatedAt(?string $validatedAt)
    {
        $this->setData('validated_at', $validatedAt);
        return $this;
    }

    /**
     * Get the time (UTC, Y-m-d H:i:s) of the last successful validate callback
     *
     * @return string|null
     */
    public function getValidatedAt(): ?string
    {
        $value = $this->_getData('validated_at');
        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * Constructor
     *
     * @codeCoverageIgnore
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Quote::class);
    }
}
