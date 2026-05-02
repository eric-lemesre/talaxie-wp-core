<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests\Abilities\Pages;

use Talaxie\Core\Mcp\Abilities\Pages\CreatePage;
use Talaxie\Core\Mcp\Abilities\Pages\DeletePage;
use Talaxie\Core\Mcp\Abilities\Pages\GetPage;
use Talaxie\Core\Mcp\Abilities\Pages\ListPages;
use Talaxie\Core\Mcp\Abilities\Pages\UpdatePage;
use Talaxie\Core\Mcp\Sudo\TokenManager;
use Talaxie\Core\Mcp\Sudo\TokenSchema;
use Talaxie\Core\Roles\AiBotRole;
use WP_UnitTestCase;

final class PagesTest extends WP_UnitTestCase {

	private int $bot_id;
	private int $admin_id;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . TokenSchema::table_name() ); // phpcs:ignore

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->bot_id   = self::factory()->user->create( array( 'role' => AiBotRole::ROLE ) );
	}

	public function test_all_page_abilities_are_registered(): void {
		foreach ( array( ListPages::ABILITY, GetPage::ABILITY, CreatePage::ABILITY, UpdatePage::ABILITY, DeletePage::ABILITY ) as $name ) {
			$this->assertNotNull( wp_get_ability( $name ), "missing ability {$name}" );
		}
	}

	public function test_only_delete_page_is_blocked_on_production(): void {
		$this->assertTrue( ListPages::is_allowed_on_production() );
		$this->assertTrue( GetPage::is_allowed_on_production() );
		$this->assertTrue( CreatePage::is_allowed_on_production() );
		$this->assertTrue( UpdatePage::is_allowed_on_production() );
		$this->assertFalse( DeletePage::is_allowed_on_production() );
	}

	public function test_bot_can_list_create_get_update_pages(): void {
		wp_set_current_user( $this->bot_id );

		$created = wp_get_ability( CreatePage::ABILITY )->execute( array( 'title' => 'Hello bot' ) );
		$this->assertIsArray( $created );
		$this->assertGreaterThan( 0, $created['id'] );
		$this->assertSame( 'draft', $created['status'] );

		$got = wp_get_ability( GetPage::ABILITY )->execute( array( 'id' => $created['id'] ) );
		$this->assertSame( 'Hello bot', $got['title'] );

		$updated = wp_get_ability( UpdatePage::ABILITY )->execute( array( 'id' => $created['id'], 'title' => 'Renamed' ) );
		$this->assertSame( $created['id'], $updated['id'] );
		$this->assertSame( 'Renamed', get_post( $created['id'] )->post_title );

		$listed = wp_get_ability( ListPages::ABILITY )->execute( array( 'status' => 'any' ) );
		$this->assertGreaterThanOrEqual( 1, $listed['total'] );
	}

	public function test_delete_page_requires_sudo_for_bot(): void {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );

		wp_set_current_user( $this->bot_id );
		$result = wp_get_ability( DeletePage::ABILITY )->check_permissions( array( 'id' => $page_id ) );
		$this->assertInstanceOf( \WP_Error::class, $result );

		wp_set_current_user( $this->admin_id );
		$created = TokenManager::create( array( 'delete_pages' ), 900, false, $this->admin_id );

		wp_set_current_user( $this->bot_id );
		$result = wp_get_ability( DeletePage::ABILITY )->check_permissions( array( 'id' => $page_id, '_sudo' => $created['token'] ) );
		$this->assertTrue( $result );
	}

	public function test_admin_can_delete_page_without_sudo(): void {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );

		wp_set_current_user( $this->admin_id );
		$result = wp_get_ability( DeletePage::ABILITY )->execute( array( 'id' => $page_id ) );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['deleted'] );
		// Default is trash, not force-delete.
		$this->assertSame( 'trash', get_post_status( $page_id ) );
	}
}
