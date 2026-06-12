<?php
/**
 * Plugin Name:       Marketplace Leads
 * Plugin URI:        https://github.com/afnanabbasi/marketplace-leads
 * Description:       A standalone demonstrator plugin: a lead marketplace with role-based provider registration, an admin approval workflow, a credit ledger, and a curated REST API. Built to showcase clean, object-oriented WordPress engineering.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Afnan Abbasi
 * Author URI:        https://afnanabbasi.com
 * License:           MIT
 * Text Domain:       marketplace-leads
 *
 * @package AfnanAbbasi\MarketplaceLeads
 */

namespace AfnanAbbasi\MarketplaceLeads;

defined( 'ABSPATH' ) || exit;

define( 'ML_VERSION', '1.0.0' );
define( 'ML_PLUGIN_FILE', __FILE__ );
define( 'ML_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ML_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Minimal PSR-4 style autoloader.
 *
 * Maps AfnanAbbasi\MarketplaceLeads\Foo\Bar to src/Foo/Bar.php so the plugin
 * runs with zero external dependencies (no `composer install` required).
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix   = __NAMESPACE__ . '\\';
		$base_dir = ML_PLUGIN_DIR . 'src/';

		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

// Activation / deactivation lifecycle.
register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Deactivator::class, 'deactivate' ) );

// Boot the plugin once all other plugins are loaded.
add_action(
	'plugins_loaded',
	static function () {
		Plugin::instance()->init();
	}
);
