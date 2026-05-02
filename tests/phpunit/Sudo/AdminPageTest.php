<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests\Sudo;

use Talaxie\Core\Mcp\Sudo\AdminPage;
use Talaxie\Core\Mcp\Sudo\TokenManager;
use Talaxie\Core\Mcp\Sudo\TokenSchema;
use WP_UnitTestCase;

/**
 * @covers \Talaxie\Core\Mcp\Sudo\AdminPage
 */
final class AdminPageTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . TokenSchema::table_name() ); // phpcs:ignore
	}

	public function test_render_does_not_leak_token_when_no_creation_pending(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		set_current_screen( 'tools_page_' . AdminPage::SLUG );

		ob_start();
		AdminPage::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'MCP Sudo', $html );
		$this->assertStringNotContainsString( 'tlx_sudo_', $html );
	}

	public function test_render_lists_active_tokens(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$created = TokenManager::create( array( 'manage_options' ), 600, false, $admin );
		$this->assertIsArray( $created );

		set_current_screen( 'tools_page_' . AdminPage::SLUG );
		ob_start();
		AdminPage::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'manage_options', $html );
		// The cleartext is not shown unless the just-created transient is set.
		$this->assertStringNotContainsString( $created['token'], $html );
	}

	public function test_pending_creation_displays_cleartext_once(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$created = TokenManager::create( array( 'manage_options' ), 600, false, $admin );
		set_transient(
			AdminPage::TRANSIENT_KEY . $admin,
			array(
				'token'      => $created['token'],
				'expires_at' => $created['expires_at'],
			),
			MINUTE_IN_SECONDS
		);

		set_current_screen( 'tools_page_' . AdminPage::SLUG );
		ob_start();
		AdminPage::render();
		$first = (string) ob_get_clean();
		$this->assertStringContainsString( $created['token'], $first );

		// Second render no longer shows it.
		ob_start();
		AdminPage::render();
		$second = (string) ob_get_clean();
		$this->assertStringNotContainsString( $created['token'], $second );
	}
}
