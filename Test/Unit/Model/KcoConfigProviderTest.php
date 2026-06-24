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
use Klarna\Kco\Model\Checkout\Url;
use Klarna\Kco\Model\KcoConfigProvider;
use Klarna\Kco\Model\Payment\Kco;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\KcoConfigProvider
 */
class KcoConfigProviderTest extends TestCase
{
    /**
     * @var MockFactory
     */
    private MockFactory $mockFactory;
    /**
     * @var KcoConfigProvider
     */
    private KcoConfigProvider $model;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject[]
     */
    private array $dependencyMocks;
    /**
     * @var Store
     */
    private Store $store;

    /**
     * @covers ::isNoticeEnabled()
     */
    public function testIsNoticeEnabledReturnsResult(): void
    {
        static::assertTrue($this->model->isNoticeEnabled($this->store));
    }

    /**
     * @covers ::getConfig()
     */
    public function testGetConfigReturnsFailureUrl(): void
    {
        $expected = 'my failure url';
        $this->dependencyMocks['url']->method('getFailureUrl')
            ->willReturn($expected);

        $result = $this->model->getConfig();
        static::assertSame($expected, $result['klarna']['failureUrl']);
    }

    /**
     * @covers ::getConfig()
     */
    public function testGetConfigReturnsUpdateKlarnaOrderUrl(): void
    {
        $result = $this->model->getConfig();
        static::assertSame('update Klarna order url', $result['klarna']['updateKlarnaOrderUrl']);
    }

    /**
     * @covers ::getConfig()
     */
    public function testGetConfigReturnsGetAddressesUrl(): void
    {
        $result = $this->model->getConfig();
        static::assertSame('get Addresses url', $result['klarna']['getAddressesUrl']);
    }

    /**
     * @covers ::getConfig()
     */
    public function testGetConfigReturnsFrontendShippingFlag(): void
    {
        $expected = true;
        $this->dependencyMocks['shippingOptions']->method('isShippingInIframe')
            ->willReturn($expected);

        $result = $this->model->getConfig();
        static::assertSame($expected, $result['klarna']['frontEndShipping']);
    }

    /**
     * @covers ::getConfig()
     */
    public function testGetConfigReturnsPaymentMethodCode(): void
    {
        $result = $this->model->getConfig();
        static::assertSame(Kco::METHOD_CODE, $result['klarna']['paymentMethod']);
    }

    /**
     * @covers ::getConfig()
     */
    public function testGetConfigReturnsAcceptTermsUrl(): void
    {
        $result = $this->model->getConfig();
        static::assertSame('my terms url', $result['klarna']['acceptTermsUrl']);
    }

    /**
     * @covers ::getConfig()
     */
    public function testGetConfigReturnsUserTermsUrl(): void
    {
        $expected = 'my user terms url';
        $this->dependencyMocks['url']->method('getUserTermsUrl')
            ->willReturn($expected);

        $result = $this->model->getConfig();
        static::assertSame($expected, $result['klarna']['userTermsUrl']);
    }

    /**
     * @covers ::getConfig()
     */
    public function testGetConfigReturnsPrefillNoticeEnabled(): void
    {
        $result = $this->model->getConfig();
        static::assertTrue($result['klarna']['prefillNoticeEnabled']);
    }

    /**
     * @covers ::getConfig()
     */
    public function testGetConfigReturnsMethodUrl(): void
    {
        $result = $this->model->getConfig();
        static::assertSame('save shipping method url', $result['klarna']['methodUrl']);
    }

    /**
     * @covers ::getConfig()
     */
    public function testGetConfigReturnsKssEnabledFlag(): void
    {
        $expected = true;
        $this->dependencyMocks['kssConfigProvider']->method('isKssEnabled')
            ->willReturn($expected);

        $result = $this->model->getConfig();
        static::assertSame($expected, $result['klarna']['isKssEnabled']);
    }

    /**
     * @covers ::getConfig()
     */
    public function testGetConfigReturnsUpdateKssStatusUrl(): void
    {
        $result = $this->model->getConfig();
        static::assertSame('update kss status url', $result['klarna']['updateKssStatusUrl']);
    }

    /**
     * @covers ::getConfig()
     */
    public function testGetConfigReturnsUpdateKssDiscountUrl(): void
    {
        $result = $this->model->getConfig();
        static::assertSame('update kss discount order url', $result['klarna']['updateKssDiscountOrderUrl']);
    }

    protected function setUp(): void
    {
        $this->mockFactory     = new MockFactory($this);
        $objectFactory         = new TestObjectFactory('');

        $this->model           = $objectFactory->create(KcoConfigProvider::class);
        $this->dependencyMocks = $objectFactory->getDependencyMocks();

        $this->store = $this->mockFactory->create(Store::class);
        $quote = $this->mockFactory->create(Quote::class);
        $quote->method('getStore')
            ->willReturn($this->store);
        $this->dependencyMocks['session']->method('getQuote')
            ->willReturn($quote);

        $this->dependencyMocks['urlBuilder']->method('getUrl')
            ->willReturnCallback(fn($url, $options) =>
                match([$url, $options]) {
                    [
                        Url::CHECKOUT_ACTION_PREFIX . '/updateKlarnaOrder',
                        []
                    ] => 'update Klarna order url',
                    [
                        Url::API_ACTION_PREFIX . '/getAddresses',
                        []
                    ] => 'get Addresses url',
                    [
                        '*/*/*/terms/accept',
                        ['_nosid' => true, '_forced_secure' => true]
                    ] => 'my terms url',
                    [
                        Url::CHECKOUT_ACTION_PREFIX . '/saveShippingMethod',
                        []
                    ] => 'save shipping method url',
                    [
                        Url::API_ACTION_PREFIX . '/updateKssStatus',
                        []
                    ] => 'update kss status url',
                    [
                        Url::API_ACTION_PREFIX . '/updateKssDiscountOrder',
                        []
                    ] => 'update kss discount order url'

                }
            );

        $this->dependencyMocks['config']->method('isPrefillNoticeEnabled')
            ->willReturn(true);
    }
}
