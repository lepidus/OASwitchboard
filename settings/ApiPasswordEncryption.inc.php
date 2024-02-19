<?php

use Firebase\JWT\JWT;

trait ApiPasswordEncryption
{
    public function encryptPassword($password)
    {
        return JWT::encode($password, Config::getVar('security', 'api_key_secret'), 'HS256');
    }
}
