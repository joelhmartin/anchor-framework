<?php
/**
 * Settings Page — API key and provider configuration.
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_Settings {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function init() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_menu() {
        add_submenu_page(
            'anchor',
            __( 'Settings', 'anchor' ),
            __( 'Settings', 'anchor' ),
            'manage_options',
            'anchor-settings',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'apa_settings', 'apa_ai_provider' );
        register_setting( 'apa_settings', 'apa_ai_api_key' );
        register_setting( 'apa_settings', 'apa_ai_model' );
    }

    public function render_page() {
        $provider = get_option( 'apa_ai_provider', 'anthropic' );
        $api_key  = get_option( 'apa_ai_api_key', '' );
        $model    = get_option( 'apa_ai_model', '' );

        // Default models per provider.
        $models = [
            'anthropic' => [
                'claude-sonnet-4-20250514' => 'Claude Sonnet 4',
                'claude-opus-4-20250514'   => 'Claude Opus 4',
                'claude-haiku-4-20250414'  => 'Claude Haiku 4',
            ],
            'openai' => [
                'gpt-4o'      => 'GPT-4o',
                'gpt-4o-mini' => 'GPT-4o Mini',
                'o3'          => 'o3',
            ],
        ];

        $masked_key = $api_key ? substr( $api_key, 0, 8 ) . '...' . substr( $api_key, -4 ) : '';
        ?>
        <div class="wrap">
            <h1>Page Assistant Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'apa_settings' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">AI Provider</th>
                        <td>
                            <select name="apa_ai_provider" id="apa-provider-select">
                                <option value="anthropic" <?php selected( $provider, 'anthropic' ); ?>>Anthropic (Claude)</option>
                                <option value="openai" <?php selected( $provider, 'openai' ); ?>>OpenAI (GPT)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="password" name="apa_ai_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" placeholder="<?php echo $masked_key ? esc_attr( $masked_key ) : 'Enter API key...'; ?>">
                            <p class="description">
                                <?php if ( 'anthropic' === $provider ) : ?>
                                    Get your key at <a href="https://console.anthropic.com/settings/keys" target="_blank">console.anthropic.com</a>
                                <?php else : ?>
                                    Get your key at <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Model</th>
                        <td>
                            <?php foreach ( $models as $prov => $model_list ) : ?>
                                <select name="apa_ai_model" class="apa-model-select" data-provider="<?php echo esc_attr( $prov ); ?>" <?php echo $prov !== $provider ? 'style="display:none;"' : ''; ?>>
                                    <?php foreach ( $model_list as $val => $label ) : ?>
                                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $model, $val ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endforeach; ?>
                            <script>
                                document.getElementById('apa-provider-select').addEventListener('change', function() {
                                    document.querySelectorAll('.apa-model-select').forEach(function(s) {
                                        s.style.display = s.dataset.provider === this.value ? '' : 'none';
                                        if (s.dataset.provider === this.value) s.name = 'apa_ai_model';
                                        else s.removeAttribute('name');
                                    }.bind(this));
                                });
                            </script>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Save Settings' ); ?>
            </form>

            <?php if ( $api_key ) : ?>
                <h2>Connection Test</h2>
                <button id="apa-test-btn" class="button">Test API Connection</button>
                <span id="apa-test-result" style="margin-left:12px;"></span>
                <script>
                    document.getElementById('apa-test-btn').addEventListener('click', function() {
                        var result = document.getElementById('apa-test-result');
                        result.textContent = 'Testing...';
                        this.disabled = true;
                        var self = this;
                        fetch('<?php echo esc_url( rest_url( 'anchor-assistant/v1/ai/chat' ) ); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': '<?php echo wp_create_nonce( 'wp_rest' ); ?>'
                            },
                            body: JSON.stringify({ message: 'Say "Connection successful!" and nothing else.', history: [] })
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.error) result.textContent = '✗ ' + data.error;
                            else result.textContent = '✓ Connected! Response: ' + (data.reply || '').substring(0, 60);
                            result.style.color = data.error ? '#d63638' : '#46b450';
                        })
                        .catch(function(e) { result.textContent = '✗ ' + e.message; result.style.color = '#d63638'; })
                        .finally(function() { self.disabled = false; });
                    });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }
}
