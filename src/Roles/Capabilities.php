<?php
/**
 * Specialised capabilities introduced by talaxie-core.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Grants/revokes the talaxie_release-specific capabilities used by the
 * Release CPT (capability_type => 'talaxie_release').
 *
 * Administrators and editors must own these explicitly, otherwise
 * map_meta_cap would silently lock them out of the CPT.
 */
final class Capabilities {

	/**
	 * Capabilities required to drive the talaxie_release CPT end-to-end.
	 *
	 * @return list<string>
	 */
	public static function release_caps(): array {
		return array(
			'edit_talaxie_release',
			'edit_talaxie_releases',
			'edit_published_talaxie_releases',
			'edit_others_talaxie_releases',
			'edit_private_talaxie_releases',
			'publish_talaxie_releases',
			'delete_talaxie_release',
			'delete_talaxie_releases',
			'delete_published_talaxie_releases',
			'delete_others_talaxie_releases',
			'delete_private_talaxie_releases',
			'read_private_talaxie_releases',
		);
	}

	/**
	 * Add the talaxie release capabilities to the appropriate WP roles.
	 *
	 * Idempotent — safe to call from the activation hook on every load.
	 *
	 * @return void
	 */
	public static function grant_release_caps(): void {
		$targets = array( 'administrator', 'editor' );
		foreach ( $targets as $role_slug ) {
			$role = get_role( $role_slug );
			if ( ! $role instanceof \WP_Role ) {
				continue;
			}
			foreach ( self::release_caps() as $cap ) {
				if ( ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Remove the talaxie release capabilities from every role. Used on uninstall.
	 *
	 * @return void
	 */
	public static function revoke_release_caps(): void {
		$roles = wp_roles();
		foreach ( $roles->role_objects as $role ) {
			foreach ( self::release_caps() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
