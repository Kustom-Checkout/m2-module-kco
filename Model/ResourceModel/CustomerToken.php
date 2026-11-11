<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\ResourceModel;

use Klarna\Kco\Api\Data\CustomerTokenInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

/**
 * Persists customer tokens. The token column holds ciphertext; models always carry the plain token.
 *
 * @internal
 */
class CustomerToken extends AbstractDb
{
    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @param Context $context
     * @param EncryptorInterface $encryptor
     * @param string|null $connectionName
     * @codeCoverageIgnore
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->encryptor = $encryptor;
    }

    /**
     * Constructor
     *
     * @codeCoverageIgnore
     */
    protected function _construct()
    {
        $this->_init('kustom_customer_token', 'entity_id');
    }

    /**
     * Encrypt the token in the data that is written; the model keeps the plain value
     *
     * Used by insert and update, so a changed token is never written in plain text.
     *
     * @param DataObject $object
     * @param string $table
     * @return array
     */
    protected function _prepareDataForTable(DataObject $object, $table)
    {
        $data = parent::_prepareDataForTable($object, $table);
        if (isset($data[CustomerTokenInterface::TOKEN]) && $data[CustomerTokenInterface::TOKEN] !== '') {
            $data[CustomerTokenInterface::TOKEN] = $this->encryptor->encrypt(
                (string)$data[CustomerTokenInterface::TOKEN]
            );
        }
        return $data;
    }

    /**
     * Decrypt the token after a load
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterLoad(AbstractModel $object)
    {
        $this->decryptToken($object);
        return parent::_afterLoad($object);
    }

    /**
     * Replace the encrypted token of a loaded model with the plain one
     *
     * @param AbstractModel $object
     * @return void
     */
    public function decryptToken(AbstractModel $object): void
    {
        $token = $object->getData(CustomerTokenInterface::TOKEN);
        if (is_string($token) && $token !== '') {
            $object->setData(CustomerTokenInterface::TOKEN, $this->encryptor->decrypt($token));
        }
    }
}
