<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests\Sudo;

use Talaxie\Core\Mcp\Sudo\TokenManager;
use Talaxie\Core\Mcp\Sudo\TokenSchema;
use WP_UnitTestCase;

/**
 * @covers \Talaxie\Core\Mcp\Sudo\TokenManager
 */
final class TokenManagerTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		// Clean slate: every test starts with no tokens.
		$wpdb->query( 'TRUNCATE TABLE ' . TokenSchema::table_name() ); // phpcs:ignore
	}

	public function test_create_returns_token_and_persists_a_row(): void {
		$result = TokenManager::create( array( 'manage_options' ), 900, false, 1 );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'token', $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'expires_at', $result );
		$this->assertStringStartsWith( 'tlx_sudo_', $result['token'] );

		global $wpdb;
		$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . TokenSchema::table_name() ); // phpcs:ignore
		$this->assertSame( 1, $count );
	}

	public function test_create_rejects_empty_scope(): void {
		$result = TokenManager::create( array(), 900, false, 1 );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'sudo_scope_required', $result->get_error_code() );
	}

	public function test_validate_succeeds_with_correct_scope(): void {
		$created = TokenManager::create( array( 'manage_options' ), 900, false, 1 );
		$this->assertTrue( TokenManager::validate( $created['token'], 'manage_options' ) );
	}

	public function test_validate_fails_when_capability_not_in_scope(): void {
		$created = TokenManager::create( array( 'manage_options' ), 900, false, 1 );
		$this->assertFalse( TokenManager::validate( $created['token'], 'delete_users' ) );
	}

	public function test_validate_fails_for_unknown_token(): void {
		$this->assertFalse( TokenManager::validate( 'tlx_sudo_NEVERissued', 'manage_options' ) );
	}

	public function test_validate_fails_for_token_without_prefix(): void {
		$this->assertFalse( TokenManager::validate( 'random-string', 'manage_options' ) );
	}

	public function test_validate_increments_usage_count_on_success(): void {
		$created = TokenManager::create( array( 'manage_options' ), 900, false, 1 );
		TokenManager::validate( $created['token'], 'manage_options' );
		TokenManager::validate( $created['token'], 'manage_options' );

		global $wpdb;
		$used = (int) $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare( 'SELECT usage_count FROM ' . TokenSchema::table_name() . ' WHERE id = %d', $created['id'] )
		);
		$this->assertSame( 2, $used );
	}

	public function test_single_use_token_is_revoked_after_first_validation(): void {
		$created = TokenManager::create( array( 'manage_options' ), 900, true, 1 );
		$this->assertTrue( TokenManager::validate( $created['token'], 'manage_options' ) );
		$this->assertFalse( TokenManager::validate( $created['token'], 'manage_options' ) );
	}

	public function test_revoke_all_disables_active_tokens(): void {
		$created = TokenManager::create( array( 'manage_options' ), 900, false, 1 );
		$revoked = TokenManager::revoke_all();
		$this->assertSame( 1, $revoked );
		$this->assertFalse( TokenManager::validate( $created['token'], 'manage_options' ) );
	}

	public function test_validate_fails_after_expiration(): void {
		$created = TokenManager::create( array( 'manage_options' ), 900, false, 1 );

		global $wpdb;
		// Force the row to be expired.
		$wpdb->update( // phpcs:ignore
			TokenSchema::table_name(),
			array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ),
			array( 'id' => $created['id'] ),
			array( '%s' ),
			array( '%d' )
		);

		$this->assertFalse( TokenManager::validate( $created['token'], 'manage_options' ) );
	}

	public function test_sweep_expired_removes_old_rows(): void {
		$created = TokenManager::create( array( 'manage_options' ), 900, false, 1 );
		global $wpdb;
		$wpdb->update( // phpcs:ignore
			TokenSchema::table_name(),
			array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - ( 2 * DAY_IN_SECONDS ) ) ),
			array( 'id' => $created['id'] ),
			array( '%s' ),
			array( '%d' )
		);

		$removed = TokenManager::sweep_expired();
		$this->assertSame( 1, $removed );
	}

	public function test_ttl_is_clamped_to_hard_max(): void {
		$created = TokenManager::create( array( 'manage_options' ), 999999, false, 1 );
		$this->assertIsArray( $created );

		$expires = strtotime( $created['expires_at'] . ' UTC' );
		$now     = time();
		$diff    = $expires - $now;
		$this->assertLessThanOrEqual( TokenManager::HARD_MAX_TTL + 5, $diff );
	}
}
