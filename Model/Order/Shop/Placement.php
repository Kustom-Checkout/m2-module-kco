<?php
/**
 * Copyright 2025 Kustom AB (Originally developed by Klarna Bank AB)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Order\Shop;

use Klarna\Base\Api\OrderInterface;
use Klarna\Base\Api\OrderRepositoryInterface;
use Klarna\AdminSettings\Model\Configurations\Api;
use Klarna\Kco\Api\QuoteInterface;
use Klarna\AdminSettings\Model\Configurations\Kco\Checkout;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface as MagentoOrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface as MagentoOrderInterface;

/**
 * @internal
 */
class Placement
{
    /**
     * @var CartManagementInterface
     */
    private CartManagementInterface $cartManagement;
    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $klarnaOrderRepository;
    /**
     * @var MagentoOrderRepositoryInterface
     */
    private MagentoOrderRepositoryInterface $magentoOrderRepository;
    /**
     * @var Checkout
     */
    private Checkout $checkoutConfiguration;
    /**
     * @var Api
     */
    private Api $apiConfiguration;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param CartManagementInterface $cartManagement
     * @param OrderRepositoryInterface $klarnaOrderRepository
     * @param MagentoOrderRepositoryInterface $magentoOrderRepository
     * @param Checkout $checkoutConfiguration
     * @param Api $apiConfiguration
     * @param LoggerInterface $logger
     * @codeCoverageIgnore
     */
    public function __construct(
        CartManagementInterface $cartManagement,
        OrderRepositoryInterface $klarnaOrderRepository,
        MagentoOrderRepositoryInterface $magentoOrderRepository,
        Checkout $checkoutConfiguration,
        Api $apiConfiguration,
        LoggerInterface $logger
    ) {
        $this->cartManagement = $cartManagement;
        $this->klarnaOrderRepository = $klarnaOrderRepository;
        $this->magentoOrderRepository = $magentoOrderRepository;
        $this->checkoutConfiguration = $checkoutConfiguration;
        $this->apiConfiguration = $apiConfiguration;
        $this->logger = $logger;
    }

    /**
     * Placing the order
     *
     * @param CartInterface $quote
     * @param QuoteInterface $klarnaQuote
     * @param OrderInterface $klarnaOrder
     * @return MagentoOrderInterface
     */
    public function placeOrder(
        CartInterface $quote,
        QuoteInterface $klarnaQuote,
        OrderInterface $klarnaOrder
    ): MagentoOrderInterface {
        try {
            $magentoOrderId = $this->cartManagement->placeOrder($quote->getId());
        } catch (\Exception $e) {
            /**
             * Logging with the quote context before rethrowing. Third party around plugins on
             * CartManagementInterface::placeOrder() can replace the original exception, so this is the last place
             * where the failing quote is known for sure.
             */
            $this->logger->error(
                'Order placement failed for quote ' . $quote->getId()
                . ' (reserved order id: ' . (string)$quote->getReservedOrderId()
                . ', Kustom order id: ' . (string)$klarnaQuote->getKlarnaCheckoutId()
                . ') - ' . get_class($e) . ': ' . $e->getMessage()
            );

            throw $e;
        }

        $klarnaOrder->setOrderId($magentoOrderId);
        $klarnaOrder->setKlarnaOrderId($klarnaQuote->getKlarnaCheckoutId());
        $klarnaOrder->setUsedMid(
            $this->apiConfiguration->getUserName(
                $quote->getStore(),
                $quote->getStore()->getCurrentCurrency()->getCode()
            )
        );
        $klarnaOrder->setIsB2b($this->checkoutConfiguration->isB2bEnabled($quote->getStore()));

        $this->klarnaOrderRepository->save($klarnaOrder);
        return $this->magentoOrderRepository->get($magentoOrderId);
    }
}
