<?php
/**
 * Copyright © Kustom AB
 *
 * For the full copyright and license information, please view the NOTICE
 * and LICENSE files that were distributed with this source code.
 */
declare(strict_types=1);

namespace Klarna\Kco\Console\Command;

use Klarna\Kco\Model\Recurring\TokenRecovery;
use Klarna\Kco\Model\Recurring\TokenRecoveryFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Recovers missing recurring tokens by re-reading the Kustom checkout order. Prints increment id and outcome only,
 * never the token.
 *
 * @internal
 */
class RecoverRecurringToken extends Command
{
    public const ARGUMENT_INCREMENT_ID = 'increment_id';
    public const OPTION_FROM = 'from';
    public const OPTION_TO = 'to';

    /**
     * @var TokenRecoveryFactory
     */
    private TokenRecoveryFactory $recoveryFactory;
    /**
     * @var State
     */
    private State $appState;

    /**
     * @param TokenRecoveryFactory $recoveryFactory
     * @param State                $appState
     * @param string|null          $name
     */
    public function __construct(TokenRecoveryFactory $recoveryFactory, State $appState, ?string $name = null)
    {
        parent::__construct($name);
        $this->recoveryFactory = $recoveryFactory;
        $this->appState = $appState;
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('kustom:recurring:recover-token')
            ->setDescription(
                'Stores the missing recurring token of Kustom Checkout orders by re-reading the Kustom order'
            )
            ->addArgument(
                self::ARGUMENT_INCREMENT_ID,
                InputArgument::OPTIONAL,
                'Increment id of the Magento order'
            )
            ->addOption(
                self::OPTION_FROM,
                null,
                InputOption::VALUE_REQUIRED,
                'All Kustom orders created from this date (UTC, Y-m-d or Y-m-d H:i:s) without a token'
            )
            ->addOption(
                self::OPTION_TO,
                null,
                InputOption::VALUE_REQUIRED,
                'All Kustom orders created up to this date (UTC, Y-m-d or Y-m-d H:i:s) without a token'
            );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $incrementId = $input->getArgument(self::ARGUMENT_INCREMENT_ID);
        $from = $input->getOption(self::OPTION_FROM);
        $to = $input->getOption(self::OPTION_TO);
        $hasRange = $from !== null || $to !== null;

        if (($incrementId === null) === !$hasRange) {
            $output->writeln('<error>Give either an increment id or --from/--to.</error>');
            return Cli::RETURN_FAILURE;
        }

        $fromDate = null;
        $toDate = null;
        if ($hasRange) {
            $fromDate = $from === null ? null : $this->normalizeDate((string)$from, false);
            $toDate = $to === null ? null : $this->normalizeDate((string)$to, true);
            if (($from !== null && $fromDate === null) || ($to !== null && $toDate === null)) {
                $output->writeln('<error>Dates must be Y-m-d or Y-m-d H:i:s.</error>');
                return Cli::RETURN_FAILURE;
            }
        }

        /** @var TokenRecovery $recovery */
        $recovery = $this->recoveryFactory->create();
        $results = $this->appState->emulateAreaCode(
            Area::AREA_ADMINHTML,
            static function () use ($recovery, $incrementId, $hasRange, $fromDate, $toDate): array {
                return $hasRange
                    ? $recovery->recoverByDateRange($fromDate, $toDate)
                    : $recovery->recoverByIncrementId((string)$incrementId);
            }
        );

        return $this->printResults($results, $output);
    }

    /**
     * Prints one line per order: increment id and outcome
     *
     * @param array           $results Increment id => outcome
     * @param OutputInterface $output
     * @return int
     */
    private function printResults(array $results, OutputInterface $output): int
    {
        $failed = false;
        foreach ($results as $incrementId => $outcome) {
            $output->writeln(sprintf('%s: %s', $incrementId, $outcome));
            if ($outcome === TokenRecovery::OUTCOME_ERROR || $outcome === TokenRecovery::OUTCOME_NOT_FOUND) {
                $failed = true;
            }
        }
        if ($results === []) {
            $output->writeln('No matching orders.');
        }

        return $failed ? Cli::RETURN_FAILURE : Cli::RETURN_SUCCESS;
    }

    /**
     * Validates the date and returns it as Y-m-d H:i:s
     *
     * @param string $date
     * @param bool   $endOfDay Date-only input becomes 23:59:59
     * @return string|null
     */
    private function normalizeDate(string $date, bool $endOfDay): ?string
    {
        $dateOnly = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
        $format = $dateOnly ? 'Y-m-d' : 'Y-m-d H:i:s';
        $parsed = \DateTimeImmutable::createFromFormat('!' . $format, $date, new \DateTimeZone('UTC'));
        if ($parsed === false || $parsed->format($format) !== $date) {
            return null;
        }
        if ($dateOnly && $endOfDay) {
            $parsed = $parsed->setTime(23, 59, 59);
        }

        return $parsed->format('Y-m-d H:i:s');
    }
}
