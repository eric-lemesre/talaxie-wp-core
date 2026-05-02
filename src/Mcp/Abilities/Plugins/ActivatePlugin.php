<?php
/**
 * Activate a plugin.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Plugins;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Activates a plugin by file path. Capability: activate_plugins (sudo).
 * Disabled on production.
 */
final class ActivatePlugin implements AbilityInterface {

	public const ABILITY = 'talaxie-core/plugins-activate';

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
				'label'               => __( 'Activate plugin', 'talaxie-core' ),
				'description'         => __( 'Activate an installed plugin by its file path. Sudo only, prod-blocked.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'file'  => array( 'type' => 'string' ),
						'_sudo' => array( 'type' => 'string' ),
					),
					'required'             => array( 'file' ),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					return CapabilityGate::check(
						self::ABILITY,
						'activate_plugins',
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function ( $input ) {
					$input = is_array( $input ) ? $input : array();
					$file  = (string) ( $input['file'] ?? '' );
					if ( '' === $file || str_contains( $file, '..' ) ) {
						return new \WP_Error( 'talaxie_plugin_invalid_file', __( 'Invalid plugin file.', 'talaxie-core' ), array( 'status' => 400 ) );
					}

					if ( ! function_exists( 'activate_plugin' ) ) {
						require_once ABSPATH . 'wp-admin/includes/plugin.php';
					}
					$result = activate_plugin( $file );
					if ( is_wp_error( $result ) ) {
						return $result;
					}

					return array(
						'file'   => $file,
						'active' => is_plugin_active( $file ),
					);
				},
				'meta'                => array(
					'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}
}
