<?php
/**
 * Tests unitaires — LBS_Config_Migrator
 *
 * @package LeboSecu
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName

/**
 * Class ConfigMigratorTest
 */
class ConfigMigratorTest extends WP_UnitTestCase {

	/**
	 * Config sans version → doit être migrée vers 1.0.0 et renommer rest → rest_api.
	 */
	public function test_config_without_version_is_migrated(): void {
		$old_config = array(
			'features' => array(
				'rest' => array( 'enabled' => true ),
			),
		);

		$migrated = LBS_Config_Migrator::migrate( $old_config );

		$this->assertEquals( '1.0.0', $migrated['version'] );
		$this->assertArrayHasKey( 'rest_api', $migrated['features'] );
		$this->assertArrayNotHasKey( 'rest', $migrated['features'] );
		$this->assertTrue( $migrated['features']['rest_api']['enabled'] );
	}

	/**
	 * Config déjà à jour → ne doit pas être modifiée.
	 */
	public function test_current_version_config_is_unchanged(): void {
		$config = array(
			'version'  => '1.0.0',
			'features' => array(
				'rest_api' => array( 'enabled' => false ),
			),
		);

		$migrated = LBS_Config_Migrator::migrate( $config );

		$this->assertSame( $config, $migrated );
	}

	/**
	 * Config sans clé 'rest' → la migration ne doit pas planter.
	 */
	public function test_migration_without_rest_key_does_not_crash(): void {
		$config = array(
			'features' => array(
				'hide_version' => array( 'enabled' => true ),
			),
		);

		$migrated = LBS_Config_Migrator::migrate( $config );

		$this->assertEquals( '1.0.0', $migrated['version'] );
		$this->assertArrayNotHasKey( 'rest_api', $migrated['features'] );
	}

	/**
	 * Config avec version ancienne mais sans clé 'rest' → doit être migrée sans erreur.
	 */
	public function test_migration_with_old_version_and_no_rest(): void {
		$config = array(
			'version'  => '0.9.0',
			'features' => array(
				'login_protection' => array( 'enabled' => true ),
			),
		);

		$migrated = LBS_Config_Migrator::migrate( $config );

		$this->assertEquals( '1.0.0', $migrated['version'] );
		$this->assertTrue( $migrated['features']['login_protection']['enabled'] );
	}
}
