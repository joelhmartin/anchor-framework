/**
 * Anchor Page Assistant — Admin App
 *
 * Handles page list, section editor, and config management.
 * Communicates with the REST API to read/write config files.
 */
(function() {
    'use strict';

    const { restBase, nonce, pages, sections } = window.apaData || {};
    if ( ! restBase ) return;

    const headers = {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce,
    };

    // State
    let currentPage = null;
    let currentConfig = null;
    let pageData = null;
    let isDirty = false;

    // DOM refs
    const pageList      = document.getElementById( 'apa-page-list' );
    const emptyState    = document.getElementById( 'apa-empty-state' );
    const editor        = document.getElementById( 'apa-editor' );
    const editorTitle   = document.getElementById( 'apa-editor-title' );
    const sectionList   = document.getElementById( 'apa-section-list' );
    const saveBtn       = document.getElementById( 'apa-save-btn' );
    const previewLink   = document.getElementById( 'apa-preview-link' );
    const addSelect     = document.getElementById( 'apa-add-section-type' );
    const jsonEditor    = document.getElementById( 'apa-json-editor' );
    const jsonTitle     = document.getElementById( 'apa-json-title' );
    const jsonTextarea  = document.getElementById( 'apa-json-textarea' );
    const jsonSave      = document.getElementById( 'apa-json-save' );

    // ─── Init ─────────────────────────────────────────────────────

    function init() {
        renderPageList();
        populateAddSection();
        bindEvents();
    }

    function renderPageList() {
        if ( ! pageList ) return;
        pageList.innerHTML = '';

        pages.forEach( function( page ) {
            const li = document.createElement( 'li' );
            const btn = document.createElement( 'button' );
            btn.className = 'apa-page-btn';
            btn.textContent = page.slug;
            btn.dataset.slug = page.slug;
            btn.addEventListener( 'click', function() { loadPage( page.slug ); } );
            li.appendChild( btn );
            pageList.appendChild( li );
        } );
    }

    function populateAddSection() {
        if ( ! addSelect ) return;
        Object.keys( sections ).forEach( function( type ) {
            const opt = document.createElement( 'option' );
            opt.value = type;
            opt.textContent = sections[ type ].label;
            addSelect.appendChild( opt );
        } );
    }

    function bindEvents() {
        if ( saveBtn ) saveBtn.addEventListener( 'click', savePage );

        if ( addSelect ) {
            addSelect.addEventListener( 'change', function() {
                if ( ! this.value || ! pageData ) return;
                addSection( this.value );
                this.value = '';
            } );
        }

        // Config buttons (site, data, globals, media)
        document.querySelectorAll( '.apa-config-btn' ).forEach( function( btn ) {
            btn.addEventListener( 'click', function() {
                loadConfig( this.dataset.config );
            } );
        } );

        if ( jsonSave ) jsonSave.addEventListener( 'click', saveConfig );
    }

    // ─── Page Loading ─────────────────────────────────────────────

    function loadPage( slug ) {
        if ( isDirty && ! confirm( 'Unsaved changes will be lost. Continue?' ) ) return;

        // Update active state
        document.querySelectorAll( '.apa-page-btn, .apa-config-btn' ).forEach( function( b ) {
            b.classList.remove( 'is-active' );
        } );
        const activeBtn = pageList.querySelector( '[data-slug="' + slug + '"]' );
        if ( activeBtn ) activeBtn.classList.add( 'is-active' );

        fetch( restBase + 'pages/' + slug, { headers: headers } )
            .then( function( r ) { return r.json(); } )
            .then( function( data ) {
                currentPage   = slug;
                currentConfig = null;
                pageData      = data;
                isDirty       = false;
                renderEditor();
            } )
            .catch( function( err ) { showStatus( 'Error loading page: ' + err.message, 'error' ); } );
    }

    // ─── Section Rendering ────────────────────────────────────────

    function renderEditor() {
        emptyState.style.display = 'none';
        jsonEditor.style.display = 'none';
        editor.style.display = '';

        editorTitle.textContent = currentPage;
        previewLink.href = '/' + currentPage.replace( 'home', '' ) + '/';

        sectionList.innerHTML = '';

        if ( ! pageData.sections ) {
            sectionList.innerHTML = '<p class="apa-loading">No sections found. This page config may use a different structure.</p>';
            return;
        }

        pageData.sections.forEach( function( section, index ) {
            if ( ! section ) return; // skip null entries

            if ( typeof section === 'string' ) {
                // Global reference
                sectionList.appendChild( renderGlobalRef( section, index ) );
            } else {
                sectionList.appendChild( renderSection( section, index ) );
            }
        } );
    }

    function renderGlobalRef( ref, index ) {
        const item = document.createElement( 'div' );
        item.className = 'apa-section-item';
        item.innerHTML = '<div class="apa-section-header">' +
            '<span class="apa-section-type">global</span>' +
            '<span class="apa-section-title">' + escHtml( ref ) + '</span>' +
            '<div class="apa-section-actions">' +
                '<button class="apa-delete" data-index="' + index + '">Remove</button>' +
            '</div></div>';
        item.querySelector( '.apa-delete' ).addEventListener( 'click', function( e ) {
            e.stopPropagation();
            removeSection( index );
        } );
        return item;
    }

    function renderSection( section, index ) {
        const type    = section.type || 'unknown';
        const variant = section.variant || '';
        const schema  = sections[ type ];
        const label   = schema ? schema.label : type;
        const props   = section.props || {};

        // Determine a display title from props
        const title = props.heading || props.eyebrow || '';

        const item = document.createElement( 'div' );
        item.className = 'apa-section-item';

        // Header
        const header = document.createElement( 'div' );
        header.className = 'apa-section-header';
        header.innerHTML =
            '<span class="apa-section-type">' + escHtml( type ) + '</span>' +
            ( variant ? '<span class="apa-section-variant">' + escHtml( variant ) + '</span>' : '' ) +
            '<span class="apa-section-title">' + escHtml( title ) + '</span>' +
            '<div class="apa-section-actions">' +
                ( index > 0 ? '<button class="apa-move-up" data-index="' + index + '">↑</button>' : '' ) +
                ( index < pageData.sections.length - 1 ? '<button class="apa-move-down" data-index="' + index + '">↓</button>' : '' ) +
                '<button class="apa-delete" data-index="' + index + '">×</button>' +
            '</div>';

        header.addEventListener( 'click', function( e ) {
            if ( e.target.tagName === 'BUTTON' ) return;
            body.classList.toggle( 'is-open' );
        } );

        // Body (prop editor)
        const body = document.createElement( 'div' );
        body.className = 'apa-section-body';

        if ( schema && schema.props ) {
            Object.keys( schema.props ).forEach( function( propKey ) {
                const propSchema = schema.props[ propKey ];
                const value = props[ propKey ];
                body.appendChild( renderField( propKey, propSchema, value, index ) );
            } );
        }

        // Variant selector
        if ( schema && schema.variants && schema.variants.length > 0 ) {
            const variantField = document.createElement( 'div' );
            variantField.className = 'apa-field';
            variantField.innerHTML = '<label>Variant</label>';
            const sel = document.createElement( 'select' );
            sel.innerHTML = '<option value="">(default)</option>' +
                schema.variants.map( function( v ) {
                    return '<option value="' + v + '"' + ( v === variant ? ' selected' : '' ) + '>' + v + '</option>';
                } ).join( '' );
            sel.addEventListener( 'change', function() {
                pageData.sections[ index ].variant = this.value;
                isDirty = true;
            } );
            variantField.appendChild( sel );
            body.insertBefore( variantField, body.firstChild );
        }

        // flush_bottom toggle
        const flushField = document.createElement( 'div' );
        flushField.className = 'apa-field';
        flushField.innerHTML = '<label><input type="checkbox" ' +
            ( section.flush_bottom ? 'checked' : '' ) +
            '> Remove bottom padding (flush_bottom)</label>';
        flushField.querySelector( 'input' ).addEventListener( 'change', function() {
            pageData.sections[ index ].flush_bottom = this.checked;
            isDirty = true;
        } );
        body.appendChild( flushField );

        // Action buttons
        header.querySelector( '.apa-delete' ).addEventListener( 'click', function( e ) {
            e.stopPropagation();
            removeSection( index );
        } );

        const moveUp = header.querySelector( '.apa-move-up' );
        const moveDown = header.querySelector( '.apa-move-down' );
        if ( moveUp ) moveUp.addEventListener( 'click', function( e ) { e.stopPropagation(); moveSection( index, -1 ); } );
        if ( moveDown ) moveDown.addEventListener( 'click', function( e ) { e.stopPropagation(); moveSection( index, 1 ); } );

        item.appendChild( header );
        item.appendChild( body );
        return item;
    }

    function renderField( key, schema, value, sectionIndex ) {
        const field = document.createElement( 'div' );
        field.className = 'apa-field';

        const label = document.createElement( 'label' );
        label.textContent = schema.label || key;
        field.appendChild( label );

        const type = schema.type || 'string';

        if ( type === 'boolean' ) {
            const cb = document.createElement( 'input' );
            cb.type = 'checkbox';
            cb.checked = !! value;
            cb.addEventListener( 'change', function() {
                setNestedProp( sectionIndex, key, this.checked );
            } );
            label.prepend( cb );
            label.prepend( document.createTextNode( ' ' ) );
        } else if ( type === 'select' && schema.options ) {
            const sel = document.createElement( 'select' );
            schema.options.forEach( function( opt ) {
                const o = document.createElement( 'option' );
                o.value = opt;
                o.textContent = opt;
                if ( opt === value ) o.selected = true;
                sel.appendChild( o );
            } );
            sel.addEventListener( 'change', function() {
                setNestedProp( sectionIndex, key, this.value );
            } );
            field.appendChild( sel );
        } else if ( type === 'number' ) {
            const inp = document.createElement( 'input' );
            inp.type = 'number';
            inp.value = value !== undefined ? value : ( schema.default || '' );
            inp.addEventListener( 'input', function() {
                setNestedProp( sectionIndex, key, parseInt( this.value, 10 ) || 0 );
            } );
            field.appendChild( inp );
        } else if ( type === 'text' || type === 'array' ) {
            // Show as JSON textarea for complex types
            const ta = document.createElement( 'textarea' );
            ta.value = value ? JSON.stringify( value, null, 2 ) : '';
            ta.addEventListener( 'change', function() {
                try {
                    setNestedProp( sectionIndex, key, JSON.parse( this.value ) );
                } catch( e ) {
                    // Try as plain string
                    setNestedProp( sectionIndex, key, this.value );
                }
            } );
            field.appendChild( ta );
        } else if ( type === 'cta' || type === 'object' ) {
            const ta = document.createElement( 'textarea' );
            ta.value = value ? JSON.stringify( value, null, 2 ) : '';
            ta.rows = 4;
            ta.addEventListener( 'change', function() {
                try {
                    setNestedProp( sectionIndex, key, JSON.parse( this.value ) );
                } catch( e ) { /* invalid json, ignore */ }
            } );
            field.appendChild( ta );
        } else {
            // String / media
            const inp = document.createElement( 'input' );
            inp.type = 'text';
            inp.value = value !== undefined && value !== null ? String( value ) : '';
            inp.addEventListener( 'input', function() {
                setNestedProp( sectionIndex, key, this.value );
            } );
            field.appendChild( inp );
        }

        return field;
    }

    // ─── Section Operations ───────────────────────────────────────

    function setNestedProp( sectionIndex, key, value ) {
        if ( ! pageData.sections[ sectionIndex ].props ) {
            pageData.sections[ sectionIndex ].props = {};
        }
        pageData.sections[ sectionIndex ].props[ key ] = value;
        isDirty = true;
    }

    function addSection( type ) {
        const schema   = sections[ type ];
        const defaults = schema ? schema.defaults || {} : {};
        const section  = { type: type, props: Object.assign( {}, defaults ) };

        if ( ! pageData.sections ) pageData.sections = [];
        pageData.sections.push( section );
        isDirty = true;
        renderEditor();
    }

    function removeSection( index ) {
        if ( ! confirm( 'Remove this section?' ) ) return;
        pageData.sections.splice( index, 1 );
        isDirty = true;
        renderEditor();
    }

    function moveSection( index, direction ) {
        const target = index + direction;
        if ( target < 0 || target >= pageData.sections.length ) return;
        const temp = pageData.sections[ index ];
        pageData.sections[ index ] = pageData.sections[ target ];
        pageData.sections[ target ] = temp;
        isDirty = true;
        renderEditor();
    }

    // ─── Save ─────────────────────────────────────────────────────

    function savePage() {
        if ( ! currentPage || ! pageData ) return;

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        fetch( restBase + 'pages/' + currentPage, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify( pageData ),
        } )
        .then( function( r ) { return r.json(); } )
        .then( function( data ) {
            if ( data.success ) {
                showStatus( 'Saved successfully.', 'success' );
                isDirty = false;
            } else {
                showStatus( 'Error: ' + ( data.error || 'Unknown error' ), 'error' );
            }
        } )
        .catch( function( err ) { showStatus( 'Error: ' + err.message, 'error' ); } )
        .finally( function() {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Changes';
        } );
    }

    // ─── Config Editor (JSON) ─────────────────────────────────────

    function loadConfig( configKey ) {
        if ( isDirty && ! confirm( 'Unsaved changes will be lost. Continue?' ) ) return;

        document.querySelectorAll( '.apa-page-btn, .apa-config-btn' ).forEach( function( b ) {
            b.classList.remove( 'is-active' );
        } );
        document.querySelector( '[data-config="' + configKey + '"]' ).classList.add( 'is-active' );

        fetch( restBase + configKey, { headers: headers } )
            .then( function( r ) { return r.json(); } )
            .then( function( data ) {
                currentPage   = null;
                currentConfig = configKey;
                isDirty       = false;

                emptyState.style.display = 'none';
                editor.style.display = 'none';
                jsonEditor.style.display = '';

                jsonTitle.textContent = configKey + '.php';
                jsonTextarea.value = JSON.stringify( data, null, 2 );
            } )
            .catch( function( err ) { showStatus( 'Error: ' + err.message, 'error' ); } );
    }

    function saveConfig() {
        if ( ! currentConfig ) return;

        let data;
        try {
            data = JSON.parse( jsonTextarea.value );
        } catch( e ) {
            showStatus( 'Invalid JSON: ' + e.message, 'error' );
            return;
        }

        jsonSave.disabled = true;
        jsonSave.textContent = 'Saving...';

        fetch( restBase + currentConfig, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify( data ),
        } )
        .then( function( r ) { return r.json(); } )
        .then( function( result ) {
            if ( result.success ) {
                showStatus( 'Saved successfully.', 'success' );
                isDirty = false;
            } else {
                showStatus( 'Error: ' + ( result.error || 'Unknown error' ), 'error' );
            }
        } )
        .catch( function( err ) { showStatus( 'Error: ' + err.message, 'error' ); } )
        .finally( function() {
            jsonSave.disabled = false;
            jsonSave.textContent = 'Save';
        } );
    }

    // ─── Utilities ────────────────────────────────────────────────

    function showStatus( message, type ) {
        // Remove existing
        const existing = document.querySelector( '.apa-status' );
        if ( existing ) existing.remove();

        const el = document.createElement( 'div' );
        el.className = 'apa-status apa-status--' + type;
        el.textContent = message;

        const main = document.querySelector( '.apa-main' );
        main.insertBefore( el, main.firstChild );

        setTimeout( function() { el.remove(); }, 4000 );
    }

    function escHtml( str ) {
        const div = document.createElement( 'div' );
        div.textContent = str;
        return div.innerHTML;
    }

    // Boot
    init();

})();
