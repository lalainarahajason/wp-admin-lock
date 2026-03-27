<?php
/**
 * Bootstrap PHPUnit pour les tests Lebo Secu.
 *
 * @package LeboSecu
 */

// Connexion aux functions WordPress de test.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	exit( 1 );
}

// Chemin du plugin.
define( 'LBS_TESTS_DIR', dirname( __DIR__ ) );
define( 'LBS_PLUGIN_FILE_FOR_TESTS', LBS_TESTS_DIR . '/plugin/lebo-secu.php' );

// Charger l'autoloader Composer (requis pour PHPUnit Polyfills).
require_once LBS_TESTS_DIR . '/vendor/autoload.php';

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require LBS_PLUGIN_FILE_FOR_TESTS;
	}
);

require $_tests_dir . '/includes/bootstrap.php';
