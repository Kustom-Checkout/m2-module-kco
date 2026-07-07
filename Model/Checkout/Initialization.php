<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Model\Checkout;

use Klarna\Base\Exception;
use Klarna\Kco\Model\Checkout\Initialization\Startup;
use Klarna\Kco\Model\Checkout\Initialization\Update;
use Klarna\Kco\Model\Checkout\Initialization\Validator;

class Initialization
{
    public const STATE_NONE = 0;
    public const STATE_CREATE = 1;
    public const STATE_UPDATE = 2;

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
     * @param Update $update
     * @param Startup $startup
     * @param Validator $validator
     */
    public function __construct(
        Update $update,
        Startup $startup,
        Validator $validator
    ) {
        $this->update = $update;
        $this->startup = $startup;
        $this->validator = $validator;
    }

    /**
     * If allowed, initialize new session or update the existing one
     *
     * @return int
     * @throws Exception
     */
    public function createUpdateKlarnaSession(): int
    {
        if (!$this->validator->isCheckoutAllowedForCustomer()) {
            return self::STATE_NONE;
        }

        if ($this->validator->isKlarnaSessionRunning()) {
            $this->update->updateKlarnaSession();

            return self::STATE_UPDATE;
        }

        $this->startup->createKlarnaSession();

        return self::STATE_CREATE;
    }
}
