<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Model\Cart\Validations;

use Klarna\Kco\Exception\AddressValidationException;
use Klarna\Kco\Model\Cart\Validations\CustomerAddress;
use Klarna\Kco\Model\Cart\Validations\Handler;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use PHPUnit\Framework\TestCase;

class CustomerAddressTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var CustomerAddress
     */
    private $validator;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->validator = $this->objectManager->create(CustomerAddress::class);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testHandlerHasCustomerAddressValidationWired(): void
    {
        $handler = $this->objectManager->create(Handler::class);
        $property = new \ReflectionProperty(Handler::class, 'validations');

        static::assertInstanceOf(CustomerAddress::class, $property->getValue($handler)['customer_address']);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testValidateAcceptsValidGuestBillingAddress(): void
    {
        $quote = $this->getQuote();
        $quote->getBillingAddress()->setCity('Stockholm');

        $this->validator->validate(new DataObject(), $quote);

        static::assertSame('Stockholm', $quote->getBillingAddress()->getCity());
    }

    /**
     * Magento 2.4.8+ validates guest billing addresses at submit, older versions do not: we must mirror that.
     *
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testValidateMirrorsMagentoForInvalidCity(): void
    {
        $quote = $this->getQuote();
        $quote->getBillingAddress()->setCity('Lidingö #1');
        $version = $this->objectManager->get(ProductMetadataInterface::class)->getVersion();

        if (version_compare($version, '2.4.8', '>=')) {
            $this->expectException(AddressValidationException::class);
        }

        $this->validator->validate(new DataObject(), $quote);

        static::assertTrue(true);
    }

    private function getQuote()
    {
        $quote = $this->objectManager->create(GetQuoteByReservedOrderId::class)->execute('100000001');
        $quote->setCustomerId(null);
        $quote->setCustomerIsGuest(true);
        $quote->setCheckoutMethod('guest');

        return $quote;
    }
}
