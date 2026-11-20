<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * @internal
 */
class Quote extends AbstractDb
{
    /**
     * KUSTOM-128: column which is only written by updateValidatedAt(), never by a whole-row save
     */
    public const COLUMN_VALIDATED_AT = 'validated_at';

    /**
     * Single-column UPDATE of validated_at (set or clear)
     *
     * @param int|string $kcoQuoteId
     * @param string|null $value UTC Y-m-d H:i:s, null clears
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateValidatedAt($kcoQuoteId, ?string $value): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            [self::COLUMN_VALIDATED_AT => $value],
            [$this->getIdFieldName() . ' = ?' => $kcoQuoteId]
        );
    }

    /**
     * Whole-row saves must never write validated_at on updates (inserts keep NULL)
     *
     * @param AbstractModel $object
     * @return array
     */
    protected function prepareDataForUpdate($object)
    {
        $data = parent::prepareDataForUpdate($object);
        unset($data[self::COLUMN_VALIDATED_AT]);
        return $data;
    }

    /**
     * Constructor
     *
     * @codeCoverageIgnore
     */
    protected function _construct()
    {
        $this->_init('klarna_kco_quote', 'kco_quote_id');
    }
}
