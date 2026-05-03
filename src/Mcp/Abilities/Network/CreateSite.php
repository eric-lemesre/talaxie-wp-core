<?php
/**
 * Create a multisite network site.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Network;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Creates a site on the network. Capability: virtual super_admin.
 * Disabled on production.
 */
final class CreateSite implements AbilityInterface {

	public const ABILITY = 'talaxie-core/network-create-site';

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
				'label'               => __( 'Create site (multisite)', 'talaxie-core' ),
				'description'         => __( 'Create a new site on the network. Multisite + super_admin only, prod-blocked.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'domain'      => array( 'type' => 'string' ),
						'path'        => array(
							'type'    => 'string',
							'default' => '/',
						),
						'title'       => array( 'type' => 'string' ),
						'admin_email' => array(
							'type'   => 'string',
							'format' => 'email',
						),
						'_sudo'       => array( 'type' => 'string' ),
					),
					'required'             => array( 'domain', 'title' ),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					if ( ! is_multisite() ) {
						return new \WP_Error(
							'talaxie_network_unavailable',
							__( 'This ability requires multisite.', 'talaxie-core' ),
							array( 'status' => 412 )
						);
					}
					return CapabilityGate::check(
						self::ABILITY,
						CapabilityGate::VIRTUAL_SUPER_ADMIN,
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function ( $input ) {
					if ( ! is_multisite() || ! function_exists( 'wpmu_create_blog' ) ) {
						return new \WP_Error( 'talaxie_network_unavailable', __( 'Multisite is not active.', 'talaxie-core' ), array( 'status' => 412 ) );
					}

					$input  = is_array( $input ) ? $input : array();
					$domain = sanitize_text_field( (string) ( $input['domain'] ?? '' ) );
					$path   = (string) ( $input['path'] ?? '/' );
					$title  = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
					$admin  = isset( $input['admin_email'] ) ? sanitize_email( (string) $input['admin_email'] ) : (string) get_option( 'admin_email' );

					$user_id = email_exists( $admin );
					if ( ! $user_id ) {
						$user_id = get_current_user_id();
					}

					$site_id = wpmu_create_blog( $domain, $path, $title, (int) $user_id );
					if ( is_wp_error( $site_id ) ) {
						return $site_id;
					}

					return array(
						'site_id' => (int) $site_id,
						'domain'  => $domain,
						'path'    => $path,
					);
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}
}
