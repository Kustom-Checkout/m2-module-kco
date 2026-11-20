<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Model;

use Klarna\Kco\Api\QuoteRepositoryInterface;
use Klarna\Kco\Model\Checkout\ValidatedMarker;
use Klarna\Kco\Model\QuoteRepository;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * KUSTOM-128: validated_at is written only by the single-column update
 *
 * @covers \Klarna\Kco\Model\QuoteRepository
 * @covers \Klarna\Kco\Model\CacheQuoteRepository
 */
class QuoteRepositoryTest extends TestCase
{
    private const CHECKOUT_ID = '123456-1234-1234-1234-1234567890';

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture Klarna_Base::Test/Integration/_files/fixtures/quote_setup1_single_simple_product.php
     */
    public function testValidatedAtIsWrittenOnlyByTheMarkerUpdate(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $cached = $objectManager->get(QuoteRepositoryInterface::class);
        $plain = $objectManager->get(QuoteRepository::class);
        $marker = $objectManager->get(ValidatedMarker::class);

        $this->assertNull($cached->getByCheckoutId(self::CHECKOUT_ID)->getValidatedAt());

        // stale copy (marker still NULL) taken before the marker is set
        $stale = $plain->getByCheckoutId(self::CHECKOUT_ID);
        $marker->update(self::CHECKOUT_ID, '2026-11-20 10:00:00');
        $this->assertSame('2026-11-20 10:00:00', $cached->getByCheckoutId(self::CHECKOUT_ID)->getValidatedAt());

        // a whole-row save of the stale copy must not erase the marker
        $stale->setIsActive(1);
        $cached->save($stale);
        $this->assertSame('2026-11-20 10:00:00', $plain->getByCheckoutId(self::CHECKOUT_ID)->getValidatedAt());

        // clear it, then a whole-row save of a copy which still has the old value must not resurrect it
        $copyWithMarker = $plain->getByCheckoutId(self::CHECKOUT_ID);
        $marker->update(self::CHECKOUT_ID, null);
        $this->assertNull($cached->getByCheckoutId(self::CHECKOUT_ID)->getValidatedAt());
        $copyWithMarker->setIsActive(1);
        $cached->save($copyWithMarker);
        $this->assertNull($plain->getByCheckoutId(self::CHECKOUT_ID)->getValidatedAt());
        $this->assertNull($cached->getByCheckoutId(self::CHECKOUT_ID)->getValidatedAt());
    }
}
