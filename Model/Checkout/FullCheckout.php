<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Model\Checkout;

use Klarna\Base\Exception;
use Klarna\Kco\Model\Api\Factory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;

class FullCheckout
{
    /**
     * @var Initialization
     */
    private Initialization $sessionInit;

    /**
     * @var Factory
     */
    private Factory $apiFactory;

    /**
     * @param Initialization $sessionInit
     * @param Factory $apiFactory
     */
    public function __construct(
        Initialization $sessionInit,
        Factory $apiFactory
    ) {
        $this->sessionInit = $sessionInit;
        $this->apiFactory = $apiFactory;
    }

    /**
     * Initializes or updates the checkout session, then grabs the full_checkout_url from the API order details
     *
     * @param StoreInterface $store
     *
     * @return string
     * @throws Exception
     * @throws LocalizedException
     */
    public function generateFullCheckoutUrl(StoreInterface $store): string
    {
        $state = $this->sessionInit->createUpdateKlarnaSession();
        if ($state === Initialization::STATE_NONE) {
            return '';
        }

        $apiInstance = $this->apiFactory->createApiInstance($store);
        $order = $apiInstance->getKlarnaOrder();

        return (string) $order->getFullCheckoutUri();
    }
}
