<?php
/**
 * Feature F4 — Protection de l'API REST WordPress.
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LBS_RestApiProtection
 */
class LBS_RestApiProtection implements LBS_Feature_Interface {

	/** @var array<string, mixed> */
	private array $config;

	public function __construct( array $config ) {
		$this->config = $config['features']['rest_api'] ?? array();
	}

	public static function is_enabled( array $config ): bool {
		return ! empty( $config['features']['rest_api']['enabled'] );
	}

	public function init(): void {
		add_filter( 'rest_authentication_errors', array( $this, 'restrict_rest_access' ) );
	}

	/**
	 * Bloquer les requêtes REST non authentifiées hors whitelist.
	 *
	 * @param WP_Error|true|null $result Résultat courant.
	 * @return WP_Error|true|null
	 */
	public function restrict_rest_access( $result ) {
		// Ne pas interférer si déjà en erreur ou si authentifié.
		if ( null !== $result ) {
			return $result;
		}

		if ( is_user_logged_in() ) {
			return $result;
		}

		// Vérifier whitelist IPs.
		$client_ip      = $this->get_client_ip();
		$whitelist_ips  = $this->config['whitelist_ips'] ?? array();

		if ( in_array( $client_ip, $whitelist_ips, true ) ) {
			return $result;
		}

		// Vérifier whitelist endpoints.
		$request_path         = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
		$whitelist_endpoints  = $this->config['whitelist_endpoints'] ?? array();

		foreach ( $whitelist_endpoints as $endpoint ) {
			if ( str_starts_with( $request_path, $endpoint ) ) {
				return $result;
			}
		}

		LBS_AuditLog::log( LBS_AuditLog::EVENT_REST_DENIED, LBS_AuditLog::SEVERITY_WARNING, array( 'endpoint' => $request_path ) );

		return new WP_Error(
			'rest_not_logged_in',
			__( 'API REST restreinte. Authentification requise.', 'lebo-secu' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Récupérer l'IP cliente (REMOTE_ADDR uniquement en v1).
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
	}
}
