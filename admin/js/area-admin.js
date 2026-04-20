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
	// Vertex management
	// ------------------------------------------------------------------

	function makeVertexIcon() {
		return L.divIcon( {
			className : 'bmg-area-vertex-icon',
			iconSize  : [ 14, 14 ],
			iconAnchor: [ 7, 7 ],
			html: '<div style="' +
				'width:14px;height:14px;border-radius:50%;' +
				'background:' + getColor() + ';' +
				'border:2px solid #fff;' +
				'box-shadow:0 1px 4px rgba(0,0,0,.4);' +
				'cursor:move;' +
			'"></div>',
		} );
	}

	function addVertex( latLng ) {
		var marker = L.marker( latLng, {
			icon     : makeVertexIcon(),
			draggable: true,
		} ).addTo( leafletMap );

		var entry = { latLng: latLng, marker: marker };
		vertices.push( entry );

		marker.on( 'dragend', function ( e ) {
			entry.latLng = e.target.getLatLng();
			refreshPolygon();
			syncPointsToTextarea();
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
	}

	function removeLastVertex() {
		if ( ! vertices.length ) return;
		vertices.pop().marker.remove();
		refreshPolygon();
		updateVertexCount();
		syncPointsToTextarea();
	}

	function clearAll() {
		vertices.forEach( function ( v ) { v.marker.remove(); } );
		vertices = [];
		if ( polyline ) { polyline.remove(); polyline = null; }
		if ( polygon  ) { polygon.remove();  polygon  = null; }
		updateVertexCount();
		syncPointsToTextarea();
	}

	// ------------------------------------------------------------------
	// Build / rebuild the Leaflet editor for a given image
	// ------------------------------------------------------------------

	function initEditor( imageUrl, w, h ) {
		imgW = w || 1;
		imgH = h || 1;

		var wrap = document.getElementById( 'bmg-area-editor-wrap' );
		if ( ! wrap ) return;

		if ( leafletMap ) {
			leafletMap.remove();
			leafletMap = null;
		}
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
			hint.textContent = 'Click the map to add vertices. Drag a vertex to reposition it.';
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

			leafletMap.on( 'click', function ( e ) {
				addVertex( e.latlng );
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
	// Event listeners
	// ------------------------------------------------------------------

	mapSelect.addEventListener( 'change', function () {
		var mapId = this.value;
		var wrap  = document.getElementById( 'bmg-area-editor-wrap' );

		if ( ! mapId ) {
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
		data.append( 'action', 'bmg_get_map_image' );
		data.append( 'nonce',  meta.nonce );
		data.append( 'map_id', mapId );

		fetch( meta.ajaxUrl, { method: 'POST', body: data } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				if ( json.success && json.data.url ) {
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
