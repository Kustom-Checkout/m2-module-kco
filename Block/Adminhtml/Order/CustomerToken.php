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
 * Shows the Kustom recurring customer token of the order in the admin order view, masked to its last 4 characters.
 *
 * Only rendered when the admin holds `Klarna_Kco::customer_token` and a token row exists. The full token is
 * never passed to the template.
 *
 * @internal
 */
class CustomerToken extends Template
{
    private const VISIBLE_CHARACTERS = 4;

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
    private $maskedToken = false;

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
     * Masked token (e.g. ****abcd), or null when the block must stay hidden
     *
     * @return string|null
     */
    public function getMaskedToken(): ?string
    {
        if ($this->maskedToken !== false) {
            return $this->maskedToken;
        }
        $this->maskedToken = $this->resolveMaskedToken();
        return $this->maskedToken;
    }

    /**
     * Mask all but the last 4 characters; tokens of 4 characters or fewer are fully masked
     *
     * @param string $token
     * @return string
     */
    public function mask(string $token): string
    {
        $length = mb_strlen($token);
        if ($length === 0) {
            return '';
        }
        if ($length <= self::VISIBLE_CHARACTERS) {
            return str_repeat('*', $length);
        }
        return '****' . mb_substr($token, -self::VISIBLE_CHARACTERS);
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml()
    {
        if ($this->getMaskedToken() === null) {
            return '';
        }
        return parent::_toHtml();
    }

    /**
     * @return string|null
     */
    private function resolveMaskedToken(): ?string
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
        return $this->mask($value);
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
