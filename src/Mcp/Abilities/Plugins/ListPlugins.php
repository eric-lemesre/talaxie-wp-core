<?php
/**
 * List installed plugins.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Plugins;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Lists installed plugins. Capability: activate_plugins (sudo for ai_bot).
 */
final class ListPlugins implements AbilityInterface {

	public const ABILITY = 'talaxie-core/plugins-list';

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
				'label'               => __( 'List plugins', 'talaxie-core' ),
				'description'         => __( 'List installed WordPress plugins (file path, name, version, active state).', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'_sudo' => array( 'type' => 'string' ),
					),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					return CapabilityGate::check(
						self::ABILITY,
						'activate_plugins',
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function () {
					if ( ! function_exists( 'get_plugins' ) ) {
						require_once ABSPATH . 'wp-admin/includes/plugin.php';
					}
					$plugins = get_plugins();
					$active  = (array) get_option( 'active_plugins', array() );

					$items = array();
					foreach ( $plugins as $file => $data ) {
						$items[] = array(
							'file'        => (string) $file,
							'name'        => isset( $data['Name'] ) ? (string) $data['Name'] : '',
							'version'     => isset( $data['Version'] ) ? (string) $data['Version'] : '',
							'description' => isset( $data['Description'] ) ? wp_strip_all_tags( (string) $data['Description'] ) : '',
							'active'      => in_array( $file, $active, true ),
						);
					}

					return array( 'items' => $items );
				},
				'meta'                => array(
					'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}
}
