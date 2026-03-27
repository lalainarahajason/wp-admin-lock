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

		register_rest_route(
			'lebo-secu/v1',
			'/htaccess',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_htaccess' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_htaccess' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'save_full_htaccess' ),
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

		// Synchronisation active du .htaccess avec la nouvelle config globale
		$htaccess_config = $params['features']['htaccess'] ?? array();
		$manager = new LBS_HtaccessManager( $params );
		
		if ( ! empty( $htaccess_config['enabled'] ) && isset( $htaccess_config['rules'] ) ) {
			$manager->write( (string) $htaccess_config['rules'] );
		} else {
			$manager->remove();
		}

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
	/**
	 * Récupère le contenu actuel du .htaccess.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_htaccess( WP_REST_Request $request ): WP_REST_Response {
		$config  = LBS_Helpers::get_config();
		$manager = new LBS_HtaccessManager( $config );
		$content = $manager->read();

		if ( is_wp_error( $content ) ) {
			return new WP_REST_Response( array( 'error' => $content->get_error_message() ), 500 );
		}

		return new WP_REST_Response( array( 'content' => $content ), 200 );
	}

	/**
	 * Met à jour le bloc Lebo Secu dans le .htaccess.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function update_htaccess( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();
		if ( ! isset( $params['rules'] ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid data' ), 400 );
		}

		$config  = LBS_Helpers::get_config();
		$manager = new LBS_HtaccessManager( $config );

		$result = $manager->write( (string) $params['rules'] );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 500 );
		}

		// Update config
		$config['features']['htaccess']['rules'] = $params['rules'];
		update_option( 'lebosecu_config', $config, false );

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Écrit directement le contenu complet du .htaccess (éditeur libre).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function save_full_htaccess( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();
		if ( ! isset( $params['content'] ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid data' ), 400 );
		}

		$path = ABSPATH . '.htaccess';

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() ) {
			return new WP_REST_Response( array( 'error' => 'Impossible d\'initialiser WP_Filesystem.' ), 500 );
		}

		global $wp_filesystem;

		// Backup before overwrite
		$current = $wp_filesystem->get_contents( $path );
		if ( $current ) {
			update_option( 'lbs_htaccess_backup_' . time(), $current, false );
		}

		if ( ! $wp_filesystem->put_contents( $path, (string) $params['content'], FS_CHMOD_FILE ) ) {
			return new WP_REST_Response( array( 'error' => 'Impossible d\'écrire le fichier .htaccess.' ), 500 );
		}

		return new WP_REST_Response( array( 'success' => true, 'content' => (string) $params['content'] ), 200 );
	}
}
