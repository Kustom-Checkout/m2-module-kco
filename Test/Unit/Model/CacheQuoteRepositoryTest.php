<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

namespace Klarna\Kco\Test\Unit\Model;

use Klarna\Base\Test\Unit\Mock\MockFactory;
use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use Klarna\Kco\Api\QuoteRepositoryInterface;
use Klarna\Kco\Model\CacheQuoteRepository;
use Klarna\Kco\Model\Quote as KlarnaQuote;
use Klarna\Kco\Model\ResourceModel\Quote as QuoteResource;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\CacheQuoteRepository
 */
class CacheQuoteRepositoryTest extends TestCase
{
    /**
     * @var QuoteRepositoryInterface
     */
    private $model;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject[]
     */
    private $dependencyMocks;

    /**
     * @var KlarnaQuote|\PHPUnit_Framework_MockObject_MockObject
     */
    private $klarnaQuoteMock;

    /**
     * @var Quote|\PHPUnit_Framework_MockObject_MockObject
     */
    private $mageQuoteMock;

    /**
     * @var QuoteResource|\PHPUnit_Framework_MockObject_MockObject
     */
    private $quoteResourceMock;

    /**
     * @covers ::getByCheckoutId()
     */
    public function testGetByCheckoutId()
    {
        $checkoutId = '752a7690-b999-5d5b-bcd6-3f198defa1d4';

        $this->dependencyMocks['quoteRepository']->expects(static::once())
            ->method('getByCheckoutId')
            ->with($checkoutId)
            ->willReturn($this->klarnaQuoteMock);

        static::assertEquals($this->klarnaQuoteMock, $this->model->getByCheckoutId($checkoutId));
        # second call retrieves from cache
        static::assertEquals($this->klarnaQuoteMock, $this->model->getByCheckoutId($checkoutId));
    }

    /**
     * @covers ::save()
     */
    public function testSave()
    {
        $this->dependencyMocks['quoteRepository']->expects(static::once())
            ->method('save')
            ->with($this->klarnaQuoteMock)
            ->willReturn($this->quoteResourceMock);

        static::assertEquals($this->quoteResourceMock, $this->model->save($this->klarnaQuoteMock));
    }

    /**
     * @covers ::getActiveByQuote()
     */
    public function testGetActiveByQuote()
    {
        $this->dependencyMocks['quoteRepository']->expects(static::once())
            ->method('getActiveByQuote')
            ->with($this->mageQuoteMock)
            ->willReturn($this->klarnaQuoteMock);

        static::assertEquals($this->klarnaQuoteMock, $this->model->getActiveByQuote($this->mageQuoteMock));
    }

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $mockFactory = new MockFactory($this);
        $objectFactory = new TestObjectFactory('');

        $this->mageQuoteMock = $mockFactory->create(Quote::class);
        $this->klarnaQuoteMock = $mockFactory->create(KlarnaQuote::class);
        $this->quoteResourceMock = $mockFactory->create(QuoteResource::class);

        $this->model = $objectFactory->create(CacheQuoteRepository::class);
        $this->dependencyMocks = $objectFactory->getDependencyMocks();
    }
}
