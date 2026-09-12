/* BMG Interactive Map — Admin polygon editor */
( function () {
	'use strict';

	var meta          = window.bmgAreaMeta;
	var adminSettings = window.bmgAdminSettings || {};
	var shared        = window.BMGAdminMapEditor;
	var ADMIN_MIN_ZOOM = ( adminSettings.minZoom != null ) ? Number( adminSettings.minZoom ) : -3;
	var ADMIN_MAX_ZOOM = ( adminSettings.maxZoom != null ) ? Number( adminSettings.maxZoom ) : 3;

	var mapSelect            = document.getElementById( 'bmg_area_map_id' );
	var colorInput           = document.getElementById( 'bmg_area_color' );
	var fillColorInput       = document.getElementById( 'bmg_area_fill_color' );
	var opacityInput         = document.getElementById( 'bmg_area_fill_opacity' );
	var namedColorSelect     = document.getElementById( 'bmg_area_named_color' );
	var namedFillColorSelect = document.getElementById( 'bmg_area_named_fill_color' );
	var pointsTextarea = document.getElementById( 'bmg_area_points' );
	var vertexCountEl  = document.getElementById( 'bmg-area-vertex-count' );
	var undoBtn        = document.getElementById( 'bmg-area-undo' );
	var clearBtn       = document.getElementById( 'bmg-area-clear' );

	if ( ! mapSelect || typeof L === 'undefined' || ! shared ) return;

	var leafletMap = null;
	var vertices   = []; // [{latLng, marker}, …]
	var polyline   = null;
	var polygon    = null;
	var imgW       = 1;
	var imgH       = 1;

	// Selected vertex for insert-after behaviour (null = append mode).
	var selectedEntry = null;

	// Sibling area overlays.
	var siblingPolygons    = [];
	var showSiblings       = true;
	var currentSiblingData = meta.siblingAreas || [];

	// Coordinate conversion — 4 decimal places, matching the previous
	// area-editor precision (locations use 2, see admin.js).
	var coordConverter = shared.makeCoordConverter( function () { return imgW; }, function () { return imgH; }, 4 );
	var toLeaflet       = coordConverter.toLeaflet;
	var fromLeaflet     = coordConverter.fromLeaflet;

	function getColor()       { return colorInput     ? colorInput.value                    : '#3388ff'; }
	function getFillColor()   { return fillColorInput ? fillColorInput.value                : '#3388ff'; }
	function getFillOpacity() { return opacityInput   ? parseFloat( opacityInput.value ) || 0 : 0.2; }

	// ------------------------------------------------------------------
	// Polygon / polyline management
	// ------------------------------------------------------------------

	function refreshPolygon() {
		if ( polyline ) { polyline.remove(); polyline = null; }
		if ( polygon  ) { polygon.remove();  polygon  = null; }

		if ( vertices.length < 2 ) return;

		var latlngs = vertices.map( function ( v ) { return v.latLng; } );
		var color   = getColor();

		if ( vertices.length === 2 ) {
			polyline = L.polyline( latlngs, {
				color    : color,
				weight   : 2,
				dashArray: '5, 5',
			} ).addTo( leafletMap );
		} else {
			polygon = L.polygon( latlngs, {
				color      : color,
				fillColor  : getFillColor(),
				fillOpacity: getFillOpacity(),
				weight     : 2,
			} ).addTo( leafletMap );
		}

		refreshAllVertexIcons();
	}

	function updateVertexCount() {
		if ( vertexCountEl ) {
			var n = vertices.length;
			vertexCountEl.textContent = n + ' ' + ( n === 1 ? 'vertex' : 'vertices' );
		}
	}

	function syncPointsToTextarea() {
		if ( ! pointsTextarea ) return;
		pointsTextarea.value = JSON.stringify(
			vertices.map( function ( v ) { return fromLeaflet( v.latLng ); } )
		);
	}

	// ------------------------------------------------------------------
	// Vertex icon helpers
	// ------------------------------------------------------------------

	function makeVertexIcon( isSelected ) {
		var bg   = isSelected ? '#f0c040' : getColor();
		var size = isSelected ? 16 : 14;
		return shared.makeCircleDivIcon( {
			className: 'bmg-admin-map-vertex-icon',
			size     : size,
			color    : bg,
			shadow   : '0 1px 4px rgba(0,0,0,.4)',
			cursor   : 'move',
		} );
	}

	function refreshAllVertexIcons() {
		vertices.forEach( function ( v ) {
			v.marker.setIcon( makeVertexIcon( v === selectedEntry ) );
		} );
	}

	function setSelected( entry ) {
		selectedEntry = entry;
		refreshAllVertexIcons();
	}

	// ------------------------------------------------------------------
	// Vertex management
	// ------------------------------------------------------------------

	function insertVertex( idx, latLng ) {
		var marker = L.marker( latLng, {
			icon     : makeVertexIcon( false ),
			draggable: true,
		} ).addTo( leafletMap );

		var entry = { latLng: latLng, marker: marker };
		vertices.splice( idx, 0, entry );

		marker.on( 'dragend', function ( e ) {
			entry.latLng = e.target.getLatLng();
			refreshPolygon();
			syncPointsToTextarea();
		} );

		marker.on( 'click', function ( e ) {
			L.DomEvent.stopPropagation( e );
			setSelected( selectedEntry === entry ? null : entry );
		} );

		// Stop mousedown from bubbling so dragging a vertex doesn't pan the map.
		shared.stopMarkerMousedownBubble( marker );

		refreshPolygon();
		updateVertexCount();
		syncPointsToTextarea();
		setSelected( entry );
	}

	function addVertex( latLng ) {
		insertVertex( vertices.length, latLng );
	}

	function removeLastVertex() {
		if ( ! vertices.length ) return;
		var last = vertices[ vertices.length - 1 ];
		if ( selectedEntry === last ) {
			selectedEntry = null;
		}
		vertices.pop().marker.remove();
		refreshPolygon();
		updateVertexCount();
		syncPointsToTextarea();
	}

	function clearAll() {
		selectedEntry = null;
		vertices.forEach( function ( v ) { v.marker.remove(); } );
		vertices = [];
		if ( polyline ) { polyline.remove(); polyline = null; }
		if ( polygon  ) { polygon.remove();  polygon  = null; }
		updateVertexCount();
		syncPointsToTextarea();
	}

	// ------------------------------------------------------------------
	// Sibling area overlays
	// ------------------------------------------------------------------

	function renderSiblingAreas( areasData ) {
		siblingPolygons.forEach( function ( p ) { p.remove(); } );
		siblingPolygons = [];
		if ( ! showSiblings || ! areasData || ! leafletMap ) return;
		areasData.forEach( function ( a ) {
			var latlngs = a.points.map( function ( p ) { return toLeaflet( p.x, p.y ); } );
			var poly = L.polygon( latlngs, {
				color      : a.color,
				fillColor  : a.fillColor,
				fillOpacity: a.fillOpacity * 0.5,
				weight     : 1.5,
				dashArray  : '4 4',
				opacity    : 0.6,
			} ).addTo( leafletMap );
			poly.bindTooltip( shared.escHtml( a.title ), { sticky: true } );
			poly.on( 'click', function ( e ) {
				var idx = selectedEntry ? vertices.indexOf( selectedEntry ) + 1 : vertices.length;
				insertVertex( idx, e.latlng );
			} );
			siblingPolygons.push( poly );
		} );
	}

	function clearSiblingPolygons() {
		siblingPolygons.forEach( function ( p ) { p.remove(); } );
		siblingPolygons = [];
	}

	// ------------------------------------------------------------------
	// Build / rebuild the Leaflet editor for a given image
	// ------------------------------------------------------------------

	function initEditor( imageUrl, w, h ) {
		imgW = w || 1;
		imgH = h || 1;

		var wrap = document.getElementById( 'bmg-area-editor-wrap' );
		if ( ! wrap ) return;

		clearSiblingPolygons();
		if ( leafletMap ) {
			leafletMap.remove();
			leafletMap = null;
		}
		selectedEntry = null;
		vertices = [];
		polyline = null;
		polygon  = null;

		wrap.style.aspectRatio = imgW + ' / ' + imgH;
		wrap.innerHTML = '<div id="bmg-area-editor" class="bmg-admin-editor-canvas"></div>';

		// Hint lives as a sibling after the wrap so the wrap's max-height doesn't clip it.
		var hint = wrap.nextElementSibling;
		if ( ! hint || ! hint.classList.contains( 'bmg-map-editor-hint' ) ) {
			hint = document.createElement( 'p' );
			hint.className = 'description bmg-map-editor-hint';
			hint.textContent = 'Click the map to add a vertex. Click a vertex to select it \u2014 the next click inserts after it. Drag to reposition.';
			wrap.parentNode.insertBefore( hint, wrap.nextSibling );
		}

		requestAnimationFrame( function () {
			var editor = shared.initSimpleCrsEditor( {
				containerId: 'bmg-area-editor',
				imageUrl   : imageUrl,
				imgW       : imgW,
				imgH       : imgH,
				minZoom    : ADMIN_MIN_ZOOM,
				maxZoom    : ADMIN_MAX_ZOOM,
				onClick    : function ( e ) {
					var idx = selectedEntry ? vertices.indexOf( selectedEntry ) + 1 : vertices.length;
					insertVertex( idx, e.latlng );
				},
			} );
			leafletMap = editor.map;

			// Restore previously saved polygon vertices.
			if ( meta.points && meta.points.length ) {
				meta.points.forEach( function ( p ) {
					addVertex( toLeaflet( p.x, p.y ) );
				} );
				meta.points = null; // consumed; map-change should start fresh
			}

			// Show sibling areas after vertices are restored.
			renderSiblingAreas( currentSiblingData );
		} );
	}

	// ------------------------------------------------------------------
	// Inject sibling-toggle checkbox after the clear button
	// ------------------------------------------------------------------

	( function injectSiblingToggle() {
		if ( ! clearBtn ) return;
		var label = document.createElement( 'label' );
		label.style.cssText = 'display:inline-flex;align-items:center;gap:4px;margin-left:8px;cursor:pointer;';
		var cb = document.createElement( 'input' );
		cb.type    = 'checkbox';
		cb.id      = 'bmg-area-show-siblings';
		cb.checked = true;
		label.appendChild( cb );
		label.appendChild( document.createTextNode( 'Show other areas' ) );
		clearBtn.parentNode.appendChild( label );
		cb.addEventListener( 'change', function () {
			showSiblings = this.checked;
			renderSiblingAreas( currentSiblingData );
		} );
	} )();

	// ------------------------------------------------------------------
	// Event listeners
	// ------------------------------------------------------------------

	mapSelect.addEventListener( 'change', function () {
		var mapId = this.value;
		var wrap  = document.getElementById( 'bmg-area-editor-wrap' );

		if ( ! mapId ) {
			clearSiblingPolygons();
			currentSiblingData = [];
			if ( leafletMap ) { leafletMap.remove(); leafletMap = null; }
			clearAll();
			if ( wrap ) {
				wrap.style.aspectRatio = '';
				wrap.innerHTML = '<p id="bmg-no-map-notice" class="description">Select a map above to enable the visual polygon editor.</p>';
			}
			return;
		}

		if ( wrap ) {
			wrap.style.aspectRatio = '';
			wrap.innerHTML = '<p class="description">Loading map image\u2026</p>';
		}

		shared.postForm( meta.ajaxUrl, {
			action        : 'bmg_get_map_image',
			nonce         : meta.nonce,
			map_id        : mapId,
			exclude_area_id: meta.postId || 0,
		} )
			.then( function ( json ) {
				if ( json.success && json.data.url ) {
					currentSiblingData = json.data.areas || [];
					initEditor( json.data.url, json.data.width, json.data.height );
				} else {
					if ( wrap ) wrap.innerHTML = '<p class="description" style="color:#c00;">This map has no featured image. Add one and try again.</p>';
				}
			} )
			.catch( function () {
				if ( wrap ) wrap.innerHTML = '<p class="description" style="color:#c00;">Could not load map image.</p>';
			} );
	} );

	colorInput     && colorInput.addEventListener( 'input', function () {
		if ( namedColorSelect ) namedColorSelect.value = '';
		refreshPolygon();
	} );
	fillColorInput && fillColorInput.addEventListener( 'input', function () {
		if ( namedFillColorSelect ) namedFillColorSelect.value = '';
		refreshPolygon();
	} );
	opacityInput && opacityInput.addEventListener( 'input', refreshPolygon );

	namedColorSelect && namedColorSelect.addEventListener( 'change', function () {
		var opt = this.options[ this.selectedIndex ];
		if ( opt && opt.dataset.hex ) {
			colorInput.value = opt.dataset.hex;
			refreshPolygon();
		}
	} );
	namedFillColorSelect && namedFillColorSelect.addEventListener( 'change', function () {
		var opt = this.options[ this.selectedIndex ];
		if ( opt && opt.dataset.hex ) {
			fillColorInput.value = opt.dataset.hex;
			refreshPolygon();
		}
	} );

	undoBtn  && undoBtn.addEventListener(  'click', removeLastVertex );
	clearBtn && clearBtn.addEventListener( 'click', clearAll );

	// ------------------------------------------------------------------
	// Initial state: load editor if editing an existing area with a map.
	// ------------------------------------------------------------------

	if ( meta.imageUrl ) {
		initEditor( meta.imageUrl, meta.imageWidth, meta.imageHeight );
	}

} )();
