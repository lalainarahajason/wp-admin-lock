<?php
/**
 * Helpers globaux Lebo Secu.
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LBS_Helpers
 *
 * Utilitaires partagés : lecture de config, merge avec defaults, liste des features.
 */
class LBS_Helpers {

	/**
	 * Version courante du schéma de configuration.
	 * À incrémenter chaque fois que la structure de config change.
	 */
	public const CONFIG_SCHEMA_VERSION = '1.0.0';

	/**
	 * Clé WordPress option pour la config.
	 */
	public const OPTION_KEY = 'lebosecu_config';

	/**
	 * Lire, migrer et fusionner la config sauvegardée avec les defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_config(): array {
		$saved = get_option( self::OPTION_KEY, null );

		if ( null === $saved ) {
			return self::get_default_config();
		}

		if ( is_string( $saved ) ) {
			$saved = json_decode( $saved, true ) ?? array();
		}

		if ( ! is_array( $saved ) ) {
			return self::get_default_config();
		}

		// 1. Migrer le schéma si nécessaire.
		$migrated = LBS_Config_Migrator::migrate( $saved );

		// 2. Fusionner avec les defaults (ajoute les clés manquantes sans écraser).
		$merged = self::merge_with_defaults( $migrated, self::get_default_config() );

		// 3. Persister silencieusement si la migration a produit un changement.
		if ( $migrated !== $saved ) {
			update_option( self::OPTION_KEY, wp_json_encode( $merged ) );
		}

		return $merged;
	}

	/**
	 * Sauvegarder la configuration en base.
	 *
	 * @param array<string, mixed> $config Config à sauvegarder.
	 * @return bool
	 */
	public static function save_config( array $config ): bool {
		$config['version'] = self::CONFIG_SCHEMA_VERSION;
		return update_option( self::OPTION_KEY, wp_json_encode( $config ) );
	}

	/**
	 * Retourne la configuration par défaut complète.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_config(): array {
		return array(
			'version'  => self::CONFIG_SCHEMA_VERSION,
			'features' => array(
				'admin_url'        => array(
					'enabled' => false,
					'slug'    => 'wp-securite',
				),
				'hide_version'     => array(
					'enabled'       => true,
					'hide_in_admin' => false,
				),
				'htaccess'         => array(
					'enabled' => false,
					'rules'   => array(),
				),
				'rest_api'         => array(
					'enabled'             => false,
					'whitelist_endpoints' => array(),
					'whitelist_ips'       => array(),
				),
				'login_protection' => array(
					'enabled'          => true,
					'max_attempts'     => 5,
					'lockout_duration' => 900,
					'basic_auth'       => false,
					'email_notify'     => false,
					'whitelist_ips'    => array(),
				),
				'user_enumeration' => array(
					'enabled' => true,
				),
				'security_headers' => array(
					'enabled'        => true,
					'headers'        => array(
						'x_frame_options'    => array(
							'enabled' => true,
							'value'   => 'SAMEORIGIN',
						),
						'x_content_type'     => array(
							'enabled' => true,
						),
						'referrer_policy'    => array(
							'enabled' => true,
							'value'   => 'strict-origin-when-cross-origin',
						),
						'permissions_policy' => array(
							'enabled' => true,
							'value'   => 'camera=(), microphone=(), geolocation=()',
						),
						'csp'                => array(
							'enabled'     => false,
							'report_only' => true,
							'value'       => '',
						),
					),
					'custom_headers' => array(),
				),
				'disable_features' => array(
					'enabled'     => true,
					'file_editor' => true,
					'xmlrpc'      => true,
					'oembed'      => false,
					'pingbacks'   => true,
				),
				'audit_log'        => array(
					'enabled'        => true,
					'retention_days' => 30,
				),
			),
		);
	}

	/**
	 * Fusion récursive non-destructive — ajoute les clés manquantes sans écraser.
	 *
	 * @param array<string, mixed> $saved    Config sauvegardée.
	 * @param array<string, mixed> $defaults Config par défaut.
	 * @return array<string, mixed>
	 */
	public static function merge_with_defaults( array $saved, array $defaults ): array {
		foreach ( $defaults as $key => $default_value ) {
			if ( ! array_key_exists( $key, $saved ) ) {
				$saved[ $key ] = $default_value;
			} elseif ( is_array( $default_value ) && is_array( $saved[ $key ] ) ) {
				$saved[ $key ] = self::merge_with_defaults( $saved[ $key ], $default_value );
			}
			// Valeur scalaire existante → conserver sans toucher.
		}
		return $saved;
	}

	/**
	 * Retourne la liste ordonnée des classes Feature à instancier.
	 *
	 * @return array<int, class-string<LBS_Feature_Interface>>
	 */
	public static function get_feature_classes(): array {
		return array(
			'LBS_HideVersion',
			'LBS_UserEnumeration',
			'LBS_DisableFeatures',
			'LBS_AdminUrl',
			'LBS_LoginProtection',
			'LBS_RestApiProtection',
			'LBS_SecurityHeaders',
			'LBS_HtaccessManager',
			'LBS_AuditLog',
		);
	}

	/**
	 * Slugs réservés WordPress — interdits comme custom admin URL.
	 *
	 * @return array<int, string>
	 */
	public static function get_reserved_slugs(): array {
		return array(
			'wp-admin',
			'wp-login.php',
			'wp-content',
			'wp-includes',
			'wp-json',
			'wp-cron.php',
			'wp-activate.php',
			'wp-signup.php',
			'wp-mail.php',
			'xmlrpc.php',
			'admin-ajax.php',
			'async-upload.php',
			'index.php',
			'feed',
			'rss',
			'rss2',
			'atom',
			'comments',
			'page',
		);
	}
}
