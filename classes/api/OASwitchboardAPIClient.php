<?php

namespace APP\plugins\generic\OASwitchboard\classes\api;

use APP\plugins\generic\OASwitchboard\classes\exceptions\ConnectionFailedException;
use APP\plugins\generic\OASwitchboard\classes\exceptions\OASwitchboardException;
use APP\plugins\generic\OASwitchboard\classes\exceptions\UnconfirmedDeliveryException;
use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use PKP\config\Config;

class OASwitchboardAPIClient
{
    private const API_AUTHORIZATION_ENDPOINT = 'authorize';
    private const API_SEND_MESSAGE_ENDPOINT = 'message';
    private const API_BASE_URL = 'https://api.oaswitchboard.org/v2/';
    private const API_SANDBOX_BASE_URL = 'https://sandboxapi.oaswitchboard.org/v2/';
    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const REQUEST_TIMEOUT_SECONDS = 15;
    private const MAX_AUTHORIZATION_RESPONSE_BYTES = 16384;
    private const CURL_COULDNT_RESOLVE_HOST = 6;
    private const CURL_COULDNT_CONNECT = 7;
    private const CURL_SSL_CONNECT_ERROR = 35;
    private $httpClient;
    private $apiBaseUrl;

    public function __construct($httpClient, ?bool $useSandboxApi = null)
    {
        $this->httpClient = $httpClient;
        $useSandboxApi ??= self::usesSandboxApi();
        $this->apiBaseUrl = $useSandboxApi ? self::API_SANDBOX_BASE_URL : self::API_BASE_URL;
    }

    /**
     * Development-only switch: the sandbox API is selected through
     * config.inc.php ([oaswitchboard] sandbox = On), never through the journal
     * settings form. Anything the configuration file does not read as enabled
     * keeps the production API, so a typo cannot send messages to the sandbox.
     */
    public static function usesSandboxApi(): bool
    {
        return filter_var(Config::getVar('oaswitchboard', 'sandbox', false), FILTER_VALIDATE_BOOLEAN);
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
        $options = [
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
            $this->assertSuccessfulResponse($endpoint, $response);
            return $response;
        } catch (ServerException $e) {
            $this->logRequestFailure($this->getOperationName($endpoint), $endpoint, $e);
            throw new OASwitchboardException(
                __('plugins.generic.OASwitchboard.serverError')
            );
        } catch (ClientException $e) {
            $this->logRequestFailure($this->getOperationName($endpoint), $endpoint, $e);
            throw new OASwitchboardException(
                __('plugins.generic.OASwitchboard.postRequirements')
            );
        } catch (TransferException $e) {
            $this->logRequestFailure($this->getOperationName($endpoint), $endpoint, $e);
            if ($e instanceof RequestException && $e->hasResponse()) {
                throw new OASwitchboardException(__('plugins.generic.OASwitchboard.serverError'));
            }
            if ($endpoint === self::API_SEND_MESSAGE_ENDPOINT && !$this->failedBeforeReachingTheServer($e)) {
                throw new UnconfirmedDeliveryException(__('plugins.generic.OASwitchboard.unconfirmedDelivery'));
            }
            throw new ConnectionFailedException(__('plugins.generic.OASwitchboard.connectionError'));
        }
    }

    private function failedBeforeReachingTheServer(TransferException $exception): bool
    {
        $handlerContext = method_exists($exception, 'getHandlerContext') ? $exception->getHandlerContext() : [];
        $unreachedServerErrors = [self::CURL_COULDNT_RESOLVE_HOST, self::CURL_COULDNT_CONNECT, self::CURL_SSL_CONNECT_ERROR];
        if (in_array($handlerContext['errno'] ?? null, $unreachedServerErrors, true)) {
            return true;
        }

        return isset($handlerContext['pretransfer_time']) && (float) $handlerContext['pretransfer_time'] === 0.0;
    }

    private function extractAuthorizationToken($response): string
    {
        try {
            $responseBody = $this->readAuthorizationResponse($response->getBody());
        } catch (\Throwable $exception) {
            $this->rejectInvalidAuthorizationResponse($response);
        }

        if (strlen($responseBody) > self::MAX_AUTHORIZATION_RESPONSE_BYTES) {
            $this->rejectInvalidAuthorizationResponse($response);
        }

        $responseData = json_decode($responseBody, true);
        if (
            json_last_error() !== JSON_ERROR_NONE ||
            !is_array($responseData) ||
            !isset($responseData['token']) ||
            !is_string($responseData['token']) ||
            $responseData['token'] === ''
        ) {
            $this->rejectInvalidAuthorizationResponse($response);
        }

        return $responseData['token'];
    }

    private function readAuthorizationResponse($stream): string
    {
        $responseBody = '';
        $readLimit = self::MAX_AUTHORIZATION_RESPONSE_BYTES + 1;

        while (!$stream->eof() && strlen($responseBody) < $readLimit) {
            $bytesToRead = $readLimit - strlen($responseBody);
            $chunk = $stream->read($bytesToRead);
            if ($chunk === '') {
                break;
            }
            $responseBody .= $chunk;
        }

        return $responseBody;
    }

    private function assertSuccessfulResponse(string $endpoint, $response): void
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 200 && $statusCode < 300) {
            return;
        }

        $this->logResponseFailure($this->getOperationName($endpoint), $endpoint, $response);
        $messageKey = $statusCode >= 400 && $statusCode < 500
            ? 'plugins.generic.OASwitchboard.postRequirements'
            : 'plugins.generic.OASwitchboard.serverError';
        throw new OASwitchboardException(__($messageKey));
    }

    private function rejectInvalidAuthorizationResponse($response): void
    {
        $this->logResponseFailure('getAuthorization', self::API_AUTHORIZATION_ENDPOINT, $response);
        throw new OASwitchboardException(__('plugins.generic.OASwitchboard.serverError'));
    }

    private function getOperationName(string $endpoint): string
    {
        return $endpoint === self::API_AUTHORIZATION_ENDPOINT ? 'getAuthorization' : 'sendMessage';
    }

    private function logRequestFailure(string $operation, string $endpoint, $exception): void
    {
        if (method_exists($exception, 'hasResponse') && $exception->hasResponse()) {
            $this->logResponseFailure($operation, $endpoint, $exception->getResponse());
            return;
        }

        $logContext = [
            'operation=' . $operation,
            'endpoint=' . $endpoint,
            'exception=' . (new \ReflectionClass($exception))->getShortName(),
            'error=' . $this->describeTransferError($exception),
        ];
        $this->writeLog('OASwitchboard API request failed: ' . implode(' ', $logContext));
    }

    private function describeTransferError($exception): string
    {
        $message = $exception->getMessage();
        if (method_exists($exception, 'getRequest')) {
            $message = str_replace((string) $exception->getRequest()->getUri(), '[request uri]', $message);
        }
        return $message;
    }

    private function logResponseFailure(string $operation, string $endpoint, $response): void
    {
        $logContext = [
            'operation=' . $operation,
            'endpoint=' . $endpoint,
            'status=' . $response->getStatusCode(),
        ];

        $correlationId = $this->getCorrelationId($response);
        if ($correlationId !== null) {
            $logContext[] = 'correlationId=' . $correlationId;
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
