<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.OASwitchboard.classes.api.OASwitchboardAPIClient');
import('plugins.generic.OASwitchboard.tests.helpers.ClientInterfaceForTests');
import('plugins.generic.OASwitchboard.classes.messages.P1Pio');
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;

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
            ->willThrowException(new ServerException('Server error', new Request('POST', 'https://sandboxapi.example.org/v2/')));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "##plugins.generic.OASwitchboard.serverError##"
        );
        $statusCode = $apiClient->sendMessage($this->createP1PioMock(), 'mock_token');
    }

    public function testSendMessageFailureWithClientError()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willThrowException(new ClientException('Client error', new Request('POST', 'https://sandboxapi.example.org/v2/')));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "##plugins.generic.OASwitchboard.postRequirements##"
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

    public function testSendMessageShouldRejectRedirectWithoutFollowingItOrLoggingSensitiveData()
    {
        $requestHistory = [];
        $logMessages = new ArrayObject();
        $tokenMarker = 'audit-token-marker';
        $locationMarker = 'redirect-location-marker';
        $bodyMarker = 'redirect-body-marker';
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
        $this->assertStringContainsString('status=302', $logMessages[0]);
        $this->assertStringNotContainsString($tokenMarker, $logMessages[0]);
        $this->assertStringNotContainsString($locationMarker, $logMessages[0]);
        $this->assertStringNotContainsString($bodyMarker, $logMessages[0]);
        $this->assertStringNotContainsString('untrusted.example.test', $logMessages[0]);
    }

    /**
     * @dataProvider messageResponseStatusProvider
     */
    public function testSendMessageShouldAcceptOnlyTwoHundredStatusCodes($statusCode, $shouldSucceed)
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')->willReturn(new Response($statusCode));
        $apiClient = new OASwitchboardAPIClient($httpClientMock);

        if (!$shouldSucceed) {
            $this->expectException(Exception::class);
        }

        $result = $apiClient->sendMessage($this->createP1PioMock(), 'mock_token');
        $this->assertSame($statusCode, $result);
    }

    public static function messageResponseStatusProvider()
    {
        return [
            'lower success boundary' => [200, true],
            'upper success boundary' => [299, true],
            'informational response' => [199, false],
            'redirect lower boundary' => [300, false],
            'redirect upper boundary' => [399, false],
            'client error' => [400, false],
            'server error' => [500, false],
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
            'oversized response' => [str_repeat('a', 16385)],
        ];
    }

    public function testGetAuthorizationShouldAcceptResponseExactlyAtSizeLimitWithoutContentLength()
    {
        $token = str_repeat('t', 16372);
        $responseBody = json_encode(['token' => $token]);
        $this->assertSame(16384, strlen($responseBody));
        $response = new Response(200, [], $responseBody);
        $this->assertFalse($response->hasHeader('Content-Length'));
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')->willReturn($response);

        $apiClient = new OASwitchboardAPIClient($httpClientMock);

        $this->assertSame($token, $apiClient->getAuthorization('test@example.com', 'password'));
    }

    public function testGetAuthorizationShouldRejectResponseOneByteAboveLimitWithoutLoggingBodyOrToken()
    {
        $tokenMarker = str_repeat('t', 16373);
        $responseBody = json_encode(['token' => $tokenMarker]);
        $this->assertSame(16385, strlen($responseBody));
        $logMessages = new ArrayObject();
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')->willReturn(new Response(200, [], $responseBody));
        $apiClient = $this->createApiClientWithCapturedLogs($httpClientMock, $logMessages);

        try {
            $apiClient->getAuthorization('test@example.com', 'password');
            $this->fail('Expected oversized authorization response to fail');
        } catch (Exception $exception) {
            $this->assertStringNotContainsString($tokenMarker, $exception->getMessage());
        }

        $this->assertCount(1, $logMessages);
        $this->assertStringContainsString('operation=getAuthorization', $logMessages[0]);
        $this->assertStringContainsString('status=200', $logMessages[0]);
        $this->assertStringNotContainsString($tokenMarker, $logMessages[0]);
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
            ->willThrowException(new ServerException('Server error', new Request('POST', 'https://sandboxapi.example.org/v2/')));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "##plugins.generic.OASwitchboard.serverError##"
        );
        $apiClient->getAuthorization('test@example.com', 'password');
    }

    public function testGetAuthorizationFailureWithClientError()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willThrowException(new ClientException('Client error', new Request('POST', 'https://sandboxapi.example.org/v2/')));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "##plugins.generic.OASwitchboard.postRequirements##"
        );
        $apiClient->getAuthorization('test@example.com', 'password');
    }
}
