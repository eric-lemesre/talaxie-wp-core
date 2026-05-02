<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests\Abilities\Site;

use Talaxie\Core\Mcp\Abilities\Site\GetInfo;
use WP_UnitTestCase;

/**
 * @covers \Talaxie\Core\Mcp\Abilities\Site\GetInfo
 */
final class GetInfoTest extends WP_UnitTestCase {

	public function test_ability_is_registered_with_core_registry(): void {
		$ability = wp_get_ability( GetInfo::ABILITY );
		$this->assertNotNull( $ability );
		$this->assertSame( GetInfo::ABILITY, $ability->get_name() );
	}

	public function test_anonymous_caller_is_denied(): void {
		wp_set_current_user( 0 );
		$ability = wp_get_ability( GetInfo::ABILITY );
		$result  = $ability->check_permissions( array() );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'talaxie_mcp_permission_denied', $result->get_error_code() );
	}

	public function test_logged_in_user_gets_basic_site_info(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$ability = wp_get_ability( GetInfo::ABILITY );
		$result  = $ability->execute( array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertArrayHasKey( 'wp_version', $result );
		$this->assertNotSame( '', $result['wp_version'] );
	}

	public function test_is_allowed_on_production(): void {
		$this->assertTrue( GetInfo::is_allowed_on_production() );
	}
}
