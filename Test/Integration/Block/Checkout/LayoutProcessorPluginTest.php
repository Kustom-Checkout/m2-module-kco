<?php

/**
 * Copyright © Klarna Bank AB (publ)
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */

declare(strict_types=1);

namespace Klarna\Kco\Test\Integration\Block\Checkout;

use Klarna\Kco\Model\Api\Rest\Service\Checkout as CheckoutApi;
use Magento\Checkout\Block\Checkout\LayoutProcessor;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klarna\Kco\Block\Checkout\LayoutProcessorPlugin
 */
class LayoutProcessorPluginTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CheckoutApi|MockObject
     */
    private $checkoutApiMock;

    /**
     * @var LayoutProcessor
     */
    private $model;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();

        $this->checkoutSession = $this->objectManager->get(CheckoutSession::class);
        $this->checkoutApiMock = $this->createMock(CheckoutApi::class);
        $this->objectManager->addSharedInstance($this->checkoutApiMock, CheckoutApi::class);

        $this->model = $this->objectManager->create(LayoutProcessor::class);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testAfterProcessShouldIncludeHtmlSnippetInLayoutBasedOnApiResponse(): void
    {
        $expectedHtmlSnippet = '<p>I am HTML</p>';

        $quote = $this->checkoutSession->getQuote();
        $quote->save();

        $this->checkoutApiMock->expects($this->atLeastOnce())->method('createOrder')
            ->willReturn([
                'id' => '12345',
                'is_successful' => true,
                'html_snippet' => $expectedHtmlSnippet,
            ]);

        $result = $this->model->process($this->generateDummyJsLayout());
        $htmlSnippet = $result['components']['checkout']['children']['steps']['children']['klarna_kco']['klarna_iframe'] ?? '';
        $this->assertEquals($expectedHtmlSnippet, $htmlSnippet);
    }

    /**
     * @return mixed[]
     */
    private function generateDummyJsLayout(): array
    {
        $jsLayout = [];
        $jsLayout['components']['checkout']['children']['steps']['children'] = [
            'klarna_kco' => [],
            'billing-step' => [
                'children' => [
                    'payment' => [
                        'children' => [
                            'payments-list' => ['children' => []],
                            'renders' => ['children' => []],
                        ],
                    ],
                ],
            ],
            'shipping-step' => [
                'children' => [
                    'step-config' => [
                        'children' => [
                            'shipping-rates-validation' => [
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $jsLayout;
    }
}
