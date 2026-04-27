/* BMG Interactive Map — Admin polygon editor */
( function () {
	'use strict';

	var meta          = window.bmgAreaMeta;
	var adminSettings = window.bmgAdminSettings || {};
	var ADMIN_MIN_ZOOM = ( adminSettings.minZoom != null ) ? Number( adminSettings.minZoom ) : -3;
	var ADMIN_MAX_ZOOM = ( adminSettings.maxZoom != null ) ? Number( adminSettings.maxZoom ) : 3;

	var mapSelect      = document.getElementById( 'bmg_area_map_id' );
	var colorInput     = document.getElementById( 'bmg_area_color' );
	var fillColorInput = document.getElementById( 'bmg_area_fill_color' );
	var opacityInput   = document.getElementById( 'bmg_area_fill_opacity' );
	var pointsTextarea = document.getElementById( 'bmg_area_points' );
	var vertexCountEl  = document.getElementById( 'bmg-area-vertex-count' );
	var undoBtn        = document.getElementById( 'bmg-area-undo' );
	var clearBtn       = document.getElementById( 'bmg-area-clear' );

	if ( ! mapSelect || typeof L === 'undefined' ) return;

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

	// ------------------------------------------------------------------
	// Coordinate conversion (same convention as admin.js and public.js)
	// ------------------------------------------------------------------

	function toLeaflet( x, y ) {
		return L.latLng( imgH - ( y / 100 ) * imgH, ( x / 100 ) * imgW );
	}

	function fromLeaflet( latLng ) {
		return {
			x: +( latLng.lng / imgW * 100 ).toFixed( 4 ),
			y: +( ( imgH - latLng.lat ) / imgH * 100 ).toFixed( 4 ),
		};
	}

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
		var half = size / 2;
		return L.divIcon( {
			className : 'bmg-area-vertex-icon',
			iconSize  : [ size, size ],
			iconAnchor: [ half, half ],
			html: '<div style="' +
				'width:' + size + 'px;height:' + size + 'px;border-radius:50%;' +
				'background:' + bg + ';' +
				'border:2px solid #fff;' +
				'box-shadow:0 1px 4px rgba(0,0,0,.4);' +
				'cursor:move;' +
			'"></div>',
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
		setTimeout( function () {
			var el = marker.getElement();
			if ( el ) {
				L.DomEvent.on( el, 'mousedown', L.DomEvent.stopPropagation );
			}
		}, 0 );

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
				interactive: false,
				opacity    : 0.6,
			} ).addTo( leafletMap );
			poly.bindTooltip( a.title, { sticky: true } );
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
		wrap.innerHTML = '<div id="bmg-area-editor" class="bmg-admin-area-editor"></div>';

		// Hint lives as a sibling after the wrap so the wrap's max-height doesn't clip it.
		var hint = wrap.nextElementSibling;
		if ( ! hint || ! hint.classList.contains( 'bmg-map-editor-hint' ) ) {
			hint = document.createElement( 'p' );
			hint.className = 'description bmg-map-editor-hint';
			hint.textContent = 'Click the map to add a vertex. Click a vertex to select it \u2014 the next click inserts after it. Drag to reposition.';
			wrap.parentNode.insertBefore( hint, wrap.nextSibling );
		}

		requestAnimationFrame( function () {
			leafletMap = L.map( 'bmg-area-editor', {
				crs              : L.CRS.Simple,
				minZoom          : ADMIN_MIN_ZOOM,
				maxZoom          : ADMIN_MAX_ZOOM,
				zoomSnap         : 0,
				zoomControl      : true,
				attributionControl: false,
			} );

			var bounds = [ [ 0, 0 ], [ imgH, imgW ] ];
			L.imageOverlay( imageUrl, bounds ).addTo( leafletMap );
			leafletMap.fitBounds( bounds );
			leafletMap.setMaxBounds( [
				[ -imgH * 0.1, -imgW * 0.1 ],
				[  imgH * 1.1,  imgW * 1.1 ],
			] );

			// Restore previously saved polygon vertices.
			if ( meta.points && meta.points.length ) {
				meta.points.forEach( function ( p ) {
					addVertex( toLeaflet( p.x, p.y ) );
				} );
				meta.points = null; // consumed; map-change should start fresh
			}

			// Show sibling areas after vertices are restored.
			renderSiblingAreas( currentSiblingData );

			leafletMap.on( 'click', function ( e ) {
				var idx = selectedEntry ? vertices.indexOf( selectedEntry ) + 1 : vertices.length;
				insertVertex( idx, e.latlng );
			} );

			if ( typeof ResizeObserver !== 'undefined' ) {
				var ro = new ResizeObserver( function () {
					if ( leafletMap ) {
						leafletMap.invalidateSize();
						leafletMap.fitBounds( bounds );
					}
				} );
				ro.observe( document.getElementById( 'bmg-area-editor' ) );
			}
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

		var data = new FormData();
		data.append( 'action',          'bmg_get_map_image' );
		data.append( 'nonce',           meta.nonce );
		data.append( 'map_id',          mapId );
		data.append( 'exclude_area_id', meta.postId || 0 );

		fetch( meta.ajaxUrl, { method: 'POST', body: data } )
			.then( function ( r ) { return r.json(); } )
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

	colorInput     && colorInput.addEventListener(     'input', refreshPolygon );
	fillColorInput && fillColorInput.addEventListener( 'input', refreshPolygon );
	opacityInput   && opacityInput.addEventListener(   'input', refreshPolygon );

	undoBtn  && undoBtn.addEventListener(  'click', removeLastVertex );
	clearBtn && clearBtn.addEventListener( 'click', clearAll );

	// ------------------------------------------------------------------
	// Initial state: load editor if editing an existing area with a map.
	// ------------------------------------------------------------------

	if ( meta.imageUrl ) {
		initEditor( meta.imageUrl, meta.imageWidth, meta.imageHeight );
	}

} )();
