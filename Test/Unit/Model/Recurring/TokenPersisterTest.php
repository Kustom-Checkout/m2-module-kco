<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Recurring;

use Klarna\Kco\Api\CustomerTokenRepositoryInterface;
use Klarna\Kco\Api\Data\CustomerTokenInterfaceFactory;
use Klarna\Kco\Model\CustomerToken;
use Klarna\Kco\Model\Recurring\TokenPersister;
use Klarna\Kco\Model\ResourceModel\CustomerToken as CustomerTokenResource;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Recurring\TokenPersister
 */
class TokenPersisterTest extends TestCase
{
    private const SECRET = 'tok_secret_value_123';

    /**
     * @var CustomerTokenRepositoryInterface|MockObject
     */
    private $repository;
    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;
    /**
     * @var TokenPersister
     */
    private $model;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CustomerTokenRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $factory = $this->createMock(CustomerTokenInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(fn(): CustomerToken => $this->newToken());
        $this->model = new TokenPersister($this->repository, $factory, $this->logger);
    }

    /**
     * @covers ::persist
     */
    public function testRecurringWithTokenIsSaved(): void
    {
        $saved = null;
        $this->repository->expects(static::once())->method('saveIfNotExists')
            ->willReturnCallback(function (CustomerToken $token) use (&$saved): CustomerToken {
                $saved = $token;
                return $token;
            });
        $this->logger->expects(static::never())->method('warning');

        $this->model->persist($this->checkout(true, self::SECRET), $this->order(12), 'MID_1');

        static::assertSame(self::SECRET, $saved->getToken());
        static::assertSame('K-1', $saved->getKustomOrderId());
        static::assertSame(77, $saved->getOrderId());
        static::assertSame('000000012', $saved->getIncrementId());
        static::assertSame(12, $saved->getCustomerId());
        static::assertSame(3, $saved->getStoreId());
        static::assertSame('MID_1', $saved->getMerchantId());
        static::assertSame('active', $saved->getStatus());
        static::assertNull($saved->getPaymentMethodType());
    }

    /**
     * @covers ::persist
     */
    public function testGuestHasNoCustomerId(): void
    {
        $saved = null;
        $this->repository->expects(static::once())->method('saveIfNotExists')
            ->willReturnCallback(function (CustomerToken $token) use (&$saved): CustomerToken {
                $saved = $token;
                return $token;
            });

        $this->model->persist($this->checkout(true, self::SECRET), $this->order(null), null);

        static::assertNull($saved->getCustomerId());
        static::assertNull($saved->getMerchantId());
    }

    /**
     * @covers ::persist
     */
    public function testNotRecurringDoesNothing(): void
    {
        $this->repository->expects(static::never())->method('saveIfNotExists');
        $this->logger->expects(static::never())->method(static::anything());

        $this->model->persist($this->checkout(false, null), $this->order(1), 'MID_1');
        $this->model->persist(new DataObject(['order_id' => 'K-1']), $this->order(1), 'MID_1');
    }

    /**
     * @covers ::persist
     */
    public function testRecurringWithEmptyTokenWarnsAndSavesNothing(): void
    {
        $this->repository->expects(static::never())->method('saveIfNotExists');
        $this->logger->expects(static::once())->method('warning')
            ->with(static::stringContains('K-1'));

        $this->model->persist($this->checkout(true, ''), $this->order(1), 'MID_1');
    }

    /**
     * @covers ::persist
     */
    public function testRequestedButNoTokenWarnsOnceWithIdsOnly(): void
    {
        $messages = [];
        $this->logger->method('warning')->willReturnCallback(function ($message) use (&$messages): void {
            $messages[] = (string)$message;
        });
        $this->repository->expects(static::never())->method('saveIfNotExists');

        $this->model->persist($this->checkout(true, ''), $this->order(1), 'MID_1', true);
        static::assertCount(1, $messages);
        static::assertStringContainsString('K-1', $messages[0]);
        static::assertStringContainsString('000000012', $messages[0]);
        static::assertStringContainsString('store id: 3', $messages[0]);

        $messages = [];
        $this->model->persist($this->checkout(false, null), $this->order(1), 'MID_1', true);
        static::assertCount(1, $messages);
    }

    /**
     * @covers ::persist
     */
    public function testRequestedWithTokenDoesNotWarn(): void
    {
        $this->repository->expects(static::once())->method('saveIfNotExists')
            ->willReturnCallback(fn(CustomerToken $token): CustomerToken => $token);
        $this->logger->expects(static::never())->method('warning');

        $this->model->persist($this->checkout(true, self::SECRET), $this->order(1), 'MID_1', true);
    }

    /**
     * @covers ::persist
     */
    public function testNotRequestedAndNoTokenDoesNotWarn(): void
    {
        $this->logger->expects(static::never())->method('warning');

        $this->model->persist($this->checkout(false, null), $this->order(1), 'MID_1', false);
    }

    /**
     * @covers ::persist
     */
    public function testWarningNeverContainsTheToken(): void
    {
        $messages = [];
        $this->logger->method('warning')->willReturnCallback(function ($message) use (&$messages): void {
            $messages[] = (string)$message;
        });

        $this->model->persist(
            new DataObject(['order_id' => 'K-1', 'recurring' => false, 'recurring_token' => self::SECRET]),
            $this->order(1),
            'MID_1',
            true
        );

        static::assertCount(1, $messages);
        static::assertStringNotContainsString(self::SECRET, $messages[0]);
    }

    /**
     * @covers ::persist
     */
    public function testThrowingLoggerNeverBreaksPersist(): void
    {
        $this->logger->method('warning')->willThrowException(new \RuntimeException('log down'));

        $this->model->persist($this->checkout(false, null), $this->order(1), 'MID_1', true);
        static::assertTrue(true);
    }

    /**
     * @covers ::persist
     */
    public function testRepositoryExceptionIsPropagatedWithoutLoggingTheToken(): void
    {
        $this->repository->method('saveIfNotExists')
            ->willThrowException(new CouldNotSaveException(__('The customer token could not be saved.')));
        $this->logger->expects(static::never())->method(static::anything());

        $this->expectException(CouldNotSaveException::class);
        $this->model->persist($this->checkout(true, self::SECRET), $this->order(1), 'MID_1');
    }

    private function checkout(bool $recurring, ?string $token): DataObject
    {
        return new DataObject(['order_id' => 'K-1', 'recurring' => $recurring, 'recurring_token' => $token]);
    }

    private function order(?int $customerId): OrderInterface
    {
        $order = $this->createStub(OrderInterface::class);
        $order->method('getEntityId')->willReturn(77);
        $order->method('getIncrementId')->willReturn('000000012');
        $order->method('getCustomerId')->willReturn($customerId);
        $order->method('getStoreId')->willReturn(3);
        return $order;
    }

    private function newToken(): CustomerToken
    {
        $resource = $this->createStub(CustomerTokenResource::class);
        $resource->method('getIdFieldName')->willReturn('entity_id');
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
