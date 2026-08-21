<?php
/**
 * Copyright 2025 Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Checkout;

use Klarna\AdminSettings\Model\Configurations\Kco\Checkout;
use Klarna\Kco\Model\Checkout\B2b;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covering the handling of an unselected business id attribute which crashed the company checkout
 *
 * @coversDefaultClass \Klarna\Kco\Model\Checkout\B2b
 */
class B2bTest extends TestCase
{
    /**
     * @var Checkout|MockObject
     */
    private $checkoutConfiguration;

    /**
     * @var CustomerRepositoryInterface|MockObject
     */
    private $customerRepository;

    /**
     * @var B2b
     */
    private B2b $model;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->checkoutConfiguration = $this->createMock(Checkout::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);

        $this->model = new B2b(
            $this->checkoutConfiguration,
            $this->customerRepository,
            $this->createMock(AddressRepositoryInterface::class)
        );
    }

    /**
     * @return array
     */
    public static function unselectedAttributeDataProvider(): array
    {
        return [
            'please select option' => ['0'],
            'empty string'         => [''],
            'only whitespace'      => ['   ']
        ];
    }

    /**
     * @dataProvider unselectedAttributeDataProvider
     * @param string $configuredValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unselectedAttributeDataProvider')]
    public function testUnselectedAttributeIsTreatedAsEmpty(string $configuredValue): void
    {
        $this->checkoutConfiguration->method('getBusinessIdAttribute')->willReturn($configuredValue);
        $this->customerRepository->expects($this->never())->method('getById');

        $store = $this->createMock(StoreInterface::class);

        $this->assertSame('', $this->model->getBusinessIdAttributeCode($store));
        $this->assertFalse($this->model->getBusinessIdAttributeValue('1', $store));
    }

    /**
     * A configured attribute code must still be forwarded untouched
     */
    public function testConfiguredAttributeCodeIsReturned(): void
    {
        $this->checkoutConfiguration->method('getBusinessIdAttribute')->willReturn('organization_id');

        $this->assertSame(
            'organization_id',
            $this->model->getBusinessIdAttributeCode($this->createMock(StoreInterface::class))
        );
    }
}

