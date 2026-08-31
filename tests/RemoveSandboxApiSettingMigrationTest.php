<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\migrations\RemoveSandboxApiSettingMigration;
use PKP\install\DowngradeNotSupportedException;
use PKP\tests\PKPTestCase;

class RemoveSandboxApiSettingMigrationTest extends PKPTestCase
{
    private function createMigration(array $rows)
    {
        return new class ($rows) extends RemoveSandboxApiSettingMigration {
            public $clearedContextIds = [];
            public $settingWasRemoved = false;
            public $logMessages = [];
            private $rows;

            public function __construct(array $rows)
            {
                $this->rows = $rows;
            }

            protected function getSandboxApiSettings()
            {
                return $this->rows;
            }

            protected function clearCredentials(int $contextId): void
            {
                $this->clearedContextIds[] = $contextId;
            }

            protected function removeSandboxApiSetting(): void
            {
                $this->settingWasRemoved = true;
            }

            protected function writeLog(string $message): void
            {
                $this->logMessages[] = $message;
            }
        };
    }

    private function createSettingRow($contextId, $settingValue)
    {
        return (object) ['context_id' => $contextId, 'setting_value' => $settingValue];
    }

    public function testShouldClearTheCredentialsOfJournalsUsingTheSandboxApi()
    {
        $migration = $this->createMigration([
            $this->createSettingRow(1, '1'),
            $this->createSettingRow(3, '1'),
        ]);

        $migration->up();

        $this->assertSame([1, 3], $migration->clearedContextIds);
        $this->assertCount(2, $migration->logMessages);
    }

    public function testShouldKeepTheCredentialsOfJournalsUsingTheProductionApi()
    {
        $migration = $this->createMigration([
            $this->createSettingRow(1, '0'),
            $this->createSettingRow(2, ''),
            $this->createSettingRow(3, null),
        ]);

        $migration->up();

        $this->assertSame([], $migration->clearedContextIds);
        $this->assertSame([], $migration->logMessages);
    }

    public function testShouldRemoveTheSettingEvenWhenNoJournalUsedTheSandboxApi()
    {
        $migration = $this->createMigration([]);

        $migration->up();

        $this->assertTrue($migration->settingWasRemoved);
    }

    public function testShouldRemoveTheSettingAfterClearingTheCredentials()
    {
        $migration = $this->createMigration([$this->createSettingRow(1, '1')]);

        $migration->up();

        $this->assertSame([1], $migration->clearedContextIds);
        $this->assertTrue($migration->settingWasRemoved);
    }

    public function testShouldRefuseToDowngrade()
    {
        $migration = $this->createMigration([]);

        $this->expectException(DowngradeNotSupportedException::class);
        $migration->down();
    }
}
