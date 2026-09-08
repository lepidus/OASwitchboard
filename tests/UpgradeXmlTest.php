<?php

import('lib.pkp.tests.PKPTestCase');

class UpgradeXmlTest extends PKPTestCase
{
    private function getMigrationClasses()
    {
        $upgradeXml = simplexml_load_file(__DIR__ . '/../upgrade.xml');
        $classes = [];

        foreach ($upgradeXml->migration as $migration) {
            $classes[] = (string) $migration['class'];
        }

        return $classes;
    }

    /**
     * The installer never autoloads the class attribute of a migration node. It
     * hands the attribute to import(), which replaces the dots with slashes and
     * requires the file from the installation root, and then instantiates the
     * segment after the last dot. A name that does not resolve raises a fatal
     * error import() cannot catch, which leaves the upgrade halfway through, so
     * matching the attribute against a string is not enough: the path has to
     * reach a file that really declares the class the installer will build.
     */
    public function testEveryMigrationShouldBeLoadableByTheInstaller()
    {
        $classes = $this->getMigrationClasses();
        $this->assertNotEmpty($classes, 'upgrade.xml declares no migration.');

        foreach ($classes as $class) {
            $file = BASE_SYS_DIR . '/' . str_replace('.', '/', $class) . '.inc.php';
            $this->assertFileExists($file, "The migration \"{$class}\" does not resolve to a file.");

            $shortClassName = substr($class, strrpos($class, '.') + 1);
            import($class);
            $this->assertTrue(
                class_exists($shortClassName),
                "The file of \"{$class}\" does not declare {$shortClassName}."
            );
        }
    }

    public function testDirectUpgradeShouldRunTheCredentialMigration()
    {
        $this->assertContains(
            'plugins.generic.OASwitchboard.classes.migrations.EncryptApiCredentialsMigration',
            $this->getMigrationClasses()
        );
    }

    public function testDirectUpgradeShouldRemoveTheSandboxSetting()
    {
        $this->assertContains(
            'plugins.generic.OASwitchboard.classes.migrations.RemoveSandboxApiSettingMigration',
            $this->getMigrationClasses()
        );
    }
}
