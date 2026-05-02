<?php
/**
 * List WordPress users.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Users;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Lists users. Capability: list_users (sudo for ai_bot).
 */
final class ListUsers implements AbilityInterface {

	public const ABILITY = 'talaxie-core/users-list';

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
				'label'               => __( 'List users', 'talaxie-core' ),
				'description'         => __( 'List WordPress users with role and registration date.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
						'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ),
						'role'     => array( 'type' => 'string' ),
						'search'   => array( 'type' => 'string' ),
						'_sudo'    => array( 'type' => 'string' ),
					),
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
					$input    = is_array( $input ) ? $input : array();
					$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
					$per_page = max( 1, min( 100, (int) ( $input['per_page'] ?? 50 ) ) );

					$args = array(
						'number' => $per_page,
						'paged'  => $page,
						'fields' => array( 'ID', 'user_login', 'user_email', 'user_registered', 'display_name' ),
					);
					if ( isset( $input['role'] ) && '' !== $input['role'] ) {
						$args['role'] = sanitize_key( (string) $input['role'] );
					}
					if ( isset( $input['search'] ) && '' !== $input['search'] ) {
						$args['search']         = '*' . esc_attr( (string) $input['search'] ) . '*';
						$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
					}

					$query = new \WP_User_Query( $args );

					$items = array();
					foreach ( $query->get_results() as $user ) {
						$wp_user = get_userdata( (int) $user->ID );
						$items[] = array(
							'id'           => (int) $user->ID,
							'login'        => (string) $user->user_login,
							'email'        => (string) $user->user_email,
							'display_name' => (string) $user->display_name,
							'registered'   => (string) $user->user_registered,
							'roles'        => $wp_user instanceof \WP_User ? array_values( $wp_user->roles ) : array(),
						);
					}

					return array(
						'items'    => $items,
						'page'     => $page,
						'per_page' => $per_page,
						'total'    => (int) $query->get_total(),
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
