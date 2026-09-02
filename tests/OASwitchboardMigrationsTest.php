<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.OASwitchboard.OASwitchboardPlugin');
import('plugins.generic.OASwitchboard.classes.migrations.OASwitchboardMigrations');

use Illuminate\Database\Migrations\Migration;
use PKP\install\DowngradeNotSupportedException;

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
            ['EncryptApiCredentialsMigration', 'RemoveSandboxApiSettingMigration'],
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
        // The plugin imports its migration relative to its own path, which is
        // only set once the plugin is registered.
        $plugin = new class () extends OASwitchboardPlugin {
            public function getPluginPath()
            {
                return 'plugins/generic/OASwitchboard';
            }
        };

        $this->assertInstanceOf(OASwitchboardMigrations::class, $plugin->getInstallMigration());
    }
}
