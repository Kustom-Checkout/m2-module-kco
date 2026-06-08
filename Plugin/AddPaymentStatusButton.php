<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Plugin;

use Klarna\Kco\Model\Payment\Kco;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Sales\Block\Adminhtml\Order\View;

/**
 * Adding a custom button to the admin order details page to re-trigger the push controller action
 *
 * @internal
 */
class AddPaymentStatusButton
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;
    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @param UrlInterface           $urlBuilder
     * @param AuthorizationInterface $authorization
     * @codeCoverageIgnore
     */
    public function __construct(
        UrlInterface $urlBuilder,
        AuthorizationInterface $authorization
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->authorization = $authorization;
    }

    /**
     * Intercept setLayout method to add custom button
     *
     * @param View            $view
     * @param LayoutInterface $layout
     * @return void
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function beforeSetLayout(View $view, LayoutInterface $layout)
    {
        if (!$this->authorization->isAllowed('Klarna_Kco::payment_status')) {
            return;
        }

        $order = $view->getOrder();
        if ($order->getPayment()->getMethod() !== Kco::METHOD_CODE) {
            return;
        }
        if (!$order->isPaymentReview()) {
            return;
        }

        $message = __(
            'This will make an API call to Kustom to attempt to update the order state. ' .
            'Are you sure you want to do this?'
        );

        $url = $this->urlBuilder->getUrl(
            'klarna/om/paymentStatus/id/'
            . $order->getId()
            . '/store/'
            . $order->getStore()->getCode()
        );

        $view->addButton(
            'klarna_order_acknowledge',
            [
                'label'   => __('Update Payment Status'),
                'onclick' => "confirmSetLocation('{$message}', '{$url}')"
            ]
        );
    }
}
