<?php
/**
 * Feature F9 — Désactivation de features WordPress vulnérables.
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LBS_DisableFeatures
 */
class LBS_DisableFeatures implements LBS_Feature_Interface {

	/** @var array<string, mixed> */
	private array $config;

	public function __construct( array $config ) {
		$this->config = $config['features']['disable_features'] ?? array();
	}

	public static function is_enabled( array $config ): bool {
		return ! empty( $config['features']['disable_features']['enabled'] );
	}

	public function init(): void {
		if ( ! empty( $this->config['file_editor'] ) ) {
			$this->disable_file_editor();
		}

		if ( ! empty( $this->config['xmlrpc'] ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( $this, 'remove_x_pingback_header' ) );
		}

		if ( ! empty( $this->config['oembed'] ) ) {
			$this->disable_oembed();
		}

		if ( ! empty( $this->config['pingbacks'] ) ) {
			add_filter( 'pre_option_default_ping_status', '__return_zero' );
			add_filter( 'pre_option_default_pingback_flag', '__return_zero' );
		}
	}

	/**
	 * Désactiver l'éditeur de fichiers/thèmes dans l'admin.
	 *
	 * @return void
	 */
	private function disable_file_editor(): void {
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}
	}

	/**
	 * Désactiver toutes les fonctions oEmbed.
	 *
	 * @return void
	 */
	private function disable_oembed(): void {
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
		add_filter( 'embed_oembed_discover', '__return_false' );
	}

	/**
	 * Retirer le header X-Pingback des réponses HTTP.
	 *
	 * @param array<string, string> $headers Headers HTTP.
	 * @return array<string, string>
	 */
	public function remove_x_pingback_header( array $headers ): array {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
}
