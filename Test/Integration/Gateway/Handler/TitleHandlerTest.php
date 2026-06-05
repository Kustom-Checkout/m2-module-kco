<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Gateway\Handler;

use Klarna\Kco\Gateway\Handler\TitleHandler;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Gateway\Data\Order\OrderAdapter;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order\Payment\Info;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class TitleHandlerTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var TitleHandler
     */
    private $titleHandler;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();

        $this->titleHandler = $this->objectManager->create(TitleHandler::class);
    }

    /**
     * @dataProvider handleDataProvider
     * @magentoAppIsolation enabled
     */
    public function testHandle(array $additionalInfo, string $expectedTitle): void
    {
        $subject = $this->createHandleSubject($additionalInfo);
        $title = $this->titleHandler->handle($subject);
        $this->assertEquals($expectedTitle, $title);
    }

    /**
     * @return mixed[]
     */
    public static function handleDataProvider(): array
    {
        return [
            'should return the default title with no additional info' => [
                'additionalInfo' => [],
                'expectedTitle' => 'Kustom Checkout',
            ],
            'should return the default title appended with additional info' => [
                'additionalInfo' => [
                    'method_title' => 'Kustom Checkout',
                ],
                'expectedTitle' => 'Kustom Checkout - Kustom Checkout',
            ],
            'should return the default title appended with additional info and Klarna replaced with Kustom' => [
                'additionalInfo' => [
                    'method_title' => 'Klarna Checkout',
                ],
                'expectedTitle' => 'Kustom Checkout - Kustom Checkout',
            ],
        ];
    }

    /**
     * @param mixed[] $additionalInfo
     *
     * @return PaymentDataObject[]
     * @throws LocalizedException
     */
    private function createHandleSubject(array $additionalInfo): array
    {
        /** @var Info $paymentInfo */
        $paymentInfo = $this->objectManager->create(Info::class);
        $paymentInfo->setAdditionalInformation($additionalInfo);

        /** @var OrderAdapter $orderAdapter */
        $orderAdapter = $this->objectManager->create(OrderAdapter::class);
        /** @var PaymentDataObject $paymentData */
        $paymentData = $this->objectManager->create(PaymentDataObject::class, [
            'order' => $orderAdapter,
            'payment' => $paymentInfo,
        ]);

        return ['payment' => $paymentData];
    }
}
