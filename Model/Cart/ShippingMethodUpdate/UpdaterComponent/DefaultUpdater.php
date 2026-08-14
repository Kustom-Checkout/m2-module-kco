<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Model\Cart\ShippingMethodUpdate\UpdaterComponent;

use Klarna\Kco\Model\Cart\ShippingMethod\KlarnaRequestQuoteTransformer;
use Klarna\Kco\Model\Cart\ShippingMethodUpdate\UpdaterComponentInterface;
use Magento\Framework\DataObject;

class DefaultUpdater implements UpdaterComponentInterface
{
    public const STATE_CODE = 100;

    /**
     * @var KlarnaRequestQuoteTransformer
     */
    private KlarnaRequestQuoteTransformer $quoteTransformer;

    /**
     * @param KlarnaRequestQuoteTransformer $quoteTransformer
     */
    public function __construct(
        KlarnaRequestQuoteTransformer $quoteTransformer
    ) {
        $this->quoteTransformer = $quoteTransformer;
    }

    /**
     * @inheritDoc
     */
    public function isRelevant(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): ?int
    {
        return 100;
    }

    /**
     * @inheritDoc
     */
    public function executeByData(DataObject $data): int
    {
        $klarnaOrderId = (string) $data->getId();

        $this->quoteTransformer->updateQuoteShippingMethod($data, $klarnaOrderId);

        return self::STATE_CODE;
    }
}
