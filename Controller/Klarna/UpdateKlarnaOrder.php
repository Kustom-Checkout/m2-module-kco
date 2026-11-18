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
use Klarna\Kco\Model\Responder\Ajax;
use Klarna\Kco\Model\Checkout\Kco\Initializer;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
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
     * @param LoggerInterface     $logger
     * @param Initializer         $initializer
     * @param ManagerInterface    $manager
     * @param Ajax                $ajax
     * @codeCoverageIgnore
     */
    public function __construct(
        LoggerInterface $logger,
        Initializer $initializer,
        ManagerInterface $manager,
        Ajax $ajax
    ) {
        $this->logger         = $logger;
        $this->initializer    = $initializer;
        $this->manager        = $manager;
        $this->ajax           = $ajax;
    }

    /**
     * Updating the Klarna order
     *
     * @return Json|ResultInterface
     */
    public function execute()
    {
        try {
            $kcoSession = $this->initializer->getKcoSession();
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
        } catch (Exception $e) {
            $this->logger->critical($e);
            $this->manager->addErrorMessage($e->getMessage());

            return $this->ajax->getSummaryResponse([
                'error' => true
            ]);
        }
    }
}
