<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model;

use Klarna\Kco\Model\CustomerToken;
use Klarna\Kco\Model\CustomerTokenFactory;
use Klarna\Kco\Model\CustomerTokenRepository;
use Klarna\Kco\Model\ResourceModel\CustomerToken as CustomerTokenResource;
use Klarna\Kco\Model\ResourceModel\CustomerToken\CollectionFactory;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\CustomerTokenRepository
 */
class CustomerTokenRepositoryTest extends TestCase
{
    /**
     * @var CustomerTokenResource|MockObject
     */
    private $resource;
    /**
     * @var CustomerTokenRepository
     */
    private $model;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(CustomerTokenResource::class);
        $factory = $this->createMock(CustomerTokenFactory::class);
        $factory->method('create')->willReturnCallback(fn(): CustomerToken => $this->newToken());
        $this->model = new CustomerTokenRepository(
            $factory,
            $this->resource,
            $this->createMock(CollectionFactory::class)
        );
    }

    /**
     * @covers ::save
     */
    public function testSaveWrapsDuplicateWithoutLeakingTheToken(): void
    {
        $this->resource->method('save')->willThrowException(new DuplicateException('secret-token SQL'));
        $token = ($this->newToken())->setToken('secret-token')->setKustomOrderId('ko-1');

        try {
            $this->model->save($token);
            static::fail('Expected exception');
        } catch (CouldNotSaveException $e) {
            static::assertStringNotContainsString('secret-token', $e->getMessage());
            static::assertNull($e->getPrevious());
        }
    }

    /**
     * @covers ::saveIfNotExists
     */
    public function testSaveIfNotExistsDoesNotSaveWhenFound(): void
    {
        $this->resource->method('load')->willReturnCallback(
            function (CustomerToken $object): CustomerTokenResource {
                $object->setData(['entity_id' => 5, 'kustom_order_id' => 'ko-1']);
                return $this->resource;
            }
        );
        $this->resource->expects($this->never())->method('save');

        $result = $this->model->saveIfNotExists(($this->newToken())->setKustomOrderId('ko-1'));

        static::assertSame(5, $result->getEntityId());
    }

    /**
     * @covers ::saveIfNotExists
     */
    public function testSaveIfNotExistsReturnsExistingRowOnRace(): void
    {
        $loads = 0;
        $this->resource->method('load')->willReturnCallback(
            function (CustomerToken $object) use (&$loads): CustomerTokenResource {
                if (++$loads > 1) {
                    $object->setData(['entity_id' => 9, 'kustom_order_id' => 'ko-1']);
                }
                return $this->resource;
            }
        );
        $this->resource->expects($this->once())->method('save')->willThrowException(new DuplicateException('dup'));

        $result = $this->model->saveIfNotExists(($this->newToken())->setKustomOrderId('ko-1'));

        static::assertSame(9, $result->getEntityId());
    }

    /**
     * @covers ::getByKustomOrderId
     */
    public function testGetByKustomOrderIdThrowsWhenMissing(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->model->getByKustomOrderId('missing');
    }

    /**
     * @covers ::getListByOrderIds
     */
    public function testGetListByOrderIdsWithEmptyInputReturnsEmptyArray(): void
    {
        static::assertSame([], $this->model->getListByOrderIds([]));
    }

    private function newToken(): CustomerToken
    {
        $resource = $this->createStub(CustomerTokenResource::class);
        $resource->method('getIdFieldName')->willReturn('entity_id');
        // The real _construct() would look up the resource model through the ObjectManager.
        return new class (
            $this->createStub(Context::class),
            $this->createStub(Registry::class),
            $resource
        ) extends CustomerToken {
            // phpcs:ignore
            protected function _construct(): void
            {
            }
        };
    }
}
