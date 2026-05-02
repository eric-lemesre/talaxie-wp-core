<?php
/**
 * Fallback ability that forwards to wp-json/wp/v2/* endpoints.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities\Generic;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\CapabilityGate;

defined( 'ABSPATH' ) || exit;

/**
 * Generic REST proxy. Each HTTP method maps to a capability, all gated.
 *
 * GET    → read        (sudo not needed, ai_bot has read)
 * POST   → edit_posts  (ai_bot OK on /wp/v2/posts, etc.)
 * PUT    → edit_posts
 * PATCH  → edit_posts
 * DELETE → delete_posts (sudo)
 *
 * Disabled on production: it is the loophole that makes the surface
 * unbounded, so it stays a test-server-only escape hatch.
 */
final class RestCall implements AbilityInterface {

	public const ABILITY = 'talaxie-core/rest-call';

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
				'label'               => __( 'Forward to wp/v2 REST', 'talaxie-core' ),
				'description'         => __( 'Forward an HTTP method + path to /wp-json/wp/v2/*. Capability is mapped per HTTP method. Test server only.', 'talaxie-core' ),
				'category'            => 'talaxie-core',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'method' => array(
							'type'    => 'string',
							'enum'    => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ),
							'default' => 'GET',
						),
						'path'   => array(
							'type'        => 'string',
							'description' => __( 'Relative path inside wp/v2 (e.g. "posts/123" → /wp-json/wp/v2/posts/123).', 'talaxie-core' ),
						),
						'query'  => array( 'type' => 'object', 'additionalProperties' => true ),
						'body'   => array( 'type' => 'object', 'additionalProperties' => true ),
						'_sudo'  => array( 'type' => 'string' ),
					),
					'required'             => array( 'path' ),
					'additionalProperties' => false,
				),
				'permission_callback' => static function ( $input ) {
					$input  = is_array( $input ) ? $input : array();
					$method = strtoupper( (string) ( $input['method'] ?? 'GET' ) );
					$cap    = self::cap_for_method( $method );
					return CapabilityGate::check(
						self::ABILITY,
						$cap,
						$input
					);
				},
				'execute_callback'    => static function ( $input ) {
					$input  = is_array( $input ) ? $input : array();
					$method = strtoupper( (string) ( $input['method'] ?? 'GET' ) );
					$path   = ltrim( (string) ( $input['path'] ?? '' ), '/' );
					if ( '' === $path || str_contains( $path, '..' ) ) {
						return new \WP_Error( 'talaxie_rest_invalid_path', __( 'Invalid path.', 'talaxie-core' ), array( 'status' => 400 ) );
					}

					$req = new \WP_REST_Request( $method, '/wp/v2/' . $path );
					if ( isset( $input['query'] ) && is_array( $input['query'] ) ) {
						foreach ( $input['query'] as $k => $v ) {
							$req->set_query_params( array_merge( $req->get_query_params(), array( (string) $k => $v ) ) );
						}
					}
					if ( isset( $input['body'] ) && is_array( $input['body'] ) ) {
						$req->set_body_params( $input['body'] );
					}

					$res = rest_do_request( $req );
					return array(
						'status' => $res->get_status(),
						'data'   => $res->get_data(),
					);
				},
				'meta'                => array(
					'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Map an HTTP method to a capability.
	 *
	 * @param string $method HTTP verb.
	 *
	 * @return string Capability slug.
	 */
	private static function cap_for_method( string $method ): string {
		switch ( $method ) {
			case 'POST':
			case 'PUT':
			case 'PATCH':
				return 'edit_posts';
			case 'DELETE':
				return 'delete_posts';
			case 'GET':
			default:
				return 'read';
		}
	}
}
