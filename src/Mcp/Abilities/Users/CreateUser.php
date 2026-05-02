<?php
/**
 * Create a WordPress user.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Users;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Inserts a new user. Capability: create_users (sudo only). Prod-blocked.
 */
final class CreateUser implements AbilityInterface {

	public const ABILITY = 'talaxie-core/users-create';

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
				'label'               => __( 'Create user', 'talaxie-core' ),
				'description'         => __( 'Create a WordPress user. Requires create_users via sudo. Disabled on production.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'login'    => array( 'type' => 'string' ),
						'email'    => array( 'type' => 'string', 'format' => 'email' ),
						'role'     => array( 'type' => 'string', 'default' => 'subscriber' ),
						'password' => array( 'type' => 'string' ),
						'_sudo'    => array( 'type' => 'string' ),
					),
					'required'             => array( 'login', 'email' ),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					return CapabilityGate::check(
						self::ABILITY,
						'create_users',
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function ( $input ) {
					$input    = is_array( $input ) ? $input : array();
					$login    = sanitize_user( (string) ( $input['login'] ?? '' ), true );
					$email    = sanitize_email( (string) ( $input['email'] ?? '' ) );
					$role     = isset( $input['role'] ) ? sanitize_key( (string) $input['role'] ) : 'subscriber';
					$password = isset( $input['password'] ) ? (string) $input['password'] : wp_generate_password( 24, true, true );

					if ( '' === $login || '' === $email ) {
						return new \WP_Error( 'talaxie_user_invalid_input', __( 'A login and an email are required.', 'talaxie-core' ), array( 'status' => 400 ) );
					}
					if ( ! get_role( $role ) ) {
						return new \WP_Error( 'talaxie_user_invalid_role', __( 'Unknown role.', 'talaxie-core' ), array( 'status' => 400 ) );
					}

					$user_id = wp_insert_user(
						array(
							'user_login' => $login,
							'user_email' => $email,
							'user_pass'  => $password,
							'role'       => $role,
						)
					);
					if ( is_wp_error( $user_id ) ) {
						return $user_id;
					}

					return array(
						'id'    => (int) $user_id,
						'login' => $login,
						'email' => $email,
						'role'  => $role,
					);
				},
				'meta'                => array(
					'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}
}
