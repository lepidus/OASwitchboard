<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\migrations\EncryptApiCredentialsMigration;
use Exception;
use PKP\tests\PKPTestCase;

class EncryptApiCredentialsMigrationTest extends PKPTestCase
{
    private function createMigration(array $rows, $encrypter)
    {
        return new class ($rows, $encrypter) extends EncryptApiCredentialsMigration {
            public $updates = [];
            public $clearedContextIds = [];
            public $logMessages = [];
            private $rows;
            private $encrypter;

            public function __construct(array $rows, $encrypter)
            {
                $this->rows = $rows;
                $this->encrypter = $encrypter;
            }

            protected function createEncrypter()
            {
                return $this->encrypter;
            }

            protected function getPasswordSettings()
            {
                return $this->rows;
            }

            protected function updatePassword(int $contextId, string $encryptedValue): void
            {
                $this->updates[$contextId] = $encryptedValue;
            }

            protected function clearPassword(int $contextId): void
            {
                $this->clearedContextIds[] = $contextId;
            }

            protected function writeLog(string $message): void
            {
                $this->logMessages[] = $message;
            }
        };
    }

    private function createEncrypter(bool $secretConfigExists, array $failingValues = [])
    {
        return new class ($secretConfigExists, $failingValues) {
            private $secretConfigExists;
            private $failingValues;

            public function __construct(bool $secretConfigExists, array $failingValues)
            {
                $this->secretConfigExists = $secretConfigExists;
                $this->failingValues = $failingValues;
            }

            public function secretConfigExists(): bool
            {
                return $this->secretConfigExists;
            }

            public function textIsEncrypted(string $value): bool
            {
                return $value === 'already-encrypted';
            }

            public function encryptString(string $value): string
            {
                if (in_array($value, $this->failingValues, true)) {
                    throw new Exception('Encryption failed');
                }

                return 'encrypted:' . $value;
            }
        };
    }

    private function createLegacyJwt(string $value): string
    {
        $encode = function ($segment) {
            return rtrim(strtr(base64_encode($segment), '+/', '-_'), '=');
        };

        return implode('.', [
            $encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])),
            $encode(json_encode($value)),
            'signature',
        ]);
    }

    public function testShouldClearCredentialsWhenApiKeySecretIsMissing()
    {
        $legacyPassword = 'legacy-password-marker';
        $migration = $this->createMigration(
            [(object) ['context_id' => 1, 'setting_value' => $legacyPassword]],
            $this->createEncrypter(false)
        );

        $migration->up();

        $this->assertSame([], $migration->updates);
        $this->assertSame([1], $migration->clearedContextIds);
        $this->assertStringNotContainsString($legacyPassword, implode('', $migration->logMessages));
    }

    public function testShouldClearOnlyTheCredentialThatCannotBeMigrated()
    {
        $legacyPassword = 'legacy-password-marker';
        $invalidPassword = 'invalid-password-marker';
        $migration = $this->createMigration(
            [
                (object) ['context_id' => 1, 'setting_value' => $this->createLegacyJwt($legacyPassword)],
                (object) ['context_id' => 2, 'setting_value' => $invalidPassword],
            ],
            $this->createEncrypter(true, [$invalidPassword])
        );

        $migration->up();

        $this->assertSame([1 => 'encrypted:' . $legacyPassword], $migration->updates);
        $this->assertSame([2], $migration->clearedContextIds);
        $this->assertStringNotContainsString($invalidPassword, implode('', $migration->logMessages));
    }
}
