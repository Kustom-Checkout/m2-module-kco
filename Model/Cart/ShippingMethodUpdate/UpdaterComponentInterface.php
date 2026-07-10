<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Model\Cart\ShippingMethodUpdate;

use Magento\Framework\DataObject;

interface UpdaterComponentInterface
{
    /**
     * @return bool
     */
    public function isRelevant(): bool;

    /**
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * @param DataObject $data
     *
     * @return int
     */
    public function executeByData(DataObject $data): int;
}
