<?php
/**
 * Providers approval screen.
 *
 * @package AfnanAbbasi\MarketplaceLeads
 */

namespace AfnanAbbasi\MarketplaceLeads\Admin;

use AfnanAbbasi\MarketplaceLeads\Providers\ProviderManager;
use AfnanAbbasi\MarketplaceLeads\Roles\Roles;
use AfnanAbbasi\MarketplaceLeads\Credits\CreditLedger;

defined( 'ABSPATH' ) || exit;

/**
 * Lists providers and lets an administrator approve or reject them. Actions
 * run through admin-post.php and are protected by capability checks and nonces.
 */
class ProvidersPage {

	public function register_hooks(): void {
		add_action( 'admin_post_ml_approve_provider', array( $this, 'handle_action' ) );
		add_action( 'admin_post_ml_reject_provider', array( $this, 'handle_action' ) );
	}

	public function handle_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'marketplace-leads' ) );
		}

		$user_id = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
		$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		check_admin_referer( $action . '_' . $user_id );

		if ( $user_id ) {
			if ( 'ml_approve_provider' === $action ) {
				ProviderManager::approve( $user_id );
			} elseif ( 'ml_reject_provider' === $action ) {
				ProviderManager::reject( $user_id );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=marketplace-leads&updated=1' ) );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$providers = get_users(
			array(
				'role'   => Roles::PROVIDER,
				'number' => 100,
			)
		);

		echo '<div class="wrap"><h1>' . esc_html__( 'Marketplace Providers', 'marketplace-leads' ) . '</h1>';

		if ( empty( $providers ) ) {
			echo '<p>' . esc_html__( 'No providers have registered yet.', 'marketplace-leads' ) . '</p></div>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Provider', 'marketplace-leads' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'marketplace-leads' ) . '</th>';
		echo '<th>' . esc_html__( 'Credits', 'marketplace-leads' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'marketplace-leads' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $providers as $provider ) {
			$status  = ProviderManager::get_status( $provider->ID );
			$balance = CreditLedger::get_balance( $provider->ID );

			$approve_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=ml_approve_provider&user=' . $provider->ID ),
				'ml_approve_provider_' . $provider->ID
			);
			$reject_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=ml_reject_provider&user=' . $provider->ID ),
				'ml_reject_provider_' . $provider->ID
			);

			echo '<tr>';
			echo '<td>' . esc_html( $provider->display_name ) . '<br><small>' . esc_html( $provider->user_email ) . '</small></td>';
			echo '<td>' . esc_html( ucfirst( $status ) ) . '</td>';
			echo '<td>' . esc_html( (string) $balance ) . '</td>';
			echo '<td>';
			echo '<a class="button button-primary" href="' . esc_url( $approve_url ) . '">' . esc_html__( 'Approve', 'marketplace-leads' ) . '</a> ';
			echo '<a class="button" href="' . esc_url( $reject_url ) . '">' . esc_html__( 'Reject', 'marketplace-leads' ) . '</a>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}
}
