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
			event_type varchar(50) NOT NULL,
			user_id bigint(20) unsigned NULL DEFAULT NULL,
			ip_address varchar(45) NOT NULL DEFAULT '',
			user_agent text NULL DEFAULT NULL,
			details json NULL DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY event_type (event_type),
			KEY created_at (created_at),
			KEY ip_address (ip_address)
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
