<?php
/**
 * Centralised capability + sudo check shared by every MCP ability.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp;

use Talaxie\Core\Mcp\Sudo\TokenManager;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates the "either the user has the capability, or they hand
 * over a valid sudo token scoped to it" decision used by every ability
 * permission_callback.
 *
 * The gate also fires the `talaxie_mcp_audit` action so the Phase 2
 * audit logger can subscribe without coupling the abilities to it.
 */
final class CapabilityGate {

	public const SUDO_INPUT_KEY = '_sudo';
	public const VIRTUAL_SUPER_ADMIN = 'super_admin';

	/**
	 * Run the capability check for an ability.
	 *
	 * Accepts the virtual capability `super_admin` for multisite-only
	 * abilities — it is not a real WP cap, so it must be handled here.
	 *
	 * @param string                $ability_name Fully qualified ability slug.
	 * @param string                $required_cap Capability the ability needs.
	 * @param array<string, mixed>  $input        Raw input passed to the permission callback.
	 *
	 * @return bool|\WP_Error true on success, WP_Error on permission denial.
	 */
	public static function check( string $ability_name, string $required_cap, array $input ) {
		if ( self::has_native_capability( $required_cap ) ) {
			self::audit( $ability_name, $required_cap, false, true );
			return true;
		}

		$token = isset( $input[ self::SUDO_INPUT_KEY ] ) && is_string( $input[ self::SUDO_INPUT_KEY ] )
			? trim( $input[ self::SUDO_INPUT_KEY ] )
			: '';

		if ( '' !== $token && TokenManager::validate( $token, $required_cap ) ) {
			self::audit( $ability_name, $required_cap, true, true );
			return true;
		}

		self::audit( $ability_name, $required_cap, '' !== $token, false );

		return new \WP_Error(
			'talaxie_mcp_permission_denied',
			sprintf(
				/* translators: %s: capability slug. */
				__( 'This ability requires the "%s" capability. Provide a sudo token in the "_sudo" parameter.', 'talaxie-core' ),
				$required_cap
			),
			array(
				'status'              => 401,
				'required_capability' => $required_cap,
			)
		);
	}

	/**
	 * Fire the audit event. Phase 2 will hook a CPT-backed logger; for now
	 * we leave a structured action so consumers can subscribe.
	 *
	 * @param string $ability_name Ability slug.
	 * @param string $required_cap Capability requested.
	 * @param bool   $sudo_used    Whether a sudo token was used.
	 * @param bool   $allowed      Outcome of the check.
	 *
	 * @return void
	 */
	/**
	 * Native capability check that also understands the virtual super_admin.
	 *
	 * @param string $cap Capability slug.
	 *
	 * @return bool
	 */
	private static function has_native_capability( string $cap ): bool {
		if ( self::VIRTUAL_SUPER_ADMIN === $cap ) {
			return is_multisite() && is_super_admin( get_current_user_id() );
		}
		return current_user_can( $cap );
	}

	private static function audit( string $ability_name, string $required_cap, bool $sudo_used, bool $allowed ): void {
		do_action(
			'talaxie_mcp_audit',
			array(
				'ability'   => $ability_name,
				'capability'=> $required_cap,
				'user_id'   => get_current_user_id(),
				'sudo_used' => $sudo_used,
				'allowed'   => $allowed,
				'timestamp' => time(),
			)
		);
	}
}
