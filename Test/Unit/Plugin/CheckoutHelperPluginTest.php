<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

namespace Klarna\Kco\Test\Unit\Plugin;

use Klarna\Base\Test\Unit\Mock\MockFactory;
use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use Klarna\Kco\Plugin\CheckoutHelperPlugin;
use PHPUnit\Framework\TestCase;
use Magento\Checkout\Model\Session;
use Magento\Quote\Model\Quote;
use Magento\Checkout\Helper\Data;
use Magento\Store\Model\Store;

/**
 * @coversDefaultClass \Klarna\Kco\Plugin\CheckoutHelperPlugin
 */
class CheckoutHelperPluginTest extends TestCase
{
    /**
     * @var CheckoutHelperPlugin
     */
    private CheckoutHelperPlugin $model;
    /**
     * @var array|\PHPUnit\Framework\MockObject\MockObject[]
     */
    private array $dependencyMocks;
    /**
     * @var Data|\PHPUnit\Framework\MockObject\MockObject
     */
    private Data $subject;

    public function testAfterIsAllowedGuestCheckoutKcoIsDisabled(): void
    {
        static::assertFalse($this->model->afterIsAllowedGuestCheckout($this->subject, false));
    }

    public function testAfterIsAllowedGuestCheckoutCustomerIsGuest(): void
    {
        $this->dependencyMocks['config']->method('isKcoEnabled')
            ->willReturn(true);
        $this->dependencyMocks['customerSession']->method('isLoggedIn')
            ->willReturn(true);
        static::assertTrue($this->model->afterIsAllowedGuestCheckout($this->subject, false));
    }

    public function testAfterIsAllowedGuestCheckoutReturnAllowGuestCheckoutFlag(): void
    {
        $this->dependencyMocks['config']->method('isKcoEnabled')
            ->willReturn(true);
        $this->dependencyMocks['config']->method('isAllowedGuestCheckout')
            ->willReturn(true);
        static::assertTrue($this->model->afterIsAllowedGuestCheckout($this->subject, false));
    }

    protected function setUp(): void
    {
        $mockFactory = new MockFactory($this);
        $objectFactory = new TestObjectFactory('');

        $this->model = $objectFactory->create(CheckoutHelperPlugin::class);
        $this->dependencyMocks = $objectFactory->getDependencyMocks();

        $checkoutSession = $mockFactory->create(Session::class);
        $store = $mockFactory->create(Store::class);
        $quote = $mockFactory->create(Quote::class);
        $quote->method('getStore')
            ->willReturn($store);
        $checkoutSession->method('getQuote')
            ->willReturn($quote);

        $this->subject = $mockFactory->create(Data::class);
        $this->subject->method('getCheckout')
            ->willReturn($checkoutSession);
    }
}
