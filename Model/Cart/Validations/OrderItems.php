<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Cart\Validations;

use Klarna\Base\Exception as KlarnaException;
use Klarna\Base\Model\Api\OrderLineProcessor;
use Klarna\Kco\Api\CheckoutValidationInterface;
use Klarna\Orderlines\Model\Container\Parameter;
use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\CartInterface;

/**
 * @internal
 */
class OrderItems implements CheckoutValidationInterface
{
    /**
     * @var Parameter
     */
    private Parameter $parameter;

    /**
     * @param Parameter $parameter
     * @codeCoverageIgnore
     */
    public function __construct(Parameter $parameter)
    {
        $this->parameter = $parameter;
    }

    /**
     * Checking if on the quote and the Klarna request there are the same items
     *
     * @param DataObject $request
     * @param CartInterface $quote
     * @throws KlarnaException
     */
    public function validate(DataObject $request, CartInterface $quote): void
    {
        $this->parameter->resetOrderLines();
        $this->parameter->getOrderLineProcessor()
            ->processByQuote($this->parameter, $quote);
        $localOrderLines = $this->parameter->getOrderLines();

        foreach ($localOrderLines as $localOrderLine) {
            if (!$this->isProductUrlGiven($localOrderLine)) {
                continue;
            }
            foreach ($request->getOrderLines() as $requestOrderLine) {
                if (!$this->isProductUrlGiven($requestOrderLine)) {
                    continue;
                }
                if ($this->isItemFound($localOrderLine, $requestOrderLine)) {
                    continue 2;
                }
            }

            $errorMessage = sprintf(
                'Order items do not match for quote ID %s. Kustom order items are %s vs Magento order items %s',
                $quote->getId(),
                json_encode($localOrderLines),
                json_encode($request->getOrderLines())
            );
            throw new KlarnaException(__($errorMessage));
        }
    }

    /**
     * Returns true if the product url is given which means that the order line item is a product
     *
     * @param array $orderLineItem
     * @return bool
     */
    private function isProductUrlGiven(array $orderLineItem): bool
    {
        return isset($orderLineItem['product_url']);
    }

    /**
     * Returns true if  a match between the local order line item and the request order line item was found.
     *
     * @param array $localOrderLine
     * @param array $requestOrderLine
     * @return bool
     */
    private function isItemFound(array $localOrderLine, array $requestOrderLine): bool
    {
        return $localOrderLine['name'] === $requestOrderLine['name'] &&
            $localOrderLine['product_url'] === $requestOrderLine['product_url'] &&
            $localOrderLine['quantity'] === $requestOrderLine['quantity'];
    }
}
