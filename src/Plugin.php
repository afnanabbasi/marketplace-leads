<?php
/**
 * Main plugin orchestrator.
 *
 * @package AfnanAbbasi\MarketplaceLeads
 */

namespace AfnanAbbasi\MarketplaceLeads;

use AfnanAbbasi\MarketplaceLeads\PostTypes\Lead;
use AfnanAbbasi\MarketplaceLeads\Rest\RestController;
use AfnanAbbasi\MarketplaceLeads\Admin\Settings;
use AfnanAbbasi\MarketplaceLeads\Admin\ProvidersPage;
use AfnanAbbasi\MarketplaceLeads\Admin\AdminMenu;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's modules into WordPress. Kept as a thin coordinator:
 * each concern (post types, REST, admin) lives in its own class.
 */
final class Plugin {

	/**
	 * @var Plugin|null
	 */
	private static $instance = null;

	private function __construct() {}

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register every component's hooks.
	 */
	public function init(): void {
		load_plugin_textdomain(
			'marketplace-leads',
			false,
			dirname( plugin_basename( ML_PLUGIN_FILE ) ) . '/languages'
		);

		// Content type + curated REST API (front and back end).
		( new Lead() )->register_hooks();
		( new RestController() )->register_hooks();

		// Admin-only screens.
		if ( is_admin() ) {
			$settings  = new Settings();
			$providers = new ProvidersPage();

			$settings->register_hooks();
			$providers->register_hooks();
			( new AdminMenu( $settings, $providers ) )->register_hooks();
		}
	}
}
