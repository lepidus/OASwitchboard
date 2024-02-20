<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.OASwitchboardForOJS.settings.ApiPasswordEncryption');

use Firebase\JWT\JWT;

class ApiPasswordEncryptionTest extends PKPTestCase
{
    protected function setUp(): void
    {
        $this->ApiPasswordEncryption = new class () {
            use ApiPasswordEncryption;
        };

        parent::setUp();
    }

    public function testShouldEncryptPassword()
    {
        $password = 'DummyPassword123';
        $secret = Config::getVar('security', 'api_key_secret');
        $encryptedPassword = $this->ApiPasswordEncryption->encryptPassword($password, $secret);

        $this->assertNotNull($encryptedPassword);
        $this->assertNotEquals($password, $encryptedPassword);
        $this->assertEquals($encryptedPassword, JWT::encode($password, $secret, 'HS256'));
    }

    public function testShouldNotEncryptPasswordWithoutApiKeySecret()
    {
        $this->expectException(Exception::class);
        $password = 'DummyPassword123';
        $this->ApiPasswordEncryption->encryptPassword($password, "");
    }

    public function testShouldDecryptPassword()
    {
        $password = 'DummyPassword123';
        $secret = Config::getVar('security', 'api_key_secret');
        $encryptedPassword = $this->ApiPasswordEncryption->encryptPassword($password, $secret);
        $decryptedPassword = $this->ApiPasswordEncryption->decryptPassword($encryptedPassword, $secret);

        $this->assertNotNull($decryptedPassword);
        $this->assertEquals($password, $decryptedPassword);
    }

    public function testShouldNotDecryptPasswordWithoutApiKeySecret()
    {
        $this->expectException(Exception::class);
        $password = 'DummyPassword123';
        $this->ApiPasswordEncryption->decryptPassword($password, "");
    }

    public function testShouldThrowExceptionOnDecryptionIfApiSecretIsDifferentFromEncryption(): Returntype
    {
        $password = 'DummyPassword123';
        $secret = Config::getVar('security', 'api_key_secret');
        $encryptedPassword = $this->ApiPasswordEncryption->encryptPassword($password, $secret);
        $secret = $secret . 'notTheSameString';

        $this->expectException(Firebase\JWT\SignatureInvalidException::class);
        $decryptedPassword = $this->ApiPasswordEncryption->decryptPassword($encryptedPassword, $secret);
    }
}
