<?php
/**
 * Read a single WordPress option.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Site;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the value of a WP option from the allowlist. Capability:
 * manage_options (sudo for ai_bot).
 */
final class GetOption implements AbilityInterface {

	public const ABILITY = 'talaxie-core/site-get-option';

	/**
	 * Options that are safe to expose. Filterable via talaxie_mcp_option_allowlist.
	 *
	 * @return list<string>
	 */
	public static function allowlist(): array {
		$default = array(
			'blogname',
			'blogdescription',
			'permalink_structure',
			'date_format',
			'time_format',
			'siteurl',
			'home',
			'admin_email',
			'WPLANG',
			'timezone_string',
			'start_of_week',
			'default_role',
		);
		return (array) apply_filters( 'talaxie_mcp_option_allowlist', $default );
	}

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
				'label'               => __( 'Get site option', 'talaxie-core' ),
				'description'         => __( 'Return the value of a WordPress option (allowlist enforced).', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name'  => array( 'type' => 'string' ),
						'_sudo' => array( 'type' => 'string' ),
					),
					'required'             => array( 'name' ),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					return CapabilityGate::check(
						self::ABILITY,
						'manage_options',
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function ( $input ) {
					$name = isset( $input['name'] ) ? (string) $input['name'] : '';
					if ( ! in_array( $name, self::allowlist(), true ) ) {
						return new \WP_Error(
							'talaxie_option_not_allowlisted',
							__( 'This option is not in the MCP allowlist.', 'talaxie-core' ),
							array( 'status' => 403 )
						);
					}
					return array(
						'name'  => $name,
						'value' => get_option( $name ),
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
