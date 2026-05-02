<?php
/**
 * Plugin bootstrap.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core;

defined( 'ABSPATH' ) || exit;

use Talaxie\Core\Mcp\Audit\AuditRetention;
use Talaxie\Core\Mcp\DevMode;
use Talaxie\Core\Mcp\Drift\RestController as DriftRestController;
use Talaxie\Core\Mcp\Server as McpServer;
use Talaxie\Core\Mcp\Sudo\AdminPage as SudoAdminPage;
use Talaxie\Core\Mcp\Sudo\CliCommand as SudoCliCommand;
use Talaxie\Core\Mcp\Sudo\RestController as SudoRestController;
use Talaxie\Core\Mcp\Sudo\TokenSchema;
use Talaxie\Core\PostTypes\Contributor;
use Talaxie\Core\PostTypes\Release;
use Talaxie\Core\Roles\AiBotRole;
use Talaxie\Core\Roles\Capabilities;
use Talaxie\Core\Taxonomies\Component;

/**
 * Wires the plugin into WordPress.
 */
final class Plugin {

	/**
	 * Register all WordPress hooks for this plugin.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'load_textdomain' ) );
		add_action( 'init', array( Release::class, 'register' ) );
		add_action( 'init', array( Contributor::class, 'register' ) );
		add_action( 'init', array( Component::class, 'register' ) );
		McpServer::register();
		SudoAdminPage::register();
		SudoRestController::register();
		SudoCliCommand::register();
		DriftRestController::register();
		DevMode::register();
		register_activation_hook( TALAXIE_CORE_FILE, array( self::class, 'activate' ) );
		register_deactivation_hook( TALAXIE_CORE_FILE, array( self::class, 'deactivate' ) );
	}

	/**
	 * Load the plugin translations.
	 *
	 * @return void
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain(
			'talaxie-core',
			false,
			dirname( plugin_basename( TALAXIE_CORE_FILE ) ) . '/languages'
		);
	}

	/**
	 * Runs on plugin activation.
	 *
	 * Re-registers post types and taxonomies before flushing rewrite rules so
	 * the public URLs (/release/, /contributor/, /component/...) become
	 * available immediately after activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Release::register();
		Contributor::register();
		Component::register();
		AiBotRole::register();
		Capabilities::grant_release_caps();
		TokenSchema::install();
		AuditRetention::activate();
		flush_rewrite_rules();
		update_option( 'talaxie_core_version', TALAXIE_CORE_VERSION );
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		AuditRetention::deactivate();
		flush_rewrite_rules();
	}
}
