<?php

/**
 * @file classes/migrations/RemoveSandboxApiSettingMigration.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class RemoveSandboxApiSettingMigration
 *
 * @brief Drops the per journal sandbox API setting, which is now a
 *   development-only switch read from config.inc.php.
 */

namespace APP\plugins\generic\OASwitchboard\classes\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;

class RemoveSandboxApiSettingMigration extends Migration
{
    private const PLUGIN_NAME = 'oaswitchboardplugin';
    private const SANDBOX_SETTING = 'isSandBoxAPI';
    private const CREDENTIAL_SETTINGS = ['username', 'password'];

    /**
     * Journals left pointing at the sandbox would otherwise start using the
     * production API with credentials that were never meant for it, either
     * failing every send or publishing test messages for real. Their
     * credentials are cleared instead, so the plugin reports itself as not
     * configured until a journal manager enters production credentials.
     */
    public function up(): void
    {
        foreach ($this->getSandboxApiSettings() as $row) {
            $row = get_object_vars($row);
            if (!filter_var($row['setting_value'], FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $contextId = (int) $row['context_id'];
            $this->clearCredentials($contextId);
            $this->writeLog(
                "OASwitchboard Migration: Cleared the credentials for context_id {$contextId} "
                . 'because they were saved for the sandbox API, which journals can no longer select'
            );
        }

        $this->removeSandboxApiSetting();
    }

    /**
     * Reverting would put journals back on an endpoint the settings form can no
     * longer show, with credentials this migration has already cleared.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException('Downgrading the removal of the OA Switchboard sandbox setting is not supported.');
    }

    protected function getSandboxApiSettings()
    {
        return DB::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('setting_name', self::SANDBOX_SETTING)
            ->get();
    }

    protected function clearCredentials(int $contextId): void
    {
        DB::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('context_id', $contextId)
            ->whereIn('setting_name', self::CREDENTIAL_SETTINGS)
            ->delete();
    }

    protected function removeSandboxApiSetting(): void
    {
        DB::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('setting_name', self::SANDBOX_SETTING)
            ->delete();
    }

    protected function writeLog(string $message): void
    {
        error_log($message);
    }
}
