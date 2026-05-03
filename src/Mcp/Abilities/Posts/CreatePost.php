<?php
/**
 * Create a new post.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Posts;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Inserts a draft (or pending) post on behalf of the agent.
 *
 * Capability: edit_posts. Publishing requires `publish_posts`, which the
 * ai_bot role does not have — the post is saved as draft by default.
 */
final class CreatePost implements AbilityInterface {

	public const ABILITY = 'talaxie-core/posts-create';

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
				'label'               => __( 'Create post', 'talaxie-core' ),
				'description'         => __( 'Create a new post (defaults to draft). Requires edit_posts.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
						'excerpt' => array( 'type' => 'string' ),
						'status'  => array(
							'type'    => 'string',
							'enum'    => array( 'draft', 'pending' ),
							'default' => 'draft',
						),
						'slug'    => array( 'type' => 'string' ),
						'_sudo'   => array( 'type' => 'string' ),
					),
					'required'             => array( 'title' ),
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
					$input  = is_array( $input ) ? $input : array();
					$status = (string) ( $input['status'] ?? 'draft' );
					if ( ! in_array( $status, array( 'draft', 'pending' ), true ) ) {
						$status = 'draft';
					}

					$post_id = wp_insert_post(
						array(
							'post_type'    => 'post',
							'post_title'   => isset( $input['title'] ) ? wp_strip_all_tags( (string) $input['title'] ) : '',
							'post_content' => isset( $input['content'] ) ? (string) $input['content'] : '',
							'post_excerpt' => isset( $input['excerpt'] ) ? (string) $input['excerpt'] : '',
							'post_status'  => $status,
							'post_name'    => isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : '',
							'post_author'  => get_current_user_id(),
						),
						true
					);

					if ( is_wp_error( $post_id ) ) {
						return $post_id;
					}

					$post = get_post( (int) $post_id );

					return array(
						'id'     => (int) $post_id,
						'status' => $post instanceof \WP_Post ? (string) $post->post_status : $status,
						'slug'   => $post instanceof \WP_Post ? (string) $post->post_name : '',
						'link'   => (string) get_permalink( (int) $post_id ),
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
