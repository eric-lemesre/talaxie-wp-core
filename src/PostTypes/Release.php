<?php
/**
 * "Release" custom post type.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * A Talaxie release published by the community (binaries, changelog, status).
 */
final class Release {

	public const POST_TYPE = 'talaxie_release';

	/**
	 * Register the custom post type with WordPress.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = array(
			'name'                  => _x( 'Releases', 'post type general name', 'talaxie-core' ),
			'singular_name'         => _x( 'Release', 'post type singular name', 'talaxie-core' ),
			'menu_name'             => _x( 'Releases', 'admin menu', 'talaxie-core' ),
			'name_admin_bar'        => _x( 'Release', 'add new on admin bar', 'talaxie-core' ),
			'add_new'               => __( 'Add new', 'talaxie-core' ),
			'add_new_item'          => __( 'Add new release', 'talaxie-core' ),
			'new_item'              => __( 'New release', 'talaxie-core' ),
			'edit_item'             => __( 'Edit release', 'talaxie-core' ),
			'view_item'             => __( 'View release', 'talaxie-core' ),
			'all_items'             => __( 'All releases', 'talaxie-core' ),
			'search_items'          => __( 'Search releases', 'talaxie-core' ),
			'not_found'             => __( 'No release found.', 'talaxie-core' ),
			'not_found_in_trash'    => __( 'No release found in trash.', 'talaxie-core' ),
			'featured_image'        => __( 'Release artwork', 'talaxie-core' ),
			'set_featured_image'    => __( 'Set release artwork', 'talaxie-core' ),
			'remove_featured_image' => __( 'Remove release artwork', 'talaxie-core' ),
			'use_featured_image'    => __( 'Use as release artwork', 'talaxie-core' ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Talaxie public releases (binaries, changelogs, milestones).', 'talaxie-core' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rest_base'          => 'releases',
			'menu_position'      => 21,
			'menu_icon'          => 'dashicons-download',
			'capability_type'    => array( 'talaxie_release', 'talaxie_releases' ),
			'map_meta_cap'       => true,
			'has_archive'        => 'releases',
			'hierarchical'       => false,
			'rewrite'            => array(
				'slug'       => 'release',
				'with_front' => false,
			),
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
			'taxonomies'         => array( 'talaxie_component' ),
		);

		register_post_type( self::POST_TYPE, $args );
	}
}
