<?php
/**
 * Feature F10 — Audit Log.
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LBS_AuditLog
 *
 * Journalisation des événements de sécurité dans wp_lebosecu_logs.
 */
class LBS_AuditLog implements LBS_Feature_Interface {

	/** @var array<string, mixed> */
	private array $config;

	public function __construct( array $config ) {
		$this->config = $config['features']['audit_log'] ?? array();
	}

	public static function is_enabled( array $config ): bool {
		return ! empty( $config['features']['audit_log']['enabled'] );
	}

	public function init(): void {
		add_action( 'wp_login', array( $this, 'log_login_success' ), 10, 2 );
		add_action( 'wp_login_failed', array( $this, 'log_login_failed' ) );
		add_action( 'lbs_audit_log_cleanup', array( $this, 'cleanup_old_logs' ) );

		// Planifier le nettoyage quotidien si pas déjà planifié.
		if ( ! wp_next_scheduled( 'lbs_audit_log_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'lbs_audit_log_cleanup' );
		}
	}

	/**
	 * Journaliser un événement de sécurité.
	 *
	 * @param string                $event_type Type d'événement.
	 * @param int|null              $user_id    ID utilisateur WP.
	 * @param array<string, mixed>  $details    Données contextuelles.
	 * @return void
	 */
	public static function log( string $event_type, ?int $user_id, array $details = array() ): void {
		global $wpdb;

		$ip         = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'lebosecu_logs',
			array(
				'event_type' => sanitize_key( $event_type ),
				'user_id'    => $user_id,
				'ip_address' => $ip,
				'user_agent' => $user_agent,
				'details'    => wp_json_encode( $details ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Logger une connexion réussie.
	 *
	 * @param string  $user_login Login utilisateur.
	 * @param WP_User $user       Objet utilisateur WordPress.
	 * @return void
	 */
	public function log_login_success( string $user_login, WP_User $user ): void {
		self::log( 'login_success', $user->ID, array( 'username' => $user_login ) );
	}

	/**
	 * Logger un échec de connexion.
	 *
	 * @param string $username Login tenté.
	 * @return void
	 */
	public function log_login_failed( string $username ): void {
		self::log( 'login_fail', null, array( 'username' => sanitize_user( $username ) ) );
	}

	/**
	 * Supprimer les entrées de log plus anciennes que retention_days.
	 *
	 * @return void
	 */
	public function cleanup_old_logs(): void {
		global $wpdb;

		$retention = absint( $this->config['retention_days'] ?? 30 );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}lebosecu_logs WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
				$retention
			)
		);
	}
}
