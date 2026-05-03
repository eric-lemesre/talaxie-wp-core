<?php
/**
 * PHPUnit bootstrap for talaxie-core integration tests.
 *
 * Loads the standard WordPress test library and activates the plugin
 * inside the test runtime so every ability, role and table exists.
 *
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

$tests_dir = getenv( 'WP_TESTS_DIR' );
if ( false === $tests_dir || '' === $tests_dir ) {
	$tests_dir = '/tmp/wordpress-tests-lib';
}
$tests_dir = rtrim( $tests_dir, '/\\' );

if ( ! file_exists( $tests_dir . '/includes/functions.php' ) ) {
	fwrite(
		STDERR,
		"Could not find {$tests_dir}/includes/functions.php — run bin/install-wp-tests.sh first.\n"
	);
	exit( 1 );
}

require_once $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		// Load mcp-adapter if it is installed alongside the plugin under
		// test (typical local layout). Absent on CI: tests that require
		// the adapter must mark themselves skipped.
		$adapter = dirname( __DIR__, 2 ) . '/mcp-adapter/mcp-adapter.php';
		if ( file_exists( $adapter ) ) {
			require $adapter;
		}
		require dirname( __DIR__ ) . '/talaxie-core.php';
	}
);

tests_add_filter(
	'plugins_loaded',
	static function (): void {
		// Ensure the role, custom caps and sudo tokens table exist for every
		// test run, even when the activation hook did not fire.
		\Talaxie\Core\Roles\AiBotRole::unregister();
		\Talaxie\Core\Roles\AiBotRole::register();
		\Talaxie\Core\Roles\Capabilities::grant_release_caps();
		\Talaxie\Core\Mcp\Sudo\TokenSchema::install();
	},
	20
);

require $tests_dir . '/includes/bootstrap.php';
