<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Model\Checkout;

use Klarna\Kco\Model\Api\Rest\Service\Checkout as CheckoutApi;
use Klarna\Kco\Model\Checkout\Initialization;
use Klarna\Kco\Model\Checkout\Kco\Session as KcoSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klarna\Kco\Model\Checkout\Initialization
 */
class InitializationTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var KcoSession
     */
    private $kcoSession;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CheckoutApi|MockObject
     */
    private $checkoutApiMock;

    /**
     * @var Initialization
     */
    private $model;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();

        $this->kcoSession = $this->objectManager->get(KcoSession::class);
        $this->checkoutSession = $this->objectManager->get(CheckoutSession::class);
        $this->checkoutApiMock = $this->createMock(CheckoutApi::class);
        $this->objectManager->addSharedInstance($this->checkoutApiMock, CheckoutApi::class);

        $this->model = $this->objectManager->create(Initialization::class);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store checkout/klarna_kco/guest_checkout 1
     */
    public function testCreateUpdateKlarnaSessionShouldCreateSessionWhenNoExistingDataInPlace(): void
    {
        $quote = $this->checkoutSession->getQuote();
        $quote->save();

        $this->checkoutApiMock->expects($this->atLeastOnce())->method('createOrder')
            ->willReturn([
                'id' => '12345',
                'is_successful' => true,
            ]);

        $expectedResult = Initialization::STATE_CREATE;
        $result = $this->model->createUpdateKlarnaSession();
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store checkout/klarna_kco/guest_checkout 1
     */
    public function testCreateUpdateKlarnaSessionShouldUpdateSessionWhenItAlreadyExists(): void
    {
        $quote = $this->checkoutSession->getQuote();
        $quote->save();

        $this->kcoSession->setKlarnaQuoteKlarnaCheckoutId('12345');

        $this->checkoutApiMock->expects($this->atLeastOnce())->method('updateOrder')
            ->willReturn([
                'id' => '12345',
                'is_successful' => true,
            ]);

        $expectedResult = Initialization::STATE_UPDATE;
        $result = $this->model->createUpdateKlarnaSession();
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoConfigFixture current_store checkout/klarna_kco/guest_checkout 0
     */
    public function testCreateUpdateKlarnaSessionShouldDoNothingWithGuestAndGuestCheckoutTurnedOff(): void
    {
        $this->checkoutApiMock->expects($this->never())->method('createOrder');
        $this->checkoutApiMock->expects($this->never())->method('updateOrder');

        $expectedResult = Initialization::STATE_NONE;
        $result = $this->model->createUpdateKlarnaSession();
        $this->assertEquals($expectedResult, $result);
    }
}
