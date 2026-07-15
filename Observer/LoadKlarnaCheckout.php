<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Observer;

use Klarna\AdminSettings\Model\Configurations\Kco\Checkout;
use Klarna\Base\Exception;
use Klarna\Kco\Model\Checkout\Configuration\SettingsProvider;
use Klarna\Kco\Model\Checkout\FullCheckout;
use Klarna\Logger\Api\LoggerInterface;
use Klarna\PluginsApi\Model\Update\Validator;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\Manager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Url;
use Magento\Framework\DataObjectFactory;
use Magento\Store\Api\Data\StoreInterface;

use function __;

/**
 * This observer will be called when a customer reaches/opens the default Magento checkout page.
 * In this observer we decide if we forward the customer to the Klarna KCO page or if we do nothing.
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 * @internal
 */
class LoadKlarnaCheckout implements ObserverInterface
{
    /**
     * @var Manager
     */
    private Manager $manager;

    /**
     * @var Url
     */
    private Url $url;

    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var SettingsProvider
     */
    private SettingsProvider $config;

    /**
     * @var DataObjectFactory
     */
    private DataObjectFactory $dataObjectFactory;

    /**
     * @var Validator
     */
    private Validator $pluginsApiValidator;

    /**
     * @var Checkout
     */
    private Checkout $checkoutConfig;

    /**
     * @var FullCheckout
     */
    private FullCheckout $fullCheckout;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $messageManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Manager $manager
     * @param Url $urlModel
     * @param Session $session
     * @param SettingsProvider $config
     * @param DataObjectFactory $dataObjectFactory
     * @param Validator $pluginsApiValidator
     * @param Checkout|null $checkoutConfig
     * @param FullCheckout|null $fullCheckout
     * @param ManagerInterface|null $messageManager
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Manager $manager,
        Url $urlModel,
        Session $session,
        SettingsProvider $config,
        DataObjectFactory $dataObjectFactory,
        Validator $pluginsApiValidator,
        ?Checkout $checkoutConfig = null,
        ?FullCheckout $fullCheckout = null,
        ?ManagerInterface $messageManager = null,
        ?LoggerInterface $logger = null
    ) {
        $this->config = $config;
        $this->url = $urlModel;
        $this->manager = $manager;
        $this->checkoutSession = $session;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->pluginsApiValidator = $pluginsApiValidator;

        // TODO: Remove OM usage in next major release, done for backwards compatibility
        $this->checkoutConfig = $checkoutConfig ?: ObjectManager::getInstance()->get(Checkout::class);
        $this->fullCheckout = $fullCheckout ?: ObjectManager::getInstance()->get(FullCheckout::class);
        $this->messageManager = $messageManager ?: ObjectManager::getInstance()->get(ManagerInterface::class);
        $this->logger = $logger ?: ObjectManager::getInstance()->get(LoggerInterface::class);
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        try {
            $store = $this->checkoutSession->getQuote()->getStore();
            if ($this->pluginsApiValidator->isPspMerchantByStore($store)) {
                return;
            }

            $redirectUrl = $this->getRedirectUrl($observer, $store);
            if (!$redirectUrl) {
                return;
            }
        } catch (\Throwable $exception) {
            $this->logger->critical($exception->getMessage());
            $this->messageManager->addErrorMessage(__('Kustom Checkout has failed to load'));
            $redirectUrl = $this->url->getRouteUrl('checkout/cart');
        }

        $observer->getControllerAction()->getResponse()
            ->setRedirect($redirectUrl)
            ->sendResponse();
    }

    // TODO: Following could be moved to a dedicated class since observers are supposed to be just glue.
    // Cleans up also the constructor and we would do changes there in next major release anyway.

    /**
     * Resolves the url to redirect the user to for checkout experience
     *
     * @param Observer $observer TODO: Needed for getKlarnaCheckoutUrl, should be removed
     * @param StoreInterface $store
     *
     * @return string|null
     * @throws Exception
     * @throws LocalizedException
     */
    private function getRedirectUrl(Observer $observer, StoreInterface $store): ?string
    {
        if (!$this->checkoutConfig->isUseFullCheckout($store)) {
            return $this->getKlarnaCheckoutUrl($observer, $store);
        }

        $redirectUrl = $this->fullCheckout->generateFullCheckoutUrl($store);
        if ($redirectUrl) {
            return $redirectUrl;
        }

        return $this->getKlarnaCheckoutUrl($observer, $store);
    }

    /**
     * Resolves url for Kustom checkout while leaving an extension point to update this
     *
     * @param Observer $observer TODO: Needed for backwards compatibility (event dispatch), should be removed
     * @param StoreInterface $store
     *
     * @return string|null
     */
    private function getKlarnaCheckoutUrl(Observer $observer, StoreInterface $store): ?string
    {
        $overrideObject = $this->dataObjectFactory->create();
        $overrideObject->setData([
            'force_disabled' => false,
            'force_enabled' => false,
            'redirect_url' => $this->url->getRouteUrl('checkout/klarna'),
        ]);

        $this->manager->dispatch('kco_override_load_checkout', [
            'override_object' => $overrideObject,
            'parent_observer' => $observer,
        ]);

        if ($overrideObject->getForceEnabled()
            || (!$overrideObject->getForceDisabled()
                && !$this->checkoutSession
                    ->getKlarnaOverride()
                && $this->config->isKcoEnabled($store))
        ) {
            return $overrideObject->getRedirectUrl();
        }

        return null;
    }
}
