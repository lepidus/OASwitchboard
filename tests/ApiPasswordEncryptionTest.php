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
        $encryptedPassword = $this->ApiPasswordEncryption->encryptPassword($password);

        $this->assertNotNull($encryptedPassword);
        $this->assertNotEquals($password, $encryptedPassword);
        $this->assertEquals($encryptedPassword, JWT::encode($password, Config::getVar('security', 'api_key_secret'), 'HS256'));
    }

    // TO DO: Should Not Encrypt Without api_key_secret Set On OJS
}
