<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Model\Cart\Validations;

use Klarna\Base\Exception;
use Klarna\Kco\Model\Cart\Validations\OrderItems;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderItemsTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var DataObject
     */
    private $request;

    /**
     * @var GetQuoteByReservedOrderId
     */
    private $quoteGetter;

    /**
     * @var OrderItems
     */
    private $validator;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->request = $this->objectManager->create(DataObject::class);
        $this->quoteGetter = $this->objectManager->create(GetQuoteByReservedOrderId::class);
        $this->validator = $this->objectManager->create(OrderItems::class);
    }

    #[DataProvider('validateDataProvider')]
    /**
     * @dataProvider validateDataProvider
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     * @param mixed[] $requestData
     * @param string|null $expectedException
     * @param string|null $expectedMessage
     */
    public function testValidate(
        array $requestData,
        ?string $expectedException = null,
        ?string $expectedMessage = null
    ): void {
        $quoteId = '100000001';
        $quote = $this->quoteGetter->execute($quoteId);

        $this->request->setData($requestData);

        if ($expectedException && $expectedMessage) {
            $this->expectException($expectedException);
            $this->expectExceptionMessageMatches($expectedMessage);
        }

        $this->validator->validate($this->request, $quote);
    }

    /**
     * @return mixed[]
     */
    public static function validateDataProvider(): array
    {
        return [
            'should successfully pass validation with correct name, url and quantity data' => [
                'requestData' => [
                    'order_lines' => [
                        [
                            'name' => 'Simple Product',
                            'product_url' => 'http://localhost/index.php/simple-product.html',
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
            'should throw error due to name mismatch' => [
                'requestData' => [
                    'order_lines' => [
                        [
                            'name' => 'Name Mismatch',
                            'product_url' => 'http://localhost/index.php/simple-product.html',
                            'quantity' => 1,
                        ],
                    ],
                ],
                'expectedException' => Exception::class,
                'expectedMessage' => '/Order items do not match/',
            ],
            'should throw error due to url mismatch' => [
                'requestData' => [
                    'order_lines' => [
                        [
                            'name' => 'Simple Product',
                            'product_url' => 'http://localhost/index.php/name-mismatch.html',
                            'quantity' => 1,
                        ],
                    ],
                ],
                'expectedException' => Exception::class,
                'expectedMessage' => '/Order items do not match/',
            ],
            'should throw error due to quantity mismatch' => [
                'requestData' => [
                    'order_lines' => [
                        [
                            'name' => 'Simple Product',
                            'product_url' => 'http://localhost/index.php/simple-product.html',
                            'quantity' => 2,
                        ],
                    ],
                ],
                'expectedException' => Exception::class,
                'expectedMessage' => '/Order items do not match/',
            ],
            'should not throw error due to trailing space in name' => [
                'requestData' => [
                    'order_lines' => [
                        [
                            'name' => 'Simple Product ',
                            'product_url' => 'http://localhost/index.php/simple-product.html',
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
            'should not throw error due to leading space in name' => [
                'requestData' => [
                    'order_lines' => [
                        [
                            'name' => ' Simple Product',
                            'product_url' => 'http://localhost/index.php/simple-product.html',
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
            'should not throw error due to string format quantity of same value' => [
                'requestData' => [
                    'order_lines' => [
                        [
                            'name' => 'Simple Product ',
                            'product_url' => 'http://localhost/index.php/simple-product.html',
                            'quantity' => '1.0000',
                        ],
                    ],
                ],
            ],
            'should not crash due to null name but throw the usual exception' => [
                'requestData' => [
                    'order_lines' => [
                        [
                            'name' => null,
                            'product_url' => 'http://localhost/index.php/simple-product.html',
                            'quantity' => '1.0000',
                        ],
                    ],
                ],
                'expectedException' => Exception::class,
                'expectedMessage' => '/Order items do not match/',
            ],
        ];
    }
}
