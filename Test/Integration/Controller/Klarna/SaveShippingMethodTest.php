<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Controller\Klarna;

use Klarna\Kco\Api\QuoteRepositoryInterface;
use Klarna\Kco\Model\Checkout\ValidatedMarker;
use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use Magento\TestFramework\TestCase\AbstractController;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * KUSTOM-128: the frontend shipping method change is refused between validate and order creation
 *
 * @covers \Klarna\Kco\Controller\Klarna\SaveShippingMethod
 */
class SaveShippingMethodTest extends AbstractController
{
    private const KLARNA_ORDER_ID = '123456-1234-1234-1234-1234567890';
    private const ORIGINAL_METHOD = 'flatrate_flatrate';
    private const NEW_METHOD = 'freeshipping_freeshipping';

    /**
     * @var Checkout|MockObject
     */
    private $checkoutMock;

    /**
     * @var int
     */
    private $quoteId;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->checkoutMock = $this->createMock(Checkout::class);
        $this->_objectManager->addSharedInstance($this->checkoutMock, Checkout::class);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store carriers/freeshipping/active 1
     * @magentoConfigFixture current_store carriers/freeshipping/free_shipping_subtotal 0
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testChangesMethodBeforeValidate(): void
    {
        $this->checkoutMock->expects($this->never())->method('getOrder');

        $this->dispatchSave();

        $this->assertEquals(self::NEW_METHOD, $this->loadShippingMethod());
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store carriers/freeshipping/active 1
     * @magentoConfigFixture current_store carriers/freeshipping/free_shipping_subtotal 0
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testRefusedInsideWindowWithoutApiCall(): void
    {
        $this->setMarker(gmdate('Y-m-d H:i:s', time() - 10));
        $this->checkoutMock->expects($this->never())->method('getOrder');

        $this->dispatchSave();

        $this->assertEquals(self::ORIGINAL_METHOD, $this->loadShippingMethod());
        $body = json_decode($this->getResponse()->getBody(), true);
        $this->assertTrue($body['locked'] ?? false);
        $this->assertStringContainsString('Your payment is being processed', $this->getResponse()->getBody());
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store carriers/freeshipping/active 1
     * @magentoConfigFixture current_store carriers/freeshipping/free_shipping_subtotal 0
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testChangesMethodWhenMarkerOlderThanWindowAndCheckoutIncomplete(): void
    {
        $this->setMarker(gmdate('Y-m-d H:i:s', time() - 400));
        $this->checkoutMock->expects($this->once())->method('getOrder')
            ->willReturn(['status' => 'checkout_incomplete']);

        $this->dispatchSave();

        $this->assertEquals(self::NEW_METHOD, $this->loadShippingMethod());
        $this->assertNull($this->_objectManager->get(QuoteRepositoryInterface::class)
            ->getByCheckoutId(self::KLARNA_ORDER_ID)->getValidatedAt());
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store carriers/freeshipping/active 1
     * @magentoConfigFixture current_store carriers/freeshipping/free_shipping_subtotal 0
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testRefusedWhenMarkerOlderThanWindowAndCheckoutComplete(): void
    {
        $this->setMarker(gmdate('Y-m-d H:i:s', time() - 400));
        $this->checkoutMock->expects($this->once())->method('getOrder')
            ->willReturn(['status' => 'checkout_complete']);

        $this->dispatchSave();

        $this->assertEquals(self::ORIGINAL_METHOD, $this->loadShippingMethod());
        $body = json_decode($this->getResponse()->getBody(), true);
        $this->assertTrue($body['locked'] ?? false);
    }

    /**
     * A validate deny clears the marker (NULL), so the customer can change the method again
     *
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoConfigFixture current_store carriers/freeshipping/active 1
     * @magentoConfigFixture current_store carriers/freeshipping/free_shipping_subtotal 0
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testChangesMethodWhenMarkerIsNullAfterDecline(): void
    {
        $this->setMarker(gmdate('Y-m-d H:i:s', time() - 10));
        $this->setMarker(null);
        $this->checkoutMock->expects($this->never())->method('getOrder');

        $this->dispatchSave();

        $this->assertEquals(self::NEW_METHOD, $this->loadShippingMethod());
    }

    /**
     * @return void
     */
    private function dispatchSave(): void
    {
        $quote = $this->_objectManager->get(GetQuoteByReservedOrderId::class)->execute('100000001');
        $this->quoteId = (int) $quote->getId();
        $this->_objectManager->get(CheckoutSession::class)->replaceQuote($quote);

        [$carrier, $method] = explode('_', self::NEW_METHOD);
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->getRequest()->setContent(json_encode(['carrier_code' => $carrier, 'method_code' => $method]));
        $this->dispatch('checkout/klarna/saveShippingMethod');
    }

    /**
     * Read straight from the database
     *
     * @return string|null
     */
    private function loadShippingMethod(): ?string
    {
        $quote = $this->_objectManager->create(QuoteFactory::class)->create();
        $this->_objectManager->get(QuoteResource::class)->load($quote, $this->quoteId);

        return $quote->getShippingAddress()->getShippingMethod();
    }

    /**
     * @param string|null $validatedAt
     * @return void
     */
    private function setMarker(?string $validatedAt): void
    {
        $this->_objectManager->get(ValidatedMarker::class)->update(self::KLARNA_ORDER_ID, $validatedAt);
    }
}
