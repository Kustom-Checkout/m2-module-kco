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
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Cache\Manager as CacheManager;
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
     * @var bool|null original is_required of the company attribute, null while unchanged
     */
    private ?bool $originalCompanyRequired = null;

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
     * The EAV attribute change is not reliably rolled back by the DB isolation, so it is restored here
     *
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        if ($this->originalCompanyRequired !== null) {
            $this->setCompanyRequired($this->originalCompanyRequired);
            $this->originalCompanyRequired = null;
        }

        parent::tearDown();
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
     * KUSTOM-129 follow-up: logged-in customer, address typed in the checkout (no saved address id), company required
     *
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testLoggedInCustomerWithNewAddressWithoutCompanyAnswers400(): void
    {
        $this->useUnsavedQuoteAddresses('');
        // Required only after the quote is saved, so the fixture setup is not affected by the rule; the test relies
        // on the validate callback checking the addresses as they are stored on the quote.
        $this->requireCompanyAttribute();

        $this->dispatchValidate('{"order_id":"' . self::KLARNA_ORDER_ID . '"}');

        $this->assertEquals(400, $this->getResponse()->getHttpResponseCode());
        $this->assertStringContainsString('address_error', $this->getResponse()->getBody());
        $this->assertNull($this->readMarker());
    }

    /**
     * KUSTOM-129 follow-up: same quote with the required company filled in is accepted
     *
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testLoggedInCustomerWithNewAddressWithCompanyAnswers200(): void
    {
        $this->useUnsavedQuoteAddresses('CompanyName');
        // Required only after the quote is saved, so the fixture setup is not affected by the rule; the test relies
        // on the validate callback checking the addresses as they are stored on the quote.
        $this->requireCompanyAttribute();

        $this->dispatchValidate('{"order_id":"' . self::KLARNA_ORDER_ID . '"}');

        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
        $this->assertStringNotContainsString('address_error', $this->getResponse()->getBody());
        $this->assertNotNull($this->readMarker());
    }

    /**
     * KUSTOM-129 follow-up with the real address import (FullUpdate, module-base Address Handler)
     *
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testLoggedInCustomerRealImportWithoutCompanyAnswers400(): void
    {
        $handler = $this->_objectManager->create(Handler::class, [
            'validations' => ['customer_address' => $this->_objectManager->create(CustomerAddress::class)],
        ]);
        $this->_objectManager->addSharedInstance($handler, Handler::class);
        $this->requireCompanyAttribute();
        $address = [
            'given_name' => 'John',
            'family_name' => 'Smith',
            'email' => 'any_mail@mail.me',
            'street_address' => 'Green str, 67',
            'postal_code' => '36104',
            'city' => 'CityM',
            'region' => 'CA',
            'country' => 'US',
            'phone' => '3468676',
        ];

        $this->dispatchValidate(json_encode([
            'order_id' => self::KLARNA_ORDER_ID,
            'billing_address' => $address,
            'shipping_address' => $address,
        ]));

        $this->assertEquals(400, $this->getResponse()->getHttpResponseCode());
        $this->assertStringContainsString('address_error', $this->getResponse()->getBody());
        $this->assertNull($this->readMarker());
    }

    /**
     * KUSTOM-129 follow-up for a guest: valid billing address, shipping address without the required company
     *
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store payment/klarna_kco/active 1
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testGuestRealImportWithShippingWithoutCompanyAnswers400(): void
    {
        $quote = $this->_objectManager->create(GetQuoteByReservedOrderId::class)->execute('100000001');
        $quote->setCustomerId(null)->setCustomerIsGuest(true)->setCheckoutMethod('guest')
            ->setCustomerEmail('guest@example.com');
        $this->_objectManager->get(CartRepositoryInterface::class)->save($quote);
        $handler = $this->_objectManager->create(Handler::class, [
            'validations' => ['customer_address' => $this->_objectManager->create(CustomerAddress::class)],
        ]);
        $this->_objectManager->addSharedInstance($handler, Handler::class);
        $this->requireCompanyAttribute();
        $address = [
            'given_name' => 'John',
            'family_name' => 'Smith',
            'email' => 'any_mail@mail.me',
            'street_address' => 'Green str, 67',
            'postal_code' => '36104',
            'city' => 'CityM',
            'region' => 'CA',
            'country' => 'US',
            'phone' => '3468676',
        ];

        $this->dispatchValidate(json_encode([
            'order_id' => self::KLARNA_ORDER_ID,
            'billing_address' => $address + ['organization_name' => 'CompanyName'],
            'shipping_address' => $address,
        ]));

        $this->assertEquals(400, $this->getResponse()->getHttpResponseCode());
        $this->assertStringContainsString('address_error', $this->getResponse()->getBody());
        $this->assertNull($this->readMarker());
    }

    /**
     * Same effect as customer/address/company_show = req, which the admin save stores on the EAV attribute
     *
     * @return void
     */
    private function requireCompanyAttribute(): void
    {
        $this->originalCompanyRequired = (bool) $this->_objectManager->get(EavConfig::class)
            ->getAttribute('customer_address', 'company')->getIsRequired();
        $this->setCompanyRequired(true);
    }

    /**
     * Saves is_required on the attribute and cleans the EAV caches
     *
     * @param bool $required
     * @return void
     */
    private function setCompanyRequired(bool $required): void
    {
        $eavConfig = $this->_objectManager->get(EavConfig::class);
        $attribute = $eavConfig->getAttribute('customer_address', 'company');
        $attribute->setIsRequired($required);
        $attribute->save();
        $eavConfig->clear();
        $this->_objectManager->get(CacheManager::class)->clean(['eav', 'config']);
    }

    /**
     * Real address validation on the fixture quote of a logged-in customer whose billing and shipping address were
     * typed in the checkout: no customer_address_id, so only the submit address rules can reject them.
     *
     * @param string $company
     * @return void
     */
    private function useUnsavedQuoteAddresses(string $company): void
    {
        $quote = $this->_objectManager->create(GetQuoteByReservedOrderId::class)->execute('100000001');
        $this->assertNotEmpty($quote->getCustomerId());

        foreach ([$quote->getBillingAddress(), $quote->getShippingAddress()] as $address) {
            $address->setCustomerAddressId(null)->setCompany($company);
        }
        $this->_objectManager->get(CartRepositoryInterface::class)->save($quote);

        $handler = $this->_objectManager->create(Handler::class, [
            'fullUpdate' => $this->createMock(FullUpdate::class),
            'validations' => ['customer_address' => $this->_objectManager->create(CustomerAddress::class)],
        ]);
        $this->_objectManager->addSharedInstance($handler, Handler::class);
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
