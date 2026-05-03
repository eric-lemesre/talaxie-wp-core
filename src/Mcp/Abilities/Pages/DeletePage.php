<?php
/**
 * Delete a page. Capability: delete_pages (sudo required for ai_bot).
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Pages;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Deletes a page. Defaults to trash; supports force=true for permanent
 * removal. Capability: delete_pages, which the ai_bot role does not own —
 * a sudo token is required.
 *
 * Disabled on production servers.
 */
final class DeletePage implements AbilityInterface {

	public const ABILITY = 'talaxie-core/pages-delete';

	public static function name(): string {
		return self::ABILITY;
	}

	public static function is_allowed_on_production(): bool {
		return false;
	}

	public static function register(): void {
		wp_register_ability(
			self::ABILITY,
			array(
				'label'               => __( 'Delete page', 'talaxie-core' ),
				'description'         => __( 'Move a page to trash, or permanently delete it. Requires delete_pages (sudo token).', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'force' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'_sudo' => array( 'type' => 'string' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					return CapabilityGate::check(
						self::ABILITY,
						'delete_pages',
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function ( $input ) {
					$input = is_array( $input ) ? $input : array();
					$id    = (int) ( $input['id'] ?? 0 );
					$force = ! empty( $input['force'] );
					$post  = $id > 0 ? get_post( $id ) : null;
					if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type ) {
						return new \WP_Error( 'talaxie_page_not_found', __( 'No page matches that id.', 'talaxie-core' ), array( 'status' => 404 ) );
					}

					$result = $force ? wp_delete_post( $id, true ) : wp_trash_post( $id );
					if ( ! $result ) {
						return new \WP_Error( 'talaxie_page_delete_failed', __( 'Could not delete the page.', 'talaxie-core' ), array( 'status' => 500 ) );
					}

					return array(
						'id'      => $id,
						'deleted' => true,
						'force'   => $force,
					);
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}
}
