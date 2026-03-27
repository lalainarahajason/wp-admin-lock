<?php
/**
 * Feature F8 — Security Headers HTTP.
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LBS_SecurityHeaders
 */
class LBS_SecurityHeaders implements LBS_Feature_Interface {

	/** @var array<string, mixed> */
	private array $config;

	public function __construct( array $config ) {
		$this->config = $config['features']['security_headers'] ?? array();
	}

	public static function is_enabled( array $config ): bool {
		return ! empty( $config['features']['security_headers']['enabled'] );
	}

	public function init(): void {
		add_action( 'send_headers', array( $this, 'send_security_headers' ) );
	}

	/**
	 * Envoyer les headers de sécurité configurés.
	 *
	 * @return void
	 */
	public function send_security_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		$headers = $this->config['headers'] ?? array();

		if ( ! empty( $headers['x_frame_options']['enabled'] ) ) {
			$value = sanitize_text_field( $headers['x_frame_options']['value'] ?? 'SAMEORIGIN' );
			header( 'X-Frame-Options: ' . $value );
		}

		if ( ! empty( $headers['x_content_type']['enabled'] ) ) {
			header( 'X-Content-Type-Options: nosniff' );
		}

		if ( ! empty( $headers['referrer_policy']['enabled'] ) ) {
			$value = sanitize_text_field( $headers['referrer_policy']['value'] ?? 'strict-origin-when-cross-origin' );
			header( 'Referrer-Policy: ' . $value );
		}

		if ( ! empty( $headers['permissions_policy']['enabled'] ) ) {
			$value = sanitize_text_field( $headers['permissions_policy']['value'] ?? 'camera=(), microphone=()' );
			header( 'Permissions-Policy: ' . $value );
		}

		if ( ! empty( $headers['csp']['enabled'] ) ) {
			$csp_value = sanitize_text_field( $headers['csp']['value'] ?? '' );
			if ( $csp_value ) {
				$csp_header = ! empty( $headers['csp']['report_only'] )
					? 'Content-Security-Policy-Report-Only'
					: 'Content-Security-Policy';
				header( $csp_header . ': ' . $csp_value );
			}
		}
	}
}
