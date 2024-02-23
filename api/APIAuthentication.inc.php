<?php

use GuzzleHttp\Exception\ClientException;

class APIAuthentication
{
    public static function authenticate(string $email, string $password): bool
    {
        $client = Application::get()->getHttpClient();
        try {
            $response = $client->request(
                "POST",
                "https://sandboxapi.oaswitchboard.org/v2/authorize",
                [
                    'json' => [
                        'email' => $email,
                        'password' => $password
                    ]
                ]
            );
            return $response->getStatusCode() === 200;
        } catch (GuzzleHttp\Exception\ClientException $e) {
            return false;
        }
    }
}
