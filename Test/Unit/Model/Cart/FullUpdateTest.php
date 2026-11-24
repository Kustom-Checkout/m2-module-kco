<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Model\Cart;

use Klarna\Base\Model\Quote\Address\Handler;
use Klarna\Base\Model\Quote\Customer;
use Klarna\Base\Model\Quote\ShippingMethod\SelectionAssurance;
use Klarna\Kco\Model\Cart\FullUpdate;
use Klarna\Kco\Model\Cart\Payment;
use Klarna\Kco\Model\Checkout\Company\Update;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Rate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Model\Cart\FullUpdate
 */
class FullUpdateTest extends TestCase
{
    /**
     * @var FullUpdate
     */
    private FullUpdate $model;
    /**
     * @var SelectionAssurance|MockObject
     */
    private $selectionAssurance;
    /**
     * @var Handler|MockObject
     */
    private $handler;
    /**
     * @var Customer|MockObject
     */
    private $customer;
    /**
     * @var Quote|MockObject
     */
    private $quote;
    /**
     * @var Address|MockObject
     */
    private $address;
    /**
     * @var string[]
     */
    private array $calls = [];
    /**
     * @var Payment|MockObject
     */
    private $payment;
    /**
     * @var bool[]
     */
    private array $addToQuoteArgs = [];
    /**
     * @var bool Current state of the transient keep-rates flag on the address
     */
    private bool $keepFlag = false;
    /**
     * @var array Every change of the flag, in order
     */
    private array $flagChanges = [];

    /**
     * @covers ::updateByKlarnaRequestObject
     */
    public function testAlwaysUpdatesAddressesCustomerAndQuote(): void
    {
        $this->handler->expects(static::once())->method('setBillingAddressDataFromRequest');
        $this->handler->expects(static::once())->method('setShippingAddressDataFromRequest');
        $this->customer->expects(static::once())->method('setCustomerDataFromRequest');
        $this->quote->expects(static::once())->method('collectTotals');

        $this->model->updateByKlarnaRequestObject($this->request('checkout_complete'), '1', $this->quote);
    }

    /**
     * @covers ::updateByKlarnaRequestObject
     */
    public function testCompletedOrderWithStoredRateReusesRate(): void
    {
        $this->prepareAddress('flatrate_flatrate', $this->rate(false));
        $this->expectStoredRateReused();

        $this->model->updateByKlarnaRequestObject($this->request('checkout_complete'), '1', $this->quote);

        static::assertSame([false], $this->addToQuoteArgs);
        static::assertSame(['set', 'unset'], $this->flagChanges);
        static::assertFalse($this->keepFlag);
        static::assertSame(
            [
                'flag:false', 'addToQuote:false', 'flag:false', 'ensure', 'collectTotals',
                'repoSave', 'flag:false', 'addressSave'
            ],
            $this->calls
        );
    }

    /**
     * @covers ::updateByKlarnaRequestObject
     */
    #[DataProvider('reusedStatusProvider')]
    public function testStatusIsComparedCaseInsensitively(string $status): void
    {
        $this->prepareAddress('flatrate_flatrate', $this->rate(false));
        $this->expectStoredRateReused();

        $this->model->updateByKlarnaRequestObject($this->request($status), '1', $this->quote);

        static::assertSame([false], $this->addToQuoteArgs);
        static::assertSame(
            [
                'flag:false', 'addToQuote:false', 'flag:false', 'ensure', 'collectTotals',
                'repoSave', 'flag:false', 'addressSave'
            ],
            $this->calls
        );
    }

    /**
     * @return string[][]
     */
    public static function reusedStatusProvider(): array
    {
        return [['CHECKOUT_COMPLETE'], ['created'], ['Created']];
    }

    /**
     * @covers ::updateByKlarnaRequestObject
     */
    public function testCompletedOrderWithoutStoredRateCollectsRates(): void
    {
        $this->prepareAddress('flatrate_flatrate', false);
        $this->expectPreCollect();

        $this->model->updateByKlarnaRequestObject($this->request('checkout_complete'), '1', $this->quote);

        static::assertSame([true], $this->addToQuoteArgs);
    }

    /**
     * @covers ::updateByKlarnaRequestObject
     */
    public function testCompletedOrderWithoutShippingMethodCollectsRates(): void
    {
        $this->prepareAddress(null, false);
        $this->expectPreCollect();

        $this->model->updateByKlarnaRequestObject($this->request('checkout_complete'), '1', $this->quote);

        static::assertSame([true], $this->addToQuoteArgs);
    }

    /**
     * @covers ::updateByKlarnaRequestObject
     */
    public function testDeletedStoredRateCollectsRates(): void
    {
        $this->prepareAddress('flatrate_flatrate', $this->rate(true));
        $this->expectPreCollect();

        $this->model->updateByKlarnaRequestObject($this->request('checkout_complete'), '1', $this->quote);

        static::assertSame([true], $this->addToQuoteArgs);
    }

    /**
     * @covers ::updateByKlarnaRequestObject
     */
    #[DataProvider('notCompletedProvider')]
    public function testNotCompletedOrderCollectsRates(?string $status): void
    {
        $this->prepareAddress('flatrate_flatrate', $this->rate(false));
        $this->expectPreCollect();

        $this->model->updateByKlarnaRequestObject($this->request($status), '1', $this->quote);

        static::assertSame([true], $this->addToQuoteArgs);
    }

    /**
     * @return array
     */
    public static function notCompletedProvider(): array
    {
        return [['checkout_incomplete'], [null], ['']];
    }

    /**
     * @covers ::updateByKlarnaRequestObject
     */
    public function testVirtualQuoteIsUnchanged(): void
    {
        $this->quote->method('isVirtual')->willReturn(true);
        $this->address->expects(static::never())->method('getShippingRateByCode');
        $this->expectPreCollect();

        $this->model->updateByKlarnaRequestObject($this->request('checkout_complete'), '1', $this->quote);

        static::assertSame([true], $this->addToQuoteArgs);
    }

    /**
     * @param string|null $status
     * @return DataObject
     */
    private function request(?string $status): DataObject
    {
        $data = [
            'billing_address' => ['country' => 'SE', 'city' => 'A'],
            'shipping_address' => ['country' => 'SE', 'city' => 'A'],
        ];
        if ($status !== null) {
            $data['status'] = $status;
        }

        return new DataObject($data);
    }

    /**
     * @param bool $deleted
     * @return Rate|MockObject
     */
    private function rate(bool $deleted)
    {
        $rate = $this->createMock(Rate::class);
        $rate->method('isDeleted')->willReturn($deleted);

        return $rate;
    }

    /**
     * @param string|null $method
     * @param Rate|false $rate
     */
    private function prepareAddress(?string $method, $rate): void
    {
        $this->address->method('getShippingMethod')->willReturn($method);
        $this->address->method('getShippingRateByCode')->willReturn($rate);
    }

    private function expectStoredRateReused(): void
    {
        $this->selectionAssurance->expects(static::never())->method('ensureShippingMethodSelectedWithPreCollect');
        $this->selectionAssurance->expects(static::once())->method('ensureShippingMethodSelected')
            ->willReturnCallback(function () {
                $this->calls[] = 'ensure';
                return $this->selectionAssurance;
            });
        $this->address->expects(static::once())->method('save')->willReturnCallback(function () {
            $this->calls[] = 'addressSave';
            return $this->address;
        });
        $this->address->method('__call')->willReturnCallback(function (string $name, array $args) {
            if ($name === 'setCollectShippingRates' && $args === [false]) {
                $this->calls[] = 'flag:false';
            }
            return $this->address;
        });
    }

    private function expectPreCollect(): void
    {
        $this->selectionAssurance->expects(static::once())->method('ensureShippingMethodSelectedWithPreCollect');
        $this->address->expects(static::never())->method('save');
        $this->selectionAssurance->expects(static::never())->method('ensureShippingMethodSelected');
        $this->address->method('__call')->willReturnCallback(function (string $name, array $args) {
            $this->calls[] = $name;
            return $this->address;
        });
    }

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $this->calls = [];
        $this->addToQuoteArgs = [];
        $this->keepFlag = false;
        $this->flagChanges = [];
        $this->payment = $this->createMock(Payment::class);
        $this->payment->method('addToQuote')->willReturnCallback(function ($quote) {
            $collect = !$this->keepFlag;
            $this->addToQuoteArgs[] = $collect;
            $this->calls[] = 'addToQuote:' . ($collect ? 'true' : 'false');
        });
        $this->selectionAssurance = $this->createMock(SelectionAssurance::class);
        $this->handler = $this->createMock(Handler::class);
        $this->customer = $this->createMock(Customer::class);
        $this->address = $this->createMock(Address::class);
        $this->address->method('setData')->willReturnCallback(function ($key, $value = null) {
            if ($key === Payment::KEEP_SHIPPING_RATES_FLAG) {
                $this->keepFlag = true;
                $this->flagChanges[] = 'set';
            }
            return $this->address;
        });
        $this->address->method('unsetData')->willReturnCallback(function ($key = null) {
            if ($key === Payment::KEEP_SHIPPING_RATES_FLAG) {
                $this->keepFlag = false;
                $this->flagChanges[] = 'unset';
            }
            return $this->address;
        });
        $this->quote = $this->createMock(Quote::class);
        $this->quote->method('getShippingAddress')->willReturn($this->address);
        $this->quote->method('getReservedOrderId')->willReturn('100000001');
        $this->quote->method('collectTotals')->willReturnCallback(function () {
            $this->calls[] = 'collectTotals';
            return $this->quote;
        });

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('save')->willReturnCallback(function () {
            $this->calls[] = 'repoSave';
        });

        $this->model = new FullUpdate(
            $this->customer,
            $this->handler,
            $this->selectionAssurance,
            $cartRepository,
            $this->createMock(Update::class),
            $this->payment
        );
    }
}
