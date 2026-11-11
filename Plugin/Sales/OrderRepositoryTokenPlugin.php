<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Plugin\Sales;

use Klarna\Kco\Api\CustomerTokenRepositoryInterface;
use Klarna\Kco\Model\Payment\Kco;
use Magento\Framework\AuthorizationInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Adds the extension attribute `kustom_customer_token` to orders loaded through the order repository.
 *
 * The decrypted token is only populated when the current caller is allowed `Klarna_Kco::customer_token`
 * (admin session, or an integration holding that resource). Without authorisation (no session, CLI, cron,
 * storefront, REST without the resource) the attribute stays null. Orders loaded through factories or
 * collections do not get the attribute. Only orders paid with Kustom Checkout (`klarna_kco`) are looked up.
 *
 * @internal
 */
class OrderRepositoryTokenPlugin
{
    public const ACL_RESOURCE = 'Klarna_Kco::customer_token';

    /**
     * @var CustomerTokenRepositoryInterface
     */
    private CustomerTokenRepositoryInterface $tokenRepository;
    /**
     * @var AuthorizationInterface
     */
    private AuthorizationInterface $authorization;

    /**
     * @param CustomerTokenRepositoryInterface $tokenRepository
     * @param AuthorizationInterface           $authorization
     * @codeCoverageIgnore
     */
    public function __construct(
        CustomerTokenRepositoryInterface $tokenRepository,
        AuthorizationInterface $authorization
    ) {
        $this->tokenRepository = $tokenRepository;
        $this->authorization = $authorization;
    }

    /**
     * Add the token to a single order
     *
     * @param OrderRepositoryInterface $subject
     * @param OrderInterface           $result
     * @return OrderInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGet(OrderRepositoryInterface $subject, OrderInterface $result): OrderInterface
    {
        if (!$this->isAllowed()) {
            return $result;
        }

        $orderId = (int)$result->getEntityId();
        if ($orderId === 0 || !$this->isKustomOrder($result)) {
            return $result;
        }

        $tokens = $this->loadTokens([$orderId]);
        $this->assign($result, $tokens[$orderId] ?? null);
        return $result;
    }

    /**
     * Add the tokens to a list of orders with one query
     *
     * @param OrderRepositoryInterface  $subject
     * @param OrderSearchResultInterface $result
     * @return OrderSearchResultInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetList(
        OrderRepositoryInterface $subject,
        OrderSearchResultInterface $result
    ): OrderSearchResultInterface {
        if (!$this->isAllowed()) {
            return $result;
        }

        $orders = $result->getItems();
        $orderIds = [];
        foreach ($orders as $order) {
            if ((int)$order->getEntityId() > 0 && $this->isKustomOrder($order)) {
                $orderIds[] = (int)$order->getEntityId();
            }
        }
        if ($orderIds === []) {
            return $result;
        }

        $tokens = $this->loadTokens($orderIds);
        if ($tokens === []) {
            return $result;
        }
        foreach ($orders as $order) {
            $this->assign($order, $tokens[(int)$order->getEntityId()] ?? null);
        }
        return $result;
    }

    /**
     * Load the tokens; any failure leaves the attribute null (silent, nothing about the token is exposed)
     *
     * @param int[] $orderIds
     * @return array
     */
    private function loadTokens(array $orderIds): array
    {
        try {
            return $this->tokenRepository->getListByOrderIds($orderIds);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Set the attribute on the order
     *
     * @param OrderInterface                                        $order
     * @param \Klarna\Kco\Api\Data\CustomerTokenInterface|null $token
     * @return void
     */
    private function assign(OrderInterface $order, $token): void
    {
        if ($token === null) {
            return;
        }
        $extension = $order->getExtensionAttributes();
        if ($extension === null) {
            return;
        }
        $extension->setKustomCustomerToken($token);
    }

    /**
     * Only orders paid with Kustom Checkout can have a token; any problem reading the payment means no
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function isKustomOrder(OrderInterface $order): bool
    {
        try {
            $payment = $order->getPayment();
            return $payment !== null && $payment->getMethod() === Kco::METHOD_CODE;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Fail closed: any problem resolving the ACL means not allowed
     *
     * @return bool
     */
    private function isAllowed(): bool
    {
        try {
            return $this->authorization->isAllowed(self::ACL_RESOURCE);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
