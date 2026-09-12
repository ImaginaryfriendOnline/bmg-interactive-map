/* BMG Interactive Map — shared admin map/polygon editor helpers.
 * Used by admin.js (location marker editor) and area-admin.js (polygon
 * editor). Plain global, no bundler — consistent with the rest of the
 * plugin's admin/public scripts. */
window.BMGAdminMapEditor = ( function () {
	'use strict';

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	// ------------------------------------------------------------------
	// Coordinate conversion
	//
	// Storage: x%, y% where (0,0) = top-left, y increases downward.
	// Leaflet CRS.Simple with pixel bounds [H, W], lat increases upward.
	//   leaflet_lat = H - (y/100) * H
	//   leaflet_lng = (x/100) * W
	//
	// getW/getH are functions (not plain values) so the converter stays
	// correct even after initEditor() reassigns the caller's imgW/imgH on
	// a map-select change. precision is the number of decimal digits kept
	// when converting back from a Leaflet latLng (2 for locations, 4 for
	// areas — preserved exactly as before, not unified).
	// ------------------------------------------------------------------

	function makeCoordConverter( getW, getH, precision ) {
		return {
			toLeaflet: function ( x, y ) {
				var w = getW(), h = getH();
				return L.latLng( h - ( y / 100 ) * h, ( x / 100 ) * w );
			},
			fromLeaflet: function ( latLng ) {
				var w = getW(), h = getH();
				return {
					x: +( latLng.lng / w * 100 ).toFixed( precision ),
					y: +( ( h - latLng.lat ) / h * 100 ).toFixed( precision ),
				};
			},
		};
	}

	function postForm( url, fields ) {
		var data = new FormData();
		Object.keys( fields ).forEach( function ( k ) { data.append( k, fields[ k ] ); } );
		return fetch( url, { method: 'POST', body: data } ).then( function ( r ) { return r.json(); } );
	}

	// Shared Leaflet CRS.Simple bootstrap: creates the map, image overlay,
	// bounds, and resize-refit observer. options:
	//   containerId, imageUrl, imgW, imgH, minZoom, maxZoom,
	//   onClick( latlng )  — optional map click handler
	// Returns { map, bounds }.
	function initSimpleCrsEditor( options ) {
		var map = L.map( options.containerId, {
			crs                : L.CRS.Simple,
			minZoom            : options.minZoom,
			maxZoom            : options.maxZoom,
			zoomSnap           : 0,
			zoomControl        : true,
			attributionControl: false,
		} );

		var bounds = [ [ 0, 0 ], [ options.imgH, options.imgW ] ];
		L.imageOverlay( options.imageUrl, bounds ).addTo( map );
		map.fitBounds( bounds );
		map.setMaxBounds( [
			[ -options.imgH * 0.1, -options.imgW * 0.1 ],
			[  options.imgH * 1.1,  options.imgW * 1.1 ],
		] );

		if ( options.onClick ) {
			map.on( 'click', options.onClick );
		}

		if ( typeof ResizeObserver !== 'undefined' ) {
			var ro = new ResizeObserver( function () {
				map.invalidateSize();
				map.fitBounds( bounds );
			} );
			ro.observe( document.getElementById( options.containerId ) );
		}

		return { map: map, bounds: bounds };
	}

	// opts: { className, size, color, borderColor, shadow, cursor }
	function makeCircleDivIcon( opts ) {
		var size = opts.size, half = size / 2;
		return L.divIcon( {
			className : opts.className,
			iconSize  : [ size, size ],
			iconAnchor: [ half, half ],
			html      : '<div style="' +
				'width:' + size + 'px;height:' + size + 'px;border-radius:50%;' +
				'background:' + opts.color + ';' +
				'border:2px solid ' + ( opts.borderColor || '#fff' ) + ';' +
				'box-shadow:' + ( opts.shadow || '0 2px 6px rgba(0,0,0,.45)' ) + ';' +
				( opts.cursor ? 'cursor:' + opts.cursor + ';' : '' ) +
			'"></div>',
		} );
	}

	// After a marker's icon is replaced (setIcon swaps the DOM element),
	// stop mousedown from bubbling to the map's drag handler. setTimeout
	// defers until Leaflet has finished the swap.
	function stopMarkerMousedownBubble( marker ) {
		setTimeout( function () {
			var el = marker && marker.getElement();
			if ( el ) {
				L.DomEvent.on( el, 'mousedown', L.DomEvent.stopPropagation );
			}
		}, 0 );
	}

	return {
		escHtml                  : escHtml,
		makeCoordConverter       : makeCoordConverter,
		postForm                 : postForm,
		initSimpleCrsEditor      : initSimpleCrsEditor,
		makeCircleDivIcon        : makeCircleDivIcon,
		stopMarkerMousedownBubble: stopMarkerMousedownBubble,
	};
} )();
