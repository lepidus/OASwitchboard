<?php

/**
 * @file classes/migrations/OASwitchboardMigrations.inc.php
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

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use PKP\install\DowngradeNotSupportedException;

import('plugins.generic.OASwitchboard.classes.migrations.EncryptApiCredentialsMigration');
import('plugins.generic.OASwitchboard.classes.migrations.RemoveSandboxApiSettingMigration');

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
        throw new DowngradeNotSupportedException();
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
        $contextIds = Capsule::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->distinct()
            ->pluck('context_id')
            ->all();

        return array_map('intval', $contextIds);
    }

    protected function refreshSettingsCache(array $contextIds): void
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        foreach ($contextIds as $contextId) {
            $pluginSettingsDao->getPluginSettings($contextId, self::PLUGIN_NAME);
        }
    }
}
