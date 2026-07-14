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
        $encrypter = $this->createEncrypter();
        $secretConfigExists = $encrypter->secretConfigExists();

        foreach ($this->getPasswordSettings() as $row) {
            $row = get_object_vars($row);
            $settingValue = $row['setting_value'];
            $contextId = (int) $row['context_id'];

            if ($settingValue === null || $settingValue === '') {
                continue;
            }

            if (!$secretConfigExists) {
                $this->clearPassword($contextId);
                $this->writeLog(
                    "OASwitchboard Migration: Cleared password for context_id {$contextId} because api_key_secret is not configured"
                );
                continue;
            }

            try {
                if ($encrypter->textIsEncrypted($settingValue)) {
                    continue;
                }

                if (strpos($settingValue, 'base64:') === 0) {
                    throw new Exception('Invalid encrypted credential');
                }

                $settingValue = $this->extractSettingValue($settingValue);
                $encryptedValue = $encrypter->encryptString($settingValue);
                $this->updatePassword($contextId, $encryptedValue);
            } catch (Throwable $exception) {
                $this->clearPassword($contextId);
                $this->writeLog(
                    "OASwitchboard Migration: Cleared password for context_id {$contextId} because it could not be migrated"
                );
            }
        }
    }

    protected function createEncrypter()
    {
        return new APIKeyEncryption();
    }

    protected function getPasswordSettings()
    {
        return Capsule::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('setting_name', self::PASSWORD_SETTING)
            ->get();
    }

    protected function updatePassword(int $contextId, string $encryptedValue): void
    {
        Capsule::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('context_id', $contextId)
            ->where('setting_name', self::PASSWORD_SETTING)
            ->update(['setting_value' => $encryptedValue]);
    }

    protected function clearPassword(int $contextId): void
    {
        Capsule::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME)
            ->where('context_id', $contextId)
            ->where('setting_name', self::PASSWORD_SETTING)
            ->delete();
    }

    protected function writeLog(string $message): void
    {
        error_log($message);
    }

    protected function extractSettingValue($settingValue): string
    {
        $jwtParts = explode('.', $settingValue);
        if (count($jwtParts) !== 3) {
            return $settingValue;
        }

        $header = json_decode($this->decodeJwtSegment($jwtParts[0]), true);
        if (!is_array($header) || !isset($header['alg']) || !isset($header['typ'])) {
            return $settingValue;
        }

        $value = json_decode($this->decodeJwtSegment($jwtParts[1]), true);
        if (!is_string($value)) {
            throw new Exception('Invalid legacy credential');
        }

        return $value;
    }

    private function decodeJwtSegment(string $segment): string
    {
        $segment = strtr($segment, '-_', '+/');
        $padding = strlen($segment) % 4;
        if ($padding !== 0) {
            $segment .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($segment, true);
        if ($decoded === false) {
            throw new Exception('Invalid legacy credential');
        }

        return $decoded;
    }
}
