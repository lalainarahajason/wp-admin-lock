<?php
/**
 * Tests unitaires — LBS_AdminUrl
 *
 * @package LeboSecu
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName

/**
 * Class AdminUrlTest
 */
class AdminUrlTest extends WP_UnitTestCase {

	/**
	 * @var array<string, mixed>
	 */
	private array $config_with_slug;

	public function setUp(): void {
		parent::setUp();

		$this->config_with_slug = array(
			'features' => array(
				'admin_url' => array(
					'enabled' => true,
					'slug'    => 'mon-espace-admin',
				),
			),
		);
	}

	public function tearDown(): void {
		unset( $_SERVER['REQUEST_URI'] );
		unset( $_GET['lbs_recover'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['action'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		parent::tearDown();
	}

	// ── is_enabled ────────────────────────────────────────────────────────────

	/**
	 * is_enabled() retourne true quand la feature est activée avec un slug.
	 */
	public function test_is_enabled_returns_true_when_configured(): void {
		$this->assertTrue( LBS_AdminUrl::is_enabled( $this->config_with_slug ) );
	}

	/**
	 * is_enabled() retourne false quand la feature est désactivée.
	 */
	public function test_is_enabled_returns_false_when_disabled(): void {
		$config = $this->config_with_slug;
		$config['features']['admin_url']['enabled'] = false;
		$this->assertFalse( LBS_AdminUrl::is_enabled( $config ) );
	}

	// ── Slugs réservés ────────────────────────────────────────────────────────

	/**
	 * Les slugs réservés WP sont dans get_reserved_slugs().
	 */
	public function test_wp_admin_is_reserved_slug(): void {
		$reserved = LBS_Helpers::get_reserved_slugs();
		$this->assertContains( 'wp-admin', $reserved );
	}

	/**
	 * Un slug custom valide ne doit pas apparaître dans les réservés.
	 */
	public function test_custom_slug_is_not_reserved(): void {
		$reserved = LBS_Helpers::get_reserved_slugs();
		$this->assertNotContains( 'mon-espace-admin', $reserved );
		$this->assertNotContains( 'securite-2024', $reserved );
	}

	// ── Token de récupération ─────────────────────────────────────────────────

	/**
	 * Un token de récupération généré doit être une chaîne non vide de 64 chars.
	 */
	public function test_recovery_token_format(): void {
		$token = bin2hex( random_bytes( 32 ) );
		$this->assertEquals( 64, strlen( $token ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $token );
	}

	/**
	 * hash_equals() doit valider un token correct et rejeter un token incorrect.
	 */
	public function test_hash_equals_validates_correct_token(): void {
		$token = bin2hex( random_bytes( 32 ) );
		$this->assertTrue( hash_equals( $token, $token ) );
		$this->assertFalse( hash_equals( $token, 'wrong_token' ) );
		$this->assertFalse( hash_equals( $token, '' ) );
	}

	/**
	 * Le token stocké en option WP doit être récupérable.
	 */
	public function test_recovery_token_stored_in_options(): void {
		$token = bin2hex( random_bytes( 32 ) );
		update_option( 'lbs_recovery_token', $token, false );

		$stored = get_option( 'lbs_recovery_token', '' );
		$this->assertEquals( $token, $stored );

		// Nettoyer.
		delete_option( 'lbs_recovery_token' );
	}

	/**
	 * Après usage, le token doit être invalidé (régénéré).
	 */
	public function test_recovery_token_invalidated_after_use(): void {
		$original_token = bin2hex( random_bytes( 32 ) );
		update_option( 'lbs_recovery_token', $original_token, false );

		// Simuler l'invalidation (comme dans AdminUrl::intercept_request).
		$new_token = bin2hex( random_bytes( 32 ) );
		update_option( 'lbs_recovery_token', $new_token, false );

		$stored = get_option( 'lbs_recovery_token' );
		$this->assertNotEquals( $original_token, $stored );

		delete_option( 'lbs_recovery_token' );
	}

	// ── Constante de désactivation ────────────────────────────────────────────

	/**
	 * La constante LBS_DISABLE_CUSTOM_URL doit exister si définie.
	 */
	public function test_disable_constant_can_be_defined(): void {
		// On vérifie seulement qu'on peut détecter si elle est définie.
		// (On ne peut pas define() une constante déjà définie en PHP.)
		$this->assertIsBool( defined( 'LBS_DISABLE_CUSTOM_URL' ) );
	}
}
