<?php
/**
 * Provider lifecycle management.
 *
 * @package AfnanAbbasi\MarketplaceLeads
 */

namespace AfnanAbbasi\MarketplaceLeads\Providers;

use AfnanAbbasi\MarketplaceLeads\Roles\Roles;
use AfnanAbbasi\MarketplaceLeads\Credits\CreditLedger;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the provider state machine: pending -> approved | rejected.
 * Each transition fires an action hook so notifications (email, WhatsApp,
 * etc.) can be attached without touching this class.
 */
class ProviderManager {

	const STATUS_META = '_ml_provider_status';

	const STATUS_PENDING  = 'pending';
	const STATUS_APPROVED = 'approved';
	const STATUS_REJECTED = 'rejected';

	/**
	 * Promote an existing user to a (pending) provider and grant starter credits.
	 */
	public static function register_provider( int $user_id ): void {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}

		$user->add_role( Roles::PROVIDER );
		update_user_meta( $user_id, self::STATUS_META, self::STATUS_PENDING );

		$starter = (int) get_option( 'ml_starter_credits', 20 );
		if ( $starter > 0 ) {
			CreditLedger::record( $user_id, $starter, 'starter_credits' );
		}

		/**
		 * Fires after a provider registers and is awaiting approval.
		 *
		 * @param int $user_id The new provider's user ID.
		 */
		do_action( 'ml_provider_registered', $user_id );
	}

	public static function approve( int $user_id ): void {
		update_user_meta( $user_id, self::STATUS_META, self::STATUS_APPROVED );

		/** Fires when an admin approves a provider. */
		do_action( 'ml_provider_approved', $user_id );
	}

	public static function reject( int $user_id ): void {
		update_user_meta( $user_id, self::STATUS_META, self::STATUS_REJECTED );

		/** Fires when an admin rejects a provider. */
		do_action( 'ml_provider_rejected', $user_id );
	}

	public static function get_status( int $user_id ): string {
		$status = get_user_meta( $user_id, self::STATUS_META, true );

		return $status ? (string) $status : self::STATUS_PENDING;
	}

	public static function is_provider( int $user_id ): bool {
		$user = get_user_by( 'id', $user_id );

		return $user && in_array( Roles::PROVIDER, (array) $user->roles, true );
	}

	public static function is_approved( int $user_id ): bool {
		return self::is_provider( $user_id ) && self::STATUS_APPROVED === self::get_status( $user_id );
	}
}
