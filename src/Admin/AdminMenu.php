<?php
/**
 * Admin menu registration.
 *
 * @package AfnanAbbasi\MarketplaceLeads
 */

namespace AfnanAbbasi\MarketplaceLeads\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the top-level "Marketplace" menu and delegates rendering to the
 * Settings and Providers page classes. The Leads CPT attaches itself here via
 * its `show_in_menu` argument.
 */
class AdminMenu {

	/** @var Settings */
	private $settings;

	/** @var ProvidersPage */
	private $providers;

	public function __construct( Settings $settings, ProvidersPage $providers ) {
		$this->settings  = $settings;
		$this->providers = $providers;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'Marketplace Leads', 'marketplace-leads' ),
			__( 'Marketplace', 'marketplace-leads' ),
			'manage_options',
			'marketplace-leads',
			array( $this->providers, 'render' ),
			'dashicons-networking',
			26
		);

		add_submenu_page(
			'marketplace-leads',
			__( 'Providers', 'marketplace-leads' ),
			__( 'Providers', 'marketplace-leads' ),
			'manage_options',
			'marketplace-leads',
			array( $this->providers, 'render' )
		);

		add_submenu_page(
			'marketplace-leads',
			__( 'Settings', 'marketplace-leads' ),
			__( 'Settings', 'marketplace-leads' ),
			'manage_options',
			'ml-settings',
			array( $this->settings, 'render' )
		);
	}
}
