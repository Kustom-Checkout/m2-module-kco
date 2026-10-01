<?php

/**
 * Copyright 2025 Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Controller\Klarna;

use Klarna\Base\Exception as KlarnaException;
use Klarna\Base\Test\Unit\Mock\MockFactory;
use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use Klarna\Kco\Controller\Klarna\Confirmation;
use Klarna\Kco\Model\Checkout\Url;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\CartLockedException;
use Magento\Sales\Model\Order as MagentoOrder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covering the serialisation of the order creation in the confirmation controller
 *
 * @coversDefaultClass \Klarna\Kco\Controller\Klarna\Confirmation
 */
class ConfirmationTest extends TestCase
{
    private const KLARNA_ORDER_ID = 'c5909ea7-78d0-2042-9d49-8e20f9622aa1';
    private const FAILURE_URL = 'https://example.com/checkout/klarna/failure';
    private const EXPECTED_SESSION_DATA = [
        'setLastQuoteId' => 42,
        'setLastSuccessQuoteId' => 42,
        'setLastOrderId' => 7,
        'setLastRealOrderId' => '100000001',
        'setLastOrderStatus' => 'pending',
    ];

    /**
     * @var Confirmation
     */
    private $model;

    /**
     * @var MockObject[]
     */
    private $dependencyMocks;

    /**
     * @var Redirect|MockObject
     */
    private $redirect;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var MockFactory
     */
    private $mockFactory;

    protected function setUp(): void
    {
        $this->mockFactory = new MockFactory($this);
        $this->checkoutSession = $this->createCheckoutSessionStub();

        $objectFactory = new TestObjectFactory('');
        $this->model = $objectFactory->create(
            Confirmation::class,
            [],
            [CheckoutSession::class => $this->checkoutSession]
        );
        $this->dependencyMocks = $objectFactory->getDependencyMocks();

        $this->dependencyMocks['request']->method('getParam')->with('id')->willReturn(self::KLARNA_ORDER_ID);
        $this->dependencyMocks['url']->method('getFailureUrl')->willReturn(self::FAILURE_URL);

        $this->redirect = $this->mockFactory->create(Redirect::class);
        $this->redirect->method('setPath')->willReturnSelf();
        $this->redirect->method('setUrl')->willReturnSelf();
        $this->dependencyMocks['redirectFactory']->method('create')->willReturn($this->redirect);
    }

    /**
     * @covers ::execute
     */
    public function testExecuteRedirectsToSuccessWhenLockCanNotBeAcquiredAndNoOrderExists(): void
    {
        $this->dependencyMocks['creationLock']
            ->expects(static::once())
            ->method('acquire')
            ->with(self::KLARNA_ORDER_ID)
            ->willReturn(false);
        $this->dependencyMocks['creationLock']->expects(static::never())->method('release');
        $this->dependencyMocks['checkoutOrder']->method('isMagentoOrderExists')->willReturn(false);
        $this->dependencyMocks['checkoutOrder']->expects(static::never())->method('createMagentoOrder');
        $this->expectSuccessRedirect();

        static::assertSame($this->redirect, $this->model->execute());
        static::assertSame([], $this->checkoutSession->calls);
    }

    /**
     * @covers ::execute
     * @covers ::getExistingOrderResponse
     */
    public function testExecuteFillsCheckoutSessionWhenLockCanNotBeAcquiredButOrderExists(): void
    {
        $this->stubExistingMagentoOrder();
        $this->dependencyMocks['creationLock']->method('acquire')->willReturn(false);
        $this->dependencyMocks['creationLock']->expects(static::never())->method('release');
        $this->dependencyMocks['checkoutOrder']->method('isMagentoOrderExists')->willReturn(true);
        $this->dependencyMocks['checkoutOrder']->expects(static::never())->method('createMagentoOrder');
        $this->expectSuccessRedirect();

        static::assertSame($this->redirect, $this->model->execute());
        static::assertSame(self::EXPECTED_SESSION_DATA, $this->checkoutSession->calls);
    }

    /**
     * @covers ::execute
     * @covers ::getExistingOrderResponse
     */
    public function testExecuteSkipsCreationAndFillsCheckoutSessionWhenConcurrentRequestCreatedTheOrder(): void
    {
        $this->stubExistingMagentoOrder();
        $this->expectLockAcquiredAndReleased();
        $this->dependencyMocks['checkoutOrder']->method('isMagentoOrderExists')->willReturn(true);
        $this->dependencyMocks['checkoutOrder']->expects(static::never())->method('createMagentoOrder');
        $this->dependencyMocks['checkoutOrder']->expects(static::never())->method('sendCustomerMail');
        $this->dependencyMocks['workflowProvider']
            ->expects(static::once())
            ->method('setKlarnaOrderId')
            ->with(self::KLARNA_ORDER_ID);
        $this->expectSuccessRedirect();

        static::assertSame($this->redirect, $this->model->execute());
        static::assertSame(self::EXPECTED_SESSION_DATA, $this->checkoutSession->calls);
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
        $this->expectSuccessRedirect();

        static::assertSame($this->redirect, $this->model->execute());
        static::assertSame(['create', 'release', 'mail'], $calls);
        // The order placement itself fills the session of the request which created the order
        static::assertSame([], $this->checkoutSession->calls);
    }

    /**
     * @covers ::execute
     * @covers ::getExistingOrderResponse
     */
    public function testExecuteFillsCheckoutSessionAndDoesNotSendMailWhenOrderAlreadyExists(): void
    {
        $this->stubExistingMagentoOrder();
        $this->expectLockAcquiredAndReleased();
        $this->dependencyMocks['checkoutOrder']->method('isMagentoOrderExists')->willReturn(false);
        $this->dependencyMocks['checkoutOrder']
            ->method('createMagentoOrder')
            ->willThrowException(new AlreadyExistsException(__('Order already exist.')));
        $this->dependencyMocks['checkoutOrder']->expects(static::never())->method('sendCustomerMail');
        $this->expectSuccessRedirect();

        static::assertSame($this->redirect, $this->model->execute());
        static::assertSame(self::EXPECTED_SESSION_DATA, $this->checkoutSession->calls);
    }

    /**
     * @covers ::execute
     */
    public function testExecuteRedirectsToSuccessWithoutSessionDataWhenCartIsLocked(): void
    {
        $this->expectLockAcquiredAndReleased();
        $this->dependencyMocks['checkoutOrder']->method('isMagentoOrderExists')->willReturn(false);
        $this->dependencyMocks['checkoutOrder']
            ->method('createMagentoOrder')
            ->willThrowException(new CartLockedException(__('The cart is locked for processing.')));
        $this->dependencyMocks['checkoutOrder']->expects(static::never())->method('sendCustomerMail');
        $this->expectSuccessRedirect();

        static::assertSame($this->redirect, $this->model->execute());
        static::assertSame([], $this->checkoutSession->calls);
    }

    /**
     * @covers ::execute
     * @covers ::getExistingOrderResponse
     */
    public function testExecuteFillsCheckoutSessionWhenOrderCreationFailedButOrderExists(): void
    {
        $this->stubExistingMagentoOrder();
        $this->expectLockAcquiredAndReleased();
        $this->dependencyMocks['checkoutOrder']
            ->method('isMagentoOrderExists')
            ->willReturnOnConsecutiveCalls(false, true);
        $this->dependencyMocks['checkoutOrder']
            ->method('createMagentoOrder')
            ->willThrowException(new LocalizedException(__('Something went wrong')));
        $this->dependencyMocks['checkoutOrder']->expects(static::never())->method('sendCustomerMail');
        $this->expectSuccessRedirect();

        static::assertSame($this->redirect, $this->model->execute());
        static::assertSame(self::EXPECTED_SESSION_DATA, $this->checkoutSession->calls);
    }

    /**
     * @covers ::execute
     */
    public function testExecuteReleasesTheLockAndRedirectsToFailureWhenOrderCreationFails(): void
    {
        $this->expectLockAcquiredAndReleased();
        $this->dependencyMocks['checkoutOrder']->method('isMagentoOrderExists')->willReturn(false);
        $this->dependencyMocks['checkoutOrder']
            ->method('createMagentoOrder')
            ->willThrowException(new LocalizedException(__('Something went wrong')));
        $this->dependencyMocks['checkoutOrder']->expects(static::never())->method('sendCustomerMail');
        $this->redirect->expects(static::once())->method('setUrl')->with(self::FAILURE_URL);

        static::assertSame($this->redirect, $this->model->execute());
        static::assertSame([], $this->checkoutSession->calls);
    }

    /**
     * @covers ::getExistingOrderResponse
     */
    public function testExecuteStillRedirectsToSuccessWhenExistingOrderCanNotBeLoaded(): void
    {
        $this->expectLockAcquiredAndReleased();
        $this->dependencyMocks['checkoutOrder']->method('isMagentoOrderExists')->willReturn(true);
        $this->dependencyMocks['workflowProvider']
            ->method('getMagentoOrder')
            ->willThrowException(new KlarnaException(__('No Magento order could be found')));
        $this->expectSuccessRedirect();

        static::assertSame($this->redirect, $this->model->execute());
        static::assertSame([], $this->checkoutSession->calls);
    }

    /**
     * Stubbing the Magento order which was created by the concurrent request
     */
    private function stubExistingMagentoOrder(): void
    {
        $magentoOrder = $this->mockFactory->create(MagentoOrder::class);
        $magentoOrder->method('getQuoteId')->willReturn(42);
        $magentoOrder->method('getId')->willReturn(7);
        $magentoOrder->method('getIncrementId')->willReturn('100000001');
        $magentoOrder->method('getStatus')->willReturn('pending');
        $this->dependencyMocks['workflowProvider']->method('getMagentoOrder')->willReturn($magentoOrder);
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
     * Expecting a redirect to the success page
     */
    private function expectSuccessRedirect(): void
    {
        $this->redirect
            ->expects(static::once())
            ->method('setPath')
            ->with(Url::CHECKOUT_ACTION_PREFIX . '/success');
    }

    /**
     * Creating a checkout session which records the magic setter calls
     *
     * The checkout session data is set through magic methods which can not be mocked with newer PHPUnit versions.
     *
     * @return CheckoutSession
     */
    private function createCheckoutSessionStub(): CheckoutSession
    {
        return new class extends CheckoutSession {
            /**
             * @var array
             */
            public array $calls = [];

            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedFunction
            public function __construct()
            {
            }

            /**
             * @param string $method
             * @param array $args
             * @return $this
             */
            public function __call($method, $args)
            {
                $this->calls[$method] = $args[0] ?? null;

                return $this;
            }
        };
    }
}
