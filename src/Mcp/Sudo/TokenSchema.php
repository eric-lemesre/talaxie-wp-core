<?php
/**
 * Database schema for sudo tokens used by the MCP capability gate.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Sudo;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the {prefix}talaxie_sudo_tokens table lifecycle.
 *
 * The table stores hashed sudo tokens — never the cleartext value. See
 * TokenManager for the create/validate flow.
 */
final class TokenSchema {

	public const TABLE_SUFFIX = 'talaxie_sudo_tokens';

	/**
	 * The fully prefixed table name for the current site.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create or upgrade the sudo tokens table.
	 *
	 * Uses dbDelta so it is safe to call on every activation.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			token_hash VARCHAR(255) NOT NULL,
			scope LONGTEXT NOT NULL,
			single_use TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			usage_count INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			revoked_at DATETIME DEFAULT NULL,
			created_by_user_id BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			KEY expires_at (expires_at),
			KEY created_by_user_id (created_by_user_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drop the table. Called from uninstall.php.
	 *
	 * @return void
	 */
	public static function drop(): void {
		global $wpdb;

		$table = self::table_name();
		// Schema cleanup at uninstall — direct DDL is the standard pattern.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
}
