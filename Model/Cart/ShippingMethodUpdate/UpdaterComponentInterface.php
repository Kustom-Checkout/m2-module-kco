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
     * Whether executeByData() is relevant to execute
     *
     * @return bool
     */
    public function isRelevant(): bool;

    /**
     * Position of this component in relation to other implementations of this interface
     *
     * @return int|null
     */
    public function getSortOrder(): ?int;

    /**
     * Executes shipping method update related processes based on the given data
     *
     * @param DataObject $data
     *
     * @return int
     */
    public function executeByData(DataObject $data): int;
}
