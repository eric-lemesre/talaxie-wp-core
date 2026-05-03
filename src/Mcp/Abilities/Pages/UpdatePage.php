<?php
/**
 * Update an existing page.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Pages;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Updates a page. Capability: edit_pages, plus per-post edit_post check.
 */
final class UpdatePage implements AbilityInterface {

	public const ABILITY = 'talaxie-core/pages-update';

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
				'label'               => __( 'Update page', 'talaxie-core' ),
				'description'         => __( 'Update an existing page (title, content, status, slug, parent, menu_order).', 'talaxie-core' ),
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
						'parent'     => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'menu_order' => array( 'type' => 'integer' ),
						'_sudo'      => array( 'type' => 'string' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					$gate = CapabilityGate::check(
						self::ABILITY,
						'edit_pages',
						is_array( $input ) ? $input : array()
					);
					if ( true !== $gate ) {
						return $gate;
					}
					$id = isset( $input['id'] ) ? (int) $input['id'] : 0;
					if ( $id <= 0 || ! current_user_can( 'edit_post', $id ) ) {
						return new \WP_Error( 'talaxie_page_edit_forbidden', __( 'You are not allowed to edit this page.', 'talaxie-core' ), array( 'status' => 403 ) );
					}
					return true;
				},
				'execute_callback'    => static function ( $input ) {
					$input = is_array( $input ) ? $input : array();
					$id    = (int) ( $input['id'] ?? 0 );
					$post  = $id > 0 ? get_post( $id ) : null;
					if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type ) {
						return new \WP_Error( 'talaxie_page_not_found', __( 'No page matches that id.', 'talaxie-core' ), array( 'status' => 404 ) );
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
					if ( array_key_exists( 'parent', $input ) ) {
						$update['post_parent'] = (int) $input['parent'];
					}
					if ( array_key_exists( 'menu_order', $input ) ) {
						$update['menu_order'] = (int) $input['menu_order'];
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
