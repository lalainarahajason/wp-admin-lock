<?php
/**
 * Plugin Name:       Lebo Secu
 * Plugin URI:        https://github.com/lalainarahajason/wp-admin-lock
 * Description:       Plugin WordPress de sécurisation multi-sites : protection login, custom admin URL, headers HTTP, audit log et plus.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Lalainarahajason
 * License:           GPL-2.0-or-later
 * Text Domain:       lebo-secu
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

// ── Compatibilité PHP ────────────────────────────────────────────────────────
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Lebo Secu requiert PHP 8.1 ou supérieur.', 'lebo-secu' ) .
				'</p></div>';
		}
	);
	return;
}

// ── Constantes ───────────────────────────────────────────────────────────────
define( 'LBS_VERSION', '1.0.0' );
define( 'LBS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LBS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LBS_PLUGIN_FILE', __FILE__ );

// ── Chargement des classes core ──────────────────────────────────────────────
require_once LBS_PLUGIN_DIR . 'includes/LBS_Feature_Interface.php';
require_once LBS_PLUGIN_DIR . 'includes/Config/Migrator.php';
require_once LBS_PLUGIN_DIR . 'includes/Helpers.php';
require_once LBS_PLUGIN_DIR . 'includes/Database.php';
require_once LBS_PLUGIN_DIR . 'includes/Admin/AdminApi.php';
require_once LBS_PLUGIN_DIR . 'includes/Admin/AdminPage.php';

// ── Features ─────────────────────────────────────────────────────────────────
require_once LBS_PLUGIN_DIR . 'includes/Features/HideVersion.php';
require_once LBS_PLUGIN_DIR . 'includes/Features/UserEnumeration.php';
require_once LBS_PLUGIN_DIR . 'includes/Features/DisableFeatures.php';
require_once LBS_PLUGIN_DIR . 'includes/Features/AdminUrl.php';
require_once LBS_PLUGIN_DIR . 'includes/Features/LoginProtection.php';
require_once LBS_PLUGIN_DIR . 'includes/Features/RestApiProtection.php';
require_once LBS_PLUGIN_DIR . 'includes/Features/SecurityHeaders.php';
require_once LBS_PLUGIN_DIR . 'includes/Features/HtaccessManager.php';
require_once LBS_PLUGIN_DIR . 'includes/Features/AuditLog.php';
require_once LBS_PLUGIN_DIR . 'includes/ImportExport.php';

// ── Activation / Désactivation ────────────────────────────────────────────────
register_activation_hook(
	__FILE__,
	static function (): void {
		LBS_Database::create_tables();
		// Générer le recovery token F1 à l'activation
		if ( ! get_option( 'lbs_recovery_token' ) ) {
			update_option( 'lbs_recovery_token', bin2hex( random_bytes( 32 ) ), false );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		wp_clear_scheduled_hook( 'lbs_audit_log_cleanup' );
	}
);

// ── Initialisation ────────────────────────────────────────────────────────────
add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain( 'lebo-secu', false, dirname( plugin_basename( LBS_PLUGIN_FILE ) ) . '/languages' );

		$config = LBS_Helpers::get_config();

		foreach ( LBS_Helpers::get_feature_classes() as $class ) {
			if ( $class::is_enabled( $config ) ) {
				( new $class( $config ) )->init();
			}
		}

		if ( is_admin() ) {
			( new LBS_Admin_Page( $config ) )->init();
		}
		( new LBS_Admin_Api() )->init();
	}
);
