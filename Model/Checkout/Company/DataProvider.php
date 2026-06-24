<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Checkout\Company;

use Klarna\Kco\Model\Payment\Kco as PaymentKco;
use Magento\Framework\DataObject;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * @internal
 */
class DataProvider
{
    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @codeCoverageIgnore
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Getting back the store company ID attribute code
     *
     * @param StoreInterface $store
     * @return string
     */
    public function getStoreCompanyIdAttributeCode(StoreInterface $store): string
    {
        return (string) $this->scopeConfig->getValue(
            'checkout/' . PaymentKco::METHOD_CODE . '/business_id_attribute',
            ScopeInterface::SCOPE_STORES,
            $store
        );
    }

    /**
     * Getting back the Klarna request company ID
     *
     * @param DataObject $request
     * @return string
     */
    public function getKlarnaRequestCompanyId(DataObject $request): string
    {
        $customer = $request->getCustomer();

        return (string) ($customer['organization_registration_id'] ?? '');
    }
}
