<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Block\Adminhtml\Order;

use Klarna\Kco\Api\CustomerTokenRepositoryInterface;
use Klarna\Kco\Model\Payment\Kco;
use Klarna\Kco\Plugin\Sales\OrderRepositoryTokenPlugin;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Shows the Kustom recurring customer token of the order in the admin order view, with its full value.
 *
 * Only rendered when the admin holds `Klarna_Kco::customer_token` and a token row exists.
 *
 * @internal
 */
class CustomerToken extends Template
{
    /**
     * @var CustomerTokenRepositoryInterface
     */
    private CustomerTokenRepositoryInterface $tokenRepository;
    /**
     * @var AuthorizationInterface
     */
    private AuthorizationInterface $authorization;
    /**
     * @var Registry
     */
    private Registry $registry;
    /**
     * @var string|null|false false = not resolved yet
     */
    private $resolvedToken = false;

    /**
     * @param Context $context
     * @param CustomerTokenRepositoryInterface $tokenRepository
     * @param AuthorizationInterface $authorization
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        CustomerTokenRepositoryInterface $tokenRepository,
        AuthorizationInterface $authorization,
        Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->tokenRepository = $tokenRepository;
        $this->authorization = $authorization;
        $this->registry = $registry;
    }

    /**
     * The token, or null when the block must stay hidden
     *
     * @return string|null
     */
    public function getToken(): ?string
    {
        if ($this->resolvedToken !== false) {
            return $this->resolvedToken;
        }
        $this->resolvedToken = $this->resolveToken();
        return $this->resolvedToken;
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml()
    {
        if ($this->getToken() === null) {
            return '';
        }
        return parent::_toHtml();
    }

    /**
     * @return string|null
     */
    private function resolveToken(): ?string
    {
        if (!$this->authorization->isAllowed(OrderRepositoryTokenPlugin::ACL_RESOURCE)) {
            return null;
        }
        $order = $this->registry->registry('current_order');
        if ($order === null || !$order->getEntityId()) {
            return null;
        }
        if (!$this->isKustomOrder($order)) {
            return null;
        }
        try {
            $token = $this->tokenRepository->getByOrderId((int) $order->getEntityId());
        } catch (NoSuchEntityException $e) {
            return null;
        }
        $value = $token->getToken();
        if ($value === null || $value === '') {
            return null;
        }
        return $value;
    }

    /**
     * Only orders paid with Kustom Checkout can have a token; any problem reading the payment means no
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return bool
     */
    private function isKustomOrder($order): bool
    {
        try {
            $payment = $order->getPayment();
            return $payment !== null && $payment->getMethod() === Kco::METHOD_CODE;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
