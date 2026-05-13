<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="apa-live" class="apa-live">

    <!-- Sidebar: config editor -->
    <div class="apa-live__sidebar">
        <div class="apa-live__sidebar-header">
            <h2>Live Editor</h2>
            <select id="apa-live-page-select">
                <option value="">Select page...</option>
            </select>
        </div>

        <div class="apa-live__config-area">
            <textarea id="apa-live-config" placeholder="Select a page to load its config..."></textarea>
        </div>

        <div class="apa-live__actions">
            <button id="apa-live-save" class="button button-primary" disabled>Save & Refresh</button>
            <span id="apa-live-status"></span>
        </div>
    </div>

    <!-- Preview: iframe -->
    <div class="apa-live__preview">
        <div class="apa-live__preview-bar">
            <span id="apa-live-url"></span>
            <button id="apa-live-refresh" class="button" title="Refresh preview">↻ Refresh</button>
        </div>
        <iframe id="apa-live-iframe" src="about:blank"></iframe>
    </div>

</div>
