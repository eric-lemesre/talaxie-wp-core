<?php
/**
 * Sudo token manager — create, validate, revoke.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Sudo;

defined( 'ABSPATH' ) || exit;

/**
 * Issues short-lived elevation tokens scoped to a list of capabilities.
 *
 * Cleartext tokens are returned to the human admin once at creation time.
 * Only a bcrypt hash is persisted, so the table dump alone cannot be
 * replayed to elevate a session.
 */
final class TokenManager {

	public const TOKEN_PREFIX     = 'tlx_sudo_';
	public const DEFAULT_TTL      = 15 * MINUTE_IN_SECONDS;
	public const MAX_TTL_CONSTANT = 'TALAXIE_MCP_SUDO_MAX_TTL';
	public const HARD_MAX_TTL     = 60 * MINUTE_IN_SECONDS;

	/**
	 * Issue a new sudo token.
	 *
	 * @param string[]  $scope          Capabilities the token elevates to.
	 * @param int       $ttl_seconds    Lifetime in seconds (clamped to the configured max).
	 * @param bool      $single_use     If true, the token is invalidated after one validation.
	 * @param int|null  $created_by     User ID creating the token (defaults to the current user).
	 *
	 * @return array{token:string, expires_at:string, id:int}|\WP_Error
	 */
	public static function create( array $scope, int $ttl_seconds = self::DEFAULT_TTL, bool $single_use = false, ?int $created_by = null ) {
		if ( empty( $scope ) ) {
			return new \WP_Error(
				'sudo_scope_required',
				__( 'A sudo token must declare at least one capability in its scope.', 'talaxie-core' )
			);
		}

		$max_ttl = self::max_ttl();
		if ( $ttl_seconds <= 0 ) {
			$ttl_seconds = self::DEFAULT_TTL;
		}
		$ttl_seconds = min( $ttl_seconds, $max_ttl );

		$cleartext = self::TOKEN_PREFIX . self::random_suffix( 32 );
		$hash      = password_hash( $cleartext, PASSWORD_BCRYPT );
		if ( false === $hash ) {
			return new \WP_Error(
				'sudo_hash_failed',
				__( 'Could not hash the sudo token.', 'talaxie-core' )
			);
		}

		$now        = time();
		$created_at = gmdate( 'Y-m-d H:i:s', $now );
		$expires_at = gmdate( 'Y-m-d H:i:s', $now + $ttl_seconds );
		$user_id    = $created_by ?? get_current_user_id();

		global $wpdb;
		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			TokenSchema::table_name(),
			array(
				'token_hash'         => $hash,
				'scope'              => wp_json_encode( array_values( $scope ) ),
				'single_use'         => $single_use ? 1 : 0,
				'usage_count'        => 0,
				'created_at'         => $created_at,
				'expires_at'         => $expires_at,
				'created_by_user_id' => $user_id,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%d' )
		);

		if ( false === $inserted ) {
			return new \WP_Error(
				'sudo_insert_failed',
				__( 'Could not persist the sudo token.', 'talaxie-core' )
			);
		}

		return array(
			'id'         => (int) $wpdb->insert_id,
			'token'      => $cleartext,
			'expires_at' => $expires_at,
		);
	}

	/**
	 * Validate a sudo token against a required capability.
	 *
	 * On success, increments usage_count and returns true. On failure,
	 * returns false (no throw, no leak — the caller decides the response).
	 *
	 * @param string $token        Cleartext token submitted by the agent.
	 * @param string $required_cap Capability the caller is trying to exercise.
	 *
	 * @return bool
	 */
	public static function validate( string $token, string $required_cap ): bool {
		if ( '' === $token || 0 !== strpos( $token, self::TOKEN_PREFIX ) ) {
			return false;
		}

		global $wpdb;
		$table = TokenSchema::table_name();
		$now   = gmdate( 'Y-m-d H:i:s' );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id, token_hash, scope, single_use, usage_count
				 FROM {$table}
				 WHERE revoked_at IS NULL
				   AND expires_at > %s",
				$now
			)
		);

		if ( empty( $rows ) ) {
			return false;
		}

		foreach ( $rows as $row ) {
			if ( ! password_verify( $token, $row->token_hash ) ) {
				continue;
			}

			$scope = json_decode( $row->scope, true );
			if ( ! is_array( $scope ) || ! in_array( $required_cap, $scope, true ) ) {
				return false;
			}

			$single_use = (int) $row->single_use === 1;
			$used       = (int) $row->usage_count;
			if ( $single_use && $used > 0 ) {
				return false;
			}

			$update = array(
				'usage_count' => $used + 1,
			);
			$format = array( '%d' );
			if ( $single_use ) {
				$update['revoked_at'] = $now;
				$format[]             = '%s';
			}

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				$update,
				array( 'id' => (int) $row->id ),
				$format,
				array( '%d' )
			);

			return true;
		}

		return false;
	}

	/**
	 * Revoke every token that has not yet expired.
	 *
	 * @return int Number of tokens marked as revoked.
	 */
	public static function revoke_all(): int {
		global $wpdb;
		$table = TokenSchema::table_name();
		$now   = gmdate( 'Y-m-d H:i:s' );

		$count = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"UPDATE {$table} SET revoked_at = %s WHERE revoked_at IS NULL AND expires_at > %s",
				$now,
				$now
			)
		);

		return (int) $count;
	}

	/**
	 * Hard-delete tokens that expired more than a day ago.
	 *
	 * Wired to a daily cron in Phase 2.
	 *
	 * @return int Number of rows removed.
	 */
	public static function sweep_expired(): int {
		global $wpdb;
		$table  = TokenSchema::table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

		$count = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE expires_at < %s",
				$cutoff
			)
		);

		return (int) $count;
	}

	/**
	 * Effective max TTL: configurable via constant, capped by HARD_MAX_TTL.
	 *
	 * @return int
	 */
	private static function max_ttl(): int {
		if ( defined( self::MAX_TTL_CONSTANT ) ) {
			$configured = (int) constant( self::MAX_TTL_CONSTANT );
			if ( $configured > 0 ) {
				return min( $configured, self::HARD_MAX_TTL );
			}
		}

		return self::HARD_MAX_TTL;
	}

	/**
	 * Cryptographically strong base62 suffix.
	 *
	 * @param int $length Number of characters to produce.
	 *
	 * @return string
	 */
	private static function random_suffix( int $length ): string {
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		$max      = strlen( $alphabet ) - 1;
		$out      = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$out .= $alphabet[ random_int( 0, $max ) ];
		}

		return $out;
	}
}
