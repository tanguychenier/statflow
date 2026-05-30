<?php

declare(strict_types=1);

/*
 * This file is part of Statflow.
 *
 * (c) Tanguy Chénier <tanguychenier@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Reporting\Infrastructure\Console;

use App\Reporting\Domain\Model\ScheduledReport;
use App\Reporting\Domain\Port\Clock;
use App\Reporting\Domain\Port\ScheduledReportRepository;
use App\Reporting\Infrastructure\Messenger\ScheduledReportDue;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Sweeps for scheduled reports whose next send time has passed and enqueues a
 * delivery job for each. Meant to run frequently (e.g. every minute) from the
 * host scheduler; the per-report consumer re-checks dueness, so an overlapping
 * run never double-sends.
 */
#[AsCommand(
    name: 'statflow:reporting:dispatch-due-reports',
    description: 'Enqueue delivery jobs for scheduled reports that are due.',
)]
final class DispatchDueReportsCommand extends Command
{
    private const BATCH_SIZE = 200;

    public function __construct(
        private readonly ScheduledReportRepository $schedules,
        private readonly MessageBusInterface $eventBus,
        private readonly Clock $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $due = $this->schedules->findDue($this->clock->now(), self::BATCH_SIZE);

        foreach ($due as $report) {
            $this->enqueue($report);
        }

        $output->writeln(sprintf('Enqueued %d due scheduled report(s).', count($due)));

        return Command::SUCCESS;
    }

    private function enqueue(ScheduledReport $report): void
    {
        $this->eventBus->dispatch(new ScheduledReportDue(
            $report->siteId()->getValue(),
            $report->id()->getValue(),
        ));
    }
}
