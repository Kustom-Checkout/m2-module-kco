<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Controller\Klarna;

use Klarna\Kco\Api\QuoteRepositoryInterface;
use Klarna\Kco\Exception\CheckoutLockedException;
use Klarna\Kco\Model\Checkout\MutationGuard;
use Klarna\Base\Model\Quote\ShippingMethod\QuoteMethodHandler as QuoteShippingMethod;
use Klarna\Logger\Api\LoggerInterface;
use Klarna\Kco\Model\Checkout\Kco\Initializer as kcoInitializer;
use Klarna\Kco\Model\Responder\Ajax;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;

/**
 * This method is used when backend shipping method callbacks are not supported in the Klarna market.
 * It is for example the case when the selection of the shipping methods is happening outside of the iframe.
 *
 * @api
 */
class SaveShippingMethod implements HttpPostActionInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var kcoInitializer
     */
    private $kcoInitializer;
    /**
     * @var Ajax
     */
    private $ajax;
    /**
     * @var QuoteShippingMethod
     */
    private QuoteShippingMethod $quoteShippingMethod;
    /**
     * @var RequestInterface
     */
    private RequestInterface $request;
    /**
     * @var ManagerInterface
     */
    private ManagerInterface $messageManager;
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
     * @param kcoInitializer $kcoInitializer
     * @param Ajax $ajax
     * @param QuoteShippingMethod $quoteShippingMethod
     * @param RequestInterface $request
     * @param ManagerInterface $messageManager
     * @param MutationGuard|null $mutationGuard
     * @param QuoteRepositoryInterface|null $klarnaQuoteRepository
     * @codeCoverageIgnore
     */
    public function __construct(
        LoggerInterface $logger,
        kcoInitializer $kcoInitializer,
        Ajax $ajax,
        QuoteShippingMethod $quoteShippingMethod,
        RequestInterface $request,
        ManagerInterface $messageManager,
        ?MutationGuard $mutationGuard = null,
        ?QuoteRepositoryInterface $klarnaQuoteRepository = null
    ) {
        $this->logger              = $logger;
        $this->kcoInitializer      = $kcoInitializer;
        $this->ajax                = $ajax;
        $this->quoteShippingMethod = $quoteShippingMethod;
        $this->request = $request;
        $this->messageManager = $messageManager;
        $this->mutationGuard = $mutationGuard ?: ObjectManager::getInstance()->get(MutationGuard::class);
        $this->klarnaQuoteRepository = $klarnaQuoteRepository
            ?: ObjectManager::getInstance()->get(QuoteRepositoryInterface::class);
    }

    /**
     * Saving the shipping method
     *
     * @return ResultInterface
     */
    public function execute()
    {
        try {
            $body = json_decode($this->request->getContent(), true);
            $quote = $this->kcoInitializer->getKcoSession()->getQuote();
            $method = $body['carrier_code'] . '_' . $body['method_code'];

            // KUSTOM-128: saving the method the quote already has changes nothing (e.g. a page reload)
            if ($quote->getShippingAddress()->getShippingMethod() !== $method) {
                $kustomId = $this->getKustomId($quote);
                if ($kustomId !== '') {
                    $this->mutationGuard->guardFrontend($kustomId);
                }
            }

            $this->quoteShippingMethod->updateShippingMethod($quote, $method);
        } catch (CheckoutLockedException $e) {
            // KUSTOM-128: payment in progress or order created, the quote stays untouched
            return $this->ajax->getSummaryResponse(
                ['error' => true, 'locked' => true, 'message' => $e->getMessage()]
            );
        } catch (LocalizedException $e) {
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(__(
                'An issue occurred when saving the shipping method.'
            ));
        }

        return $this->ajax->getSummaryResponse();
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
        } catch (NoSuchEntityException $e) {
            return '';
        }
    }
}
