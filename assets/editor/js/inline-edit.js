/**
 * Anchor Page Assistant — Inline Edit Mode
 *
 * Enables direct text editing on the frontend.
 * - Blog/event posts: edits article content, title, excerpt
 * - Config pages: edits section props (heading, text, eyebrow, CTA labels)
 *
 * Activated via pencil toggle button. Cmd+Click selects, regular click edits.
 */
(function() {
    'use strict';

    var data = window.apaFrontend;
    if ( ! data ) return;

    var headers = {
        'Content-Type': 'application/json',
        'X-WP-Nonce': data.nonce,
    };

    var editMode = false;
    var activeEl = null;
    var originalContent = '';
    var dirty = false;
    var imageFrame = null;
    var pendingImageEl = null;

    // Where each section type stores its image(s) in section.props.
    // single:true        → props.image
    // array+field        → props[array][N][field]  (e.g., members[0].photo)
    // array+field:null   → props[array][N]         (e.g., gallery images[])
    var IMAGE_PROP_MAP = {
        hero:          { single: true },
        iso_split:     { single: true },
        split_content: { single: true },
        cta_band:      { single: true },
        team_grid:     { array: 'members', field: 'photo' },
        gallery_band:  { array: 'images',  field: null },
        card_grid:     { array: 'items',   field: 'image' },
        services_tabs: { array: 'tabs',    field: 'image' },
    };

    // ─── Build Edit Toggle ────────────────────────────────────────

    function init() {
        var btn = document.createElement( 'button' );
        btn.className = 'apa-edit-toggle';
        btn.innerHTML = '<span class="dashicons dashicons-edit-page"></span>';
        btn.title = 'Toggle inline edit mode';
        btn.addEventListener( 'click', toggleEditMode );
        document.body.appendChild( btn );

        // Save bar (hidden by default)
        var bar = document.createElement( 'div' );
        bar.className = 'apa-edit-bar';
        bar.id = 'apa-edit-bar';
        bar.innerHTML =
            '<span class="apa-edit-bar__label">Editing mode</span>' +
            '<div class="apa-edit-bar__actions">' +
                '<button id="apa-edit-cancel" class="apa-edit-bar__btn">Cancel</button>' +
                '<button id="apa-edit-save" class="apa-edit-bar__btn apa-edit-bar__btn--primary">Save Changes</button>' +
            '</div>';
        document.body.appendChild( bar );

        document.getElementById( 'apa-edit-cancel' ).addEventListener( 'click', cancelEdits );
        document.getElementById( 'apa-edit-save' ).addEventListener( 'click', saveEdits );

        // Keyboard shortcut: Escape to cancel, Cmd+S to save
        document.addEventListener( 'keydown', function( e ) {
            if ( ! editMode ) return;
            if ( e.key === 'Escape' ) cancelEdits();
            if ( ( e.metaKey || e.ctrlKey ) && e.key === 's' ) {
                e.preventDefault();
                saveEdits();
            }
        } );
    }

    // ─── Toggle Edit Mode ─────────────────────────────────────────

    function toggleEditMode() {
        editMode = ! editMode;
        document.body.classList.toggle( 'apa-edit-mode', editMode );
        document.getElementById( 'apa-edit-bar' ).classList.toggle( 'is-visible', editMode );

        if ( editMode ) {
            enableEditables();
        } else {
            disableEditables();
        }
    }

    // ─── Find and mark editable elements ──────────────────────────

    function enableEditables() {
        var editables = getEditableElements();
        editables.forEach( function( el ) {
            el.setAttribute( 'data-apa-editable', 'true' );
            el.addEventListener( 'click', onEditableClick );
            el.addEventListener( 'input', onEditableInput );
            el.addEventListener( 'blur', onEditableBlur );
        } );
    }

    function disableEditables() {
        document.querySelectorAll( '[data-apa-editable]' ).forEach( function( el ) {
            el.removeAttribute( 'contenteditable' );
            el.removeAttribute( 'data-apa-editable' );
            el.classList.remove( 'apa-editable', 'apa-editable--active' );
            el.removeEventListener( 'click', onEditableClick );
            el.removeEventListener( 'input', onEditableInput );
            el.removeEventListener( 'blur', onEditableBlur );
        } );
        activeEl = null;
    }

    function getEditableElements() {
        var els = [];

        if ( data.postContext ) {
            // Blog/event post: article content, title
            var article = document.querySelector( '.anchor-article' );
            if ( article ) {
                els.push( article );
                article.dataset.apaField = 'content';
                article.dataset.apaTarget = 'post';
            }

            // Hero heading (post title)
            var heroTitle = document.querySelector( '.anchor-hero__heading' );
            if ( heroTitle ) {
                els.push( heroTitle );
                heroTitle.dataset.apaField = 'title';
                heroTitle.dataset.apaTarget = 'post';
            }
        }

        if ( data.pageSlug ) {
            // Config-driven pages: find text elements inside sections
            document.querySelectorAll( '[data-section-type]' ).forEach( function( section, sectionIndex ) {
                // Headings
                section.querySelectorAll( '.anchor-heading-group__title, .anchor-hero__heading, .anchor-cta-card__heading' ).forEach( function( el ) {
                    if ( ! el.closest( '.anchor-article' ) ) {
                        els.push( el );
                        el.dataset.apaField = 'heading';
                        el.dataset.apaTarget = 'section';
                        el.dataset.apaSectionIndex = sectionIndex;
                    }
                } );

                // Eyebrow badges
                section.querySelectorAll( '.anchor-badge' ).forEach( function( el ) {
                    if ( el.closest( '.anchor-heading-group' ) || el.closest( '.anchor-hero' ) ) {
                        els.push( el );
                        el.dataset.apaField = 'eyebrow';
                        el.dataset.apaTarget = 'section';
                        el.dataset.apaSectionIndex = sectionIndex;
                    }
                } );

                // Body text / descriptions
                section.querySelectorAll( '.anchor-text-muted, .anchor-heading-group__text, .anchor-hero__text, .anchor-hero__excerpt, .anchor-split__paragraph, .anchor-cta-card__text' ).forEach( function( el ) {
                    if ( ! el.closest( '.anchor-article' ) ) {
                        els.push( el );
                        el.dataset.apaField = 'text';
                        el.dataset.apaTarget = 'section';
                        el.dataset.apaSectionIndex = sectionIndex;
                    }
                } );

                // Button/CTA labels
                section.querySelectorAll( '.anchor-btn span, .anchor-link-arrow' ).forEach( function( el ) {
                    els.push( el );
                    el.dataset.apaField = 'cta_label';
                    el.dataset.apaTarget = 'section';
                    el.dataset.apaSectionIndex = sectionIndex;
                } );

                // Card titles
                section.querySelectorAll( '.anchor-icon-list__item-heading, .anchor-team-card__name, .anchor-post-card__title' ).forEach( function( el ) {
                    els.push( el );
                    el.dataset.apaField = 'item_title';
                    el.dataset.apaTarget = 'section';
                    el.dataset.apaSectionIndex = sectionIndex;
                } );

                // Images (section backgrounds, feature images, gallery, team, etc.)
                var type = section.dataset.sectionType;
                var map  = IMAGE_PROP_MAP[ type ];
                if ( map ) {
                    var imgs = collectSectionImages( section );
                    if ( map.single ) {
                        if ( imgs[0] ) markImageEditable( imgs[0], sectionIndex, 'image' );
                    } else {
                        imgs.forEach( function( img, itemIdx ) {
                            var path = map.field
                                ? map.array + '[' + itemIdx + '].' + map.field
                                : map.array + '[' + itemIdx + ']';
                            markImageEditable( img, sectionIndex, path );
                        } );
                    }
                    imgs.forEach( function( img ) { els.push( img ); } );
                }
            } );
        }

        return els;
    }

    function collectSectionImages( section ) {
        // All <img> inside the section, minus obviously-decorative ones.
        return Array.prototype.filter.call(
            section.querySelectorAll( 'img' ),
            function( img ) {
                if ( img.closest( '.anchor-article' ) ) return false;
                if ( img.closest( '.anchor-btn' ) ) return false;
                if ( img.closest( '.anchor-floating-badge' ) ) return false;
                return true;
            }
        );
    }

    function markImageEditable( img, sectionIndex, imagePath ) {
        img.dataset.apaField = 'image';
        img.dataset.apaTarget = 'section';
        img.dataset.apaSectionIndex = sectionIndex;
        img.dataset.apaImagePath = imagePath;
    }

    // Walk a dotted/bracketed path (e.g., "members[0].photo") and set the value.
    // Creates intermediate objects/arrays if missing.
    function setByPath( root, path, value ) {
        var tokens = path.split( /\.|\[|\]/ ).filter( function( t ) { return t !== ''; } );
        var obj = root;
        for ( var i = 0; i < tokens.length - 1; i++ ) {
            var k = /^\d+$/.test( tokens[ i ] ) ? parseInt( tokens[ i ], 10 ) : tokens[ i ];
            if ( obj[ k ] === undefined || obj[ k ] === null ) {
                var next = tokens[ i + 1 ];
                obj[ k ] = /^\d+$/.test( next ) ? [] : {};
            }
            obj = obj[ k ];
        }
        var last = tokens[ tokens.length - 1 ];
        obj[ /^\d+$/.test( last ) ? parseInt( last, 10 ) : last ] = value;
    }

    // ─── Edit Events ──────────────────────────────────────────────

    function onEditableClick( e ) {
        if ( ! editMode ) return;
        e.preventDefault();
        e.stopPropagation();

        var el = e.currentTarget;

        // Image target: open WP media frame instead of contenteditable.
        if ( el.dataset.apaField === 'image' ) {
            openImageFrame( el );
            return;
        }

        if ( el.getAttribute( 'contenteditable' ) === 'true' ) return;

        // Deactivate previous
        if ( activeEl && activeEl !== el ) {
            activeEl.classList.remove( 'apa-editable--active' );
        }

        // Store original
        originalContent = el.innerHTML;
        el.setAttribute( 'contenteditable', 'true' );
        el.classList.add( 'apa-editable--active' );
        el.focus();
        activeEl = el;
    }

    function openImageFrame( el ) {
        if ( ! window.wp || ! window.wp.media ) return;
        pendingImageEl = el;

        if ( ! imageFrame ) {
            imageFrame = window.wp.media( {
                title: 'Replace image',
                button: { text: 'Use this image' },
                library: { type: 'image' },
                multiple: false,
            } );

            imageFrame.on( 'select', function() {
                if ( ! pendingImageEl ) return;
                var att = imageFrame.state().get( 'selection' ).first().toJSON();
                var target = pendingImageEl;
                pendingImageEl = null;

                target.src = att.url;
                target.removeAttribute( 'srcset' );
                target.removeAttribute( 'sizes' );
                target.dataset.apaNewImageId = att.id;
                target.dataset.apaNewImageUrl = att.url;

                dirty = true;
                document.getElementById( 'apa-edit-bar' )
                    .querySelector( '.apa-edit-bar__label' ).textContent = 'Unsaved changes';
            } );
        }

        imageFrame.open();
    }

    function onEditableInput() {
        dirty = true;
        document.getElementById( 'apa-edit-bar' ).querySelector( '.apa-edit-bar__label' ).textContent = 'Unsaved changes';
    }

    function onEditableBlur( e ) {
        var el = e.currentTarget;
        el.removeAttribute( 'contenteditable' );
        el.classList.remove( 'apa-editable--active' );
    }

    // ─── Save ─────────────────────────────────────────────────────

    function saveEdits() {
        if ( ! dirty ) {
            cancelEdits();
            return;
        }

        var bar = document.getElementById( 'apa-edit-bar' );
        bar.querySelector( '.apa-edit-bar__label' ).textContent = 'Saving...';

        // Collect all changes
        if ( data.postContext ) {
            savePostEdits();
        }
        if ( data.pageSlug ) {
            saveConfigEdits();
        }
    }

    function savePostEdits() {
        var update = { post_action: 'update', post_id: data.postContext.id };

        var article = document.querySelector( '[data-apa-field="content"][data-apa-target="post"]' );
        if ( article ) {
            update.content = article.innerHTML;
        }

        var title = document.querySelector( '[data-apa-field="title"][data-apa-target="post"]' );
        if ( title ) {
            update.title = title.textContent.trim();
        }

        fetch( data.restBase + 'ai/apply', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify( { config: update, page_slug: '', config_key: '' } ),
        } )
        .then( function( r ) { return r.json(); } )
        .then( function( result ) {
            if ( result.success ) {
                showSaveResult( 'Saved!' );
            } else {
                showSaveResult( 'Error: ' + ( result.error || 'Unknown' ) );
            }
        } )
        .catch( function( err ) { showSaveResult( 'Error: ' + err.message ); } );
    }

    function saveConfigEdits() {
        // Load current config, apply edits, save
        fetch( data.restBase + 'pages/' + data.pageSlug, { headers: { 'X-WP-Nonce': data.nonce } } )
            .then( function( r ) { return r.json(); } )
            .then( function( config ) {
                if ( ! config.sections ) return;

                // Walk edited elements and map back to config
                document.querySelectorAll( '[data-apa-target="section"]' ).forEach( function( el ) {
                    var idx   = parseInt( el.dataset.apaSectionIndex, 10 );
                    var field = el.dataset.apaField;
                    var section = config.sections[ idx ];

                    if ( ! section || ! section.props ) return;

                    var newText = el.textContent.trim();

                    switch ( field ) {
                        case 'heading':
                            // Heading might have accent span — get text without drama span
                            var drama = el.querySelector( '.anchor-text-drama' );
                            if ( drama ) {
                                var accentText = drama.textContent.trim();
                                var fullText = el.textContent.trim();
                                var headingText = fullText.replace( accentText, '' ).trim();
                                section.props.heading = headingText;
                                section.props.heading_accent = accentText;
                            } else {
                                section.props.heading = newText;
                            }
                            break;
                        case 'eyebrow':
                            section.props.eyebrow = newText;
                            break;
                        case 'text':
                            section.props.text = newText;
                            break;
                        case 'image':
                            if ( el.dataset.apaNewImageId ) {
                                setByPath(
                                    section.props,
                                    el.dataset.apaImagePath,
                                    parseInt( el.dataset.apaNewImageId, 10 )
                                );
                            }
                            break;
                    }
                } );

                return fetch( data.restBase + 'pages/' + data.pageSlug, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify( config ),
                } );
            } )
            .then( function( r ) { return r ? r.json() : null; } )
            .then( function( result ) {
                if ( result && result.success ) {
                    showSaveResult( 'Saved!' );
                } else if ( result ) {
                    showSaveResult( 'Error: ' + ( result.error || 'Unknown' ) );
                }
            } )
            .catch( function( err ) { showSaveResult( 'Error: ' + err.message ); } );
    }

    function showSaveResult( msg ) {
        var bar = document.getElementById( 'apa-edit-bar' );
        bar.querySelector( '.apa-edit-bar__label' ).textContent = msg;
        dirty = false;

        // Notify parent frame (for split-screen live editor)
        if ( window.parent !== window ) {
            window.parent.postMessage( { type: 'apa-inline-saved' }, '*' );
        }

        setTimeout( function() {
            if ( ! dirty ) {
                bar.querySelector( '.apa-edit-bar__label' ).textContent = 'Editing mode';
            }
        }, 2000 );
    }

    function cancelEdits() {
        if ( dirty && ! confirm( 'Discard unsaved changes?' ) ) return;
        window.location.reload();
    }

    // ─── Boot ─────────────────────────────────────────────────────
    init();
})();
