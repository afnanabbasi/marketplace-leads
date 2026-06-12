<?php
/**
 * Custom roles and capabilities.
 *
 * @package AfnanAbbasi\MarketplaceLeads
 */

namespace AfnanAbbasi\MarketplaceLeads\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Defines the marketplace provider role and the custom capabilities used to
 * gate the REST API. Capabilities (rather than role-name checks) keep
 * authorization decisions explicit and testable.
 */
class Roles {

	const PROVIDER = 'ml_provider';

	const CAP_VIEW_LEADS   = 'ml_view_leads';
	const CAP_UNLOCK_LEADS = 'ml_unlock_leads';

	/**
	 * Create the provider role. Called on activation.
	 */
	public static function register(): void {
		add_role(
			self::PROVIDER,
			__( 'Marketplace Provider', 'marketplace-leads' ),
			array(
				'read'                 => true,
				self::CAP_VIEW_LEADS   => true,
				self::CAP_UNLOCK_LEADS => true,
			)
		);

		// Let administrators see the same capability surface.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAP_VIEW_LEADS );
			$admin->add_cap( self::CAP_UNLOCK_LEADS );
		}
	}

	/**
	 * Remove the role and capabilities. Called on uninstall.
	 */
	public static function unregister(): void {
		remove_role( self::PROVIDER );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->remove_cap( self::CAP_VIEW_LEADS );
			$admin->remove_cap( self::CAP_UNLOCK_LEADS );
		}
	}
}
