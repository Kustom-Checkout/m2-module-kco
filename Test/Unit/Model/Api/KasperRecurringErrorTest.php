<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Api;

use Klarna\AdminSettings\Model\Configurations\Api as ApiConfiguration;
use Klarna\Base\Exception as KlarnaException;
use Klarna\Kco\Model\Api\Builder\Kasper as KasperBuilder;
use Klarna\Kco\Model\Api\Kasper;
use Klarna\Kco\Model\Api\Rest\Service\Checkout;
use Klarna\Kco\Model\Checkout\Kco\Session as KcoSession;
use Klarna\Logger\Api\LoggerInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Api\Kasper
 */
class KasperRecurringErrorTest extends TestCase
{
    /**
     * @var Checkout|MockObject
     */
    private $checkout;
    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;
    /**
     * @var ApiConfiguration|MockObject
     */
    private $apiConfiguration;
    /**
     * @var KasperBuilder|MockObject
     */
    private $builder;

    protected function setUp(): void
    {
        $this->checkout = $this->createMock(Checkout::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->apiConfiguration = $this->createMock(ApiConfiguration::class);
        $this->apiConfiguration->method('getUserName')->willReturn('K123_mid');
        $this->builder = $this->createMock(KasperBuilder::class);
    }

    /**
     * @covers ::createOrder
     */
    public function testRecurringNotEnabledIsLoggedAndCheckoutStillFails(): void
    {
        $this->checkout->method('createOrder')->willReturn(
            $this->error(400, 'BAD_VALUE', ['recurring is not enabled'])
        );
        $this->logger->expects(static::once())->method('error')->with(static::callback(
            function (string $message): bool {
                foreach (['not enabled', 'merchant support', 'K123_mid', 'BAD_VALUE', 'corr-1', '31', '9'] as $part) {
                    if (!str_contains($message, $part)) {
                        return false;
                    }
                }
                return !str_contains($message, 'secret');
            }
        ));

        $this->expectException(KlarnaException::class);
        $this->expectExceptionMessage('Unable to initialize Kustom checkout order. Kustom api error: recurring');
        $this->model($this->builder(['recurring' => true]))->createOrder();
    }

    /**
     * @covers ::createOrder
     */
    public function testOtherBadValueIsNotLogged(): void
    {
        $this->checkout->method('createOrder')->willReturn($this->error(400, 'BAD_VALUE', ['order_amount wrong']));
        $this->logger->expects(static::never())->method('error');

        $this->expectException(KlarnaException::class);
        $this->model($this->builder(['recurring' => true]))->createOrder();
    }

    /**
     * @covers ::createOrder
     */
    public function testNonRecurringRequestIsNotLogged(): void
    {
        $this->checkout->method('createOrder')->willReturn($this->error(400, 'BAD_VALUE', ['recurring']));
        $this->logger->expects(static::never())->method('error');

        $this->expectException(KlarnaException::class);
        $this->model($this->builder(['order_amount' => 1]))->createOrder();
    }

    /**
     * @covers ::createOrder
     */
    public function testOtherStatusIsNotLogged(): void
    {
        $this->checkout->method('createOrder')->willReturn($this->error(403, 'BAD_VALUE', ['recurring']));
        $this->logger->expects(static::never())->method('error');

        $this->expectException(KlarnaException::class);
        $this->model($this->builder(['recurring' => true]))->createOrder();
    }

    private function error(int $status, string $code, array $messages): array
    {
        return [
            'is_successful' => false,
            'response_status_code' => $status,
            'error_code' => $code,
            'error_messages' => $messages,
            'correlation_id' => 'corr-1',
        ];
    }

    private function builder(array $request): KasperBuilder
    {
        $this->builder->method('generateCreateRequest')->willReturnSelf();
        $this->builder->method('getRequest')->willReturn($request);
        return $this->builder;
    }

    private function model(KasperBuilder $builder): Kasper
    {
        $factory = $this->createMock(DataObjectFactory::class);
        $factory->method('create')->willReturnCallback(
            fn(array $args = []): DataObject => new DataObject($args['data'] ?? [])
        );
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getStoreId', 'getStore', '__call'])
            ->getMock();
        $quote->method('getId')->willReturn(9);
        $quote->method('getStoreId')->willReturn(31);
        $quote->method('getStore')->willReturn($this->createStub(Store::class));
        // getBaseCurrencyCode is a magic getter
        $quote->method('__call')->willReturn('SEK');
        return new Kasper(
            $this->createStub(KcoSession::class),
            $this->checkout,
            $factory,
            $builder,
            ['quote' => $quote],
            $this->logger,
            $this->apiConfiguration
        );
    }
}
