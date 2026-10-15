<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\api\APIKeyEncryption;
use PKP\config\Config;
use PKP\tests\PKPTestCase;

class APIKeyEncryptionTest extends PKPTestCase
{
    private bool $secretWasConfigured;
    private $originalSecret;

    protected function setUp(): void
    {
        parent::setUp();
        $configurationData = & Config::getData();
        $this->secretWasConfigured = array_key_exists('api_key_secret', $configurationData['security'] ?? []);
        $this->originalSecret = $configurationData['security']['api_key_secret'] ?? null;
    }

    protected function tearDown(): void
    {
        $configurationData = & Config::getData();
        unset($configurationData['security']['api_key_secret']);
        if ($this->secretWasConfigured) {
            $configurationData['security']['api_key_secret'] = $this->originalSecret;
        }
        parent::tearDown();
    }

    private function setSecret(?string $secret): void
    {
        $configurationData = & Config::getData();
        unset($configurationData['security']['api_key_secret']);
        if ($secret !== null) {
            $configurationData['security']['api_key_secret'] = $secret;
        }
    }

    public function testSecretShouldBeMissingWhenTheConfigurationLineIsAbsent()
    {
        $this->setSecret(null);

        $this->assertFalse((new APIKeyEncryption())->secretConfigExists());
    }

    public function testSecretShouldBeMissingWhenTheConfigurationValueIsEmpty()
    {
        $this->setSecret('');

        $this->assertFalse((new APIKeyEncryption())->secretConfigExists());
    }

    public function testEncryptedTextShouldDecryptBackToThePlainText()
    {
        $this->setSecret('test-secret');
        $encryption = new APIKeyEncryption();

        $encryptedText = $encryption->encryptString('password');

        $this->assertTrue($encryption->secretConfigExists());
        $this->assertTrue($encryption->textIsEncrypted($encryptedText));
        $this->assertSame('password', $encryption->decryptString($encryptedText));
    }
}
