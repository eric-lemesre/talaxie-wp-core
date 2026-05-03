<?php
/**
 * Lightweight WP_CLI shim used by the InvokeCommand tests.
 *
 * The real WP-CLI runtime is not loaded inside PHPUnit, so we substitute
 * a class that captures log/error/table output instead of writing to
 * STDOUT/STDERR.
 *
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace {

	if ( ! class_exists( '\WP_CLI' ) ) {

		class WP_CLI { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

			/**
			 * Log lines collected during the test.
			 *
			 * @var array<int, string>
			 */
			private static array $logs = array();

			/**
			 * Error messages collected during the test.
			 *
			 * @var array<int, string>
			 */
			private static array $errors = array();

			/**
			 * Last formatted table emitted via Utils\format_items.
			 *
			 * @var array<int, array<string, mixed>>|null
			 */
			private static ?array $table = null;

			public static function reset(): void {
				self::$logs   = array();
				self::$errors = array();
				self::$table  = null;
			}

			public static function captured_logs(): array {
				return self::$logs;
			}

			public static function captured_errors(): array {
				return self::$errors;
			}

			public static function captured_table(): ?array {
				return self::$table;
			}

			public static function record_table( array $items ): void {
				self::$table = $items;
			}

			public static function log( string $message ): void {
				self::$logs[] = $message;
			}

			public static function success( string $message ): void {
				self::$logs[] = '[OK] ' . $message;
			}

			public static function error( string $message ): void {
				self::$errors[] = $message;
				throw new \RuntimeException( 'WP_CLI::error: ' . $message );
			}

			public static function error_multi_line( array $lines ): void {
				foreach ( $lines as $line ) {
					self::$errors[] = (string) $line;
				}
			}

			public static function halt( int $code ): void {
				throw new \RuntimeException( 'WP_CLI::halt: ' . $code );
			}

			public static function add_command( string $name, $callable, array $args = array() ): void {
				// Tests invoke the static handler methods directly; no-op here.
				unset( $name, $callable, $args );
			}
		}
	}
}

namespace WP_CLI\Utils {

	if ( ! function_exists( 'WP_CLI\\Utils\\format_items' ) ) {

		/**
		 * Stand-in for the real format_items() so tests can assert what was rendered.
		 *
		 * @param string                            $format Output format.
		 * @param array<int, array<string, mixed>>  $items  Rows to render.
		 * @param array<int, string>                $fields Field list.
		 *
		 * @return void
		 */
		function format_items( string $format, array $items, array $fields ): void {
			unset( $format, $fields );
			\WP_CLI::record_table( $items );
		}
	}
}
