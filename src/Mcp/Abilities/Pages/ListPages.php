<?php
/**
 * List pages ability.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Pages;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Lists pages visible to the calling user. Capability: edit_pages.
 */
final class ListPages implements AbilityInterface {

	public const ABILITY = 'talaxie-core/pages-list';

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
				'label'               => __( 'List pages', 'talaxie-core' ),
				'description'         => __( 'List pages. Supports pagination and status filter.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
						'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
						'status'   => array(
							'type'    => 'string',
							'enum'    => array( 'any', 'publish', 'draft', 'pending', 'private' ),
							'default' => 'publish',
						),
						'search'   => array( 'type' => 'string' ),
						'parent'   => array( 'type' => 'integer', 'minimum' => 0 ),
						'_sudo'    => array( 'type' => 'string' ),
					),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					return CapabilityGate::check(
						self::ABILITY,
						'edit_pages',
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function ( $input ) {
					$input    = is_array( $input ) ? $input : array();
					$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
					$per_page = max( 1, min( 100, (int) ( $input['per_page'] ?? 20 ) ) );
					$status   = isset( $input['status'] ) ? (string) $input['status'] : 'publish';

					$args = array(
						'post_type'      => 'page',
						'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'pending', 'private' ) : $status,
						'posts_per_page' => $per_page,
						'paged'          => $page,
						's'              => isset( $input['search'] ) ? (string) $input['search'] : '',
						'no_found_rows'  => false,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					);
					if ( isset( $input['parent'] ) ) {
						$args['post_parent'] = (int) $input['parent'];
					}

					$query = new \WP_Query( $args );

					$items = array();
					foreach ( $query->posts as $post ) {
						$items[] = array(
							'id'      => (int) $post->ID,
							'title'   => (string) $post->post_title,
							'status'  => (string) $post->post_status,
							'slug'    => (string) $post->post_name,
							'parent'  => (int) $post->post_parent,
							'date'    => (string) $post->post_date_gmt,
							'menu_order' => (int) $post->menu_order,
						);
					}

					return array(
						'items'       => $items,
						'page'        => $page,
						'per_page'    => $per_page,
						'total'       => (int) $query->found_posts,
						'total_pages' => (int) $query->max_num_pages,
					);
				},
				'meta'                => array(
					'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}
}
