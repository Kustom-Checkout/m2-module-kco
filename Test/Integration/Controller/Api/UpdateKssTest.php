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
use Klarna\Kco\Api\QuoteRepositoryInterface;
use Klarna\Kco\Model\Checkout\ValidatedMarker;
use Klarna\Kco\Model\Order\Order as CheckoutOrder;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use Magento\TestFramework\TestCase\AbstractController;
use PHPUnit\Framework\Attributes\DataProvider;

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
     * An iframe-originated request inside the window is allowed and clears the marker
     *
     * @dataProvider kssUriProvider
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    #[DataProvider('kssUriProvider')]
    public function testIframeOriginAllowedInsideWindow(string $uri): void
    {
        $this->prepare();
        $this->getRequest()->setParam('origin', 'iframe');
        $this->dispatch($uri);

        $body = json_decode($this->getResponse()->getBody(), true);
        $this->assertFalse($body['locked'] ?? false);
        $this->assertNull(
            $this->_objectManager->get(QuoteRepositoryInterface::class)
                ->getByCheckoutId(self::KLARNA_ORDER_ID)->getValidatedAt()
        );
    }

    /**
     * @dataProvider kssUriProvider
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    #[DataProvider('kssUriProvider')]
    public function testIframeOriginRefusedWhenOrderExists(string $uri): void
    {
        $orderMock = $this->createMock(CheckoutOrder::class);
        $orderMock->method('isMagentoOrderExists')->willReturn(true);
        $this->_objectManager->addSharedInstance($orderMock, CheckoutOrder::class);
        $this->prepare();
        $this->getRequest()->setParam('origin', 'iframe');
        $this->dispatch($uri);

        $body = json_decode($this->getResponse()->getBody(), true);
        $this->assertTrue($body['locked'] ?? false);
    }

    /**
     * @return array
     */
    public static function kssUriProvider(): array
    {
        return [
            'status' => ['kco/api/updateKssStatus'],
            'discount order' => ['kco/api/updateKssDiscountOrder'],
        ];
    }

    /**
     * Marker inside the window, session quote, POST
     *
     * @return void
     */
    private function prepare(): void
    {
        $checkoutMock = $this->createMock(Checkout::class);
        $checkoutMock->expects($this->never())->method($this->anything());
        $this->_objectManager->addSharedInstance($checkoutMock, Checkout::class);
        $this->_objectManager->get(ValidatedMarker::class)
            ->update(self::KLARNA_ORDER_ID, gmdate('Y-m-d H:i:s', time() - 10));

        $quote = $this->_objectManager->get(GetQuoteByReservedOrderId::class)->execute('100000001');
        $this->_objectManager->get(CheckoutSession::class)->replaceQuote($quote);

        $this->getRequest()->setMethod(Http::METHOD_POST);
    }

    /**
     * @param string $uri
     * @return void
     */
    private function assertRefused(string $uri): void
    {
        $this->prepare();
        $this->dispatch($uri);

        $body = json_decode($this->getResponse()->getBody(), true);
        $this->assertTrue($body['locked'] ?? false);
        $this->assertStringContainsString('Your payment is being processed', (string) ($body['message'] ?? ''));
    }
}
