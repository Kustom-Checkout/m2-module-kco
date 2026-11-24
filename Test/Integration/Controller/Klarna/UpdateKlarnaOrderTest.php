<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Controller\Klarna;

use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Klarna\Kco\Model\Checkout\ValidatedMarker;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * KUSTOM-128: the frontend order update is refused between validate and order creation
 *
 * @covers \Klarna\Kco\Controller\Klarna\UpdateKlarnaOrder
 */
class UpdateKlarnaOrderTest extends AbstractController
{
    private const KLARNA_ORDER_ID = '123456-1234-1234-1234-1234567890';

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testRefusedInsideWindowLeavesQuoteUnchanged(): void
    {
        $checkoutMock = $this->createMock(Checkout::class);
        $checkoutMock->expects($this->never())->method($this->anything());
        $this->_objectManager->addSharedInstance($checkoutMock, Checkout::class);
        $this->_objectManager->get(ValidatedMarker::class)
            ->update(self::KLARNA_ORDER_ID, gmdate('Y-m-d H:i:s', time() - 10));

        $quote = $this->_objectManager->get(GetQuoteByReservedOrderId::class)->execute('100000001');
        $this->_objectManager->get(CheckoutSession::class)->replaceQuote($quote);
        $grandTotalBefore = (float) $quote->getGrandTotal();

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('checkout/klarna/updateKlarnaOrder');

        $body = json_decode($this->getResponse()->getBody(), true);
        $this->assertTrue($body['locked'] ?? false);
        $this->assertStringContainsString('Your payment is being processed', (string) ($body['message'] ?? ''));

        $reloaded = $this->_objectManager->create(QuoteFactory::class)->create();
        $this->_objectManager->get(QuoteResource::class)->load($reloaded, (int) $quote->getId());
        $this->assertEquals($grandTotalBefore, (float) $reloaded->getGrandTotal());
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testAllowedWithoutMarkerUpdatesKustomOrder(): void
    {
        $checkoutMock = $this->createMock(Checkout::class);
        $checkoutMock->expects($this->atLeastOnce())->method('updateOrder')
            ->willReturn(['id' => self::KLARNA_ORDER_ID, 'is_successful' => true]);
        $this->_objectManager->addSharedInstance($checkoutMock, Checkout::class);

        $quote = $this->_objectManager->get(GetQuoteByReservedOrderId::class)->execute('100000001');
        $this->_objectManager->get(CheckoutSession::class)->replaceQuote($quote);

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('checkout/klarna/updateKlarnaOrder');

        $body = json_decode($this->getResponse()->getBody(), true);
        $this->assertFalse($body['locked'] ?? false);
        $this->assertStringNotContainsString('Your payment is being processed', $this->getResponse()->getBody());
    }
}
