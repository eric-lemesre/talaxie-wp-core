<?php
/**
 * Optional dev-mode bypass — disables the capability gate entirely.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Active when the `TALAXIE_MCP_DEV_MODE` constant is defined and truthy
 * AND the environment type is local/development.
 *
 * In production-like environments, the constant is silently ignored and
 * an admin notice flags the misconfiguration.
 */
final class DevMode {

	public const CONSTANT = 'TALAXIE_MCP_DEV_MODE';
	public const FILTER   = 'talaxie_mcp_dev_mode_active';

	/**
	 * Hook the dev-mode notice.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'render_notice' ) );
		add_action( 'network_admin_notices', array( self::class, 'render_notice' ) );
	}

	/**
	 * Whether the dev-mode bypass is active *and* allowed for the current
	 * environment.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		$constant_set = defined( self::CONSTANT ) && constant( self::CONSTANT );
		$active       = $constant_set && self::is_environment_safe();
		/**
		 * Filter the resolved dev-mode flag.
		 *
		 * Useful for integration tests that need to toggle the bypass
		 * without redefining the wp-config constant. Production code
		 * should leave this filter alone.
		 *
		 * @param bool $active Whether dev-mode is active.
		 */
		return (bool) apply_filters( self::FILTER, $active ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- self::FILTER expands to talaxie_mcp_dev_mode_active.
	}

	/**
	 * Whether the environment is safe to host the dev-mode bypass.
	 *
	 * @return bool
	 */
	public static function is_environment_safe(): bool {
		$env  = function_exists( 'wp_get_environment_type' ) ? (string) wp_get_environment_type() : 'production';
		$safe = in_array( $env, array( 'local', 'development' ), true );

		/**
		 * Filter the environment-safety check used by DevMode.
		 *
		 * Tests use this to flip the verdict without redefining the
		 * underlying WP_ENVIRONMENT_TYPE constant (which is sticky).
		 *
		 * @param bool   $safe Result from wp_get_environment_type().
		 * @param string $env  Resolved environment type string.
		 */
		return (bool) apply_filters( 'talaxie_mcp_dev_mode_environment_safe', $safe, $env );
	}

	/**
	 * Show a permanent red banner when dev-mode is active so it cannot be
	 * forgotten on a long-lived environment. Also flag the unsafe case
	 * (constant set but environment is staging/prod).
	 *
	 * @return void
	 */
	public static function render_notice(): void {
		if ( ! defined( self::CONSTANT ) || ! constant( self::CONSTANT ) ) {
			return;
		}

		if ( ! self::is_environment_safe() ) {
			echo '<div class="notice notice-error" style="border-left-color:#b32d2e;">';
			echo '<p><strong>' . esc_html__( 'Talaxie MCP — TALAXIE_MCP_DEV_MODE is set but ignored.', 'talaxie-core' ) . '</strong> ';
			echo esc_html__( 'The dev-mode bypass only activates when WP_ENVIRONMENT_TYPE is local or development. Update wp-config or remove the constant.', 'talaxie-core' );
			echo '</p></div>';
			return;
		}

		echo '<div class="notice notice-error" style="border-left-color:#b32d2e;background:#fef1f1;">';
		echo '<p><strong style="color:#b32d2e;">' . esc_html__( 'Talaxie MCP — DEV MODE ACTIVE.', 'talaxie-core' ) . '</strong> ';
		echo esc_html__( 'The capability gate is disabled. Every MCP ability accepts every caller. Remove TALAXIE_MCP_DEV_MODE before going live.', 'talaxie-core' );
		echo '</p></div>';
	}
}
