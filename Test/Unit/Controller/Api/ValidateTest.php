<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Controller\Api;

use Klarna\Base\Exception as KlarnaException;
use Klarna\Base\Model\Responder\Result;
use Klarna\Kco\Controller\Api\Validate;
use Klarna\Kco\Exception\AddressValidationException;
use Klarna\Kco\Model\Cart\Validations\Handler;
use Klarna\Kco\Model\Responder\Klarna;
use Klarna\Kco\Model\WorkflowProvider;
use Klarna\Logger\Api\LoggerInterface;
use Klarna\Logger\Model\Api\Container;
use Klarna\Logger\Model\Api\Logger;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Klarna\Kco\Controller\Api\Validate
 */
class ValidateTest extends TestCase
{
    /**
     * @var Validate
     */
    private Validate $controller;
    /**
     * @var Handler|MockObject
     */
    private $handler;
    /**
     * @var Result|MockObject
     */
    private $result;
    /**
     * @var RedirectFactory|MockObject
     */
    private $redirectFactory;
    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;
    /**
     * @var Logger|MockObject
     */
    private $apiLogger;

    protected function setUp(): void
    {
        $this->handler = $this->createMock(Handler::class);
        $this->result = $this->createMock(Result::class);
        $this->redirectFactory = $this->createMock(RedirectFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->apiLogger = $this->createMock(Logger::class);
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturn('checkout-id-1');
        $klarna = $this->createMock(Klarna::class);
        $klarna->method('getKlarnaRequestBody')->willReturn(new DataObject());

        $this->controller = new Validate(
            $this->logger,
            $this->handler,
            $this->createMock(WorkflowProvider::class),
            $this->result,
            $klarna,
            $this->apiLogger,
            $this->createMock(Container::class),
            $request,
            $this->createMock(ManagerInterface::class),
            $this->redirectFactory
        );
    }

    /**
     * @covers ::execute
     */
    public function testExecuteAnswers400AddressErrorForAddressValidationException(): void
    {
        $this->handler->method('validateRequestObject')
            ->willThrowException(new AddressValidationException(__('We could not use this address.')));
        $json = $this->createMock(Json::class);
        $this->result->expects(static::once())->method('getJsonResult')
            ->with(
                400,
                ['error_type' => 'address_error', 'error_text' => 'We could not use this address.']
            )
            ->willReturn($json);
        $this->redirectFactory->expects(static::never())->method('create');
        $this->apiLogger->expects(static::never())->method('logCallback');

        static::assertSame($json, $this->controller->execute());
    }

    /**
     * @covers ::execute
     */
    public function testExecuteKeeps303ForOtherKlarnaExceptions(): void
    {
        $this->handler->method('validateRequestObject')
            ->willThrowException(new KlarnaException(__('Order total does not match')));
        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setHttpResponseCode')->with(303)->willReturnSelf();
        $redirect->method('setStatusHeader')->willReturnSelf();
        $redirect->method('setPath')->willReturnSelf();
        $this->redirectFactory->expects(static::once())->method('create')->willReturn($redirect);
        $this->result->expects(static::never())->method('getJsonResult');

        static::assertSame($redirect, $this->controller->execute());
    }

    /**
     * @covers ::execute
     */
    public function testExecuteAnswers200OnSuccess(): void
    {
        $json = $this->createMock(Json::class);
        $this->result->expects(static::once())->method('getJsonResult')->with(200)->willReturn($json);

        static::assertSame($json, $this->controller->execute());
    }
}
