<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Api;

use Klarna\AdminSettings\Model\Configurations\Kco\ShippingOptions;
use Klarna\Kco\Model\Api\Builder\Kasper as KasperBuilder;
use Klarna\Kco\Model\Api\Kasper;
use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Klarna\Kco\Model\Checkout\Kco\Session;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Api\Kasper
 */
#[AllowMockObjectsWithoutExpectations]
class KasperUpdateOrderTest extends TestCase
{
    /**
     * @covers ::updateOrder
     */
    public function testUpdateOrderClearsShippingOptionsWhenShippingNotInIframe(): void
    {
        $sent = $this->runUpdateOrder(false, []);

        static::assertSame([], $sent['shipping_options']);
    }

    /**
     * @covers ::updateOrder
     */
    public function testUpdateOrderKeepsBuilderShippingOptionsWhenShippingNotInIframe(): void
    {
        $sent = $this->runUpdateOrder(false);

        static::assertSame([['id' => 'kss']], $sent['shipping_options']);
    }

    /**
     * @covers ::updateOrder
     */
    public function testUpdateOrderKeepsShippingOptionsWhenShippingInIframe(): void
    {
        $sent = $this->runUpdateOrder(true);

        static::assertSame([['id' => 'kss']], $sent['shipping_options']);
    }

    /**
     * Runs updateOrder and returns the request data handed to the checkout API
     *
     * @param bool $inIframe
     * @param array $builderData
     * @return array
     */
    private function runUpdateOrder(
        bool $inIframe,
        array $builderData = ['shipping_options' => [['id' => 'kss']]]
    ): array {
        $store = $this->createStub(StoreInterface::class);
        $quote = new DataObject(['store' => $store, 'base_currency_code' => 'EUR']);

        $shippingOptions = $this->createStub(ShippingOptions::class);
        $shippingOptions->method('isShippingInIframe')->willReturn($inIframe);

        $order = new DataObject(['is_successful' => true]);
        $factory = $this->createStub(DataObjectFactory::class);
        $factory->method('create')->willReturn($order);

        $captured = null;
        $checkout = $this->createMock(Checkout::class);
        $checkout->expects(static::once())
            ->method('updateOrder')
            ->willReturnCallback(function ($id, $data) use (&$captured) {
                $captured = $data;
                return [];
            });

        $model = $this->getMockBuilder(Kasper::class)
            ->setConstructorArgs([
                $this->createStub(Session::class),
                $checkout,
                $factory,
                $this->createStub(KasperBuilder::class),
                ['quote' => $quote],
                $shippingOptions
            ])
            ->onlyMethods(['getGeneratedUpdateRequest'])
            ->getMock();
        $model->method('getGeneratedUpdateRequest')->willReturn($builderData);

        $model->updateOrder('abc');

        return $captured;
    }
}
