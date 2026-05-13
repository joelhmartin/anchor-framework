<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="apa-app" class="wrap">
    <div class="apa-header">
        <h1>Anchor Page Assistant</h1>
        <span class="apa-version">v<?php echo esc_html( Anchor_Editor::VERSION ); ?></span>
    </div>

    <div class="apa-layout">
        <!-- Sidebar: page list -->
        <div class="apa-sidebar">
            <h3>Pages</h3>
            <ul class="apa-page-list" id="apa-page-list">
                <li class="apa-loading">Loading pages...</li>
            </ul>

            <h3>Configs</h3>
            <ul class="apa-config-list">
                <li><button class="apa-config-btn" data-config="site">Site Settings</button></li>
                <li><button class="apa-config-btn" data-config="data">Shared Data</button></li>
                <li><button class="apa-config-btn" data-config="globals">Global Sections</button></li>
                <li><button class="apa-config-btn" data-config="media">Media Registry</button></li>
            </ul>

            <?php if ( ! Anchor_Editor_Config_Manager::instance()->is_writable() ) : ?>
                <div class="apa-warning">
                    Config directory is not writable. Changes cannot be saved.
                </div>
            <?php endif; ?>
        </div>

        <!-- Main content area -->
        <div class="apa-main">
            <div class="apa-empty-state" id="apa-empty-state">
                <p>Select a page or config from the sidebar to begin editing.</p>
            </div>

            <!-- Section list (shown when a page is selected) -->
            <div class="apa-editor" id="apa-editor" style="display:none;">
                <div class="apa-editor-header">
                    <h2 id="apa-editor-title"></h2>
                    <div class="apa-editor-actions">
                        <a id="apa-preview-link" href="#" target="_blank" class="button">Preview</a>
                        <button id="apa-save-btn" class="button button-primary">Save Changes</button>
                    </div>
                </div>

                <div id="apa-section-list" class="apa-section-list"></div>

                <div class="apa-add-section">
                    <select id="apa-add-section-type">
                        <option value="">+ Add Section...</option>
                    </select>
                </div>
            </div>

            <!-- JSON editor (shown for raw config editing) -->
            <div class="apa-json-editor" id="apa-json-editor" style="display:none;">
                <div class="apa-editor-header">
                    <h2 id="apa-json-title"></h2>
                    <button id="apa-json-save" class="button button-primary">Save</button>
                </div>
                <textarea id="apa-json-textarea" rows="30"></textarea>
            </div>
        </div>
    </div>
</div>
