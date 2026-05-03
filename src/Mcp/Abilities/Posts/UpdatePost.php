<?php
/**
 * Update an existing post.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Posts;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Updates a post the caller is allowed to edit.
 *
 * Capability: edit_posts. The post-level `edit_post` meta-cap is
 * re-checked on the target id, so the ai_bot role cannot edit posts
 * locked to another user via plugin-level overrides.
 */
final class UpdatePost implements AbilityInterface {

	public const ABILITY = 'talaxie-core/posts-update';

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
				'label'               => __( 'Update post', 'talaxie-core' ),
				'description'         => __( 'Update an existing post (title, content, excerpt, status, slug). Requires edit_posts.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'      => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
						'excerpt' => array( 'type' => 'string' ),
						'status'  => array(
							'type' => 'string',
							'enum' => array( 'draft', 'pending', 'publish', 'private' ),
						),
						'slug'    => array( 'type' => 'string' ),
						'_sudo'   => array( 'type' => 'string' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					$gate = CapabilityGate::check(
						self::ABILITY,
						'edit_posts',
						is_array( $input ) ? $input : array()
					);
					if ( true !== $gate ) {
						return $gate;
					}

					$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
					if ( $id <= 0 || ! current_user_can( 'edit_post', $id ) ) {
						return new \WP_Error(
							'talaxie_post_edit_forbidden',
							__( 'You are not allowed to edit this post.', 'talaxie-core' ),
							array( 'status' => 403 )
						);
					}

					return true;
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

					$update = array( 'ID' => $id );
					if ( array_key_exists( 'title', $input ) ) {
						$update['post_title'] = wp_strip_all_tags( (string) $input['title'] );
					}
					if ( array_key_exists( 'content', $input ) ) {
						$update['post_content'] = (string) $input['content'];
					}
					if ( array_key_exists( 'excerpt', $input ) ) {
						$update['post_excerpt'] = (string) $input['excerpt'];
					}
					if ( array_key_exists( 'status', $input ) ) {
						$update['post_status'] = (string) $input['status'];
					}
					if ( array_key_exists( 'slug', $input ) ) {
						$update['post_name'] = sanitize_title( (string) $input['slug'] );
					}

					$result = wp_update_post( $update, true );
					if ( is_wp_error( $result ) ) {
						return $result;
					}

					$updated = get_post( (int) $result );

					return array(
						'id'     => (int) $result,
						'status' => $updated instanceof \WP_Post ? (string) $updated->post_status : '',
						'slug'   => $updated instanceof \WP_Post ? (string) $updated->post_name : '',
						'link'   => (string) get_permalink( (int) $result ),
					);
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}
}
