<?php
/**
 * Cleanup performed when the plugin is uninstalled (not just deactivated).
 *
 * Deletes only options and transients owned by this plugin. Custom post type
 * content (releases, contributors) is intentionally preserved — operators
 * who want a hard reset can drop their tables manually.
 *
 * @package Talaxie\Core
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'talaxie_core_version' );
delete_option( 'talaxie_core_settings' );

delete_site_transient( 'talaxie_core_github_releases' );
delete_site_transient( 'talaxie_core_discord_stats' );
