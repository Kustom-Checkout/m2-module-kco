<?php
/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

namespace Klarna\Kco\Test\Unit\Model\Carrier;

use Klarna\Kco\Model\Carrier\Klarna;
use Klarna\Kss\Model\ShippingMethodGateway;
use Klarna\Base\Test\Unit\Mock\MockFactory;
use Klarna\Base\Test\Unit\Mock\TestObjectFactory;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Item;
use Magento\Shipping\Model\Rate\Result as RateResult;
use Magento\Shipping\Model\Rate\ResultFactory as RateResultFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Carrier\Klarna
 */
class KlarnaTest extends TestCase
{
    /**
     * @var Klarna
     */
    private $model;
    /**
     * @var MockObject[]
     */
    private $dependencyMocks;
    /**
     * @var MockFactory
     */
    private $mockFactory;
    /**
     * @var MockObject|Item
     */
    private $item;

    /**
     * @covers ::getAllowedMethods
     */
    public function testGetAllowedMethodsReturnsEmptyArray()
    {
        static::assertEquals([], $this->model->getAllowedMethods());
    }

    /**
     * @covers ::processAdditionalValidation
     */
    public function testProcessAdditionalValidationReturnsFalseWhenNoItems()
    {
        /** @var MockObject|DataObject $requestDataObject */
        $requestDataObject = $this->mockFactory->create(DataObject::class, [], [
            'getAllItems'
        ]);
        $requestDataObject->method('getAllItems')
            ->willReturn([]);
        static::assertFalse($this->model->processAdditionalValidation($requestDataObject));
    }

    /**
     * @covers ::processAdditionalValidation
     */
    public function testProcessAdditionalValidationReturnsFalseWhenKssInactive()
    {
        /** @var MockObject|DataObject $requestDataObject */
        $requestDataObject = $this->mockFactory->create(DataObject::class, [], [
            'getAllItems'
        ]);
        $requestDataObject->method('getAllItems')
            ->willReturn([
                $this->item
            ]);

        $this->dependencyMocks['carrier']->method('isValidRequest')
            ->willReturn(true);
        $this->dependencyMocks['klarnaSession']->expects(static::once())
            ->method('hasActiveKlarnaShippingGatewayInformation')
            ->willReturn(false);

        static::assertFalse($this->model->processAdditionalValidation($requestDataObject));
    }

    /**
     * @covers ::processAdditionalValidation
     */
    public function testProcessAdditionalValidationReturnsSelfWhenKssActive()
    {
        /** @var MockObject|DataObject $requestDataObject */
        $requestDataObject = $this->mockFactory->create(DataObject::class, [], [
            'getAllItems'
        ]);
        $requestDataObject->method('getAllItems')
            ->willReturn([
                $this->item
            ]);

        $this->dependencyMocks['carrier']->method('isValidRequest')
            ->willReturn(true);
        $this->dependencyMocks['klarnaSession']->expects(static::once())
            ->method('hasActiveKlarnaShippingGatewayInformation')
            ->willReturn(true);

        static::assertEquals($this->model, $this->model->processAdditionalValidation($requestDataObject));
    }

    /**
     * @covers ::collectRates
     */
    public function testCollectRatesAppendsKlarnaCarrierToResult(): void
    {
        $rateResult = $this->mockFactory->create(RateResult::class);
        $this->dependencyMocks['carrier']->method('collectRates')
            ->willReturn($rateResult);
        $rateRequest = $this->mockFactory->create(RateRequest::class);
        $result = $this->model->collectRates($rateRequest);
        static::assertSame($rateResult, $result);
    }

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $this->mockFactory     = new MockFactory($this);
        $objectFactory         = new TestObjectFactory('');
        $this->model           = $objectFactory->create(Klarna::class, [
            RateResultFactory::class => ['create'],
            MethodFactory::class     => ['create']
        ]);
        $this->dependencyMocks = $objectFactory->getDependencyMocks();
        $shippingMethodGateWay = $this->mockFactory->create(ShippingMethodGateway::class);
        $this->item            = $this->mockFactory->create(Item::class);

        $this->dependencyMocks['klarnaSession']->method('getKlarnaShippingGateway')
            ->willReturn($shippingMethodGateWay);
    }
}
