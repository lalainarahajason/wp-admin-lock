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
			array( $this, 'render_settings' ),
			'dashicons-shield',
			80
		);

		add_submenu_page(
			'lebo-secu',
			esc_html__( 'Paramètres', 'lebo-secu' ),
			esc_html__( 'Paramètres', 'lebo-secu' ),
			'manage_options',
			'lebo-secu',
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

		add_submenu_page(
			'lebo-secu',
			esc_html__( 'Fichier .htaccess', 'lebo-secu' ),
			esc_html__( 'Fichier .htaccess', 'lebo-secu' ),
			'manage_options',
			'lebo-secu-htaccess',
			array( $this, 'render_htaccess' )
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
	 * Rendu de l'éditeur .htaccess.
	 *
	 * @return void
	 */
	public function render_htaccess(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap lbs-wrap">
			<h1><?php esc_html_e( 'Lebo Secu — Éditeur .htaccess', 'lebo-secu' ); ?></h1>
			<div id="lbs-htaccess-app"></div>
		</div>
		<?php
	}

}
