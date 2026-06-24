<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

namespace Klarna\Kco\Test\Unit\Model\Api;

use Klarna\Kco\Api\ApiInterface;
use Klarna\Kco\Model\Api\Factory;
use Klarna\Base\Test\Unit\Mock\MockFactory;
use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use Magento\Store\Model\Store;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Api\Factory
 */
class FactoryTest extends TestCase
{
    /**
     * @var Factory
     */
    private $model;

    /**
     * @var Store|\PHPUnit_Framework_MockObject_MockObject
     */
    private $storeMock;

    /**
     * @covers ::createApiInstance
     */
    public function testCreateInstanceGetBackInstance()
    {
        $instance = $this->model->createApiInstance($this->storeMock);
        static::assertTrue($instance instanceof ApiInterface);
    }

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $mockFactory = new MockFactory($this);
        $objectFactory = new TestObjectFactory('');

        $this->storeMock = $mockFactory->create(Store::class);

        $this->model = $objectFactory->create(Factory::class);
    }
}
