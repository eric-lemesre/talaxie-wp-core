<?php
/**
 * Read-only site introspection ability.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Site;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Returns a small, safe digest of the site (name, URL, environment type,
 * WP version). Available to any logged-in user — capability check is `read`.
 */
final class GetInfo implements AbilityInterface {

	public const ABILITY = 'talaxie-core/site-get-info';

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
				'label'               => __( 'Get site info', 'talaxie-core' ),
				'description'         => __( 'Return basic public information about the WordPress site (name, URL, environment, version).', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'name'        => array( 'type' => 'string' ),
						'url'         => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'environment' => array( 'type' => 'string' ),
						'wp_version'  => array( 'type' => 'string' ),
						'language'    => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => static function ( $input ) {
					return CapabilityGate::check(
						self::ABILITY,
						'read',
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function () {
					global $wp_version;

					return array(
						'name'        => (string) get_bloginfo( 'name' ),
						'url'         => (string) get_bloginfo( 'url' ),
						'description' => (string) get_bloginfo( 'description' ),
						'environment' => (string) wp_get_environment_type(),
						'wp_version'  => (string) $wp_version,
						'language'    => (string) get_locale(),
					);
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}
}
