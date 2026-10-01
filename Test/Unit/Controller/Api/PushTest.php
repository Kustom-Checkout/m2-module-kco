<?php

/**
 * Copyright 2025 Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Controller\Api;

use Klarna\Base\Exception as KlarnaException;
use Klarna\Base\Test\Unit\Mock\MockFactory;
use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use Klarna\Kco\Controller\Api\Push;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order as MagentoOrder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covering the serialisation of the order creation in the push controller
 *
 * @coversDefaultClass \Klarna\Kco\Controller\Api\Push
 */
class PushTest extends TestCase
{
    private const KLARNA_ORDER_ID = 'c5909ea7-78d0-2042-9d49-8e20f9622aa1';

    /**
     * @var Push
     */
    private $model;

    /**
     * @var MockObject[]
     */
    private $dependencyMocks;

    /**
     * @var MockFactory
     */
    private $mockFactory;

    /**
     * @var Json|MockObject
     */
    private $jsonResult;

    protected function setUp(): void
    {
        $this->mockFactory = new MockFactory($this);
        $objectFactory = new TestObjectFactory('');
        $this->model = $objectFactory->create(Push::class);
        $this->dependencyMocks = $objectFactory->getDependencyMocks();

        $this->dependencyMocks['request']->method('getParam')->with('id')->willReturn(self::KLARNA_ORDER_ID);
        // No Magento order exists yet for the Kustom order, so the push tries to create it
        $this->dependencyMocks['workflowProvider']
            ->method('getMagentoOrder')
            ->willThrowException(new KlarnaException(__('No Magento order could be found')));

        $magentoOrder = $this->mockFactory->create(MagentoOrder::class);
        $magentoOrder->method('getIncrementId')->willReturn('100000001');
        $this->dependencyMocks['checkoutOrder']->method('getMagentoOrder')->willReturn($magentoOrder);

        $this->jsonResult = $this->mockFactory->create(Json::class);
    }

    /**
     * @covers ::execute
     */
    public function testExecuteReturnsRetryResponseWhenOrderCreationLockCanNotBeAcquired(): void
    {
        $this->dependencyMocks['creationLock']
            ->expects(static::once())
            ->method('acquire')
            ->with(self::KLARNA_ORDER_ID)
            ->willReturn(false);
        $this->dependencyMocks['creationLock']->expects(static::never())->method('release');
        $this->dependencyMocks['checkoutOrder']->expects(static::never())->method('createMagentoOrder');
        $this->dependencyMocks['checkoutOrder']->expects(static::never())->method('updateOrderState');
        $this->dependencyMocks['result']
            ->expects(static::once())
            ->method('getJsonResult')
            ->with(503)
            ->willReturn($this->jsonResult);

        static::assertSame($this->jsonResult, $this->model->execute());
    }

    /**
     * @covers ::execute
     */
    public function testExecuteSkipsOrderCreationWhenConcurrentRequestAlreadyCreatedTheOrder(): void
    {
        $this->expectLockAcquiredAndReleased();
        $this->dependencyMocks['checkoutOrder']->method('isMagentoOrderExists')->willReturn(true);
        $this->dependencyMocks['checkoutOrder']->expects(static::never())->method('createMagentoOrder');
        $this->dependencyMocks['checkoutOrder']->expects(static::never())->method('sendCustomerMail');
        $this->dependencyMocks['checkoutOrder']->expects(static::once())->method('updateOrderState');
        $this->expectJsonResult(200);

        static::assertSame($this->jsonResult, $this->model->execute());
    }

    /**
     * @covers ::execute
     */
    public function testExecuteCreatesOrderAndSendsMailAfterReleasingTheLock(): void
    {
        $calls = [];
        $this->dependencyMocks['creationLock']->method('acquire')->willReturn(true);
        $this->dependencyMocks['creationLock']
            ->expects(static::once())
            ->method('release')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'release';
            });
        $this->dependencyMocks['checkoutOrder']->method('isMagentoOrderExists')->willReturn(false);
        $this->dependencyMocks['checkoutOrder']
            ->expects(static::once())
            ->method('createMagentoOrder')
            ->with(self::KLARNA_ORDER_ID)
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'create';
                return $this->mockFactory->create(MagentoOrder::class);
            });
        $this->dependencyMocks['checkoutOrder']
            ->expects(static::once())
            ->method('sendCustomerMail')
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'mail';
            });
        $this->expectJsonResult(200);

        static::assertSame($this->jsonResult, $this->model->execute());
        static::assertSame(['create', 'release', 'mail'], $calls);
    }

    /**
     * @covers ::execute
     */
    public function testExecuteDoesNotSendMailWhenOrderAlreadyExists(): void
    {
        $this->expectLockAcquiredAndReleased();
        $this->dependencyMocks['checkoutOrder']->method('isMagentoOrderExists')->willReturn(false);
        $this->dependencyMocks['checkoutOrder']
            ->method('createMagentoOrder')
            ->willThrowException(new AlreadyExistsException(__('Order already exist.')));
        $this->dependencyMocks['checkoutOrder']->expects(static::never())->method('sendCustomerMail');
        $this->expectJsonResult(200);

        static::assertSame($this->jsonResult, $this->model->execute());
    }

    /**
     * @covers ::execute
     */
    public function testExecuteReleasesTheLockWhenOrderCreationFails(): void
    {
        $this->expectLockAcquiredAndReleased();
        $this->dependencyMocks['checkoutOrder']->method('isMagentoOrderExists')->willReturn(false);
        $this->dependencyMocks['checkoutOrder']
            ->method('createMagentoOrder')
            ->willThrowException(new LocalizedException(__('Something went wrong')));
        $this->dependencyMocks['checkoutOrder']->expects(static::never())->method('sendCustomerMail');
        $this->dependencyMocks['checkoutOrder']->expects(static::never())->method('updateOrderState');
        $this->expectJsonResult(500);

        static::assertSame($this->jsonResult, $this->model->execute());
    }

    /**
     * Setting up the creation lock so that it is acquired and released exactly once
     */
    private function expectLockAcquiredAndReleased(): void
    {
        $this->dependencyMocks['creationLock']
            ->expects(static::once())
            ->method('acquire')
            ->with(self::KLARNA_ORDER_ID)
            ->willReturn(true);
        $this->dependencyMocks['creationLock']
            ->expects(static::once())
            ->method('release')
            ->with(self::KLARNA_ORDER_ID);
    }

    /**
     * Expecting exactly one json result with the given http code
     *
     * @param int $httpCode
     */
    private function expectJsonResult(int $httpCode): void
    {
        $this->dependencyMocks['result']
            ->expects(static::once())
            ->method('getJsonResult')
            ->with($httpCode)
            ->willReturn($this->jsonResult);
    }
}
