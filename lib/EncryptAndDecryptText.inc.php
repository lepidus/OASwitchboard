<?php

use Firebase\JWT\JWT;

class EncryptAndDecryptText
{
    private $secret;

    public function __construct()
    {
        $this->secret = Config::getVar('security', 'api_key_secret');
        $this->validateSecret();
    }

    private function validateSecret()
    {
        if ($this->secret === "") {
            throw new Exception("Your site administrator must set a secret in the config file ('api_key_secret').");
        }
    }

    public function encryptText($text)
    {
        return JWT::encode($encryptText, $this->secret, 'HS256');
    }

    public function decryptText($encryptedText)
    {
        $this->validateSecret();
        try {
            return JWT::decode($encryptedText, $this->secret, ['HS256']);
        } catch (Firebase\JWT\SignatureInvalidException $e) {
            throw new Firebase\JWT\SignatureInvalidException(
                'Your system administrator changed the `api_key_secret` configuration,'
                . ' please enter the Open Access Switchboard credentials again on the plugin settings.',
                1
            );
        }
    }
}
