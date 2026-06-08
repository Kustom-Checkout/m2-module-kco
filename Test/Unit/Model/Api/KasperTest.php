<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

namespace Klarna\Kco\Test\Unit\Model\Api;

use Klarna\Kco\Model\Api\Kasper;
use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Klarna\Kco\Model\Checkout\Kco\Session;
use Klarna\Base\Test\Unit\Mock\MockFactory;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use PHPUnit\Framework\TestCase;
use Klarna\Kco\Model\Api\Builder\Kasper as KasperBuilder;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Api\Kasper
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class KasperTest extends TestCase
{
    /**
     * @var Kasper
     */
    private $modelPartialMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $modelPartialMockBuilder;

    /**
     * @var Klarna\Kco\Model\Checkout\Kco\Session|\PHPUnit_Framework_MockObject_MockObject
     */
    private $kcoSessionMock;

    /**
     * @var Klarna\Kco\Model\Api\Rest\Service\Checkout|\PHPUnit_Framework_MockObject_MockObject
     */
    private $checkoutMock;

    /**
     * @var Magento\Framework\DataObjectFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    private $dataObjectFactory;

    /**
     * @var Magento\Framework\DataObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $klarnaOrderObjectMock;
    /**
     * @var KasperBuilder|\PHPUnit\Framework\MockObject\MockObject
     */
    private KasperBuilder $kasperBuilder;

    /**
     * @covers ::retrieveOrder
     */
    public function testRetrieveOrderWithInvalidCheckoutId(): void
    {
        $this->expectException(\Klarna\Base\Exception::class);
        $this->expectExceptionMessage("Unable to initialize Kustom checkout order");
        $this->modelPartialMock->retrieveOrder('EUR', null);
    }

    /**
     * @covers ::retrieveOrder
     */
    public function testRetrieveOrderWithValidCheckoutId(): void
    {
        $this->checkoutMock->expects(static::once())
            ->method('getOrder')
            ->willReturn([]);
        $this->modelPartialMock->expects(static::once())
            ->method('setKlarnaOrder')
            ->with($this->klarnaOrderObjectMock);

        $klarnaOrder = $this->modelPartialMock->retrieveOrder('EUR', '1');
        static::assertInstanceOf(DataObject::class, $klarnaOrder);
    }

    public function requestDataProvider(): array
    {
        return [
            [
                [ // request with no order lines
                    'order_lines' => []
                ],
                [ // request with 1 order line (no shipping)
                    'order_lines' => [
                        [
                            'type' => 'physical',
                        ],
                    ]
                ],
                [ // request with 2 order lines
                    'order_lines' => [
                        [
                            'type' => 'shipping_fee',
                        ],
                        [
                            'type' => 'physical',
                        ],
                    ]
                ],
            ],
        ];
    }

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $mockFactory = new MockFactory($this);

        $this->klarnaOrderObjectMock = $mockFactory->create(DataObject::class, [], [
            'getIsSuccessful',
            'getResponseStatusCode'
        ]);
        $this->klarnaOrderObjectMock->method('getIsSuccessful')
            ->willReturn(true);

        // Mocks needed to initialize Klarna\Kco\Model\Api\Kasper
        $this->kcoSessionMock     = $mockFactory->create(Session::class);
        $this->checkoutMock       = $mockFactory->create(Checkout::class);
        $this->dataObjectFactory  = $mockFactory->create(DataObjectFactory::class, ['create']);
        $this->kasperBuilder      = $mockFactory->create(KasperBuilder::class);

        $this->dataObjectFactory->method('create')
            ->willReturn($this->klarnaOrderObjectMock);

        // Using a partial mock instead of a real object to mock private and magic methods
        $this->modelPartialMockBuilder = $this->getMockBuilder(Kasper::class)
            ->setConstructorArgs([
                $this->kcoSessionMock,
                $this->checkoutMock,
                $this->dataObjectFactory,
                $this->kasperBuilder,
                []
            ])
            ->onlyMethods([
                'getGeneratedCreateRequest',
                'getGeneratedUpdateRequest',
                'setKlarnaOrder'
            ]);

        // Separating the mock builder and actual mock into two variables because
        // ::testUpdateOrderForExpiredOrder needs its own instance of the mock to
        // mock a class method that shouldn't be mocked otherwise
        $this->modelPartialMock = $this->modelPartialMockBuilder->getMock();
    }
}
