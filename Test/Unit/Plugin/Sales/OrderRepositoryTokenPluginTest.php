<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Plugin\Sales;

use Klarna\Kco\Api\CustomerTokenRepositoryInterface;
use Klarna\Kco\Api\Data\CustomerTokenInterface;
use Klarna\Kco\Plugin\Sales\OrderRepositoryTokenPlugin;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderRepositoryTokenPluginTest extends TestCase
{
    /** @var CustomerTokenRepositoryInterface&MockObject */
    private $tokenRepository;
    /** @var OrderRepositoryInterface&MockObject */
    private $subject;
    private OrderRepositoryTokenPlugin $plugin;

    protected function setUp(): void
    {
        $this->tokenRepository = $this->createMock(CustomerTokenRepositoryInterface::class);
        $this->subject = $this->createMock(OrderRepositoryInterface::class);
        $this->plugin = new OrderRepositoryTokenPlugin($this->tokenRepository);
    }

    /**
     * Order with an extension object that records setKustomCustomerToken (the generated class only exists
     * after setup:di:compile, so a plain stand-in is used)
     *
     * @return array{0: OrderInterface&MockObject, 1: \stdClass}
     */
    private function order(int $id, ?string $method = 'klarna_kco'): array
    {
        $extension = new class {
            public $token = null;
            public int $calls = 0;
            public function setKustomCustomerToken($token): void
            {
                $this->token = $token;
                $this->calls++;
            }
        };
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn($id);
        $order->method('getExtensionAttributes')->willReturn($extension);
        if ($method === null) {
            $order->method('getPayment')->willReturn(null);
        } else {
            $payment = $this->createMock(OrderPaymentInterface::class);
            $payment->method('getMethod')->willReturn($method);
            $order->method('getPayment')->willReturn($payment);
        }
        return [$order, $extension];
    }

    public function testAfterGetTokenLookupFailureLeavesTheAttributeNull(): void
    {
        [$order, $extension] = $this->order(5);
        $this->tokenRepository->method('getListByOrderIds')->willThrowException(new \RuntimeException('db down'));

        $this->assertSame($order, $this->plugin->afterGet($this->subject, $order));
        $this->assertSame(0, $extension->calls);
    }

    public function testAfterGetListTokenLookupFailureLeavesTheAttributesNull(): void
    {
        [$o1, $e1] = $this->order(1);
        $this->tokenRepository->method('getListByOrderIds')->willThrowException(new \RuntimeException('db down'));
        $result = $this->createMock(OrderSearchResultInterface::class);
        $result->method('getItems')->willReturn([$o1]);

        $this->assertSame($result, $this->plugin->afterGetList($this->subject, $result));
        $this->assertSame(0, $e1->calls);
    }

    public function testAfterGetPopulatesTokenForPhpCallersWithoutAdminSession(): void
    {
        [$order, $extension] = $this->order(5);
        $token = $this->createMock(CustomerTokenInterface::class);
        $this->tokenRepository->expects($this->once())->method('getListByOrderIds')
            ->with([5])->willReturn([5 => $token]);

        $this->plugin->afterGet($this->subject, $order);
        $this->assertSame($token, $extension->token);
    }

    public function testAfterGetOrderWithoutTokenRowStaysNull(): void
    {
        [$order, $extension] = $this->order(5);
        $this->tokenRepository->method('getListByOrderIds')->willReturn([]);

        $this->plugin->afterGet($this->subject, $order);
        $this->assertSame(0, $extension->calls);
        $this->assertNull($extension->token);
    }

    public function testAfterGetListUsesOneQueryAndSkipsOrdersWithoutToken(): void
    {
        [$o1, $e1] = $this->order(1);
        [$o2, $e2] = $this->order(2);
        [$o3, $e3] = $this->order(3);
        $t1 = $this->createMock(CustomerTokenInterface::class);
        $t3 = $this->createMock(CustomerTokenInterface::class);
        $this->tokenRepository->expects($this->once())->method('getListByOrderIds')
            ->with([1, 2, 3])->willReturn([1 => $t1, 3 => $t3]);
        $result = $this->createMock(OrderSearchResultInterface::class);
        $result->method('getItems')->willReturn([$o1, $o2, $o3]);

        $this->assertSame($result, $this->plugin->afterGetList($this->subject, $result));
        $this->assertSame($t1, $e1->token);
        $this->assertNull($e2->token);
        $this->assertSame($t3, $e3->token);
    }

    public function testEmptyListDoesNotQuery(): void
    {
        $this->tokenRepository->expects($this->never())->method('getListByOrderIds');
        $result = $this->createMock(OrderSearchResultInterface::class);
        $result->method('getItems')->willReturn([]);

        $this->assertSame($result, $this->plugin->afterGetList($this->subject, $result));
    }

    public function testAfterGetNonKustomOrderDoesNotQuery(): void
    {
        [$order, $extension] = $this->order(5, 'checkmo');
        $this->tokenRepository->expects($this->never())->method('getListByOrderIds');
        $this->tokenRepository->expects($this->never())->method('getByOrderId');

        $this->assertSame($order, $this->plugin->afterGet($this->subject, $order));
        $this->assertSame(0, $extension->calls);
    }

    public function testAfterGetOrderWithoutPaymentDoesNotQueryNorFail(): void
    {
        [$order, $extension] = $this->order(5, null);
        $this->tokenRepository->expects($this->never())->method('getListByOrderIds');

        $this->assertSame($order, $this->plugin->afterGet($this->subject, $order));
        $this->assertSame(0, $extension->calls);
    }

    public function testAfterGetPaymentThrowingDoesNotQueryNorFail(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(5);
        $order->method('getPayment')->willThrowException(new \RuntimeException('boom'));
        $this->tokenRepository->expects($this->never())->method('getListByOrderIds');

        $this->assertSame($order, $this->plugin->afterGet($this->subject, $order));
    }

    public function testAfterGetListOnlyNonKustomOrdersDoesNotQuery(): void
    {
        [$o1, $e1] = $this->order(1, 'checkmo');
        [$o2, $e2] = $this->order(2, null);
        $this->tokenRepository->expects($this->never())->method('getListByOrderIds');
        $result = $this->createMock(OrderSearchResultInterface::class);
        $result->method('getItems')->willReturn([$o1, $o2]);

        $this->assertSame($result, $this->plugin->afterGetList($this->subject, $result));
        $this->assertSame(0, $e1->calls + $e2->calls);
    }

    public function testAfterGetListQueriesOnlyKustomOrderIds(): void
    {
        [$o1] = $this->order(1, 'checkmo');
        [$o2, $e2] = $this->order(2);
        [$o3] = $this->order(3, null);
        $t2 = $this->createMock(CustomerTokenInterface::class);
        $this->tokenRepository->expects($this->once())->method('getListByOrderIds')
            ->with([2])->willReturn([2 => $t2]);
        $result = $this->createMock(OrderSearchResultInterface::class);
        $result->method('getItems')->willReturn([$o1, $o2, $o3]);

        $this->plugin->afterGetList($this->subject, $result);
        $this->assertSame($t2, $e2->token);
    }
}
