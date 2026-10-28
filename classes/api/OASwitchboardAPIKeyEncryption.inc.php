<?php

use Illuminate\Encryption\Encrypter;

class OASwitchboardAPIKeyEncryption
{
    private const ENCRYPTION_CIPHER = 'AES-256-CBC';
    private const BASE64_PREFIX = 'base64:';

    public function secretConfigExists(): bool
    {
        try {
            $this->getSecretFromConfig();
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    private function getSecretFromConfig(): string
    {
        $secret = Config::getVar('security', 'api_key_secret');
        if (!is_string($secret) || $secret === '') {
            throw new Exception("OASwitchboard Plugin - A secret must be set in the config file ('api_key_secret') so that keys can be encrypted and decrypted");
        }

        return hash('sha256', $secret, true);
    }

    public function textIsEncrypted(string $text): bool
    {
        if (strpos($text, self::BASE64_PREFIX) !== 0) {
            return false;
        }

        try {
            $this->decryptString($text);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function encryptString(
        #[\SensitiveParameter]
        string $plainText
    ): string {
        $encrypter = new Encrypter($this->getSecretFromConfig(), self::ENCRYPTION_CIPHER);

        try {
            $encryptedString = $encrypter->encrypt($plainText);
        } catch (Exception $e) {
            throw new Exception('OASwitchboard Plugin - Failed to encrypt string');
        }

        return self::BASE64_PREFIX . base64_encode($encryptedString);
    }

    public function decryptString(string $encryptedText): string
    {
        $encrypter = new Encrypter($this->getSecretFromConfig(), self::ENCRYPTION_CIPHER);
        $payload = base64_decode(substr($encryptedText, strlen(self::BASE64_PREFIX)));

        try {
            return $encrypter->decrypt($payload);
        } catch (Exception $e) {
            throw new Exception('OASwitchboard Plugin - Failed to decrypt string');
        }
    }
}
