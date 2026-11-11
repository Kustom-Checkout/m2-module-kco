<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Test\Unit\Console\Command;

use Klarna\Kco\Console\Command\RecoverRecurringToken;
use Klarna\Kco\Model\Recurring\TokenRecovery;
use Klarna\Kco\Model\Recurring\TokenRecoveryFactory;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @coversDefaultClass \Klarna\Kco\Console\Command\RecoverRecurringToken
 */
class RecoverRecurringTokenTest extends TestCase
{
    private const SECRET = 'tok_secret_value_123';

    /**
     * @var TokenRecovery|MockObject
     */
    private $recovery;
    /**
     * @var CommandTester
     */
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->recovery = $this->createMock(TokenRecovery::class);
        $factory = $this->createMock(TokenRecoveryFactory::class);
        $factory->method('create')->willReturn($this->recovery);
        $state = $this->createMock(State::class);
        $state->method('emulateAreaCode')->willReturnCallback(
            static fn(string $area, callable $callback) => $callback()
        );
        $this->tester = new CommandTester(new RecoverRecurringToken($factory, $state));
    }

    /**
     * @covers ::execute
     */
    #[DataProvider('outcomeProvider')]
    public function testPrintsIncrementIdAndOutcomeOnly(string $outcome, int $exitCode): void
    {
        $this->recovery->expects($this->once())->method('recoverByIncrementId')->with('100000001')
            ->willReturn(['100000001' => $outcome]);

        $this->assertSame($exitCode, $this->tester->execute(['increment_id' => '100000001']));
        $this->assertSame('100000001: ' . $outcome . PHP_EOL, $this->tester->getDisplay());
        $this->assertStringNotContainsString(self::SECRET, $this->tester->getDisplay());
    }

    public static function outcomeProvider(): array
    {
        return [
            'persisted' => [TokenRecovery::OUTCOME_PERSISTED, Cli::RETURN_SUCCESS],
            'already present' => [TokenRecovery::OUTCOME_ALREADY_PRESENT, Cli::RETURN_SUCCESS],
            'not recurring' => [TokenRecovery::OUTCOME_NOT_RECURRING, Cli::RETURN_SUCCESS],
            'no token' => [TokenRecovery::OUTCOME_NO_TOKEN, Cli::RETURN_SUCCESS],
            'not readable' => [TokenRecovery::OUTCOME_NOT_READABLE, Cli::RETURN_SUCCESS],
            'not kustom' => [TokenRecovery::OUTCOME_NOT_KUSTOM, Cli::RETURN_SUCCESS],
            'not found' => [TokenRecovery::OUTCOME_NOT_FOUND, Cli::RETURN_FAILURE],
            'error' => [TokenRecovery::OUTCOME_ERROR, Cli::RETURN_FAILURE],
        ];
    }

    /**
     * @covers ::execute
     */
    public function testRangeNonFatalOutcomesKeepExitCodeZero(): void
    {
        $this->recovery->expects($this->once())->method('recoverByDateRange')
            ->with('2026-01-01 00:00:00', '2026-01-31 23:59:59')
            ->willReturn([
                '1' => TokenRecovery::OUTCOME_NOT_READABLE,
                '2' => TokenRecovery::OUTCOME_PERSISTED,
            ]);

        $code = $this->tester->execute(['--from' => '2026-01-01', '--to' => '2026-01-31']);

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertSame(
            '1: order no longer readable' . PHP_EOL . '2: persisted' . PHP_EOL,
            $this->tester->getDisplay()
        );
    }

    /**
     * @covers ::execute
     */
    public function testRangeWithErrorFailsAfterProcessingAll(): void
    {
        $this->recovery->method('recoverByDateRange')->willReturn([
            '1' => TokenRecovery::OUTCOME_ERROR,
            '2' => TokenRecovery::OUTCOME_PERSISTED,
        ]);

        $this->assertSame(Cli::RETURN_FAILURE, $this->tester->execute(['--from' => '2026-01-01']));
        $this->assertStringContainsString('2: persisted', $this->tester->getDisplay());
    }

    /**
     * @covers ::execute
     */
    #[DataProvider('invalidInputProvider')]
    public function testInvalidInput(array $input): void
    {
        $this->recovery->expects($this->never())->method('recoverByIncrementId');
        $this->recovery->expects($this->never())->method('recoverByDateRange');

        $this->assertSame(Cli::RETURN_FAILURE, $this->tester->execute($input));
    }

    public static function invalidInputProvider(): array
    {
        return [
            'nothing' => [[]],
            'both' => [['increment_id' => '1', '--from' => '2026-01-01']],
            'bad date' => [['--from' => '01/02/2026']],
            'impossible date' => [['--to' => '2026-02-30']],
        ];
    }
}
