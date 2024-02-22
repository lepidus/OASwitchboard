<?php

use Firebase\JWT\JWT;

class APIKeyEncryption
{
    private string $secret;

    public function __construct()
    {
        $this->secret = $this->getSecretFromConfig();
    }

    private function validateSecretConfigIsNotEmpty($secret): void
    {
        if ($secret === "") {
            throw new Exception("A secret must be set in the config file ('api_key_secret') so that keys can be encrypted and decrypted");
        }
    }

    private function getSecretFromConfig(): string
    {
        $secret = Config::getVar('security', 'api_key_secret');
        $this->validateSecretConfigIsNotEmpty($secret);
        return $secret;
    }

    public function encryptString(string $plainText): string
    {
        return JWT::encode($plainText, $this->secret, 'HS256');
    }

    public function decryptString(string $encryptedText): string
    {
        try {
            return JWT::decode($encryptedText, $this->secret, ['HS256']);
        } catch (Firebase\JWT\SignatureInvalidException $e) {
            throw new Exception(
                'The `api_key_secret` configuration is not the same as the one used to encrypt the key.',
                1
            );
        }
    }
}
