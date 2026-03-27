<?php
/**
 * Tests unitaires — LBS_LoginProtection
 *
 * @package LeboSecu
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName

/**
 * Class LoginProtectionTest
 */
class LoginProtectionTest extends WP_UnitTestCase {

	/**
	 * @var LBS_LoginProtection
	 */
	private LBS_LoginProtection $feature;

	/**
	 * @var array<string, mixed>
	 */
	private array $default_config;

	public function setUp(): void {
		parent::setUp();

		$this->default_config = array(
			'features' => array(
				'login_protection' => array(
					'enabled'          => true,
					'max_attempts'     => 3,
					'lockout_duration' => 60,
					'email_notify'     => false,
					'whitelist_ips'    => array( '127.0.0.1' ),
				),
			),
		);

		$this->feature = new LBS_LoginProtection( $this->default_config );

		// Nettoyer les transients entre les tests.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_lbs_%'" ); // phpcs:ignore
	}

	/**
	 * is_enabled() doit retourner true si la feature est activée.
	 */
	public function test_is_enabled_returns_true_when_configured(): void {
		$this->assertTrue( LBS_LoginProtection::is_enabled( $this->default_config ) );
	}

	/**
	 * is_enabled() doit retourner false si désactivé.
	 */
	public function test_is_enabled_returns_false_when_disabled(): void {
		$config = $this->default_config;
		$config['features']['login_protection']['enabled'] = false;
		$this->assertFalse( LBS_LoginProtection::is_enabled( $config ) );
	}

	/**
	 * unlock_ip() doit supprimer les deux transients liés à l'IP.
	 */
	public function test_unlock_ip_removes_transients(): void {
		// Simuler un verrou posé manuellement.
		$ip = '192.168.1.100';
		// Hash de l'IP (16 chars de md5).
		$hash = substr( md5( $ip ), 0, 16 );
		set_transient( 'lbs_lock_' . $hash, array( 'ip' => $ip, 'locked_at' => time() ), 60 );
		set_transient( 'lbs_attempts_' . $hash, 3, 60 );

		// Vérifier que les transients existent.
		$this->assertNotFalse( get_transient( 'lbs_lock_' . $hash ) );
		$this->assertNotFalse( get_transient( 'lbs_attempts_' . $hash ) );

		// Débloquer.
		$this->feature->unlock_ip( $ip );

		// Vérifier suppression.
		$this->assertFalse( get_transient( 'lbs_lock_' . $hash ) );
		$this->assertFalse( get_transient( 'lbs_attempts_' . $hash ) );
	}

	/**
	 * check_ip_lockout() ne doit pas bloquer une IP whitelistée même si verrouillée.
	 */
	public function test_whitelisted_ip_is_never_blocked(): void {
		$whitelisted_ip = '127.0.0.1';

		// Simuler l'environnement serveur.
		$_SERVER['REMOTE_ADDR'] = $whitelisted_ip;

		// Simuler un verrou sur cette IP.
		$hash = substr( md5( $whitelisted_ip ), 0, 16 );
		set_transient( 'lbs_lock_' . $hash, array( 'ip' => $whitelisted_ip, 'locked_at' => time() ), 60 );

		// check_ip_lockout doit laisser passer (retourner $user tel quel).
		$user   = new WP_User();
		$result = $this->feature->check_ip_lockout( $user, 'admin', 'password' );

		$this->assertSame( $user, $result );

		unset( $_SERVER['REMOTE_ADDR'] );
	}

	/**
	 * check_ip_lockout() doit bloquer une IP vérrouillée (non whitelistée).
	 */
	public function test_locked_ip_gets_wp_error(): void {
		$blocked_ip = '10.0.0.99';
		$_SERVER['REMOTE_ADDR'] = $blocked_ip;

		$hash = substr( md5( $blocked_ip ), 0, 16 );
		set_transient( 'lbs_lock_' . $hash, array( 'ip' => $blocked_ip, 'locked_at' => time() ), 60 );

		$result = $this->feature->check_ip_lockout( null, 'admin', 'password' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'too_many_attempts', $result->get_error_code() );

		unset( $_SERVER['REMOTE_ADDR'] );
	}

	/**
	 * get_locked_ips() doit retourner un tableau (vide ou pas).
	 */
	public function test_get_locked_ips_returns_array(): void {
		$result = $this->feature->get_locked_ips();
		$this->assertIsArray( $result );
	}
}
