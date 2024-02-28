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
        $statusCode = $apiClient->sendMessage(new P1Pio(), 'mock_token');
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
        $statusCode = $apiClient->sendMessage(new P1Pio(), 'mock_token');
    }

    public function testGetAuthorizationTokenSuccess()
    {
        $responseBody = json_encode(['token' => 'mock_token']);
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willReturn(new Response(200, [], $responseBody));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);
        $token = $apiClient->getAuthorizationToken('test@example.com', 'password');

        $this->assertEquals('mock_token', $token);
    }

    public function testGetAuthorizationTokenFailureWithServerError()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willThrowException(new ServerException('Server error', new Request('POST', 'https://sandboxapi.example.org/v2/')));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Server error when sending message. The OA Switchboard API server encountered an internal error."
        );
        $apiClient->getAuthorizationToken('test@example.com', 'password');
    }

    public function testGetAuthorizationTokenFailureWithClientError()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willThrowException(new ClientException('Client error', new Request('POST', 'https://sandboxapi.example.org/v2/')));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Client error when sending message. Please check your request parameters and try again."
        );
        $apiClient->getAuthorizationToken('test@example.com', 'password');
    }

    public function testValidateCredentialsValid()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willReturn(new Response(200));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);
        $result = $apiClient->validateCredentials('test@example.com', 'password');

        $this->assertTrue($result);
    }

    public function testValidateCredentialsInvalid()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willReturn(new Response(401));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);
        $result = $apiClient->validateCredentials('test@example.com', 'password');

        $this->assertFalse($result);
    }

    public function testValidateCredentialsFailureWithServerError()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willThrowException(new ServerException('Server error', new Request('POST', 'https://sandboxapi.example.org/v2/')));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Server error when sending message. The OA Switchboard API server encountered an internal error."
        );
        $apiClient = new OASwitchboardAPIClient($httpClientMock);
        $result = $apiClient->validateCredentials('test@example.com', 'password');
    }

    public function testValidateCredentialsFailureWithClientError()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willThrowException(new ClientException('Client error', new Request('POST', 'https://sandboxapi.example.org/v2/')));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Client error when sending message. Please check your request parameters and try again."
        );
        $apiClient = new OASwitchboardAPIClient($httpClientMock);
        $result = $apiClient->validateCredentials('test@example.com', 'password');
    }
}
