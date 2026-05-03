<?php
/**
 * WP-CLI commands to discover and invoke abilities directly, without MCP.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Cli;

defined( 'ABSPATH' ) || exit;

/**
 * Lets admin scripts call into the abilities registry without going
 * through MCP. Useful for cron jobs, deploy scripts and quick local
 * smoke tests.
 *
 * Three subcommands are exposed:
 *
 *     wp talaxie ability list
 *     wp talaxie ability info talaxie-core/site-get-info
 *     wp talaxie ability invoke talaxie-core/site-get-info
 *     wp talaxie ability invoke talaxie-core/posts-create --input='{"title":"Hello"}'
 *     wp talaxie ability invoke talaxie-core/posts-create --input-file=payload.json
 */
final class InvokeCommand {

	/**
	 * Register the commands when WP-CLI is available.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}
		\WP_CLI::add_command( 'talaxie ability list', array( self::class, 'cmd_list' ) );
		\WP_CLI::add_command( 'talaxie ability info', array( self::class, 'cmd_info' ) );
		\WP_CLI::add_command( 'talaxie ability invoke', array( self::class, 'cmd_invoke' ) );
	}

	/**
	 * List every registered ability (any namespace).
	 *
	 * ## OPTIONS
	 *
	 * [--prefix=<prefix>]
	 * : Only list abilities whose name starts with the given prefix (e.g. talaxie-core/).
	 *
	 * @param array<int, string>         $args  Positional args (unused, required by WP-CLI signature).
	 * @param array<string, string|bool> $assoc Named args.
	 *
	 * @return void
	 */
	public static function cmd_list( array $args, array $assoc ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		unset( $args );
		$prefix = isset( $assoc['prefix'] ) ? (string) $assoc['prefix'] : '';

		$rows = array();
		foreach ( wp_get_abilities() as $ability ) {
			$name = $ability->get_name();
			if ( '' !== $prefix && 0 !== strpos( $name, $prefix ) ) {
				continue;
			}
			$rows[] = array(
				'name'        => $name,
				'category'    => $ability->get_category(),
				'description' => self::truncate( $ability->get_description(), 80 ),
			);
		}

		if ( empty( $rows ) ) {
			\WP_CLI::log( 'No abilities matched.' );
			return;
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'name', 'category', 'description' ) );
	}

	/**
	 * Show the input/output schema of a single ability.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Fully qualified ability name, e.g. talaxie-core/site-get-info.
	 *
	 * @param array<int, string>         $args  Positional args.
	 * @param array<string, string|bool> $assoc Named args (unused, required by WP-CLI signature).
	 *
	 * @return void
	 */
	public static function cmd_info( array $args, array $assoc ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		unset( $assoc );
		$name = $args[0] ?? '';
		if ( '' === $name ) {
			\WP_CLI::error( 'Pass an ability name, e.g. wp talaxie ability info talaxie-core/site-get-info.' );
		}
		$ability = wp_get_ability( $name );
		if ( null === $ability ) {
			\WP_CLI::error( sprintf( 'Ability "%s" is not registered.', $name ) );
		}

		\WP_CLI::log( 'Name:        ' . $ability->get_name() );
		\WP_CLI::log( 'Label:       ' . $ability->get_label() );
		\WP_CLI::log( 'Category:    ' . $ability->get_category() );
		\WP_CLI::log( 'Description: ' . $ability->get_description() );
		\WP_CLI::log( '' );
		\WP_CLI::log( 'Input schema:' );
		\WP_CLI::log( (string) wp_json_encode( $ability->get_input_schema(), JSON_PRETTY_PRINT ) );
		\WP_CLI::log( '' );
		\WP_CLI::log( 'Output schema:' );
		\WP_CLI::log( (string) wp_json_encode( $ability->get_output_schema(), JSON_PRETTY_PRINT ) );
	}

	/**
	 * Execute an ability and print its result as JSON.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Fully qualified ability name.
	 *
	 * [--input=<json>]
	 * : JSON-encoded object to pass as input.
	 *
	 * [--input-file=<path>]
	 * : Read input JSON from a file. Mutually exclusive with --input.
	 *
	 * [--user=<id-or-login>]
	 * : Run the ability as the given user (default: current CLI user).
	 *
	 * ## EXAMPLES
	 *
	 *     wp talaxie ability invoke talaxie-core/site-get-info
	 *     wp talaxie ability invoke talaxie-core/posts-list --input='{"per_page":5}'
	 *     wp talaxie ability invoke talaxie-core/posts-create --input-file=post.json --user=admin
	 *
	 * @param array<int, string>         $args  Positional args.
	 * @param array<string, string|bool> $assoc Named args.
	 *
	 * @return void
	 */
	public static function cmd_invoke( array $args, array $assoc ): void {
		$name = $args[0] ?? '';
		if ( '' === $name ) {
			\WP_CLI::error( 'Pass an ability name as the first argument.' );
		}
		$ability = wp_get_ability( $name );
		if ( null === $ability ) {
			\WP_CLI::error( sprintf( 'Ability "%s" is not registered.', $name ) );
		}

		$input = self::resolve_input( $assoc );

		if ( isset( $assoc['user'] ) ) {
			$user = self::resolve_user( (string) $assoc['user'] );
			wp_set_current_user( $user->ID );
		}

		$result = $ability->execute( $input );

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			\WP_CLI::error_multi_line(
				array(
					'code:    ' . $result->get_error_code(),
					'message: ' . $result->get_error_message(),
					'data:    ' . wp_json_encode( $data ?? array() ),
				)
			);
			\WP_CLI::halt( 1 );
		}

		\WP_CLI::log( (string) wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Resolve the input payload from --input or --input-file.
	 *
	 * @param array<string, string|bool> $assoc WP-CLI assoc args.
	 *
	 * @return array<string, mixed>
	 */
	private static function resolve_input( array $assoc ): array {
		$inline = isset( $assoc['input'] ) ? (string) $assoc['input'] : '';
		$path   = isset( $assoc['input-file'] ) ? (string) $assoc['input-file'] : '';

		if ( '' !== $inline && '' !== $path ) {
			\WP_CLI::error( '--input and --input-file are mutually exclusive.' );
		}

		$json = '';
		if ( '' !== $inline ) {
			$json = $inline;
		} elseif ( '' !== $path ) {
			if ( ! is_readable( $path ) ) {
				\WP_CLI::error( sprintf( 'Cannot read input file: %s', $path ) );
			}
			$json = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local CLI helper reading a path the operator just typed.
		}

		if ( '' === $json ) {
			return array();
		}

		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			\WP_CLI::error( 'Input must be a JSON object.' );
		}
		return $decoded;
	}

	/**
	 * Resolve a user from a numeric id or a login.
	 *
	 * @param string $key Numeric id or login.
	 *
	 * @return \WP_User
	 */
	private static function resolve_user( string $key ): \WP_User {
		if ( ctype_digit( $key ) ) {
			$user = get_user_by( 'id', (int) $key );
		} else {
			$user = get_user_by( 'login', $key );
		}
		if ( ! $user instanceof \WP_User ) {
			\WP_CLI::error( sprintf( 'No user matches "%s".', $key ) );
		}
		return $user;
	}

	/**
	 * Truncate a string to the given width with an ellipsis.
	 *
	 * @param string $value Source string.
	 * @param int    $width Maximum visible width.
	 *
	 * @return string
	 */
	private static function truncate( string $value, int $width ): string {
		if ( strlen( $value ) <= $width ) {
			return $value;
		}
		return substr( $value, 0, $width - 1 ) . '…';
	}
}
