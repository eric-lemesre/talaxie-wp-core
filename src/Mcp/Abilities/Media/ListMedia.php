<?php
/**
 * List media library entries.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Media;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Lists media attachments. Capability: upload_files.
 */
final class ListMedia implements AbilityInterface {

	public const ABILITY = 'talaxie-core/media-list';

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
				'label'               => __( 'List media', 'talaxie-core' ),
				'description'         => __( 'List attachments in the media library. Supports pagination and mime filter.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'page'      => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page'  => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 20,
						),
						'mime_type' => array( 'type' => 'string' ),
						'_sudo'     => array( 'type' => 'string' ),
					),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					return CapabilityGate::check(
						self::ABILITY,
						'upload_files',
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function ( $input ) {
					$input    = is_array( $input ) ? $input : array();
					$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
					$per_page = max( 1, min( 100, (int) ( $input['per_page'] ?? 20 ) ) );

					$args = array(
						'post_type'      => 'attachment',
						'post_status'    => 'inherit',
						'posts_per_page' => $per_page,
						'paged'          => $page,
						'no_found_rows'  => false,
					);
					if ( isset( $input['mime_type'] ) && '' !== $input['mime_type'] ) {
						$args['post_mime_type'] = (string) $input['mime_type'];
					}

					$query = new \WP_Query( $args );

					$items = array();
					foreach ( $query->posts as $post ) {
						$items[] = array(
							'id'        => (int) $post->ID,
							'title'     => (string) $post->post_title,
							'mime_type' => (string) $post->post_mime_type,
							'url'       => (string) wp_get_attachment_url( $post->ID ),
							'date'      => (string) $post->post_date_gmt,
						);
					}

					return array(
						'items'       => $items,
						'page'        => $page,
						'per_page'    => $per_page,
						'total'       => (int) $query->found_posts,
						'total_pages' => (int) $query->max_num_pages,
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
