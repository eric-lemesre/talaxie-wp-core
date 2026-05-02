<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests\Audit;

use Talaxie\Core\Mcp\Audit\AuditLogger;
use Talaxie\Core\Mcp\Audit\AuditPostType;
use Talaxie\Core\Mcp\CapabilityGate;
use Talaxie\Core\Roles\AiBotRole;
use WP_UnitTestCase;

/**
 * @covers \Talaxie\Core\Mcp\Audit\AuditLogger
 * @covers \Talaxie\Core\Mcp\Audit\AuditPostType
 */
final class AuditLoggerTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// CPT registration runs on init; do_action ensures it is registered for tests.
		AuditPostType::register();
		AuditLogger::register();
	}

	public function test_logger_inserts_one_post_per_capability_gate_call(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$count_before = (int) wp_count_posts( AuditPostType::POST_TYPE )->publish;
		CapabilityGate::check( 'demo/x', 'manage_options', array() );
		$count_after = (int) wp_count_posts( AuditPostType::POST_TYPE )->publish;

		$this->assertSame( $count_before + 1, $count_after );
	}

	public function test_meta_records_outcome_and_capability(): void {
		$bot = self::factory()->user->create( array( 'role' => AiBotRole::ROLE ) );
		wp_set_current_user( $bot );

		CapabilityGate::check( 'posts/delete', 'delete_posts', array() );

		$posts = get_posts(
			array(
				'post_type'      => AuditPostType::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$this->assertNotEmpty( $posts );
		$post_id = $posts[0]->ID;

		$this->assertSame( 'posts/delete', get_post_meta( $post_id, AuditLogger::META_ABILITY, true ) );
		$this->assertSame( 'delete_posts', get_post_meta( $post_id, AuditLogger::META_CAPABILITY, true ) );
		$this->assertSame( '0', get_post_meta( $post_id, AuditLogger::META_ALLOWED, true ) );
		$this->assertSame( '0', get_post_meta( $post_id, AuditLogger::META_SUDO_USED, true ) );
		$this->assertSame( (string) $bot, (string) get_post_meta( $post_id, AuditLogger::META_USER, true ) );
	}

	public function test_sudo_token_is_redacted_in_raw_event(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		AuditLogger::log(
			array(
				'ability'   => 'demo/x',
				'capability'=> 'manage_options',
				'user_id'   => $admin,
				'sudo_used' => true,
				'allowed'   => true,
				'_sudo'     => 'tlx_sudo_SECRET',
			)
		);

		$posts = get_posts(
			array(
				'post_type'      => AuditPostType::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$raw = get_post_meta( $posts[0]->ID, AuditLogger::META_RAW, true );
		$this->assertIsArray( $raw );
		$this->assertSame( '***REDACTED***', $raw['_sudo'] );
	}
}
