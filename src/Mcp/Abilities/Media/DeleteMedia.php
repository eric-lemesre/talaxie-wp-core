<?php
/**
 * Delete a media attachment. Capability: delete_posts (sudo for ai_bot).
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Media;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Permanently deletes an attachment, including its files on disk.
 *
 * Hidden from the production MCP server.
 */
final class DeleteMedia implements AbilityInterface {

	public const ABILITY = 'talaxie-core/media-delete';

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
				'label'               => __( 'Delete media', 'talaxie-core' ),
				'description'         => __( 'Permanently delete an attachment and its files. Requires delete_posts (sudo token).', 'talaxie-core' ),
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
						'delete_posts',
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function ( $input ) {
					$input = is_array( $input ) ? $input : array();
					$id    = (int) ( $input['id'] ?? 0 );
					$post  = $id > 0 ? get_post( $id ) : null;
					if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
						return new \WP_Error( 'talaxie_media_not_found', __( 'No attachment matches that id.', 'talaxie-core' ), array( 'status' => 404 ) );
					}

					$result = wp_delete_attachment( $id, true );
					if ( false === $result || null === $result ) {
						return new \WP_Error( 'talaxie_media_delete_failed', __( 'Could not delete the attachment.', 'talaxie-core' ), array( 'status' => 500 ) );
					}

					return array(
						'id'      => $id,
						'deleted' => true,
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
