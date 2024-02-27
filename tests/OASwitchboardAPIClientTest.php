<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.OASwitchboardForOJS.classes.api.OASwitchboardAPIClient');
import('plugins.generic.OASwitchboardForOJS.tests.helpers.ClientInterfaceForTests');
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;

class OASwitchboardAPIClientTest extends PKPTestCase
{
    public function testSendMessageSuccess()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willReturn(new Response(200));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);
        $statusCode = $apiClient->sendMessage(new P1Pio(), 'mock_token');

        $this->assertEquals(200, $statusCode);
    }

    public function testSendMessageFailure()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willReturn(new Response(500));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);
        $statusCode = $apiClient->sendMessage(new P1Pio(), 'mock_token');

        $this->assertEquals(500, $statusCode);
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

    public function testGetAuthorizationTokenFailure()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willThrowException(new \Exception('Request failed'));

        $apiClient = new OASwitchboardAPIClient($httpClientMock);

        $this->expectException(\Exception::class);
        $apiClient->getAuthorizationToken('test@example.com', 'password');
    }

    public function testValidateCredentialsValid()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willReturn(new Response(200));

        $result = OASwitchboardAPIClient::validateCredentials('test@example.com', 'password', $httpClientMock);

        $this->assertTrue($result);
    }

    public function testValidateCredentialsInvalid()
    {
        $httpClientMock = $this->createMock(ClientInterfaceForTests::class);
        $httpClientMock->method('request')
            ->willReturn(new Response(401));

        $result = OASwitchboardAPIClient::validateCredentials('test@example.com', 'password', $httpClientMock);

        $this->assertFalse($result);
    }
}
