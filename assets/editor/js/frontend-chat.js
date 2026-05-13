/**
 * Anchor Page Assistant — Frontend Chat
 *
 * Floating chat widget on the frontend for logged-in admins.
 * Knows which page config it's on, sends AI requests, and
 * auto-refreshes the page after applying changes.
 */
(function() {
    'use strict';

    var data = window.apaFrontend;
    if ( ! data || ! data.restBase ) return;

    var headers = {
        'Content-Type': 'application/json',
        'X-WP-Nonce': data.nonce,
    };

    var STORAGE_KEY = 'apa_chat_' + ( data.pageSlug || 'global' );
    var history = [];
    var chatMessages = [];
    var isOpen = false;
    var selectedContext = '';
    var selectedSectionType  = '';
    var lastSelectedSectionType = '';
    var hasDomContext = false;

    // Load persisted state
    try {
        var stored = JSON.parse( localStorage.getItem( STORAGE_KEY ) || '{}' );
        history = stored.history || [];
        chatMessages = stored.messages || [];
    } catch(e) {}

    // ─── Build UI ─────────────────────────────────────────────────

    function build() {
        // Toggle button
        var toggle = document.createElement( 'button' );
        toggle.className = 'apa-fe-toggle';
        toggle.innerHTML = '<span class="dashicons dashicons-edit-large"></span>';
        toggle.title = 'Page Assistant';
        toggle.addEventListener( 'click', togglePanel );

        // Panel
        var panel = document.createElement( 'div' );
        panel.className = 'apa-fe-panel';
        panel.id = 'apa-fe-panel';

        panel.innerHTML =
            '<div class="apa-fe-header">' +
                ( data.hasPageContentFile ? '<button id="apa-fe-edit-code" class="apa-fe-admin-link" title="Edit raw PHP for this page">Edit code</button>' : '' ) +
                '<div class="apa-fe-header-info">' +
                    '<strong>Page Assistant</strong>' +
                    '<span class="apa-fe-slug">' + esc( data.pageSlug || 'unknown page' ) + '</span>' +
                '</div>' +
                '<div class="apa-fe-header-actions">' +
                    '<button id="apa-fe-undo" class="apa-fe-admin-link" title="Undo last change">Undo</button>' +
                    '<button id="apa-fe-clear" class="apa-fe-admin-link" title="Clear chat">Clear</button>' +
                    '<a href="' + esc( data.adminUrl ) + '" class="apa-fe-admin-link" title="Open editor">Editor</a>' +
                    '<button class="apa-fe-close" title="Close">&times;</button>' +
                '</div>' +
            '</div>' +
            '<div class="apa-fe-messages" id="apa-fe-messages"></div>' +
            '<div class="apa-fe-input-area">' +
                '<div class="apa-fe-input-actions">' +
                    '<button id="apa-fe-attach" class="apa-fe-icon-btn" title="Upload file"><span class="dashicons dashicons-paperclip"></span></button>' +
                    '<button id="apa-fe-browse" class="apa-fe-icon-btn" title="Media library"><span class="dashicons dashicons-images-alt2"></span></button>' +
                '</div>' +
                '<textarea id="apa-fe-input" placeholder="Describe changes for this page..." rows="2"></textarea>' +
                '<button id="apa-fe-send">Send</button>' +
                '<input type="file" id="apa-fe-file-input" accept="image/*,.pdf,.svg,.webp" style="display:none;" multiple>' +
            '</div>';

        document.body.appendChild( toggle );
        document.body.appendChild( panel );

        // Events
        panel.querySelector( '.apa-fe-close' ).addEventListener( 'click', togglePanel );
        document.getElementById( 'apa-fe-send' ).addEventListener( 'click', send );
        document.getElementById( 'apa-fe-attach' ).addEventListener( 'click', function() {
            document.getElementById( 'apa-fe-file-input' ).click();
        } );
        document.getElementById( 'apa-fe-file-input' ).addEventListener( 'change', handleFileUpload );
        document.getElementById( 'apa-fe-browse' ).addEventListener( 'click', openMediaFrame );
        document.getElementById( 'apa-fe-clear' ).addEventListener( 'click', clearChat );
        document.getElementById( 'apa-fe-undo' ).addEventListener( 'click', undoLastChange );

        var editCodeBtn = document.getElementById( 'apa-fe-edit-code' );
        if ( editCodeBtn ) {
            editCodeBtn.addEventListener( 'click', function() {
                if ( ! window.APA || ! window.APA.codeEditor ) {
                    addMsg( 'assistant', 'Code editor is still loading. Try again in a moment.' );
                    return;
                }
                window.APA.codeEditor.open( data.pageSlug, function( info ) {
                    addMsg( 'assistant', 'Saved ' + ( info.path || data.pageSlug ) + '.' );
                    showRefreshButton();
                } );
            } );
        }
        document.getElementById( 'apa-fe-input' ).addEventListener( 'keydown', function( e ) {
            if ( e.key === 'Enter' && ! e.shiftKey ) {
                e.preventDefault();
                send();
            }
        } );

        // Restore persisted messages or show welcome
        if ( chatMessages.length > 0 ) {
            restoreMessages();
        } else if ( data.pageSlug ) {
            addMsg( 'assistant', 'Editing "' + data.pageSlug + '". What would you like to change?' );
        } else {
            addMsg( 'assistant', 'This page doesn\'t have a config file. I can help with site-wide settings.' );
        }
    }

    function togglePanel() {
        isOpen = ! isOpen;
        document.getElementById( 'apa-fe-panel' ).classList.toggle( 'is-open', isOpen );
    }

    // ─── Chat ─────────────────────────────────────────────────────

    function send() {
        var input = document.getElementById( 'apa-fe-input' );
        var message = input.value.trim();
        if ( ! message ) return;

        // Gate: if no DOM context and this looks like a change request, prompt without an AI call.
        if ( ! hasDomContext && ! selectedContext && looksLikeChangeRequest( message ) ) {
            addMsg( 'assistant', 'Cmd+click the section you want to change, then resend.', false, 'tip' );
            return; // Leave message in the input so the user can resend immediately after clicking.
        }

        input.value = '';
        addMsg( 'user', message );

        // Prepend selected context if any, then clear it.
        var fullMessage = selectedContext ? selectedContext + '\n\n' + message : message;
        selectedContext = '';
        hasDomContext   = false;
        var sectionTypeForRequest = selectedSectionType;
        selectedSectionType = '';

        var thinkingEl = addMsg( 'assistant', 'Thinking...', false );

        fetch( data.restBase + 'ai/chat', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify( {
                message: fullMessage,
                history: history.slice( -10 ),
                page_slug: data.pageSlug || '',
                config_key: '',
                post_context: data.postContext || null,
                selected_section_type: sectionTypeForRequest || '',
            } ),
        } )
        .then( function( r ) { return r.json(); } )
        .then( function( result ) {
            thinkingEl.remove();

            if ( result.error ) {
                addMsg( 'assistant', 'Error: ' + result.error );
                return;
            }

            history.push( { role: 'user', content: message } );
            history.push( { role: 'assistant', content: result.reply } );

            // Strip JSON, PHP, and CSS fenced blocks from the rendered reply.
            var displayText = result.reply
                .replace( /```json[\s\S]*?```/g, '' )
                .replace( /```php[\s\S]*?```/g, '' )
                .replace( /```css[\s\S]*?```/g, '' )
                .trim();

            // Direct-PHP mode: AI returned a full file replacement.
            if ( result.file_contents && data.hasPageContentFile && data.pageSlug ) {
                var phpMsg = addMsg( 'assistant', displayText || 'Updated file ready to apply.' );
                var phpBtn = document.createElement( 'button' );
                phpBtn.className = 'apa-fe-apply';
                phpBtn.textContent = 'Apply & Refresh';
                phpBtn.addEventListener( 'click', function() {
                    applyFileContents( result.file_contents, phpBtn );
                } );
                phpMsg.appendChild( phpBtn );

                // Also check for a CSS block in combined PHP+CSS responses.
                var cssMatchPhp = result.reply.match( /```css\s*\n\s*\/\*\s*file:\s*([a-z0-9_-]+\.css)\s*\*\/\s*\n([\s\S]*?)```/ );
                if ( cssMatchPhp ) {
                    var cssFilePhp     = cssMatchPhp[1];
                    var cssContentsPhp = cssMatchPhp[2].trim();
                    var cssBtnPhp      = document.createElement( 'button' );
                    cssBtnPhp.className    = 'button button-secondary apa-apply-btn';
                    cssBtnPhp.textContent  = 'Apply CSS & Refresh';
                    cssBtnPhp.addEventListener( 'click', function() {
                        cssBtnPhp.disabled    = true;
                        cssBtnPhp.textContent = 'Applying…';
                        fetch( data.restBase + 'ai/apply', {
                            method: 'POST',
                            headers: headers,
                            body: JSON.stringify( { css_write: { file: cssFilePhp, contents: cssContentsPhp } } ),
                        } )
                        .then( function(r) { return r.json(); } )
                        .then( function(res) {
                            if ( res.success ) {
                                cssBtnPhp.textContent = 'Applied! Refreshing…';
                                setTimeout( function() { location.reload(); }, 800 );
                            } else {
                                cssBtnPhp.textContent = 'Error: ' + ( res.error || 'unknown' );
                                cssBtnPhp.style.background = '#d63638';
                                cssBtnPhp.style.color = '#fff';
                            }
                        } )
                        .catch( function() {
                            cssBtnPhp.textContent = 'Error';
                            cssBtnPhp.style.background = '#d63638';
                        } );
                    } );
                    phpMsg.appendChild( cssBtnPhp );
                }
                return;
            }

            var msgEl = addMsg( 'assistant', displayText );

            // Apply button if config was returned (config-driven pages).
            if ( result.config && data.pageSlug ) {
                var btn = document.createElement( 'button' );
                btn.className = 'apa-fe-apply';
                btn.textContent = 'Apply & Refresh';
                btn.addEventListener( 'click', function() {
                    apply( result.config, btn );
                } );
                msgEl.appendChild( btn );
            }

            // After extracting any PHP block, also check for a CSS block.
            var cssMatch = result.reply.match( /```css\s*\n\s*\/\*\s*file:\s*([a-z0-9_-]+\.css)\s*\*\/\s*\n([\s\S]*?)```/ );
            if ( cssMatch ) {
                var cssFile     = cssMatch[1];
                var cssContents = cssMatch[2].trim();
                var cssBtn      = document.createElement( 'button' );
                cssBtn.className    = 'button button-secondary apa-apply-btn';
                cssBtn.textContent  = 'Apply CSS & Refresh';
                cssBtn.addEventListener( 'click', function() {
                    cssBtn.disabled    = true;
                    cssBtn.textContent = 'Applying…';
                    fetch( data.restBase + 'ai/apply', {
                        method: 'POST',
                        headers: headers,
                        body: JSON.stringify( { css_write: { file: cssFile, contents: cssContents } } ),
                    } )
                    .then( function(r) { return r.json(); } )
                    .then( function(res) {
                        if ( res.success ) {
                            cssBtn.textContent = 'Applied! Refreshing…';
                            setTimeout( function() { location.reload(); }, 800 );
                        } else {
                            cssBtn.textContent = 'Error: ' + ( res.error || 'unknown' );
                            cssBtn.style.background = '#d63638';
                            cssBtn.style.color = '#fff';
                        }
                    } )
                    .catch( function() {
                        cssBtn.textContent = 'Error';
                        cssBtn.style.background = '#d63638';
                    } );
                } );
                msgEl.appendChild( cssBtn );
            }
        } )
        .catch( function( err ) {
            thinkingEl.remove();
            addMsg( 'assistant', 'Error: ' + err.message );
        } );
    }

    function applyFileContents( contents, btn ) {
        btn.disabled = true;
        btn.textContent = 'Applying...';

        fetch( data.restBase + 'ai/apply', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify( {
                file_contents: contents,
                page_slug: data.pageSlug,
            } ),
        } )
        .then( function( r ) { return r.json().then( function( body ) { return { ok: r.ok, body: body }; } ); } )
        .then( function( res ) {
            if ( res.ok && res.body.success ) {
                btn.textContent = 'Applied';
                btn.style.background = '#46b450';
                showRefreshButton();
            } else {
                btn.disabled = false;
                btn.textContent = 'Retry';
                btn.style.background = '#d63638';
                addMsg( 'assistant', 'Failed: ' + ( ( res.body && res.body.error ) || 'Unknown error' ) );
            }
        } )
        .catch( function( err ) {
            btn.disabled = false;
            btn.textContent = 'Retry';
            addMsg( 'assistant', 'Failed: ' + err.message );
        } );
    }

    function showRefreshButton() {
        var msg = addMsg( 'assistant', 'Refresh to see your changes.' );
        var btn = document.createElement( 'button' );
        btn.className = 'apa-fe-apply';
        btn.textContent = 'Refresh preview';
        btn.addEventListener( 'click', function() {
            window.location.reload();
        } );
        msg.appendChild( btn );
    }

    function apply( config, btn ) {
        btn.disabled = true;
        btn.textContent = 'Applying...';

        fetch( data.restBase + 'ai/apply', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify( {
                config: config,
                page_slug: data.pageSlug,
                config_key: '',
            } ),
        } )
        .then( function( r ) { return r.json(); } )
        .then( function( result ) {
            if ( result.success ) {
                btn.textContent = 'Applied! Refreshing...';
                btn.style.background = '#46b450';
                setTimeout( function() { window.location.reload(); }, 800 );
            } else {
                btn.textContent = 'Error';
                btn.style.background = '#d63638';
                addMsg( 'assistant', 'Failed: ' + ( result.error || 'Unknown error' ) );
            }
        } )
        .catch( function( err ) {
            btn.textContent = 'Error';
            addMsg( 'assistant', 'Failed: ' + err.message );
        } );
    }

    // ─── Persistence ────────────────────────────────────────────────

    function saveState() {
        try {
            localStorage.setItem( STORAGE_KEY, JSON.stringify( {
                history: history.slice( -20 ),
                messages: chatMessages.slice( -40 ),
            } ) );
        } catch(e) {}
    }

    function restoreMessages() {
        var container = document.getElementById( 'apa-fe-messages' );
        if ( ! chatMessages.length ) return;

        chatMessages.forEach( function( m ) {
            var msg = document.createElement( 'div' );
            msg.className = 'apa-fe-msg apa-fe-msg--' + m.role;
            msg.textContent = m.text;
            container.appendChild( msg );
        } );
        container.scrollTop = container.scrollHeight;
    }

    function clearChat() {
        history = [];
        chatMessages = [];
        selectedContext = '';
        hasDomContext   = false;
        saveState();
        document.getElementById( 'apa-fe-messages' ).innerHTML = '';
        addMsg( 'assistant', 'Chat cleared. What would you like to do?' );
    }

    // ─── Undo ─────────────────────────────────────────────────────────

    function undoLastChange() {
        if ( ! data.pageSlug ) {
            addMsg( 'assistant', 'Undo requires being on a config-driven page.' );
            return;
        }

        addMsg( 'assistant', 'Looking for backups...', false );

        fetch( data.restBase + 'history/pages/' + data.pageSlug, {
            headers: { 'X-WP-Nonce': data.nonce },
        } )
        .then( function( r ) { return r.json(); } )
        .then( function( backups ) {
            // Remove the "looking" message
            var msgs = document.getElementById( 'apa-fe-messages' );
            if ( msgs.lastChild ) msgs.lastChild.remove();

            if ( ! backups || ! backups.length ) {
                addMsg( 'assistant', 'No backups found for this page.' );
                return;
            }

            var latest = backups[0];
            var msg = addMsg( 'assistant', 'Found backup from ' + latest.date + '. Restore it?' );

            var btn = document.createElement( 'button' );
            btn.className = 'apa-fe-apply';
            btn.textContent = 'Restore & Refresh';
            btn.addEventListener( 'click', function() {
                btn.disabled = true;
                btn.textContent = 'Restoring...';

                fetch( data.restBase + 'history/restore', {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify( {
                        backup_file: latest.file,
                        type: 'pages',
                        slug: data.pageSlug,
                    } ),
                } )
                .then( function( r ) { return r.json(); } )
                .then( function( result ) {
                    if ( result.success ) {
                        addMsg( 'assistant', 'Restored! Refreshing...' );
                        setTimeout( function() { window.location.reload(); }, 800 );
                    } else {
                        addMsg( 'assistant', 'Failed: ' + ( result.error || 'Unknown error' ) );
                        btn.disabled = false;
                        btn.textContent = 'Restore & Refresh';
                    }
                } );
            } );
            msg.appendChild( btn );
        } );
    }

    // ─── File Upload ────────────────────────────────────────────────

    function handleFileUpload() {
        var input = document.getElementById( 'apa-fe-file-input' );
        var files = input.files;
        if ( ! files.length ) return;

        for ( var i = 0; i < files.length; i++ ) {
            uploadFile( files[ i ] );
        }
        input.value = '';
    }

    function uploadFile( file ) {
        var msgEl = addMsg( 'assistant', 'Uploading ' + file.name + '...' );

        var formData = new FormData();
        formData.append( 'file', file );

        fetch( data.restBase + 'media/upload', {
            method: 'POST',
            headers: { 'X-WP-Nonce': data.nonce },
            body: formData,
        } )
        .then( function( r ) { return r.json(); } )
        .then( function( result ) {
            msgEl.remove();
            if ( result.error ) {
                addMsg( 'assistant', 'Upload failed: ' + result.error );
                return;
            }

            var att = result.attachment;

            // Show the uploaded image
            var msg = addMsg( 'assistant', '' );
            msg.innerHTML = '<div class="apa-fe-uploaded">' +
                '<img src="' + esc( att.thumbnail ) + '" alt="">' +
                '<div class="apa-fe-uploaded-info">' +
                    '<strong>' + esc( att.filename ) + '</strong>' +
                    '<span>ID: ' + att.id + ' • ' + ( att.width || '?' ) + '×' + ( att.height || '?' ) + '</span>' +
                    '<code>' + esc( att.url ) + '</code>' +
                '</div></div>' +
                'Uploaded! Tell me where to use this image.';

            // Store the URL as context for the AI
            selectedContext = '[Uploaded image: url="' + att.url + '", id=' + att.id + ', filename="' + att.filename + '"]';
        } )
        .catch( function( err ) {
            msgEl.remove();
            addMsg( 'assistant', 'Upload failed: ' + err.message );
        } );
    }

    // ─── Media Browser (WordPress media frame) ──────────────────────

    var mediaFrame = null;

    function openMediaFrame() {
        if ( ! window.wp || ! window.wp.media ) {
            addMsg( 'assistant', 'Media library is unavailable on this page.' );
            return;
        }

        if ( ! mediaFrame ) {
            mediaFrame = window.wp.media( {
                title: 'Select an image',
                button: { text: 'Use this image' },
                library: { type: 'image' },
                multiple: false,
            } );

            mediaFrame.on( 'select', function() {
                var att = mediaFrame.state().get( 'selection' ).first().toJSON();
                var sizes = att.sizes || {};
                var thumb = ( sizes.thumbnail && sizes.thumbnail.url )
                    || ( sizes.medium && sizes.medium.url )
                    || att.url;

                selectMediaItem( {
                    id:        att.id,
                    url:       att.url,
                    thumbnail: thumb,
                    title:     att.title || att.filename || '',
                    filename:  att.filename || '',
                    width:     att.width,
                    height:    att.height,
                } );
            } );
        }

        mediaFrame.open();
    }

    function selectMediaItem( item ) {
        var msg = addMsg( 'assistant', '' );
        msg.innerHTML = '<div class="apa-fe-uploaded">' +
            '<img src="' + esc( item.thumbnail ) + '" alt="">' +
            '<div class="apa-fe-uploaded-info">' +
                '<strong>' + esc( item.filename ) + '</strong>' +
                '<span>' + ( item.width || '?' ) + '×' + ( item.height || '?' ) + '</span>' +
            '</div></div>' +
            'Selected from library. Tell me where to use this image.';

        selectedContext = '[Media library image: url="' + item.url + '", id=' + item.id + ', filename="' + item.filename + '"]';
        document.getElementById( 'apa-fe-input' ).focus();
    }

    // ─── Sticky footer offset ────────────────────────────────────

    function watchStickyFooter() {
        var sf = document.querySelector( '[data-sticky-footer]' );
        if ( ! sf ) return;

        var observer = new MutationObserver( function() {
            var visible = sf.classList.contains( 'anchor-sticky-footer--visible' );
            document.documentElement.style.setProperty( '--apa-bottom', visible ? '84px' : '24px' );
        } );
        observer.observe( sf, { attributes: true, attributeFilter: ['class'] } );
    }
    watchStickyFooter();

    // ─── Helpers ──────────────────────────────────────────────────

    function addMsg( role, text, persist, kind ) {
        var container = document.getElementById( 'apa-fe-messages' );
        var msg = document.createElement( 'div' );
        msg.className = 'apa-fe-msg apa-fe-msg--' + role + ( kind ? ' apa-fe-msg--' + kind : '' );
        msg.textContent = text;
        container.appendChild( msg );
        container.scrollTop = container.scrollHeight;

        // Persist by default (skip for transient messages like "Thinking...")
        if ( persist !== false && text ) {
            chatMessages.push( { role: role, text: text } );
            saveState();
        }
        return msg;
    }

    function esc( str ) {
        var d = document.createElement( 'div' );
        d.textContent = str;
        return d.innerHTML;
    }

    // ─── Cmd+Click Inspector ──────────────────────────────────────

    var inspectMode = false;
    var highlightEl = null;
    var overlay = null;

    function initInspector() {
        // Create full-page overlay that blocks all clicks in inspect mode
        overlay = document.createElement( 'div' );
        overlay.className = 'apa-inspect-overlay';
        document.body.appendChild( overlay );

        // Enter inspect mode on Cmd/Ctrl keydown
        document.addEventListener( 'keydown', function( e ) {
            if ( ( e.key === 'Meta' || e.key === 'Control' ) && ! inspectMode ) {
                inspectMode = true;
                overlay.classList.add( 'is-active' );
                document.body.style.cursor = 'crosshair';
            }
        } );

        // Exit inspect mode on keyup
        document.addEventListener( 'keyup', function( e ) {
            if ( e.key === 'Meta' || e.key === 'Control' ) {
                inspectMode = false;
                overlay.classList.remove( 'is-active' );
                document.body.style.cursor = '';
                clearHighlight();
            }
        } );

        // Highlight the element under cursor while in inspect mode
        overlay.addEventListener( 'mousemove', function( e ) {
            if ( ! inspectMode ) return;

            // Temporarily hide overlay to get the real element underneath
            overlay.style.pointerEvents = 'none';
            var target = document.elementFromPoint( e.clientX, e.clientY );
            overlay.style.pointerEvents = '';

            if ( ! target || target === overlay ) return;

            // Find the best selectable element — prefer specific components, fall back to sections
            var el = target.closest( '.anchor-btn, .anchor-badge, .anchor-card, .anchor-nav__link, .anchor-hero__heading, h1, h2, h3, p, .anchor-image-wrapper, .anchor-floating-badge, .anchor-team-card, .anchor-post-card, .anchor-faq-item, .anchor-icon-box' )
                  || target.closest( '[data-section-type]' )
                  || target;

            // Don't highlight the chat panel itself
            if ( el.closest( '.apa-fe-panel, .apa-fe-toggle, .apa-inspect-overlay' ) ) return;

            highlight( el );
        } );

        // Click to select in inspect mode
        overlay.addEventListener( 'click', function( e ) {
            if ( ! inspectMode ) return;

            e.preventDefault();
            e.stopPropagation();

            // Get real element under overlay
            overlay.style.pointerEvents = 'none';
            var target = document.elementFromPoint( e.clientX, e.clientY );
            overlay.style.pointerEvents = '';

            if ( ! target ) return;

            var el = target.closest( '.anchor-btn, .anchor-badge, .anchor-card, .anchor-nav__link, .anchor-hero__heading, h1, h2, h3, p, .anchor-image-wrapper, .anchor-floating-badge, .anchor-team-card, .anchor-post-card, .anchor-faq-item, .anchor-icon-box' )
                  || target.closest( '[data-section-type]' )
                  || target;

            if ( el.closest( '.apa-fe-panel, .apa-fe-toggle' ) ) return;

            // Always use the section element for context (not sub-elements).
            var section      = el.closest( '[data-section-type]' );
            var contextEl    = section || el;
            var sectionType  = section ? section.dataset.sectionType  : '';
            var sectionVariant = section ? section.dataset.sectionVariant : '';
            var sectionIndex = section ? getSectionIndex( section ) : -1;
            var tag          = el.tagName.toLowerCase();

            // Pretty label: "services_tabs" → "Services Tabs"
            var label = sectionType
                ? sectionType.replace( /_/g, ' ' ).replace( /\b\w/g, function(c) { return c.toUpperCase(); } )
                : tag;

            // Heading text for display label
            var heading     = contextEl.querySelector( 'h1, h2, h3' );
            var headingText = heading ? heading.textContent.trim().substring( 0, 40 ) : '';

            // ── Collect DOM context ─────────────────────────────────
            var rawHtml     = contextEl.outerHTML;
            var htmlSnippet = rawHtml.length > 4000 ? rawHtml.substring( 0, 4000 ) + '\n... [truncated]' : rawHtml;
            var allClasses  = extractAllClasses( rawHtml );
            var cssResult   = extractCssForClasses( allClasses );

            selectedContext =
                '[DOM Context]\n' +
                'Section: ' + ( sectionType || tag ) +
                ( sectionVariant ? ' (variant: ' + sectionVariant + ')' : '' ) +
                ' (index: ' + sectionIndex + ')\n' +
                ( data.pageContentPath ? 'File: ' + data.pageContentPath + '\n' : '' ) +
                '\nHTML:\n' + htmlSnippet + '\n' +
                ( cssResult.rules
                    ? '\nRelevant CSS' + ( cssResult.files.length ? ' (from ' + cssResult.files.join( ', ' ) + ')' : '' ) + ':\n' + cssResult.rules + '\n'
                    : '' ) +
                '[/DOM Context]';

            hasDomContext           = true;
            selectedSectionType     = sectionType || '';
            lastSelectedSectionType = sectionType || '';

            // Open chat and focus input
            if ( ! isOpen ) togglePanel();
            document.getElementById( 'apa-fe-input' ).focus();
            clearHighlight();

            // Confirmation — no AI call, JS shows this directly
            addMsg(
                'assistant',
                '✓ ' + label +
                    ( headingText ? ' — “' + headingText + '”' : '' ) +
                    ' (' + cssResult.ruleCount + ' CSS rules captured). What would you like to change?',
                true,
                'context'
            );
        } );
    }

    function highlight( el ) {
        clearHighlight();
        highlightEl = el;
        el.style.outline = '2px solid rgba(34, 113, 177, 0.8)';
        el.style.outlineOffset = '2px';
        el.style.boxShadow = '0 0 0 4px rgba(34, 113, 177, 0.15)';
    }

    function clearHighlight() {
        if ( highlightEl ) {
            highlightEl.style.outline = '';
            highlightEl.style.outlineOffset = '';
            highlightEl.style.boxShadow = '';
            highlightEl = null;
        }
    }

    function getSectionIndex( el ) {
        var sections = document.querySelectorAll( '[data-section-type]' );
        for ( var i = 0; i < sections.length; i++ ) {
            if ( sections[ i ] === el ) return i;
        }
        return -1;
    }

    // ─── DOM Context Utilities ────────────────────────────────────

    function looksLikeChangeRequest( msg ) {
        var lower = msg.toLowerCase().trim();
        if ( lower.slice(-1) === '?' ) return false;
        var qWords = [ 'what ', 'how ', 'why ', 'where ', 'which ', 'who ', 'is ', 'are ', 'do ', 'does ',
                       'can ', 'could ', 'should ', 'would ', 'tell me', 'explain', 'describe', 'show me', 'list ' ];
        for ( var i = 0; i < qWords.length; i++ ) {
            if ( lower.indexOf( qWords[i] ) === 0 ) return false;
        }
        // Very short or single-word messages are ambiguous — let through
        if ( msg.trim().split( /\s+/ ).length <= 2 ) return false;
        return true;
    }

    function extractAllClasses( htmlStr ) {
        var classes = [];
        var re = /\bclass="([^"]*)"/g;
        var m;
        while ( ( m = re.exec( htmlStr ) ) !== null ) {
            m[1].split( /\s+/ ).forEach( function( c ) {
                c = c.trim();
                if ( c && classes.indexOf( c ) === -1 ) classes.push( c );
            } );
        }
        return classes;
    }

    function selectorMatchesClasses( selectorText, classes ) {
        if ( ! selectorText ) return false;
        return classes.some( function( c ) {
            return selectorText.indexOf( '.' + c ) !== -1;
        } );
    }

    function collectCssRule( rule, classes, rules, seen ) {
        if ( rule.cssRules ) {
            // Wrapper: @media, @supports, @layer, etc.
            var inner = [];
            for ( var k = 0; k < rule.cssRules.length; k++ ) {
                var r = rule.cssRules[ k ];
                if ( r.selectorText && selectorMatchesClasses( r.selectorText, classes ) ) {
                    inner.push( '  ' + r.cssText );
                }
            }
            if ( inner.length ) {
                var wrapHead = rule.cssText.substring( 0, rule.cssText.indexOf( '{' ) ).trim();
                var text     = wrapHead + ' {\n' + inner.join( '\n' ) + '\n}';
                if ( ! seen[ text ] ) { seen[ text ] = true; rules.push( text ); }
            }
        } else if ( rule.selectorText && selectorMatchesClasses( rule.selectorText, classes ) ) {
            var text = rule.cssText;
            if ( ! seen[ text ] ) { seen[ text ] = true; rules.push( text ); }
        }
    }

    function extractCssForClasses( classes ) {
        if ( ! classes.length ) return { rules: '', files: [], ruleCount: 0 };
        var rules = [];
        var seen  = {};
        var files = [];
        var sheets = document.styleSheets;
        for ( var i = 0; i < sheets.length; i++ ) {
            try {
                var sheet    = sheets[ i ];
                var cssRules = sheet.cssRules || sheet.rules;
                if ( ! cssRules ) continue;
                var prevLen  = rules.length;
                for ( var j = 0; j < cssRules.length; j++ ) {
                    collectCssRule( cssRules[ j ], classes, rules, seen );
                }
                if ( rules.length > prevLen && sheet.href ) {
                    var filename = sheet.href.split( '/' ).pop().split( '?' )[ 0 ];
                    if ( files.indexOf( filename ) === -1 ) files.push( filename );
                }
            } catch(e) { /* cross-origin stylesheet — skip */ }
        }
        return { rules: rules.join( '\n' ), files: files, ruleCount: rules.length };
    }

    // Boot
    build();
    initInspector();
})();
