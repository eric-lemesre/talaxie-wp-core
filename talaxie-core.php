<?php
/**
 * Plugin Name:       Talaxie Core
 * Plugin URI:        https://github.com/eric-lemesre/talaxie-wp-core
 * Description:       Companion plugin for the Talaxie community website. Provides custom post types (releases, contributors), a "component" taxonomy, and the integration entry points with GitHub, Discord and Discourse.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Talaxie Community
 * Author URI:        https://talaxie.org
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       talaxie-core
 * Domain Path:       /languages
 *
 * @package Talaxie\Core
 */

defined( 'ABSPATH' ) || exit;

define( 'TALAXIE_CORE_VERSION', '0.1.0' );
define( 'TALAXIE_CORE_FILE', __FILE__ );
define( 'TALAXIE_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'TALAXIE_CORE_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader (required — installs the PSR-4 namespace Talaxie\Core).
$talaxie_core_autoload = TALAXIE_CORE_DIR . 'vendor/autoload.php';
if ( ! file_exists( $talaxie_core_autoload ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Talaxie Core: vendor/autoload.php is missing. Run "composer install" inside the plugin folder.', 'talaxie-core' );
			echo '</p></div>';
		}
	);
	return;
}
require_once $talaxie_core_autoload;

\Talaxie\Core\Plugin::register();
