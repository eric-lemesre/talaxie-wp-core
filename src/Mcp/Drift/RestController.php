<?php
/**
 * Drift detection endpoint — exposes what is REALLY on each MCP server.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Drift;

use Talaxie\Core\Mcp\Server;
use WP\MCP\Core\McpAdapter;

defined( 'ABSPATH' ) || exit;

/**
 * GET /wp-json/talaxie-core/v1/mcp/abilities-on-server
 *
 * Walks the McpAdapter registry, lists tools per server and cross-checks
 * each tool against `is_allowed_on_production()` so an operator can spot
 * an ability leaking onto the prod server through a config override.
 *
 * Capability: manage_options.
 */
final class RestController {

	public const NAMESPACE = 'talaxie-core/v1';
	public const ROUTE     = '/mcp/abilities-on-server';

	/**
	 * Hook into rest_api_init.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Register the GET route.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'handle' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Build the report.
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle(): \WP_REST_Response {
		$report = array(
			'environment_type' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'unknown',
			'servers'          => array(),
			'drift'            => array(),
		);

		$prod_safe_names = array();
		foreach ( Server::abilities() as $class ) {
			if ( $class::is_allowed_on_production() ) {
				$prod_safe_names[] = $class::name();
			}
		}

		if ( class_exists( McpAdapter::class ) ) {
			$adapter = McpAdapter::instance();
			foreach ( $adapter->get_servers() as $server_id => $server ) {
				$tools_array = method_exists( $server, 'get_tools' ) ? $server->get_tools() : array();
				$tool_names  = array();
				foreach ( $tools_array as $tool ) {
					if ( is_object( $tool ) && method_exists( $tool, 'get_name' ) ) {
						$tool_names[] = (string) $tool->get_name();
					} elseif ( is_string( $tool ) ) {
						$tool_names[] = $tool;
					}
				}

				$report['servers'][] = array(
					'id'    => (string) $server_id,
					'tools' => array_values( array_unique( $tool_names ) ),
				);

				if ( Server::SERVER_PROD === $server_id ) {
					$leaks = array_values( array_diff( $tool_names, $prod_safe_names ) );
					if ( ! empty( $leaks ) ) {
						$report['drift'][] = array(
							'server' => $server_id,
							'kind'   => 'prod_leak',
							'tools'  => $leaks,
							'note'   => __( 'These tools are exposed on the production server but their abilities declare is_allowed_on_production() = false.', 'talaxie-core' ),
						);
					}
				}
			}
		}

		return new \WP_REST_Response( $report, 200 );
	}
}
