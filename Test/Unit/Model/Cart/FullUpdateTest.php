<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

namespace Klarna\Kco\Test\Unit\Model\Cart;

use Klarna\Kco\Model\Cart\FullUpdate;
use Klarna\Base\Test\Unit\Mock\MockFactory;
use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magento\Quote\Api\Data\CartExtension;
use Magento\Quote\Model\Quote\Address as QuoteAddress;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Cart\FullUpdate
 */
class FullUpdateTest extends TestCase
{
    /**
     * @var FullUpdate
     */
    private $model;
    /**
     * @var MockObject[]
     */
    private $dependencyMocks;
    /**
     * @var MockObject|DataObject
     */
    private $checkout;
    /**
     * @var MockObject|Quote
     */
    private $quote;
    /**
     * @var MockFactory
     */
    private $mockFactory;

    public function testUpdateByKlarnaRequestObjectAlwaysUpdatesAddressesAndQuote(): void
    {
        $this->dependencyMocks['handler']->expects(static::once())
            ->method('setBillingAddressDataFromRequest');
        $this->dependencyMocks['handler']->expects(static::once())
            ->method('setShippingAddressDataFromRequest');
        $this->dependencyMocks['customer']->expects(static::once())
            ->method('setCustomerDataFromRequest');

        $this->model->updateByKlarnaRequestObject($this->checkout, '1', $this->quote);
    }

    /**
     * @return array
     */
    public function checkoutDataObjectProvider(): array
    {
        return [
            [
                [
                    'hasBillingAddress' => true,
                    'hasShippingAddress' => true
                ]
            ],
            [
                [
                    'hasBillingAddress' => false,
                    'hasShippingAddress' => true
                ]
            ],
            [
                [
                    'hasBillingAddress' => true,
                    'hasShippingAddress' => false
                ]
            ],
            [
                [
                    'hasBillingAddress' => false,
                    'hasShippingAddress' => false
                ]
            ]
        ];
    }

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $this->mockFactory     = new MockFactory($this);
        $objectFactory         = new TestObjectFactory($this->mockFactory);
        $this->model           = $objectFactory->create(FullUpdate::class);
        $this->dependencyMocks = $objectFactory->getDependencyMocks();
        $this->checkout        = $this->mockFactory->create(DataObject::class, [], [
            'getShippingAddress',
            'getBillingAddress',
            'getSelectedShippingOption',
            'hasBillingAddress',
            'hasShippingAddress',
            'setShippingAddress'
        ]);

        $this->quote           = $this->mockFactory->create(Quote::class, []);
        $quoteShippingAddress = $this->mockFactory->create(QuoteAddress::class);
        $this->quote->method('getShippingAddress')
            ->willReturn($quoteShippingAddress);

        if (method_exists(CartExtension::class, 'getShippingAssignments')) {
            $extensionAttributes = $this->mockFactory->create(CartExtension::class, ['getShippingAssignments']);
        } else {
            $extensionAttributes = $this->mockFactory->create(CartExtension::class, [], ['getShippingAssignments']);
        }

        $extensionAttributes->method('getShippingAssignments')
            ->willReturn([]);

        $this->checkout->expects(static::once())
            ->method('getShippingAddress')
            ->willReturn([
                'country' => 'value_1',
                'city' => 'value_2'
            ]);
        $this->checkout->expects(static::once())
            ->method('getBillingAddress')
            ->willReturn([
                'country' => 'value_3',
                'city' => 'value_4'
            ]);
    }
}
