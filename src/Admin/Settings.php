<?php
/**
 * Settings screen.
 *
 * @package AfnanAbbasi\MarketplaceLeads
 */

namespace AfnanAbbasi\MarketplaceLeads\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers plugin settings via the WordPress Settings API and renders the
 * settings form. Each option has a sanitize callback and a default.
 */
class Settings {

	const GROUP = 'ml_settings_group';
	const PAGE  = 'ml-settings';

	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings(): void {
		register_setting(
			self::GROUP,
			'ml_lead_unlock_cost',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 5,
			)
		);

		register_setting(
			self::GROUP,
			'ml_starter_credits',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 20,
			)
		);

		add_settings_section( 'ml_main', __( 'Credit settings', 'marketplace-leads' ), '__return_false', self::PAGE );

		add_settings_field( 'ml_lead_unlock_cost', __( 'Credits to unlock a lead', 'marketplace-leads' ), array( $this, 'field_unlock_cost' ), self::PAGE, 'ml_main' );
		add_settings_field( 'ml_starter_credits', __( 'Starter credits on registration', 'marketplace-leads' ), array( $this, 'field_starter_credits' ), self::PAGE, 'ml_main' );
	}

	public function field_unlock_cost(): void {
		printf( '<input type="number" min="0" name="ml_lead_unlock_cost" value="%d" />', (int) get_option( 'ml_lead_unlock_cost', 5 ) );
	}

	public function field_starter_credits(): void {
		printf( '<input type="number" min="0" name="ml_starter_credits" value="%d" />', (int) get_option( 'ml_starter_credits', 20 ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Marketplace Settings', 'marketplace-leads' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP );
		do_settings_sections( self::PAGE );
		submit_button();
		echo '</form></div>';
	}
}
