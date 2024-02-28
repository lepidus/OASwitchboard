<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.OASwitchboardForOJS.classes.api.OASwitchboardAPIClient');
import('plugins.generic.OASwitchboardForOJS.tests.helpers.ClientInterfaceForTests');
import('plugins.generic.OASwitchboardForOJS.classes.messages.P1Pio');
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ClientException;
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

    public function testSendMessageFailureWithServerError()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willThrowException(new ServerException('Server error', new Request('POST', 'https://sandboxapi.example.org/v2/')));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Server error when sending message. The OA Switchboard API server encountered an internal error."
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
            "Client error when sending message. Please check your request parameters and try again."
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

    public function testGetAuthorizationFailureWithServerError()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willThrowException(new ServerException('Server error', new Request('POST', 'https://sandboxapi.example.org/v2/')));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Server error when sending message. The OA Switchboard API server encountered an internal error."
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
            "Client error when sending message. Please check your request parameters and try again."
        );
        $apiClient->getAuthorization('test@example.com', 'password');
    }
}
