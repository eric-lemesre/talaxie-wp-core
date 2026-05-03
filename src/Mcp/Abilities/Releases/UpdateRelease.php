<?php
/**
 * Update a Talaxie release entry.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Releases;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;
use Talaxie\Core\PostTypes\Release;

defined( 'ABSPATH' ) || exit;

/**
 * Update an existing talaxie_release entry. Capability: edit_talaxie_releases.
 */
final class UpdateRelease implements AbilityInterface {

	public const ABILITY = 'talaxie-core/releases-update';

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
				'label'               => __( 'Update release', 'talaxie-core' ),
				'description'         => __( 'Update an existing Talaxie release entry.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'         => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'title'      => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'string' ),
						'excerpt'    => array( 'type' => 'string' ),
						'status'     => array(
							'type' => 'string',
							'enum' => array( 'draft', 'pending', 'publish', 'private' ),
						),
						'slug'       => array( 'type' => 'string' ),
						'components' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'_sudo'      => array( 'type' => 'string' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					return CapabilityGate::check(
						self::ABILITY,
						'edit_talaxie_releases',
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function ( $input ) {
					$input = is_array( $input ) ? $input : array();
					$id    = (int) ( $input['id'] ?? 0 );
					$post  = $id > 0 ? get_post( $id ) : null;
					if ( ! $post instanceof \WP_Post || Release::POST_TYPE !== $post->post_type ) {
						return new \WP_Error( 'talaxie_release_not_found', __( 'No release matches that id.', 'talaxie-core' ), array( 'status' => 404 ) );
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

					if ( isset( $input['components'] ) && is_array( $input['components'] ) ) {
						$slugs = array_map( 'sanitize_title', array_map( 'strval', $input['components'] ) );
						wp_set_post_terms( $id, $slugs, 'talaxie_component', false );
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
