<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

namespace Klarna\Kco\Test\Unit\Model\Api\Rest\Service;

use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Klarna\Base\Test\Unit\Mock\MockFactory;
use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Api\Rest\Service\Checkout
 */
class CheckoutTest extends TestCase
{
    /**
     * @var Checkout
     */
    private $model;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject[]
     */
    private $dependencyMocks;

    /**
     * @covers ::createOrder
     */
    public function testCreateOrderMakesRequest()
    {
        $this->dependencyMocks['service']->expects(static::once())
            ->method('makeRequest');
        $this->model->createOrder([], 'EUR');
    }

    /**
     * @covers ::updateOrder
     */
    public function testUpdateOrderWithValidId()
    {
        $this->dependencyMocks['service']->expects(static::once())
            ->method('makeRequest');
        $this->model->updateOrder(1, [], 'EUR');
    }

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $mockFactory = new MockFactory($this);
        $objectFactory = new TestObjectFactory('');

        $this->model = $objectFactory->create(Checkout::class);
        $this->dependencyMocks = $objectFactory->getDependencyMocks();

        $store = $mockFactory->create(\Magento\Store\Model\Store::class);
        $this->dependencyMocks['storeManager']->method('getStore')
            ->willReturn($store);
    }
}
