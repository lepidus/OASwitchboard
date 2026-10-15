<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.OASwitchboard.classes.api.OASwitchboardAPIKeyEncryption');
import('plugins.generic.OASwitchboard.classes.migrations.EncryptApiCredentialsMigration');

class OASwitchboardAPIKeyEncryptionTest extends PKPTestCase
{
    private const SUBMODULE_CIPHERTEXT = 'base64:ZXlKcGRpSTZJbTV2YlRWc1RXaENkRE50Y25RdmVXUlNNV2hFTkdjOVBTSXNJblpoYkhWbElqb2lPVkJQVFZsdGRtbHZXa3RuVTNwcGFXZHpaR3cxYzFCeE1UUmFkMDgwTkhaNlRVaEtUekEwUldWc1NUMGlMQ0p0WVdNaU9pSXlaak14TWpZM056bGhNR1UwTldZNFpqZzVPRE14WlRZME5qUXhZMkkxWXpoak16TmlOVFZsTjJVME1tWm1OV00zTWpZNVltVmhNalExT0RBeU5EazRJbjA9';

    private $secretWasConfigured;
    private $originalSecret;

    protected function setUp(): void
    {
        parent::setUp();
        $configurationData = & Config::getData();
        $this->secretWasConfigured = isset($configurationData['security']) && array_key_exists('api_key_secret', $configurationData['security']);
        $this->originalSecret = $this->secretWasConfigured ? $configurationData['security']['api_key_secret'] : null;
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

    private function setSecret($secret)
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

        $this->assertFalse((new OASwitchboardAPIKeyEncryption())->secretConfigExists());
    }

    public function testSecretShouldBeMissingWhenTheConfigurationValueIsEmpty()
    {
        $this->setSecret('');

        $this->assertFalse((new OASwitchboardAPIKeyEncryption())->secretConfigExists());
    }

    public function testSecretShouldBeMissingWhenTheConfigurationValueIsNotText()
    {
        $this->setSecret(['not', 'text']);

        $this->assertFalse((new OASwitchboardAPIKeyEncryption())->secretConfigExists());
    }

    public function testEncryptingWithoutASecretShouldFailWithAnException()
    {
        $this->setSecret(null);

        $this->expectException(Exception::class);
        (new OASwitchboardAPIKeyEncryption())->encryptString('password');
    }

    public function testEncryptedTextShouldDecryptBackToThePlainText()
    {
        $this->setSecret('test-secret');
        $encryption = new OASwitchboardAPIKeyEncryption();

        $encryptedText = $encryption->encryptString('password');

        $this->assertTrue($encryption->secretConfigExists());
        $this->assertStringStartsWith('base64:', $encryptedText);
        $this->assertTrue($encryption->textIsEncrypted($encryptedText));
        $this->assertSame('password', $encryption->decryptString($encryptedText));
    }

    public function testPlainOrCorruptedTextShouldNotCountAsEncrypted()
    {
        $this->setSecret('test-secret');
        $encryption = new OASwitchboardAPIKeyEncryption();

        $this->assertFalse($encryption->textIsEncrypted('plain-password'));
        $this->assertFalse($encryption->textIsEncrypted('base64:' . base64_encode('corrupted')));
    }

    public function testPasswordsSavedByTheSubmoduleVersionShouldStillDecrypt()
    {
        $this->setSecret('compat-test-secret');
        $encryption = new OASwitchboardAPIKeyEncryption();

        $this->assertTrue($encryption->textIsEncrypted(self::SUBMODULE_CIPHERTEXT));
        $this->assertSame('legacy-password', $encryption->decryptString(self::SUBMODULE_CIPHERTEXT));
    }

    public function testDecryptingWithAnotherSecretShouldFail()
    {
        $this->setSecret('another-secret');

        $this->expectException(Exception::class);
        (new OASwitchboardAPIKeyEncryption())->decryptString(self::SUBMODULE_CIPHERTEXT);
    }

    public function testShouldCoexistWithAnotherPluginsAPIKeyEncryptionClass()
    {
        require_once dirname(__FILE__) . '/fixtures/ForeignAPIKeyEncryption.inc.php';
        $this->setSecret('test-secret');
        $encryption = new OASwitchboardAPIKeyEncryption();

        $this->assertTrue(class_exists('APIKeyEncryption', false));
        $this->assertSame('password', $encryption->decryptString($encryption->encryptString('password')));
    }

    public function testMigrationShouldUseThePluginOwnEncryptionClass()
    {
        $migration = new class () extends EncryptApiCredentialsMigration {
            public function exposedCreateEncrypter()
            {
                return $this->createEncrypter();
            }
        };

        $this->assertInstanceOf(OASwitchboardAPIKeyEncryption::class, $migration->exposedCreateEncrypter());
    }
}
