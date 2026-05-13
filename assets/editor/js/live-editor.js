/**
 * Anchor Page Assistant — Live Editor (Split Screen)
 *
 * Left: JSON config editor. Right: live iframe preview.
 * Save writes the config and refreshes the iframe.
 */
(function() {
    'use strict';

    var data = window.apaLive;
    if ( ! data ) return;

    var headers = {
        'Content-Type': 'application/json',
        'X-WP-Nonce': data.nonce,
    };

    var currentSlug = '';
    var isDirty = false;

    // DOM
    var pageSelect = document.getElementById( 'apa-live-page-select' );
    var configArea = document.getElementById( 'apa-live-config' );
    var saveBtn    = document.getElementById( 'apa-live-save' );
    var status     = document.getElementById( 'apa-live-status' );
    var iframe     = document.getElementById( 'apa-live-iframe' );
    var urlDisplay = document.getElementById( 'apa-live-url' );
    var refreshBtn = document.getElementById( 'apa-live-refresh' );

    // ─── Init ─────────────────────────────────────────────────────

    function init() {
        // Populate page select
        data.pages.forEach( function( page ) {
            var opt = document.createElement( 'option' );
            opt.value = page.slug;
            opt.textContent = page.slug;
            pageSelect.appendChild( opt );
        } );

        // Events
        pageSelect.addEventListener( 'change', function() {
            if ( isDirty && ! confirm( 'Unsaved changes. Continue?' ) ) {
                pageSelect.value = currentSlug;
                return;
            }
            loadPage( this.value );
        } );

        saveBtn.addEventListener( 'click', save );
        refreshBtn.addEventListener( 'click', function() {
            iframe.src = iframe.src;
        } );

        configArea.addEventListener( 'input', function() {
            isDirty = true;
            saveBtn.disabled = false;
            status.textContent = 'Unsaved changes';
            status.style.color = '#dba617';
        } );

        // Keyboard shortcut: Ctrl/Cmd + S to save
        document.addEventListener( 'keydown', function( e ) {
            if ( ( e.ctrlKey || e.metaKey ) && e.key === 's' ) {
                e.preventDefault();
                if ( currentSlug && isDirty ) save();
            }
        } );
    }

    // ─── Load Page ────────────────────────────────────────────────

    function loadPage( slug ) {
        if ( ! slug ) {
            configArea.value = '';
            iframe.src = 'about:blank';
            urlDisplay.textContent = '';
            saveBtn.disabled = true;
            currentSlug = '';
            return;
        }

        status.textContent = 'Loading...';
        status.style.color = 'rgba(255,255,255,0.5)';

        fetch( data.restBase + 'pages/' + slug, { headers: headers } )
            .then( function( r ) { return r.json(); } )
            .then( function( config ) {
                currentSlug = slug;
                configArea.value = JSON.stringify( config, null, 2 );
                isDirty = false;
                saveBtn.disabled = true;
                status.textContent = 'Loaded';

                // Map slug to URL
                var pageUrl = slugToUrl( slug );
                iframe.src = pageUrl;
                urlDisplay.textContent = pageUrl;
            } )
            .catch( function( err ) {
                status.textContent = 'Error: ' + err.message;
                status.style.color = '#e65054';
            } );
    }

    // ─── Save ─────────────────────────────────────────────────────

    function save() {
        if ( ! currentSlug ) return;

        var parsed;
        try {
            parsed = JSON.parse( configArea.value );
        } catch ( e ) {
            status.textContent = 'Invalid JSON: ' + e.message;
            status.style.color = '#e65054';
            return;
        }

        saveBtn.disabled = true;
        status.textContent = 'Saving...';
        status.style.color = 'rgba(255,255,255,0.5)';

        fetch( data.restBase + 'pages/' + currentSlug, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify( parsed ),
        } )
        .then( function( r ) { return r.json(); } )
        .then( function( result ) {
            if ( result.success ) {
                isDirty = false;
                status.textContent = 'Saved! Refreshing preview...';
                status.style.color = '#68de7c';

                // Refresh the iframe
                setTimeout( function() {
                    iframe.src = iframe.src;
                    status.textContent = 'Saved';
                }, 300 );
            } else {
                status.textContent = 'Error: ' + ( result.error || 'Unknown' );
                status.style.color = '#e65054';
                saveBtn.disabled = false;
            }
        } )
        .catch( function( err ) {
            status.textContent = 'Error: ' + err.message;
            status.style.color = '#e65054';
            saveBtn.disabled = false;
        } );
    }

    // ─── Helpers ──────────────────────────────────────────────────

    function slugToUrl( slug ) {
        var map = {
            'home':     '/',
            'blog':     '/blog/',
            'events':   '/events/',
        };
        if ( map[ slug ] ) return data.siteUrl + map[ slug ].replace( /^\//, '' );
        return data.siteUrl + slug + '/';
    }

    // Listen for saves from inline-edit inside the iframe
    window.addEventListener( 'message', function( e ) {
        if ( e.data && e.data.type === 'apa-inline-saved' && currentSlug ) {
            status.textContent = 'Inline edit saved — reloading config...';
            status.style.color = '#68de7c';
            // Reload the config to reflect changes
            setTimeout( function() { loadPage( currentSlug ); }, 500 );
        }
    } );

    init();
})();
