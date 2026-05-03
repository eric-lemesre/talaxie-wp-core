<?php
/**
 * @package Talaxie\Core\Tests
 */

declare(strict_types=1);

namespace Talaxie\Core\Tests\Cli;

use Talaxie\Core\Mcp\Cli\InvokeCommand;
use Talaxie\Core\Roles\AiBotRole;
use WP_UnitTestCase;

/**
 * Exercises the resolve_input/resolve_user helpers indirectly through
 * the public surface, plus a smoke test of cmd_invoke against a real
 * registered ability.
 *
 * The cmd_* methods write to WP-CLI which doesn't exist in the test
 * environment — they are exercised via a small reflection helper that
 * substitutes a no-op WP-CLI shim so we can capture errors and output.
 *
 * @covers \Talaxie\Core\Mcp\Cli\InvokeCommand
 */
final class InvokeCommandTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// The Cli namespace expects WP-CLI calls. We shim it for the test runtime.
		if ( ! class_exists( '\WP_CLI' ) ) {
			require_once __DIR__ . '/wp-cli-shim.php';
		}
		\WP_CLI::reset();
	}

	public function test_cmd_list_returns_filtered_abilities(): void {
		InvokeCommand::cmd_list( array(), array( 'prefix' => 'talaxie-core/' ) );

		$rendered = \WP_CLI::captured_table();
		$this->assertNotNull( $rendered );
		$names = array_column( $rendered, 'name' );
		$this->assertContains( 'talaxie-core/site-get-info', $names );
		// Other-namespace abilities (mcp-adapter/...) must not leak through the prefix filter.
		foreach ( $names as $name ) {
			$this->assertStringStartsWith( 'talaxie-core/', $name );
		}
	}

	public function test_cmd_info_prints_input_schema(): void {
		InvokeCommand::cmd_info( array( 'talaxie-core/site-get-info' ), array() );

		$logs = \WP_CLI::captured_logs();
		$this->assertNotEmpty( $logs );
		$haystack = implode( "\n", $logs );
		$this->assertStringContainsString( 'Name:        talaxie-core/site-get-info', $haystack );
		$this->assertStringContainsString( '"properties"', $haystack );
	}

	public function test_cmd_invoke_runs_the_ability(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		InvokeCommand::cmd_invoke( array( 'talaxie-core/site-get-info' ), array() );

		$logs = \WP_CLI::captured_logs();
		$this->assertNotEmpty( $logs );
		$payload = json_decode( $logs[0], true );
		$this->assertIsArray( $payload );
		$this->assertArrayHasKey( 'name', $payload );
		$this->assertArrayHasKey( 'wp_version', $payload );
	}

	public function test_cmd_invoke_reports_permission_denied(): void {
		$this->setExpectedIncorrectUsage( 'WP_Ability::execute' );
		$bot = self::factory()->user->create( array( 'role' => AiBotRole::ROLE ) );
		wp_set_current_user( $bot );

		try {
			InvokeCommand::cmd_invoke(
				array( 'talaxie-core/users-create' ),
				array( 'input' => '{"login":"x","email":"x@example.com"}' )
			);
			$this->fail( 'Expected halt' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'WP_CLI::halt', $e->getMessage() );
		}

		$errors   = \WP_CLI::captured_errors();
		$haystack = implode( "\n", $errors );
		// WP_Ability::execute wraps gate errors into ability_invalid_permissions.
		$this->assertStringContainsString( 'ability_invalid_permissions', $haystack );
	}

	public function test_cmd_invoke_rejects_unknown_ability(): void {
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/is not registered/' );
		InvokeCommand::cmd_invoke( array( 'no-plugin/no-ability' ), array() );
	}

	public function test_cmd_invoke_rejects_invalid_json_input(): void {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/must be a JSON object/' );
		InvokeCommand::cmd_invoke(
			array( 'talaxie-core/site-get-info' ),
			array( 'input' => 'not-json' )
		);
	}
}
