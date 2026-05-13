<?php
/**
 * AI Handler — manages communication with Claude or OpenAI APIs.
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_AI_Handler {

    const MODEL = 'claude-sonnet-4-6';

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_provider() {
        return get_option( 'apa_ai_provider', 'anthropic' );
    }

    public function get_api_key() {
        return get_option( 'apa_ai_api_key', '' );
    }

    public function set_api_key( $key ) {
        return update_option( 'apa_ai_api_key', sanitize_text_field( $key ) );
    }

    public function get_model() {
        $model = get_option( 'apa_ai_model', '' );
        if ( $model ) return $model;

        // Defaults per provider.
        return 'anthropic' === $this->get_provider()
            ? 'claude-sonnet-4-20250514'
            : 'gpt-4o';
    }

    public function is_configured() {
        return ! empty( $this->get_api_key() );
    }

    /**
     * Send a chat message.
     *
     * @param string      $message      User message.
     * @param array       $history      Conversation history.
     * @param string|null $page_slug    Current page.
     * @param string|null $config_key   Current config.
     * @param array|null  $post_context Post data for blog/event pages.
     * @return array|WP_Error
     */
    public function chat( $message, $history = [], $page_slug = null, $config_key = null, $post_context = null, $selected_section_type = null ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'no_api_key', 'API key not configured. Go to Anchor → Settings.' );
        }

        $system = Anchor_AI_Prompt_Builder::build( $page_slug, $config_key, $post_context, $selected_section_type );

        $provider = $this->get_provider();
        if ( 'openai' === $provider ) {
            return $this->call_openai( $system, $message, $history );
        }
        return $this->call_anthropic( $system, $message, $history );
    }

    // ─── Anthropic (Claude) ───────────────────────────────────────

    private function call_anthropic( $system, $message, $history ) {
        $messages = [];
        foreach ( $history as $msg ) {
            $messages[] = [ 'role' => $msg['role'], 'content' => $msg['content'] ];
        }
        $messages[] = [ 'role' => 'user', 'content' => $message ];

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 90,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'        => $this->get_api_key(),
                'anthropic-version' => '2023-06-01',
            ],
            'body' => wp_json_encode( [
                'model'      => $this->get_model(),
                'max_tokens' => 4096,
                'system'     => $system,
                'messages'   => $messages,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code ) {
            return new WP_Error( 'api_error', $body['error']['message'] ?? "Anthropic API returned {$code}" );
        }

        $reply = '';
        foreach ( ( $body['content'] ?? [] ) as $block ) {
            if ( 'text' === $block['type'] ) $reply .= $block['text'];
        }

        return [
            'reply'        => $reply,
            'config'       => self::extract_json( $reply ),
            'file_contents' => self::extract_php_file( $reply ),
        ];
    }

    // ─── OpenAI ───────────────────────────────────────────────────

    private function call_openai( $system, $message, $history ) {
        $messages = [
            [ 'role' => 'system', 'content' => $system ],
        ];
        foreach ( $history as $msg ) {
            $messages[] = [ 'role' => $msg['role'], 'content' => $msg['content'] ];
        }
        $messages[] = [ 'role' => 'user', 'content' => $message ];

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 90,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->get_api_key(),
            ],
            'body' => wp_json_encode( [
                'model'      => $this->get_model(),
                'max_tokens' => 4096,
                'messages'   => $messages,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code ) {
            return new WP_Error( 'api_error', $body['error']['message'] ?? "OpenAI API returned {$code}" );
        }

        $reply = $body['choices'][0]['message']['content'] ?? '';

        return [
            'reply'        => $reply,
            'config'       => self::extract_json( $reply ),
            'file_contents' => self::extract_php_file( $reply ),
        ];
    }

    // ─── Shared ───────────────────────────────────────────────────

    private static function extract_json( $text ) {
        if ( preg_match( '/```json\s*([\s\S]*?)\s*```/', $text, $matches ) ) {
            $decoded = json_decode( $matches[1], true );
            if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                return $decoded;
            }
        }
        return null;
    }

    /**
     * Extract the first ```php fenced code block from a reply.
     * Used in direct-PHP mode where the AI returns a full file.
     *
     * @return string|null Raw file contents, or null if no block found.
     */
    private static function extract_php_file( $text ) {
        if ( preg_match( '/```php\s*([\s\S]*?)\s*```/', $text, $matches ) ) {
            $contents = trim( $matches[1] );
            if ( '' !== $contents ) {
                return $contents;
            }
        }
        return null;
    }
}
