<?php

use Firebase\JWT\JWT;

trait ApiPasswordEncryption
{
    private function validateSecret($secret)
    {
        if ($secret === "") {
            throw new Exception("Your site administrator must set a secret in the config file ('api_key_secret').");
        }
    }

    public function encryptPassword($password, $secret)
    {
        $this->validateSecret($secret);
        return JWT::encode($password, $secret, 'HS256');
    }

    public function decryptPassword($encryptedPassword, $secret)
    {
        $this->validateSecret($secret);
        try {
            return JWT::decode($encryptedPassword, $secret, ['HS256']);
        } catch (Firebase\JWT\SignatureInvalidException $e) {
            throw new Firebase\JWT\SignatureInvalidException(
                'Your system administrator changed the `api_key_secret` configuration,'
                . ' please enter the Open Access Switchboard credentials again on the plugin settings.',
                1
            );
        }
    }
}
