/**
 * Anchor Page Assistant — Code Editor Panel
 *
 * Loads Monaco on demand and opens a slide-over panel to edit the
 * current page's page-content/{slug}.php file directly.
 *
 * Depends on window.apaFrontend (from wp_localize_script in
 * class-frontend-chat.php) and calls back into window.APA.codeEditor
 * so the main chat widget can wire its "Edit code" button to `open()`.
 */
(function () {
	'use strict';

	var data = window.apaFrontend;
	if ( ! data || ! data.restBase ) return;

	var MONACO_VS = 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs';

	var state = {
		monacoLoaded: false,
		monacoLoading: false,
		editor: null,
		container: null,
		originalContents: '',
		currentSlug: '',
		onSaveCallback: null,
		currentTab: 'page',
		currentCssFile: '',
		loadSeq: 0,
	};

	/**
	 * Lazily load Monaco via its AMD loader. Resolves once the
	 * `monaco` global is ready.
	 */
	function loadMonaco() {
		if ( state.monacoLoaded ) return Promise.resolve();
		if ( state.monacoLoading ) {
			return new Promise( function ( resolve ) {
				var iv = setInterval( function () {
					if ( state.monacoLoaded ) {
						clearInterval( iv );
						resolve();
					}
				}, 50 );
			} );
		}
		state.monacoLoading = true;

		return new Promise( function ( resolve, reject ) {
			var loader = document.createElement( 'script' );
			loader.src = MONACO_VS + '/loader.min.js';
			loader.onload = function () {
				/* global require */
				require.config( { paths: { vs: MONACO_VS } } );
				require( [ 'vs/editor/editor.main' ], function () {
					state.monacoLoaded = true;
					state.monacoLoading = false;
					resolve();
				} );
			};
			loader.onerror = function () {
				state.monacoLoading = false;
				reject( new Error( 'Failed to load Monaco loader.' ) );
			};
			document.head.appendChild( loader );
		} );
	}

	function buildPanel() {
		if ( state.container ) return state.container;

		var root = document.createElement( 'div' );
		root.className = 'apa-code-panel';
		root.id = 'apa-code-panel';
		root.innerHTML =
			'<div class="apa-code-header">' +
				'<div class="apa-code-title">' +
					'<strong>Code Editor</strong>' +
					'<span class="apa-code-path"></span>' +
				'</div>' +
				'<div class="apa-code-actions">' +
					'<button class="apa-code-btn apa-code-btn--ghost" id="apa-code-revert">Revert</button>' +
					'<button class="apa-code-btn" id="apa-code-save">Save</button>' +
					'<button class="apa-code-btn apa-code-btn--close" id="apa-code-close" title="Close">&times;</button>' +
				'</div>' +
			'</div>' +
			'<div class="apa-code-tabs" id="apa-code-tabs"></div>' +
			'<div class="apa-code-status" id="apa-code-status" hidden></div>' +
			'<div class="apa-code-host" id="apa-code-host"></div>';

		document.body.appendChild( root );

		root.querySelector( '#apa-code-close' ).addEventListener( 'click', close );
		root.querySelector( '#apa-code-revert' ).addEventListener( 'click', revert );
		root.querySelector( '#apa-code-save' ).addEventListener( 'click', save );

		state.container = root;
		return root;
	}

	function setStatus( text, kind ) {
		var el = state.container && state.container.querySelector( '#apa-code-status' );
		if ( ! el ) return;
		if ( ! text ) {
			el.hidden = true;
			el.textContent = '';
			el.className = 'apa-code-status';
			return;
		}
		el.hidden = false;
		el.textContent = text;
		el.className = 'apa-code-status apa-code-status--' + ( kind || 'info' );
	}

	function renderTabs() {
		var tabBar = state.container && state.container.querySelector( '#apa-code-tabs' );
		if ( ! tabBar ) return;
		tabBar.innerHTML = '';

		var tabs = [
			{ id: 'page',     label: 'Page' },
			{ id: 'page-css', label: state.currentSlug + '.css' },
			{ id: 'css',      label: state.currentCssFile },
		];

		tabs.forEach( function( tab ) {
			var btn = document.createElement( 'button' );
			btn.className  = 'apa-code-tab' + ( tab.id === state.currentTab ? ' is-active' : '' );
			btn.dataset.tab = tab.id;
			btn.textContent = tab.label;
			btn.addEventListener( 'click', function() {
				if ( tab.id === state.currentTab ) return;
				state.currentTab = tab.id;
				renderTabs();
				loadTab( tab.id );
			} );
			tabBar.appendChild( btn );
		} );
	}

	function loadTab( tabId ) {
		var seq = ++state.loadSeq;
		setStatus( 'Loading…', 'info' );
		var pathEl = state.container && state.container.querySelector( '.apa-code-path' );

		var fetchPromise;
		if ( 'page' === tabId ) {
			if ( pathEl ) pathEl.textContent = 'page-content/' + state.currentSlug + '.php';
			fetchPromise = fetchFile( state.currentSlug );
		} else if ( 'page-css' === tabId ) {
			if ( pathEl ) pathEl.textContent = 'assets/css/pages/' + state.currentSlug + '.css';
			fetchPromise = fetchPageCssFile( state.currentSlug );
		} else {
			if ( pathEl ) pathEl.textContent = 'assets/css/' + state.currentCssFile;
			fetchPromise = fetchCssFile( state.currentCssFile );
		}

		Promise.all( [ loadMonaco(), fetchPromise ] )
			.then( function( results ) {
				if ( seq !== state.loadSeq ) return;
				var file     = results[ 1 ];
				var contents = file.contents || '';
				var lang     = ( 'page' === tabId ) ? 'php' : 'css';
				state.originalContents = contents;
				mountEditor( contents, lang );
				setStatus( file.exists ? '' : 'File does not exist yet — save to create it.', file.exists ? '' : 'warn' );
			} )
			.catch( function( err ) {
				if ( seq !== state.loadSeq ) return;
				state.originalContents = '';
				setStatus( 'Error: ' + err.message, 'error' );
			} );
	}

	function open( slug, onSave ) {
		state.currentSlug        = slug;
		state.onSaveCallback     = onSave || null;
		state.currentTab = 'page';

		buildPanel();
		renderTabs();
		state.container.classList.add( 'is-open' );
		loadTab( state.currentTab );
	}

	function close() {
		if ( state.container ) state.container.classList.remove( 'is-open' );
	}

	function fetchFile( slug ) {
		return fetch( data.restBase + 'files/page/' + encodeURIComponent( slug ), {
			headers: { 'X-WP-Nonce': data.nonce },
		} ).then( function ( r ) {
			return r.json().then( function ( body ) {
				if ( ! r.ok ) throw new Error( body.error || ( 'HTTP ' + r.status ) );
				return body;
			} );
		} );
	}

	function fetchCssFile( filename ) {
		return fetch( data.restBase + 'files/css/' + encodeURIComponent( filename ), {
			headers: { 'X-WP-Nonce': data.nonce },
		} ).then( function( r ) {
			return r.json().then( function( body ) {
				if ( ! r.ok ) throw new Error( body.error || ( 'HTTP ' + r.status ) );
				return body;
			} );
		} );
	}

	function fetchPageCssFile( slug ) {
		return fetch( data.restBase + 'files/page-css/' + encodeURIComponent( slug ), {
			headers: { 'X-WP-Nonce': data.nonce },
		} ).then( function( r ) {
			return r.json().then( function( body ) {
				if ( ! r.ok ) throw new Error( body.error || ( 'HTTP ' + r.status ) );
				return body;
			} );
		} );
	}

	function mountEditor( contents, lang ) {
		var host = state.container.querySelector( '#apa-code-host' );
		host.innerHTML = '';
		if ( state.editor ) {
			state.editor.dispose();
			state.editor = null;
		}
		state.editor = window.monaco.editor.create( host, {
			value: contents,
			language: lang || 'php',
			theme: 'vs-dark',
			automaticLayout: true,
			fontSize: 13,
			minimap: { enabled: false },
			scrollBeyondLastLine: false,
			wordWrap: 'on',
			tabSize: 4,
		} );

		state.editor.addCommand(
			window.monaco.KeyMod.CtrlCmd | window.monaco.KeyCode.KeyS,
			save
		);
	}

	function revert() {
		if ( state.editor ) {
			state.editor.setValue( state.originalContents );
			setStatus( 'Reverted to last loaded contents.', 'info' );
		}
	}

	function save() {
		if ( ! state.editor ) return;

		var contents = state.editor.getValue();
		var saveBtn  = state.container.querySelector( '#apa-code-save' );
		saveBtn.disabled    = true;
		saveBtn.textContent = 'Saving…';
		setStatus( 'Writing file…', 'info' );

		var url, payload;
		if ( 'page' === state.currentTab ) {
			url     = data.restBase + 'files/page/' + encodeURIComponent( state.currentSlug );
			payload = { contents: contents };
		} else if ( 'page-css' === state.currentTab ) {
			url     = data.restBase + 'files/page-css/' + encodeURIComponent( state.currentSlug );
			payload = { contents: contents };
		} else {
			url     = data.restBase + 'files/css/' + encodeURIComponent( state.currentCssFile );
			payload = { contents: contents };
		}

		fetch( url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': data.nonce },
			body: JSON.stringify( payload ),
		} )
		.then( function( r ) {
			return r.json().then( function( body ) { return { ok: r.ok, body: body }; } );
		} )
		.then( function( res ) {
			saveBtn.disabled    = false;
			saveBtn.textContent = 'Save';
			if ( ! res.ok || ! res.body.success ) {
				setStatus( 'Save failed: ' + ( ( res.body && res.body.error ) || 'HTTP error' ), 'error' );
				return;
			}
			state.originalContents = contents;
			setStatus( 'Saved.', 'ok' );
			if ( typeof state.onSaveCallback === 'function' ) {
				try { state.onSaveCallback( { slug: state.currentSlug, tab: state.currentTab, path: res.body.path } ); } catch ( e ) { /* noop */ }
			}
		} )
		.catch( function( err ) {
			saveBtn.disabled    = false;
			saveBtn.textContent = 'Save';
			setStatus( 'Save failed: ' + err.message, 'error' );
		} );
	}

	// Expose API for the main chat widget to drive.
	window.APA = window.APA || {};
	window.APA.codeEditor = {
		open:  open,
		close: close,
	};
})();
