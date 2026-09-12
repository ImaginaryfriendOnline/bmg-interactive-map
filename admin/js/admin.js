/* BMG Interactive Map — Admin Leaflet editor */
( function () {
	'use strict';

	var meta          = window.bmgLocationMeta;
	var adminSettings = window.bmgAdminSettings || {};
	var shared        = window.BMGAdminMapEditor;
	// wp_localize_script serialises scalars as strings — coerce back to numbers.
	var ADMIN_MIN_ZOOM = ( adminSettings.minZoom != null ) ? Number( adminSettings.minZoom ) : -3;
	var ADMIN_MAX_ZOOM = ( adminSettings.maxZoom != null ) ? Number( adminSettings.maxZoom ) : 3;

	var mapSelect        = document.getElementById( 'bmg_map_id' );
	var xInput           = document.getElementById( 'bmg_loc_x' );
	var yInput           = document.getElementById( 'bmg_loc_y' );
	var colorInput       = document.getElementById( 'bmg_loc_color' );
	var namedColorSelect = document.getElementById( 'bmg_loc_named_color' );

	if ( ! mapSelect || typeof L === 'undefined' || ! shared ) return;

	var leafletMap = null; // active Leaflet instance
	var marker     = null; // active Leaflet marker
	var imgW       = 1;    // natural image width (px)
	var imgH       = 1;    // natural image height (px)

	// Sibling location overlays.
	var siblingMarkers     = [];
	var showSiblings       = true;
	var currentSiblingData = meta.siblingLocations || [];

	// Coordinate conversion — 2 decimal places, matching the previous
	// location-editor precision (areas use 4, see area-admin.js).
	var coordConverter = shared.makeCoordConverter( function () { return imgW; }, function () { return imgH; }, 2 );
	var toLeaflet       = coordConverter.toLeaflet;
	var fromLeaflet     = coordConverter.fromLeaflet;

	// ------------------------------------------------------------------
	// Build / rebuild the Leaflet editor for a given image URL
	// ------------------------------------------------------------------

	function initEditor( imageUrl, w, h ) {
		imgW = w || 1;
		imgH = h || 1;

		var wrap = document.getElementById( 'bmg-map-editor-wrap' );
		if ( ! wrap ) return;

		// Tear down any previous Leaflet instance.
		clearSiblingMarkers();
		if ( leafletMap ) {
			leafletMap.remove();
			leafletMap = null;
			marker     = null;
		}

		// Set the wrapper aspect ratio to match the image, then wait for
		// the browser to reflow before initialising Leaflet (same pattern
		// as the frontend renderer to avoid whitespace / mis-centred map).
		wrap.style.aspectRatio = imgW + ' / ' + imgH;

		wrap.innerHTML = '<div id="bmg-map-editor" class="bmg-admin-editor-canvas"></div>';

		// Hint lives as a sibling after the wrap so the wrap's max-height doesn't clip it.
		var hint = wrap.nextElementSibling;
		if ( ! hint || ! hint.classList.contains( 'bmg-map-editor-hint' ) ) {
			hint = document.createElement( 'p' );
			hint.className = 'description bmg-map-editor-hint';
			hint.innerHTML = 'Click the map to place the marker, or drag the marker to reposition it. ' +
				'You can also enter coordinates manually in the X&nbsp;% and Y&nbsp;% fields above.';
			wrap.parentNode.insertBefore( hint, wrap.nextSibling );
		}

		requestAnimationFrame( function () {
			var editor = shared.initSimpleCrsEditor( {
				containerId: 'bmg-map-editor',
				imageUrl   : imageUrl,
				imgW       : imgW,
				imgH       : imgH,
				minZoom    : ADMIN_MIN_ZOOM,
				maxZoom    : ADMIN_MAX_ZOOM,
				onClick    : function ( e ) {
					placeMarker( e.latlng );
					syncInputsFromLatLng( e.latlng );
				},
			} );
			leafletMap = editor.map;

			// If coordinates are already stored, place the marker immediately.
			var x = parseFloat( xInput.value );
			var y = parseFloat( yInput.value );
			if ( ! isNaN( x ) && ! isNaN( y ) ) {
				placeMarker( toLeaflet( x, y ) );
			}

			// Show sibling locations.
			renderSiblingLocations( currentSiblingData );
		} );
	}

	// ------------------------------------------------------------------
	// Marker management
	// ------------------------------------------------------------------

	function makeIcon( color ) {
		return shared.makeCircleDivIcon( {
			className: 'bmg-admin-map-vertex-icon',
			size     : 20,
			color    : color,
			shadow   : '0 2px 6px rgba(0,0,0,.45)',
			cursor   : 'move',
		} );
	}

	function makeSiblingIcon( color ) {
		return shared.makeCircleDivIcon( {
			className: 'bmg-admin-map-vertex-icon',
			size     : 14,
			color    : color,
			shadow   : '0 1px 4px rgba(0,0,0,.3)',
		} );
	}

	function renderSiblingLocations( locData ) {
		siblingMarkers.forEach( function ( m ) { m.remove(); } );
		siblingMarkers = [];
		if ( ! showSiblings || ! locData || ! leafletMap ) return;
		locData.forEach( function ( loc ) {
			var m = L.marker( toLeaflet( loc.x, loc.y ), {
				icon   : makeSiblingIcon( loc.color ),
				opacity: 0.5,
			} ).addTo( leafletMap );
			m.bindTooltip( shared.escHtml( loc.title ), { sticky: true } );
			siblingMarkers.push( m );
		} );
	}

	function clearSiblingMarkers() {
		siblingMarkers.forEach( function ( m ) { m.remove(); } );
		siblingMarkers = [];
	}

	function placeMarker( latLng ) {
		var color = colorInput ? colorInput.value : '#e74c3c';

		if ( marker ) {
			marker.setLatLng( latLng );
			marker.setIcon( makeIcon( color ) );
			shared.stopMarkerMousedownBubble( marker );
		} else {
			marker = L.marker( latLng, {
				icon     : makeIcon( color ),
				draggable: true,
			} ).addTo( leafletMap );

			marker.on( 'dragend', function ( e ) {
				syncInputsFromLatLng( e.target.getLatLng() );
			} );

			shared.stopMarkerMousedownBubble( marker );
		}
	}

	// ------------------------------------------------------------------
	// Sync inputs ↔ marker
	// ------------------------------------------------------------------

	function syncInputsFromLatLng( latLng ) {
		var coords   = fromLeaflet( latLng );
		xInput.value = coords.x;
		yInput.value = coords.y;
	}

	function syncMarkerFromInputs() {
		if ( ! leafletMap ) return;
		var x = parseFloat( xInput.value );
		var y = parseFloat( yInput.value );
		if ( isNaN( x ) || isNaN( y ) ) return;
		var ll = toLeaflet( x, y );
		if ( marker ) {
			marker.setLatLng( ll );
		} else {
			placeMarker( ll );
		}
	}

	function syncMarkerColor() {
		if ( marker ) {
			marker.setIcon( makeIcon( colorInput.value ) );
		}
	}

	// ------------------------------------------------------------------
	// Event listeners
	// ------------------------------------------------------------------

	// Map dropdown change → fetch image + dimensions then rebuild editor.
	mapSelect.addEventListener( 'change', function () {
		var mapId = this.value;
		var wrap  = document.getElementById( 'bmg-map-editor-wrap' );

		if ( ! mapId ) {
			clearSiblingMarkers();
			currentSiblingData = [];
			if ( leafletMap ) { leafletMap.remove(); leafletMap = null; marker = null; }
			if ( wrap ) {
				wrap.style.aspectRatio = '';
				wrap.innerHTML = '<p id="bmg-no-map-notice" class="description">Select a map above to enable the visual position editor.</p>';
			}
			return;
		}

		if ( wrap ) {
			wrap.style.aspectRatio = '';
			wrap.innerHTML = '<p class="description">Loading map image\u2026</p>';
		}

		shared.postForm( meta.ajaxUrl, {
			action             : 'bmg_get_map_image',
			nonce              : meta.nonce,
			map_id             : mapId,
			exclude_location_id: meta.postId || 0,
		} )
			.then( function ( json ) {
				if ( json.success && json.data.url ) {
					currentSiblingData = json.data.locations || [];
					initEditor( json.data.url, json.data.width, json.data.height );
				} else {
					if ( wrap ) wrap.innerHTML = '<p class="description" style="color:#c00;">This map has no featured image. Add one and try again.</p>';
				}
			} )
			.catch( function () {
				if ( wrap ) wrap.innerHTML = '<p class="description" style="color:#c00;">Could not load map image.</p>';
			} );
	} );

	xInput     && xInput.addEventListener( 'input', syncMarkerFromInputs );
	yInput     && yInput.addEventListener( 'input', syncMarkerFromInputs );
	colorInput && colorInput.addEventListener( 'input', function () {
		if ( namedColorSelect ) namedColorSelect.value = '';
		syncMarkerColor();
	} );
	namedColorSelect && namedColorSelect.addEventListener( 'change', function () {
		var opt = this.options[ this.selectedIndex ];
		if ( opt && opt.dataset.hex ) {
			colorInput.value = opt.dataset.hex;
			syncMarkerColor();
		}
	} );

	// ------------------------------------------------------------------
	// Sibling-toggle checkbox (injected after the colour input row)
	// ------------------------------------------------------------------

	( function injectSiblingToggle() {
		var previewBtn = document.getElementById( 'bmg-preview-toggle' );
		if ( ! previewBtn ) return;
		var label = document.createElement( 'label' );
		label.style.cssText = 'display:inline-flex;align-items:center;gap:4px;margin-left:8px;cursor:pointer;';
		var cb = document.createElement( 'input' );
		cb.type    = 'checkbox';
		cb.id      = 'bmg-loc-show-siblings';
		cb.checked = true;
		label.appendChild( cb );
		label.appendChild( document.createTextNode( 'Show other locations' ) );
		previewBtn.parentNode.appendChild( label );
		cb.addEventListener( 'change', function () {
			showSiblings = this.checked;
			renderSiblingLocations( currentSiblingData );
		} );
	} )();

	// ------------------------------------------------------------------
	// Popup preview toggle
	// ------------------------------------------------------------------

	var previewToggle  = document.getElementById( 'bmg-preview-toggle' );
	var previewWrap    = document.getElementById( 'bmg-popup-preview-wrap' );

	if ( previewToggle && previewWrap ) {
		previewToggle.addEventListener( 'click', function () {
			var visible = previewWrap.style.display !== 'none';
			previewWrap.style.display = visible ? 'none' : 'block';
			previewToggle.textContent = visible ? 'Show Popup Preview' : 'Hide Popup Preview';
		} );
	}

	// ------------------------------------------------------------------
	// Initial state: if an image URL was pre-loaded (editing existing
	// location that already has a map), initialise the editor right away.
	// ------------------------------------------------------------------

	if ( meta.imageUrl ) {
		initEditor( meta.imageUrl, meta.imageWidth, meta.imageHeight );
	}

} )();
