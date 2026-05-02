<?php
/**
 * Custom post type used to persist MCP audit events.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the talaxie_mcp_audit CPT lifecycle.
 *
 * The CPT is non-public — entries are listable from wp-admin only by
 * users with manage_options. It is purposefully terse: each row stores
 * one MCP request and the surrounding decision (allowed / denied,
 * sudo used, capability requested, etc.).
 */
final class AuditPostType {

	public const POST_TYPE = 'talaxie_mcp_audit';

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => _x( 'MCP audit', 'post type general name', 'talaxie-core' ),
					'singular_name' => _x( 'MCP audit entry', 'post type singular name', 'talaxie-core' ),
					'menu_name'     => _x( 'MCP audit', 'admin menu', 'talaxie-core' ),
					'all_items'     => __( 'All audit entries', 'talaxie-core' ),
					'search_items'  => __( 'Search audit entries', 'talaxie-core' ),
					'not_found'     => __( 'No audit entry found.', 'talaxie-core' ),
				),
				'description'         => __( 'MCP audit log — every ability invocation captured by the capability gate.', 'talaxie-core' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => 'tools.php',
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'exclude_from_search' => true,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'capabilities'        => array(
					'create_posts' => 'do_not_allow',
				),
				'supports'            => array( 'title', 'custom-fields' ),
			)
		);
	}
}
