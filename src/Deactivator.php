<?php
/**
 * Deactivation routine.
 *
 * @package AfnanAbbasi\MarketplaceLeads
 */

namespace AfnanAbbasi\MarketplaceLeads;

defined( 'ABSPATH' ) || exit;

/**
 * Runs when the plugin is deactivated. Intentionally non-destructive:
 * the credit ledger, leads, and provider data are preserved so toggling the
 * plugin off and on again loses nothing. Full teardown lives in uninstall.php.
 */
class Deactivator {

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
