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

	// Sévérités
	public const SEVERITY_INFO     = 'INFO';
	public const SEVERITY_NOTICE   = 'NOTICE';
	public const SEVERITY_WARNING  = 'WARNING';
	public const SEVERITY_CRITICAL = 'CRITICAL';

	// Codes d'événements
	public const EVENT_AUTH_LOGIN_SUCCESS       = 'AUTH_LOGIN_SUCCESS';
	public const EVENT_AUTH_LOGIN_FAILED        = 'AUTH_LOGIN_FAILED';
	public const EVENT_AUTH_RECOVERY_TOKEN_USED = 'AUTH_RECOVERY_TOKEN_USED';
	public const EVENT_AUTH_LOCKOUT             = 'AUTH_LOCKOUT';
	public const EVENT_ENUMERATION_BLOCKED      = 'SECURITY_ENUMERATION_BLOCKED';
	public const EVENT_REST_DENIED              = 'SECURITY_REST_DENIED';
	public const EVENT_CONFIG_UPDATED           = 'CONFIG_UPDATED';
	public const EVENT_CONFIG_IMPORTED          = 'CONFIG_IMPORTED';
	public const EVENT_CONFIG_EXPORTED          = 'CONFIG_EXPORTED';
	public const EVENT_HTACCESS_MODIFIED        = 'SYSTEM_HTACCESS_MODIFIED';


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
	 * @param string                $event_code Code de l'événement (ex: AUTH_LOGIN_FAILED).
	 * @param string                $severity   Niveau de sévérité (ex: INFO, CRITICAL).
	 * @param array<string, mixed>  $metadata   Données contextuelles.
	 * @param int|null              $actor_id   ID utilisateur (remplace get_current_user_id si besoin).
	 * @return void
	 */
	public static function log( string $event_code, string $severity = self::SEVERITY_INFO, array $metadata = array(), ?int $actor_id = null ): void {
		global $wpdb;

		$ip          = self::get_real_ip();
		$user_agent  = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
		$request_url = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user_id = $actor_id ?? get_current_user_id();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'lebosecu_logs',
			array(
				'event_code'  => sanitize_text_field( $event_code ),
				'severity'    => sanitize_text_field( $severity ),
				'actor_id'    => $user_id,
				'actor_ip'    => $ip,
				'user_agent'  => $user_agent,
				'request_url' => $request_url,
				'metadata'    => wp_json_encode( $metadata ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Récupérer la véritable adresse IP de l'utilisateur (gère proxy/Cloudflare).
	 *
	 * @return string
	 */
	private static function get_real_ip(): string {
		$headers = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );
		foreach ( $headers as $header ) {
			if ( array_key_exists( $header, $_SERVER ) ) {
				foreach ( explode( ',', $_SERVER[ $header ] ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return sanitize_text_field( $ip );
					}
				}
			}
		}
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' ) );
	}

	/**
	 * Logger une connexion réussie.
	 *
	 * @param string  $user_login Login utilisateur.
	 * @param WP_User $user       Objet utilisateur WordPress.
	 * @return void
	 */
	public function log_login_success( string $user_login, WP_User $user ): void {
		self::log( self::EVENT_AUTH_LOGIN_SUCCESS, self::SEVERITY_INFO, array( 'username' => $user_login ), $user->ID );
	}

	/**
	 * Logger un échec de connexion.
	 *
	 * @param string $username Login tenté.
	 * @return void
	 */
	public function log_login_failed( string $username ): void {
		self::log( self::EVENT_AUTH_LOGIN_FAILED, self::SEVERITY_WARNING, array( 'username' => sanitize_user( $username ) ) );
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
