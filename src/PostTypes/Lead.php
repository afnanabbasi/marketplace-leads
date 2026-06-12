<?php
/**
 * Lead custom post type.
 *
 * @package AfnanAbbasi\MarketplaceLeads
 */

namespace AfnanAbbasi\MarketplaceLeads\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a job/lead submitted by a client. Contact details are stored as
 * meta and only ever exposed through the REST API once a provider has paid to
 * unlock them.
 */
class Lead {

	const POST_TYPE = 'ml_lead';

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'        => __( 'Leads', 'marketplace-leads' ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'marketplace-leads',
				'supports'     => array( 'title', 'editor', 'custom-fields' ),
				// We expose a curated REST API instead of the default WP one,
				// so contact data is never leaked through core endpoints.
				'show_in_rest' => false,
				'menu_icon'    => 'dashicons-megaphone',
			)
		);
	}

	public function register_meta(): void {
		$fields = array(
			'_ml_category'      => 'string',
			'_ml_location'      => 'string',
			'_ml_budget'        => 'integer',
			'_ml_contact_email' => 'string',
			'_ml_contact_phone' => 'string',
		);

		foreach ( $fields as $key => $type ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => 'integer' === $type ? 'absint' : 'sanitize_text_field',
					'auth_callback'     => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Public-safe view of a lead. Deliberately omits contact details.
	 *
	 * @param \WP_Post $post Lead post.
	 * @return array<string, mixed>
	 */
	public static function to_public_array( \WP_Post $post ): array {
		return array(
			'id'       => (int) $post->ID,
			'title'    => get_the_title( $post ),
			'summary'  => wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ),
			'category' => (string) get_post_meta( $post->ID, '_ml_category', true ),
			'location' => (string) get_post_meta( $post->ID, '_ml_location', true ),
			'budget'   => (int) get_post_meta( $post->ID, '_ml_budget', true ),
			'created'  => get_post_time( 'c', true, $post ),
		);
	}

	/**
	 * Contact details — returned only to a provider who has unlocked the lead.
	 *
	 * @param int $lead_id Lead ID.
	 * @return array<string, string>
	 */
	public static function contact_details( int $lead_id ): array {
		return array(
			'email' => (string) get_post_meta( $lead_id, '_ml_contact_email', true ),
			'phone' => (string) get_post_meta( $lead_id, '_ml_contact_phone', true ),
		);
	}
}
