<?php
/**
 * Activation routine.
 *
 * @package AfnanAbbasi\MarketplaceLeads
 */

namespace AfnanAbbasi\MarketplaceLeads;

use AfnanAbbasi\MarketplaceLeads\Roles\Roles;
use AfnanAbbasi\MarketplaceLeads\Credits\CreditLedger;
use AfnanAbbasi\MarketplaceLeads\PostTypes\Lead;

defined( 'ABSPATH' ) || exit;

/**
 * Runs once when the plugin is activated.
 */
class Activator {

	public static function activate(): void {
		Roles::register();
		CreditLedger::create_table();

		// Register the CPT in this request so rewrite rules flush cleanly.
		( new Lead() )->register_post_type();
		flush_rewrite_rules();

		// Seed default settings without overwriting existing values.
		add_option( 'ml_lead_unlock_cost', 5 );
		add_option( 'ml_starter_credits', 20 );
		add_option( 'ml_db_version', ML_VERSION );
	}
}
