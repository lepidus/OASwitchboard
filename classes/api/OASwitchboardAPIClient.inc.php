<?php

class OASwitchboardAPIClient
{
    private const API_BASE_URL = 'https://sandboxapi.oaswitchboard.org/v2/';
    private const API_AUTHORIZATION_ENDPOINT = 'authorize';
    private const API_SEND_MESSAGE_ENDPOINT = 'message';
    private $httpClient;

    public function __construct($httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function sendMessage(P1Pio $message, string $authToken): int
    {
        $response = $this->httpClient->request(
            'POST',
            self::API_BASE_URL . self::API_SEND_MESSAGE_ENDPOINT,
            [
                'headers' => ['Authorization' => 'Bearer ' . $authToken],
                'json' => $message->getContent(),
            ]
        );
        return $response->getStatusCode();
    }

    public function getAuthorizationToken(string $email, string $password): string
    {
        $response = $this->httpClient->request(
            'POST',
            self::API_BASE_URL . self::API_AUTHORIZATION_ENDPOINT,
            [
                'json' => [
                    'email' => $email,
                    'password' => $password
                ]
            ]
        );
        $responseBody = json_decode($response->getBody());
        return $responseBody->token;
    }

    public static function validateCredentials(string $email, string $password): bool
    {
        $httpClient = Application::get()->getHttpClient();
        $credentials = ['email' => $email, 'password' => $password];
        try {
            $response = $httpClient->request(
                'POST',
                self::API_BASE_URL . self::API_AUTHORIZATION_ENDPOINT,
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
