<?php
/**
 * Fetch a single post by ID.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Posts;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the full editable representation of a post.
 *
 * Capability: edit_posts (granted to ai_bot, no sudo needed).
 */
final class GetPost implements AbilityInterface {

	public const ABILITY = 'talaxie-core/posts-get';

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
				'label'               => __( 'Get post', 'talaxie-core' ),
				'description'         => __( 'Return a single post (id, title, content, status, meta).', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'_sudo' => array( 'type' => 'string' ),
					),
					'required'             => array( 'id' ),
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
					$input = is_array( $input ) ? $input : array();
					$id    = (int) ( $input['id'] ?? 0 );
					$post  = $id > 0 ? get_post( $id ) : null;

					if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
						return new \WP_Error(
							'talaxie_post_not_found',
							__( 'No post matches that id.', 'talaxie-core' ),
							array( 'status' => 404 )
						);
					}

					return array(
						'id'           => (int) $post->ID,
						'title'        => (string) $post->post_title,
						'content'      => (string) $post->post_content,
						'excerpt'      => (string) $post->post_excerpt,
						'status'       => (string) $post->post_status,
						'slug'         => (string) $post->post_name,
						'author'       => (int) $post->post_author,
						'date_gmt'     => (string) $post->post_date_gmt,
						'modified_gmt' => (string) $post->post_modified_gmt,
						'link'         => (string) get_permalink( $post ),
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
