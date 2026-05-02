<?php
/**
 * Common contract for every MCP ability shipped by talaxie-core.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Implementations live under Talaxie\Core\Mcp\Abilities\<Domain>\.
 *
 * The plugin's MCP bootstrap walks every implementation, calls register()
 * during wp_abilities_api_init, and exposes the resulting ability names on
 * the appropriate MCP server (test or prod) based on
 * is_allowed_on_production().
 */
interface AbilityInterface {

	/**
	 * Fully qualified ability slug, e.g. "talaxie-core/posts/list".
	 *
	 * @return string
	 */
	public static function name(): string;

	/**
	 * Whether the ability may be exposed on the production MCP server.
	 *
	 * Destructive abilities should return false and rely on the test server
	 * (or a manual override constant) for execution.
	 *
	 * @return bool
	 */
	public static function is_allowed_on_production(): bool;

	/**
	 * Register the ability with WordPress core's Abilities API.
	 *
	 * Must be called inside the `wp_abilities_api_init` action.
	 *
	 * @return void
	 */
	public static function register(): void;
}
