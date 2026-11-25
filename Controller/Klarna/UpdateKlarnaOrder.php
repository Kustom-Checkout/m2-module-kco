<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Controller\Klarna;

use Exception;
use Klarna\Kco\Api\QuoteRepositoryInterface;
use Klarna\Kco\Exception\CheckoutLockedException;
use Klarna\Kco\Model\Checkout\MutationGuard;
use Klarna\Kco\Model\Responder\Ajax;
use Klarna\Kco\Model\Checkout\Kco\Initializer;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;

/**
 * Sending a Klarna order update request for the current state of the quote
 *
 * @api
 */
class UpdateKlarnaOrder implements HttpPostActionInterface
{
    /**
     * @var ManagerInterface
     */
    private $manager;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Initializer
     */
    private $initializer;
    /**
     * @var Ajax
     */
    private $ajax;
    /**
     * @var MutationGuard
     */
    private MutationGuard $mutationGuard;
    /**
     * @var QuoteRepositoryInterface
     */
    private QuoteRepositoryInterface $klarnaQuoteRepository;

    /**
     * @param LoggerInterface $logger
     * @param Initializer $initializer
     * @param ManagerInterface $manager
     * @param Ajax $ajax
     * @param MutationGuard|null $mutationGuard
     * @param QuoteRepositoryInterface|null $klarnaQuoteRepository
     * @codeCoverageIgnore
     */
    public function __construct(
        LoggerInterface $logger,
        Initializer $initializer,
        ManagerInterface $manager,
        Ajax $ajax,
        ?MutationGuard $mutationGuard = null,
        ?QuoteRepositoryInterface $klarnaQuoteRepository = null
    ) {
        $this->logger         = $logger;
        $this->initializer    = $initializer;
        $this->manager        = $manager;
        $this->ajax           = $ajax;
        $this->mutationGuard  = $mutationGuard ?: ObjectManager::getInstance()->get(MutationGuard::class);
        $this->klarnaQuoteRepository = $klarnaQuoteRepository
            ?: ObjectManager::getInstance()->get(QuoteRepositoryInterface::class);
    }

    /**
     * Updating the Klarna order
     *
     * @return Json|ResultInterface
     */
    public function execute()
    {
        $kustomId = '';
        $this->logger->debug('UpdateKlarnaOrder: start');
        try {
            $kcoSession = $this->initializer->getKcoSession();
            $kustomId = $this->getKustomId($kcoSession->getQuote());
            if ($kustomId !== '') {
                $this->mutationGuard->guardFrontend($kustomId);
            }
            $oldCheckoutId = (string)$kcoSession->getKlarnaQuote()->getKlarnaCheckoutId();
            $oldRecurring = (bool)$kcoSession->getKlarnaQuote()->getData('is_recurring');
            $this->initializer->updateKlarnaTotals();
            $newCheckoutId = (string)$kcoSession->getKlarnaQuote()->getKlarnaCheckoutId();
            $newRecurring = (bool)$kcoSession->getKlarnaQuote()->getData('is_recurring');

            $data = [];
            if ($oldCheckoutId !== '' && $oldCheckoutId !== $newCheckoutId && $oldRecurring !== $newRecurring) {
                // The Kustom order was recreated because the recurring state flipped: render the new one.
                // An expired-order recreate with an unchanged recurring state keeps the previous response.
                $data['html_snippet'] = $this->initializer->getLatestKlarnaCheckout()->getHtmlSnippet();
            }
            return $this->ajax->getSummaryResponse($data);
        } catch (CheckoutLockedException $e) {
            // KUSTOM-128: payment in progress or order created, the quote stays untouched
            $this->logger->info('UpdateKlarnaOrder: refused, checkout locked, Kustom checkout id: ' . $kustomId);
            return $this->ajax->getSummaryResponse(
                ['error' => true, 'locked' => true, 'message' => $e->getMessage()]
            );
        } catch (Exception $e) {
            $this->logger->critical($e);
            $this->manager->addErrorMessage($e->getMessage());

            return $this->ajax->getSummaryResponse([
                'error' => true
            ]);
        }
    }

    /**
     * Kustom checkout id of the active Kustom quote, empty if there is none (no row is created)
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return string
     */
    private function getKustomId($quote): string
    {
        if ($quote === null) {
            return '';
        }

        try {
            return (string) $this->klarnaQuoteRepository->getActiveByQuote($quote)->getKlarnaCheckoutId();
        } catch (\Throwable $e) {
            // Fail-open: without a Kustom id the guard is skipped, as before KUSTOM-128
            $this->logger->warning(
                'UpdateKlarnaOrder: Kustom checkout id lookup failed, mutation guard skipped: ' . $e->getMessage()
            );

            return '';
        }
    }
}
