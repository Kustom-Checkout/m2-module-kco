<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Model\Recurring;

use Klarna\Kco\Api\RecurringResolverInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Default resolver: recurring if any registered resolver says so. Not recurring when none is registered.
 */
class CompositeResolver implements RecurringResolverInterface
{
    /**
     * @var RecurringResolverInterface[]
     */
    private array $resolvers;

    /**
     * @param RecurringResolverInterface[] $resolvers
     * @throws \InvalidArgumentException
     */
    public function __construct(array $resolvers = [])
    {
        foreach ($resolvers as $key => $resolver) {
            if (!$resolver instanceof RecurringResolverInterface) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Recurring resolver "%s" must implement %s.',
                        (string) $key,
                        RecurringResolverInterface::class
                    )
                );
            }
        }
        $this->resolvers = $resolvers;
    }

    /**
     * @inheritDoc
     */
    public function isRecurring(CartInterface $quote): bool
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->isRecurring($quote)) {
                return true;
            }
        }

        return false;
    }
}
