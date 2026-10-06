<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Checkout\Kco;

use Klarna\Logger\Api\LoggerInterface;
use Klarna\Kco\Api\QuoteInterface;
use Klarna\Kco\Model\Quote;
use Klarna\Kco\Model\QuoteFactory as KlarnaQuoteFactory;
use Klarna\Kco\Api\QuoteRepositoryInterface as KlarnaQuoteRepositoryInterface;
use Klarna\Kss\Model\ShippingMethodGateway;
use Klarna\Kss\Model\ShippingMethodGatewayRepository;
use Klarna\Kss\Model\Validator;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteRepository as MageQuoteRepository;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * Class Session provide method for checkout sessions
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 * @internal
 */
class Session
{
    /**
     * @var QuoteInterface
     */
    private $klarnaQuote;

    /**
     * @var CartInterface
     */
    private $quote;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var MageQuoteRepository
     */
    private $quoteRepository;

    /**
     * @var KlarnaQuoteRepositoryInterface
     */
    private $klarnaQuoteRepository;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /** @var LoggerInterface $logger */
    private $logger;

    /** @var ShippingMethodGateway $shippingGateway */
    private $shippingGateway;

    /** @var ShippingMethodGatewayRepository $gatewayRepository */
    private $gatewayRepository;
    /**
     * @var Validator
     */
    private $validator;
    /**
     * @var KlarnaQuoteFactory
     */
    private $klarnaQuoteFactory;
    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;

    /**
     * Session constructor.
     *
     * @param CheckoutSession                 $checkoutSession
     * @param CustomerSession                 $customerSession
     * @param KlarnaQuoteRepositoryInterface  $klarnaQuoteRepository
     * @param MageQuoteRepository             $quoteRepository
     * @param LoggerInterface                 $logger
     * @param ShippingMethodGatewayRepository $shippingGatewayRepository
     * @param Validator                       $validator
     * @param KlarnaQuoteFactory              $klarnaQuoteFactory
     * @param CartRepositoryInterface         $cartRepository
     * @codeCoverageIgnore
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        KlarnaQuoteRepositoryInterface $klarnaQuoteRepository,
        MageQuoteRepository $quoteRepository,
        LoggerInterface $logger,
        ShippingMethodGatewayRepository $shippingGatewayRepository,
        Validator $validator,
        KlarnaQuoteFactory $klarnaQuoteFactory,
        CartRepositoryInterface $cartRepository
    ) {
        $this->checkoutSession       = $checkoutSession;
        $this->customerSession       = $customerSession;
        $this->klarnaQuoteRepository = $klarnaQuoteRepository;
        $this->quoteRepository       = $quoteRepository;
        $this->logger                = $logger;
        $this->gatewayRepository     = $shippingGatewayRepository;
        $this->validator             = $validator;
        $this->klarnaQuoteFactory    = $klarnaQuoteFactory;
        $this->cartRepository        = $cartRepository;
    }

    /**
     * Getting back the Kss validator instance
     *
     * @return Validator
     */
    public function getKssValidator(): Validator
    {
        return $this->validator;
    }

    /**
     * Get frontend checkout session object
     *
     * @return CheckoutSession
     */
    public function getCheckout()
    {
        return $this->checkoutSession;
    }

    /**
     * Set Klarnaquote object
     *
     * @param QuoteInterface $klarnaQuote
     *
     * @return $this
     */
    public function setKlarnaQuote($klarnaQuote)
    {
        $this->klarnaQuote = $klarnaQuote;

        return $this;
    }

    /**
     * Quote object getter
     *
     * @return CartInterface
     */
    public function getQuote(): ?CartInterface
    {
        if ($this->quote === null) {
            try {
                $magentoQuoteId = $this->checkoutSession->getQuoteId();
                if (!$magentoQuoteId) {
                    $magentoQuote = $this->checkoutSession->getQuote();
                } else {
                    $magentoQuote = $this->cartRepository->get($magentoQuoteId);
                }

                $this->quote = $magentoQuote;
            } catch (\LogicException $e) {
                return null;
            }
        }

        return $this->quote;
    }

    /**
     * Declare checkout quote instance
     *
     * @param CartInterface $quote
     *
     * @return $this
     */
    public function setQuote(CartInterface $quote)
    {
        $this->quote = $quote;

        return $this;
    }

    /**
     * Get Klarna quote object based off current checkout quote. If it could not be found create it
     *
     * @return QuoteInterface
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getKlarnaQuote(): ?QuoteInterface
    {
        if ($this->klarnaQuote !== null) {
            return $this->klarnaQuote;
        }
        try {
            $this->klarnaQuote = $this->klarnaQuoteRepository->getActiveByQuote($this->getQuote());
        } catch (NoSuchEntityException $e) {
            $this->klarnaQuote = $this->createKlarnaQuote();
        }

        return $this->klarnaQuote;
    }

    /**
     * Getting back the shipping method gateway
     *
     * @return null|ShippingMethodGateway
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getKlarnaShippingGateway(): ?ShippingMethodGateway
    {
        if ($this->shippingGateway !== null) {
            return $this->shippingGateway;
        }

        $klarnaQuote = $this->getKlarnaQuote();

        if ($klarnaQuote->getKlarnaCheckoutId() === null) {
            return null;
        }

        try {
            $this->shippingGateway = $this->gatewayRepository->loadShipping(
                $klarnaQuote->getKlarnaCheckoutId(),
                'klarna_session_id'
            );
        } catch (NoSuchEntityException $e) {
            return null;
        }
        return $this->shippingGateway;
    }

    /**
     * Returns true when we have shipping gateway information and they can be used
     *
     * @return bool
     */
    public function hasActiveKlarnaShippingGatewayInformation(): bool
    {
        if ($this->validator->getKssUsedFlag() !== $this->validator::CHECK_NOT_SET) {
            return $this->validator->getKssUsedFlag() > 0;
        }

        $quote = $this->getQuote();
        if ($quote === null) {
            $this->validator->setKssUsed($this->validator::CHECK_RESULT_NOT_USED);
            return false;
        }

        try {
            if ($this->klarnaQuote === null) {
                $this->klarnaQuote = $this->klarnaQuoteRepository->getActiveByQuote($quote);
            }
        } catch (NoSuchEntityException $e) {
            $this->validator->setKssUsed($this->validator::CHECK_RESULT_NOT_USED);
            return false;
        }

        if (!$this->klarnaQuote->getIsActive()) {
            $this->validator->setKssUsed($this->validator::CHECK_RESULT_NOT_USED);
            return false;
        }

        $checkoutId = $this->klarnaQuote->getKlarnaCheckoutId();

        if ($checkoutId === null) {
            $this->validator->setKssUsed($this->validator::CHECK_RESULT_NOT_USED);
            return false;
        }

        $result = $this->validator->isKssUsed($quote->getStore(), $this->klarnaQuote->getKlarnaCheckoutId());
        if ($result) {
            $this->validator->setKssUsed($this->validator::CHECK_RESULT_USED);
            return true;
        }

        $this->validator->setKssUsed($this->validator::CHECK_RESULT_NOT_USED);
        return false;
    }

    /**
     * Set the Klarna checkout id
     *
     * @param string $klarnaCheckoutId
     *
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setKlarnaQuoteKlarnaCheckoutId($klarnaCheckoutId)
    {
        $klarnaCheckoutId = trim($klarnaCheckoutId);

        if ('' === $klarnaCheckoutId) {
            return $this;
        }

        $klarnaQuote = $this->getKlarnaQuote();

        if (!$klarnaQuote->getId()) {
            $klarnaQuote = $this->createKlarnaQuote($klarnaCheckoutId);
        }
        if ($klarnaQuote->getKlarnaCheckoutId() === null) {
            $klarnaQuote->setKlarnaCheckoutId($klarnaCheckoutId);
            $this->klarnaQuoteRepository->save($klarnaQuote);
        }
        if ($klarnaQuote->getKlarnaCheckoutId() !== $klarnaCheckoutId) {
            $klarnaQuote->setIsActive(0);
            $this->klarnaQuoteRepository->save($klarnaQuote);

            $klarnaQuote = $this->createKlarnaQuote($klarnaCheckoutId);
        }

        $this->setKlarnaQuote($klarnaQuote);

        return $this;
    }

    /**
     * Create a new klarna quote object
     *
     * @param string $klarnaCheckoutId
     *
     * @return QuoteInterface
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    private function createKlarnaQuote($klarnaCheckoutId = null)
    {
        $data = [
            'klarna_checkout_id' => $klarnaCheckoutId,
            'is_active' => 1,
            'quote_id' => $this->getQuote()->getId(),
        ];
        /** @var Quote $klarnaQuote */
        $klarnaQuote = $this->klarnaQuoteFactory->create();
        $klarnaQuote->setData($data);
        $this->klarnaQuoteRepository->save($klarnaQuote);

        return $klarnaQuote;
    }

    /**
     * Get customer session object
     *
     * @return \Magento\Customer\Model\Session
     */
    public function getCustomerSession()
    {
        return $this->customerSession;
    }
}
