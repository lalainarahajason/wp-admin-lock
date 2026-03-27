<?php
/**
 * Feature F2 — Masquage de la version WordPress.
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LBS_HideVersion
 */
class LBS_HideVersion implements LBS_Feature_Interface {

	/** @var array<string, mixed> */
	private array $config;

	public function __construct( array $config ) {
		$this->config = $config['features']['hide_version'] ?? array();
	}

	public static function is_enabled( array $config ): bool {
		return ! empty( $config['features']['hide_version']['enabled'] );
	}

	public function init(): void {
		// Retirer le generator tag du <head> et des flux RSS.
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );

		// Retirer la version des scripts/styles enqueuées.
		add_filter( 'style_loader_src', array( $this, 'remove_version_query' ), 9999 );
		add_filter( 'script_loader_src', array( $this, 'remove_version_query' ), 9999 );
	}

	/**
	 * Supprimer le paramètre ?ver= des URLs de scripts/styles.
	 *
	 * @param string $src URL du script ou style.
	 * @return string
	 */
	public function remove_version_query( string $src ): string {
		if ( str_contains( $src, 'ver=' ) ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}
}
