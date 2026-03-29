<?php
/**
 * Feature F6 — Protection de la page de login (rate limiting).
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LBS_LoginProtection
 *
 * Rate limiting par IP via transients WP. Deux transients par IP pour éviter les race conditions.
 *
 * @see docs/features/F6.md pour la spec complète.
 */
class LBS_LoginProtection implements LBS_Feature_Interface {

	/** @var array<string, mixed> */
	private array $config;

	public function __construct( array $config ) {
		$this->config = $config['features']['login_protection'] ?? array();
	}

	public static function is_enabled( array $config ): bool {
		return ! empty( $config['features']['login_protection']['enabled'] );
	}

	public function init(): void {
		// Vérifier le verrou avant toute authentification.
		add_filter( 'authenticate', array( $this, 'check_ip_lockout' ), 1, 3 );
		// Enregistrer les échecs après tentative.
		add_action( 'wp_login_failed', array( $this, 'record_failed_attempt' ) );
	}

	/**
	 * Vérifie si l'IP est verrouillée. Retourne WP_Error si oui.
	 *
	 * @param WP_User|WP_Error|null $user     Utilisateur en cours d'authentification.
	 * @param string                $username Login saisi.
	 * @param string                $password Mot de passe saisi.
	 * @return WP_User|WP_Error|null
	 */
	public function check_ip_lockout( $user, string $username, string $password ) {
		$ip = $this->get_client_ip();

		if ( $this->is_whitelisted( $ip ) ) {
			return $user;
		}

		$lock_data = get_transient( $this->lock_key( $ip ) );

		if ( false !== $lock_data && is_array( $lock_data ) ) {
			$locked_at   = (int) ( $lock_data['locked_at'] ?? time() );
			$duration    = (int) ( $this->config['lockout_duration'] ?? 900 );
			$retry_after = max( 0, $locked_at + $duration - time() );

			if ( ! headers_sent() ) {
				header( 'Retry-After: ' . $retry_after );
			}

			return new WP_Error(
				'too_many_attempts',
				sprintf(
					/* translators: %d: minutes restantes */
					__( 'Trop de tentatives. Réessayez dans %d minutes.', 'lebo-secu' ),
					(int) ceil( $retry_after / 60 )
				)
			);
		}

		return $user;
	}

	/**
	 * Enregistrer un échec de connexion et poser le verrou si seuil atteint.
	 *
	 * @param string $username Login qui a échoué.
	 * @return void
	 */
	public function record_failed_attempt( string $username ): void {
		$ip = $this->get_client_ip();

		if ( $this->is_whitelisted( $ip ) ) {
			return;
		}

		$count = $this->increment_attempts( $ip );
		$max   = (int) ( $this->config['max_attempts'] ?? 5 );

		if ( $count >= $max ) {
			$duration = (int) ( $this->config['lockout_duration'] ?? 900 );
			set_transient(
				$this->lock_key( $ip ),
				array(
					'ip'        => $ip,
					'locked_at' => time(),
					'username'  => sanitize_user( $username ),
				),
				$duration
			);

			LBS_AuditLog::log(
				LBS_EventCodes::AUTH_LOCKOUT,
				LBS_EventCodes::SEVERITY_CRITICAL,
				array(
					'username'     => sanitize_user( $username ),
					'duration_sec' => $duration,
				)
			);

			if ( ! empty( $this->config['email_notify'] ) ) {
				$this->send_lockout_notification( $ip, $username );
			}
		}
	}

	/**
	 * Incrémenter le compteur de tentatives avec TTL glissant.
	 *
	 * @param string $ip Adresse IP.
	 * @return int Nouveau compteur.
	 */
	private function increment_attempts( string $ip ): int {
		$key      = $this->attempts_key( $ip );
		$count    = (int) get_transient( $key ) + 1;
		$duration = (int) ( $this->config['lockout_duration'] ?? 900 );
		set_transient( $key, $count, $duration );
		return $count;
	}

	/**
	 * Récupérer les IPs actuellement verrouillées (pour l'interface admin).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_locked_ips(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options}
				 WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_lbs_lock_' ) . '%'
			)
		);

		$locked = array();
		foreach ( $rows as $row ) {
			$data = maybe_unserialize( $row->option_value );
			if ( is_array( $data ) ) {
				$locked[] = $data;
			}
		}
		return $locked;
	}

	/**
	 * Débloquer manuellement une IP (depuis l'interface admin).
	 *
	 * @param string $ip Adresse IP à débloquer.
	 * @return void
	 */
	public function unlock_ip( string $ip ): void {
		delete_transient( $this->lock_key( $ip ) );
		delete_transient( $this->attempts_key( $ip ) );
	}

	/**
	 * Envoyer un email de notification de blocage.
	 *
	 * @param string $ip       IP bloquée.
	 * @param string $username Login tenté.
	 * @return void
	 */
	private function send_lockout_notification( string $ip, string $username ): void {
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_option( 'blogname' );
		$subject     = sprintf(
			/* translators: %s: nom du site */
			__( '[%s] Alerte sécurité — IP bloquée', 'lebo-secu' ),
			$site_name
		);
		$message = sprintf(
			/* translators: 1: IP, 2: login tenté */
			__( "L'IP %1\$s a été bloquée après trop de tentatives de connexion (login tenté : %2\$s).", 'lebo-secu' ),
			$ip,
			$username
		);
		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Récupérer l'IP cliente (REMOTE_ADDR uniquement en v1 — anti-spoofing).
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
	}

	/**
	 * Vérifier si une IP est dans la whitelist.
	 *
	 * @param string $ip Adresse IP.
	 * @return bool
	 */
	private function is_whitelisted( string $ip ): bool {
		$whitelist = $this->config['whitelist_ips'] ?? array();
		return in_array( $ip, $whitelist, true );
	}

	/**
	 * Clé de transient pour le compteur de tentatives.
	 *
	 * @param string $ip Adresse IP.
	 * @return string
	 */
	private function attempts_key( string $ip ): string {
		return 'lbs_attempts_' . substr( md5( $ip ), 0, 16 );
	}

	/**
	 * Clé de transient pour le verrou actif.
	 *
	 * @param string $ip Adresse IP.
	 * @return string
	 */
	private function lock_key( string $ip ): string {
		return 'lbs_lock_' . substr( md5( $ip ), 0, 16 );
	}
}
