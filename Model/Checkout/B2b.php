<?php
/**
 * Copyright 2025 Kustom AB (Originally developed by Klarna Bank AB)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Checkout;

use Klarna\AdminSettings\Model\Configurations\Kco\Checkout;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;

/**
 * @internal
 */
class B2b
{
    /**
     * @var Checkout
     */
    private Checkout $checkoutConfiguration;
    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;
    /**
     * @var AddressRepositoryInterface
     */
    private AddressRepositoryInterface $addressRepository;

    /**
     * @param Checkout $checkoutConfiguration
     * @param CustomerRepositoryInterface $customerRepository
     * @param AddressRepositoryInterface $addressRepository
     * @codeCoverageIgnore
     */
    public function __construct(
        Checkout $checkoutConfiguration,
        CustomerRepositoryInterface $customerRepository,
        AddressRepositoryInterface $addressRepository
    ) {
        $this->checkoutConfiguration = $checkoutConfiguration;
        $this->customerRepository = $customerRepository;
        $this->addressRepository = $addressRepository;
    }

    /**
     * Check if this customer is a business customer
     *
     * @param ?string $customerId
     * @param StoreInterface $store
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isB2bCustomer(?string $customerId, StoreInterface $store): bool
    {
        if ($customerId) {
            $businessIdValue = $this->getBusinessIdAttributeValue($customerId, $store);
            $businessNameValue = $this->getCompanyNameFromAddress($customerId);
            if (!empty($businessIdValue) || !empty($businessNameValue)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get organization id value
     *
     * @param string $customerId
     * @param StoreInterface $store
     * @return bool|string
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getBusinessIdAttributeValue(string $customerId, StoreInterface $store)
    {
        $attributeCode = $this->getBusinessIdAttributeCode($store);

        /**
         * The admin setting is optional. When nothing is selected Magento stores either an empty string or the
         * string "0" (the value of the "-- Please Select --" option). Neither of them is a real customer attribute
         * code, so looking it up would raise an exception and break the whole company checkout flow.
         */
        if ($attributeCode === '') {
            return false;
        }

        $customerObj = $this->customerRepository->getById($customerId);
        $businessIdValue = $customerObj->getCustomAttribute($attributeCode);
        if ($businessIdValue) {
            return $businessIdValue->getValue();
        }
        return false;
    }

    /**
     * Getting back the configured business id attribute code, empty string when nothing is configured
     *
     * @param StoreInterface $store
     * @return string
     */
    public function getBusinessIdAttributeCode(StoreInterface $store): string
    {
        $attributeCode = trim((string)$this->checkoutConfiguration->getBusinessIdAttribute($store));

        return ($attributeCode === '0') ? '' : $attributeCode;
    }

    /**
     * Check if customer's default billing address contain company name
     *
     * @param string $customerId
     * @return bool|null|string
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getCompanyNameFromAddress(string $customerId)
    {
        $customerObj = $this->customerRepository->getById($customerId);
        $billingAddressId = $customerObj->getDefaultBilling();
        if ($billingAddressId) {
            try {
                $defaultBillingAddress = $this->addressRepository->getById($billingAddressId);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                return false;
            }

            return $defaultBillingAddress->getCompany();
        }
        return false;
    }
}
