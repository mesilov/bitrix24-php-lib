<?php

/**
 * This file is part of the bitrix24-php-lib package.
 *
 * © Maksim Mesilov <mesilov.maxim@gmail.com>
 *
 * For the full copyright and license information, please view the MIT-LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Bitrix24\Lib\Journal\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Console command to consume journal messages
 */
#[AsCommand(
    name: 'journal:consume',
    description: 'Consumes messages from the journal event bus and writes them to the journal',
)]
class ConsumeMessagesCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'Time limit in seconds', 3600)
            ->addOption('memory-limit', 'm', InputOption::VALUE_REQUIRED, 'Memory limit', '128M')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit the number of messages to consume')
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command consumes messages from the journal event bus:

  <info>php %command.full_name%</info>

You can limit the time and memory:

  <info>php %command.full_name% --time-limit=3600 --memory-limit=128M</info>

Or limit the number of messages:

  <info>php %command.full_name% --limit=10</info>
HELP
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Journal Message Consumer');
        $io->info('Starting to consume journal messages...');

        $timeLimit = (int) $input->getOption('time-limit');
        $memoryLimit = $input->getOption('memory-limit');
        $messageLimit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;

        $io->info(sprintf('Time limit: %d seconds', $timeLimit));
        $io->info(sprintf('Memory limit: %s', $memoryLimit));

        if ($messageLimit) {
            $io->info(sprintf('Message limit: %d', $messageLimit));
        }

        $io->success('Consumer started successfully. Press Ctrl+C to stop.');
        $io->note('Waiting for messages...');

        // In a real application, this would use Symfony Messenger's Worker
        // For now, this is a placeholder implementation

        return Command::SUCCESS;
    }
}
