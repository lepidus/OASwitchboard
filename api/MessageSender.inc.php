<?php

use GuzzleHttp\Exception\ClientException;

import('plugins.generic.OASwitchboardForOJS.messages.P1Pio');

class MessageSender
{
    public static function sendMessage(P1Pio $message, string $authToken)
    {
        $client = Application::get()->getHttpClient();
        $response = $client->request(
            "POST",
            "https://sandboxapi.oaswitchboard.org/v2/message",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $authToken
                ],
                'json' => $message->getMessage()
            ]
        );
        return $response->getStatusCode();
    }
}
