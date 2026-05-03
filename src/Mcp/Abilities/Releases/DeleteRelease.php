<?php
/**
 * Delete a Talaxie release entry.
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
 * Delete a release. The bot is the owner of releases — capability is the
 * specialized delete_talaxie_releases granted to ai_bot, no sudo needed.
 */
final class DeleteRelease implements AbilityInterface {

	public const ABILITY = 'talaxie-core/releases-delete';

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
				'label'               => __( 'Delete release', 'talaxie-core' ),
				'description'         => __( 'Trash or permanently delete a Talaxie release entry.', 'talaxie-core' ),
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
						'delete_talaxie_releases',
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function ( $input ) {
					$input = is_array( $input ) ? $input : array();
					$id    = (int) ( $input['id'] ?? 0 );
					$force = ! empty( $input['force'] );
					$post  = $id > 0 ? get_post( $id ) : null;
					if ( ! $post instanceof \WP_Post || Release::POST_TYPE !== $post->post_type ) {
						return new \WP_Error( 'talaxie_release_not_found', __( 'No release matches that id.', 'talaxie-core' ), array( 'status' => 404 ) );
					}

					$result = $force ? wp_delete_post( $id, true ) : wp_trash_post( $id );
					if ( ! $result ) {
						return new \WP_Error( 'talaxie_release_delete_failed', __( 'Could not delete the release.', 'talaxie-core' ), array( 'status' => 500 ) );
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
