<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Controller\Api;

use Klarna\Kco\Exception\CheckoutLockedException;
use Klarna\Kco\Model\Checkout\MutationGuard;
use Klarna\Kco\Model\Cart\ShippingMethod\KlarnaRequestQuoteTransformer;
use Klarna\Kss\Model\KssConfigProvider;
use Klarna\Logger\Api\LoggerInterface;
use Klarna\Base\Exception as KlarnaException;
use Klarna\Kco\Model\Checkout\Kco\Initializer;
use Klarna\Kco\Model\Checkout\Kco\Session;
use Klarna\Kco\Model\Responder\Ajax;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Klarna\Base\Controller\CsrfAbstract;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @api
 */
class UpdateKssStatus implements HttpPostActionInterface
{

    /**
     * @var Initializer
     */
    private $initializer;
    /**
     * @var Session
     */
    private $session;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Ajax
     */
    private $ajax;
    /**
     * @var KlarnaRequestQuoteTransformer
     */
    private KlarnaRequestQuoteTransformer $klarnaRequestQuoteTransformer;
    /**
     * @var KssConfigProvider
     */
    private KssConfigProvider $kssConfigProvider;
    /**
     * @var MutationGuard
     */
    private MutationGuard $mutationGuard;
    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @param LoggerInterface $logger
     * @param Initializer $initializer
     * @param Session $session
     * @param Ajax $ajax
     * @param KlarnaRequestQuoteTransformer $klarnaRequestQuoteTransformer
     * @param KssConfigProvider $kssConfigProvider
     * @param MutationGuard|null $mutationGuard
     * @param RequestInterface|null $request
     * @codeCoverageIgnore
     */
    public function __construct(
        LoggerInterface $logger,
        Initializer $initializer,
        Session $session,
        Ajax $ajax,
        KlarnaRequestQuoteTransformer $klarnaRequestQuoteTransformer,
        KssConfigProvider $kssConfigProvider,
        ?MutationGuard $mutationGuard = null,
        ?RequestInterface $request = null
    ) {
        $this->logger = $logger;
        $this->initializer = $initializer;
        $this->session = $session;
        $this->ajax = $ajax;
        $this->klarnaRequestQuoteTransformer = $klarnaRequestQuoteTransformer;
        $this->kssConfigProvider = $kssConfigProvider;
        $this->mutationGuard = $mutationGuard ?: ObjectManager::getInstance()->get(MutationGuard::class);
        $this->request = $request ?: ObjectManager::getInstance()->get(RequestInterface::class);
    }

    /**
     * This is just needed on the initial request for KSS.
     *
     * When the customer does not need to add the address anymore then we get in this class the first time
     * the information if KSS is used or not via the api. Therefore we check if there is a switch from Not-KSS to
     * KSS-is-used. In this situation we need to update the assigned shipping method.
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
                if ($this->request->getParam('origin') === 'iframe') {
                    // KUSTOM-128: the customer changed it inside the iframe, so the checkout is editable
                    $this->mutationGuard->guardIframeChange($kustomId);
                } else {
                    $this->mutationGuard->guardFrontend($kustomId);
                }
            } catch (CheckoutLockedException $e) {
                // KUSTOM-128: payment in progress or order created, the quote stays untouched
                return $this->ajax->getSummaryResponse(
                    ['error' => true, 'locked' => true, 'message' => $e->getMessage()]
                );
            }
        }

        $store = $this->session->getQuote()->getStore();
        $isKssEnabled = $this->kssConfigProvider->isKssEnabled($store);

        $data = [];
        if ($isKssEnabled || $this->session->hasActiveKlarnaShippingGatewayInformation()) {
            $this->logger->info('UpdateKssStatus: Updating the values in the Kustom table and quote');

            $oldGrandTotal = $this->session->getQuote()->getGrandTotal();

            $klarnaOrder = $this->initializer->getOrder(
                $this->session->getKlarnaQuote()->getKlarnaCheckoutId()
            );
            $this->klarnaRequestQuoteTransformer->updateQuoteShippingMethod(
                $klarnaOrder,
                $this->session->getKlarnaQuote()->getKlarnaCheckoutId()
            );

            $this->logger->info('UpdateKssStatus: Updated the values');

            $newGrandTotal = $this->session->getQuote()->getGrandTotal();
            $data['changed_grand_total'] = $oldGrandTotal !== $newGrandTotal;
        }

        return $this->ajax->getSummaryResponse($data);
    }
}
