<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Block\Adminhtml\Order;

use Klarna\Kco\Api\CustomerTokenRepositoryInterface;
use Klarna\Kco\Api\Data\CustomerTokenInterface;
use Klarna\Kco\Block\Adminhtml\Order\CustomerToken;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CustomerTokenTest extends TestCase
{
    /** @var CustomerTokenRepositoryInterface|MockObject */
    private $repository;
    /** @var AuthorizationInterface|MockObject */
    private $authorization;
    /** @var Registry|MockObject */
    private $registry;
    /** @var CustomerToken */
    private $block;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CustomerTokenRepositoryInterface::class);
        $this->authorization = $this->createMock(AuthorizationInterface::class);
        $this->registry = $this->createMock(Registry::class);
        $this->block = new CustomerToken(
            $this->createMock(Context::class),
            $this->repository,
            $this->authorization,
            $this->registry
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function maskProvider(): array
    {
        return [
            'empty' => ['', ''],
            '1 char' => ['a', '*'],
            '2 chars' => ['ab', '**'],
            '3 chars' => ['abc', '***'],
            '4 chars' => ['abcd', '****'],
            '5 chars' => ['abcde', '****bcde'],
            'long' => ['0123456789abcdef', '****cdef'],
        ];
    }

    #[DataProvider('maskProvider')]
    public function testMask(string $token, string $expected): void
    {
        $this->assertSame($expected, $this->block->mask($token));
    }

    public function testMaskedTokenForAllowedAdmin(): void
    {
        $this->allow(true);
        $this->withOrder(5);
        $tokenData = $this->createMock(CustomerTokenInterface::class);
        $tokenData->method('getToken')->willReturn('secret-token-abcd');
        $this->repository->expects($this->once())->method('getByOrderId')->with(5)->willReturn($tokenData);

        $this->assertSame('****abcd', $this->block->getMaskedToken());
        $this->assertSame('****abcd', $this->block->getMaskedToken());
    }

    public function testAclDeniedDoesNotQueryTheRepository(): void
    {
        $this->allow(false);
        $this->repository->expects($this->never())->method('getByOrderId');

        $this->assertNull($this->block->getMaskedToken());
        $this->assertSame('', $this->renderHtml());
    }

    public function testNoTokenRowHidesBlock(): void
    {
        $this->allow(true);
        $this->withOrder(5);
        $this->repository->method('getByOrderId')->willThrowException(new NoSuchEntityException());

        $this->assertNull($this->block->getMaskedToken());
        $this->assertSame('', $this->renderHtml());
    }

    public function testEmptyStoredTokenHidesBlock(): void
    {
        $this->allow(true);
        $this->withOrder(5);
        $tokenData = $this->createMock(CustomerTokenInterface::class);
        $tokenData->method('getToken')->willReturn('');
        $this->repository->method('getByOrderId')->willReturn($tokenData);

        $this->assertNull($this->block->getMaskedToken());
    }

    public function testNoCurrentOrderHidesBlock(): void
    {
        $this->allow(true);
        $this->registry->method('registry')->with('current_order')->willReturn(null);
        $this->repository->expects($this->never())->method('getByOrderId');

        $this->assertNull($this->block->getMaskedToken());
    }

    public function testNonKustomOrderDoesNotQueryTheRepository(): void
    {
        $this->allow(true);
        $this->withOrder(5, 'checkmo');
        $this->repository->expects($this->never())->method('getByOrderId');

        $this->assertNull($this->block->getMaskedToken());
    }

    public function testOrderWithoutPaymentDoesNotQueryTheRepository(): void
    {
        $this->allow(true);
        $this->withOrder(5, null);
        $this->repository->expects($this->never())->method('getByOrderId');

        $this->assertNull($this->block->getMaskedToken());
    }

    private function allow(bool $allowed): void
    {
        $this->authorization->method('isAllowed')->with('Klarna_Kco::customer_token')->willReturn($allowed);
    }

    private function withOrder(int $id, ?string $method = 'klarna_kco'): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn($id);
        if ($method === null) {
            $order->method('getPayment')->willReturn(null);
        } else {
            $payment = $this->createMock(OrderPaymentInterface::class);
            $payment->method('getMethod')->willReturn($method);
            $order->method('getPayment')->willReturn($payment);
        }
        $this->registry->method('registry')->with('current_order')->willReturn($order);
    }

    private function renderHtml(): string
    {
        $method = new \ReflectionMethod(CustomerToken::class, '_toHtml');
        return (string) $method->invoke($this->block);
    }
}
