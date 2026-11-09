<?php

/**
 * @file classes/migrations/OASwitchboardMigrations.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class OASwitchboardMigrations
 *
 * @brief Every migration the plugin needs, in the order they must run.
 *
 * The plugin gallery reads upgrade.xml, but the two command line paths do not:
 * lib/pkp/tools/installPluginVersion.php and a core OJS upgrade only run the
 * migration this class is returned from. Listing the migrations here is what
 * makes an installation reach the same state whichever path updated it. They
 * all correct existing data and are safe to run more than once, which is what
 * lets the gallery run them from both places.
 */

namespace APP\plugins\generic\OASwitchboard\classes\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\install\DowngradeNotSupportedException;

class OASwitchboardMigrations extends Migration
{
    private const PLUGIN_NAME = 'oaswitchboardplugin';

    public function up(): void
    {
        $contextIds = $this->getContextIdsWithSettings();

        foreach ($this->getMigrations() as $migration) {
            $migration->up();
        }

        $this->refreshSettingsCache($contextIds);
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException('Downgrading the OA Switchboard plugin is not supported.');
    }

    protected function getMigrations(): array
    {
        return [
            new EncryptApiCredentialsMigration(),
            new RemoveSandboxApiSettingMigration(),
        ];
    }

    protected function getContextIdsWithSettings(): array
    {
        return DB::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->distinct()
            ->pluck('context_id')
            ->map(fn ($contextId) => (int) $contextId)
            ->all();
    }

    protected function refreshSettingsCache(array $contextIds): void
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        foreach ($contextIds as $contextId) {
            $pluginSettingsDao->getPluginSettings($contextId, self::PLUGIN_NAME);
        }
    }
}
