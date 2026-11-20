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
     * @param LoggerInterface     $logger
     * @param Initializer         $initializer
     * @param ManagerInterface    $manager
     * @param Ajax                $ajax
     * @param MutationGuard|null  $mutationGuard
     * @codeCoverageIgnore
     */
    public function __construct(
        LoggerInterface $logger,
        Initializer $initializer,
        ManagerInterface $manager,
        Ajax $ajax,
        ?MutationGuard $mutationGuard = null
    ) {
        $this->logger         = $logger;
        $this->initializer    = $initializer;
        $this->manager        = $manager;
        $this->ajax           = $ajax;
        $this->mutationGuard  = $mutationGuard ?: ObjectManager::getInstance()->get(MutationGuard::class);
    }

    /**
     * Updating the Klarna order
     *
     * @return Json|ResultInterface
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
        try {
            $kustomId = (string) $this->initializer->getKcoSession()->getKlarnaQuote()->getKlarnaCheckoutId();
            if ($kustomId !== '') {
                $this->mutationGuard->guardFrontend($kustomId);
            }
            $this->initializer->updateKlarnaTotals();
            return $this->ajax->getSummaryResponse();
        } catch (CheckoutLockedException $e) {
            // KUSTOM-128: payment in progress or order created, the quote stays untouched
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
}
