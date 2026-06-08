<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Block\Checkout;

use Klarna\Base\Model\Api\Rest\Service;
use Klarna\Kco\Model\Checkout\Initialization\Startup;
use Klarna\Kco\Model\Checkout\Initialization\Update;
use Klarna\Kco\Model\Checkout\Initialization\Validator;
use Klarna\Kco\Model\Checkout\Kco\Initializer as kcoInitializer;
use Klarna\Base\Helper\VersionInfo;
use Magento\Checkout\Block\Checkout\LayoutProcessor;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Klarna\Base\Exception as KlarnaException;

/**
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @internal
 */
class LayoutProcessorPlugin
{
    /**
     * @var Session
     */
    private $session;
    /**
     * @var ManagerInterface
     */
    private $manager;
    /**
     * @var kcoInitializer
     */
    private $kcoInitializer;
    /**
     * @var VersionInfo
     */
    private $info;
    /**
     * @var ScopeConfigInterface
     */
    private $config;
    /**
     * @var Update
     */
    private Update $update;
    /**
     * @var Startup
     */
    private Startup $startup;
    /**
     * @var Validator
     */
    private Validator $validator;

    /**
     * @param Session $session
     * @param ManagerInterface $manager
     * @param kcoInitializer $kcoInitializer
     * @param VersionInfo $info
     * @param ScopeConfigInterface $config
     * @param Update $update
     * @param Startup $startup
     * @param Validator $validator
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @codeCoverageIgnore
     */
    public function __construct(
        Session $session,
        ManagerInterface $manager,
        kcoInitializer $kcoInitializer,
        VersionInfo $info,
        ScopeConfigInterface $config,
        Update $update,
        Startup $startup,
        Validator $validator
    ) {
        $this->session = $session;
        $this->manager = $manager;
        $this->kcoInitializer = $kcoInitializer;
        $this->info = $info;
        $this->config = $config;
        $this->update = $update;
        $this->startup = $startup;
        $this->validator = $validator;
    }

    /**
     * Runs after the standard LayoutProcessor to conditionally enable Klarna checkout
     *
     * @param LayoutProcessor $subject
     * @param array           $result
     * @return array
     * @throws LocalizedException
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function afterProcess(LayoutProcessor $subject, array $result)
    {
        if (isset($result['components']['checkout']['children']['steps']['children']["klarna_kco"])) {
            $quote = $this->session->getQuote();
            $iframe = $this->generateKlarnaIframe($quote);
            $result['components']['checkout']['children']['steps']['children']['klarna_kco']['klarna_iframe'] = $iframe;
            $result = $this->moveShippingAdditional($result);
            return $this->checkForEnterprise($result, $quote->getStore());
        }
        return $result;
    }

    /**
     * Returns iframe snippet of checkout form
     *
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @return string
     * @throws LocalizedException
     * @throws \Klarna\Base\Exception
     */
    private function generateKlarnaIframe($quote)
    {
        try {
            if ($this->validator->isCheckoutAllowedForCustomer()) {
                if ($this->validator->isKlarnaSessionRunning()) {
                    $this->update->updateKlarnaSession();
                } else {
                    $this->startup->createKlarnaSession();
                }
            }
        } catch (\Throwable $e) {
            if ($e->getCode() === Service::HTTP_UNAUTHORIZED) {
                return __(
                    'Invalid Kustom API Credentials. Please check your Merchant ID, ' .
                    'Shared Secret, and selected API Version.'
                );
            }
            $errorMessage = $e->getMessage();
            $this->manager->addErrorMessage($errorMessage);
            return __(
                'Kustom Checkout has failed to load. Please ' .
                '<a href="javascript:;" onclick="location.reload(true)">reload checkout.</a>'
            );
        }
        return $this->kcoInitializer->getKlarnaCheckoutGui($quote->getStore());
    }

    /**
     * Moving the key "shippingAdditional"
     *
     * @param array $result
     * @return array
     */
    private function moveShippingAdditional(array $result)
    {
        $shippingStep = $result['components']['checkout']['children']['steps']['children']['shipping-step'];
        if (!isset($shippingStep['children']['shippingAddress']['children']['shippingAdditional'])) {
            return $result;
        }
        $additional = $shippingStep['children']['shippingAddress']['children']['shippingAdditional'];
        $result['components']['checkout']['children']['sidebar']['children']
        ['klarna_shipping']['children']['shippingAdditional'] = $additional;
        unset(
            $result['components']['checkout']['children']['steps']['children']
            ['shipping-step']['children']['shippingAddress']['children']['shippingAdditional']
        );
        return $result;
    }

    /**
     * Checking for the enterprise edition
     *
     * @param array          $result
     * @param StoreInterface $store
     * @return array
     */
    private function checkForEnterprise(array $result, StoreInterface $store)
    {
        if ($this->info->getMageEdition() !== 'Enterprise') {
            return $result;
        }

        if ($this->config->isSetFlag(
            'customer/magento_customerbalance/is_enabled',
            ScopeInterface::SCOPE_STORES,
            $store
        )) {
            $result['components']['checkout']['children']['sidebar']['children']
            ['klarna_sidebar']['children']['storeCredit'] = [
                'component'   => 'Magento_CustomerBalance/js/view/payment/customer-balance',
                'displayArea' => 'klarna-summary',
                'sortOrder'   => '20',
            ];
        }

        if ($this->config->isSetFlag('giftcard/general/is_redeemable', ScopeInterface::SCOPE_STORES, $store)) {
            $result['components']['checkout']['children']['sidebar']['children']
            ['klarna_sidebar']['children']['giftCardAccount'] = [
                'component'   => 'Magento_GiftCardAccount/js/view/payment/gift-card-account',
                'displayArea' => 'klarna-summary',
                'sortOrder'   => '30',
                'children'    => [
                    'errors' => [
                        'sortOrder'   => '0',
                        'component'   => 'Magento_GiftCardAccount/js/view/payment/gift-card-messages',
                        'displayArea' => 'messages',
                    ],
                ],
            ];
        }

        if ($this->config->isSetFlag(
            'magento_reward/general/is_enabled_on_front',
            ScopeInterface::SCOPE_STORES,
            $store
        )) {
            $result['components']['checkout']['children']['sidebar']['children']
            ['klarna_sidebar']['children']['reward'] = [
                'component'   => 'Magento_Reward/js/view/payment/reward',
                'displayArea' => 'klarna-summary',
                'sortOrder'   => '40',
            ];
        }
        return $result;
    }
}
