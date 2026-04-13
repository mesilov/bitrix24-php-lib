<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationSettings\Services;

use Bitrix24\Lib\ApplicationSettings\Services\DefaultSettingsInstaller;
use Bitrix24\Lib\ApplicationSettings\UseCase\Create\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\Create\Handler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(DefaultSettingsInstaller::class)]
class DefaultSettingsInstallerTest extends TestCase
{
    private LoggerInterface $logger;

    #[\Override]
    protected function setUp(): void
    {
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    public function testCanCreateDefaultSettings(): void
    {
        $uuidV7 = Uuid::v7();
        $defaultSettings = [
            'app.name' => ['value' => 'Test App', 'required' => true],
            'app.language' => ['value' => 'ru', 'required' => false],
        ];
        $createHandler = $this->createMock(Handler::class);
        $defaultSettingsInstaller = new DefaultSettingsInstaller($createHandler, $this->logger);

        // Expect Create Handler to be called twice (once for each setting)
        $createHandler->expects($this->exactly(2))
            ->method('handle')
            ->with($this->callback(function (Command $command) use ($uuidV7): bool {
                // Verify command has correct application installation ID
                if ($command->applicationInstallationId->toRfc4122() !== $uuidV7->toRfc4122()) {
                    return false;
                }

                // Verify key and value match one of the settings
                if ($command->key === 'app.name') {
                    return $command->value === 'Test App' && $command->isRequired;
                }

                if ($command->key === 'app.language') {
                    return $command->value === 'ru' && false === $command->isRequired;
                }

                return false;
            }));

        $defaultSettingsInstaller->createDefaultSettings($uuidV7, $defaultSettings);
    }

    public function testLogsStartAndFinish(): void
    {
        $uuidV7 = Uuid::v7();
        $defaultSettings = [
            'test.key' => ['value' => 'test', 'required' => false],
        ];
        $handledCommands = [];
        $createHandler = new readonly class(
            static function (Command $command) use (&$handledCommands): void {
                $handledCommands[] = $command;
            }
        ) extends Handler {
            public function __construct(private \Closure $onHandle) {}

            #[\Override]
            public function handle(Command $command): void
            {
                ($this->onHandle)($command);
            }
        };
        $logger = $this->createMock(LoggerInterface::class);
        $defaultSettingsInstaller = new DefaultSettingsInstaller($createHandler, $logger);

        $logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use ($uuidV7): bool {
                if ('DefaultSettingsInstaller.createDefaultSettings.start' === $message) {
                    $this->assertEquals($uuidV7->toRfc4122(), $context['applicationInstallationId']);
                    $this->assertEquals(1, $context['settingsCount']);

                    return true;
                }

                if ('DefaultSettingsInstaller.createDefaultSettings.finish' === $message) {
                    $this->assertEquals($uuidV7->toRfc4122(), $context['applicationInstallationId']);

                    return true;
                }

                return false;
            });

        $logger->expects($this->once())
            ->method('debug')
            ->with('DefaultSettingsInstaller.settingProcessed', $this->arrayHasKey('key'));

        $defaultSettingsInstaller->createDefaultSettings($uuidV7, $defaultSettings);

        $this->assertCount(1, $handledCommands);
        $this->assertSame('test.key', $handledCommands[0]->key);
    }

    public function testCreatesGlobalSettings(): void
    {
        $uuidV7 = Uuid::v7();
        $defaultSettings = [
            'global.setting' => ['value' => 'value', 'required' => true],
        ];
        $createHandler = $this->createMock(Handler::class);
        $defaultSettingsInstaller = new DefaultSettingsInstaller($createHandler, $this->logger);

        // Verify that created commands are for global settings (no user/department ID)
        $createHandler->expects($this->once())
            ->method('handle')
            ->with($this->callback(fn(Command $command): bool => null === $command->b24UserId && null === $command->b24DepartmentId));

        $defaultSettingsInstaller->createDefaultSettings($uuidV7, $defaultSettings);
    }

    public function testHandlesEmptySettingsArray(): void
    {
        $uuidV7 = Uuid::v7();
        $defaultSettings = [];
        $createHandler = $this->createMock(Handler::class);
        $logger = $this->createMock(LoggerInterface::class);
        $defaultSettingsInstaller = new DefaultSettingsInstaller($createHandler, $logger);

        // Create Handler should not be called
        $createHandler->expects($this->never())
            ->method('handle');

        // But logging should still happen
        $logger->expects($this->exactly(2))
            ->method('info');

        $defaultSettingsInstaller->createDefaultSettings($uuidV7, $defaultSettings);
    }
}
