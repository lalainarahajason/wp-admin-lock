<?php
/**
 * Page d'administration principale Lebo Secu.
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LBS_Admin_Page
 *
 * Enregistre le menu d'administration WordPress et gère le rendu des onglets.
 */
class LBS_Admin_Page {

	/**
	 * @var array<string, mixed> Configuration complète.
	 */
	private array $config;

	/**
	 * @param array<string, mixed> $config Configuration complète.
	 */
	public function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * Enregistrer les hooks admin.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enregistrer le menu principal dans le dashboard WordPress.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			esc_html__( 'Lebo Secu', 'lebo-secu' ),
			esc_html__( 'Lebo Secu', 'lebo-secu' ),
			'manage_options',
			'lebo-secu',
			array( $this, 'render_dashboard' ),
			'dashicons-shield',
			80
		);

		add_submenu_page(
			'lebo-secu',
			esc_html__( 'Tableau de bord', 'lebo-secu' ),
			esc_html__( 'Tableau de bord', 'lebo-secu' ),
			'manage_options',
			'lebo-secu',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'lebo-secu',
			esc_html__( 'Paramètres', 'lebo-secu' ),
			esc_html__( 'Paramètres', 'lebo-secu' ),
			'manage_options',
			'lebo-secu-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'lebo-secu',
			esc_html__( 'Import / Export', 'lebo-secu' ),
			esc_html__( 'Import / Export', 'lebo-secu' ),
			'manage_options',
			'lebo-secu-import-export',
			array( $this, 'render_import_export' )
		);

		add_submenu_page(
			'lebo-secu',
			esc_html__( 'Journal d\'audit', 'lebo-secu' ),
			esc_html__( 'Journal d\'audit', 'lebo-secu' ),
			'manage_options',
			'lebo-secu-audit',
			array( $this, 'render_audit_log' )
		);
	}

	/**
	 * Enqueuer les assets admin uniquement sur les pages du plugin.
	 *
	 * @param string $hook Slug de la page admin courante.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! str_contains( $hook, 'lebo-secu' ) ) {
			return;
		}

		$asset_file = LBS_PLUGIN_DIR . 'build/index.asset.php';
		if ( file_exists( $asset_file ) ) {
			$asset        = require $asset_file;
			$dependencies = $asset['dependencies'];
			$version      = $asset['version'];
		} else {
			$dependencies = array( 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' );
			$version      = LBS_VERSION;
		}

		wp_enqueue_style(
			'lebo-secu-admin-components',
			wp_styles()->base_url . '/wp-includes/css/dist/components/style.min.css',
			array(),
			$version
		);

		wp_enqueue_style(
			'lebo-secu-admin',
			LBS_PLUGIN_URL . 'build/style-index.css',
			array( 'lebo-secu-admin-components' ),
			$version
		);

		wp_enqueue_script(
			'lebo-secu-admin',
			LBS_PLUGIN_URL . 'build/index.js',
			$dependencies,
			$version,
			true
		);

		wp_localize_script(
			'lebo-secu-admin',
			'lbsAdmin',
			array(
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => rest_url( 'lebo-secu/v1' ),
				'version' => LBS_VERSION,
			)
		);
	}

	/**
	 * Rendu du tableau de bord.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$score    = $this->calculate_security_score();
		$features = $this->config['features'] ?? array();
		?>
		<div class="wrap lbs-wrap">
			<h1><?php esc_html_e( 'Lebo Secu — Tableau de bord', 'lebo-secu' ); ?></h1>

			<div class="lbs-score-card">
				<div class="lbs-score-value"><?php echo esc_html( $score ); ?>/10</div>
				<div class="lbs-score-label"><?php esc_html_e( 'Score de sécurité', 'lebo-secu' ); ?></div>
			</div>

			<div class="lbs-features-grid">
				<?php foreach ( $features as $id => $feature ) : ?>
				<div class="lbs-feature-card <?php echo ( $feature['enabled'] ?? false ) ? 'lbs-active' : 'lbs-inactive'; ?>">
					<span class="lbs-feature-status dashicons <?php echo ( $feature['enabled'] ?? false ) ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
					<span class="lbs-feature-name"><?php echo esc_html( $id ); ?></span>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Rendu de la page paramètres.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap lbs-wrap">
			<h1><?php esc_html_e( 'Lebo Secu — Paramètres', 'lebo-secu' ); ?></h1>
			<p><?php esc_html_e( 'Configuration des features de sécurité.', 'lebo-secu' ); ?></p>
			<div id="lbs-settings-app"></div>
		</div>
		<?php
	}

	/**
	 * Rendu de la page Import/Export.
	 *
	 * @return void
	 */
	public function render_import_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap lbs-wrap">
			<h1><?php esc_html_e( 'Lebo Secu — Import / Export', 'lebo-secu' ); ?></h1>
			<div id="lbs-import-export-app"></div>
		</div>
		<?php
	}

	/**
	 * Rendu du journal d'audit.
	 *
	 * @return void
	 */
	public function render_audit_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap lbs-wrap">
			<h1><?php esc_html_e( 'Lebo Secu — Journal d\'audit', 'lebo-secu' ); ?></h1>
			<div id="lbs-audit-log-app"></div>
		</div>
		<?php
	}

	/**
	 * Calculer le score de sécurité sur 10 basé sur les features critiques actives.
	 *
	 * @return int Score entre 0 et 10.
	 */
	private function calculate_security_score(): int {
		$critical_features = array(
			'hide_version',
			'admin_url',
			'login_protection',
			'user_enumeration',
			'rest_api',
			'security_headers',
			'disable_features',
			'htaccess',
			'audit_log',
		);

		$score    = 0;
		$features = $this->config['features'] ?? array();

		foreach ( $critical_features as $feature ) {
			if ( ! empty( $features[ $feature ]['enabled'] ) ) {
				++$score;
			}
		}

		// Arrondi sur 10.
		return (int) round( ( $score / count( $critical_features ) ) * 10 );
	}
}
