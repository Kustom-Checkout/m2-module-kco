<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Controller\Api;

use Klarna\Kco\Model\Order\Order as CheckoutOrder;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * KUSTOM-128: the address_update callback must not change a quote whose order exists
 *
 * @covers \Klarna\Kco\Controller\Api\AddressUpdate
 */
class AddressUpdateTest extends AbstractController
{
    private const KLARNA_ORDER_ID = '123456-1234-1234-1234-1234567890';

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store klarna/api/debug 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testQuoteUnchangedWhenMagentoOrderExists(): void
    {
        $orderMock = $this->createMock(CheckoutOrder::class);
        $orderMock->method('isMagentoOrderExists')->willReturn(true);
        $this->_objectManager->addSharedInstance($orderMock, CheckoutOrder::class);

        $quote = $this->_objectManager->get(GetQuoteByReservedOrderId::class)->execute('100000001');
        $this->_objectManager->get(CheckoutSession::class)->replaceQuote($quote);
        $emailBefore = $quote->getBillingAddress()->getEmail();
        $cityBefore = $quote->getShippingAddress()->getCity();

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->getRequest()->setContent(json_encode([
            'shipping_address' => ['city' => 'Changedcity', 'email' => 'changed@example.com'],
            'billing_address' => ['city' => 'Changedcity', 'email' => 'changed@example.com'],
        ]));
        $this->dispatch('kco/api/addressUpdate/id/' . self::KLARNA_ORDER_ID);

        $this->assertEquals(400, $this->getResponse()->getHttpResponseCode());
        $reloaded = $this->_objectManager->create(QuoteFactory::class)->create();
        $this->_objectManager->get(QuoteResource::class)->load($reloaded, (int) $quote->getId());
        $this->assertEquals($cityBefore, $reloaded->getShippingAddress()->getCity());
        $this->assertEquals($emailBefore, $reloaded->getBillingAddress()->getEmail());
    }
}
