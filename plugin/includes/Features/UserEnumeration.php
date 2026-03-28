<?php
/**
 * Feature F7 — Blocage de l'énumération des utilisateurs.
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LBS_UserEnumeration
 */
class LBS_UserEnumeration implements LBS_Feature_Interface {

	/** @var array<string, mixed> */
	private array $config;

	public function __construct( array $config ) {
		$this->config = $config['features']['user_enumeration'] ?? array();
	}

	public static function is_enabled( array $config ): bool {
		return ! empty( $config['features']['user_enumeration']['enabled'] );
	}

	public function init(): void {
		add_action( 'template_redirect', array( $this, 'block_author_scan' ) );
		add_filter( 'rest_endpoints', array( $this, 'restrict_users_endpoint' ) );
	}

	/**
	 * Bloquer l'énumération via /?author=N → redirect 301 vers accueil.
	 *
	 * @return void
	 */
	public function block_author_scan(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$author = sanitize_text_field( wp_unslash( $_GET['author'] ?? '' ) );

		if ( '' !== $author ) {
			LBS_AuditLog::log( LBS_AuditLog::EVENT_ENUMERATION_BLOCKED, LBS_AuditLog::SEVERITY_WARNING, array( 'author' => $author ) );
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	/**
	 * Restreindre l'endpoint REST /wp/v2/users aux utilisateurs avec manage_options.
	 *
	 * @param array<string, mixed> $endpoints Endpoints REST enregistrés.
	 * @return array<string, mixed>
	 */
	public function restrict_users_endpoint( array $endpoints ): array {
		if ( isset( $endpoints['/wp/v2/users'] ) && is_array( $endpoints['/wp/v2/users'] ) ) {
			foreach ( $endpoints['/wp/v2/users'] as &$endpoint ) {
				if ( is_array( $endpoint ) ) {
					$endpoint['permission_callback'] = static fn() => current_user_can( 'manage_options' );
				}
			}
		}
		if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) && is_array( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
			foreach ( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] as &$endpoint ) {
				if ( is_array( $endpoint ) ) {
					$endpoint['permission_callback'] = static fn() => current_user_can( 'manage_options' );
				}
			}
		}
		return $endpoints;
	}
}
