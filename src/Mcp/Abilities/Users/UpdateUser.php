<?php
/**
 * Update a WordPress user.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Users;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Updates email, display name or role. Capability: edit_users (sudo only).
 */
final class UpdateUser implements AbilityInterface {

	public const ABILITY = 'talaxie-core/users-update';

	public static function name(): string {
		return self::ABILITY;
	}

	public static function is_allowed_on_production(): bool {
		return false;
	}

	public static function register(): void {
		wp_register_ability(
			self::ABILITY,
			array(
				'label'               => __( 'Update user', 'talaxie-core' ),
				'description'         => __( 'Update a WordPress user (email, display name, role). Requires edit_users via sudo.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'           => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'email'        => array(
							'type'   => 'string',
							'format' => 'email',
						),
						'display_name' => array( 'type' => 'string' ),
						'role'         => array( 'type' => 'string' ),
						'_sudo'        => array( 'type' => 'string' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					return CapabilityGate::check(
						self::ABILITY,
						'edit_users',
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

					$update = array( 'ID' => $id );
					if ( isset( $input['email'] ) ) {
						$update['user_email'] = sanitize_email( (string) $input['email'] );
					}
					if ( isset( $input['display_name'] ) ) {
						$update['display_name'] = sanitize_text_field( (string) $input['display_name'] );
					}
					if ( isset( $input['role'] ) ) {
						$role = sanitize_key( (string) $input['role'] );
						if ( ! get_role( $role ) ) {
							return new \WP_Error( 'talaxie_user_invalid_role', __( 'Unknown role.', 'talaxie-core' ), array( 'status' => 400 ) );
						}
						$update['role'] = $role;
					}

					$result = wp_update_user( $update );
					if ( is_wp_error( $result ) ) {
						return $result;
					}

					return array( 'id' => (int) $result );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}
}
