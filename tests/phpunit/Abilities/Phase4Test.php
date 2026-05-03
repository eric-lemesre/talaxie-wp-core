<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests\Abilities;

use Talaxie\Core\Mcp\Abilities\Generic\RestCall;
use Talaxie\Core\Mcp\Abilities\Network\CreateSite;
use Talaxie\Core\Mcp\Abilities\Network\DeleteSite;
use Talaxie\Core\Mcp\Abilities\Plugins\ActivatePlugin;
use Talaxie\Core\Mcp\Abilities\Plugins\ListPlugins;
use Talaxie\Core\Mcp\Abilities\Site\GetOption;
use Talaxie\Core\Mcp\Abilities\Site\UpdateOption;
use Talaxie\Core\Mcp\CapabilityGate;
use Talaxie\Core\Mcp\Sudo\TokenManager;
use Talaxie\Core\Mcp\Sudo\TokenSchema;
use Talaxie\Core\Roles\AiBotRole;
use WP_UnitTestCase;

/**
 * Smoke coverage for the Phase 4 surface (plugins, options, generic
 * REST proxy, multisite networking).
 */
final class Phase4Test extends WP_UnitTestCase {

	private int $bot_id;
	private int $admin_id;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . TokenSchema::table_name() ); // phpcs:ignore

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->bot_id   = self::factory()->user->create( array( 'role' => AiBotRole::ROLE ) );
	}

	public function test_phase4_abilities_are_registered(): void {
		$names = array(
			GetOption::ABILITY,
			UpdateOption::ABILITY,
			ListPlugins::ABILITY,
			ActivatePlugin::ABILITY,
			CreateSite::ABILITY,
			DeleteSite::ABILITY,
			RestCall::ABILITY,
		);
		foreach ( $names as $name ) {
			$this->assertNotNull( wp_get_ability( $name ), "missing {$name}" );
		}
	}

	public function test_destructive_phase4_abilities_blocked_on_production(): void {
		$this->assertFalse( UpdateOption::is_allowed_on_production() );
		$this->assertFalse( ActivatePlugin::is_allowed_on_production() );
		$this->assertFalse( CreateSite::is_allowed_on_production() );
		$this->assertFalse( DeleteSite::is_allowed_on_production() );
		$this->assertFalse( RestCall::is_allowed_on_production() );

		$this->assertTrue( GetOption::is_allowed_on_production() );
		$this->assertTrue( ListPlugins::is_allowed_on_production() );
	}

	public function test_get_option_respects_allowlist(): void {
		wp_set_current_user( $this->admin_id );

		$ok = wp_get_ability( GetOption::ABILITY )->execute( array( 'name' => 'blogname' ) );
		$this->assertIsArray( $ok );
		$this->assertSame( 'blogname', $ok['name'] );

		$nope = wp_get_ability( GetOption::ABILITY )->execute( array( 'name' => 'secret_api_key' ) );
		$this->assertInstanceOf( \WP_Error::class, $nope );
		$this->assertSame( 'talaxie_option_not_allowlisted', $nope->get_error_code() );
	}

	public function test_update_option_requires_allowlist_and_sudo(): void {
		// Bot needs sudo even with the allowlist.
		wp_set_current_user( $this->bot_id );
		$denied = wp_get_ability( UpdateOption::ABILITY )->check_permissions(
			array( 'name' => 'blogname', 'value' => 'X' )
		);
		$this->assertInstanceOf( \WP_Error::class, $denied );

		wp_set_current_user( $this->admin_id );
		$created = TokenManager::create( array( 'manage_options' ), 600, false, $this->admin_id );

		wp_set_current_user( $this->bot_id );
		$res = wp_get_ability( UpdateOption::ABILITY )->execute(
			array( 'name' => 'blogname', 'value' => 'Updated', '_sudo' => $created['token'] )
		);
		$this->assertIsArray( $res );
		$this->assertTrue( $res['updated'] );
		$this->assertSame( 'Updated', get_option( 'blogname' ) );
	}

	public function test_list_plugins_returns_active_state(): void {
		wp_set_current_user( $this->admin_id );
		$res = wp_get_ability( ListPlugins::ABILITY )->execute( array() );
		$this->assertIsArray( $res );
		$this->assertArrayHasKey( 'items', $res );
		foreach ( $res['items'] as $item ) {
			$this->assertArrayHasKey( 'file', $item );
			$this->assertArrayHasKey( 'active', $item );
		}
	}

	public function test_rest_call_get_proxies_to_wp_v2(): void {
		// When mcp-adapter is loaded beside the plugin (local dev), rest_do_request()
		// briefly references its abilities by name and triggers an incorrect-usage
		// notice on WP_Abilities_Registry::get_registered. Tolerate it conditionally.
		if ( class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );
		}

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		wp_set_current_user( $this->admin_id );
		$res = wp_get_ability( RestCall::ABILITY )->execute(
			array( 'method' => 'GET', 'path' => 'posts/' . $post_id )
		);
		$this->assertIsArray( $res );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( $post_id, $res['data']['id'] );
	}

	public function test_capability_gate_super_admin_is_unavailable_outside_multisite(): void {
		wp_set_current_user( $this->admin_id );
		// On single-site installs the virtual super_admin must always be denied.
		$res = CapabilityGate::check( 'demo/x', CapabilityGate::VIRTUAL_SUPER_ADMIN, array() );
		$this->assertInstanceOf( \WP_Error::class, $res );
	}

	public function test_network_abilities_refuse_when_not_multisite(): void {
		wp_set_current_user( $this->admin_id );
		// Single-site test runtime — both abilities must short-circuit.
		$create = wp_get_ability( CreateSite::ABILITY )->check_permissions(
			array( 'domain' => 'site.test', 'title' => 'X' )
		);
		$delete = wp_get_ability( DeleteSite::ABILITY )->check_permissions( array( 'site_id' => 2 ) );
		$this->assertInstanceOf( \WP_Error::class, $create );
		$this->assertInstanceOf( \WP_Error::class, $delete );
		$this->assertSame( 'talaxie_network_unavailable', $create->get_error_code() );
		$this->assertSame( 'talaxie_network_unavailable', $delete->get_error_code() );
	}
}
