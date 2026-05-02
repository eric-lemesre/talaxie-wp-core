<?php
/**
 * REST endpoints for sudo token issuance and revocation.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Sudo;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes /talaxie-core/v1/mcp/sudo-token (POST + GET + DELETE).
 *
 * Caller must hold manage_options. The cleartext token is returned only
 * on POST — it is never persisted in plaintext, so a repeat GET cannot
 * recover it.
 */
final class RestController {

	public const NAMESPACE = 'talaxie-core/v1';
	public const ROUTE     = '/mcp/sudo-token';

	/**
	 * Hook the controller into WordPress.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Register the REST routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'handle_list' ),
					'permission_callback' => array( self::class, 'permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'handle_create' ),
					'permission_callback' => array( self::class, 'permission' ),
					'args'                => array(
						'scope'      => array(
							'required'    => true,
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Capabilities the token grants.', 'talaxie-core' ),
						),
						'ttl'        => array(
							'type'        => 'integer',
							'minimum'     => 60,
							'description' => __( 'Lifetime in seconds (capped server-side).', 'talaxie-core' ),
						),
						'single_use' => array(
							'type' => 'boolean',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			self::ROUTE . '/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( self::class, 'handle_revoke' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			self::ROUTE . '/all',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( self::class, 'handle_revoke_all' ),
				'permission_callback' => array( self::class, 'permission' ),
			)
		);
	}

	/**
	 * Capability check for the controller.
	 *
	 * @return bool|\WP_Error
	 */
	public static function permission() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return new \WP_Error(
			'rest_forbidden',
			__( 'manage_options is required to manage sudo tokens.', 'talaxie-core' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * GET — list active tokens.
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle_list(): \WP_REST_Response {
		return new \WP_REST_Response( array( 'tokens' => TokenManager::list_active() ), 200 );
	}

	/**
	 * POST — create a token.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_create( \WP_REST_Request $request ) {
		$scope      = (array) $request->get_param( 'scope' );
		$scope      = array_values( array_filter( array_map( 'strval', $scope ), 'strlen' ) );
		$ttl        = (int) ( $request->get_param( 'ttl' ) ?? TokenManager::DEFAULT_TTL );
		$single_use = (bool) $request->get_param( 'single_use' );

		$result = TokenManager::create( $scope, $ttl, $single_use );
		if ( $result instanceof \WP_Error ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 201 );
	}

	/**
	 * DELETE /id — revoke a single token.
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle_revoke( \WP_REST_Request $request ): \WP_REST_Response {
		$revoked = TokenManager::revoke( (int) $request->get_param( 'id' ) );
		return new \WP_REST_Response( array( 'revoked' => $revoked ), $revoked ? 200 : 404 );
	}

	/**
	 * DELETE /all — revoke every active token.
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle_revoke_all(): \WP_REST_Response {
		return new \WP_REST_Response( array( 'revoked' => TokenManager::revoke_all() ), 200 );
	}
}
