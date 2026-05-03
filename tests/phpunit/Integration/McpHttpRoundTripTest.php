<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests\Integration;

use Talaxie\Core\Mcp\Server;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Real HTTP MCP round-trip: initialize → tools/list → tools/call.
 *
 * Skipped automatically when mcp-adapter is not loaded (e.g. on CI).
 * Run locally where the adapter ships beside the plugin to validate
 * the wire format end-to-end.
 *
 * @group integration-mcp
 */
final class McpHttpRoundTripTest extends WP_UnitTestCase {

	private const SERVER_URL = '/mcp/' . Server::SERVER_PROD;

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->markTestSkipped( 'mcp-adapter not loaded; HTTP round-trip test only runs locally.' );
		}

		// The McpAdapter is a singleton with a static "already initialised"
		// flag and an internal $servers map. Other tests in the suite
		// recreate the global REST server, which discards the routes
		// registered on the previous instance. Reset both pieces via
		// reflection so this test re-fires mcp_adapter_init and our
		// routes are present again, without tripping duplicate-id guards.
		$adapter_class = new \ReflectionClass( \WP\MCP\Core\McpAdapter::class );
		$initialized   = $adapter_class->getProperty( 'initialized' );
		$initialized->setAccessible( true );
		$initialized->setValue( null, false );

		$servers = $adapter_class->getProperty( 'servers' );
		$servers->setAccessible( true );
		$servers->setValue( \WP\MCP\Core\McpAdapter::instance(), array() );

		$this->server = rest_get_server();
		do_action( 'rest_api_init' );

		// Re-firing mcp_adapter_init re-registers the adapter's own
		// discovery abilities by name. Their wp_abilities_api_init hook
		// does not fire again in the test runtime, so the registry can
		// log a missing-ability notice here. Tolerate it.
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );

		// Authenticate as admin so the transport check_permission() passes.
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
	}

	public function test_prod_server_route_is_registered(): void {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( self::SERVER_URL, $routes, 'talaxie-mcp-prod-server REST route is missing' );
	}

	public function test_initialize_returns_a_session_envelope(): void {
		$init = $this->jsonrpc(
			'initialize',
			array(
				'protocolVersion' => '2025-06-18',
				'capabilities'    => new \stdClass(),
				'clientInfo'      => array(
					'name'    => 'talaxie-phpunit',
					'version' => '0.1',
				),
			)
		);
		$this->assertSame( 200, $init['status'], 'initialize should return 200' );
		$body = $this->to_array( $init['body'] );
		$this->assertArrayHasKey( 'result', $body, 'initialize must return a JSON-RPC result envelope' );
		$result = $this->to_array( $body['result'] );
		$this->assertArrayHasKey( 'protocolVersion', $result );
		$this->assertArrayHasKey( 'serverInfo', $result );
	}

	public function test_tools_list_without_session_is_rejected(): void {
		// Hitting tools/list without first calling initialize / capturing
		// a session id must yield a JSON-RPC error rather than crashing.
		$list = $this->jsonrpc( 'tools/list', new \stdClass() );
		$this->assertGreaterThanOrEqual( 400, $list['status'] );
		$body = $this->to_array( $list['body'] );
		$this->assertArrayHasKey( 'error', $body );
	}

	/**
	 * Recursively cast stdClass into associative arrays so tests can use
	 * assertArrayHasKey on whatever the JSON-RPC response decodes to.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return mixed
	 */
	private function to_array( $value ) {
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = $this->to_array( $v );
			}
		}
		return $value;
	}

	/**
	 * Send a JSON-RPC request to the prod MCP server route.
	 *
	 * @param string                 $method  JSON-RPC method.
	 * @param object|array<mixed>    $params  Method params.
	 * @param string|null            $session Optional Mcp-Session-Id.
	 *
	 * @return array{status:int, headers:array<string,string>, body:mixed}
	 */
	private function jsonrpc( string $method, $params, ?string $session = null ): array {
		$req = new WP_REST_Request( 'POST', self::SERVER_URL );
		$req->add_header( 'Content-Type', 'application/json' );
		$req->add_header( 'Accept', 'application/json, text/event-stream' );
		if ( null !== $session ) {
			$req->add_header( 'Mcp-Session-Id', $session );
		}
		$req->set_body(
			(string) wp_json_encode(
				array(
					'jsonrpc' => '2.0',
					'id'      => 1,
					'method'  => $method,
					'params'  => $params,
				)
			)
		);

		$res = $this->server->dispatch( $req );

		return array(
			'status'  => $res->get_status(),
			'headers' => $res->get_headers(),
			'body'    => $res->get_data(),
		);
	}
}
