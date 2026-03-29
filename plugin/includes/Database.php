<?php
/**
 * Gestion de la base de données Lebo Secu.
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LBS_Database
 *
 * Création et mise à jour des tables custom du plugin.
 * Appelé uniquement depuis register_activation_hook.
 */
class LBS_Database {

	/**
	 * Créer ou mettre à jour toutes les tables du plugin.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'lebosecu_logs';

		// Migration manuelle car dbDelta ne gère pas bien les renommages de colonnes.
		self::maybe_migrate_logs_table( $table_name );

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			event_code varchar(50) NOT NULL,
			severity varchar(20) NOT NULL DEFAULT 'INFO',
			actor_id bigint(20) unsigned NULL DEFAULT 0,
			actor_ip varchar(45) NOT NULL DEFAULT '',
			user_agent text NULL DEFAULT NULL,
			request_url text NULL DEFAULT NULL,
			metadata longtext NULL DEFAULT NULL,
			PRIMARY KEY (id),
			KEY event_code (event_code),
			KEY created_at (created_at),
			KEY actor_ip (actor_ip),
			KEY severity (severity)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Gère la migration des colonnes si la table existe déjà avec l'ancien schéma.
	 *
	 * @param string $table_name Nom de la table.
	 */
	private static function maybe_migrate_logs_table( string $table_name ): void {
		global $wpdb;

		// Vérifier si la table existe.
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $table_name ) ) );
		if ( ! $table_exists ) {
			return;
		}

		// Récupérer les colonnes actuelles.
		$columns = $wpdb->get_col( "DESCRIBE {$table_name}" );

		// 1. Renommer event_type -> event_code.
		if ( in_array( 'event_type', $columns, true ) && ! in_array( 'event_code', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} CHANGE event_type event_code varchar(50) NOT NULL" );
		}

		// 2. Renommer user_id -> actor_id.
		if ( in_array( 'user_id', $columns, true ) && ! in_array( 'actor_id', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} CHANGE user_id actor_id bigint(20) unsigned NULL DEFAULT 0" );
		}

		// 3. Renommer ip_address -> actor_ip.
		if ( in_array( 'ip_address', $columns, true ) && ! in_array( 'actor_ip', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} CHANGE ip_address actor_ip varchar(45) NOT NULL DEFAULT ''" );
		}

		// 4. Renommer details -> metadata.
		if ( in_array( 'details', $columns, true ) && ! in_array( 'metadata', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} CHANGE details metadata longtext NULL DEFAULT NULL" );
		}
	}

	/**
	 * Supprimer toutes les tables du plugin (désinstallation).
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}lebosecu_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
