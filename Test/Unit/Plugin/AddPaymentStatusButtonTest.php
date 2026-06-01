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
use Klarna\Kco\Model\Payment\Kco;
use Klarna\Kco\Plugin\AddPaymentStatusButton;
use PHPUnit\Framework\TestCase;
use Magento\Sales\Block\Adminhtml\Order\View;
use Magento\Framework\View\Layout;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\Store;

/**
 * @coversDefaultClass \Klarna\Kco\Plugin\AddPaymentStatusButton
 */
class AddPaymentStatusButtonTest extends TestCase
{
    /**
     * @var AddPaymentStatusButton|object
     */
    private AddPaymentStatusButton $model;
    /**
     * @var View|\PHPUnit\Framework\MockObject\MockObject
     */
    private View $view;
    /**
     * @var Layout|\PHPUnit\Framework\MockObject\MockObject
     */
    private Layout $layout;
    /**
     * @var array|\PHPUnit\Framework\MockObject\MockObject[]
     */
    private array $dependencyMocks;
    /**
     * @var Order|\PHPUnit\Framework\MockObject\MockObject
     */
    private Order $magentoOrder;
    /**
     * @var Payment|\PHPUnit\Framework\MockObject\MockObject
     */
    private Payment $payment;

    public function testBeforeSetLayoutNotAuthorizedToSeeButton(): void
    {
        $this->view->expects(static::never())
            ->method('addButton');

        $this->model->beforeSetLayout($this->view, $this->layout);
    }

    public function testBeforeSetLayoutPaymentMethodIsNotKco(): void
    {
        $this->dependencyMocks['authorization']->method('isAllowed')
            ->willReturn(true);

        $this->view->expects(static::never())
            ->method('addButton');

        $this->model->beforeSetLayout($this->view, $this->layout);
    }

    public function testBeforeSetLayoutOrderIsNotInStatePaymentReview(): void
    {
        $this->dependencyMocks['authorization']->method('isAllowed')
            ->willReturn(true);
        $this->payment->method('getMethod')
            ->willReturn(Kco::METHOD_CODE);

        $this->view->expects(static::never())
            ->method('addButton');

        $this->model->beforeSetLayout($this->view, $this->layout);
    }

    public function testBeforeSetLayoutButtonIsAddedToTheView(): void
    {
        $this->dependencyMocks['authorization']->method('isAllowed')
            ->willReturn(true);
        $this->payment->method('getMethod')
            ->willReturn(Kco::METHOD_CODE);
        $this->magentoOrder->method('isPaymentReview')
            ->willReturn(true);

        $this->view->expects(static::once())
            ->method('addButton');

        $this->model->beforeSetLayout($this->view, $this->layout);
    }

    protected function setUp(): void
    {
        $mockFactory = new MockFactory($this);
        $objectFactory = new TestObjectFactory('');

        $this->model = $objectFactory->create(AddPaymentStatusButton::class);
        $this->dependencyMocks = $objectFactory->getDependencyMocks();

        $this->view = $mockFactory->create(View::class);
        $this->magentoOrder = $mockFactory->create(Order::class);
        $this->view->method('getOrder')
            ->willReturn($this->magentoOrder);
        $this->payment = $mockFactory->create(Payment::class);
        $this->magentoOrder->method('getPayment')
            ->willReturn($this->payment);
        $store = $mockFactory->create(Store::class);
        $this->magentoOrder->method('getStore')
            ->willReturn($store);

        $this->layout = $mockFactory->create(Layout::class);
    }
}
