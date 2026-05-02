<?php
/**
 * WP-CLI commands for sudo token management.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Sudo;

defined( 'ABSPATH' ) || exit;

/**
 * Implements `wp talaxie mcp sudo-token` and friends.
 *
 *     wp talaxie mcp sudo-token --scope=manage_options --ttl=15m
 *     wp talaxie mcp sudo-list
 *     wp talaxie mcp sudo-revoke <id>
 *     wp talaxie mcp sudo-revoke --all
 */
final class CliCommand {

	/**
	 * Hook the command into WP-CLI when available.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}
		\WP_CLI::add_command( 'talaxie mcp sudo-token', array( self::class, 'cmd_create' ) );
		\WP_CLI::add_command( 'talaxie mcp sudo-list', array( self::class, 'cmd_list' ) );
		\WP_CLI::add_command( 'talaxie mcp sudo-revoke', array( self::class, 'cmd_revoke' ) );
	}

	/**
	 * Create a sudo token.
	 *
	 * ## OPTIONS
	 *
	 * --scope=<scope>
	 * : Comma-separated capabilities the token grants (e.g. manage_options or delete_users,edit_users).
	 *
	 * [--ttl=<duration>]
	 * : Lifetime, accepted suffixes s/m/h. Defaults to 15m.
	 *
	 * [--single-use]
	 * : The token is invalidated after one validation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp talaxie mcp sudo-token --scope=manage_options
	 *     wp talaxie mcp sudo-token --scope=delete_users,edit_users --ttl=30m --single-use
	 *
	 * @param array<int, string>           $args Positional args.
	 * @param array<string, string|bool>   $assoc Named args.
	 *
	 * @return void
	 */
	public static function cmd_create( array $args, array $assoc ): void {
		$scope_raw = isset( $assoc['scope'] ) ? (string) $assoc['scope'] : '';
		$scope     = array_values( array_filter( array_map( 'trim', explode( ',', $scope_raw ) ), 'strlen' ) );
		if ( empty( $scope ) ) {
			\WP_CLI::error( 'Provide --scope=cap1,cap2 with at least one capability.' );
		}

		$ttl        = isset( $assoc['ttl'] ) ? self::parse_duration( (string) $assoc['ttl'] ) : TokenManager::DEFAULT_TTL;
		$single_use = ! empty( $assoc['single-use'] );

		$result = TokenManager::create( $scope, $ttl, $single_use );
		if ( $result instanceof \WP_Error ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		\WP_CLI::log( '' );
		\WP_CLI::log( 'Token (copy now — it is shown only once):' );
		\WP_CLI::log( '  ' . $result['token'] );
		\WP_CLI::log( '' );
		\WP_CLI::log( sprintf( 'expires_at (UTC): %s', $result['expires_at'] ) );
		\WP_CLI::success( sprintf( 'Sudo token #%d created.', $result['id'] ) );
	}

	/**
	 * List active sudo tokens.
	 *
	 * @param array<int, string>         $args Positional args.
	 * @param array<string, string|bool> $assoc Named args.
	 *
	 * @return void
	 */
	public static function cmd_list( array $args, array $assoc ): void {
		$tokens = TokenManager::list_active();
		if ( empty( $tokens ) ) {
			\WP_CLI::log( 'No active sudo tokens.' );
			return;
		}

		$rows = array();
		foreach ( $tokens as $row ) {
			$rows[] = array(
				'id'         => $row['id'],
				'scope'      => implode( ',', $row['scope'] ),
				'expires_at' => $row['expires_at'],
				'single_use' => $row['single_use'] ? 'yes' : 'no',
				'used'       => $row['usage_count'],
				'by_user'    => $row['created_by_user_id'],
			);
		}

		\WP_CLI\Utils\format_items(
			'table',
			$rows,
			array( 'id', 'scope', 'expires_at', 'single_use', 'used', 'by_user' )
		);
	}

	/**
	 * Revoke a sudo token by id, or every active one with --all.
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Numeric token id to revoke.
	 *
	 * [--all]
	 * : Revoke every active token.
	 *
	 * @param array<int, string>         $args Positional args.
	 * @param array<string, string|bool> $assoc Named args.
	 *
	 * @return void
	 */
	public static function cmd_revoke( array $args, array $assoc ): void {
		if ( ! empty( $assoc['all'] ) ) {
			$count = TokenManager::revoke_all();
			\WP_CLI::success( sprintf( 'Revoked %d active sudo token(s).', $count ) );
			return;
		}

		$id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( $id <= 0 ) {
			\WP_CLI::error( 'Pass a token id, or use --all to revoke every active token.' );
		}

		$ok = TokenManager::revoke( $id );
		if ( ! $ok ) {
			\WP_CLI::error( sprintf( 'Token #%d not found or already revoked.', $id ) );
		}
		\WP_CLI::success( sprintf( 'Sudo token #%d revoked.', $id ) );
	}

	/**
	 * Parse a TTL string with optional s/m/h suffix.
	 *
	 * @param string $value Raw value (e.g. "15m" or "900").
	 *
	 * @return int Seconds.
	 */
	private static function parse_duration( string $value ): int {
		$value = trim( $value );
		if ( '' === $value ) {
			return TokenManager::DEFAULT_TTL;
		}
		if ( preg_match( '/^(\d+)\s*(s|m|h)$/i', $value, $m ) ) {
			$n   = (int) $m[1];
			$mul = strtolower( $m[2] ) === 'h' ? HOUR_IN_SECONDS : ( strtolower( $m[2] ) === 'm' ? MINUTE_IN_SECONDS : 1 );
			return $n * $mul;
		}
		return (int) $value;
	}
}
