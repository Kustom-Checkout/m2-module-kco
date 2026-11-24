<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Cart\Validations;

use Klarna\Kco\Exception\AddressValidationException;
use Klarna\Kco\Model\Cart\Validations\CustomerAddress;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\DataObject;
use Magento\Framework\Validator\Exception as ValidatorException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\CustomerManagement;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Cart\Validations\CustomerAddress
 */
class CustomerAddressTest extends TestCase
{
    /**
     * @var CustomerAddress
     */
    private CustomerAddress $model;
    /**
     * @var CustomerManagement|MockObject
     */
    private $customerManagement;
    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    protected function setUp(): void
    {
        $this->customerManagement = $this->createMock(CustomerManagement::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->model = new CustomerAddress($this->customerManagement, $this->logger);
    }

    /**
     * @param string|null $country
     * @param int|null $customerId
     * @param string|null $checkoutMethod
     * @return Quote|MockObject
     */
    private function createQuote(?string $country, ?int $customerId, ?string $checkoutMethod = null)
    {
        $address = $this->createPartialMock(Address::class, ['getCountryId']);
        $address->method('getCountryId')->willReturn($country);
        $quote = $this->createPartialMock(Quote::class, ['getBillingAddress', 'getCheckoutMethod']);
        $quote->method('getBillingAddress')->willReturn($address);
        $quote->setData('customer_id', $customerId);
        $quote->method('getCheckoutMethod')->willReturn($checkoutMethod);

        return $quote;
    }

    /**
     * @covers ::validate
     */
    public function testValidatePassesWhenMagentoAcceptsTheAddress(): void
    {
        $this->customerManagement->expects(static::once())->method('validateAddresses');
        $this->logger->expects(static::never())->method('warning');
        $this->logger->expects(static::once())->method('info')->with('Validate: address check passed');

        $this->model->validate(new DataObject(), $this->createQuote('SE', null));
    }

    /**
     * @covers ::validate
     */
    public function testValidateMapsValidatorExceptionToAddressValidationException(): void
    {
        $this->customerManagement->method('validateAddresses')
            ->willThrowException(new ValidatorException(null, null, ['city' => [__('Invalid City: secret value')]]));
        $this->logger->expects(static::once())->method('warning')
            ->with(static::callback(
                fn (string $m): bool => str_contains($m, 'errors: 1, fields: other') && !str_contains($m, 'secret')
            ));
        $this->logger->expects(static::never())->method('info');

        try {
            $this->model->validate(new DataObject(), $this->createQuote('SE', null));
            static::fail('AddressValidationException expected');
        } catch (AddressValidationException $e) {
            static::assertSame(
                'We could not use this address. Please check the billing address and try again.',
                $e->getMessage()
            );
            static::assertStringNotContainsString('secret', $e->getMessage());
        }
    }

    /**
     * @covers ::validate
     * @covers ::getFailedFields
     */
    public function testValidateLogsOnlyTheFailedFieldNames(): void
    {
        $this->customerManagement->method('validateAddresses')
            ->willThrowException(new ValidatorException(null, null, [
                'city' => [__('Invalid City. Please use letters, numbers, spaces.')],
                'company' => [__('"%fieldName" is required. Enter and try again.', ['fieldName' => 'company'])],
                'other' => [__('Invalid value of "%1" provided for the city field.', 'secret value')],
            ]));
        $this->logger->expects(static::once())->method('warning')
            ->with(static::callback(
                fn (string $m): bool => str_contains($m, 'errors: 3, fields: City, company, other')
                    && !str_contains($m, 'secret')
            ));

        $this->expectException(AddressValidationException::class);
        $this->model->validate(new DataObject(), $this->createQuote('SE', null));
    }

    /**
     * @covers ::validate
     */
    public function testValidateLogsAndFailsOpenOnOtherExceptions(): void
    {
        $this->customerManagement->method('validateAddresses')->willThrowException(new \RuntimeException('boom'));
        $this->logger->expects(static::once())->method('warning');

        $this->model->validate(new DataObject(), $this->createQuote('SE', null));
        static::assertTrue(true);
    }

    /**
     * @covers ::validate
     */
    public function testValidateIsNoopWithoutBillingCountry(): void
    {
        $this->customerManagement->expects(static::never())->method('validateAddresses');
        $this->logger->expects(static::once())->method('debug')
            ->with('Validate: address check skipped, no billing address country');

        $this->model->validate(new DataObject(), $this->createQuote(null, null));
    }

    /**
     * @covers ::validate
     */
    public function testValidateIsNoopForNonMagentoQuote(): void
    {
        $this->customerManagement->expects(static::never())->method('validateAddresses');

        $this->model->validate(new DataObject(), $this->createMock(CartInterface::class));
    }

    /**
     * @covers ::validate
     */
    public function testValidateMarksCloneAsGuestWithoutCustomerAndLeavesQuoteUntouched(): void
    {
        $quote = $this->createQuote('SE', null);
        $probeGuest = null;
        $this->customerManagement->expects(static::once())->method('validateAddresses')
            ->willReturnCallback(function (Quote $probe) use ($quote, &$probeGuest): void {
                static::assertNotSame($quote, $probe);
                $probeGuest = $probe->getCustomerIsGuest();
            });

        $this->model->validate(new DataObject(), $quote);

        static::assertTrue($probeGuest);
        static::assertNull($quote->getCustomerIsGuest());
    }

    /**
     * @covers ::validate
     */
    public function testValidateMarksCloneAsGuestForGuestCheckoutMethod(): void
    {
        $quote = $this->createQuote('SE', null, Onepage::METHOD_GUEST);
        $probeGuest = null;
        $this->customerManagement->method('validateAddresses')
            ->willReturnCallback(function (Quote $probe) use (&$probeGuest): void {
                $probeGuest = $probe->getCustomerIsGuest();
            });

        $this->model->validate(new DataObject(), $quote);

        static::assertTrue($probeGuest);
        static::assertNull($quote->getCustomerIsGuest());
    }

    /**
     * @covers ::validate
     */
    public function testValidateDoesNotMarkCloneAsGuestForCustomer(): void
    {
        $quote = $this->createQuote('SE', 5, 'register');
        $probeGuest = 'unset';
        $this->customerManagement->method('validateAddresses')
            ->willReturnCallback(function (Quote $probe) use (&$probeGuest): void {
                $probeGuest = $probe->getCustomerIsGuest();
            });

        $this->model->validate(new DataObject(), $quote);

        static::assertNull($probeGuest);
    }
}
