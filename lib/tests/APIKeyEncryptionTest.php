<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.OASwitchboardForOJS.lib.APIKeyEncryption');

class APIKeyEncryptionTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Config::setConfigFileName(Core::getBaseDir() . DIRECTORY_SEPARATOR . 'config.inc.php');
        parent::tearDown();
    }

    public function testInstantiateClassWithoutASecretConfiguredShouldRaiseException()
    {
        Config::setConfigFileName(dirname(__FILE__) . '/fixtures/config.testNoSecret.inc.php');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "A secret must be set in the config file ('api_key_secret') so that keys can be encrypted and decrypted"
        );
        $this->APIKeyEncryption = new APIKeyEncryption();
    }

    public function testDecryptStringWithOtherSecretShouldRaiseException()
    {
        Config::setConfigFileName(dirname(__FILE__) . '/fixtures/config.testSecret.inc.php');
        $this->APIKeyEncryption = new APIKeyEncryption();
        $encryptedString  = $this->APIKeyEncryption->encryptString('MyString');

        $this->APIKeyEncryption = null;

        Config::setConfigFileName(dirname(__FILE__) . '/fixtures/config.testOtherSecret.inc.php');

        $this->APIKeyEncryption = new APIKeyEncryption();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "The `api_key_secret` configuration is not the same as the one used to encrypt the key."
        );
        $this->APIKeyEncryption->decryptString($encryptedString);
    }
}
