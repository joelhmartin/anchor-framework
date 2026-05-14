<?php
/**
 * Plan builder — generates a structured JSON plan from a user message.
 *
 * Calls Anchor_AI_Handler with a system prompt that includes the tool
 * catalogue and blast-radius rules. Parses the AI response for a fenced
 * ```json block. Validates the plan against the schema in spec §4.2.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_AI_Plan_Builder {

	const MAX_STEPS = 25;

	/**
	 * Build a plan from a user message.
	 *
	 * @param string $message  User's request.
	 * @param array  $context  Optional: open_files (array), current_page_slug (string).
	 * @return array|WP_Error  { summary, steps: [{tool, args, rationale}, ...] }
	 */
	public static function build_plan( $message, $context = [] ) {
		$prompt = self::compose_system_prompt( $context );
		$result = Anchor_AI_Handler::instance()->chat( $message, [], null, null, null, null, $prompt );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$raw = $result['reply'] ?? '';
		$plan = self::extract_json_plan( $raw );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$validation = self::validate( $plan );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}
		return $plan;
	}

	/**
	 * Build the system prompt with tool catalogue + blast-radius rules + context.
	 */
	private static function compose_system_prompt( $context ) {
		$tools   = Anchor_AI_Tool_Registry::get_tool_catalogue();
		$tools_s = '';
		foreach ( $tools as $name => $desc ) {
			$tools_s .= "  - {$name}: {$desc}\n";
		}

		$open_files = $context['open_files'] ?? [];
		$open_s     = $open_files ? implode( ', ', $open_files ) : '(none)';
		$page_slug  = $context['current_page_slug'] ?? '(none)';

		$prompt  = "You are the Anchor Editor agent. You help a WordPress site author edit their site by generating a JSON plan that is then executed step-by-step.\n\n";
		$prompt .= "Available tools:\n{$tools_s}\n";
		$prompt .= "Allowed write roots (you can write here):\n";
		$prompt .= "  - child-theme/page-content/{slug}.php\n";
		$prompt .= "  - child-theme/assets/css/*.css\n";
		$prompt .= "  - child-theme/assets/css/pages/{slug}.css\n\n";
		$prompt .= "Allowed read roots (you cannot write here):\n";
		$prompt .= "  - theme/template-parts/*\n\n";
		$prompt .= "Current context:\n";
		$prompt .= "  - Open files: {$open_s}\n";
		$prompt .= "  - Current page: {$page_slug}\n\n";
		$prompt .= "Respond with ONLY a fenced ```json block containing the plan. Schema:\n";
		$prompt .= "  { \"summary\": string, \"steps\": [ { \"tool\": ..., \"args\": ..., \"rationale\": ... } ] }\n\n";
		$prompt .= "The last step's tool MUST be \"done\". Max " . self::MAX_STEPS . " steps. No prose before or after the JSON.\n";

		return $prompt;
	}

	/**
	 * Extract the JSON plan from a fenced ```json block in the AI response.
	 */
	private static function extract_json_plan( $raw ) {
		if ( preg_match( '/```json\s*(.+?)\s*```/s', $raw, $m ) ) {
			$json = $m[1];
		} else {
			// Best-effort: try parsing the whole response as JSON.
			$json = trim( $raw );
		}
		$plan = json_decode( $json, true );
		if ( ! is_array( $plan ) ) {
			return new WP_Error( 'plan_parse', 'Could not parse the agent\'s plan as JSON.' );
		}
		return $plan;
	}

	/**
	 * Validate plan shape per spec §4.2.
	 *
	 * @return true|WP_Error
	 */
	private static function validate( $plan ) {
		if ( ! isset( $plan['summary'] ) || ! is_string( $plan['summary'] ) || $plan['summary'] === '' ) {
			return new WP_Error( 'plan_summary', 'Plan must include a non-empty summary.' );
		}
		if ( strlen( $plan['summary'] ) > 200 ) {
			return new WP_Error( 'plan_summary', 'Plan summary too long (max 200 chars).' );
		}
		if ( ! isset( $plan['steps'] ) || ! is_array( $plan['steps'] ) || empty( $plan['steps'] ) ) {
			return new WP_Error( 'plan_steps', 'Plan must include at least one step.' );
		}
		if ( count( $plan['steps'] ) > self::MAX_STEPS ) {
			return new WP_Error( 'plan_steps', 'Plan exceeds max steps (' . self::MAX_STEPS . ').' );
		}
		$tools = Anchor_AI_Tool_Registry::get_tool_catalogue();
		foreach ( $plan['steps'] as $i => $step ) {
			if ( ! is_array( $step ) ) {
				return new WP_Error( 'plan_step', "Step {$i} is not an object." );
			}
			if ( empty( $step['tool'] ) || ! isset( $tools[ $step['tool'] ] ) ) {
				return new WP_Error( 'plan_tool', "Step {$i}: unknown tool '" . ( $step['tool'] ?? '' ) . "'." );
			}
			if ( ! array_key_exists( 'args', $step ) ) {
				return new WP_Error( 'plan_args', "Step {$i}: missing args." );
			}
			if ( ! isset( $step['rationale'] ) || ! is_string( $step['rationale'] ) ) {
				return new WP_Error( 'plan_rationale', "Step {$i}: missing rationale." );
			}
		}
		$last = end( $plan['steps'] );
		if ( $last['tool'] !== 'done' ) {
			return new WP_Error( 'plan_done', 'Last step must use the "done" tool.' );
		}
		return true;
	}
}
