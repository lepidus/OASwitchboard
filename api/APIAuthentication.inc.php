<?php

use GuzzleHttp\Exception\ClientException;

class APIAuthentication
{
    private static function requestAuthentication(string $email, string $password)
    {
        $client = Application::get()->getHttpClient();
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
        return $response;
    }

    public static function getAuthenticationToken(string $email, string $password): string
    {
        $client = Application::get()->getHttpClient();
        $response = self::requestAuthentication($email, $password);
        $responseBody = json_decode($response->getBody());
        return $responseBody->token;
    }

    public static function validateCredentials(string $email, string $password): bool
    {
        try {
            $response = self::requestAuthentication($email, $password);
            return $response->getStatusCode() === 200;
        } catch (GuzzleHttp\Exception\ClientException $e) {
            return false;
        }
    }
}
