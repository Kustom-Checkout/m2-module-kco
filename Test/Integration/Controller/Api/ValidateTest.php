<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Controller\Api;

use Klarna\Base\Exception as KlarnaException;
use Klarna\Kco\Api\QuoteRepositoryInterface;
use Klarna\Kco\Model\Checkout\ValidatedMarker;
use Klarna\Kco\Exception\AddressValidationException;
use Klarna\Kco\Model\Cart\Validations\Handler;
use Klarna\Kco\Model\QuoteFactory;
use Klarna\Kco\Model\ResourceModel\Quote as QuoteResource;
use Magento\Framework\App\Request\Http;
use Magento\TestFramework\TestCase\AbstractController;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * KUSTOM-128: the validate callback sets or clears klarna_kco_quote.validated_at
 *
 * @covers \Klarna\Kco\Controller\Api\Validate
 */
class ValidateTest extends AbstractController
{
    private const KLARNA_ORDER_ID = '123456-1234-1234-1234-1234567890';

    /**
     * @var Handler|MockObject
     */
    private $handlerMock;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->handlerMock = $this->createMock(Handler::class);
        $this->_objectManager->addSharedInstance($this->handlerMock, Handler::class);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testSuccessSetsMarker(): void
    {
        $this->assertNull($this->readMarker());

        $this->dispatchValidate();

        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
        $marker = $this->readMarker();
        $this->assertNotNull($marker);
        $this->assertEqualsWithDelta(time(), strtotime($marker . ' UTC'), 60);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testValidatorFailureAnswers303AndClearsMarker(): void
    {
        $this->writeMarker('2020-01-01 00:00:00');
        $this->handlerMock->method('validateRequestObject')
            ->willThrowException(new KlarnaException(__('Order total does not match')));

        $this->dispatchValidate();

        $this->assertEquals(303, $this->getResponse()->getHttpResponseCode());
        $this->assertNull($this->readMarker());
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testAddressErrorAnswers400AndClearsMarker(): void
    {
        $this->writeMarker('2020-01-01 00:00:00');
        $this->handlerMock->method('validateRequestObject')
            ->willThrowException(new AddressValidationException(__('We could not use this address.')));

        $this->dispatchValidate();

        $this->assertEquals(400, $this->getResponse()->getHttpResponseCode());
        $this->assertStringContainsString('address_error', $this->getResponse()->getBody());
        $this->assertNull($this->readMarker());
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testSecondSuccessRenewsMarker(): void
    {
        $this->writeMarker('2020-01-01 00:00:00');

        $this->dispatchValidate();

        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
        $marker = $this->readMarker();
        $this->assertNotNull($marker);
        $this->assertGreaterThan(strtotime('2020-01-01 00:00:00 UTC'), strtotime($marker . ' UTC'));
    }

    /**
     * @return void
     */
    private function dispatchValidate(): void
    {
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->getRequest()->setContent('{}');
        $this->dispatch('kco/api/validate/id/' . self::KLARNA_ORDER_ID);
    }

    /**
     * Read straight from the database, bypassing the in-memory repository cache
     *
     * @return string|null
     */
    private function readMarker(): ?string
    {
        $quote = $this->_objectManager->create(QuoteFactory::class)->create();
        $this->_objectManager->get(QuoteResource::class)->load($quote, self::KLARNA_ORDER_ID, 'klarna_checkout_id');

        return $quote->getValidatedAt();
    }

    /**
     * @param string|null $value
     * @return void
     */
    private function writeMarker(?string $value): void
    {
        $this->_objectManager->get(ValidatedMarker::class)->update(self::KLARNA_ORDER_ID, $value);
    }
}
