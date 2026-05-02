<?php
/**
 * Create a new page.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Pages;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Inserts a draft (or pending) page. Capability: edit_pages.
 */
final class CreatePage implements AbilityInterface {

	public const ABILITY = 'talaxie-core/pages-create';

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
				'label'               => __( 'Create page', 'talaxie-core' ),
				'description'         => __( 'Create a new page (defaults to draft). Requires edit_pages.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'     => array( 'type' => 'string' ),
						'content'   => array( 'type' => 'string' ),
						'excerpt'   => array( 'type' => 'string' ),
						'status'    => array( 'type' => 'string', 'enum' => array( 'draft', 'pending' ), 'default' => 'draft' ),
						'slug'      => array( 'type' => 'string' ),
						'parent'    => array( 'type' => 'integer', 'minimum' => 0 ),
						'menu_order'=> array( 'type' => 'integer' ),
						'_sudo'     => array( 'type' => 'string' ),
					),
					'required'             => array( 'title' ),
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
					$input  = is_array( $input ) ? $input : array();
					$status = (string) ( $input['status'] ?? 'draft' );
					if ( ! in_array( $status, array( 'draft', 'pending' ), true ) ) {
						$status = 'draft';
					}

					$args = array(
						'post_type'    => 'page',
						'post_title'   => isset( $input['title'] ) ? wp_strip_all_tags( (string) $input['title'] ) : '',
						'post_content' => isset( $input['content'] ) ? (string) $input['content'] : '',
						'post_excerpt' => isset( $input['excerpt'] ) ? (string) $input['excerpt'] : '',
						'post_status'  => $status,
						'post_name'    => isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : '',
						'post_author'  => get_current_user_id(),
					);
					if ( isset( $input['parent'] ) ) {
						$args['post_parent'] = (int) $input['parent'];
					}
					if ( isset( $input['menu_order'] ) ) {
						$args['menu_order'] = (int) $input['menu_order'];
					}

					$post_id = wp_insert_post( $args, true );
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
					'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}
}
