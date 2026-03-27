<?php
/**
 * Feature F1 — Custom Admin URL.
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LBS_AdminUrl
 *
 * Remplace l'URL d'accès /wp-admin et /wp-login.php par un slug personnalisé.
 * Retourne HTTP 404 (léger, sans rendu thème) sur l'ancienne URL.
 *
 * @see docs/features/F1.md pour la spec complète.
 */
class LBS_AdminUrl implements LBS_Feature_Interface {

	/** @var array<string, mixed> */
	private array $config;

	public function __construct( array $config ) {
		$this->config = $config['features']['admin_url'] ?? array();
	}

	public static function is_enabled( array $config ): bool {
		return ! empty( $config['features']['admin_url']['enabled'] );
	}

	public function init(): void {
		// Hook init priorité 1 — avant tout le reste.
		add_action( 'init', array( $this, 'intercept_request' ), 1 );

		// Réécrire les URLs générées par WordPress.
		add_filter( 'site_url', array( $this, 'rewrite_login_url' ), 10, 4 );
		add_filter( 'network_site_url', array( $this, 'rewrite_login_url' ), 10, 3 );
		add_filter( 'wp_redirect', array( $this, 'rewrite_redirect' ), 10, 2 );

		// Admin notice — afficher le token de récupération à l'activation.
		add_action( 'admin_notices', array( $this, 'maybe_show_recovery_token_notice' ) );
	}

	/**
	 * Intercepter les requêtes entrantes (spec F1.md — 7 étapes dans l'ordre).
	 *
	 * @return void
	 */
	public function intercept_request(): void {
		// 1. Cron, AJAX, autosave → laisser passer.
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) return;
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

		// 2. Constante de désactivation d'urgence → laisser passer.
		if ( defined( 'LBS_DISABLE_CUSTOM_URL' ) && LBS_DISABLE_CUSTOM_URL ) return;

		// 3. Token de récupération valide → laisser passer + invalider.
		$token_from_url = sanitize_text_field( wp_unslash( $_GET['lbs_recover'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$stored_token   = get_option( 'lbs_recovery_token', '' );

		if ( $stored_token && hash_equals( $stored_token, $token_from_url ) ) {
			update_option( 'lbs_recovery_token', bin2hex( random_bytes( 32 ) ), false );
			return;
		}

		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		$path        = (string) parse_url( $request_uri, PHP_URL_PATH );

		// 4. admin-ajax.php ou async-upload.php → laisser passer.
		if ( str_ends_with( $path, 'admin-ajax.php' ) || str_ends_with( $path, 'async-upload.php' ) ) return;

		$slug = sanitize_title( $this->config['slug'] ?? '' );

		// 6. Slug custom → intercepter et charger le moteur de connexion (wp-login.php).
		$path_without_slash = trim( $path, '/' );
		if ( $slug && ( $path_without_slash === $slug || str_starts_with( $path_without_slash, $slug . '/' ) ) ) {
			global $pagenow, $error, $interim_login, $action, $user_login;
			$pagenow = 'wp-login.php';
			@require_once ABSPATH . 'wp-login.php';
			exit;
		}

		// 7. Accès direct interdit à wp-login.php et wp-admin (si non connecté).
		// On ne bloque wp-admin que si l'utilisateur n'est pas connecté.
		if ( str_contains( $path, 'wp-login.php' ) ) {
			wp_die( '', '', array( 'response' => 404 ) );
		}

		// Si c'est wp-admin ET non connecté (et ce n'est pas admin-ajax)
		if ( str_contains( $path, 'wp-admin' ) && ! is_user_logged_in() ) {
			wp_die( '', '', array( 'response' => 404 ) );
		}
	}

	/**
	 * Réécrire les URLs wp-login.php générées par WordPress.
	 *
	 * @param string      $url     URL générée.
	 * @param string      $path    Chemin relatif.
	 * @param string      $scheme  Schéma (https, http, login).
	 * @param int|null    $blog_id Blog ID (multisite).
	 * @return string
	 */
	public function rewrite_login_url( string $url, string $path, ?string $scheme = null, ?int $blog_id = null ): string {
		$slug = sanitize_title( $this->config['slug'] ?? '' );
		if ( $slug && str_contains( $url, 'wp-login.php' ) ) {
			$url = str_replace( 'wp-login.php', $slug, $url );
		}
		return $url;
	}

	/**
	 * Réécrire les redirections vers wp-login.php.
	 *
	 * @param string $location URL cible de la redirection.
	 * @param int    $status   Code HTTP de la redirection.
	 * @return string
	 */
	public function rewrite_redirect( string $location, int $status ): string {
		$slug = sanitize_title( $this->config['slug'] ?? '' );
		if ( $slug && str_contains( $location, 'wp-login.php' ) ) {
			if ( str_starts_with( $location, 'wp-login.php' ) ) {
				$location = home_url( '/' . $slug . substr( $location, 12 ) );
			} else {
				$location = str_replace( 'wp-login.php', $slug, $location );
			}
		}
		return $location;
	}

	/**
	 * Afficher le notice avec le token de récupération si l'option existe.
	 *
	 * @return void
	 */
	public function maybe_show_recovery_token_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;
		if ( ! get_option( 'lbs_show_recovery_notice' ) ) return;

		$token = get_option( 'lbs_recovery_token', '' );
		delete_option( 'lbs_show_recovery_notice' );

		if ( ! $token ) return;
		?>
		<div class="notice notice-warning lbs-notice-token">
			<p>
				<strong><?php esc_html_e( 'Lebo Secu — Token de récupération d\'urgence :', 'lebo-secu' ); ?></strong><br>
				<?php esc_html_e( 'Copiez ce lien et conservez-le précieusement. Valide à usage unique.', 'lebo-secu' ); ?>
			</p>
			<p>
				<code id="lbs-recovery-url"><?php echo esc_url( home_url( '/wp-login.php?lbs_recover=' . $token ) ); ?></code>
				<button type="button" data-lbs-copy="lbs-recovery-url" class="button button-small">
					<?php esc_html_e( 'Copier', 'lebo-secu' ); ?>
				</button>
			</p>
		</div>
		<?php
	}
}
