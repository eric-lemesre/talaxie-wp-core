<?php
/**
 * Read-only ability that exposes the audit log.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Audit;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\Audit\AuditLogger;
use Talaxie\Core\Mcp\Audit\AuditPostType;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Lists recent audit entries. Capability: manage_options (sudo if needed).
 */
final class ListAudit implements AbilityInterface {

	public const ABILITY = 'talaxie-core/audit-list';

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
				'label'               => __( 'List MCP audit entries', 'talaxie-core' ),
				'description'         => __( 'Return the most recent MCP audit entries (allow/deny, capability, sudo usage). Requires manage_options.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 200,
							'default' => 50,
						),
						'allowed_only' => array( 'type' => 'boolean' ),
						'denied_only'  => array( 'type' => 'boolean' ),
						'_sudo'    => array( 'type' => 'string' ),
					),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					return CapabilityGate::check(
						self::ABILITY,
						'manage_options',
						is_array( $input ) ? $input : array()
					);
				},
				'execute_callback'    => static function ( $input ) {
					$input    = is_array( $input ) ? $input : array();
					$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
					$per_page = max( 1, min( 200, (int) ( $input['per_page'] ?? 50 ) ) );

					$meta_query = array();
					if ( ! empty( $input['allowed_only'] ) ) {
						$meta_query[] = array(
							'key'   => AuditLogger::META_ALLOWED,
							'value' => '1',
						);
					}
					if ( ! empty( $input['denied_only'] ) ) {
						$meta_query[] = array(
							'key'   => AuditLogger::META_ALLOWED,
							'value' => '0',
						);
					}

					$query = new \WP_Query(
						array(
							'post_type'      => AuditPostType::POST_TYPE,
							'post_status'    => 'any',
							'posts_per_page' => $per_page,
							'paged'          => $page,
							'orderby'        => 'date',
							'order'          => 'DESC',
							'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							'no_found_rows'  => false,
						)
					);

					$items = array();
					foreach ( $query->posts as $post ) {
						$items[] = array(
							'id'         => (int) $post->ID,
							'date_gmt'   => (string) $post->post_date_gmt,
							'ability'    => (string) get_post_meta( $post->ID, AuditLogger::META_ABILITY, true ),
							'capability' => (string) get_post_meta( $post->ID, AuditLogger::META_CAPABILITY, true ),
							'user_id'    => (int) get_post_meta( $post->ID, AuditLogger::META_USER, true ),
							'allowed'    => '1' === (string) get_post_meta( $post->ID, AuditLogger::META_ALLOWED, true ),
							'sudo_used'  => '1' === (string) get_post_meta( $post->ID, AuditLogger::META_SUDO_USED, true ),
							'ip'         => (string) get_post_meta( $post->ID, AuditLogger::META_IP, true ),
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
					'annotations' => array(
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
