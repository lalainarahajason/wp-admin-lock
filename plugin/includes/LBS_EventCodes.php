<?php
/**
 * Dictionnaire des codes d'événements et sévérités pour le journal d'audit.
 *
 * @package LeboSecu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LBS_EventCodes
 *
 * Centralise les constantes utilisées pour le logging.
 */
class LBS_EventCodes {

	// Sévérités.
	public const SEVERITY_INFO     = 'INFO';
	public const SEVERITY_NOTICE   = 'NOTICE';
	public const SEVERITY_WARNING  = 'WARNING';
	public const SEVERITY_CRITICAL = 'CRITICAL';

	// Codes d'événements : Authentification.
	public const AUTH_LOGIN_SUCCESS       = 'AUTH_LOGIN_SUCCESS';
	public const AUTH_LOGIN_FAILED        = 'AUTH_LOGIN_FAILED';
	public const AUTH_RECOVERY_TOKEN_USED = 'AUTH_RECOVERY_TOKEN_USED';
	public const AUTH_LOCKOUT             = 'AUTH_LOCKOUT';

	// Codes d'événements : Sécurité.
	public const SECURITY_ENUMERATION_BLOCKED = 'SECURITY_ENUMERATION_BLOCKED';
	public const SECURITY_REST_DENIED         = 'SECURITY_REST_DENIED';

	// Codes d'événements : Configuration et Système.
	public const CONFIG_UPDATED          = 'CONFIG_UPDATED';
	public const CONFIG_IMPORTED         = 'CONFIG_IMPORTED';
	public const CONFIG_EXPORTED         = 'CONFIG_EXPORTED';
	public const SYSTEM_HTACCESS_WRITTEN = 'SYSTEM_HTACCESS_WRITTEN';
}
