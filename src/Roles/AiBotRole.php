<?php
/**
 * The "ai_bot" WordPress role used by AI agents calling MCP abilities.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight WordPress role given to the application user that AI agents
 * authenticate as. Capabilities are intentionally narrow: anything beyond
 * publishing content requires a sudo token (see Mcp\Sudo\TokenManager).
 */
final class AiBotRole {

	public const ROLE = 'ai_bot';

	/**
	 * Capabilities granted to the role at registration time.
	 *
	 * @return array<string, bool>
	 */
	public static function capabilities(): array {
		return array(
			'read'                          => true,
			'edit_posts'                    => true,
			'edit_published_posts'          => true,
			'edit_pages'                    => true,
			'edit_published_pages'          => true,
			'upload_files'                  => true,
			'read_private_posts'            => true,
			'edit_talaxie_release'          => true,
			'edit_talaxie_releases'         => true,
			'edit_published_talaxie_release'=> true,
			'edit_talaxie_contributor'      => true,
			'edit_talaxie_contributors'     => true,
			'edit_published_talaxie_contributor' => true,
			'read_private_talaxie_release'  => true,
			'read_private_talaxie_contributor' => true,
		);
	}

	/**
	 * Add the role if it is not already present.
	 *
	 * Idempotent: safe to call on every plugin activation.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( get_role( self::ROLE ) instanceof \WP_Role ) {
			return;
		}

		add_role(
			self::ROLE,
			__( 'AI Bot', 'talaxie-core' ),
			self::capabilities()
		);
	}

	/**
	 * Remove the role. Called from uninstall.php.
	 *
	 * @return void
	 */
	public static function unregister(): void {
		remove_role( self::ROLE );
	}
}
