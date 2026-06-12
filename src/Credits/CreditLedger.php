<?php
/**
 * Credit ledger.
 *
 * @package AfnanAbbasi\MarketplaceLeads
 */

namespace AfnanAbbasi\MarketplaceLeads\Credits;

defined( 'ABSPATH' ) || exit;

/**
 * An append-only credit ledger backed by a custom table.
 *
 * A provider's balance is the SUM of all ledger deltas. Recording each
 * movement — rather than mutating a single balance column — keeps a complete,
 * auditable history of how every credit was granted or spent.
 */
class CreditLedger {

	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'ml_credit_ledger';
	}

	/**
	 * Create the ledger table via dbDelta (idempotent — safe to re-run).
	 */
	public static function create_table(): void {
		global $wpdb;

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			provider_id BIGINT(20) UNSIGNED NOT NULL,
			delta       INT(11) NOT NULL,
			reason      VARCHAR(60) NOT NULL DEFAULT '',
			reference   VARCHAR(100) NOT NULL DEFAULT '',
			created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY provider_id (provider_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Current balance for a provider.
	 */
	public static function get_balance( int $provider_id ): int {
		global $wpdb;

		$table = self::table();

		// The table name is derived from $wpdb->prefix (not user input);
		// the provider_id is still bound through prepare().
		$balance = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(delta), 0) FROM {$table} WHERE provider_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$provider_id
			)
		);

		return (int) $balance;
	}

	/**
	 * Record a credit movement. Positive delta = grant, negative = spend.
	 */
	public static function record( int $provider_id, int $delta, string $reason, string $reference = '' ): bool {
		global $wpdb;

		$inserted = $wpdb->insert(
			self::table(),
			array(
				'provider_id' => $provider_id,
				'delta'       => $delta,
				'reason'      => substr( $reason, 0, 60 ),
				'reference'   => substr( $reference, 0, 100 ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		return false !== $inserted;
	}

	/**
	 * Attempt to spend credits. Returns false if the provider can't afford it.
	 *
	 * NOTE: In production this balance check + debit would run inside a
	 * transaction (or use SELECT ... FOR UPDATE) so two concurrent unlock
	 * requests can't both pass the check. Kept straightforward here.
	 */
	public static function debit( int $provider_id, int $amount, string $reason, string $reference = '' ): bool {
		$amount = abs( $amount );

		if ( self::get_balance( $provider_id ) < $amount ) {
			return false;
		}

		return self::record( $provider_id, - $amount, $reason, $reference );
	}
}
