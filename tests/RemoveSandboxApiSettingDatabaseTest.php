<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\migrations\RemoveSandboxApiSettingMigration;
use Illuminate\Support\Facades\DB;
use PKP\tests\DatabaseTestCase;

class RemoveSandboxApiSettingDatabaseTest extends DatabaseTestCase
{
    private const PLUGIN_NAME = 'oaswitchboardplugin';

    private $contextId;

    protected function getAffectedTables()
    {
        return ['plugin_settings'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->contextId = DB::table('journals')->value('journal_id');
        $this->assertNotNull($this->contextId, 'The database fixture must contain a journal');
        $this->saveSetting('username', 'oas-user@example.com', 'string');
        $this->saveSetting('password', 'encrypted-password', 'string');
    }

    private function saveSetting(string $settingName, string $settingValue, string $settingType): void
    {
        DB::table('plugin_settings')->updateOrInsert(
            [
                'plugin_name' => self::PLUGIN_NAME,
                'context_id' => $this->contextId,
                'setting_name' => $settingName,
            ],
            ['setting_value' => $settingValue, 'setting_type' => $settingType]
        );
    }

    private function getSettingValue(string $settingName)
    {
        return DB::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('context_id', $this->contextId)
            ->where('setting_name', $settingName)
            ->value('setting_value');
    }

    private function assertCredentialsWereCleared(): void
    {
        $this->assertNull($this->getSettingValue('username'));
        $this->assertNull($this->getSettingValue('password'));
    }

    private function assertCredentialsWereKept(): void
    {
        $this->assertSame('oas-user@example.com', $this->getSettingValue('username'));
        $this->assertSame('encrypted-password', $this->getSettingValue('password'));
    }

    private function runMigration(): void
    {
        $migration = new class () extends RemoveSandboxApiSettingMigration {
            protected function writeLog(string $message): void
            {
            }
        };
        $migration->up();
    }

    public function testShouldClearTheCredentialsOfAJournalUsingTheSandboxApi()
    {
        $this->saveSetting('isSandBoxAPI', '1', 'bool');

        $this->runMigration();

        $this->assertCredentialsWereCleared();
        $this->assertNull($this->getSettingValue('isSandBoxAPI'));
    }

    public function testShouldKeepTheCredentialsOfAJournalUsingTheProductionApi()
    {
        $this->saveSetting('isSandBoxAPI', '0', 'bool');

        $this->runMigration();

        $this->assertCredentialsWereKept();
        $this->assertNull($this->getSettingValue('isSandBoxAPI'));
    }

    public function testShouldKeepTheCredentialsWhenTheSettingWasNeverSaved()
    {
        $this->runMigration();

        $this->assertCredentialsWereKept();
    }

    public function testShouldBeSafeToRunTwice()
    {
        $this->saveSetting('isSandBoxAPI', '1', 'bool');

        $this->runMigration();
        $this->runMigration();

        $this->assertCredentialsWereCleared();
        $this->assertNull($this->getSettingValue('isSandBoxAPI'));
    }
}
