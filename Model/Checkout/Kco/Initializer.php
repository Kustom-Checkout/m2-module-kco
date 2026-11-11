<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Checkout\Kco;

use Klarna\Kco\Api\ApiInterface;
use Klarna\Kco\Api\RecurringResolverInterface;
use Klarna\Kco\Model\Api\Factory;
use Klarna\Kco\Model\Checkout\Kco\Session as KcoSession;
use Klarna\Kco\Api\QuoteRepositoryInterface as KlarnaQuoteRepositoryInterface;
use Klarna\Base\Exception as KlarnaException;
use Magento\Framework\DataObject;
use Magento\Store\Api\Data\StoreInterface;
use Klarna\Kss\Model\Checkout\Update as KssUpdate;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Klarna\Kss\Model\Factory as KssFactory;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Framework\App\ObjectManager;

/**
 * Class Initializer provides initialize methods
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @internal
 */
class Initializer
{
    public const METHOD_GUEST = 'guest';

    /**
     * @var \Klarna\Kco\Model\Checkout\Kco\Session
     */
    private $kcoSession;

    /**
     * @var Factory
     */
    private $factory;

    /**
     * @var DataObject
     */
    private $klarnaCheckout;

    /**
     * @var KlarnaQuoteRepositoryInterface
     */
    private $klarnaQuoteRepository;

    /**
     * @var ApiInterface
     */
    private $apiInstance;

    /**
     * @var KssUpdate
     */
    private $kssUpdate;
    /**
     * @var KssFactory
     */
    private KssFactory $kssFactory;
    /**
     * @var RecurringResolverInterface
     */
    private RecurringResolverInterface $recurringResolver;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Initializer constructor.
     *
     * @param KcoSession                      $session
     * @param Factory                         $factory
     * @param KlarnaQuoteRepositoryInterface  $klarnaQuoteRepository
     * @param KssUpdate                       $kssUpdate
     * @param KssFactory                      $kssFactory
     * @param RecurringResolverInterface|null $recurringResolver
     * @param LoggerInterface|null            $logger
     * @codeCoverageIgnore
     */
    public function __construct(
        KcoSession $session,
        Factory $factory,
        KlarnaQuoteRepositoryInterface $klarnaQuoteRepository,
        KssUpdate $kssUpdate,
        KssFactory $kssFactory,
        ?RecurringResolverInterface $recurringResolver = null,
        ?LoggerInterface $logger = null
    ) {
        $this->kcoSession               = $session;
        $this->factory                  = $factory;
        $this->klarnaQuoteRepository    = $klarnaQuoteRepository;
        $this->kssUpdate                = $kssUpdate;
        $this->kssFactory               = $kssFactory;

        // TODO: Remove OM usage in next major release, done for backwards compatibility
        $this->recurringResolver = $recurringResolver ?: ObjectManager::getInstance()->get(
            RecurringResolverInterface::class
        );
        $this->logger = $logger ?: ObjectManager::getInstance()->get(LoggerInterface::class);
    }

    /**
     * Getting back the kco session object
     *
     * @return Session
     */
    public function getKcoSession(): Session
    {
        return $this->kcoSession;
    }

    /**
     * Update state of cart to Klarna
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateKlarnaTotals()
    {
        $klarnaCheckoutId = $this->kcoSession->getKlarnaQuote()->getKlarnaCheckoutId();
        $this->updateKlarnaCheckout($klarnaCheckoutId);
        return $this;
    }

    /**
     * Getting back the klarna checkout status from the latest request
     *
     * @return DataObject
     */
    public function getLatestKlarnaCheckout(): DataObject
    {
        return $this->klarnaCheckout;
    }

    /**
     * Update Klarna checkout
     *
     * Will create checkout order in the Klarna API
     *
     * @param string|null $klarnaCheckoutId
     * @return DataObject
     * @throws KlarnaException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateKlarnaCheckout($klarnaCheckoutId = null)
    {
        $isRecurring = $this->recurringResolver->isRecurring($this->kcoSession->getQuote());
        $klarnaQuote = $this->kcoSession->getKlarnaQuote();
        $storedCheckoutId = (string)$klarnaQuote->getKlarnaCheckoutId();
        if ($storedCheckoutId !== '' && (bool)$klarnaQuote->getData('is_recurring') !== $isRecurring) {
            // The offered payment methods differ between recurring and non-recurring orders: recreate.
            $checkout = $this->createKlarnaCheckout();
            $this->logger->info(sprintf(
                'Kustom order recreated because the recurring state changed. Quote id: %s, '
                . 'old Kustom order id: %s, new Kustom order id: %s, recurring: %s',
                (string)$this->kcoSession->getQuote()->getId(),
                $storedCheckoutId,
                (string)$this->kcoSession->getKlarnaQuote()->getKlarnaCheckoutId(),
                $isRecurring ? 'true' : 'false'
            ));
            return $checkout;
        }

        $this->klarnaCheckout = $this->getApiInstance($this->kcoSession->getQuote()->getStore())
            ->updateOrder($klarnaCheckoutId);

        $this->updateKssValues();
        $this->setKlarnaOrderid();
        $this->setRecurringState($isRecurring);
        $this->saveKlarnaQuote();
        return $this->klarnaCheckout;
    }

    /**
     * Load Klarna checkout
     *
     * @param string|null $klarnaCheckoutId
     * @return DataObject
     * @throws KlarnaException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function loadKlarnaCheckout($klarnaCheckoutId = null)
    {
        $this->klarnaCheckout = $this->getApiInstance($this->kcoSession->getQuote()->getStore())
            ->retrieveOrder($this->kcoSession->getQuote()->getBaseCurrencyCode(), $klarnaCheckoutId);

        $this->setKlarnaOrderid();
        return $this->klarnaCheckout;
    }

    /**
     * Initialize Klarna checkout with creating new instance
     *
     * Will create checkout order in the Klarna API
     *
     * @return DataObject
     * @throws KlarnaException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createKlarnaCheckout()
    {
        $this->klarnaCheckout = $this->getApiInstance($this->kcoSession->getQuote()->getStore())
            ->createOrder();

        $this->updateKssValues();
        $this->setKlarnaOrderid();
        $this->setRecurringState($this->recurringResolver->isRecurring($this->kcoSession->getQuote()));
        $this->saveKlarnaQuote();
        return $this->klarnaCheckout;
    }

    /**
     * Updating the KSS values in the database with the latest status of the api response
     *
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function updateKssValues(): void
    {
        if (!isset($this->klarnaCheckout['order_id'])) {
            return;
        }

        if ($this->kssUpdate->hasKssShippingStructure($this->klarnaCheckout)) {
            $this->kssUpdate->updateStatusByApiResponse(
                $this->klarnaCheckout,
                $this->kssFactory->create($this->klarnaCheckout['order_id'])
            );
        }
    }

    /**
     * Get api instance
     *
     * @param StoreInterface|null $store
     * @return ApiInterface
     * @throws KlarnaException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \RuntimeException
     */
    private function getApiInstance(?StoreInterface $store = null)
    {
        if (null === $this->apiInstance) {
            $this->apiInstance = $this->factory->createApiInstance($store);
        }
        return $this->apiInstance;
    }

    /**
     * Get klarna order from API with klarna checkout id
     *
     * @param string $klarnaCheckoutId
     * @return DataObject
     * @throws KlarnaException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getOrder($klarnaCheckoutId): DataObject
    {
        $this->klarnaCheckout = $this->getApiInstance($this->kcoSession->getQuote()->getStore())
            ->retrieveOrder($this->kcoSession->getQuote()->getBaseCurrencyCode(), $klarnaCheckoutId);
        $this->updateKssValues();
        return $this->klarnaCheckout;
    }

    /**
     * Update klarna order id
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function setKlarnaOrderid()
    {
        $klarnaOrderId = $this->klarnaCheckout->getOrderId();
        if (!$klarnaOrderId) {
            $klarnaOrderId = $this->klarnaCheckout->getId();
        }
        $this->kcoSession->setKlarnaQuoteKlarnaCheckoutId($klarnaOrderId);
    }

    /**
     * Store the resolver result the Kustom order was created or last confirmed with
     *
     * @param bool $isRecurring
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function setRecurringState(bool $isRecurring): void
    {
        $this->kcoSession->getKlarnaQuote()->setData('is_recurring', (int)$isRecurring);
    }

    /**
     * Save klarna quote
     *
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function saveKlarnaQuote()
    {
        $this->kcoSession->getKlarnaQuote()->setIsChanged(0);
        $this->klarnaQuoteRepository->save($this->kcoSession->getKlarnaQuote());
    }

    /**
     * Get current Klarna checkout object
     *
     * @param string $klarnaCheckoutId
     * @return DataObject
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getKlarnaCheckout($klarnaCheckoutId)
    {
        if (null === $this->klarnaCheckout) {
            $this->klarnaCheckout = $this->loadKlarnaCheckout($klarnaCheckoutId);
        }
        return $this->klarnaCheckout;
    }

    /**
     * Generate update request from kco api
     *
     * @param StoreInterface|null $store
     * @return array
     * @throws KlarnaException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function generatedUpdateRequest(?StoreInterface $store = null)
    {
        return $this->getApiInstance($store)->getGeneratedUpdateRequest();
    }

    /**
     * Get reservation Id from kco api
     *
     * @param StoreInterface|null $store
     * @return string
     * @throws KlarnaException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getReservationId(?StoreInterface $store = null)
    {
        return $this->getApiInstance($store)->getReservationId();
    }

    /**
     * Get klarna checkout GUI from kco api
     *
     * @param StoreInterface|null $store
     * @return string
     * @throws KlarnaException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getKlarnaCheckoutGui(?StoreInterface $store = null)
    {
        return $this->getApiInstance($store)->getKlarnaCheckoutGui();
    }
}
