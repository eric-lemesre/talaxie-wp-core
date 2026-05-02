<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests\Audit;

use Talaxie\Core\Mcp\Audit\AuditPostType;
use Talaxie\Core\Mcp\Audit\AuditRetention;
use WP_UnitTestCase;

/**
 * @covers \Talaxie\Core\Mcp\Audit\AuditRetention
 */
final class AuditRetentionTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		AuditPostType::register();
	}

	public function test_run_deletes_posts_older_than_retention_window(): void {
		$old_id = self::factory()->post->create(
			array(
				'post_type'   => AuditPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() - ( 60 * DAY_IN_SECONDS ) ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - ( 60 * DAY_IN_SECONDS ) ),
			)
		);
		$fresh_id = self::factory()->post->create(
			array(
				'post_type'   => AuditPostType::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$deleted = AuditRetention::run();

		$this->assertGreaterThanOrEqual( 1, $deleted );
		$this->assertNull( get_post( $old_id ) );
		$this->assertNotNull( get_post( $fresh_id ) );
	}

	public function test_filter_can_extend_retention_window(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'     => AuditPostType::POST_TYPE,
				'post_status'   => 'publish',
				'post_date'     => gmdate( 'Y-m-d H:i:s', time() - ( 60 * DAY_IN_SECONDS ) ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - ( 60 * DAY_IN_SECONDS ) ),
			)
		);

		$filter = static fn(): int => 365;
		add_filter( AuditRetention::RETENTION_FILTER, $filter );

		AuditRetention::run();

		remove_filter( AuditRetention::RETENTION_FILTER, $filter );

		$this->assertNotNull( get_post( $id ) );
	}

	public function test_activate_schedules_a_daily_cron(): void {
		wp_clear_scheduled_hook( AuditRetention::HOOK );
		$this->assertFalse( wp_next_scheduled( AuditRetention::HOOK ) );

		AuditRetention::activate();
		$this->assertNotFalse( wp_next_scheduled( AuditRetention::HOOK ) );
		$this->assertSame( 'daily', wp_get_schedule( AuditRetention::HOOK ) );

		AuditRetention::deactivate();
		$this->assertFalse( wp_next_scheduled( AuditRetention::HOOK ) );
	}
}
