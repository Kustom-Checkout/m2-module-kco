<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Model\Cart;

use Klarna\Kco\Model\Cart\ShippingMethodUpdate\UpdaterComponentInterface;
use Klarna\Kco\Model\Responder\Klarna;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\InputException;

use function uasort;

class ShippingMethodUpdate implements ShippingMethodUpdateInterface
{
    public const DEFAULT_STATE_CODE = 0;

    /**
     * @var Klarna
     */
    private Klarna $klarna;

    /**
     * @var UpdaterComponentInterface[]
     */
    private array $updaterComponents;

    /**
     * @param Klarna $klarna
     * @param UpdaterComponentInterface[] $updaterComponents
     */
    public function __construct(
        Klarna $klarna,
        array $updaterComponents = []
    ) {
        $this->klarna = $klarna;
        $this->updaterComponents = $updaterComponents;
    }

    /**
     * @inheritDoc
     */
    public function updateByRequest(RequestInterface $request): int
    {
        $data = $this->klarna->getKlarnaRequestBody($request);

        return $this->updateByData($data);
    }

    /**
     * @inheritDoc
     */
    public function updateByData(DataObject $data): int
    {
        $updaterComponents = $this->getSortedUpdaterComponents();
        foreach ($updaterComponents as $index => $updaterComponent) {
            if ($updaterComponent === null) {
                continue;
            }

            if (!($updaterComponent instanceof UpdaterComponentInterface)) {
                throw new InputException(__('Updater \'%1\' does not implement UpdaterComponentInterface', $index));
            }

            if (!$updaterComponent->isRelevant()) {
                continue;
            }

            return $updaterComponent->executeByData($data);
        }

        return self::DEFAULT_STATE_CODE;
    }

    /**
     * @return UpdaterComponentInterface[]
     */
    private function getSortedUpdaterComponents(): array
    {
        $components = $this->updaterComponents;
        uasort($components, static function ($compA, $compB) {
            $sortOrderA = $compA->getSortOrder();
            $sortOrderB = $compB->getSortOrder();
            if ($sortOrderA === null || $sortOrderB === null) {
                return 0;
            }

            if ($sortOrderA > $sortOrderB) {
                return 1;
            }

            if ($sortOrderA < $sortOrderB) {
                return -1;
            }

            return 0;
        });

        return $components;
    }
}
