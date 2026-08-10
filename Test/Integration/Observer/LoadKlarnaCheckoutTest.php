<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Observer;

use Klarna\Kco\Model\Api\Rest\Service\Checkout as CheckoutApi;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use Magento\TestFramework\TestCase\AbstractController;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \Klarna\Kco\Observer\LoadKlarnaCheckout
 */
class LoadKlarnaCheckoutTest extends AbstractController
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

        $this->quoteGetter = $this->_objectManager->get(GetQuoteByReservedOrderId::class);
        $this->checkoutSession = $this->_objectManager->get(CheckoutSession::class);
        $this->checkoutApiMock = $this->createMock(CheckoutApi::class);
        $this->_objectManager->addSharedInstance($this->checkoutApiMock, CheckoutApi::class);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store checkout/klarna_kco/use_full_checkout 0
     * @magentoConfigFixture current_store klarna/api/debug 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testLoadKlarnaCheckoutShouldRedirectToKlarnaCheckoutWithConfigOn(): void
    {
        $quote = $this->quoteGetter->execute('100000001');
        $this->checkoutSession->replaceQuote($quote);

        $this->checkoutApiMock->expects($this->never())->method('updateOrder');

        $this->getRequest()->setMethod(Http::METHOD_GET);
        $this->dispatch('checkout/index/index');
        $this->assertRedirect($this->stringContains('checkout/klarna'));
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store checkout/klarna_kco/use_full_checkout 1
     * @magentoConfigFixture current_store klarna/api/debug 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testLoadKlarnaCheckoutShouldRedirectToFullCheckoutWithConfigOn(): void
    {
        $quote = $this->quoteGetter->execute('100000001');
        $this->checkoutSession->replaceQuote($quote);

        $this->checkoutApiMock->expects($this->atLeastOnce())->method('updateOrder')
            ->willReturn([
                'full_checkout_uri' => 'http://localhost/full/checkout/uri',
                'id' => '12345',
                'is_successful' => true,
            ]);

        $this->getRequest()->setMethod(Http::METHOD_GET);
        $this->dispatch('checkout/index/index');
        $this->assertRedirect($this->stringContains('full/checkout/uri'));
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 0
     * @magentoConfigFixture current_store checkout/klarna_kco/use_full_checkout 0
     * @magentoConfigFixture current_store klarna/api/debug 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testLoadKlarnaCheckoutShouldRedirectToDefaultCheckoutWithConfigOff(): void
    {
        $quote = $this->quoteGetter->execute('100000001');
        $this->checkoutSession->replaceQuote($quote);

        $this->checkoutApiMock->expects($this->never())->method('updateOrder');

        $this->getRequest()->setMethod(Http::METHOD_GET);
        $this->dispatch('checkout/index/index');
        $this->assertFalse($this->getResponse()->isRedirect());
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store checkout/klarna_kco/use_full_checkout 1
     * @magentoConfigFixture current_store klarna/api/debug 1
     * @magentoConfigFixture current_store general/region/state_required ''
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testLoadKlarnaCheckoutShouldRedirectToCartWithErrorMessageWhenApiCallsFailWithFullCheckout(): void
    {
        $quote = $this->quoteGetter->execute('100000001');
        $this->checkoutSession->replaceQuote($quote);

        $this->checkoutApiMock->expects($this->atLeastOnce())->method('updateOrder')
            ->willThrowException(new \Exception('Test'));

        $this->getRequest()->setMethod(Http::METHOD_GET);
        $this->dispatch('checkout/index/index');
        $this->assertRedirect($this->stringContains('checkout/cart'));
        $this->assertSessionMessages($this->equalTo(['Kustom Checkout has failed to load']));
    }
}
