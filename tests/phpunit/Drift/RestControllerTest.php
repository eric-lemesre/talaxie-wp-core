<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests\Drift;

use Talaxie\Core\Mcp\Drift\RestController;
use Talaxie\Core\Roles\AiBotRole;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * @covers \Talaxie\Core\Mcp\Drift\RestController
 */
final class RestControllerTest extends WP_UnitTestCase {

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
		RestController::register_routes();
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	public function test_non_admin_is_forbidden(): void {
		$bot = self::factory()->user->create( array( 'role' => AiBotRole::ROLE ) );
		wp_set_current_user( $bot );

		$res = $this->server->dispatch( new WP_REST_Request( 'GET', '/talaxie-core/v1/mcp/abilities-on-server' ) );
		$this->assertGreaterThanOrEqual( 400, $res->get_status() );
	}

	public function test_admin_gets_environment_and_servers(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$res = $this->server->dispatch( new WP_REST_Request( 'GET', '/talaxie-core/v1/mcp/abilities-on-server' ) );
		$this->assertSame( 200, $res->get_status() );

		$body = $res->get_data();
		$this->assertArrayHasKey( 'environment_type', $body );
		$this->assertArrayHasKey( 'servers', $body );
		$this->assertArrayHasKey( 'drift', $body );
		$this->assertIsArray( $body['servers'] );
	}
}
