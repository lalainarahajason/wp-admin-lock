<?php
/**
 * Migrateur de configuration Lebo Secu.
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LBS_Config_Migrator
 *
 * Exécute les migrations de schéma de configuration dans l'ordre croissant.
 * Chaque méthode de migration gère une transition de version.
 */
class LBS_Config_Migrator {

	/**
	 * Point d'entrée — exécute toutes les migrations nécessaires dans l'ordre.
	 *
	 * @param array<string, mixed> $config Config à migrer.
	 * @return array<string, mixed>
	 */
	public static function migrate( array $config ): array {
		$version = $config['version'] ?? '0.0.0';

		$config = self::migrate_to_1_0_0( $config, $version );

		// Ajouter ici les futures migrations :
		// $config = self::migrate_to_1_1_0( $config, $version );

		return $config;
	}

	/**
	 * Migration 0.x.x → 1.0.0
	 *
	 * Cas couverts :
	 * - Config sans champ `version` (créée avant versionning)
	 * - Renommage `features.rest` → `features.rest_api`
	 *
	 * @param array<string, mixed> $config          Config courante.
	 * @param string               $current_version Version détectée.
	 * @return array<string, mixed>
	 */
	private static function migrate_to_1_0_0( array $config, string $current_version ): array {
		if ( version_compare( $current_version, '1.0.0', '>=' ) ) {
			return $config; // Déjà à jour.
		}

		// Renommage : features.rest → features.rest_api.
		if ( isset( $config['features']['rest'] ) && ! isset( $config['features']['rest_api'] ) ) {
			$config['features']['rest_api'] = $config['features']['rest'];
			unset( $config['features']['rest'] );
		}

		$config['version'] = '1.0.0';
		return $config;
	}
}
