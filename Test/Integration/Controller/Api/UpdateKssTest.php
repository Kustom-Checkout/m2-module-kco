<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Controller\Api;

use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Klarna\Kco\Model\Checkout\ValidatedMarker;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * KUSTOM-128: the KSS status/discount updates are refused between validate and order creation
 *
 * @covers \Klarna\Kco\Controller\Api\UpdateKssStatus
 * @covers \Klarna\Kco\Controller\Api\UpdateKssDiscountOrder
 */
class UpdateKssTest extends AbstractController
{
    private const KLARNA_ORDER_ID = '123456-1234-1234-1234-1234567890';

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testUpdateKssStatusRefusedInsideWindow(): void
    {
        $this->assertRefused('kco/api/updateKssStatus');
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testUpdateKssDiscountOrderRefusedInsideWindow(): void
    {
        $this->assertRefused('kco/api/updateKssDiscountOrder');
    }

    /**
     * @param string $uri
     * @return void
     */
    private function assertRefused(string $uri): void
    {
        $checkoutMock = $this->createMock(Checkout::class);
        $checkoutMock->expects($this->never())->method($this->anything());
        $this->_objectManager->addSharedInstance($checkoutMock, Checkout::class);
        $this->_objectManager->get(ValidatedMarker::class)
            ->update(self::KLARNA_ORDER_ID, gmdate('Y-m-d H:i:s', time() - 10));

        $quote = $this->_objectManager->get(GetQuoteByReservedOrderId::class)->execute('100000001');
        $this->_objectManager->get(CheckoutSession::class)->replaceQuote($quote);

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch($uri);

        $body = json_decode($this->getResponse()->getBody(), true);
        $this->assertTrue($body['locked'] ?? false);
        $this->assertStringContainsString('Your payment is being processed', (string) ($body['message'] ?? ''));
    }
}
