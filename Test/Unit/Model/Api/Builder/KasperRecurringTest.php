<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Api\Builder;

use Klarna\Base\Model\Api\OrderLineProcessor;
use Klarna\Kco\Api\RecurringResolverInterface;
use Klarna\Kco\Model\Api\Builder\Kasper;
use Klarna\Kco\Model\Checkout\Kco\Session as KcoSession;
use Klarna\Orderlines\Model\Container\Parameter;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\Store;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Plain PHPUnit test (no shared Klarna mock helper), so it is never skipped.
 *
 * @coversDefaultClass \Klarna\Kco\Model\Api\Builder\Kasper
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class KasperRecurringTest extends TestCase
{
    /**
     * @var array
     */
    private array $request = [];

    /**
     * @var RecurringResolverInterface|MockObject
     */
    private $resolver;

    /**
     * @var Quote|MockObject
     */
    private $quote;

    /**
     * @return array
     */
    public static function requestMethodProvider(): array
    {
        return [
            'create' => ['generateCreateRequest'],
            'update' => ['generateUpdateRequest']
        ];
    }

    /**
     * @param string $method
     * @covers ::generateCreateRequest
     * @covers ::generateUpdateRequest
     * @covers ::handleRecurring
     */
    #[DataProvider('requestMethodProvider')]
    public function testRecurringQuoteAddsFlagAndKeepsExistingOptions(string $method): void
    {
        $baseline = $this->generate($method, false);
        $recurring = $this->generate($method, true);

        static::assertTrue($recurring['recurring']);
        static::assertTrue($recurring['options']['subscription_buy_button']);
        static::assertSame('#fff', $recurring['options']['color_button']);
        static::assertArrayNotHasKey('recurring_description', $recurring);

        unset($recurring['recurring'], $recurring['options']['subscription_buy_button']);
        static::assertSame($baseline, $recurring);
    }

    /**
     * @param string $method
     * @covers ::handleRecurring
     */
    #[DataProvider('requestMethodProvider')]
    public function testNonRecurringQuoteLeavesPayloadUntouched(string $method): void
    {
        $request = $this->generate($method, false);

        static::assertArrayNotHasKey('recurring', $request);
        static::assertArrayNotHasKey('subscription_buy_button', $request['options']);
        static::assertSame('#fff', $request['options']['color_button']);
    }

    /**
     * @covers ::generatePlaceOrderRequest
     */
    public function testPlaceOrderRequestIsNotChangedForRecurringQuote(): void
    {
        $this->resolver->expects(static::never())->method('isRecurring');
        $model = $this->createModel([]);

        $model->generatePlaceOrderRequest($this->quote);

        static::assertArrayNotHasKey('recurring', $this->request);
        static::assertArrayNotHasKey('subscription_buy_button', $this->request['options']);
    }

    /**
     * Run a generate method with the resolver answering $isRecurring and return the request
     *
     * @param string $method
     * @param bool $isRecurring
     * @return array
     */
    private function generate(string $method, bool $isRecurring): array
    {
        $this->resolver = $this->createMock(RecurringResolverInterface::class);
        $this->resolver->method('isRecurring')->willReturn($isRecurring);
        $this->createModel(['color_button' => '#fff'])->$method($this->quote);

        return $this->request;
    }

    /**
     * Build Kasper with a mock for every constructor dependency
     *
     * @param array $options
     * @return Kasper
     */
    private function createModel(array $options): Kasper
    {
        $args = [];
        foreach ((new \ReflectionClass(Kasper::class))->getConstructor()->getParameters() as $param) {
            $args[$param->getName()] = $this->createMock($param->getType()->getName());
        }
        $args['recurringResolver'] = $this->resolver;

        $args['dataObjectFactory']->method('create')->willReturnCallback(
            fn(array $arguments = []) => new DataObject($arguments['data'] ?? [])
        );

        $address = $this->createMock(Address::class);
        $store = $this->createMock(Store::class);
        $this->quote = $this->createMock(Quote::class);
        $this->quote->method('getStore')->willReturn($store);
        $this->quote->method('getShippingAddress')->willReturn($address);
        $this->quote->method('isVirtual')->willReturn(false);

        $checkoutSession = $this->createMock(CheckoutSession::class);
        $checkoutSession->method('getQuote')->willReturn($this->quote);
        $args['kcoSession']->method('getCheckout')->willReturn($checkoutSession);

        $args['checkoutConfiguration']->method('getDesign')->willReturn($options);
        $args['parameter']->method('getMerchantReferences')->willReturn(new DataObject());
        $args['parameter']->method('getOrderLineProcessor')->willReturn($this->createMock(OrderLineProcessor::class));
        $args['parameter']->method('getOrderLines')->willReturn([]);
        $args['parameter']->method('setRequest')->willReturnCallback(
            function (array $request) {
                $this->request = $request;
            }
        );
        $args['parameter']->method('getRequest')->willReturnCallback(fn() => $this->request);

        return new Kasper(...array_values($args));
    }

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->resolver = $this->createMock(RecurringResolverInterface::class);
    }
}
