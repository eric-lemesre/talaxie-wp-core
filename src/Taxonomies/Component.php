<?php
/**
 * "Component" taxonomy.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Taxonomies;

defined( 'ABSPATH' ) || exit;

use Talaxie\Core\PostTypes\Release;

/**
 * Talaxie components (tCore, tDB*, tFile*, tFlow*, ...) attached to releases.
 */
final class Component {

	public const TAXONOMY = 'talaxie_component';

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = array(
			'name'          => _x( 'Components', 'taxonomy general name', 'talaxie-core' ),
			'singular_name' => _x( 'Component', 'taxonomy singular name', 'talaxie-core' ),
			'search_items'  => __( 'Search components', 'talaxie-core' ),
			'all_items'     => __( 'All components', 'talaxie-core' ),
			'edit_item'     => __( 'Edit component', 'talaxie-core' ),
			'update_item'   => __( 'Update component', 'talaxie-core' ),
			'add_new_item'  => __( 'Add new component', 'talaxie-core' ),
			'new_item_name' => __( 'New component name', 'talaxie-core' ),
			'menu_name'     => __( 'Components', 'talaxie-core' ),
		);

		$args = array(
			'labels'            => $labels,
			'description'       => __( 'Talaxie components (e.g. tCore, tDBInput, tFileInputDelimited).', 'talaxie-core' ),
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'components',
			'hierarchical'      => false,
			'rewrite'           => array(
				'slug'       => 'component',
				'with_front' => false,
			),
		);

		register_taxonomy( self::TAXONOMY, array( Release::POST_TYPE ), $args );
	}
}
