<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests\Abilities\Users;

use Talaxie\Core\Mcp\Abilities\Users\CreateUser;
use Talaxie\Core\Mcp\Abilities\Users\GetUser;
use Talaxie\Core\Mcp\Abilities\Users\ListUsers;
use Talaxie\Core\Mcp\Abilities\Users\UpdateUser;
use Talaxie\Core\Mcp\Sudo\TokenManager;
use Talaxie\Core\Mcp\Sudo\TokenSchema;
use Talaxie\Core\Roles\AiBotRole;
use WP_UnitTestCase;

final class UsersTest extends WP_UnitTestCase {

	private int $bot_id;
	private int $admin_id;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . TokenSchema::table_name() ); // phpcs:ignore

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->bot_id   = self::factory()->user->create( array( 'role' => AiBotRole::ROLE ) );
	}

	public function test_user_abilities_are_registered(): void {
		foreach ( array( ListUsers::ABILITY, GetUser::ABILITY, CreateUser::ABILITY, UpdateUser::ABILITY ) as $name ) {
			$this->assertNotNull( wp_get_ability( $name ) );
		}
	}

	public function test_create_and_update_are_blocked_on_production(): void {
		$this->assertTrue( ListUsers::is_allowed_on_production() );
		$this->assertTrue( GetUser::is_allowed_on_production() );
		$this->assertFalse( CreateUser::is_allowed_on_production() );
		$this->assertFalse( UpdateUser::is_allowed_on_production() );
	}

	public function test_bot_needs_sudo_for_list_users(): void {
		wp_set_current_user( $this->bot_id );
		$result = wp_get_ability( ListUsers::ABILITY )->check_permissions( array() );
		$this->assertInstanceOf( \WP_Error::class, $result );

		wp_set_current_user( $this->admin_id );
		$created = TokenManager::create( array( 'list_users' ), 600, false, $this->admin_id );

		wp_set_current_user( $this->bot_id );
		$result = wp_get_ability( ListUsers::ABILITY )->check_permissions( array( '_sudo' => $created['token'] ) );
		$this->assertTrue( $result );
	}

	public function test_admin_can_create_and_update_user(): void {
		wp_set_current_user( $this->admin_id );

		$created = wp_get_ability( CreateUser::ABILITY )->execute(
			array( 'login' => 'someone', 'email' => 'someone@example.com', 'role' => 'editor' )
		);
		$this->assertIsArray( $created );
		$this->assertGreaterThan( 0, $created['id'] );

		$updated = wp_get_ability( UpdateUser::ABILITY )->execute(
			array( 'id' => $created['id'], 'display_name' => 'Some Body' )
		);
		$this->assertSame( $created['id'], $updated['id'] );
		$this->assertSame( 'Some Body', get_userdata( $created['id'] )->display_name );
	}
}
