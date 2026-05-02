<?php
/**
 * MCP server bootstrap — registers abilities and the test/prod servers.
 *
 * @package Talaxie\Core
 */

declare(strict_types=1);

namespace Talaxie\Core\Mcp;

use Talaxie\Core\Mcp\Abilities\AbilityInterface;
use Talaxie\Core\Mcp\Abilities\Audit\ListAudit;
use Talaxie\Core\Mcp\Abilities\Media\DeleteMedia;
use Talaxie\Core\Mcp\Abilities\Media\ListMedia;
use Talaxie\Core\Mcp\Abilities\Media\UploadMedia;
use Talaxie\Core\Mcp\Abilities\Pages\CreatePage;
use Talaxie\Core\Mcp\Abilities\Pages\DeletePage;
use Talaxie\Core\Mcp\Abilities\Pages\GetPage;
use Talaxie\Core\Mcp\Abilities\Pages\ListPages;
use Talaxie\Core\Mcp\Abilities\Pages\UpdatePage;
use Talaxie\Core\Mcp\Abilities\Posts\CreatePost;
use Talaxie\Core\Mcp\Abilities\Posts\GetPost;
use Talaxie\Core\Mcp\Abilities\Posts\ListPosts;
use Talaxie\Core\Mcp\Abilities\Posts\UpdatePost;
use Talaxie\Core\Mcp\Abilities\Site\GetInfo;
use Talaxie\Core\Mcp\Audit\AuditLogger;
use Talaxie\Core\Mcp\Audit\AuditPostType;
use Talaxie\Core\Mcp\Audit\AuditRetention;
use WP\MCP\Core\McpAdapter;
use WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use WP\MCP\Transport\HttpTransport;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every ability into core's Abilities API and exposes them via two
 * MCP servers:
 *
 *   - talaxie-mcp-test-server : every ability, used from the test instance.
 *   - talaxie-mcp-prod-server : abilities whose is_allowed_on_production()
 *                               returns true. The only server reachable on
 *                               an instance whose environment_type === 'production'.
 */
final class Server {

	public const CATEGORY      = 'talaxie-core';
	public const SERVER_PROD   = 'talaxie-mcp-prod-server';
	public const SERVER_TEST   = 'talaxie-mcp-test-server';

	/**
	 * Hook the bootstrap into WordPress.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( AuditPostType::class, 'register' ) );
		AuditLogger::register();
		AuditRetention::register();
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
		add_action( 'mcp_adapter_init', array( self::class, 'register_servers' ) );
	}

	/**
	 * Class names of every ability shipped by talaxie-core.
	 *
	 * @return list<class-string<AbilityInterface>>
	 */
	public static function abilities(): array {
		return array(
			GetInfo::class,
			ListPosts::class,
			GetPost::class,
			CreatePost::class,
			UpdatePost::class,
			ListPages::class,
			GetPage::class,
			CreatePage::class,
			UpdatePage::class,
			DeletePage::class,
			ListMedia::class,
			UploadMedia::class,
			DeleteMedia::class,
			ListAudit::class,
		);
	}

	/**
	 * Register the dedicated ability category.
	 *
	 * @return void
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Talaxie Core', 'talaxie-core' ),
				'description' => __( 'Abilities exposed by the talaxie-core plugin.', 'talaxie-core' ),
			)
		);
	}

	/**
	 * Register every ability at the right WordPress hook.
	 *
	 * @return void
	 */
	public static function register_abilities(): void {
		foreach ( self::abilities() as $ability ) {
			$ability::register();
		}
	}

	/**
	 * Create the two MCP servers that surface the registered abilities.
	 *
	 * @param McpAdapter $adapter The MCP adapter singleton.
	 *
	 * @return void
	 */
	public static function register_servers( McpAdapter $adapter ): void {
		if ( ! class_exists( '\\' . McpAdapter::class ) ) {
			return;
		}

		$is_production = 'production' === wp_get_environment_type();

		$prod_tools = self::filter_for_production( self::abilities() );

		$adapter->create_server(
			self::SERVER_PROD,
			'mcp',
			self::SERVER_PROD,
			__( 'Talaxie MCP — production', 'talaxie-core' ),
			__( 'Production-safe abilities exposed by the Talaxie WordPress site.', 'talaxie-core' ),
			'v1.0.0',
			array( HttpTransport::class ),
			ErrorLogMcpErrorHandler::class,
			null,
			array_map( static fn( string $class ): string => $class::name(), $prod_tools ),
			array(),
			array()
		);

		if ( ! $is_production ) {
			$test_tools = self::abilities();
			$adapter->create_server(
				self::SERVER_TEST,
				'mcp',
				self::SERVER_TEST,
				__( 'Talaxie MCP — test', 'talaxie-core' ),
				__( 'All abilities, including destructive ones. Disabled on production instances.', 'talaxie-core' ),
				'v1.0.0',
				array( HttpTransport::class ),
				ErrorLogMcpErrorHandler::class,
				null,
				array_map( static fn( string $class ): string => $class::name(), $test_tools ),
				array(),
				array()
			);
		}
	}

	/**
	 * Keep only abilities flagged as production-safe.
	 *
	 * @param list<class-string<AbilityInterface>> $abilities Ability classes.
	 *
	 * @return list<class-string<AbilityInterface>>
	 */
	private static function filter_for_production( array $abilities ): array {
		return array_values(
			array_filter(
				$abilities,
				static fn( string $class ): bool => $class::is_allowed_on_production()
			)
		);
	}
}
