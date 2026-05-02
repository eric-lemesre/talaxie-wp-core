<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests;

use Talaxie\Core\Mcp\CapabilityGate;
use Talaxie\Core\Mcp\Sudo\TokenManager;
use Talaxie\Core\Mcp\Sudo\TokenSchema;
use Talaxie\Core\Roles\AiBotRole;
use WP_UnitTestCase;

/**
 * @covers \Talaxie\Core\Mcp\CapabilityGate
 */
final class CapabilityGateTest extends WP_UnitTestCase {

	private int $admin_id;
	private int $bot_id;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . TokenSchema::table_name() ); // phpcs:ignore

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->bot_id   = self::factory()->user->create( array( 'role' => AiBotRole::ROLE ) );
	}

	public function test_admin_passes_without_sudo(): void {
		wp_set_current_user( $this->admin_id );
		$result = CapabilityGate::check( 'demo/x', 'manage_options', array() );
		$this->assertTrue( $result );
	}

	public function test_bot_is_denied_without_sudo_for_admin_capability(): void {
		wp_set_current_user( $this->bot_id );
		$result = CapabilityGate::check( 'demo/x', 'manage_options', array() );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'talaxie_mcp_permission_denied', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	public function test_bot_passes_with_valid_sudo_token(): void {
		wp_set_current_user( $this->admin_id );
		$created = TokenManager::create( array( 'manage_options' ), 900, false, $this->admin_id );

		wp_set_current_user( $this->bot_id );
		$result = CapabilityGate::check( 'demo/x', 'manage_options', array( '_sudo' => $created['token'] ) );
		$this->assertTrue( $result );
	}

	public function test_bot_is_denied_with_sudo_scoped_to_other_capability(): void {
		wp_set_current_user( $this->admin_id );
		$created = TokenManager::create( array( 'manage_options' ), 900, false, $this->admin_id );

		wp_set_current_user( $this->bot_id );
		$result = CapabilityGate::check( 'demo/x', 'delete_users', array( '_sudo' => $created['token'] ) );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_audit_action_fires_with_expected_payload(): void {
		$captured = array();
		add_action(
			'talaxie_mcp_audit',
			static function ( array $event ) use ( &$captured ): void {
				$captured[] = $event;
			}
		);

		wp_set_current_user( $this->admin_id );
		CapabilityGate::check( 'demo/x', 'manage_options', array() );

		$this->assertCount( 1, $captured );
		$this->assertSame( 'demo/x', $captured[0]['ability'] );
		$this->assertSame( 'manage_options', $captured[0]['capability'] );
		$this->assertTrue( $captured[0]['allowed'] );
		$this->assertFalse( $captured[0]['sudo_used'] );
		$this->assertSame( $this->admin_id, $captured[0]['user_id'] );
	}
}
