<?php
/**
 * Feature F5 — Import / Export de configuration.
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LBS_ImportExport
 *
 * Export JSON de la config et import avec migration + dry-run.
 */
class LBS_ImportExport {

	/**
	 * Initialiser les endpoints REST.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Enregistrer les routes REST.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'lebo-secu/v1',
			'/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_export' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			'lebo-secu/v1',
			'/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_import' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * Handler export — retourne la config JSON pour téléchargement.
	 *
	 * @param WP_REST_Request $request Requête REST.
	 * @return WP_REST_Response
	 */
	public function handle_export( WP_REST_Request $request ): WP_REST_Response {
		if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid nonce' ), 403 );
		}

		$config = LBS_Helpers::get_config();
		return new WP_REST_Response( $config, 200 );
	}

	/**
	 * Handler import — valide, migre, fusionne et applique la config importée.
	 *
	 * @param WP_REST_Request $request Requête REST.
	 * @return WP_REST_Response
	 */
	public function handle_import( WP_REST_Request $request ): WP_REST_Response {
		if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid nonce' ), 403 );
		}

		$body = $request->get_json_params();

		if ( ! $this->validate_schema( $body ) ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Schéma JSON invalide.', 'lebo-secu' ) ),
				422
			);
		}

		// Migrer si la config importée est d'une version antérieure.
		$migrated = LBS_Config_Migrator::migrate( $body );

		// Fusionner avec les defaults du code actuel.
		$final = LBS_Helpers::merge_with_defaults( $migrated, LBS_Helpers::get_default_config() );

		// Dry-run : retourner la config sans l'appliquer.
		$dry_run = (bool) $request->get_param( 'dry_run' );
		if ( $dry_run ) {
			return new WP_REST_Response(
				array(
					'dry_run' => true,
					'config'  => $final,
				),
				200
			);
		}

		LBS_Helpers::save_config( $final );

		return new WP_REST_Response(
			array(
				'success' => true,
				'config'  => $final,
			),
			200
		);
	}

	/**
	 * Valider la structure minimale d'une config importée.
	 *
	 * @param mixed $data Données à valider.
	 * @return bool
	 */
	private function validate_schema( mixed $data ): bool {
		if ( ! is_array( $data ) ) {
			return false;
		}
		// La config doit avoir une clé 'features' tableau.
		return isset( $data['features'] ) && is_array( $data['features'] );
	}
}
