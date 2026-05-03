<?php
/**
 * Fetch a single talaxie_release entry.
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
 * Return one talaxie_release entry by id. Capability: edit_talaxie_releases.
 */
final class GetRelease implements AbilityInterface {

	public const ABILITY = 'talaxie-core/releases-get';

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
				'label'               => __( 'Get release', 'talaxie-core' ),
				'description'         => __( 'Return a single Talaxie release entry.', 'talaxie-core' ),
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

					$components = wp_get_post_terms( $post->ID, 'talaxie_component', array( 'fields' => 'slugs' ) );

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
						'components'   => is_array( $components ) ? array_values( $components ) : array(),
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
