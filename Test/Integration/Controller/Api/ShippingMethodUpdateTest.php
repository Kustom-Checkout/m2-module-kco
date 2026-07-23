<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Controller\Api;

use Klarna\Kco\Model\Api\Rest\Service\Checkout as CheckoutApi;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use Magento\TestFramework\TestCase\AbstractController;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \Klarna\Kco\Controller\Api\ShippingMethodUpdate
 */
class ShippingMethodUpdateTest extends AbstractController
{
    /**
     * @var GetQuoteByReservedOrderId
     */
    private $quoteGetter;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CheckoutApi|MockObject
     */
    private $checkoutApiMock;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->checkoutApiMock = $this->createMock(CheckoutApi::class);
        $this->_objectManager->addSharedInstance($this->checkoutApiMock, CheckoutApi::class);

        $this->quoteGetter = $this->_objectManager->get(GetQuoteByReservedOrderId::class);
        $this->checkoutSession = $this->_objectManager->get(CheckoutSession::class);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store payment/klarna_kss/enabled 0
     * @magentoConfigFixture current_store klarna/api/debug 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testShippingMethodUpdateWithoutKss(): void
    {
        $quoteId = '100000001';
        $klarnaOrderId = '123456-1234-1234-1234-1234567890';
        $expectedShippingOptions = [
            [
                'id' => 'flatrate_flatrate',
                'name' => 'Fixed',
                'price' => 500,
                'promo' => '',
                'tax_amount' => 0,
                'tax_rate' => 0,
                'description' => 'Flat Rate',
                'preselected' => true,
            ],
        ];

        $this->checkoutApiMock->expects($this->never())->method('getOrder');

        $quote = $this->quoteGetter->execute($quoteId);
        $this->checkoutSession->replaceQuote($quote);

        $this->getRequest()->setContent(json_encode([
            'selected_shipping_option' => [
                'id' => 'flatrate_flatrate',
            ],
        ]));
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('kco/api/shippingMethodUpdate/id/' . $klarnaOrderId);
        $body = json_decode($this->getResponse()->getBody(), true);
        $this->assertEquals($expectedShippingOptions, $body['shipping_options'] ?? []);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store payment/klarna_kss/enabled 1
     * @magentoConfigFixture current_store klarna/api/debug 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testShippingMethodUpdateWithKss(): void
    {
        $quoteId = '100000001';
        $klarnaOrderId = '123456-1234-1234-1234-1234567890';
        $expectedShippingOptions = [
            [
                'id' => 'flatrate_flatrate',
                'name' => 'Fixed',
                'price' => 500,
                'promo' => '',
                'tax_amount' => 0,
                'tax_rate' => 0,
                'description' => 'Flat Rate',
                'preselected' => false,
            ],
            [
                'id' => 'klarna_shipping_method_gateway',
                'name' => 'DHL Express',
                'price' => 999,
                'promo' => '',
                'tax_amount' => 0,
                'tax_rate' => 0,
                'description' => 'Kustom',
                'preselected' => false,
            ],
            [
                'id' => '019f46f5-4769-7b29-bbe9-a2b179d21424',
                'name' => 'klarna_shipping_method_gateway',
                'price' => 999,
                'promo' => '',
                'tax_amount' => 0,
                'tax_rate' => 0,
                'description' => '',
            ],
        ];

        $selectedShipping = [
            'id' => '019f46f5-4769-7b29-bbe9-a2b179d21424',
            'name' => 'DHL Express',
            'price' => 999,
            'tax_amount' => 200,
            'tax_rate' => 2500,
            'shipping_method' => 'Home',
            'delivery_details' => [
                'carrier' => 'dhl-express',
                'class' => 'standard',
            ],
        ];

        $this->checkoutApiMock->expects($this->atLeastOnce())->method('getOrder')
            ->willReturn([
                'id' => $klarnaOrderId,
                'is_successful' => true,
                'order_id' => $klarnaOrderId,
                'selected_shipping_option' => $selectedShipping,
            ]);

        $quote = $this->quoteGetter->execute($quoteId);
        $this->checkoutSession->replaceQuote($quote);

        $this->getRequest()->setContent(json_encode([
            'selected_shipping_option' => $selectedShipping,
        ]));
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('kco/api/shippingMethodUpdate/id/' . $klarnaOrderId);
        $body = json_decode($this->getResponse()->getBody(), true);
        $this->assertEquals($expectedShippingOptions, $body['shipping_options'] ?? []);
    }
}
