<?php
/**
 * "Contributor" custom post type.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * A community contributor (GitHub login, role, organization).
 */
final class Contributor {

	public const POST_TYPE = 'talaxie_contributor';

	/**
	 * Register the custom post type with WordPress.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = array(
			'name'                  => _x( 'Contributors', 'post type general name', 'talaxie-core' ),
			'singular_name'         => _x( 'Contributor', 'post type singular name', 'talaxie-core' ),
			'menu_name'             => _x( 'Contributors', 'admin menu', 'talaxie-core' ),
			'name_admin_bar'        => _x( 'Contributor', 'add new on admin bar', 'talaxie-core' ),
			'add_new'               => __( 'Add new', 'talaxie-core' ),
			'add_new_item'          => __( 'Add new contributor', 'talaxie-core' ),
			'new_item'              => __( 'New contributor', 'talaxie-core' ),
			'edit_item'             => __( 'Edit contributor', 'talaxie-core' ),
			'view_item'             => __( 'View contributor', 'talaxie-core' ),
			'all_items'             => __( 'All contributors', 'talaxie-core' ),
			'search_items'          => __( 'Search contributors', 'talaxie-core' ),
			'not_found'             => __( 'No contributor found.', 'talaxie-core' ),
			'not_found_in_trash'    => __( 'No contributor found in trash.', 'talaxie-core' ),
			'featured_image'        => __( 'Avatar', 'talaxie-core' ),
			'set_featured_image'    => __( 'Set avatar', 'talaxie-core' ),
			'remove_featured_image' => __( 'Remove avatar', 'talaxie-core' ),
			'use_featured_image'    => __( 'Use as avatar', 'talaxie-core' ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Members of the Talaxie community recognized for their contributions.', 'talaxie-core' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rest_base'          => 'contributors',
			'menu_position'      => 22,
			'menu_icon'          => 'dashicons-groups',
			'capability_type'    => 'post',
			'has_archive'        => 'contributors',
			'hierarchical'       => false,
			'rewrite'            => array(
				'slug'       => 'contributor',
				'with_front' => false,
			),
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
		);

		register_post_type( self::POST_TYPE, $args );
	}
}
