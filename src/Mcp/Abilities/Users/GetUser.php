<?php
/**
 * Fetch a single WordPress user.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Users;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Returns a single user. Capability: list_users (sudo for ai_bot).
 */
final class GetUser implements AbilityInterface {

	public const ABILITY = 'talaxie-core/users-get';

	public static function name(): string {
		return self::ABILITY;
	}

	public static function is_allowed_on_production(): bool {
		return true;
	}

	public static function register(): void {
		wp_register_ability(
			self::ABILITY,
			array(
				'label'               => __( 'Get user', 'talaxie-core' ),
				'description'         => __( 'Return one WordPress user (id, login, email, roles).', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'integer', 'minimum' => 1 ),
						'_sudo' => array( 'type' => 'string' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					return CapabilityGate::check(
						self::ABILITY,
						'list_users',
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function ( $input ) {
					$input = is_array( $input ) ? $input : array();
					$id    = (int) ( $input['id'] ?? 0 );
					$user  = $id > 0 ? get_userdata( $id ) : false;
					if ( ! $user instanceof \WP_User ) {
						return new \WP_Error( 'talaxie_user_not_found', __( 'No user matches that id.', 'talaxie-core' ), array( 'status' => 404 ) );
					}

					return array(
						'id'           => (int) $user->ID,
						'login'        => (string) $user->user_login,
						'email'        => (string) $user->user_email,
						'display_name' => (string) $user->display_name,
						'registered'   => (string) $user->user_registered,
						'roles'        => array_values( $user->roles ),
					);
				},
				'meta'                => array(
					'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}
}
