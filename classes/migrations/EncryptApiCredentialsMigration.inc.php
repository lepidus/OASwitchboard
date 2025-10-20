<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

import('plugins.generic.OASwitchboard.lib.APIKeyEncryption.APIKeyEncryption');

class EncryptApiCredentialsMigration extends Migration
{
    private const PLUGIN_NAME = 'oaswitchboardplugin';
    private const PASSWORD_SETTING = 'password';

    public function up(): void
    {
        $encrypter = new APIKeyEncryption();

        if (!$encrypter->secretConfigExists()) {
            error_log(
                'OASwitchboard Migration: Skipping password encryption - ' .
                'api_key_secret not configured in config.inc.php'
            );
            return;
        }

        $result = Capsule::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('setting_name', self::PASSWORD_SETTING)
            ->get();

        if ($result->isEmpty()) {
            error_log(
                'OASwitchboard Migration: No password settings found to encrypt'
            );
            return;
        }

        $encryptedCount = 0;
        $skippedCount = 0;

        foreach ($result as $row) {
            $row = get_object_vars($row);
            $settingValue = $row['setting_value'];

            if (empty($settingValue)) {
                $skippedCount++;
                continue;
            }

            if ($encrypter->textIsEncrypted($settingValue)) {
                $skippedCount++;
                error_log(
                    "OASwitchboard Migration: Password for context_id " .
                    "{$row['context_id']} is already encrypted - skipping"
                );
                continue;
            }

            try {
                $encryptedValue = $encrypter->encryptString($settingValue);

                Capsule::table('plugin_settings')
                    ->where('plugin_name', self::PLUGIN_NAME)
                    ->where('context_id', $row['context_id'])
                    ->where('setting_name', self::PASSWORD_SETTING)
                    ->update(
                        ['setting_value' => $encryptedValue]
                    );

                $encryptedCount++;
                error_log(
                    "OASwitchboard Migration: Encrypted password for " .
                    "context_id {$row['context_id']}"
                );
            } catch (Exception $e) {
                error_log(
                    "OASwitchboard Migration: Failed to encrypt password " .
                    "for context_id {$row['context_id']}: " . $e->getMessage()
                );
            }
        }

        error_log(
            "OASwitchboard Migration: Completed - Encrypted: " .
            "{$encryptedCount}, Skipped: {$skippedCount}"
        );
    }
}
