<?php
/**
 * Copyright © Kustom AB (Originally developed by Klarna Bank AB)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Cart\Validations;

use Klarna\Base\Exception as KlarnaException;
use Klarna\Orderlines\Model\ItemGenerator;
use Klarna\Kco\Api\CheckoutValidationInterface;
use Klarna\Kco\Model\Carrier\Klarna;
use Klarna\Kss\Model\KssConfigProvider;
use Klarna\Kss\Model\ShippingMethodGatewayRepository;
use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote\Address;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Validating the selected shipping method between the Klarna order and the shop quote
 *
 * @internal
 */
class ShippingMethod implements CheckoutValidationInterface
{
    /**
     * @var ShippingMethodGatewayRepository
     */
    private ShippingMethodGatewayRepository $shippingMethodGatewayRepository;
    /**
     * @var KssConfigProvider
     */
    private KssConfigProvider $kssConfigProvider;

    /**
     * @param ShippingMethodGatewayRepository $shippingMethodGatewayRepository
     * @param KssConfigProvider               $kssConfigProvider
     * @codeCoverageIgnore
     */
    public function __construct(
        ShippingMethodGatewayRepository $shippingMethodGatewayRepository,
        KssConfigProvider $kssConfigProvider
    ) {
        $this->shippingMethodGatewayRepository = $shippingMethodGatewayRepository;
        $this->kssConfigProvider               = $kssConfigProvider;
    }

    /**
     * @inheritDoc
     */
    public function validate(DataObject $request, CartInterface $quote): void
    {
        if ($quote->isVirtual()) {
            return;
        }
        /** @var Address $address */
        $address              = $quote->getShippingAddress();
        $quoteShippingMethod  = $address->getShippingMethod();
        $klarnaShippingMethod = $this->getSelectedKlarnaShippingMethod($request, $quote);
        if ($quoteShippingMethod !== $klarnaShippingMethod) {
            $exceptionMessage = __(
                'Shipping method does not match for order #%1. Kustom method is %2 vs Magento method is %3',
                $quote->getReservedOrderId(),
                $klarnaShippingMethod,
                $quoteShippingMethod
            );
            throw new KlarnaException($exceptionMessage);
        }
    }

    /**
     * Getting back the shipping method which is set for KCO. When KSS is used then we will return the KSS gateway key.
     *
     * A KSS gateway entry is only relevant while KSS is enabled for the store. An entry left active from a
     * checkout session that started before KSS was disabled must not override the method sent by Kustom.
     *
     * @param DataObject    $request
     * @param CartInterface $quote
     * @return string
     */
    private function getSelectedKlarnaShippingMethod(DataObject $request, CartInterface $quote): string
    {
        $requestMethod = $this->getMethodFromRequest($request);

        if (!$this->kssConfigProvider->isKssEnabled($quote->getStore())) {
            return $requestMethod;
        }

        try {
            $entry = $this->shippingMethodGatewayRepository->loadShipping($request->getOrderId(), 'klarna_session_id');
        } catch (NoSuchEntityException $e) {
            return $requestMethod;
        }

        if (!$entry->isActive()) {
            return $requestMethod;
        }

        return Klarna::GATEWAY_KEY;
    }

    /**
     * Getting back the selected shipping method from the request
     *
     * @param DataObject $request
     * @return string
     */
    private function getMethodFromRequest(DataObject $request): string
    {
        // Shipping method selection happened inside of the iframe
        if (isset($request->getSelectedShippingOption()['id'])) {
            return $request->getSelectedShippingOption()['id'];
        }

        // Shipping method selection happened outside of the iframe
        foreach ($request->getOrderLines() as $item) {
            if ($item['type'] === ItemGenerator::ITEM_TYPE_SHIPPING) {
                return $item['reference'];
            }
        }

        return '';
    }
}
