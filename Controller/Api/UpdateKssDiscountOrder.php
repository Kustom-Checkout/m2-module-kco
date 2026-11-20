<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Controller\Api;

use Klarna\Base\Model\Quote\SalesRule;
use Klarna\Logger\Api\LoggerInterface;
use Klarna\Base\Exception as KlarnaException;
use Klarna\Kco\Exception\CheckoutLockedException;
use Klarna\Kco\Model\Checkout\Kco\Initializer;
use Klarna\Kco\Model\Checkout\MutationGuard;
use Klarna\Kco\Model\Checkout\Kco\Session;
use Klarna\Kco\Model\Responder\Ajax;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Klarna\Base\Controller\CsrfAbstract;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @api
 */
class UpdateKssDiscountOrder implements HttpPostActionInterface
{
    /**
     * @var Initializer
     */
    private Initializer $initializer;
    /**
     * @var Session
     */
    private Session $session;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * @var Ajax
     */
    private Ajax $ajax;
    /**
     * @var SalesRule
     */
    private SalesRule $salesRule;
    /**
     * @var MutationGuard
     */
    private MutationGuard $mutationGuard;

    /**
     * @param LoggerInterface    $logger
     * @param Initializer        $initializer
     * @param Session            $session
     * @param Ajax               $ajax
     * @param SalesRule          $salesRule
     * @param MutationGuard|null $mutationGuard
     * @codeCoverageIgnore
     */
    public function __construct(
        LoggerInterface $logger,
        Initializer $initializer,
        Session $session,
        Ajax $ajax,
        SalesRule $salesRule,
        ?MutationGuard $mutationGuard = null
    ) {
        $this->logger      = $logger;
        $this->initializer = $initializer;
        $this->session     = $session;
        $this->ajax        = $ajax;
        $this->salesRule   = $salesRule;
        $this->mutationGuard = $mutationGuard ?: ObjectManager::getInstance()->get(MutationGuard::class);
    }
    /**
     * Performing the update
     *
     * @return ResultInterface
     * @throws KlarnaException
     * @throws LocalizedException
     */
    public function execute()
    {
        try {
            return $this->executeGuarded();
        } finally {
            // KUSTOM-128: the guard keeps the order creation lock until here
            $this->mutationGuard->releaseAll();
        }
    }

    /**
     * Run the action
     *
     * @return mixed
     */
    private function executeGuarded()
    {
        $this->logger->info('UpdateKssStatus: Start');

        $kustomId = (string) $this->session->getKlarnaQuote()->getKlarnaCheckoutId();
        if ($kustomId !== '') {
            try {
                $this->mutationGuard->guardFrontend($kustomId);
            } catch (CheckoutLockedException $e) {
                // KUSTOM-128: payment in progress or order created, the quote stays untouched
                return $this->ajax->getSummaryResponse(
                    ['error' => true, 'locked' => true, 'message' => $e->getMessage()]
                );
            }
        }

        $data = [];
        if ($this->session->hasActiveKlarnaShippingGatewayInformation()) {
            $this->logger->info('UpdateKssStatus: KSS is used');

            if ($this->salesRule->isApplyToShippingUsed($this->session->getQuote())) {
                $this->logger->info('UpdateKssStatus: Discount applied to shipping is used');

                $result = $this->initializer->updateKlarnaTotals();
                $data['html_snippet'] = $result->getLatestKlarnaCheckout()->getHtmlSnippet();
            }
        }

        return $this->ajax->getSummaryResponse($data);
    }
}
