<?php
/**
 * Daily cron that prunes audit entries older than the retention window.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Schedules a daily WP-cron job that hard-deletes audit posts older than
 * RETENTION_DAYS. Also sweeps expired sudo tokens at the same time.
 */
final class AuditRetention {

	public const HOOK            = 'talaxie_mcp_audit_purge';
	public const RETENTION_DAYS  = 30;
	public const RETENTION_FILTER = 'talaxie_mcp_audit_retention_days';

	/**
	 * Hook the cron handler. The schedule itself is set by activate().
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( self::HOOK, array( self::class, 'run' ) );
	}

	/**
	 * Schedule the daily cron if not already scheduled.
	 *
	 * Idempotent — safe to call from the activation hook.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Unschedule every queued instance of the cron.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Cron callback — purges expired audit entries and sudo tokens.
	 *
	 * @return int Number of audit posts deleted.
	 */
	public static function run(): int {
		$days = (int) apply_filters( self::RETENTION_FILTER, self::RETENTION_DAYS );
		if ( $days <= 0 ) {
			return 0;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$query = new \WP_Query(
			array(
				'post_type'      => AuditPostType::POST_TYPE,
				'post_status'    => 'any',
				'date_query'     => array(
					array(
						'before'    => $cutoff,
						'inclusive' => true,
						'column'    => 'post_date_gmt',
					),
				),
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$deleted = 0;
		foreach ( $query->posts as $post_id ) {
			if ( wp_delete_post( (int) $post_id, true ) ) {
				$deleted++;
			}
		}

		\Talaxie\Core\Mcp\Sudo\TokenManager::sweep_expired();

		return $deleted;
	}
}
