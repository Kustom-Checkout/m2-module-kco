<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Model\Cart;

use Klarna\Kco\Model\Cart\ShippingMethodUpdater\UpdaterComponentInterface;
use Klarna\Kco\Model\Responder\Klarna;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\InputException;

use function uasort;

interface ShippingMethodUpdateInterface
{
    /**
     * @param RequestInterface $request
     *
     * @return int
     * @throws InputException
     */
    public function updateByRequest(RequestInterface $request): int;

    /**
     * @param DataObject $data
     *
     * @return int
     * @throws InputException
     */
    public function updateByData(DataObject $data): int;
}
