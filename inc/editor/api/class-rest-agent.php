<?php
/**
 * REST controller — agent loop endpoints.
 *
 * POST /agent/plan    — generates a plan from a user message
 * POST /agent/execute — runs an approved plan step-by-step
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_REST_Agent {

	const PLAN_TRANSIENT_TTL = 600; // 10 min

	private static $instance = null;
	private $namespace       = 'anchor-assistant/v1';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	private function init() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		$auth = function() { return current_user_can( 'manage_options' ); };

		register_rest_route( $this->namespace, '/agent/plan', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'generate_plan' ],
			'permission_callback' => $auth,
		] );
		register_rest_route( $this->namespace, '/agent/execute', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'execute_plan' ],
			'permission_callback' => $auth,
		] );
	}

	public function generate_plan( $request ) {
		$params  = $request->get_json_params();
		$message = (string) ( $params['message'] ?? '' );
		if ( $message === '' ) {
			return new WP_REST_Response( [ 'error' => 'Message is required.' ], 400 );
		}
		$context = [
			'open_files'        => is_array( $params['open_files'] ?? null ) ? $params['open_files'] : [],
			'current_page_slug' => isset( $params['current_page_slug'] ) ? sanitize_text_field( $params['current_page_slug'] ) : '',
		];

		$plan = Anchor_AI_Plan_Builder::build_plan( $message, $context );
		if ( is_wp_error( $plan ) ) {
			return new WP_REST_Response( [ 'error' => $plan->get_error_message() ], 422 );
		}

		// Sign the plan with a transient so /execute can verify it.
		$plan_id = 'p_' . wp_generate_uuid4();
		set_transient( 'anchor_editor_plan_' . $plan_id, $plan, self::PLAN_TRANSIENT_TTL );

		return rest_ensure_response( [
			'plan_id' => $plan_id,
			'plan'    => $plan,
		] );
	}

	public function execute_plan( $request ) {
		$params  = $request->get_json_params();
		$plan_id = (string) ( $params['plan_id'] ?? '' );
		$plan    = $params['plan'] ?? null;

		if ( $plan_id === '' || ! is_array( $plan ) ) {
			return new WP_REST_Response( [ 'error' => 'plan_id and plan are required.' ], 400 );
		}

		// Verify against stored transient. The submitted plan may have
		// fewer steps (user un-checked some) but every submitted step must
		// match the original by tool + args.
		$stored = get_transient( 'anchor_editor_plan_' . $plan_id );
		if ( ! is_array( $stored ) ) {
			return new WP_REST_Response( [ 'error' => 'Plan expired or not found.' ], 403 );
		}
		if ( ! $this->plan_subset_of( $plan, $stored ) ) {
			return new WP_REST_Response( [ 'error' => 'Submitted plan does not match the approved plan.' ], 403 );
		}
		delete_transient( 'anchor_editor_plan_' . $plan_id );

		// Execute sequentially.
		$results = [];
		$halted  = false;
		$halt_reason = null;
		foreach ( $plan['steps'] as $i => $step ) {
			if ( $halted ) {
				$results[] = [ 'index' => $i, 'tool' => $step['tool'], 'skipped' => true ];
				continue;
			}
			$out = Anchor_AI_Tool_Registry::execute_tool( $step['tool'], $step['args'] );
			$results[] = array_merge( [ 'index' => $i, 'tool' => $step['tool'] ], $out );
			if ( empty( $out['success'] ) ) {
				$halted = true;
				$halt_reason = $out['error'] ?? 'Unknown error';
			}
		}

		return rest_ensure_response( [
			'results'     => $results,
			'halted'      => $halted,
			'halt_reason' => $halt_reason,
		] );
	}

	/**
	 * Verify submitted plan steps are a subset of the stored plan
	 * (in order, same tool + same args JSON).
	 */
	private function plan_subset_of( $submitted, $stored ) {
		if ( ! isset( $submitted['steps'], $stored['steps'] ) ) return false;
		$stored_steps = $stored['steps'];
		$j = 0;
		foreach ( $submitted['steps'] as $step ) {
			$matched = false;
			while ( $j < count( $stored_steps ) ) {
				$s = $stored_steps[ $j++ ];
				if ( $s['tool'] === $step['tool'] && json_encode( $s['args'] ) === json_encode( $step['args'] ) ) {
					$matched = true;
					break;
				}
			}
			if ( ! $matched ) return false;
		}
		return true;
	}
}
