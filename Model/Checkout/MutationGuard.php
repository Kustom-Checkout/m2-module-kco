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
use Klarna\Kco\Model\Order\CreationLock;
use Klarna\Kco\Model\Order\Order as CheckoutOrder;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * KUSTOM-128: protects the quote between the validate callback and the order creation
 *
 * Every KCO controller which changes the quote asks this guard first. A refusal is a
 * CheckoutLockedException, the controller decides how to answer.
 *
 * Lock handling: the creation lock is acquired with timeout 0 and kept after a successful guard, so Push and
 * Confirmation cannot start creating the order while the mutation runs. The controller must call releaseAll() in
 * a finally block. A refusal releases the lock before the exception is thrown. Push then waits (CreationLock
 * acquire timeout) or answers 503, which is acceptable because a mutation is short.
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
     * @var CreationLock
     */
    private CreationLock $creationLock;
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
     * Kustom ids whose creation lock this guard acquired and has not released yet
     *
     * @var bool[]
     */
    private array $heldLocks = [];

    /**
     * @param QuoteRepositoryInterface $quoteRepository
     * @param CheckoutOrder $checkoutOrder
     * @param CreationLock $creationLock
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
        CreationLock $creationLock,
        Checkout $checkoutApi,
        CartRepositoryInterface $cartRepository,
        DateTime $dateTime,
        LoggerInterface $logger,
        ValidatedMarker $validatedMarker,
        int $blockWindowSeconds = self::DEFAULT_BLOCK_WINDOW_SECONDS
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->checkoutOrder = $checkoutOrder;
        $this->creationLock = $creationLock;
        $this->checkoutApi = $checkoutApi;
        $this->cartRepository = $cartRepository;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
        $this->validatedMarker = $validatedMarker;
        $this->blockWindowSeconds = $blockWindowSeconds;
    }

    /**
     * Guard for the callbacks Kustom sends (address_update, shipping_option_update)
     *
     * Kustom only sends them while the customer edits in the iframe, so the checkout was reopened: the marker
     * is cleared. No API call, they have a 10 s budget.
     *
     * @param string $kustomId
     * @return void
     * @throws CheckoutLockedException
     */
    public function guardKustomCallback(string $kustomId): void
    {
        $this->guardReopenedCheckout($kustomId);
    }

    /**
     * Guard for changes the customer makes inside the Kustom iframe (shipping option, address)
     *
     * The customer can only edit inside the iframe while Kustom has the checkout editable, so such a change means
     * the checkout is open again: same rule as for the Kustom callbacks, the 300 s window does not apply and the
     * marker is cleared. An existing order or a running order creation still refuses. Changes made on the Magento
     * page itself must use guardFrontend().
     *
     * @param string $kustomId
     * @return void
     * @throws CheckoutLockedException
     */
    public function guardIframeChange(string $kustomId): void
    {
        $this->guardReopenedCheckout($kustomId);
    }

    /**
     * Refuse on order/creation, otherwise keep the lock and clear the marker (a failure is logged, not thrown)
     *
     * @param string $kustomId
     * @return void
     * @throws CheckoutLockedException
     */
    private function guardReopenedCheckout(string $kustomId): void
    {
        $this->assertNoOrderAndNoCreation($kustomId);

        try {
            $quote = $this->loadQuote($kustomId);
            if ($quote !== null && $quote->getValidatedAt() !== null) {
                $this->validatedMarker->update($kustomId, null);
            }
        } catch (\Throwable $e) {
            // a failure here must never turn the Kustom callback into an error
            $this->logger->warning('Mutation guard: could not clear the validated marker: ' . $e->getMessage());
        }
    }

    /**
     * Release the creation lock this guard acquired for the Kustom id (idempotent)
     *
     * Controllers call this in a finally block.
     *
     * @param string $kustomId
     * @return void
     */
    public function release(string $kustomId): void
    {
        if (!isset($this->heldLocks[$kustomId])) {
            return;
        }
        unset($this->heldLocks[$kustomId]);
        $this->creationLock->release($kustomId);
    }

    /**
     * Release every creation lock this guard still holds (idempotent)
     *
     * Controllers call this in a finally block: the Kustom id can change during a request (a new checkout is
     * created), so releasing by the id read at the end could leak the lock of the id read at the start.
     *
     * @return void
     */
    public function releaseAll(): void
    {
        foreach (array_keys($this->heldLocks) as $kustomId) {
            $this->release((string) $kustomId);
        }
    }

    /**
     * Guard for the checkout page and KSS requests
     *
     * @param string $kustomId
     * @return void
     * @throws CheckoutLockedException
     */
    public function guardFrontend(string $kustomId): void
    {
        $this->assertNoOrderAndNoCreation($kustomId);

        try {
            $this->assertFrontendAllowed($kustomId);
        } catch (\Throwable $e) {
            $this->release($kustomId);
            throw $e;
        }
    }

    /**
     * Check the marker window and the Kustom status
     *
     * @param string $kustomId
     * @return void
     * @throws CheckoutLockedException
     */
    private function assertFrontendAllowed(string $kustomId): void
    {
        $quote = $this->loadQuote($kustomId);
        $validatedAt = $quote !== null ? $quote->getValidatedAt() : null;
        if ($validatedAt === null) {
            return;
        }

        $age = $this->dateTime->gmtTimestamp() - $this->toTimestamp($validatedAt);
        if ($age < $this->blockWindowSeconds) {
            $this->logger->debug('Mutation guard: refused, validated ' . $age . 's ago: ' . $kustomId);
            throw new CheckoutLockedException();
        }

        $status = $this->getKustomStatus($quote, $kustomId);
        if (in_array($status, self::COMPLETED_STATUSES, true)) {
            $this->logger->debug('Mutation guard: refused, checkout status ' . $status . ': ' . $kustomId);
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
     * Refuse when the Magento order exists or an order creation is running
     *
     * @param string $kustomId
     * @return void
     * @throws CheckoutLockedException
     */
    private function assertNoOrderAndNoCreation(string $kustomId): void
    {
        if ($this->checkoutOrder->isMagentoOrderExists($kustomId)) {
            $this->logger->debug('Mutation guard: refused, order exists: ' . $kustomId);
            throw new CheckoutLockedException();
        }

        if (!$this->creationLock->acquire($kustomId, 0)) {
            $this->logger->debug('Mutation guard: refused, order creation running: ' . $kustomId);
            throw new CheckoutLockedException();
        }
        // kept until release()
        $this->heldLocks[$kustomId] = true;
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
