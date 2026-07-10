<?php

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\TransferException;

class OASwitchboardAPIClient
{
    private const API_AUTHORIZATION_ENDPOINT = 'authorize';
    private const API_SEND_MESSAGE_ENDPOINT = 'message';
    private const API_BASE_URL = 'https://api.oaswitchboard.org/v2/';
    private const API_SANDBOX_BASE_URL = 'https://sandboxapi.oaswitchboard.org/v2/';
    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const REQUEST_TIMEOUT_SECONDS = 15;
    private const MAX_AUTHORIZATION_RESPONSE_BYTES = 16384;
    private $httpClient;
    private $apiBaseUrl;

    public function __construct($httpClient, bool $useSandboxApi = false)
    {
        $this->httpClient = $httpClient;
        $this->apiBaseUrl = $useSandboxApi ? self::API_SANDBOX_BASE_URL : self::API_BASE_URL;
    }

    public function sendMessage(P1Pio $message, string $authToken): int
    {
        $options = [
            'headers' => ['Authorization' => 'Bearer ' . $authToken],
            'json' => $message->getContent(),
        ];
        $response = $this->makeRequest('POST', self::API_SEND_MESSAGE_ENDPOINT, $options);
        return $response->getStatusCode();
    }

    public function getAuthorization(string $email, string $password): string
    {
        $options  = [
            'json' => [
                'email' => $email,
                'password' => $password
            ]
        ];
        $response = $this->makeRequest('POST', self::API_AUTHORIZATION_ENDPOINT, $options);
        return $this->extractAuthorizationToken($response);
    }

    private function makeRequest(string $method, string $endpoint, array $options)
    {
        $options['allow_redirects'] = false;
        $options['connect_timeout'] = self::CONNECT_TIMEOUT_SECONDS;
        $options['timeout'] = self::REQUEST_TIMEOUT_SECONDS;

        try {
            $response = $this->httpClient->request(
                $method,
                $this->apiBaseUrl . $endpoint,
                $options
            );
            return $response;
        } catch (ServerException $e) {
            $this->logRequestFailure($this->getOperationName($endpoint), $endpoint, $e);
            throw new Exception(
                __('plugins.generic.OASwitchboard.serverError')
            );
        } catch (ClientException $e) {
            $this->logRequestFailure($this->getOperationName($endpoint), $endpoint, $e);
            throw new Exception(
                __('plugins.generic.OASwitchboard.postRequirements')
            );
        } catch (TransferException $e) {
            $this->logRequestFailure($this->getOperationName($endpoint), $endpoint, $e);
            throw new Exception(
                __('plugins.generic.OASwitchboard.serverError')
            );
        }
    }

    private function extractAuthorizationToken($response): string
    {
        $responseBody = (string) $response->getBody();
        if (strlen($responseBody) > self::MAX_AUTHORIZATION_RESPONSE_BYTES) {
            throw new Exception(__('plugins.generic.OASwitchboard.serverError'));
        }

        $responseData = json_decode($responseBody, true);
        if (
            json_last_error() !== JSON_ERROR_NONE ||
            !is_array($responseData) ||
            !isset($responseData['token']) ||
            !is_string($responseData['token']) ||
            $responseData['token'] === ''
        ) {
            throw new Exception(__('plugins.generic.OASwitchboard.serverError'));
        }

        return $responseData['token'];
    }

    private function getOperationName(string $endpoint): string
    {
        return $endpoint === self::API_AUTHORIZATION_ENDPOINT ? 'getAuthorization' : 'sendMessage';
    }

    private function logRequestFailure(string $operation, string $endpoint, $exception): void
    {
        $logContext = [
            'operation=' . $operation,
            'endpoint=' . $endpoint,
        ];

        if (method_exists($exception, 'hasResponse') && $exception->hasResponse()) {
            $response = $exception->getResponse();
            $logContext[] = 'status=' . $response->getStatusCode();

            $correlationId = $this->getCorrelationId($response);
            if ($correlationId !== null) {
                $logContext[] = 'correlationId=' . $correlationId;
            }
        }

        $this->writeLog('OASwitchboard API request failed: ' . implode(' ', $logContext));
    }

    private function getCorrelationId($response): ?string
    {
        foreach (['X-Correlation-ID', 'X-Request-ID'] as $headerName) {
            $value = $response->getHeaderLine($headerName);
            if (
                preg_match(
                    '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i',
                    $value
                )
            ) {
                return $value;
            }
        }

        return null;
    }

    protected function writeLog(string $message): void
    {
        error_log($message);
    }
}
