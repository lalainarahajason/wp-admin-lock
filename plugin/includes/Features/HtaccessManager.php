<?php
/**
 * Feature F3 — Gestionnaire .htaccess.
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LBS_HtaccessManager
 *
 * Lecture, backup et écriture sécurisée du .htaccess via WP_Filesystem.
 */
class LBS_HtaccessManager implements LBS_Feature_Interface {

	/** @var array<string, mixed> */
	private array $config;

	/** Marqueurs pour identifier les blocs injectés par Lebo Secu. */
	private const MARKER_BEGIN = '# BEGIN lebo-secu';
	private const MARKER_END   = '# END lebo-secu';

	public function __construct( array $config ) {
		$this->config = $config['features']['htaccess'] ?? array();
	}

	public static function is_enabled( array $config ): bool {
		return ! empty( $config['features']['htaccess']['enabled'] );
	}

	public function init(): void {
		// L'écriture .htaccess est déclenchée manuellement depuis l'UI admin.
		// Aucun hook automatique en front-end — opération trop risquée.
	}

	/**
	 * Lire le contenu actuel du .htaccess.
	 *
	 * @return string|WP_Error
	 */
	public function read(): string|WP_Error {
		$path = ABSPATH . '.htaccess';

		if ( ! file_exists( $path ) ) {
			return new WP_Error( 'htaccess_not_found', __( '.htaccess introuvable.', 'lebo-secu' ) );
		}

		$filesystem = $this->get_filesystem();
		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}

		return (string) $filesystem->get_contents( $path );
	}

	/**
	 * Écrire les règles dans le .htaccess (avec backup automatique).
	 *
	 * @param string $rules Contenu des règles à injecter.
	 * @return true|WP_Error
	 */
	public function write( string $rules ): true|WP_Error {
		$path = ABSPATH . '.htaccess';

		$filesystem = $this->get_filesystem();
		if ( is_wp_error( $filesystem ) ) {
			return $filesystem;
		}

		// Vérifier les permissions d'écriture.
		if ( ! $filesystem->is_writable( $path ) ) {
			return new WP_Error( 'htaccess_not_writable', __( '.htaccess n\'est pas accessible en écriture.', 'lebo-secu' ) );
		}

		// Backup avant écriture.
		$backup = $this->read();
		if ( ! is_wp_error( $backup ) ) {
			update_option(
				'lbs_htaccess_backup_' . time(),
				$backup,
				false
			);
		}

		// Lire le contenu actuel et remplacer le bloc Lebo Secu.
		$current = is_wp_error( $backup ) ? '' : $backup;
		$updated = $this->inject_rules( $current, $rules );

		if ( ! $filesystem->put_contents( $path, $updated, FS_CHMOD_FILE ) ) {
			return new WP_Error( 'htaccess_write_failed', __( 'Impossible d\'écrire dans .htaccess.', 'lebo-secu' ) );
		}

		return true;
	}

	/**
	 * Supprimer le bloc Lebo Secu du .htaccess.
	 *
	 * @return true|WP_Error
	 */
	public function remove(): true|WP_Error {
		return $this->write( '' );
	}

	/**
	 * Injecter les règles entre les marqueurs, en remplaçant l'ancien bloc.
	 *
	 * @param string $content Contenu actuel du .htaccess.
	 * @param string $rules   Nouvelles règles.
	 * @return string
	 */
	private function inject_rules( string $content, string $rules ): string {
		$pattern = '/' . preg_quote( self::MARKER_BEGIN, '/' ) . '.*?' . preg_quote( self::MARKER_END, '/' ) . '/s';
		$content = preg_replace( $pattern, '', $content ) ?? $content;
		$content = rtrim( $content );

		if ( $rules ) {
			$content .= "\n\n" . self::MARKER_BEGIN . "\n" . $rules . "\n" . self::MARKER_END . "\n";
		}

		return $content;
	}

	/**
	 * Initialiser WP_Filesystem.
	 *
	 * @return WP_Filesystem_Base|WP_Error
	 */
	private function get_filesystem(): \WP_Filesystem_Base|WP_Error {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() ) {
			return new WP_Error( 'fs_error', __( 'Impossible d\'initialiser WP_Filesystem.', 'lebo-secu' ) );
		}

		return $wp_filesystem;
	}
}
