/* global bmgIconPickerConfig */
( function () {
	'use strict';

	if ( typeof bmgIconPickerConfig === 'undefined' ) {
		return;
	}

	var tabs        = bmgIconPickerConfig.tabs   || [];
	var ajaxUrl     = bmgIconPickerConfig.ajax   || '';
	var nonce       = bmgIconPickerConfig.nonce  || '';
	var iconCache   = {};  // library name → array of icon name strings
	var currentTab  = null;
	var modal       = null;
	var searchInput = null;
	var gridEl      = null;
	var hiddenField = null;
	var previewEl   = null;
	var selectedVal = null;

	// -------------------------------------------------------------------------
	// Build the modal DOM once
	// -------------------------------------------------------------------------

	function buildModal() {
		modal = document.createElement( 'div' );
		modal.className = 'bmg-icon-modal';
		modal.style.display = 'none';

		var dialog = document.createElement( 'div' );
		dialog.className = 'bmg-icon-modal__dialog';

		// Header: search + close
		var header = document.createElement( 'div' );
		header.className = 'bmg-icon-modal__header';

		searchInput = document.createElement( 'input' );
		searchInput.type = 'search';
		searchInput.placeholder = 'Search icons…';
		searchInput.className = 'bmg-icon-modal__search';
		searchInput.addEventListener( 'input', onSearch );

		var closeBtn = document.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.className = 'bmg-icon-modal__close';
		closeBtn.textContent = '✕';
		closeBtn.addEventListener( 'click', closeModal );

		header.appendChild( searchInput );
		header.appendChild( closeBtn );

		// Tab strip
		var tabStrip = document.createElement( 'div' );
		tabStrip.className = 'bmg-icon-modal__tabs';

		tabs.forEach( function ( tab ) {
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'bmg-icon-modal__tab';
			btn.textContent = tab.label || tab.name;
			btn.dataset.library = tab.name;
			btn.addEventListener( 'click', function () {
				activateTab( tab, btn );
			} );
			tabStrip.appendChild( btn );
		} );

		// Body: icon grid
		var body = document.createElement( 'div' );
		body.className = 'bmg-icon-modal__body';

		gridEl = document.createElement( 'div' );
		gridEl.className = 'bmg-icon-modal__grid';
		body.appendChild( gridEl );

		dialog.appendChild( header );
		dialog.appendChild( tabStrip );
		dialog.appendChild( body );
		modal.appendChild( dialog );
		document.body.appendChild( modal );

		// Close on backdrop click
		modal.addEventListener( 'click', function ( e ) {
			if ( e.target === modal ) {
				closeModal();
			}
		} );

		// Close on Escape
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && modal.style.display !== 'none' ) {
				closeModal();
			}
		} );
	}

	// -------------------------------------------------------------------------
	// Open / close
	// -------------------------------------------------------------------------

	function openModal( hidden, preview ) {
		hiddenField = hidden;
		previewEl   = preview;

		try {
			selectedVal = JSON.parse( hidden.value || '{}' );
		} catch ( e ) {
			selectedVal = {};
		}

		searchInput.value = '';
		modal.style.display = 'flex';

		// Activate first tab (or the tab matching the current selection)
		var firstTab  = tabs[ 0 ];
		var activeTab = firstTab;
		if ( selectedVal && selectedVal.library ) {
			var match = tabs.filter( function ( t ) { return t.name === selectedVal.library; } );
			if ( match.length ) {
				activeTab = match[ 0 ];
			}
		}

		if ( activeTab ) {
			var tabBtn = modal.querySelector( '.bmg-icon-modal__tab[data-library="' + activeTab.name + '"]' );
			activateTab( activeTab, tabBtn );
		}

		searchInput.focus();
	}

	function closeModal() {
		modal.style.display = 'none';
		hiddenField = null;
		previewEl   = null;
		selectedVal = null;
	}

	// -------------------------------------------------------------------------
	// Tab activation + icon loading
	// -------------------------------------------------------------------------

	function activateTab( tab, btn ) {
		currentTab = tab;

		// Update active tab button
		modal.querySelectorAll( '.bmg-icon-modal__tab' ).forEach( function ( b ) {
			b.classList.remove( 'is-active' );
		} );
		if ( btn ) {
			btn.classList.add( 'is-active' );
		}

		searchInput.value = '';

		if ( iconCache[ tab.name ] ) {
			renderGrid( iconCache[ tab.name ], tab );
			return;
		}

		// Load via AJAX (server reads Elementor's icon pack file)
		gridEl.innerHTML = '<div class="bmg-icon-modal__loading">Loading…</div>';

		var formData = new FormData();
		formData.append( 'action',  'bmg_get_icon_list' );
		formData.append( 'nonce',   nonce );
		formData.append( 'library', tab.name );

		fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( res.success && Array.isArray( res.data.icons ) ) {
					iconCache[ tab.name ] = res.data.icons;
					// Patch tab with displayPrefix/prefix returned from server if missing
					if ( ! tab.displayPrefix && res.data.displayPrefix ) {
						tab.displayPrefix = res.data.displayPrefix;
					}
					if ( ! tab.prefix && res.data.prefix ) {
						tab.prefix = res.data.prefix;
					}
					if ( currentTab && currentTab.name === tab.name ) {
						renderGrid( iconCache[ tab.name ], tab );
					}
				} else {
					gridEl.innerHTML = '<div class="bmg-icon-modal__no-results">Could not load icons for this library.</div>';
				}
			} )
			.catch( function () {
				gridEl.innerHTML = '<div class="bmg-icon-modal__no-results">Failed to load icons.</div>';
			} );
	}

	// -------------------------------------------------------------------------
	// Render icon grid
	// -------------------------------------------------------------------------

	function buildIconClass( tab, iconName ) {
		var dp     = tab.displayPrefix || '';
		var prefix = ( tab.prefix || '' ).replace( /-$/, '' );
		if ( dp && prefix ) {
			return dp + ' ' + prefix + '-' + iconName;
		}
		if ( dp ) {
			return dp + ' ' + dp + '-' + iconName;
		}
		return iconName;
	}

	function renderGrid( icons, tab ) {
		var query   = searchInput ? searchInput.value.toLowerCase().trim() : '';
		var filtered = query ? icons.filter( function ( n ) { return n.indexOf( query ) !== -1; } ) : icons;

		if ( ! filtered.length ) {
			gridEl.innerHTML = '<div class="bmg-icon-modal__no-results">No icons found.</div>';
			return;
		}

		gridEl.innerHTML = '';
		var frag = document.createDocumentFragment();

		filtered.forEach( function ( iconName ) {
			var fullClass = buildIconClass( tab, iconName );

			var cell = document.createElement( 'div' );
			cell.className = 'bmg-icon-modal__cell';
			cell.title = iconName;

			if ( selectedVal && selectedVal.value === fullClass ) {
				cell.classList.add( 'is-selected' );
			}

			var iEl = document.createElement( 'i' );
			iEl.className = fullClass;
			iEl.setAttribute( 'aria-hidden', 'true' );
			cell.appendChild( iEl );

			cell.addEventListener( 'click', function () {
				selectIcon( fullClass, tab.name );
			} );

			frag.appendChild( cell );
		} );

		gridEl.appendChild( frag );
	}

	// -------------------------------------------------------------------------
	// Search
	// -------------------------------------------------------------------------

	function onSearch() {
		if ( currentTab && iconCache[ currentTab.name ] ) {
			renderGrid( iconCache[ currentTab.name ], currentTab );
		}
	}

	// -------------------------------------------------------------------------
	// Select icon
	// -------------------------------------------------------------------------

	function selectIcon( fullClass, library ) {
		var value = JSON.stringify( { value: fullClass, library: library } );
		if ( hiddenField ) {
			hiddenField.value = value;
		}

		if ( previewEl ) {
			previewEl.innerHTML = '<i class="' + escAttr( fullClass ) + '" aria-hidden="true"></i>';
			previewEl.style.display = '';
		}

		selectedVal = { value: fullClass, library: library };
		closeModal();
	}

	function escAttr( s ) {
		return String( s )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	// -------------------------------------------------------------------------
	// Wire buttons on page
	// -------------------------------------------------------------------------

	function init() {
		if ( ! tabs.length ) {
			return;
		}

		buildModal();

		var wrap   = document.querySelector( '.bmg-icon-picker-wrap' );
		if ( ! wrap ) {
			return;
		}

		var chooseBtn = wrap.querySelector( '.bmg-choose-icon-btn' );
		var clearBtn  = wrap.querySelector( '.bmg-clear-icon-btn' );
		var hidden    = wrap.querySelector( '#bmg_loc_icon_json' );
		var preview   = wrap.querySelector( '.bmg-icon-preview' );

		if ( chooseBtn && hidden ) {
			chooseBtn.addEventListener( 'click', function () {
				openModal( hidden, preview );
			} );
		}

		if ( clearBtn && hidden ) {
			clearBtn.addEventListener( 'click', function () {
				hidden.value = '';
				if ( preview ) {
					preview.innerHTML = '';
				}
			} );
		}
	}

	document.addEventListener( 'DOMContentLoaded', init );
}() );
