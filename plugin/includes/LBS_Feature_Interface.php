<?php
/**
 * Interface commune à toutes les features Lebo Secu.
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Interface LBS_Feature_Interface
 *
 * Toute feature doit implémenter cette interface.
 * - is_enabled() est statique pour permettre la vérification sans instanciation.
 * - init() enregistre les hooks WordPress de la feature.
 */
interface LBS_Feature_Interface {

	/**
	 * Vérifie si la feature est activée dans la configuration.
	 *
	 * @param array<string, mixed> $config Configuration complète du plugin.
	 * @return bool
	 */
	public static function is_enabled( array $config ): bool;

	/**
	 * Constructeur — reçoit la configuration complète.
	 *
	 * @param array<string, mixed> $config Configuration complète du plugin.
	 */
	public function __construct( array $config );

	/**
	 * Enregistre les hooks WordPress de la feature.
	 * Appelé uniquement si is_enabled() retourne true.
	 *
	 * @return void
	 */
	public function init(): void;
}
