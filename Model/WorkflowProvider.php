<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model;

use Klarna\Base\Exception as KlarnaException;
use Klarna\Kco\Api\QuoteRepositoryInterface as KcoQuoteRepositoryInterface;
use Magento\Quote\Model\QuoteRepository as MagentoQuoteRepository;
use Klarna\Kco\Api\QuoteInterface;
use Klarna\Base\Api\OrderInterface;
use Klarna\Base\Model\OrderRepository as KlarnaOrderRepository;
use Magento\Sales\Model\OrderRepository as MagentoOrderRepository;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface as MagentoOrder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\InputException;

/**
 * Providing quotes and orders for the checkout workflow based on a given klarna order id
 *
 * @internal
 */
class WorkflowProvider
{
    /**
     * @var KcoQuoteRepositoryInterface
     */
    private $kcoQuoteRepository;
    /**
     * @var MagentoQuoteRepository
     */
    private $magentoQuoteRepository;
    /**
     * @var KlarnaOrderRepository
     */
    private $klarnaOrderRepository;
    /**
     * @var MagentoOrderRepository
     */
    private $magentoOrderRepository;
    /**
     * @var QuoteInterface
     */
    private $kcoQuote;
    /**
     * @var CartInterface
     */
    private $magentoQuote;
    /**
     * @var OrderInterface
     */
    private $klarnaOrder;
    /**
     * @var MagentoOrder
     */
    private $magentoOrder;
    /**
     * @var string
     */
    private $klarnaOrderId;

    /**
     * @param KcoQuoteRepositoryInterface $kcoQuoteRepository
     * @param MagentoQuoteRepository      $magentoQuoteRepository
     * @param KlarnaOrderRepository       $klarnaOrderRepository
     * @param MagentoOrderRepository      $magentoOrderRepository
     * @codeCoverageIgnore
     */
    public function __construct(
        KcoQuoteRepositoryInterface $kcoQuoteRepository,
        MagentoQuoteRepository $magentoQuoteRepository,
        KlarnaOrderRepository $klarnaOrderRepository,
        MagentoOrderRepository $magentoOrderRepository
    ) {
        $this->kcoQuoteRepository     = $kcoQuoteRepository;
        $this->magentoQuoteRepository = $magentoQuoteRepository;
        $this->klarnaOrderRepository  = $klarnaOrderRepository;
        $this->magentoOrderRepository = $magentoOrderRepository;
    }

    /**
     * Setting the klarna order id
     *
     * @param string $klarnaOrderId
     * @throws KlarnaException
     */
    public function setKlarnaOrderId(string $klarnaOrderId): void
    {
        if (empty($klarnaOrderId)) {
            throw new KlarnaException(__('The provided Klarna order id is empty'));
        }

        $this->klarnaOrderId = $klarnaOrderId;
        $this->kcoQuote = null;
        $this->magentoQuote = null;
        $this->klarnaOrder = null;
        $this->magentoOrder = null;
    }

    /**
     * Getting back the kco quote
     *
     * @throws KlarnaException
     * @return QuoteInterface
     */
    public function getKcoQuote(): QuoteInterface
    {
        if ($this->kcoQuote === null) {
            try {
                $this->kcoQuote = $this->kcoQuoteRepository->getByCheckoutId($this->klarnaOrderId);
            } catch (NoSuchEntityException $e) {
                throw new KlarnaException(__(
                    'No Klarna Kco quote could be found with the provided Klarna order id: %1',
                    $this->klarnaOrderId
                ));
            }
        }

        return $this->kcoQuote;
    }

    /**
     * Getting back the Magento quote
     *
     * @throws KlarnaException
     * @return CartInterface
     */
    public function getMagentoQuote(): CartInterface
    {
        if ($this->magentoQuote === null) {
            try {
                $this->magentoQuote = $this->magentoQuoteRepository->get($this->getKcoQuote()->getQuoteId());
            } catch (NoSuchEntityException $e) {
                throw new KlarnaException(__(
                    'No active Magento quote could be found with the provided quote id: %1',
                    $this->getKcoQuote()->getQuoteId()
                ));
            }
        }

        return $this->magentoQuote;
    }

    /**
     * Getting back the klarna order
     *
     * @throws KlarnaException
     * @return OrderInterface
     */
    public function getKlarnaOrder(): OrderInterface
    {
        if ($this->klarnaOrder === null) {
            try {
                $this->klarnaOrder = $this->klarnaOrderRepository->getByKlarnaOrderId($this->klarnaOrderId);
            } catch (NoSuchEntityException $e) {
                throw new KlarnaException(__(
                    'No Klarna order entry could be found with the provided Klarna order id: %1',
                    $this->klarnaOrderId
                ));
            }
        }

        return $this->klarnaOrder;
    }

    /**
     * Getting back the Magento order
     *
     * @throws KlarnaException
     * @return MagentoOrder
     */
    public function getMagentoOrder(): MagentoOrder
    {
        if ($this->magentoOrder === null) {
            try {
                $this->magentoOrder = $this->magentoOrderRepository->get($this->getKlarnaOrder()->getOrderId());
            } catch (NoSuchEntityException|InputException $e) {
                throw new KlarnaException(__(
                    'No Magento order could be found with the provided Klarna order id: %1',
                    $this->klarnaOrderId
                ));
            }
        }

        return $this->magentoOrder;
    }

    /**
     * Clearing the klarna order instance so that later a new instance can be requested from the database.
     */
    public function clearKlarnaOrder(): void
    {
        $this->klarnaOrder = null;
    }
}
