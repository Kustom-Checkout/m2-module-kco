<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\ResourceModel\CustomerToken;

use Klarna\Kco\Model\CustomerToken;
use Klarna\Kco\Model\ResourceModel\CustomerToken as CustomerTokenResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * @internal
 */
class Collection extends AbstractCollection
{
    /**
     * Constructor
     *
     * @codeCoverageIgnore
     * @codingStandardsIgnoreLine
     */
    protected function _construct(): void
    {
        $this->_init(CustomerToken::class, CustomerTokenResource::class);
    }

    /**
     * Decrypt the tokens of the loaded items
     *
     * @return $this
     * @codingStandardsIgnoreLine
     */
    protected function _afterLoad()
    {
        /** @var CustomerTokenResource $resource */
        $resource = $this->getResource();
        foreach ($this->_items as $item) {
            $resource->decryptToken($item);
        }
        return parent::_afterLoad();
    }
}
