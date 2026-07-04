<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Console;

use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * CLI command to list application settings.
 *
 * Usage examples:
 * - List all settings for portal:
 *   php bin/console app:settings:list <installation-id>
 *
 * - List personal settings for user:
 *   php bin/console app:settings:list <installation-id> --user-id=123
 *
 * - List departmental settings:
 *   php bin/console app:settings:list <installation-id> --department-id=456
 */
#[AsCommand(
    name: 'app:settings:list',
    description: 'List application settings for portal, user, or department'
)]
class ApplicationSettingsListCommand extends Command
{
    public function __construct(
        private readonly ApplicationSettingsItemRepositoryInterface $applicationSettingRepository
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                'installation-id',
                InputArgument::REQUIRED,
                'Application Installation UUID'
            )
            ->addOption(
                'user-id',
                'u',
                InputOption::VALUE_REQUIRED,
                'Bitrix24 User ID (for personal settings)'
            )
            ->addOption(
                'department-id',
                'd',
                InputOption::VALUE_REQUIRED,
                'Bitrix24 Department ID (for departmental settings)'
            )
            ->addOption(
                'global-only',
                'g',
                InputOption::VALUE_NONE,
                'Show only global settings'
            )
            ->setHelp(
                <<<'HELP'
The <info>app:settings:list</info> command displays application settings.

<comment>List all settings for application installation:</comment>
  <info>php bin/console app:settings:list 018c1234-5678-7abc-9def-123456789abc</info>

<comment>List global settings only:</comment>
  <info>php bin/console app:settings:list 018c1234-5678-7abc-9def-123456789abc --global-only</info>

<comment>List personal settings for specific user:</comment>
  <info>php bin/console app:settings:list 018c1234-5678-7abc-9def-123456789abc --user-id=123</info>

<comment>List departmental settings:</comment>
  <info>php bin/console app:settings:list 018c1234-5678-7abc-9def-123456789abc --department-id=456</info>
HELP
            )
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        /** @var string $installationIdString */
        $installationIdString = $input->getArgument('installation-id');

        try {
            $installationId = Uuid::fromString($installationIdString);
        } catch (\InvalidArgumentException) {
            $symfonyStyle->error('Invalid Installation ID format. Expected UUID.');

            return Command::FAILURE;
        }

        /** @var null|string $userIdInput */
        $userIdInput = $input->getOption('user-id');
        $userId = null !== $userIdInput ? (int) $userIdInput : null;

        /** @var null|string $departmentIdInput */
        $departmentIdInput = $input->getOption('department-id');
        $departmentId = null !== $departmentIdInput ? (int) $departmentIdInput : null;

        $globalOnly = $input->getOption('global-only');

        // Validate options
        if ($userId && $departmentId) {
            $symfonyStyle->error('Cannot specify both --user-id and --department-id');

            return Command::FAILURE;
        }

        if ($globalOnly && ($userId || $departmentId)) {
            $symfonyStyle->error('Cannot use --global-only with --user-id or --department-id');

            return Command::FAILURE;
        }

        // Fetch all settings and filter based on parameters
        $allSettings = $this->applicationSettingRepository->findAllForInstallation($installationId);

        if ($globalOnly || (null === $userId && null === $departmentId)) {
            $settings = array_filter($allSettings, fn ($setting): bool => $setting->isGlobal());
            $scope = 'Global';
        } elseif (null !== $userId) {
            $settings = array_filter($allSettings, fn ($setting): bool => $setting->isPersonal() && $setting->getB24UserId() === $userId);
            $scope = sprintf('Personal (User ID: %d)', $userId);
        } else {
            $settings = array_filter($allSettings, fn ($setting): bool => $setting->isDepartmental() && $setting->getB24DepartmentId() === $departmentId);
            $scope = sprintf('Departmental (Department ID: %d)', $departmentId);
        }

        // Display results
        $symfonyStyle->title(sprintf('Application Settings - %s', $scope));
        $symfonyStyle->text(sprintf('Installation ID: %s', $installationId->toRfc4122()));

        if ([] === $settings) {
            $symfonyStyle->warning('No settings found.');

            return Command::SUCCESS;
        }

        // Create table
        $table = new Table($output);
        $table->setHeaders(['Key', 'Value', 'Scope', 'Created', 'Updated']);

        foreach ($settings as $setting) {
            $settingScope = 'Global';
            if ($setting->isPersonal()) {
                $settingScope = sprintf('User #%d', $setting->getB24UserId());
            } elseif ($setting->isDepartmental()) {
                $settingScope = sprintf('Dept #%d', $setting->getB24DepartmentId());
            }

            $table->addRow([
                $setting->getKey(),
                $this->truncateValue($setting->getValue(), 50),
                $settingScope,
                $setting->getCreatedAt()->format('Y-m-d H:i:s'),
                $setting->getUpdatedAt()->format('Y-m-d H:i:s'),
            ]);
        }

        $table->render();

        $symfonyStyle->success(sprintf('Found %d setting(s)', count($settings)));

        return Command::SUCCESS;
    }

    /**
     * Truncate long values for table display.
     */
    private function truncateValue(string $value, int $maxLength): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength - 3).'...';
    }
}
