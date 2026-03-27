<?php
/**
 * Tests unitaires — LBS_Helpers
 *
 * @package LeboSecu
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName

/**
 * Class HelpersTest
 */
class HelpersTest extends WP_UnitTestCase {

	// ── merge_with_defaults ───────────────────────────────────────────────────

	/**
	 * merge_with_defaults ne doit pas écraser une valeur existante.
	 */
	public function test_merge_preserves_existing_values(): void {
		$saved    = array( 'features' => array( 'hide_version' => array( 'enabled' => false ) ) );
		$defaults = array( 'features' => array( 'hide_version' => array( 'enabled' => true ) ) );

		$merged = LBS_Helpers::merge_with_defaults( $saved, $defaults );

		$this->assertFalse( $merged['features']['hide_version']['enabled'] );
	}

	/**
	 * merge_with_defaults doit ajouter les clés absentes (fusion récursive).
	 */
	public function test_merge_adds_missing_keys(): void {
		$saved = array(
			'features' => array(
				'hide_version' => array( 'enabled' => true ),
			),
		);
		$defaults = array(
			'features' => array(
				'hide_version' => array(
					'enabled'    => true,
					'new_option' => 'default_value',
				),
			),
		);

		$merged = LBS_Helpers::merge_with_defaults( $saved, $defaults );

		$this->assertEquals( 'default_value', $merged['features']['hide_version']['new_option'] );
		$this->assertTrue( $merged['features']['hide_version']['enabled'] );
	}

	/**
	 * merge_with_defaults doit ajouter une feature absente complète.
	 */
	public function test_merge_adds_missing_feature(): void {
		$saved = array(
			'version'  => '1.0.0',
			'features' => array(),
		);
		$defaults = LBS_Helpers::get_default_config();

		$merged = LBS_Helpers::merge_with_defaults( $saved, $defaults );

		$this->assertArrayHasKey( 'hide_version', $merged['features'] );
		$this->assertArrayHasKey( 'login_protection', $merged['features'] );
	}

	/**
	 * merge_with_defaults ne doit pas écraser les sous-tableaux existants.
	 */
	public function test_merge_preserves_nested_arrays(): void {
		$saved = array(
			'features' => array(
				'login_protection' => array(
					'enabled'       => true,
					'whitelist_ips' => array( '127.0.0.1' ),
				),
			),
		);
		$defaults = array(
			'features' => array(
				'login_protection' => array(
					'enabled'       => true,
					'max_attempts'  => 5,
					'whitelist_ips' => array(),
				),
			),
		);

		$merged = LBS_Helpers::merge_with_defaults( $saved, $defaults );

		// L'IP whitelistée doit être préservée.
		$this->assertContains( '127.0.0.1', $merged['features']['login_protection']['whitelist_ips'] );
		// La clé absente doit être ajoutée.
		$this->assertEquals( 5, $merged['features']['login_protection']['max_attempts'] );
	}

	// ── get_reserved_slugs ────────────────────────────────────────────────────

	/**
	 * Les slugs réservés WordPress doivent figurer dans la liste.
	 */
	public function test_reserved_slugs_contains_wp_admin(): void {
		$slugs = LBS_Helpers::get_reserved_slugs();

		$this->assertContains( 'wp-admin', $slugs );
		$this->assertContains( 'wp-login.php', $slugs );
		$this->assertContains( 'wp-json', $slugs );
		$this->assertContains( 'admin-ajax.php', $slugs );
	}

	/**
	 * La liste ne doit pas être vide.
	 */
	public function test_reserved_slugs_is_not_empty(): void {
		$this->assertNotEmpty( LBS_Helpers::get_reserved_slugs() );
	}

	// ── get_default_config ────────────────────────────────────────────────────

	/**
	 * La config par défaut doit contenir toutes les features attendues.
	 */
	public function test_default_config_has_all_features(): void {
		$config   = LBS_Helpers::get_default_config();
		$features = $config['features'];

		$expected = array(
			'admin_url',
			'hide_version',
			'htaccess',
			'rest_api',
			'login_protection',
			'user_enumeration',
			'security_headers',
			'disable_features',
			'audit_log',
		);

		foreach ( $expected as $feature ) {
			$this->assertArrayHasKey( $feature, $features, "Feature manquante : $feature" );
		}
	}

	/**
	 * La config par défaut doit inclure la version du schéma.
	 */
	public function test_default_config_has_version(): void {
		$config = LBS_Helpers::get_default_config();
		$this->assertEquals( LBS_Helpers::CONFIG_SCHEMA_VERSION, $config['version'] );
	}

	// ── get_feature_classes ───────────────────────────────────────────────────

	/**
	 * Toutes les classes Feature retournées doivent exister.
	 */
	public function test_feature_classes_all_exist(): void {
		foreach ( LBS_Helpers::get_feature_classes() as $class ) {
			$this->assertTrue( class_exists( $class ), "Classe inexistante : $class" );
		}
	}

	/**
	 * Toutes les classes Feature doivent implémenter LBS_Feature_Interface.
	 */
	public function test_feature_classes_implement_interface(): void {
		foreach ( LBS_Helpers::get_feature_classes() as $class ) {
			$this->assertContains(
				'LBS_Feature_Interface',
				class_implements( $class ),
				"$class n'implémente pas LBS_Feature_Interface"
			);
		}
	}
}
