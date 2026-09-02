<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\migrations\EncryptApiCredentialsMigration;
use APP\plugins\generic\OASwitchboard\classes\migrations\OASwitchboardMigrations;
use APP\plugins\generic\OASwitchboard\classes\migrations\RemoveSandboxApiSettingMigration;
use APP\plugins\generic\OASwitchboard\OASwitchboardPlugin;
use ArrayObject;
use Illuminate\Database\Migrations\Migration;
use PKP\install\DowngradeNotSupportedException;
use PKP\tests\PKPTestCase;

class OASwitchboardMigrationsTest extends PKPTestCase
{
    private function createMigrationSpy(string $name, ArrayObject $executionOrder): Migration
    {
        return new class ($name, $executionOrder) extends Migration {
            private $name;
            private $executionOrder;

            public function __construct(string $name, ArrayObject $executionOrder)
            {
                $this->name = $name;
                $this->executionOrder = $executionOrder;
            }

            public function up(): void
            {
                $this->executionOrder->append($this->name);
            }
        };
    }

    private function createMigrationWith(array $migrations): OASwitchboardMigrations
    {
        return new class ($migrations) extends OASwitchboardMigrations {
            private $migrations;

            public function __construct(array $migrations)
            {
                $this->migrations = $migrations;
            }

            protected function getMigrations(): array
            {
                return $this->migrations;
            }
        };
    }

    public function testShouldRunEveryMigrationInOrder()
    {
        $executionOrder = new ArrayObject();
        $migration = $this->createMigrationWith([
            $this->createMigrationSpy('first', $executionOrder),
            $this->createMigrationSpy('second', $executionOrder),
        ]);

        $migration->up();

        $this->assertSame(['first', 'second'], $executionOrder->getArrayCopy());
    }

    public function testShouldEncryptTheCredentialsBeforeRemovingTheSandboxSetting()
    {
        $migration = new class () extends OASwitchboardMigrations {
            public function listMigrations(): array
            {
                return array_map('get_class', $this->getMigrations());
            }
        };

        $this->assertSame(
            [EncryptApiCredentialsMigration::class, RemoveSandboxApiSettingMigration::class],
            $migration->listMigrations()
        );
    }

    public function testShouldRefuseToDowngrade()
    {
        $migration = new OASwitchboardMigrations();

        $this->expectException(DowngradeNotSupportedException::class);
        $migration->down();
    }

    public function testPluginShouldInstallEveryMigration()
    {
        $plugin = new OASwitchboardPlugin();

        $this->assertInstanceOf(OASwitchboardMigrations::class, $plugin->getInstallMigration());
    }
}
