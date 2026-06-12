<?php
/**
 * Uninstall cleanup. Runs only when the user deletes the plugin from WordPress.
 *
 * @package AfnanAbbasi\MarketplaceLeads
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop the credit ledger table.
$table = $wpdb->prefix . 'ml_credit_ledger';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB

// Remove options.
delete_option( 'ml_lead_unlock_cost' );
delete_option( 'ml_starter_credits' );
delete_option( 'ml_db_version' );

// Remove the custom role.
remove_role( 'ml_provider' );

// Lead posts and provider user meta are intentionally preserved so an
// accidental delete does not destroy business data. Remove manually if needed.
