<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Exception;

use Klarna\Base\Exception;

/**
 * Thrown when the billing or shipping address would be rejected by Magento when the order is placed
 *
 * @internal
 */
class AddressValidationException extends Exception
{

}
