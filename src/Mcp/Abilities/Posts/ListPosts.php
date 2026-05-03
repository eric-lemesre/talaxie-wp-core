<?php
/**
 * List posts ability — paginated, status-aware.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Posts;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Lists posts visible to the calling user.
 *
 * Capability: edit_posts (granted to ai_bot, no sudo needed).
 */
final class ListPosts implements AbilityInterface {

	public const ABILITY = 'talaxie-core/posts-list';

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
				'label'               => __( 'List posts', 'talaxie-core' ),
				'description'         => __( 'List posts visible to the caller. Supports pagination, status, and search.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 20,
						),
						'status'   => array(
							'type'    => 'string',
							'enum'    => array( 'any', 'publish', 'draft', 'pending', 'private', 'future' ),
							'default' => 'publish',
						),
						'search'   => array( 'type' => 'string' ),
						'_sudo'    => array( 'type' => 'string' ),
					),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					return CapabilityGate::check(
						self::ABILITY,
						'edit_posts',
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function ( $input ) {
					$input    = is_array( $input ) ? $input : array();
					$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
					$per_page = max( 1, min( 100, (int) ( $input['per_page'] ?? 20 ) ) );
					$status   = isset( $input['status'] ) ? (string) $input['status'] : 'publish';
					$search   = isset( $input['search'] ) ? (string) $input['search'] : '';

					$query = new \WP_Query(
						array(
							'post_type'              => 'post',
							'post_status'            => 'any' === $status ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : $status,
							'posts_per_page'         => $per_page,
							'paged'                  => $page,
							's'                      => $search,
							'no_found_rows'          => false,
							'update_post_meta_cache' => false,
							'update_post_term_cache' => false,
						)
					);

					$items = array();
					foreach ( $query->posts as $post ) {
						$items[] = array(
							'id'      => (int) $post->ID,
							'title'   => (string) $post->post_title,
							'status'  => (string) $post->post_status,
							'slug'    => (string) $post->post_name,
							'date'    => (string) $post->post_date_gmt,
							'author'  => (int) $post->post_author,
							'excerpt' => wp_strip_all_tags( (string) $post->post_excerpt ),
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
					'annotations'  => array(
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
