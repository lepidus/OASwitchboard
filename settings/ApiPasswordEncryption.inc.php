<?php

use Firebase\JWT\JWT;

trait ApiPasswordEncryption
{
    public function encryptPassword($password, $secret)
    {
        if($secret === "") {
            throw new Exception("Your site administrator must set a secret in the config file ('api_key_secret').");
        }
        return JWT::encode($password, $secret, 'HS256');
    }
}
