<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Model\Checkout\Checkbox;

use Klarna\Kco\Model\Checkout\Checkbox\DataProvider;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class DataProviderTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var Quote
     */
    private $quote;

    /**
     * @var DataProvider
     */
    private $model;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->quote = $this->objectManager->create(Quote::class);
        $this->model = $this->objectManager->create(DataProvider::class);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture current_store checkout/klarna_kco/custom_checkboxes
     */
    public function testGetAdditionalCheckboxesShouldNotCrashWithNoConfig(): void
    {
        $expectedCheckboxes = [];
        $checkboxes = $this->model->getAdditionalCheckboxes($this->quote);
        $this->assertEquals($expectedCheckboxes, $checkboxes);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture current_store checkout/klarna_kco/custom_checkboxes {"0":{"id":"ABC","checked":"1","required":"1","text":"Checkbox 1"},"1":{"id":"BCD","checked":"0","required":"0","text":"Checkbox 2"}}
     */
    public function testGetAdditionalCheckboxesShouldReturnDataBasedOnConfig(): void
    {
        $expectedCheckboxes = [
            [
                'id' => 'ABC',
                'checked' => true,
                'required' => true,
                'text' => 'Checkbox 1',
            ],
            [
                'id' => 'BCD',
                'checked' => false,
                'required' => false,
                'text' => 'Checkbox 2',
            ],
        ];

        $checkboxes = $this->model->getAdditionalCheckboxes($this->quote);
        $this->assertEquals($expectedCheckboxes, $checkboxes);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getMerchantCheckboxTextDataProvider')]
    /**
     * @dataProvider getMerchantCheckboxTextDataProvider
     * @magentoAppIsolation enabled
     *
     * @param string $code
     * @param string $expectedText
     *
     * @return void
     */
    public function testGetMerchantCheckboxText(string $code, string $expectedText): void
    {
        $text = $this->model->getMerchantCheckboxText($code);
        $this->assertEquals($expectedText, $text);
    }

    /**
     * @return mixed[]
     */
    public static function getMerchantCheckboxTextDataProvider(): array
    {
        return [
            'should return empty text with empty code' => [
                'code' => '',
                'expectedText' => '',
            ],
            'should return empty text with -1' => [
                'code' => '-1',
                'expectedText' => '',
            ],
            'should return text related to newsletter_signup' => [
                'code' => 'newsletter_signup',
                'expectedText' => 'Signup to our newsletter',
            ],
        ];
    }
}
