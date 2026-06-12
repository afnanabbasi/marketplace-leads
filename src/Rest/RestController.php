<?php
/**
 * REST API controller.
 *
 * @package AfnanAbbasi\MarketplaceLeads
 */

namespace AfnanAbbasi\MarketplaceLeads\Rest;

use AfnanAbbasi\MarketplaceLeads\PostTypes\Lead;
use AfnanAbbasi\MarketplaceLeads\Providers\ProviderManager;
use AfnanAbbasi\MarketplaceLeads\Credits\CreditLedger;
use AfnanAbbasi\MarketplaceLeads\Roles\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Curated REST API under /wp-json/marketplace-leads/v1.
 *
 * Every write route has an explicit permission callback, every input is
 * sanitized/validated, and errors are returned as WP_Error with meaningful
 * HTTP status codes.
 */
class RestController {

	const REST_NS = 'marketplace-leads/v1';

	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NS,
			'/leads',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_leads' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_lead' ),
					'permission_callback' => array( $this, 'can_submit_lead' ),
					'args'                => $this->lead_args(),
				),
			)
		);

		register_rest_route(
			self::REST_NS,
			'/leads/(?P<id>\d+)/unlock',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'unlock_lead' ),
				'permission_callback' => array( $this, 'can_unlock_lead' ),
			)
		);

		register_rest_route(
			self::REST_NS,
			'/providers/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'register_provider' ),
				'permission_callback' => static function () {
					return is_user_logged_in();
				},
			)
		);

		register_rest_route(
			self::REST_NS,
			'/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_me' ),
				'permission_callback' => static function () {
					return is_user_logged_in();
				},
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Permission callbacks
	 * ------------------------------------------------------------------- */

	public function can_submit_lead(): bool {
		return is_user_logged_in();
	}

	/**
	 * Only a logged-in, approved provider with the unlock capability may unlock.
	 *
	 * @return true|\WP_Error
	 */
	public function can_unlock_lead() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'ml_not_logged_in', __( 'You must be logged in.', 'marketplace-leads' ), array( 'status' => 401 ) );
		}

		if ( ! current_user_can( Roles::CAP_UNLOCK_LEADS ) ) {
			return new \WP_Error( 'ml_forbidden', __( 'Your account cannot unlock leads.', 'marketplace-leads' ), array( 'status' => 403 ) );
		}

		if ( ! ProviderManager::is_approved( get_current_user_id() ) ) {
			return new \WP_Error( 'ml_not_approved', __( 'Your provider account is awaiting approval.', 'marketplace-leads' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Route callbacks
	 * ------------------------------------------------------------------- */

	public function list_leads( \WP_REST_Request $request ) {
		$query = new \WP_Query(
			array(
				'post_type'      => Lead::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'paged'          => max( 1, (int) $request->get_param( 'page' ) ),
			)
		);

		$leads = array_map(
			static function ( $post ) {
				return Lead::to_public_array( $post );
			},
			$query->posts
		);

		return rest_ensure_response( $leads );
	}

	public function create_lead( \WP_REST_Request $request ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => Lead::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => sanitize_text_field( (string) $request->get_param( 'title' ) ),
				'post_content' => sanitize_textarea_field( (string) $request->get_param( 'description' ) ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return new \WP_Error( 'ml_create_failed', __( 'Could not create lead.', 'marketplace-leads' ), array( 'status' => 500 ) );
		}

		update_post_meta( $post_id, '_ml_category', sanitize_text_field( (string) $request->get_param( 'category' ) ) );
		update_post_meta( $post_id, '_ml_location', sanitize_text_field( (string) $request->get_param( 'location' ) ) );
		update_post_meta( $post_id, '_ml_budget', absint( $request->get_param( 'budget' ) ) );
		update_post_meta( $post_id, '_ml_contact_email', sanitize_email( (string) $request->get_param( 'contact_email' ) ) );
		update_post_meta( $post_id, '_ml_contact_phone', sanitize_text_field( (string) $request->get_param( 'contact_phone' ) ) );

		$response = rest_ensure_response( Lead::to_public_array( get_post( $post_id ) ) );
		$response->set_status( 201 );

		return $response;
	}

	public function unlock_lead( \WP_REST_Request $request ) {
		$lead_id     = (int) $request->get_param( 'id' );
		$provider_id = get_current_user_id();

		$lead = get_post( $lead_id );
		if ( ! $lead || Lead::POST_TYPE !== $lead->post_type || 'publish' !== $lead->post_status ) {
			return new \WP_Error( 'ml_not_found', __( 'Lead not found.', 'marketplace-leads' ), array( 'status' => 404 ) );
		}

		// Idempotent: if already unlocked, return the contact info without re-charging.
		if ( $this->has_unlocked( $provider_id, $lead_id ) ) {
			return rest_ensure_response(
				array(
					'lead_id' => $lead_id,
					'contact' => Lead::contact_details( $lead_id ),
					'charged' => 0,
					'balance' => CreditLedger::get_balance( $provider_id ),
				)
			);
		}

		$cost = (int) get_option( 'ml_lead_unlock_cost', 5 );

		if ( ! CreditLedger::debit( $provider_id, $cost, 'unlock_lead', (string) $lead_id ) ) {
			return new \WP_Error(
				'ml_insufficient_credits',
				__( 'Not enough credits to unlock this lead.', 'marketplace-leads' ),
				array(
					'status'  => 402,
					'balance' => CreditLedger::get_balance( $provider_id ),
					'cost'    => $cost,
				)
			);
		}

		$this->mark_unlocked( $provider_id, $lead_id );

		return rest_ensure_response(
			array(
				'lead_id' => $lead_id,
				'contact' => Lead::contact_details( $lead_id ),
				'charged' => $cost,
				'balance' => CreditLedger::get_balance( $provider_id ),
			)
		);
	}

	public function register_provider( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		if ( ProviderManager::is_provider( $user_id ) ) {
			return new \WP_Error( 'ml_already_provider', __( 'You are already registered as a provider.', 'marketplace-leads' ), array( 'status' => 409 ) );
		}

		ProviderManager::register_provider( $user_id );

		$response = rest_ensure_response(
			array(
				'status'  => ProviderManager::get_status( $user_id ),
				'balance' => CreditLedger::get_balance( $user_id ),
			)
		);
		$response->set_status( 201 );

		return $response;
	}

	public function get_me( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		return rest_ensure_response(
			array(
				'user_id'     => $user_id,
				'is_provider' => ProviderManager::is_provider( $user_id ),
				'status'      => ProviderManager::get_status( $user_id ),
				'balance'     => CreditLedger::get_balance( $user_id ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Argument schema for lead submission (drives WP's built-in validation).
	 *
	 * @return array<string, array>
	 */
	private function lead_args(): array {
		return array(
			'title'         => array(
				'required' => true,
				'type'     => 'string',
			),
			'description'   => array(
				'required' => true,
				'type'     => 'string',
			),
			'category'      => array( 'type' => 'string' ),
			'location'      => array( 'type' => 'string' ),
			'budget'        => array( 'type' => 'integer' ),
			'contact_email' => array(
				'type'   => 'string',
				'format' => 'email',
			),
			'contact_phone' => array( 'type' => 'string' ),
		);
	}

	private function unlocked_meta_key( int $lead_id ): string {
		return '_ml_unlocked_' . $lead_id;
	}

	private function has_unlocked( int $provider_id, int $lead_id ): bool {
		return (bool) get_user_meta( $provider_id, $this->unlocked_meta_key( $lead_id ), true );
	}

	private function mark_unlocked( int $provider_id, int $lead_id ): void {
		update_user_meta( $provider_id, $this->unlocked_meta_key( $lead_id ), time() );
	}
}
