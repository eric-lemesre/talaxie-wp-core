<?php
/**
 * Create a Talaxie release entry.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Releases;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;
use Talaxie\Core\PostTypes\Release;

defined( 'ABSPATH' ) || exit;

final class CreateRelease implements AbilityInterface {

	public const ABILITY = 'talaxie-core/releases-create';

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
				'label'               => __( 'Create release', 'talaxie-core' ),
				'description'         => __( 'Create a Talaxie release. The bot can publish directly (publish_talaxie_releases is granted to ai_bot).', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'      => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'string' ),
						'excerpt'    => array( 'type' => 'string' ),
						'status'     => array(
							'type'    => 'string',
							'enum'    => array( 'draft', 'pending', 'publish', 'private' ),
							'default' => 'draft',
						),
						'slug'       => array( 'type' => 'string' ),
						'components' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'_sudo'      => array( 'type' => 'string' ),
					),
					'required'             => array( 'title' ),
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
					$input  = is_array( $input ) ? $input : array();
					$status = (string) ( $input['status'] ?? 'draft' );
					if ( ! in_array( $status, array( 'draft', 'pending', 'publish', 'private' ), true ) ) {
						$status = 'draft';
					}

					$post_id = wp_insert_post(
						array(
							'post_type'    => Release::POST_TYPE,
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

					if ( isset( $input['components'] ) && is_array( $input['components'] ) ) {
						$slugs = array_map( 'sanitize_title', array_map( 'strval', $input['components'] ) );
						wp_set_post_terms( (int) $post_id, $slugs, 'talaxie_component', false );
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
					'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}
}
