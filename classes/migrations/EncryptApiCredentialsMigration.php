<?php

namespace APP\plugins\generic\OASwitchboard\classes\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use APP\plugins\generic\OASwitchboard\classes\api\APIKeyEncryption;

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

        $result = DB::table('plugin_settings')
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
                $settingValue = $this->extractSettingValue($settingValue);
                $encryptedValue = $encrypter->encryptString($settingValue);

                DB::table('plugin_settings')
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
            } catch (\Exception $e) {
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

    private function extractSettingValue($settingValue)
    {
        $jwtParts = explode('.', $settingValue);
        if (count($jwtParts) == 3) {
            $header = json_decode(base64_decode($jwtParts[0]), true);
            if (!isset($header['alg']) || !isset($header['typ'])) {
                return $settingValue;
            }

            $payload = base64_decode($jwtParts[1]);
            return trim($payload, '"');
        }

        return $settingValue;
    }
}
