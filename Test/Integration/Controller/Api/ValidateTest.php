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
use Klarna\Kco\Model\Cart\FullUpdate;
use Klarna\Kco\Model\Cart\Validations\CustomerAddress;
use Klarna\Kco\Model\Cart\Validations\Handler;
use Klarna\Kco\Model\QuoteFactory;
use Klarna\Kco\Model\ResourceModel\Quote as QuoteResource;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Model\AddressRegistry;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
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
     * KUSTOM-129 for a registered customer: a valid billing address is accepted
     *
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testRegisteredCustomerWithValidAddressAnswers200(): void
    {
        $this->useAddressValidationOnly(false);

        $this->dispatchValidate('{"order_id":"' . self::KLARNA_ORDER_ID . '"}');

        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
        $this->assertStringNotContainsString('address_error', $this->getResponse()->getBody());
        $this->assertNotNull($this->readMarker());
    }

    /**
     * KUSTOM-129 for a registered customer: an address Magento rejects answers 400 address_error
     *
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testRegisteredCustomerWithInvalidAddressAnswers400(): void
    {
        $this->useAddressValidationOnly(true);

        $this->dispatchValidate('{"order_id":"' . self::KLARNA_ORDER_ID . '"}');

        $this->assertEquals(400, $this->getResponse()->getHttpResponseCode());
        $this->assertStringContainsString('address_error', $this->getResponse()->getBody());
        $this->assertNull($this->readMarker());
    }

    /**
     * Real address validation on the fixture quote of a registered customer, the rest of the handler is skipped.
     * The billing address points to a saved customer address (customer_address_id), as Magento validates those.
     *
     * @param bool $breakAddress the saved customer address is made invalid (empty city, required by Magento)
     * @return void
     */
    private function useAddressValidationOnly(bool $breakAddress): void
    {
        $quote = $this->_objectManager->create(GetQuoteByReservedOrderId::class)->execute('100000001');
        $this->assertNotEmpty($quote->getCustomerId());

        $addressFactory = $this->_objectManager->get(AddressInterfaceFactory::class);
        $customerAddress = $addressFactory->create()
            ->setCustomerId((int) $quote->getCustomerId())
            ->setFirstname('John')
            ->setLastname('Smith')
            ->setStreet(['Green str, 67'])
            ->setCity('CityM')
            ->setPostcode('36104')
            ->setCountryId('US')
            ->setRegionId(12)
            ->setTelephone('3468676')
            ->setIsDefaultBilling(true);
        $customerAddress = $this->_objectManager->get(AddressRepositoryInterface::class)->save($customerAddress);
        if ($breakAddress) {
            // A repository save would reject it: this is how an address ends up invalid after later rule changes
            $resource = $this->_objectManager->get(ResourceConnection::class);
            $resource->getConnection()->update(
                $resource->getTableName('customer_address_entity'),
                ['city' => ''],
                ['entity_id = ?' => $customerAddress->getId()]
            );
            $this->_objectManager->get(AddressRegistry::class)->remove($customerAddress->getId());
        }

        $quote->getBillingAddress()->setCustomerAddressId($customerAddress->getId());
        $this->_objectManager->get(CartRepositoryInterface::class)->save($quote);

        $handler = $this->_objectManager->create(Handler::class, [
            'fullUpdate' => $this->createMock(FullUpdate::class),
            'validations' => ['customer_address' => $this->_objectManager->create(CustomerAddress::class)],
        ]);
        $this->_objectManager->addSharedInstance($handler, Handler::class);
    }

    /**
     * @param string $body
     * @return void
     */
    private function dispatchValidate(string $body = '{}'): void
    {
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->getRequest()->setContent($body);
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
