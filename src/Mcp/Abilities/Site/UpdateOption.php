<?php
/**
 * Update a WordPress option (allowlist + sudo + prod-block).
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Site;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Writes a WordPress option from the allowlist. Capability: manage_options
 * (sudo only). Disabled on production.
 */
final class UpdateOption implements AbilityInterface {

	public const ABILITY = 'talaxie-core/site-update-option';

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
				'label'               => __( 'Update site option', 'talaxie-core' ),
				'description'         => __( 'Update a WordPress option (allowlist enforced). Sudo only, prod-blocked.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name'  => array( 'type' => 'string' ),
						'value' => array(
							'type'        => array( 'string', 'number', 'boolean' ),
							'description' => __( 'Scalar option value (string, number, or boolean).', 'talaxie-core' ),
						),
						'_sudo' => array( 'type' => 'string' ),
					),
					'required'             => array( 'name', 'value' ),
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
					$input = is_array( $input ) ? $input : array();
					$name  = (string) ( $input['name'] ?? '' );

					if ( ! in_array( $name, GetOption::allowlist(), true ) ) {
						return new \WP_Error(
							'talaxie_option_not_allowlisted',
							__( 'This option is not in the MCP allowlist.', 'talaxie-core' ),
							array( 'status' => 403 )
						);
					}

					$updated = update_option( $name, $input['value'] ?? '' );

					return array(
						'name'    => $name,
						'updated' => (bool) $updated,
					);
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}
}
