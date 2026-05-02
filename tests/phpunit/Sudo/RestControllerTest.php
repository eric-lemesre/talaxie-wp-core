<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests\Sudo;

use Talaxie\Core\Mcp\Sudo\RestController;
use Talaxie\Core\Mcp\Sudo\TokenManager;
use Talaxie\Core\Mcp\Sudo\TokenSchema;
use Talaxie\Core\Roles\AiBotRole;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * @covers \Talaxie\Core\Mcp\Sudo\RestController
 */
final class RestControllerTest extends WP_UnitTestCase {

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		global $wpdb, $wp_rest_server;
		$wpdb->query( 'TRUNCATE TABLE ' . TokenSchema::table_name() ); // phpcs:ignore

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

		$req = new WP_REST_Request( 'POST', '/talaxie-core/v1/mcp/sudo-token' );
		$req->set_param( 'scope', array( 'manage_options' ) );

		$res = $this->server->dispatch( $req );
		$this->assertSame( 403, $res->get_status() );
	}

	public function test_admin_can_create_then_list_then_revoke(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$create = new WP_REST_Request( 'POST', '/talaxie-core/v1/mcp/sudo-token' );
		$create->set_param( 'scope', array( 'manage_options' ) );
		$create->set_param( 'ttl', 600 );
		$create_res = $this->server->dispatch( $create );

		$this->assertSame( 201, $create_res->get_status() );
		$payload = $create_res->get_data();
		$this->assertArrayHasKey( 'token', $payload );
		$this->assertArrayHasKey( 'id', $payload );

		$list_res = $this->server->dispatch( new WP_REST_Request( 'GET', '/talaxie-core/v1/mcp/sudo-token' ) );
		$this->assertSame( 200, $list_res->get_status() );
		$this->assertCount( 1, $list_res->get_data()['tokens'] );

		$revoke_res = $this->server->dispatch(
			new WP_REST_Request( 'DELETE', '/talaxie-core/v1/mcp/sudo-token/' . (int) $payload['id'] )
		);
		$this->assertSame( 200, $revoke_res->get_status() );
		$this->assertTrue( $revoke_res->get_data()['revoked'] );

		$this->assertFalse( TokenManager::validate( $payload['token'], 'manage_options' ) );
	}

	public function test_revoke_all_returns_count(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		TokenManager::create( array( 'manage_options' ), 600, false, $admin );
		TokenManager::create( array( 'delete_users' ), 600, false, $admin );

		$res = $this->server->dispatch( new WP_REST_Request( 'DELETE', '/talaxie-core/v1/mcp/sudo-token/all' ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 2, $res->get_data()['revoked'] );
	}

	public function test_create_rejects_empty_scope(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$req = new WP_REST_Request( 'POST', '/talaxie-core/v1/mcp/sudo-token' );
		$req->set_param( 'scope', array() );

		$res = $this->server->dispatch( $req );
		$this->assertGreaterThanOrEqual( 400, $res->get_status() );
	}
}
