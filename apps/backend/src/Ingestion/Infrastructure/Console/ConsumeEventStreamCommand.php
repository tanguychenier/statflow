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

namespace App\Ingestion\Infrastructure\Console;

use App\Ingestion\Infrastructure\Redis\RedisStreamConsumer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Long-running batch-writer worker: drains the `events:raw` Redis Stream into
 * ClickHouse in batches until a time/message budget is hit (so a supervisor can
 * cycle it, mirroring `messenger:consume --limit/--time-limit`).
 */
#[AsCommand(
    name: 'statflow:ingestion:consume',
    description: 'Consume buffered events from Redis Streams and batch-write them to ClickHouse.',
)]
final class ConsumeEventStreamCommand extends Command
{
    public function __construct(
        private readonly RedisStreamConsumer $consumer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('consumer', null, InputOption::VALUE_REQUIRED, 'Consumer name within the group', gethostname() !== false ? gethostname() : 'worker')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Max events per ClickHouse INSERT', '1000')
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Seconds to run before exiting (0 = forever)', '0')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Stop after writing this many events (0 = no limit)', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rawConsumer = $input->getOption('consumer');
        $consumerName = is_string($rawConsumer) && $rawConsumer !== '' ? $rawConsumer : 'worker';
        $rawBatch = $input->getOption('batch-size');
        $batchSize = max(1, is_numeric($rawBatch) ? (int) $rawBatch : 1000);
        $rawTime = $input->getOption('time-limit');
        $timeLimit = max(0, is_numeric($rawTime) ? (int) $rawTime : 0);
        $rawLimit = $input->getOption('limit');
        $messageLimit = max(0, is_numeric($rawLimit) ? (int) $rawLimit : 0);

        $this->consumer->ensureGroup();

        $deadline = $timeLimit > 0 ? time() + $timeLimit : null;
        $written = 0;

        $io->writeln(sprintf('<info>Consuming events:raw as "%s"…</info>', $consumerName));

        do {
            $written += $this->consumer->consumeOnce($consumerName, $batchSize);

            if ($messageLimit > 0 && $written >= $messageLimit) {
                break;
            }
        } while ($deadline === null || time() < $deadline);

        $io->writeln(sprintf('<info>Wrote %d events.</info>', $written));

        return Command::SUCCESS;
    }
}
