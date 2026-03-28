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
			'/logs/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_csv_logs' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			'lebo-secu/v1',
			'/logs/ban',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'quick_ban_ip' ),
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

		$old_config = LBS_Helpers::get_config();
		update_option( 'lebosecu_config', $params, false );

		LBS_AuditLog::log( 
			LBS_AuditLog::EVENT_CONFIG_UPDATED, 
			LBS_AuditLog::SEVERITY_INFO, 
			array( 
				'source' => 'rest_api'
			) 
		);
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

		// On vérifie que la table existe.
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $table_name ) ) ) !== $table_name ) {
			return new WP_REST_Response( array( 'logs' => array() ), 200 );
		}

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ?: 20 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, u.user_login 
				 FROM {$table_name} l 
				 LEFT JOIN {$wpdb->users} u ON l.actor_id = u.ID 
				 ORDER BY l.created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return new WP_REST_Response( array( 'logs' => $results ?: array() ), 200 );
	}

	/**
	 * Exporte les logs en CSV.
	 *
	 * @param WP_REST_Request $request
	 */
	public function export_csv_logs( WP_REST_Request $request ): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'lebosecu_logs';

		$results = $wpdb->get_results(
			"SELECT l.*, u.user_login 
			 FROM {$table_name} l 
			 LEFT JOIN {$wpdb->users} u ON l.actor_id = u.ID 
			 ORDER BY l.created_at DESC LIMIT 1000",
			ARRAY_A
		);

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=lebo-secu-logs-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'ID', 'Date', 'Code', 'Severité', 'Utilisateur', 'IP', 'User Agent', 'URL', 'Détails' ) );

		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				fputcsv( $output, array(
					$row['id'],
					$row['created_at'],
					$row['event_code'],
					$row['severity'],
					$row['user_login'] ?: ( $row['actor_id'] ?: 'Anonyme' ),
					$row['actor_ip'],
					$row['user_agent'],
					$row['request_url'],
					$row['metadata'],
				) );
			}
		}
		fclose( $output );
		exit;
	}

	/**
	 * Bannit rapidement une IP.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function quick_ban_ip( WP_REST_Request $request ): WP_REST_Response {
		$ip = sanitize_text_field( $request->get_param( 'ip' ) );

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return new WP_REST_Response( array( 'message' => 'IP invalide' ), 400 );
		}

		// Utilisation du même mécanisme que LoginProtection (transient).
		$key      = 'lbs_lock_' . substr( md5( $ip ), 0, 16 );
		$duration = 86400 * 7; // Bannissement de 7 jours par défaut via Quick Ban.

		set_transient(
			$key,
			array(
				'ip'        => $ip,
				'locked_at' => time(),
				'username'  => 'manual_ban',
			),
			$duration
		);

		LBS_AuditLog::log( 
			LBS_AuditLog::EVENT_AUTH_LOCKOUT, 
			LBS_AuditLog::SEVERITY_CRITICAL, 
			array( 
				'ip'     => $ip, 
				'reason' => 'manual_quick_ban'
			) 
		);

		return new WP_REST_Response( array( 'message' => 'IP bannie pour 7 jours' ), 200 );
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
