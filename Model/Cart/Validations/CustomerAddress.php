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
use Magento\Framework\Phrase;
use Magento\Framework\Validator\Exception as ValidatorException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\CustomerManagement;
use Magento\Quote\Model\Quote;

/**
 * Validating the billing and shipping address with the same checks Magento applies when the order is placed
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

        // The guest flag is normally set when the order is placed. The clone is shallow and never saved: the
        // addresses are shared with the live quote, so the store id set below also lands on them (as in Magento).
        $probe = clone $quote;
        if ($quote->getCheckoutMethod() === Onepage::METHOD_GUEST || !$quote->getCustomerId()) {
            $probe->setCustomerIsGuest(true);
        }

        try {
            $this->customerManagement->validateAddresses($probe);
            $this->validateSubmitRules($probe);
            $this->logger->info('Validate: address check passed');
        } catch (ValidatorException $e) {
            $this->rejectAddress($e->getMessages());
        } catch (AddressValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Validate: Address check skipped, ' . get_class($e) . ': ' . $e->getMessage()
            );
        }
    }

    /**
     * The address rules Magento applies on submit (billing, and shipping unless the quote is virtual)
     *
     * Needed because CustomerManagement only checks saved customer addresses or the guest billing address,
     * so a logged-in customer with an address typed in the checkout is otherwise never validated.
     *
     * @param Quote $probe
     * @return void
     * @throws AddressValidationException
     */
    private function validateSubmitRules(Quote $probe): void
    {
        $errors = [];
        $billingAddress = $probe->getBillingAddress();
        $billingAddress->setStoreId($probe->getStoreId());
        $result = $billingAddress->validate();
        if (is_array($result)) {
            $errors = $result;
        }

        if (!$probe->isVirtual()) {
            $shippingAddress = $probe->getShippingAddress();
            $shippingAddress->setStoreId($probe->getStoreId());
            $result = $shippingAddress->validate();
            if (is_array($result)) {
                $errors = array_merge($errors, $result);
            }
        }

        if ($errors !== []) {
            $this->rejectAddress($errors);
        }
    }

    /**
     * Logging the failed field names and rejecting the address
     *
     * @param array $messages
     * @return void
     * @throws AddressValidationException
     */
    private function rejectAddress(array $messages): void
    {
        $this->logger->warning(
            'Validate: Magento rejects the address, validation errors: ' . count($messages)
            . ', fields: ' . implode(', ', $this->getFailedFields($messages))
        );
        throw new AddressValidationException(
            __('We could not use this address. Please check your address and try again.')
        );
    }

    /**
     * Getting back the names of the failed fields, never the submitted values
     *
     * Only fixed message texts are read: `"<field>" is required` and `Invalid <Field>.` (Magento's
     * city, street and telephone validators). Anything else, e.g. a translated message, is reported as `other`.
     *
     * @param array $messages Phrase or string items, or message objects with getText()
     * @return string[]
     */
    private function getFailedFields(array $messages): array
    {
        $fields = [];
        foreach ($messages as $message) {
            $text = !$message instanceof Phrase && is_object($message) && method_exists($message, 'getText')
                ? (string) $message->getText()
                : (string) $message;
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
