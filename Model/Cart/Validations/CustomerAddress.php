<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Cart\Validations;

use Klarna\Kco\Api\CheckoutValidationInterface;
use Klarna\Kco\Exception\AddressValidationException;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\DataObject;
use Magento\Framework\Validator\Exception as ValidatorException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\CustomerManagement;
use Magento\Quote\Model\Quote;

/**
 * Validating the billing address with the same checks Magento applies when the order is placed
 *
 * @internal
 */
class CustomerAddress implements CheckoutValidationInterface
{
    /**
     * @var CustomerManagement
     */
    private CustomerManagement $customerManagement;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param CustomerManagement $customerManagement
     * @param LoggerInterface $logger
     * @codeCoverageIgnore
     */
    public function __construct(CustomerManagement $customerManagement, LoggerInterface $logger)
    {
        $this->customerManagement = $customerManagement;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     *
     * @throws AddressValidationException
     */
    public function validate(DataObject $request, CartInterface $quote): void
    {
        if (!$quote instanceof Quote || !$quote->getBillingAddress()->getCountryId()) {
            $this->logger->debug('Validate: address check skipped, no billing address country');
            return;
        }

        // The guest flag is normally set when the order is placed. The clone is never saved.
        $probe = clone $quote;
        if ($quote->getCheckoutMethod() === Onepage::METHOD_GUEST || !$quote->getCustomerId()) {
            $probe->setCustomerIsGuest(true);
        }

        try {
            $this->customerManagement->validateAddresses($probe);
            $this->logger->info('Validate: address check passed');
        } catch (ValidatorException $e) {
            $this->logger->warning(
                'Validate: Magento rejects the billing address, validation errors: ' . count($e->getMessages())
                . ', fields: ' . implode(', ', $this->getFailedFields($e))
            );
            throw new AddressValidationException(
                __('We could not use this address. Please check the billing address and try again.')
            );
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Validate: Address check skipped, ' . get_class($e) . ': ' . $e->getMessage()
            );
        }
    }

    /**
     * Getting back the names of the failed fields, never the submitted values
     *
     * Only fixed message texts are read: `"<field>" is required` and `Invalid <Field>.` (Magento's
     * city, street and telephone validators). Anything else, e.g. a translated message, is reported as `other`.
     *
     * @param ValidatorException $e
     * @return string[]
     */
    private function getFailedFields(ValidatorException $e): array
    {
        $fields = [];
        foreach ($e->getMessages() as $message) {
            $text = (string) $message->getText();
            if (preg_match('/^"([A-Za-z_]+)" is required/', $text, $match)
                || preg_match('/^Invalid ([A-Za-z ]+?)\./', $text, $match)
            ) {
                $fields[] = $match[1];
                continue;
            }
            $fields[] = 'other';
        }

        return array_values(array_unique($fields)) ?: ['unknown'];
    }
}
