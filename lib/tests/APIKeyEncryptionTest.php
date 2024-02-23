<?php

import('lib.pkp.tests.PKPTestCase');
require_once(__DIR__ . '/../APIKeyEncryption.inc.php');

use Firebase\JWT\JWT;

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

    public function testEncryptStringWithSecretShouldNotCauseAnyProblems()
    {
        Config::setConfigFileName(dirname(__FILE__) . '/fixtures/config.testSecret.inc.php');
        $encryptedString = APIKeyEncryption::encryptString('MyString');
        $JWTResult = JWT::encode('MyString', Config::getVar('security', 'api_key_secret'), 'HS256');
        $this->assertEquals($encryptedString, $JWTResult);
    }

    public function testDecryptStringWithSecretShouldNotCauseAnyProblems()
    {
        Config::setConfigFileName(dirname(__FILE__) . '/fixtures/config.testSecret.inc.php');
        $encryptedString = APIKeyEncryption::encryptString('MyString');
        $decryptedString = APIKeyEncryption::decryptString($encryptedString);
        $JWTResult = JWT::decode($encryptedString, Config::getVar('security', 'api_key_secret'), ['HS256']);
        $this->assertEquals($decryptedString, $JWTResult);
        $this->assertEquals('MyString', $decryptedString);
    }

    public function testSecretConfigExistsWithoutASecertConfiguredShouldReturnFalse()
    {
        Config::setConfigFileName(dirname(__FILE__) . '/fixtures/config.testNoSecret.inc.php');
        $this->assertFalse(APIKeyEncryption::secretConfigExists());
    }

    public function testSecretConfigExistsWithASecertConfiguredShouldReturnTrue()
    {
        Config::setConfigFileName(dirname(__FILE__) . '/fixtures/config.testSecret.inc.php');
        $this->assertTrue(APIKeyEncryption::secretConfigExists());
    }

    public function testTryToEncryptStringWithoutASecertConfiguredShouldRaiseException()
    {
        Config::setConfigFileName(dirname(__FILE__) . '/fixtures/config.testNoSecret.inc.php');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "A secret must be set in the config file ('api_key_secret') so that keys can be encrypted and decrypted"
        );
        APIKeyEncryption::encryptString('MyString');
    }

    public function testTryToDecryptStringWithoutASecertConfiguredShouldRaiseException()
    {
        Config::setConfigFileName(dirname(__FILE__) . '/fixtures/config.testNoSecret.inc.php');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "A secret must be set in the config file ('api_key_secret') so that keys can be encrypted and decrypted"
        );
        APIKeyEncryption::decryptString('someEncryptedString');
    }

    public function testDecryptStringWithOtherSecretShouldRaiseException()
    {
        Config::setConfigFileName(dirname(__FILE__) . '/fixtures/config.testSecret.inc.php');
        $encryptedString = APIKeyEncryption::encryptString('MyString');
        Config::setConfigFileName(dirname(__FILE__) . '/fixtures/config.testOtherSecret.inc.php');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "The `api_key_secret` configuration is not the same as the one used to encrypt the key."
        );
        APIKeyEncryption::decryptString($encryptedString);
    }
}
