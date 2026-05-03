<?php
/**
 * Delete a multisite network site.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Network;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Deletes a site from the network. Capability: virtual super_admin.
 * Disabled on production.
 */
final class DeleteSite implements AbilityInterface {

	public const ABILITY = 'talaxie-core/network-delete-site';

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
				'label'               => __( 'Delete site (multisite)', 'talaxie-core' ),
				'description'         => __( 'Delete a site from the network, including its files. Multisite + super_admin only, prod-blocked.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'site_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'drop'    => array(
							'type'        => 'boolean',
							'description' => __( 'Also drop the site database tables.', 'talaxie-core' ),
							'default'     => false,
						),
						'_sudo'   => array( 'type' => 'string' ),
					),
					'required'             => array( 'site_id' ),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					if ( ! is_multisite() ) {
						return new \WP_Error( 'talaxie_network_unavailable', __( 'This ability requires multisite.', 'talaxie-core' ), array( 'status' => 412 ) );
					}
					return CapabilityGate::check(
						self::ABILITY,
						CapabilityGate::VIRTUAL_SUPER_ADMIN,
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function ( $input ) {
					if ( ! is_multisite() || ! function_exists( 'wpmu_delete_blog' ) ) {
						return new \WP_Error( 'talaxie_network_unavailable', __( 'Multisite is not active.', 'talaxie-core' ), array( 'status' => 412 ) );
					}
					$input   = is_array( $input ) ? $input : array();
					$site_id = (int) ( $input['site_id'] ?? 0 );
					$drop    = ! empty( $input['drop'] );
					if ( $site_id <= 1 ) {
						return new \WP_Error( 'talaxie_network_invalid_site', __( 'Refusing to delete the main site (id 1).', 'talaxie-core' ), array( 'status' => 400 ) );
					}

					wpmu_delete_blog( $site_id, $drop );

					return array(
						'site_id' => $site_id,
						'deleted' => true,
						'drop'    => $drop,
					);
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}
}
