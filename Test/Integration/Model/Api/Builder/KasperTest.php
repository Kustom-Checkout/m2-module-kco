<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Model\Api\Builder;

use Klarna\Kco\Model\Api\Builder\Kasper;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class KasperTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var Kasper
     */
    private $model;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->model = $this->objectManager->create(Kasper::class);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store checkout/klarna_kco/separate_address 1
     * @magentoConfigFixture current_store checkout/klarna_kco/phone_mandatory 1
     * @magentoConfigFixture current_store checkout/klarna_kco/national_identification_number_mandatory 1
     * @magentoConfigFixture current_store checkout/klarna_kco/dob_mandatory 1
     * @magentoConfigFixture current_store checkout/klarna_kco/title_mandatory 1
     * @magentoConfigFixture current_store checkout/klarna_kco/shipping_in_iframe 1
     * @magentoConfigFixture current_store checkout/klarna_kco/use_full_checkout 1
     */
    public function testGetOptionsWithAllConfigsTurnedOn(): void
    {
        $expectedOptions = [
            'allow_separate_shipping_address' => true,
            'phone_mandatory' => true,
            'national_identification_number_mandatory' => true,
            'date_of_birth_mandatory' => true,
            'require_validate_callback_success' => true,
            'title_mandatory' => true,
            'shipping_in_iframe' => true,
            'full_checkout' => true,
        ];

        /** @var Quote $quote */
        $quote = $this->objectManager->create(Quote::class);
        $options = $this->model->getOptions($quote);
        $this->assertEquals($expectedOptions, $options);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store checkout/klarna_kco/separate_address 0
     * @magentoConfigFixture current_store checkout/klarna_kco/phone_mandatory 0
     * @magentoConfigFixture current_store checkout/klarna_kco/national_identification_number_mandatory 0
     * @magentoConfigFixture current_store checkout/klarna_kco/dob_mandatory 0
     * @magentoConfigFixture current_store checkout/klarna_kco/title_mandatory 0
     * @magentoConfigFixture current_store checkout/klarna_kco/shipping_in_iframe 0
     * @magentoConfigFixture current_store checkout/klarna_kco/use_full_checkout 0
     */
    public function testGetOptionsWithAllConfigsTurnedOff(): void
    {
        $expectedOptions = [
            'allow_separate_shipping_address' => false,
            'phone_mandatory' => false,
            'national_identification_number_mandatory' => false,
            'date_of_birth_mandatory' => false,
            'require_validate_callback_success' => true, // Appears to be hardcoded
            'title_mandatory' => false,
            'shipping_in_iframe' => false,
            'full_checkout' => false,
        ];

        /** @var Quote $quote */
        $quote = $this->objectManager->create(Quote::class);
        $options = $this->model->getOptions($quote);
        $this->assertEquals($expectedOptions, $options);
    }
}
