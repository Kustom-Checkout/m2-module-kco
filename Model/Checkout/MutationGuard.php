<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Checkout;

use Klarna\Kco\Api\QuoteRepositoryInterface;
use Klarna\Kco\Exception\CheckoutLockedException;
use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Klarna\Kco\Model\Order\Order as CheckoutOrder;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * KUSTOM-128: protects the quote between the validate callback and the order creation
 *
 * The controllers which change the quote from the Magento page ask this guard first. A refusal is a
 * CheckoutLockedException, the controller decides how to answer. Any other error inside the guard is logged and
 * ignored (fail-open): the request then continues as it did before the guard existed.
 */
class MutationGuard
{
    /**
     * Seconds after a successful validate during which the frontend must not change the quote
     */
    public const DEFAULT_BLOCK_WINDOW_SECONDS = 300;

    private const COMPLETED_STATUSES = ['checkout_complete', 'created'];

    /**
     * @var QuoteRepositoryInterface
     */
    private QuoteRepositoryInterface $quoteRepository;
    /**
     * @var CheckoutOrder
     */
    private CheckoutOrder $checkoutOrder;
    /**
     * @var Checkout
     */
    private Checkout $checkoutApi;
    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;
    /**
     * @var DateTime
     */
    private DateTime $dateTime;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * @var ValidatedMarker
     */
    private ValidatedMarker $validatedMarker;
    /**
     * @var int
     */
    private int $blockWindowSeconds;

    /**
     * @param QuoteRepositoryInterface $quoteRepository
     * @param CheckoutOrder $checkoutOrder
     * @param Checkout $checkoutApi
     * @param CartRepositoryInterface $cartRepository
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     * @param ValidatedMarker $validatedMarker
     * @param int $blockWindowSeconds
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        QuoteRepositoryInterface $quoteRepository,
        CheckoutOrder $checkoutOrder,
        Checkout $checkoutApi,
        CartRepositoryInterface $cartRepository,
        DateTime $dateTime,
        LoggerInterface $logger,
        ValidatedMarker $validatedMarker,
        int $blockWindowSeconds = self::DEFAULT_BLOCK_WINDOW_SECONDS
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->checkoutOrder = $checkoutOrder;
        $this->checkoutApi = $checkoutApi;
        $this->cartRepository = $cartRepository;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
        $this->validatedMarker = $validatedMarker;
        $this->blockWindowSeconds = $blockWindowSeconds;
    }

    /**
     * Guard for the changes made on the Magento page
     *
     * @param string $kustomId
     * @return void
     * @throws CheckoutLockedException
     */
    public function guardFrontend(string $kustomId): void
    {
        try {
            $this->assertFrontendAllowed($kustomId);
        } catch (CheckoutLockedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // fail-open: a problem of the guard itself must never turn into an error for the customer
            $this->logger->error('Mutation guard: check failed, request allowed: ' . $e->getMessage());
        }
    }

    /**
     * Check the order, the marker window and the Kustom status
     *
     * @param string $kustomId
     * @return void
     * @throws CheckoutLockedException
     */
    private function assertFrontendAllowed(string $kustomId): void
    {
        if ($this->checkoutOrder->isMagentoOrderExists($kustomId)) {
            $this->logger->info('Mutation guard: refused, order exists: ' . $kustomId);
            throw new CheckoutLockedException();
        }

        $quote = $this->loadQuote($kustomId);
        $validatedAt = $quote !== null ? $quote->getValidatedAt() : null;
        if ($validatedAt === null) {
            $this->logger->debug('Mutation guard: allowed, not validated: ' . $kustomId);
            return;
        }

        $age = $this->dateTime->gmtTimestamp() - $this->toTimestamp($validatedAt);
        if ($age < $this->blockWindowSeconds) {
            $this->logger->info('Mutation guard: refused, validated ' . $age . 's ago: ' . $kustomId);
            throw new CheckoutLockedException();
        }

        $status = $this->getKustomStatus($quote, $kustomId);
        if (in_array($status, self::COMPLETED_STATUSES, true)) {
            $this->logger->info('Mutation guard: refused, checkout status ' . $status . ': ' . $kustomId);
            throw new CheckoutLockedException();
        }
        if ($status !== 'checkout_incomplete') {
            $this->logger->error('Mutation guard: unexpected checkout status, refused: ' . $kustomId);
            throw new CheckoutLockedException();
        }

        $this->logger->warning(
            'Mutation guard: validated marker older than ' . $this->blockWindowSeconds
            . 's and checkout still incomplete, marker cleared: ' . $kustomId
        );
        $this->validatedMarker->update($kustomId, null);
    }

    /**
     * Loading the Kustom quote row, null if there is none (nothing to protect)
     *
     * @param string $kustomId
     * @return \Klarna\Kco\Model\Quote|null
     */
    private function loadQuote(string $kustomId)
    {
        try {
            return $this->quoteRepository->getByCheckoutId($kustomId);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Reading the status from Kustom, a failure refuses (fail safe: a completed checkout must not be changed)
     *
     * @param \Klarna\Kco\Model\Quote $quote
     * @param string $kustomId
     * @return string
     * @throws CheckoutLockedException
     */
    private function getKustomStatus($quote, string $kustomId): string
    {
        try {
            $magentoQuote = $this->cartRepository->get($quote->getQuoteId());
            // Uses the current store: the frontend request runs in the store of the quote
            $response = $this->checkoutApi->getOrder($kustomId, $magentoQuote->getBaseCurrencyCode());
        } catch (\Throwable $e) {
            $this->logger->error('Mutation guard: status check failed, refused: ' . $e->getMessage());
            throw new CheckoutLockedException(null, $e instanceof \Exception ? $e : null);
        }

        return (string) ($response['status'] ?? '');
    }

    /**
     * Converting a UTC datetime string into a timestamp
     *
     * @param string $utcDateTime
     * @return int
     */
    private function toTimestamp(string $utcDateTime): int
    {
        return (new \DateTimeImmutable($utcDateTime, new \DateTimeZone('UTC')))->getTimestamp();
    }
}
