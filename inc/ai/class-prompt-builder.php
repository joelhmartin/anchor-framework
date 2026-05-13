<?php
/**
 * Prompt Builder — constructs the AI system prompt with full config context.
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_AI_Prompt_Builder {

    /**
     * Build the system prompt for the AI.
     *
     * @param string|null $page_slug    Current page being edited (null if editing a config).
     * @param string|null $config_key   Current config being edited (site, data, globals, media).
     * @param array|null  $post_context Post data when on a single blog/event post.
     * @return string The system prompt.
     */
    public static function build( $page_slug = null, $config_key = null, $post_context = null, $selected_section_type = null ) {
        $sections = array();
        $cm       = Anchor_Editor_Config_Manager::instance();

        // Direct-PHP mode short-circuits the config-oriented prompt.
        // When the current page is backed by page-content/{slug}.php, the
        // AI edits raw PHP/HTML instead of a JSON config.
        if ( $page_slug && class_exists( 'Anchor_Editor_File_Writer' ) ) {
            $read = Anchor_Editor_File_Writer::read_page( $page_slug );
            if ( is_array( $read ) && ! empty( $read['exists'] ) ) {
                return self::build_direct_php( $page_slug, $read['contents'], $cm );
            }
        }

        $prompt = "You are the Anchor Page Assistant, an AI that helps build and edit WordPress pages.\n\n";

        // Architecture context
        $prompt .= "## How This System Works\n\n";
        $prompt .= "Pages are built from PHP config arrays. Each page has a 'sections' array. ";
        $prompt .= "Each section has a 'type', optional 'variant', and 'props' object. ";
        $prompt .= "You modify the config and return the updated JSON. The system writes it to a PHP file.\n\n";
        $prompt .= "Section-level options: 'flush_bottom' (bool) removes bottom padding.\n\n";

        // Media keys
        $media = $cm->get_media();
        if ( ! empty( $media ) ) {
            $prompt .= "## Available Media Keys\n\n";
            $prompt .= "Use these keys in any 'media' type prop. They resolve to actual URLs.\n";
            foreach ( $media as $key => $value ) {
                $display = is_string( $value ) ? $value : "(attachment #{$value})";
                $prompt .= "  - '{$key}' → {$display}\n";
            }
            $prompt .= "\n";
        }

        // Site config
        $site = $cm->get_site();
        if ( ! empty( $site ) ) {
            $prompt .= "## Site Info\n\n";
            $prompt .= "Business name: " . ( $site['name'] ?? 'Unknown' ) . "\n";
            if ( ! empty( $site['phone'] ) ) $prompt .= "Phone: {$site['phone']}\n";
            if ( ! empty( $site['email'] ) ) $prompt .= "Email: {$site['email']}\n";
            if ( ! empty( $site['address'] ) ) $prompt .= "Address: {$site['address']}\n";
            $prompt .= "\n";
        }

        // Shared data summary
        $data = $cm->get_data();
        if ( ! empty( $data['services'] ) ) {
            $prompt .= "## Services (from data.php)\n\n";
            foreach ( $data['services'] as $s ) {
                $prompt .= "  - {$s['title']} (id: {$s['id']}, icon: {$s['icon']}, url: {$s['url']})\n";
            }
            $prompt .= "\nYou can reference services by using anchor_get_data('services') in PHP, but for JSON output just include the data inline.\n\n";
        }
        if ( ! empty( $data['team'] ) ) {
            $prompt .= "## Team Members (from data.php)\n\n";
            foreach ( $data['team'] as $m ) {
                $prompt .= "  - {$m['name']} ({$m['role']})\n";
            }
            $prompt .= "\n";
        }

        // Current page context
        if ( $page_slug ) {
            $page_config = $cm->get_page( $page_slug );
            if ( ! is_wp_error( $page_config ) ) {
                $prompt .= "## Current Page: {$page_slug}\n\n";
                $prompt .= "Current config (JSON):\n```json\n";
                $prompt .= json_encode( $page_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
                $prompt .= "\n```\n\n";
            }
        }

        // Current config context
        if ( $config_key ) {
            $config_data = null;
            switch ( $config_key ) {
                case 'site':    $config_data = $cm->get_site(); break;
                case 'data':    $config_data = $cm->get_data(); break;
                case 'globals': $config_data = $cm->get_globals(); break;
                case 'media':   $config_data = $cm->get_media(); break;
            }
            if ( $config_data ) {
                $prompt .= "## Current Config: {$config_key}.php\n\n";
                $prompt .= "```json\n" . json_encode( $config_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n```\n\n";
            }
        }

        // Navigation menus
        $locations = get_nav_menu_locations();
        if ( ! empty( $locations ) ) {
            $prompt .= "## Navigation Menus\n\n";
            $prompt .= "Menus are managed via WordPress menus (not config files). You can modify them via the REST API.\n";
            $prompt .= "Available menu locations: " . implode( ', ', array_keys( $locations ) ) . "\n";
            foreach ( $locations as $loc => $menu_id ) {
                $items = wp_get_nav_menu_items( $menu_id );
                if ( $items ) {
                    $prompt .= "\n### Menu: {$loc}\n";
                    foreach ( $items as $item ) {
                        $indent = (int) $item->menu_item_parent > 0 ? '  ' : '';
                        $prompt .= "{$indent}- {$item->title} → {$item->url} (ID: {$item->ID})\n";
                    }
                }
            }
            $prompt .= "\nTo modify menus, output a JSON block with key \"menu_action\" instead of page config:\n";
            $prompt .= "```json\n{\"menu_action\": \"add_item\", \"location\": \"primary\", \"title\": \"New Page\", \"url\": \"/new-page/\", \"parent_id\": 0}\n```\n";
            $prompt .= "Actions: add_item, delete_item (with item id), replace_menu (with full tree array).\n\n";
        }

        // Blog/event post context
        if ( $post_context && is_array( $post_context ) ) {
            $prompt .= "## Current Post (WordPress Content)\n\n";
            $prompt .= "You are on a single blog post or event page. This is NOT a config-driven page.\n";
            $prompt .= "The page layout comes from a PHP template (single.php or single-anchor_event.php).\n";
            $prompt .= "The CONTENT is stored in the WordPress database, not in a config file.\n\n";
            $prompt .= "Post ID: {$post_context['id']}\n";
            $prompt .= "Type: {$post_context['type']}\n";
            $prompt .= "Title: {$post_context['title']}\n";
            $prompt .= "Content:\n```html\n{$post_context['content']}\n```\n\n";
            if ( ! empty( $post_context['excerpt'] ) ) {
                $prompt .= "Excerpt: {$post_context['excerpt']}\n";
            }
            if ( ! empty( $post_context['featured_image'] ) ) {
                $prompt .= "Featured image: {$post_context['featured_image']} (attachment ID: {$post_context['featured_image_id']})\n";
            } else {
                $prompt .= "Featured image: none set\n";
            }
            $prompt .= "\n";

            // Yoast SEO context
            if ( ! empty( $post_context['seo'] ) ) {
                $seo = $post_context['seo'];
                $prompt .= "### Yoast SEO Meta\n";
                $prompt .= "SEO Title: " . ( $seo['title'] ?: '(not set — WordPress will use post title)' ) . "\n";
                $prompt .= "Meta Description: " . ( $seo['description'] ?: '(not set)' ) . "\n";
                $prompt .= "Focus Keyword: " . ( $seo['focus_kw'] ?: '(not set)' ) . "\n";
                if ( $seo['canonical'] ) $prompt .= "Canonical URL: {$seo['canonical']}\n";
                if ( $seo['og_title'] ) $prompt .= "OG Title: {$seo['og_title']}\n";
                if ( $seo['og_desc'] ) $prompt .= "OG Description: {$seo['og_desc']}\n";
                $prompt .= "\n";
            }

            $prompt .= "### How to edit this post\n";
            $prompt .= "Output a JSON block with key \"post_action\":\n";
            $prompt .= "```json\n";
            $prompt .= "{\n";
            $prompt .= "  \"post_action\": \"update\",\n";
            $prompt .= "  \"post_id\": {$post_context['id']},\n";
            $prompt .= "  \"title\": \"New Title\",\n";
            $prompt .= "  \"content\": \"<h2>Section</h2><p>HTML content here.</p>\",\n";
            $prompt .= "  \"excerpt\": \"Short description for archives.\",\n";
            $prompt .= "  \"featured_image_id\": 123,\n";
            $prompt .= "  \"seo_title\": \"SEO Title | Brand\",\n";
            $prompt .= "  \"seo_description\": \"Meta description for search engines.\",\n";
            $prompt .= "  \"seo_focus_kw\": \"focus keyword\"\n";
            $prompt .= "}\n```\n";
            $prompt .= "Only include fields you want to change. For featured_image_id, use a WordPress attachment ID.\n";
            $prompt .= "If the user uploaded or selected an image, use that attachment ID for featured_image_id.\n";
            $prompt .= "Do NOT try to edit the page template or config — this page's content lives in the WP database.\n\n";
        }

        // Response format instructions
        $prompt .= "## Response Format\n\n";
        $prompt .= "When the user asks you to make changes, respond with:\n";
        $prompt .= "1. A brief explanation of what you're changing\n";
        $prompt .= "2. The updated config as a JSON code block tagged ```json\n";
        $prompt .= "3. The JSON must be the COMPLETE config (not a partial diff)\n\n";
        $prompt .= "When the user asks a question (not a change), just answer normally without JSON.\n\n";
        $prompt .= "IMPORTANT: Only output valid JSON in code blocks. The system will parse it and write it to the config file.\n";

        return $prompt;
    }

    /**
     * Build the system prompt for direct-PHP mode.
     *
     * Used when the current page has a page-content/{slug}.php file.
     * The AI edits raw PHP/HTML instead of emitting JSON config.
     *
     * @param string                            $page_slug Current page slug.
     * @param string                            $contents  Current file contents.
     * @param Anchor_Editor_Config_Manager|null $cm        Config manager (for helper context).
     * @return string
     */
    private static function build_direct_php( $page_slug, $contents, $cm = null ) {
        $prompt  = "You are the Anchor Page Assistant, an AI that edits WordPress pages directly as PHP/HTML files.\n\n";

        $prompt .= "## How This Works\n\n";
        $prompt .= "This page is a direct-PHP file at `page-content/{$page_slug}.php`. ";
        $prompt .= "The file is pure PHP/HTML — write HTML directly, use `<?php ?>` tags for dynamic content. ";
        $prompt .= "The file renders inside header.php + footer.php (never include `get_header()` or `get_footer()`). ";
        $prompt .= "You can add any HTML element, CSS class, inline style, shortcode, `<video>`, `<iframe>`, or script block directly.\n\n";

        $prompt .= "When the user asks for changes, return the COMPLETE updated file contents inside a single ```php code fence. ";
        $prompt .= "The system parses the fenced block and writes it to disk atomically after a `php -l` syntax check.\n\n";

        $prompt .= "## Helpers Available in PHP Templates\n\n";
        $prompt .= "- `do_shortcode( '[shortcode_tag]' )` — run any Anchor Tools (or WP core) shortcode.\n";
        $prompt .= "- `anchor_resolve_media( 'key' )` — resolve a named media key to a full URL.\n";
        $prompt .= "- `anchor_get_site_config()` — array of business info (name, phone, email, address, logo).\n";
        $prompt .= "- `anchor_get_data( 'services' | 'team' | 'products' )` — shared entity arrays from `config/data.php`.\n";
        $prompt .= "- `get_template_part( 'template-parts/components/{name}', null, \$args )` — reusable components.\n";
        $prompt .= "- `get_template_part( 'template-parts/partials/{name}' )` — reusable page sections (cta-band, testimonials).\n";
        $prompt .= "- Standard WordPress: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`, `the_permalink()`, etc.\n\n";

        $prompt .= "## Anchor Tools Shortcodes\n\n";
        $prompt .= "These shortcodes are registered by the Anchor Tools plugin and are ready to use anywhere in the page file.\n";
        $prompt .= "**Usage:** `<?php echo do_shortcode( '[shortcode_tag]' ); ?>`\n\n";
        $prompt .= "### Business Info (managed in Settings > Anchor Tools)\n";
        $prompt .= "- `[business_name]` — business name\n";
        $prompt .= "- `[phone]` — phone number\n";
        $prompt .= "- `[phone_href]` — phone stripped to digits only (use in `href=\"tel:[phone_href]\"`)\n";
        $prompt .= "- `[address]` — full address (may contain HTML line breaks)\n";
        $prompt .= "- `[email]` — contact email\n";
        $prompt .= "- `[business_hours]` — business hours\n";
        $prompt .= "- `[current_year]` — current year\n";
        $prompt .= "- `[site_title]` — WordPress site title\n";
        $prompt .= "- `[site_image_url]` — primary logo URL\n";
        $prompt .= "- `[site_image_horizontal]` — horizontal logo URL\n";
        $prompt .= "- `[site_image_white]` — white/inverted logo URL\n";
        $prompt .= "- `[page_title]` — current page title\n\n";
        $prompt .= "### Components (CPT-based — create instances in WP Admin)\n";
        $prompt .= "- `[anchor_gallery id=\"N\"]` / `[anchor_video_slider id=\"N\"]` — video gallery or slider (create in Anchor Video Slider CPT)\n";
        $prompt .= "- `[anchor_reviews id=\"N\"]` — reviews display (create in Anchor Reviews CPT)\n";
        $prompt .= "- `[anchor_post_grid post_type=\"post\" fields=\"image,title,excerpt\"]` — post grid; supports `post_type`, `fields`, `limit`, `category`, `orderby`\n";
        $prompt .= "- `[anchor_search target=\"#selector\"]` — live search input that filters an `[anchor_post_grid]`\n";
        $prompt .= "- `[anchor_popup id=\"N\"]` — universal popup trigger (create in Anchor Universal Popups CPT)\n\n";
        $prompt .= "Custom shortcodes registered in Settings > Anchor Tools > Shortcodes tab are also available.\n\n";

        $prompt .= "## Available Components (template-parts/components/)\n\n";
        $components_dir = trailingslashit( get_template_directory() ) . 'template-parts/components';
        if ( is_dir( $components_dir ) ) {
            $files = glob( $components_dir . '/*.php' );
            if ( $files ) {
                foreach ( $files as $f ) {
                    $prompt .= '  - ' . basename( $f, '.php' ) . "\n";
                }
            }
        }
        $prompt .= "\n";

        $prompt .= "## Available Partials (template-parts/partials/)\n\n";
        if ( class_exists( 'Anchor_Editor_File_Writer' ) ) {
            foreach ( Anchor_Editor_File_Writer::list_partials() as $p ) {
                $prompt .= "  - `get_template_part( 'template-parts/partials/{$p['slug']}' )`\n";
            }
        }
        $prompt .= "\n";

        $prompt .= "## Available Section Templates (legacy reference)\n\n";
        $prompt .= "Framework sections can still be called via `anchor_render_section(['type' => 'hero', 'props' => [...]])` ";
        $prompt .= "but the preferred approach is to write HTML directly. Only use the section renderer for framework sections ";
        $prompt .= "that would be very long to inline (hero variants, services-tabs). Deka editorial sections should always be inline HTML.\n\n";

        // Collect CSS files: per-page CSS first, then only the theme CSS files relevant to this page.
        $css_found = [];

        // Per-page CSS (highest priority — most relevant for this page).
        $page_css_path = trailingslashit( get_stylesheet_directory() ) . 'assets/css/pages/' . $page_slug . '.css';
        if ( file_exists( $page_css_path ) ) {
            $page_css = file_get_contents( $page_css_path );
            if ( false !== $page_css ) {
                $css_found[ $page_slug . '.css' ] = $page_css;
            }
        }

        // Theme CSS: always include base overrides; include page-specific bespoke CSS only when relevant.
        $always_include = [ 'client-overrides.css', 'client-tokens.css' ];
        $page_specific  = apply_filters( 'anchor_editor/css_allowlist', array(), $page_slug );
        $theme_css_files = $always_include;
        if ( isset( $page_specific[ $page_slug ] ) ) {
            array_unshift( $theme_css_files, $page_specific[ $page_slug ] );
        }

        foreach ( $theme_css_files as $name ) {
            $path = trailingslashit( get_stylesheet_directory() ) . 'assets/css/' . $name;
            if ( file_exists( $path ) ) {
                $css = file_get_contents( $path );
                if ( false !== $css ) {
                    $css_found[ $name ] = $css;
                }
            }
        }

        if ( $css_found ) {
            $prompt .= "## CSS Files Available for Editing\n\n";
            $prompt .= "When making a style change, return the COMPLETE updated file in a \`\`\`css fenced block. ";
            $prompt .= "The first line inside the fence must be a comment identifying the file: `/* file: {$page_slug}.css */` ";
            $prompt .= "(or whichever filename, e.g. `home-overrides.css`). The system detects this and writes it to disk.\n\n";
            $prompt .= "**Per-page CSS** (`assets/css/pages/{$page_slug}.css`) is the preferred target for styles ";
            $prompt .= "that apply only to this page. **Theme CSS** files affect all pages — only edit them for global changes.\n\n";
            foreach ( $css_found as $name => $css ) {
                $prompt .= "### {$name}\n\n\`\`\`css\n{$css}\n\`\`\`\n\n";
            }
        }

        // Media keys context
        if ( $cm ) {
            $media = $cm->get_media();
            if ( ! empty( $media ) ) {
                $prompt .= "## Media Keys (pass to anchor_resolve_media())\n\n";
                foreach ( $media as $key => $value ) {
                    $display = is_string( $value ) ? $value : "(attachment #{$value})";
                    $prompt .= "  - '{$key}' → {$display}\n";
                }
                $prompt .= "\n";
            }

            $site = $cm->get_site();
            if ( ! empty( $site ) ) {
                $prompt .= "## Site Info (available via anchor_get_site_config())\n\n";
                $prompt .= "Business: " . ( $site['name'] ?? 'Unknown' ) . "\n";
                if ( ! empty( $site['phone'] ) )   $prompt .= "Phone: {$site['phone']}\n";
                if ( ! empty( $site['email'] ) )   $prompt .= "Email: {$site['email']}\n";
                if ( ! empty( $site['address'] ) ) $prompt .= "Address: {$site['address']}\n";
                $prompt .= "\n";
            }

            $data = $cm->get_data();
            if ( ! empty( $data['services'] ) ) {
                $prompt .= "## Services (via anchor_get_data('services'))\n\n";
                foreach ( $data['services'] as $s ) {
                    $prompt .= "  - {$s['title']} (id: {$s['id']}, icon: {$s['icon']})\n";
                }
                $prompt .= "\n";
            }
            if ( ! empty( $data['team'] ) ) {
                $prompt .= "## Team (via anchor_get_data('team'))\n\n";
                foreach ( $data['team'] as $m ) {
                    $prompt .= "  - {$m['name']} ({$m['role']})\n";
                }
                $prompt .= "\n";
            }
        }

        $prompt .= "## DOM Context (Cmd+Click Selection)\n\n";
        $prompt .= "When the user Cmd+clicks an element on the page, their message begins with a [DOM Context] block containing:\n";
        $prompt .= "- The section's rendered HTML with all class names\n";
        $prompt .= "- CSS rules extracted from loaded stylesheets that match the section's classes\n";
        $prompt .= "- The source file path\n\n";
        $prompt .= "**Use this context to:**\n";
        $prompt .= "- CSS changes: read the EXACT selector from the HTML — never guess class names. Use the extracted CSS to see the current rules and write the correct override.\n";
        $prompt .= "- PHP/content changes: the HTML shows what the PHP renders so you can find the right element to modify.\n\n";
        $prompt .= "**Mismatch detection:** If the user's request clearly doesn't match the [DOM Context] section (e.g., context is the Hero but user asks to change the contact form), ask one clarifying question before making any changes: \"I have context for [actual section] — did you mean to change something there, or Cmd+click the [requested section] first?\"\n\n";

        $prompt .= "## Current File: page-content/{$page_slug}.php\n\n";
        $prompt .= "```php\n" . $contents . "\n```\n\n";

        $prompt .= "## Response Format — READ THIS CAREFULLY\n\n";
        $prompt .= "When the user asks for a change, you MUST respond with the actual code. Never describe what the user should do — DO IT YOURSELF.\n\n";
        $prompt .= "### Rules\n";
        $prompt .= "- One sentence saying what you changed. No headers. No bullet lists. No 'Note:' or 'Summary:' sections.\n";
        $prompt .= "- Immediately follow with the complete code block(s). Never leave a code block empty or with placeholder text.\n";
        $prompt .= "- For CSS-only changes: one \`\`\`css block. First line inside: `/* file: {$page_slug}.css */` (or correct filename).\n";
        $prompt .= "- For PHP changes: one \`\`\`php block with the COMPLETE file contents including the opening `<?php` tag.\n";
        $prompt .= "- For changes needing both: one \`\`\`php block then one \`\`\`css block.\n";
        $prompt .= "- Never include `get_header()` or `get_footer()`.\n";
        $prompt .= "- Never tell the user to 'make sure to include' or 'add this to your stylesheet' — write the code yourself.\n";
        $prompt .= "- If you are unsure of the exact selector, inspect the CSS context above and make your best specific attempt. Never leave it blank.\n\n";
        $prompt .= "If the user asks a question (no change requested), answer in 1–3 sentences — no code blocks.\n";

        return $prompt;
    }
}
