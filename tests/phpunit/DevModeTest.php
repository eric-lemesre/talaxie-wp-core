<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests;

use Talaxie\Core\Mcp\CapabilityGate;
use Talaxie\Core\Mcp\DevMode;
use Talaxie\Core\Roles\AiBotRole;
use WP_UnitTestCase;

/**
 * @covers \Talaxie\Core\Mcp\DevMode
 */
final class DevModeTest extends WP_UnitTestCase {

	public function test_dev_mode_is_inactive_when_constant_unset(): void {
		$this->assertFalse( DevMode::is_active() );
	}

	public function test_dev_mode_only_activates_in_safe_environments(): void {
		$force = static fn( bool $safe, string $env ): bool => in_array( $env, array( 'local', 'development' ), true );
		add_filter( 'talaxie_mcp_dev_mode_environment_safe', $force, 10, 2 );

		$prod = static fn(): bool => false;
		$dev  = static fn(): bool => true;

		add_filter( 'talaxie_mcp_dev_mode_environment_safe', $prod );
		$this->assertFalse( DevMode::is_environment_safe() );
		remove_filter( 'talaxie_mcp_dev_mode_environment_safe', $prod );

		add_filter( 'talaxie_mcp_dev_mode_environment_safe', $dev );
		$this->assertTrue( DevMode::is_environment_safe() );
		remove_filter( 'talaxie_mcp_dev_mode_environment_safe', $dev );

		remove_filter( 'talaxie_mcp_dev_mode_environment_safe', $force, 10 );
	}

	public function test_capability_gate_audits_dev_mode_bypass(): void {
		$bot = self::factory()->user->create( array( 'role' => AiBotRole::ROLE ) );
		wp_set_current_user( $bot );

		$captured = array();
		add_action(
			'talaxie_mcp_audit',
			static function ( array $event ) use ( &$captured ): void {
				$captured[] = $event;
			}
		);

		// Without dev-mode the bot is denied.
		$res = CapabilityGate::check( 'demo/x', 'manage_options', array() );
		$this->assertInstanceOf( \WP_Error::class, $res );

		// Force dev-mode active via the dedicated test filter.
		$flip = static fn(): bool => true;
		add_filter( DevMode::FILTER, $flip );

		$res = CapabilityGate::check( 'demo/x', 'manage_options', array() );
		$this->assertTrue( $res );

		$last = end( $captured );
		$this->assertTrue( $last['allowed'] );
		$this->assertTrue( $last['dev_mode_bypass'] );

		remove_filter( DevMode::FILTER, $flip );
	}
}
