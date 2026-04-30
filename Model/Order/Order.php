<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Order;

use Klarna\AdminSettings\Model\Configurations\Kco\Checkout;
use Klarna\Backend\Api\ApiInterface;
use Klarna\Backend\Model\Api\Factory;
use Klarna\Backend\Model\Api\OrderManagement;
use Klarna\Base\Api\OrderInterface;
use Klarna\Base\Exception as KlarnaException;
use Klarna\Base\Model\OrderRepository;
use Klarna\Kco\Api\ApiInterface as KcoApiInterface;
use Klarna\Kco\Model\Cart\Validations\Handler;
use Klarna\Kco\Model\Checkout\Kco\Initializer;
use Klarna\Kco\Model\Checkout\Kco\Session as KcoSession;
use Klarna\Kco\Model\Order\Shop\Action;
use Klarna\Kco\Model\Payment\Kco;
use Klarna\Kco\Model\PaymentStatus as KcoPaymentStatus;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Klarna\Kco\Model\WorkflowProvider;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface as MagentoOrderInterface;
use Magento\Sales\Model\Order as SalesOrder;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderRepository as MageOrderRepository;


/**
 * Preparing and Creating orders and doing post order creation actions
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @internal
 */
class Order
{
    /**
     * @var KcoSession
     */
    private KcoSession $kcoSession;
    /**
     * @var Initializer
     */
    private Initializer $initializer;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * @var OrderRepository
     */
    private OrderRepository $orderRepository;
    /**
     * @var ?OrderInterface
     */
    private ?OrderInterface $klarnaOrder = null;
    /**
     * @var ?CartInterface
     */
    private ?CartInterface $mageQuote = null;
    /**
     * @var ?MagentoOrderInterface
     */
    private ?MagentoOrderInterface $mageOrder = null;
    /**
     * @var OrderSender
     */
    private OrderSender $orderSender;
    /**
     * @var WorkflowProvider
     */
    private WorkflowProvider $workflowProvider;
    /**
     * @var Factory
     */
    private Factory $factory;
    /**
     * @var MageOrderRepository
     */
    private MageOrderRepository $mageOrderRepository;
    /**
     * @var KcoPaymentStatus
     */
    private KcoPaymentStatus $paymentStatus;
    /**
     * @var Handler
     */
    private Handler $validation;
    /**
     * @var Action
     */
    private Action $action;
    /**
     * @var Checkout
     */
    private Checkout $checkoutConfiguration;
    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    /**
     * @var DataObject[]
     */
    private array $klarnaOrderDetailsCache = [];

    /**
     * @param KcoSession               $kcoSession
     * @param Initializer              $initializer
     * @param LoggerInterface          $logger
     * @param OrderRepository          $orderRepository
     * @param OrderSender              $orderSender
     * @param WorkflowProvider         $workflowProvider
     * @param Factory                  $factory
     * @param MageOrderRepository      $mageOrderRepository
     * @param KcoPaymentStatus         $paymentStatus
     * @param Handler                  $validation
     * @param Action                   $action
     * @param Checkout                 $checkoutConfiguration
     * @param SearchCriteriaBuilder    $searchCriteriaBuilder
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @codeCoverageIgnore
     */
    public function __construct(
        KcoSession $kcoSession,
        Initializer $initializer,
        LoggerInterface $logger,
        OrderRepository $orderRepository,
        OrderSender $orderSender,
        WorkflowProvider $workflowProvider,
        Factory $factory,
        MageOrderRepository $mageOrderRepository,
        KcoPaymentStatus $paymentStatus,
        Handler $validation,
        Action $action,
        Checkout $checkoutConfiguration,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->kcoSession            = $kcoSession;
        $this->initializer           = $initializer;
        $this->logger                = $logger;
        $this->orderRepository       = $orderRepository;
        $this->orderSender           = $orderSender;
        $this->workflowProvider      = $workflowProvider;
        $this->factory               = $factory;
        $this->mageOrderRepository   = $mageOrderRepository;
        $this->paymentStatus         = $paymentStatus;
        $this->validation            = $validation;
        $this->action                = $action;
        $this->checkoutConfiguration = $checkoutConfiguration;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Creating the magento order for the given Klarna order
     *
     * @param string $klarnaOrderId
     * @return MagentoOrderInterface
     * @throws KlarnaException
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws AlreadyExistsException
     */
    public function createMagentoOrder(string $klarnaOrderId): MagentoOrderInterface
    {
        $this->workflowProvider->setKlarnaOrderId($klarnaOrderId);

        try {
            $this->klarnaOrder = $this->workflowProvider->getKlarnaOrder();
        } catch (KlarnaException $e) {
            $this->klarnaOrder = $this->orderRepository->getEmptyInstance();
            $this->klarnaOrder->setOrderId($klarnaOrderId);
        }

        if ($this->klarnaOrder->getId()) {
            throw new AlreadyExistsException(__(
                'Order already exist.'
            ));
        }

        $klarnaQuote     = $this->workflowProvider->getKcoQuote();
        $this->mageQuote = $this->workflowProvider->getMagentoQuote();

        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $this->mageQuote->getReservedOrderId())
                ->create();
            $orderList = $this->mageOrderRepository->getList($searchCriteria);

            if ($orderList->getTotalCount() > 0) {
                $magentoOrder = $orderList->getFirstItem();
                $this->mageOrder = $magentoOrder;

                // It means the order already exists and we return it so that we do not create another one
                return $magentoOrder;
            }
        } catch (NoSuchEntityException $e) {
            $this->logger->debug('Order is not yet created for Magento quote ID: ' . $this->mageQuote->getId());
        }

        $this->kcoSession->setQuote($this->mageQuote);
        $this->kcoSession->setKlarnaQuote($klarnaQuote);

        /**
         * There are cases when we have to validate the Klarna order with the Magento quote. One case is for example
         * when the customer is redirected to a external page (for example "Online bank transfer") after clicking the
         * purchase button. When after it something is changed in shop which affects the quote for example one of the
         * products in the cart are disabled it can result in different orders between Klarna and the shop.
         */
        $klarnaApiOrder = $this->initializer->getKlarnaCheckout($klarnaOrderId);
        $this->validation->validateRequestObject($klarnaApiOrder, $this->mageQuote);

        if (!$this->mageQuote->getReservedOrderId()) {
            $this->mageQuote->reserveOrderId();
        }

        // Check if checkout is complete before placing the order
        $checkout = $this->initializer->getKlarnaCheckout($klarnaOrderId);
        if ($checkout->getStatus() !== 'checkout_complete' && $checkout->getStatus() !== 'created') {
            $this->logger->error('Could not create the order because the checkout is not finished');
            $exceptionMessage = __(
                'Checkout is not complete. Order for quote %1 can´t be created',
                $this->mageQuote->getReservedOrderId()
            );
            throw new KlarnaException($exceptionMessage);
        }
        $this->logger->debug('Checkout process is finished.');

        $this->mageOrder = $this->action->createOrder(
            $this->mageQuote,
            $this->kcoSession->getKlarnaQuote(),
            $this->klarnaOrder,
            $klarnaApiOrder
        );
        $this->logger->debug('Created the shop order');

        $reservationId = $this->initializer->getReservationId($this->mageQuote->getStore());

        $this->workflowProvider->clearKlarnaOrder();
        $this->klarnaOrder = $this->workflowProvider->getKlarnaOrder();
        $this->klarnaOrder->setReservationId($reservationId);
        $this->orderRepository->save($this->klarnaOrder);
        $this->logger->debug('Saved the klarna order');

        return $this->mageOrder;
    }

    /**
     * Sending the customer mail
     */
    public function sendCustomerMail(): void
    {
        try {
            /**
             * a flag to set that there will be redirect to third party after confirmation
             */
            $redirectUrl = $this->mageQuote->getPayment()->getOrderPlaceRedirectUrl();
            /**
             * we only want to send to customer about new order when there is no redirect to third party
             */
            if (!$redirectUrl && $this->mageOrder->getCanSendNewEmailFlag()) {
                $this->orderSender->send($this->mageOrder);
                $this->logger->debug('Confirmation: Sent order email');
            }
        } catch (\Exception $e) {
            // We don't want to cancel the order at this point, only just log the error
            $this->logger->info('Could not sent the customer mail');
            $this->logger->critical($e);
        }
    }

    /**
     * Setting the order status if the order state is Order::STATE_PROCESSING
     *
     * @param MagentoOrderInterface $order
     * @param string                $status
     */
    public function setOrderStatus(MagentoOrderInterface $order, string $status = ''): void
    {
        if (empty($status)) {
            $status = $this->checkoutConfiguration->getOrderStatusForNewOrders($order->getStore());
        }

        if (SalesOrder::STATE_PROCESSING === $order->getState()) {
            $order->addStatusHistoryComment(__('Order processed by Klarna.'), $status);
        }
    }

    /**
     * Canceling the Klarna order
     *
     * @param string $klarnaOrderId
     * @param string $cancelReason
     * @throws KlarnaException
     */
    public function cancelKlarnaOrder(string $klarnaOrderId, string $cancelReason): void
    {
        $this->workflowProvider->setKlarnaOrderId($klarnaOrderId);
        try {
            $magentoOrder = $this->workflowProvider->getMagentoOrder();
            $store = $magentoOrder->getStore();
        } catch (KlarnaException $e) {
            // It's ok that we don't find a Magento order, we still need to
            // cancel the order with Klarna but we need to know the store for
            // credentials. So get it from the quote
            $store =  $this->workflowProvider->getMagentoQuote()->getStore();
        }

        try {
            $orderManagement = $this->factory->createOmApi(
                Kco::METHOD_CODE,
                $this->mageOrder?->getOrderCurrencyCode() ?: $store->getCurrentCurrencyCode(),
                $store
            );
            $order = $orderManagement->getPlacedKlarnaOrder($klarnaOrderId);
            $klarnaId = $order->getReservation();
            if (!$klarnaId) {
                $klarnaId = $klarnaOrderId;
            }
            if ($order->getStatus() !== 'CANCELLED') {
                $orderManagement->cancel($klarnaId);
                $this->logger->info('Canceled order with Klarna - ' . $cancelReason);
            }

            if ($magentoOrder !== null && !$magentoOrder->isCanceled()) {
                $this->cancelMagentoOrder($magentoOrder, KcoApiInterface::ORDER_STATUS_CANCELLED);
            }
        } catch (\Exception $e) {
            $this->logger->log('error', $e);
        }
    }

    /**
     * Updating the state of the orders.
     *
     * @param string $klarnaOrderId
     * @throws KlarnaException
     * @throws LocalizedException
     */
    public function updateOrderState(string $klarnaOrderId): void
    {
        $this->workflowProvider->setKlarnaOrderId($klarnaOrderId);
        $klarnaOrder = $this->workflowProvider->getKlarnaOrder();
        $this->mageOrder = $this->workflowProvider->getMagentoOrder();

        $this->logger->debug('Order state is: ' . $this->mageOrder->getState());

        $this->updateOrderStatus($this->mageOrder);
        $omApi = $this->factory->createOmApi(
            $this->mageOrder->getPayment()->getMethod(),
            $this->mageOrder->getOrderCurrencyCode(),
            $this->mageOrder->getStore()
        );
        $klarnaOrderDetails = $this->fetchKlarnaOrderDetails($omApi, $klarnaOrder->getKlarnaOrderId());
        $klarnaStatus = $klarnaOrderDetails->getStatus();
        // TODO: Consider saving cancel status in database klarna table
        if ($klarnaStatus === KcoApiInterface::ORDER_STATUS_CANCELLED) {
            $this->logger->info(
                'Klarna order is ' . $klarnaStatus . '. Cancelling Magento order: '
                . $this->mageOrder->getIncrementId()
            );
            $this->cancelMagentoOrder($this->mageOrder, $klarnaStatus);
            return;
        }

        $this->updateOrderWithKlarnaReference($klarnaOrder, $this->mageOrder, $omApi);

        $omApi->updateMerchantReferences($klarnaOrderId, $this->mageOrder->getIncrementId());
        $this->logger->debug('Updated the merchant reference');

        $this->acknowledgeOrder($this->mageOrder, $klarnaOrder, $klarnaOrderId, $omApi);
        $this->cancelOrder($this->mageOrder, $klarnaOrderId);

        $this->mageOrderRepository->save($this->mageOrder);
    }

    /**
     * Canceling the Klarna order if the magento order is canceled in the shop
     *
     * @param MagentoOrderInterface $order
     * @param string                $klarnaOrderId
     */
    private function cancelOrder(MagentoOrderInterface $order, string $klarnaOrderId): void
    {
        if ($order->isCanceled()) {
            $this->logger->debug('Cancel the order on the klarna side because it is canceled in the shop');
            $this->cancelKlarnaOrder($klarnaOrderId, 'Order Canceled in Magento');
        }
    }

    /**
     * @param ApiInterface $omApi
     * @param string $klarnaOrderId
     * @return DataObject
     */
    private function fetchKlarnaOrderDetails(ApiInterface $omApi, string $klarnaOrderId): DataObject
    {
        if (!isset($this->klarnaOrderDetailsCache[$klarnaOrderId])) {
            $this->klarnaOrderDetailsCache[$klarnaOrderId] = $omApi->getPlacedKlarnaOrder($klarnaOrderId);
        }

        return $this->klarnaOrderDetailsCache[$klarnaOrderId];
    }

    /**
     * @param MagentoOrderInterface $order
     * @param string $klarnaStatus
     */
    private function cancelMagentoOrder(MagentoOrderInterface $order, string $klarnaStatus): void
    {
        if (!$order->canCancel()) {
            $this->logger->info(
                'Magento order ' . $order->getIncrementId()
                . ' cannot be cancelled (state: ' . $order->getState() . ')'
            );
            return;
        }

        $order->setState(SalesOrder::STATE_CANCELED);
        $order->setStatus(SalesOrder::STATE_CANCELED);
        $order->addStatusHistoryComment(
            __('Order automatically cancelled because Klarna status is: %1', $klarnaStatus)
        );
        $this->mageOrderRepository->save($order);

        $this->logger->info(
            'Magento order ' . $order->getIncrementId() . ' cancelled due to Klarna status: ' . $klarnaStatus
        );
    }

    /**
     * Updating the Magento order with the Klarna reference
     *
     * @param OrderInterface        $klarnaOrder
     * @param MagentoOrderInterface $order
     * @param ApiInterface          $omApi
     */
    private function updateOrderWithKlarnaReference(
        OrderInterface $klarnaOrder,
        MagentoOrderInterface $order,
        ApiInterface $omApi
    ): void {
        $klarnaOrderDetails = $this->fetchKlarnaOrderDetails($omApi, $klarnaOrder->getKlarnaOrderId());

        // Add invoice to order details
        $klarnaReference = $klarnaOrderDetails->getKlarnaReference();
        if ($klarnaReference) {
            $order->getPayment()->setAdditionalInformation('klarna_reference', $klarnaReference);
        }
        //add initial payment info to order
        $initialPaymentInfo = $klarnaOrderDetails->getInitialPaymentMethod();

        if ($initialPaymentInfo && is_array($initialPaymentInfo)) {
            if (isset($initialPaymentInfo['description'])) {
                $order->getPayment()->setAdditionalInformation('method_title', $initialPaymentInfo['description']);
            }
        }

        $this->logger->debug('Updated the order with the klarna reference');
    }

    /**
     * Acknowledge order with Klarna
     *
     * @param MagentoOrderInterface $order
     * @param OrderInterface        $klarnaOrder
     * @param string                $klarnaOrderId
     * @param ApiInterface          $omApi
     * @throws \Exception
     * @throws CouldNotSaveException
     */
    private function acknowledgeOrder(
        MagentoOrderInterface $order,
        OrderInterface $klarnaOrder,
        string $klarnaOrderId,
        ApiInterface $omApi
    ): void {
        if (!$klarnaOrder->getIsAcknowledged()) {
            $response = $omApi->acknowledgeOrder($klarnaOrderId);
            if (!$response->getIsSuccessful()) {
                $extra = $response->getExtra();
                if (is_array($extra)) {
                    $this->logger->logArray($response->getExtra());
                }
                $error = $response->getError();
                if (is_array($error)) {
                    $this->logger->logArray($error);
                }

                // TODO: Consider: Should we cancel order in Magento here?
                throw new KlarnaException(__('Acknowledge call failed. Check log for details.'));
            }
            $order->addStatusHistoryComment('Acknowledged request sent to Klarna');
            $klarnaOrder->setIsAcknowledged(1);
            $this->orderRepository->save($klarnaOrder);
        }

        $this->logger->debug('Acknowledged the order');
    }

    /**
     * Adding comment to order and update status if still in payment review
     *
     * @param MagentoOrderInterface $order
     */
    private function updateOrderStatus(MagentoOrderInterface $order): void
    {
        if ($order->getState() === SalesOrder::STATE_PAYMENT_REVIEW) {
            $payment = $order->getPayment();
            $payment->update(true);
            $this->setOrderStatus($order);

            $this->logger->debug(
                'Updated the order status because it had the following status: ' . $order->getState()
            );
        }
    }

    /**
     * Update order state for a specific $orderId
     *
     * @param string $orderId
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\InputException
     */
    public function checkAndUpdateOrderState(string $orderId): void
    {
        $mageOrder = $this->mageOrderRepository->get($orderId);
        $klarnaOrder = $this->getKlarnaOrderByMagentoOrder($mageOrder);

        $orderDetails = $this->paymentStatus->getStatusUpdate($klarnaOrder);
        if (!$orderDetails->getIsSuccessful()) {
            throw new LocalizedException(__('An error happened when retrieving the status of the order from Klarna'));
        }

        $this->checkOrderState($mageOrder, $orderDetails->getStatus());
        if ($this->updateKlarnaOrder($orderDetails->getFraudStatus(), $klarnaOrder)) {
            return;
        }

        throw new LocalizedException(__('Order is still PENDING with Klarna'));
    }

    /**
     * Getting back the Klarna order
     *
     * @param MagentoOrderInterface $mageOrder
     * @return OrderInterface|null
     * @throws LocalizedException
     */
    private function getKlarnaOrderByMagentoOrder(MagentoOrderInterface $mageOrder): ?OrderInterface
    {
        try {
            return $this->orderRepository->getByOrder($mageOrder);
        } catch (NoSuchEntityException $e) {
            $this->denyPayment(
                $mageOrder,
                __(
                    'Canceled the order since no Klarna information could ' .
                    'be found in the Magento database for the order.'
                )
            );

            return null;
        }
    }

    /**
     * Updating the Klarna order if the status is not pending
     *
     * @param string         $fraudStatus
     * @param OrderInterface $klarnaOrder
     * @return bool
     * @throws LocalizedException
     */
    private function updateKlarnaOrder(string $fraudStatus, OrderInterface $klarnaOrder): bool
    {
        if ($fraudStatus !== OrderManagement::ORDER_FRAUD_STATUS_PENDING) {
            $this->updateOrderState($klarnaOrder->getKlarnaOrderId());
            return true;
        }

        return false;
    }

    /**
     * Checking the order state and denying the payment if its invalid
     *
     * @param MagentoOrderInterface $mageOrder
     * @param string                $status
     * @throws LocalizedException
     */
    private function checkOrderState(MagentoOrderInterface $mageOrder, string $status): void
    {
        $stopStatuses = [
            KcoApiInterface::ORDER_STATUS_CANCELLED,
            KcoApiInterface::ORDER_STATUS_EXPIRED
        ];

        if (in_array($status, $stopStatuses)) {
            $this->denyPayment(
                $mageOrder,
                __('Canceled the order as Klarna shows it as %1', $status)
            );
        }
    }

    /**
     * Denying the payment
     *
     * @param MagentoOrderInterface $order
     * @param Phrase                $message
     * @throws LocalizedException
     */
    private function denyPayment(MagentoOrderInterface $order, Phrase $message): void
    {
        $order->getPayment()->deny(true);
        $this->mageOrderRepository->save($order);
        throw new LocalizedException($message);
    }

    /**
     * Getting back the Klarna order
     *
     * @return ?OrderInterface
     */
    public function getKlarnaOrder(): ?OrderInterface
    {
        return $this->klarnaOrder;
    }

    /**
     * Getting back the magento order
     *
     * @return ?MagentoOrderInterface
     */
    public function getMagentoOrder(): ?MagentoOrderInterface
    {
        return $this->mageOrder;
    }
}
