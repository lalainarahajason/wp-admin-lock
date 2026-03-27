<?php
/**
 * API REST pour l'administration.
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gestion des endpoints de l'interface paramétrique React.
 */
class LBS_Admin_Api {

	/**
	 * Initialise les hooks REST.
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Enregistre les routes REST.
	 */
	public function register_routes(): void {
		register_rest_route(
			'lebo-secu/v1',
			'/config',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_config' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_config' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			'lebo-secu/v1',
			'/logs',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_logs' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Vérifie les permissions d'administration.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Récupère la config.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_config( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( LBS_Helpers::get_config(), 200 );
	}

	/**
	 * Met à jour la config.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function update_config( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid data' ), 400 );
		}

		update_option( 'lebosecu_config', $params, false );

		return new WP_REST_Response( LBS_Helpers::get_config(), 200 );
	}

	/**
	 * Récupère les logs d'audit.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_logs( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$table_name = $wpdb->prefix . 'lebosecu_logs';

		// On vérifie que la table existe
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $table_name ) ) ) !== $table_name ) {
			return new WP_REST_Response( array( 'logs' => array() ), 200 );
		}

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ?: 20 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return new WP_REST_Response( array( 'logs' => $results ?: array() ), 200 );
	}
}
