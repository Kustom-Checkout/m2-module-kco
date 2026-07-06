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
     * @return void
     * @throws Exception
     */
    public function createUpdateKlarnaSession(): void
    {
        if (!$this->validator->isCheckoutAllowedForCustomer()) {
            return;
        }

        if ($this->validator->isKlarnaSessionRunning()) {
            $this->update->updateKlarnaSession();

            return;
        }

        $this->startup->createKlarnaSession();
    }
}
