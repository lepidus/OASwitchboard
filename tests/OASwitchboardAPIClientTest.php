<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\api\OASwitchboardAPIClient;
use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;
use APP\plugins\generic\OASwitchboard\tests\helpers\ClientInterfaceForTests;
use ArrayObject;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PKP\tests\PKPTestCase;

class OASwitchboardAPIClientTest extends PKPTestCase
{
    private function createP1PioMock()
    {
        $P1PioMock = $this->createMock(P1Pio::class);
        $P1PioMock->method('getContent')
            ->willReturn([]);
        return $P1PioMock;
    }

    private function createApiClientWithCapturedLogs($httpClient, ArrayObject $logMessages)
    {
        return new class ($httpClient, $logMessages) extends OASwitchboardAPIClient {
            private $logMessages;

            public function __construct($httpClient, ArrayObject $logMessages)
            {
                parent::__construct($httpClient);
                $this->logMessages = $logMessages;
            }

            protected function writeLog(string $message): void
            {
                $this->logMessages->append($message);
            }
        };
    }

    public function testSendMessageFailureWithServerError()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willThrowException(new ServerException(
                'Server error',
                new Request('POST', 'https://sandboxapi.example.org/v2/'),
                new Response(500)
            ));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            '##plugins.generic.OASwitchboard.serverError##'
        );
        $statusCode = $apiClient->sendMessage($this->createP1PioMock(), 'mock_token');
    }

    public function testSendMessageFailureWithClientError()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willThrowException(new ClientException(
                'Client error',
                new Request('POST', 'https://sandboxapi.example.org/v2/'),
                new Response(400)
            ));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            '##plugins.generic.OASwitchboard.postRequirements##'
        );
        $statusCode = $apiClient->sendMessage($this->createP1PioMock(), 'mock_token');
    }

    public function testGetAuthorizationSuccess()
    {
        $responseBody = json_encode(['token' => 'mock_token']);
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willReturn(new Response(200, [], $responseBody));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);
        $token = $apiClient->getAuthorization('test@example.com', 'password');

        $this->assertEquals('mock_token', $token);
    }

    public function testGetAuthorizationShouldDisableRedirectsAndSetTimeouts()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://api.oaswitchboard.org/v2/authorize',
                $this->callback(function ($options) {
                    return $options['allow_redirects'] === false &&
                        $options['connect_timeout'] === 5 &&
                        $options['timeout'] === 15;
                })
            )
            ->willReturn(new Response(200, [], json_encode(['token' => 'mock_token'])));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);

        $this->assertEquals('mock_token', $apiClient->getAuthorization('test@example.com', 'password'));
    }

    public function testSendMessageShouldDisableRedirectsAndSetTimeouts()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://api.oaswitchboard.org/v2/message',
                $this->callback(function ($options) {
                    return $options['allow_redirects'] === false &&
                        $options['connect_timeout'] === 5 &&
                        $options['timeout'] === 15;
                })
            )
            ->willReturn(new Response(200));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);

        $this->assertEquals(200, $apiClient->sendMessage($this->createP1PioMock(), 'mock_token'));
    }

    public function testSendMessageShouldNotFollowRedirectsOrResendCredentials()
    {
        $tokenMarker = 'audit-token-marker';
        $locationMarker = 'redirect-location-secret-marker';
        $bodyMarker = 'redirect-body-secret-marker';
        $logMessages = new ArrayObject();
        $requestHistory = [];
        $mockHandler = new MockHandler([
            new Response(
                302,
                ['Location' => 'https://untrusted.example.test/message?' . $locationMarker],
                $bodyMarker
            ),
            new Response(200),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(Middleware::history($requestHistory));

        $apiClient = $this->createApiClientWithCapturedLogs(
            new Client(['handler' => $handlerStack]),
            $logMessages
        );

        try {
            $apiClient->sendMessage($this->createP1PioMock(), $tokenMarker);
            $this->fail('Expected redirect response to fail');
        } catch (Exception $exception) {
            $this->assertStringNotContainsString($tokenMarker, $exception->getMessage());
            $this->assertStringNotContainsString($locationMarker, $exception->getMessage());
            $this->assertStringNotContainsString($bodyMarker, $exception->getMessage());
        }

        $this->assertCount(1, $requestHistory);
        $this->assertSame('https://api.oaswitchboard.org/v2/message', (string) $requestHistory[0]['request']->getUri());
        $this->assertSame('Bearer ' . $tokenMarker, $requestHistory[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame(1, count($mockHandler));
        $this->assertCount(1, $logMessages);
        $this->assertStringContainsString('operation=sendMessage', $logMessages[0]);
        $this->assertStringContainsString('endpoint=message', $logMessages[0]);
        $this->assertStringContainsString('status=302', $logMessages[0]);
        $this->assertStringNotContainsString($tokenMarker, $logMessages[0]);
        $this->assertStringNotContainsString($locationMarker, $logMessages[0]);
        $this->assertStringNotContainsString($bodyMarker, $logMessages[0]);
        $this->assertStringNotContainsString('untrusted.example.test', $logMessages[0]);
    }

    /**
     * @dataProvider successfulMessageStatusProvider
     */
    public function testSendMessageShouldAcceptAny2xxStatus(int $statusCode)
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willReturn(new Response($statusCode));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);

        $this->assertSame($statusCode, $apiClient->sendMessage($this->createP1PioMock(), 'mock_token'));
    }

    public static function successfulMessageStatusProvider(): array
    {
        return [
            'lower boundary' => [200],
            'created' => [201],
            'no content' => [204],
            'upper boundary' => [299],
        ];
    }

    /**
     * @dataProvider unsuccessfulMessageStatusProvider
     */
    public function testSendMessageShouldRejectAnyNon2xxStatus(int $statusCode)
    {
        $logMessages = new ArrayObject();
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willReturn(new Response($statusCode, ['Location' => 'https://untrusted.example.test/secret']));

        $apiClient = $this->createApiClientWithCapturedLogs($httpClientMock, $logMessages);

        try {
            $apiClient->sendMessage($this->createP1PioMock(), 'audit-token-marker');
            $this->fail('Expected non-2xx response to fail');
        } catch (Exception $exception) {
            $this->assertStringNotContainsString('audit-token-marker', $exception->getMessage());
            $this->assertStringNotContainsString('untrusted.example.test', $exception->getMessage());
        }

        $this->assertCount(1, $logMessages);
        $this->assertStringContainsString('status=' . $statusCode, $logMessages[0]);
        $this->assertStringNotContainsString('audit-token-marker', $logMessages[0]);
        $this->assertStringNotContainsString('untrusted.example.test', $logMessages[0]);
    }

    public static function unsuccessfulMessageStatusProvider(): array
    {
        return [
            'below success range' => [199],
            'redirect lower boundary' => [300],
            'redirect upper boundary' => [399],
            'client error' => [400],
            'server error' => [500],
        ];
    }

    /**
     * @dataProvider invalidAuthorizationResponseProvider
     */
    public function testGetAuthorizationShouldRejectInvalidResponses($responseBody)
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willReturn(new Response(200, [], $responseBody));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('##plugins.generic.OASwitchboard.serverError##');
        $apiClient->getAuthorization('test@example.com', 'password');
    }

    public static function invalidAuthorizationResponseProvider()
    {
        return [
            'invalid json' => ['not-json'],
            'missing token' => [json_encode([])],
            'non-string token' => [json_encode(['token' => 1])],
            'empty token' => [json_encode(['token' => ''])],
        ];
    }

    public function testAuthorizationResponseExactlyAtLimitShouldSucceed()
    {
        $prefix = '{"token":"';
        $suffix = '"}';
        $token = str_repeat('a', 16384 - strlen($prefix) - strlen($suffix));
        $responseBody = $prefix . $token . $suffix;
        $this->assertSame(16384, strlen($responseBody));

        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willReturn(new Response(200, [], $responseBody));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);

        $this->assertSame($token, $apiClient->getAuthorization('test@example.com', 'password'));
    }

    public function testAuthorizationResponseOneByteAboveLimitShouldFailWithoutLoggingBody()
    {
        $bodyMarker = 'audit-oversized-body-marker';
        $responseBody = str_pad($bodyMarker, 16385, 'x');
        $logMessages = new ArrayObject();
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willReturn(new Response(200, [], $responseBody));

        $apiClient = $this->createApiClientWithCapturedLogs($httpClientMock, $logMessages);

        try {
            $apiClient->getAuthorization('test@example.com', 'audit-password-marker');
            $this->fail('Expected oversized authorization response to fail');
        } catch (Exception $exception) {
            $this->assertStringNotContainsString($bodyMarker, $exception->getMessage());
        }

        $this->assertCount(1, $logMessages);
        $this->assertStringContainsString('status=200', $logMessages[0]);
        $this->assertStringNotContainsString($bodyMarker, $logMessages[0]);
        $this->assertStringNotContainsString('audit-password-marker', $logMessages[0]);
    }

    public function testAuthorizationResponseWithoutContentLengthShouldSucceed()
    {
        $responseBody = json_encode(['token' => 'mock_token']);
        $response = new Response(200, [], $responseBody);
        $this->assertFalse($response->hasHeader('Content-Length'));
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')->willReturn($response);

        $apiClient = new OASwitchboardAPIClient($httpClientMock);

        $this->assertSame('mock_token', $apiClient->getAuthorization('test@example.com', 'password'));
    }

    public function testInvalidAuthorizationResponseShouldNotLogBodyOrCredentials()
    {
        $bodyMarker = 'audit-invalid-body-marker';
        $passwordMarker = 'audit-password-marker';
        $logMessages = new ArrayObject();
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willReturn(new Response(200, [], json_encode(['unexpected' => $bodyMarker])));

        $apiClient = $this->createApiClientWithCapturedLogs($httpClientMock, $logMessages);

        try {
            $apiClient->getAuthorization('test@example.com', $passwordMarker);
            $this->fail('Expected invalid authorization response to fail');
        } catch (Exception $exception) {
            $this->assertStringNotContainsString($bodyMarker, $exception->getMessage());
            $this->assertStringNotContainsString($passwordMarker, $exception->getMessage());
        }

        $this->assertCount(1, $logMessages);
        $this->assertStringContainsString('operation=getAuthorization', $logMessages[0]);
        $this->assertStringContainsString('status=200', $logMessages[0]);
        $this->assertStringNotContainsString($bodyMarker, $logMessages[0]);
        $this->assertStringNotContainsString($passwordMarker, $logMessages[0]);
    }

    public function testRequestFailureShouldLogOnlySanitizedMetadata()
    {
        $passwordMarker = 'audit-password-marker';
        $tokenMarker = 'audit-token-marker';
        $responseMarker = 'audit-response-marker';
        $safeCorrelationId = '123e4567-e89b-42d3-a456-426614174000';
        $logMessages = new ArrayObject();
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willThrowException(new ServerException(
                'Server error ' . $responseMarker,
                new Request(
                    'POST',
                    'https://untrusted.example.test/authorize?password=' . $passwordMarker,
                    ['Authorization' => 'Bearer ' . $tokenMarker]
                ),
                new Response(
                    500,
                    [
                        'X-Correlation-ID' => $responseMarker,
                        'X-Request-ID' => $safeCorrelationId,
                    ],
                    $responseMarker
                )
            ));

        $apiClient = $this->createApiClientWithCapturedLogs($httpClientMock, $logMessages);

        try {
            $apiClient->getAuthorization('audit@example.test', $passwordMarker);
            $this->fail('Expected authorization request to fail');
        } catch (Exception $exception) {
            $this->assertStringNotContainsString($passwordMarker, $exception->getMessage());
            $this->assertStringNotContainsString($tokenMarker, $exception->getMessage());
            $this->assertStringNotContainsString($responseMarker, $exception->getMessage());
        }

        $this->assertCount(1, $logMessages);
        $logMessage = $logMessages[0];
        $this->assertStringContainsString('operation=getAuthorization', $logMessage);
        $this->assertStringContainsString('endpoint=authorize', $logMessage);
        $this->assertStringContainsString('status=500', $logMessage);
        $this->assertStringContainsString('correlationId=' . $safeCorrelationId, $logMessage);
        $this->assertStringNotContainsString($passwordMarker, $logMessage);
        $this->assertStringNotContainsString($tokenMarker, $logMessage);
        $this->assertStringNotContainsString($responseMarker, $logMessage);
        $this->assertStringNotContainsString('untrusted.example.test', $logMessage);
    }

    public function testConnectionFailureShouldNotExposeRequestUrl()
    {
        $secretMarker = 'audit-connection-secret-marker';
        $logMessages = new ArrayObject();
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willThrowException(new ConnectException(
                'Unable to connect to https://untrusted.example.test/authorize?password=' . $secretMarker,
                new Request('POST', 'https://untrusted.example.test/authorize?password=' . $secretMarker)
            ));

        $apiClient = $this->createApiClientWithCapturedLogs($httpClientMock, $logMessages);

        try {
            $apiClient->getAuthorization('audit@example.test', $secretMarker);
            $this->fail('Expected authorization request to fail');
        } catch (Exception $exception) {
            $this->assertStringNotContainsString($secretMarker, $exception->getMessage());
            $this->assertStringNotContainsString('untrusted.example.test', $exception->getMessage());
        }

        $this->assertCount(1, $logMessages);
        $this->assertStringContainsString('operation=getAuthorization', $logMessages[0]);
        $this->assertStringContainsString('endpoint=authorize', $logMessages[0]);
        $this->assertStringNotContainsString($secretMarker, $logMessages[0]);
        $this->assertStringNotContainsString('untrusted.example.test', $logMessages[0]);
    }

    public function testGetAuthorizationFailureWithServerError()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willThrowException(new ServerException(
                'Server error',
                new Request('POST', 'https://sandboxapi.example.org/v2/'),
                new Response(500)
            ));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            '##plugins.generic.OASwitchboard.serverError##'
        );
        $apiClient->getAuthorization('test@example.com', 'password');
    }

    public function testGetAuthorizationFailureWithClientError()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willThrowException(new ClientException(
                'Client error',
                new Request('POST', 'https://sandboxapi.example.org/v2/'),
                new Response(400)
            ));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            '##plugins.generic.OASwitchboard.postRequirements##'
        );
        $apiClient->getAuthorization('test@example.com', 'password');
    }
}
