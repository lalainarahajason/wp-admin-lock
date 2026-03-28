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
