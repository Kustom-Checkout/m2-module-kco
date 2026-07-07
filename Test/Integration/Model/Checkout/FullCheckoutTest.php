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
use Klarna\Kco\Model\Checkout\FullCheckout;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klarna\Kco\Model\Checkout\FullCheckout
 */
class FullCheckoutTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CheckoutApi|MockObject
     */
    private $checkoutApiMock;

    /**
     * @var FullCheckout
     */
    private $model;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();

        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->checkoutSession = $this->objectManager->get(CheckoutSession::class);
        $this->checkoutApiMock = $this->createMock(CheckoutApi::class);
        $this->objectManager->addSharedInstance($this->checkoutApiMock, CheckoutApi::class);

        $this->model = $this->objectManager->create(FullCheckout::class);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testGenerateFullCheckoutUrlShouldReturnFullCheckoutUrlBasedOnCreateOrderApiResponse(): void
    {
        $expectedUrl = 'http://localhost/dummy/checkout/uri';

        $quote = $this->checkoutSession->getQuote();
        $quote->save();

        $this->checkoutApiMock->expects($this->atLeastOnce())->method('createOrder')
            ->willReturn([
                'id' => '12345',
                'is_successful' => true,
                'full_checkout_uri' => $expectedUrl,
            ]);

        $url = $this->model->generateFullCheckoutUrl($this->storeManager->getStore());
        $this->assertEquals($expectedUrl, $url);
    }
}
