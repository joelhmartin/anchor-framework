/**
 * Anchor Page Assistant — AI Chat Widget
 *
 * Chat panel that sends messages to the AI endpoint,
 * displays responses, and offers one-click "Apply" for config changes.
 */
(function() {
    'use strict';

    const { restBase, nonce } = window.apaData || {};
    if ( ! restBase ) return;

    const headers = {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce,
    };

    let history = [];
    let chatOpen = false;

    // ─── Build Chat UI ────────────────────────────────────────────

    function createChatWidget() {
        // Toggle button
        const toggle = document.createElement( 'button' );
        toggle.className = 'apa-chat-toggle';
        toggle.innerHTML = '<span class="dashicons dashicons-format-chat"></span>';
        toggle.title = 'AI Assistant';
        toggle.addEventListener( 'click', toggleChat );

        // Panel
        const panel = document.createElement( 'div' );
        panel.className = 'apa-chat-panel';
        panel.id = 'apa-chat-panel';
        panel.innerHTML =
            '<div class="apa-chat-header">' +
                '<span>AI Assistant</span>' +
                '<button class="apa-chat-close" title="Close">&times;</button>' +
            '</div>' +
            '<div class="apa-chat-messages" id="apa-chat-messages"></div>' +
            '<div class="apa-chat-input-area">' +
                '<textarea id="apa-chat-input" placeholder="Ask me to edit this page..." rows="2"></textarea>' +
                '<button id="apa-chat-send" class="button button-primary">Send</button>' +
            '</div>' +
            '<div class="apa-chat-settings" id="apa-chat-settings" style="display:none;">' +
                '<p>API key not configured. <a href="admin.php?page=apa-settings">Go to Settings →</a></p>' +
            '</div>';

        document.body.appendChild( toggle );
        document.body.appendChild( panel );

        // Bind events
        panel.querySelector( '.apa-chat-close' ).addEventListener( 'click', toggleChat );
        document.getElementById( 'apa-chat-send' ).addEventListener( 'click', sendMessage );
        document.getElementById( 'apa-chat-input' ).addEventListener( 'keydown', function( e ) {
            if ( e.key === 'Enter' && ! e.shiftKey ) {
                e.preventDefault();
                sendMessage();
            }
        } );
        // Check if API is configured
        checkApiStatus();
    }

    function toggleChat() {
        chatOpen = ! chatOpen;
        document.getElementById( 'apa-chat-panel' ).classList.toggle( 'is-open', chatOpen );
    }

    // ─── API Key Management ───────────────────────────────────────

    function checkApiStatus() {
        fetch( restBase + 'ai/settings', { headers: headers } )
            .then( function( r ) { return r.json(); } )
            .then( function( data ) {
                if ( ! data.configured ) {
                    document.getElementById( 'apa-chat-settings' ).style.display = '';
                    addMessage( 'assistant', 'Please add your Anthropic API key to get started.' );
                } else {
                    addMessage( 'assistant', 'Ready! Select a page from the sidebar, then tell me what changes you\'d like.' );
                }
            } );
    }

    // ─── Chat Messages ────────────────────────────────────────────

    function sendMessage() {
        const input = document.getElementById( 'apa-chat-input' );
        const message = input.value.trim();
        if ( ! message ) return;

        input.value = '';
        addMessage( 'user', message );

        // Determine current context from the admin app state
        const activePageBtn = document.querySelector( '.apa-page-btn.is-active' );
        const activeConfigBtn = document.querySelector( '.apa-config-btn.is-active' );
        const page_slug = activePageBtn ? activePageBtn.dataset.slug : '';
        const config_key = activeConfigBtn ? activeConfigBtn.dataset.config : '';

        const thinkingId = addMessage( 'assistant', 'Thinking...' );

        fetch( restBase + 'ai/chat', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify( {
                message: message,
                history: history.slice( -10 ), // last 10 messages for context
                page_slug: page_slug,
                config_key: config_key,
            } ),
        } )
        .then( function( r ) { return r.json(); } )
        .then( function( data ) {
            removeMessage( thinkingId );

            if ( data.error ) {
                addMessage( 'assistant', 'Error: ' + data.error );
                return;
            }

            // Add to history
            history.push( { role: 'user', content: message } );
            history.push( { role: 'assistant', content: data.reply } );

            // Display reply (strip JSON block for display)
            const displayText = data.reply.replace( /```json[\s\S]*?```/g, '' ).trim();
            const msgEl = addMessage( 'assistant', displayText );

            // If config was extracted, show Apply button
            if ( data.config ) {
                const applyBtn = document.createElement( 'button' );
                applyBtn.className = 'button button-primary apa-apply-btn';
                applyBtn.textContent = 'Apply Changes';
                applyBtn.addEventListener( 'click', function() {
                    applyConfig( data.config, page_slug, config_key, applyBtn );
                } );
                msgEl.appendChild( applyBtn );
            }
        } )
        .catch( function( err ) {
            removeMessage( thinkingId );
            addMessage( 'assistant', 'Error: ' + err.message );
        } );
    }

    function applyConfig( config, page_slug, config_key, btn ) {
        btn.disabled = true;
        btn.textContent = 'Applying...';

        fetch( restBase + 'ai/apply', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify( {
                config: config,
                page_slug: page_slug,
                config_key: config_key,
            } ),
        } )
        .then( function( r ) { return r.json(); } )
        .then( function( data ) {
            if ( data.success ) {
                btn.textContent = 'Applied!';
                btn.style.background = '#46b450';
                addMessage( 'assistant', 'Changes saved. Refresh the page to see updates in the editor.' );
            } else {
                btn.textContent = 'Error';
                btn.style.background = '#d63638';
                addMessage( 'assistant', 'Failed to apply: ' + ( data.error || 'Unknown error' ) );
            }
        } )
        .catch( function( err ) {
            btn.textContent = 'Error';
            addMessage( 'assistant', 'Failed: ' + err.message );
        } );
    }

    // ─── DOM Helpers ──────────────────────────────────────────────

    let msgCounter = 0;

    function addMessage( role, text ) {
        const container = document.getElementById( 'apa-chat-messages' );
        const id = 'msg-' + ( ++msgCounter );

        const msg = document.createElement( 'div' );
        msg.className = 'apa-chat-msg apa-chat-msg--' + role;
        msg.id = id;
        msg.textContent = text;

        container.appendChild( msg );
        container.scrollTop = container.scrollHeight;
        return msg;
    }

    function removeMessage( id ) {
        const el = typeof id === 'string' ? document.getElementById( id ) : id;
        if ( el && el.id ) {
            const found = document.getElementById( el.id );
            if ( found ) found.remove();
        }
    }

    // ─── Boot ─────────────────────────────────────────────────────

    createChatWidget();

})();
