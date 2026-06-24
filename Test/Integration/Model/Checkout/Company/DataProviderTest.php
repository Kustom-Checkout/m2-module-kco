<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Model\Checkout\Company;

use Klarna\Kco\Model\Checkout\Company\DataProvider;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class DataProviderTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var DataProvider
     */
    private $dataProvider;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->dataObjectFactory = $this->objectManager->create(DataObjectFactory::class);
        $this->storeManager = $this->objectManager->create(StoreManagerInterface::class);
        $this->dataProvider = $this->objectManager->create(DataProvider::class);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture current_store checkout/klarna_kco/business_id_attribute test
     */
    public function testGetStoreCompanyIdAttributeCodeShouldReturnValueFromConfig(): void
    {
        $expectedValue = 'test';
        $store = $this->storeManager->getStore();
        $value = $this->dataProvider->getStoreCompanyIdAttributeCode($store);
        $this->assertEquals($expectedValue, $value);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetStoreCompanyIdAttributeCodeShouldReturnEmptyStringWhenConfigNotSet(): void
    {
        $expectedValue = '';
        $store = $this->storeManager->getStore();
        $value = $this->dataProvider->getStoreCompanyIdAttributeCode($store);
        $this->assertEquals($expectedValue, $value);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetKlarnaRequestCompanyIdShouldReturnTheRightValueWhenSetOnRequestAsString(): void
    {
        $expectedValue = '1234';
        $request = $this->dataObjectFactory->create([
            'data' => [
                'customer' => [
                    'organization_registration_id' => '1234',
                ],
            ],
        ]);
        $value = $this->dataProvider->getKlarnaRequestCompanyId($request);
        $this->assertEquals($expectedValue, $value);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetKlarnaRequestCompanyIdShouldReturnTheRightValueWhenSetOnRequestAsInteger(): void
    {
        $expectedValue = '1234';
        $request = $this->dataObjectFactory->create([
            'data' => [
                'customer' => [
                    'organization_registration_id' => 1234,
                ],
            ],
        ]);
        $value = $this->dataProvider->getKlarnaRequestCompanyId($request);
        $this->assertEquals($expectedValue, $value);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetKlarnaRequestCompanyIdShouldReturnEmptyStringWhenValueInRequestIsNull(): void
    {
        $expectedValue = '';
        $request = $this->dataObjectFactory->create([
            'data' => [
                'customer' => [
                    'organization_registration_id' => null,
                ],
            ],
        ]);
        $value = $this->dataProvider->getKlarnaRequestCompanyId($request);
        $this->assertEquals($expectedValue, $value);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetKlarnaRequestCompanyIdShouldReturnEmptyStringWhenNoDataIsThereInRequest(): void
    {
        $expectedValue = '';
        $request = $this->dataObjectFactory->create([
            'data' => [
                'customer' => [],
            ],
        ]);
        $value = $this->dataProvider->getKlarnaRequestCompanyId($request);
        $this->assertEquals($expectedValue, $value);
    }
}
