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
        return JWT::decode($encryptedPassword, $secret, ['HS256']);
    }
}
