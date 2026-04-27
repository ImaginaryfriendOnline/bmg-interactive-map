/* BMG Interactive Map — Frontend Leaflet renderer */
( function () {
	'use strict';

	// NOTE: The top-level Leaflet guard was intentionally removed.
	// Keeping it here would silently abort the entire IIFE — including the
	// MutationObserver and window.load listener — if any performance plugin
	// loads scripts async and Leaflet happens to arrive a fraction of a second
	// after public.js.  Instead, each initMap() call checks for L individually
	// so the infrastructure stays alive and the window.load retry can recover.

	var settings      = window.bmgMapSettings || {};
	var DEFAULT_COLOR = settings.defaultColor || '#e74c3c';
	// wp_localize_script serialises all scalar values as strings, so use Number()
	// rather than a typeof check to coerce them back to numeric values.
	var MIN_ZOOM      = ( settings.minZoom != null ) ? Number( settings.minZoom ) : -3;
	var MAX_ZOOM      = ( settings.maxZoom != null ) ? Number( settings.maxZoom ) : 3;
	var ZOOM_POSITION = settings.zoomPosition || 'topleft';

	// ------------------------------------------------------------------
	// Coordinate conversion
	//
	// Storage: x%, y% — (0,0) = top-left, y increases downward.
	// Leaflet CRS.Simple with pixel bounds [H, W], lat increases upward.
	//   leaflet_lat = H - (y/100) * H
	//   leaflet_lng = (x/100) * W
	// ------------------------------------------------------------------

	function toLatLng( x, y, W, H ) {
		return L.latLng( H - ( y / 100 ) * H, ( x / 100 ) * W );
	}

	// ------------------------------------------------------------------
	// Pick the starting view for the current viewport.
	// Reads data-responsive-start JSON (Elementor widget) with fallback
	// to legacy flat data-start-* attributes (direct shortcode usage).
	// Returns {zoom, x, y} or null (→ fitBounds).
	// ------------------------------------------------------------------

	function pickStartView( el ) {
		var raw = el.dataset.responsiveStart;
		if ( ! raw ) {
			var zoom = el.dataset.startZoom;
			var x    = el.dataset.startX;
			var y    = el.dataset.startY;
			if ( zoom !== undefined && zoom !== '' && x !== undefined && x !== '' && y !== undefined && y !== '' ) {
				return { zoom: parseFloat( zoom ), x: parseFloat( x ), y: parseFloat( y ) };
			}
			return null;
		}
		var data;
		try { data = JSON.parse( raw ); } catch ( e ) { return null; }

		var w     = window.innerWidth;
		var order = w < 768  ? [ 'mobile', 'tablet', 'desktop' ]
		          : w < 1025 ? [ 'tablet', 'desktop' ]
		          :             [ 'desktop' ];

		for ( var i = 0; i < order.length; i++ ) {
			var bp = data[ order[ i ] ];
			if ( bp && bp.zoom !== '' && bp.x !== '' && bp.y !== '' ) {
				return { zoom: parseFloat( bp.zoom ), x: parseFloat( bp.x ), y: parseFloat( bp.y ) };
			}
		}
		return null;
	}

	// ------------------------------------------------------------------
	// Initialise one map container
	// ------------------------------------------------------------------

	function initMap( el ) {
		// Guard against double-initialisation (e.g. Elementor re-renders the widget).
		if ( el.dataset.bmgReady ) return;

		// If Leaflet has not loaded yet, bail WITHOUT marking the element ready
		// so the window.load retry can pick it up once Leaflet arrives.
		if ( typeof L === 'undefined' ) return;

		el.dataset.bmgReady = '1';

		var imageUrl   = el.dataset.image;
		var tilesetUrl = el.dataset.tilesetUrl || '';
		var useTiles   = !! tilesetUrl;
		var locations;

		try {
			locations = JSON.parse( el.dataset.locations || '[]' );
		} catch ( e ) {
			locations = [];
		}

		// Load the image first so we can use its natural pixel dimensions
		// as the Leaflet bounds — this prevents Leaflet from scaling the
		// image to fit an arbitrary coordinate space.
		var img = new Image();
		img.onload = function () {
			img.onload = null; // prevent double-fire when img.complete triggers manually below
			var W = img.naturalWidth;
			var H = img.naturalHeight;
			if ( ! W || ! H ) return; // broken / zero-size image

			// Unless explicit dimensions were set server-side, correct the wrapper to
			// the image's exact natural proportions before Leaflet initialises.
			// Two mechanisms are corrected together: the CSS aspect-ratio (primary,
			// handles responsive height) and the SVG spacer viewBox (fallback that
			// prevents the wrapper collapsing in Elementor Flexbox Containers).
			var wrapper = el.parentElement;
			if ( wrapper && wrapper.classList.contains( 'bmg-map-aspect-wrapper' )
					&& ! wrapper.dataset.explicitSize ) {
				wrapper.style.aspectRatio = W + ' / ' + H;
				var spacer = wrapper.querySelector( '.bmg-map-aspect-spacer' );
				if ( spacer ) {
					spacer.src = 'data:image/svg+xml,' + encodeURIComponent(
						'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + W + ' ' + H + '"/>'
					);
				}
			}

			requestAnimationFrame( function () {

			// Per-embed zoom position and levels override global settings.
			var zoomPos   = el.dataset.zoomPosition || ZOOM_POSITION;
			var minZoom   = el.dataset.minZoom !== undefined && el.dataset.minZoom !== '' ? Number( el.dataset.minZoom ) : MIN_ZOOM;
			var maxZoom   = el.dataset.maxZoom !== undefined && el.dataset.maxZoom !== '' ? Number( el.dataset.maxZoom ) : MAX_ZOOM;
			var startView          = pickStartView( el );
			var hasStart           = startView !== null;
			var initialPositionSet = false;

			// Stash the Leaflet instance so the Elementor re-render hook can
			// destroy it cleanly before re-initialising.
			if ( el._bmgMap ) {
				try { el._bmgMap.remove(); } catch ( ignore ) {}
				delete el._bmgMap;
			}

			var map = L.map( el, {
				crs              : L.CRS.Simple,
				minZoom          : minZoom,
				maxZoom          : maxZoom,
				zoomSnap         : 0,
				zoomControl      : false, // added manually so position can be set
				attributionControl: false,
				scrollWheelZoom  : true,
			} );

			el._bmgMap = map; // retained for cleanup on Elementor re-render

			L.control.zoom( { position: zoomPos } ).addTo( map );

			var bounds = [ [ 0, 0 ], [ H, W ] ];
			if ( useTiles ) {
				L.tileLayer( tilesetUrl, {
					tileSize      : 256,
					noWrap        : true,
					minZoom       : minZoom,
					maxZoom       : maxZoom,
					maxNativeZoom : 0,
					bounds        : bounds,
				} ).addTo( map );
			} else {
				L.imageOverlay( imageUrl, bounds ).addTo( map );
			}

			// Apply starting view or fit-all, depending on whether a start view is set.
			if ( hasStart ) {
				map.setView( toLatLng( startView.x, startView.y, W, H ), startView.zoom );
				initialPositionSet = true;
			} else if ( el.offsetWidth > 0 && el.offsetHeight > 0 ) {
				map.fitBounds( bounds, { padding: [ 0, 0 ] } );
				initialPositionSet = true;
			}

			// Re-measure at increasing delays.  Elementor columns, CSS entry
			// animations, and sticky headers can all keep the container at 0px
			// for hundreds of milliseconds after the first RAF.  We correct the
			// size at 0 / 300 / 1 000 ms; after the user has interacted we still
			// call invalidateSize (to keep Leaflet's internal state correct) but
			// skip fitBounds so we don't reset their zoom/pan position.
			// Track real user interaction (mouse/touch/wheel), NOT Leaflet's
			// movestart/zoomstart — those also fire from our own fitBounds calls,
			// which would prematurely lock out the size-correction retries below.
			var userInteracted = false;
			[ 'mousedown', 'touchstart', 'wheel', 'pointerdown' ].forEach( function ( evt ) {
				el.addEventListener( evt, function () { userInteracted = true; }, { passive: true } );
			} );

			function fitIfUntouched() {
				if ( el._bmgMap !== map ) return;
				map.invalidateSize();
				if ( ! initialPositionSet && ! hasStart && ! userInteracted && el.offsetWidth > 0 && el.offsetHeight > 0 ) {
					map.fitBounds( bounds, { padding: [ 0, 0 ] } );
					initialPositionSet = true;
				}
			}

			[ 0, 300, 1000 ].forEach( function ( delay ) {
				setTimeout( fitIfUntouched, delay );
			} );

			// ResizeObserver on the WRAPPER (not the container): fires whenever
			// the column/layout width changes.  CSS aspect-ratio automatically derives
			// the correct height from the wrapper's width, so we only need to tell
			// Leaflet that its container has changed.
			if ( window.ResizeObserver ) {
				var wrapperObserver = new ResizeObserver( function () {
					if ( el._bmgMap !== map ) { wrapperObserver.disconnect(); return; }
					map.invalidateSize();
					if ( ! initialPositionSet && ! hasStart && ! userInteracted && el.offsetWidth > 0 && el.offsetHeight > 0 ) {
						map.fitBounds( bounds, { padding: [ 0, 0 ] } );
						initialPositionSet = true;
					}
				} );
				wrapperObserver.observe( wrapper || el );
			}

			// Prevent panning too far outside the image.
			map.setMaxBounds( [
				[ -H * 0.1, -W * 0.1 ],
				[  H * 1.1,  W * 1.1 ],
			] );

			// After autoPan runs, nudge the popup element if it still clips the map edge.
			map.on( 'popupopen', function ( e ) {
				setTimeout( function () {
					var popupEl = e.popup.getElement();
					var mapEl   = map.getContainer();
					if ( ! popupEl || ! mapEl ) return;

					var mapRect   = mapEl.getBoundingClientRect();
					var popupRect = popupEl.getBoundingClientRect();
					var pad = 10;
					var dx = 0, dy = 0;

					if ( popupRect.left   < mapRect.left   + pad ) dx =  ( mapRect.left   + pad ) - popupRect.left;
					if ( popupRect.right  > mapRect.right  - pad ) dx = -( popupRect.right  - ( mapRect.right  - pad ) );
					if ( popupRect.top    < mapRect.top    + pad ) dy =  ( mapRect.top    + pad ) - popupRect.top;
					if ( popupRect.bottom > mapRect.bottom - pad ) dy = -( popupRect.bottom - ( mapRect.bottom - pad ) );

					if ( dx !== 0 || dy !== 0 ) {
						popupEl.style.transform += ' translate(' + Math.round( dx ) + 'px,' + Math.round( dy ) + 'px)';
					}
				}, 0 );
			} );

			var markers = [];

			locations.forEach( function ( loc ) {
				var ll    = toLatLng( loc.x, loc.y, W, H );
				var color = escAttr( loc.color || DEFAULT_COLOR );
				var label = escAttr( loc.title );

				var icon = L.divIcon( {
					className : 'bmg-map-marker-icon',
					iconSize  : [ 26, 26 ],
					iconAnchor: [ 13, 13 ],
					html      : '<div class="bmg-pin"'
						+ ' role="button"'
						+ ' tabindex="0"'
						+ ' aria-label="' + label + '"'
						+ ' style="background:' + color + ';">'
						+ ( loc.icon ? '<i class="' + escAttr( loc.icon ) + ' bmg-pin__icon" aria-hidden="true"></i>' : '' )
						+ '</div>',
				} );

				var leafletMarker = L.marker( ll, { icon: icon } ).addTo( map );
				markers.push( leafletMarker );

				if ( el.dataset.tooltips ) {
					leafletMarker.bindTooltip( escHtml( loc.title ), {
						direction: 'top',
						offset   : [ 0, -12 ],
					} );
				}

				var popupHtml = '<h3 class="bmg-popup-title">' + escHtml( loc.title ) + '</h3>';
				if ( loc.description ) {
					// description is already sanitised HTML produced by wp_kses_post server-side.
					popupHtml += '<div class="bmg-popup-body">' + loc.description + '</div>';
				}

				leafletMarker.bindPopup( popupHtml, {
					maxWidth      : 300,
					className     : 'bmg-leaflet-popup',
					autoPanPadding: [ 20, 20 ],
				} );

				// Keyboard accessibility: open popup on Enter or Space.
				leafletMarker.on( 'add', function () {
					var markerEl = leafletMarker.getElement();
					if ( ! markerEl ) return;

					markerEl.setAttribute( 'tabindex', '0' );
					markerEl.setAttribute( 'role', 'button' );
					markerEl.setAttribute( 'aria-label', loc.title );

					markerEl.addEventListener( 'keydown', function ( e ) {
						if ( e.key === 'Enter' || e.key === ' ' ) {
							e.preventDefault();
							leafletMarker.openPopup();
						}
					} );
				} );
			} );

			// Render polygon areas.
			var areasRaw = el.getAttribute( 'data-areas' );
			var areas;
			try {
				areas = areasRaw ? JSON.parse( areasRaw ) : [];
			} catch ( e ) {
				areas = [];
			}

			var polys = [];

			areas.forEach( function ( area ) {
				var latlngs = area.points.map( function ( p ) {
					return [ H - ( p.y / 100 ) * H, ( p.x / 100 ) * W ];
				} );

				var popupHtml = '<h3 class="bmg-popup-title">' + escHtml( area.title ) + '</h3>';
				if ( area.description ) {
					popupHtml += '<div class="bmg-popup-body">' + area.description + '</div>';
				}

				var poly = L.polygon( latlngs, {
					color      : area.color,
					fillColor  : area.fillColor,
					fillOpacity: 0,
					weight     : 2,
				} ).addTo( map );

				poly.bindPopup( popupHtml, {
					className     : 'bmg-leaflet-popup',
					closeButton   : true,
					autoPanPadding: [ 20, 20 ],
				} );

				poly.bindTooltip( escHtml( area.title ), {
					sticky   : true,
					direction: 'top',
					offset   : [ 0, -4 ],
				} );

				poly.on( 'mouseover', function () {
					poly.setStyle( { fillOpacity: area.fillOpacity } );
				} );

				poly.on( 'mouseout', function () {
					if ( el.dataset.areasHighlighted !== '1' ) {
						poly.setStyle( { fillOpacity: 0 } );
					}
				} );

				poly.on( 'click', function ( e ) {
					L.DomEvent.stopPropagation( e );
				} );

				polys.push( poly );
			} );

			if ( el.dataset.closeIcon ) {
				map.on( 'popupopen', function ( e ) {
					var btn = e.popup._container
						? e.popup._container.querySelector( '.leaflet-popup-close-button' )
						: null;
					if ( btn ) {
						btn.innerHTML = el.dataset.closeIcon;
					}
				} );
			}

			if ( el.dataset.showList ) {
				initList( el, map, markers );
			}

			if ( el.dataset.showAreaList ) {
				initAreaList( el, map, polys, areas );
			}

			initToolbar( el, map, el.closest( '.bmg-map-layout' ), polys, areas );

			} ); // requestAnimationFrame
		};
		img.src = imageUrl;

		// Cached-image fallback: some browsers (Safari, and any browser when the
		// image was preloaded) set img.complete synchronously before the async
		// load event fires — meaning onload never runs.  Trigger it manually.
		// The `img.onload = null` above prevents double-execution if the browser
		// also fires the async event afterwards.
		if ( img.complete && img.naturalWidth ) {
			img.onload();
		}
	}

	// ------------------------------------------------------------------
	// Location list — links sidebar list items to Leaflet markers
	// ------------------------------------------------------------------

	function initList( mapEl, map, markers ) {
		var layoutEl = mapEl.closest( '.bmg-map-layout' );
		if ( ! layoutEl ) return;

		var listEl = layoutEl.querySelector( '.bmg-location-list' );
		if ( ! listEl ) return;

		var items = listEl.querySelectorAll( '.bmg-location-list__item' );

		// Set --bmg-map-height so the list CSS var matches the rendered map.
		// Re-sync on resize (e.g. phone rotation) so the panel stays the right height.
		function syncListHeight() {
			layoutEl.style.setProperty( '--bmg-map-height', mapEl.offsetHeight + 'px' );
		}
		syncListHeight();
		if ( window.ResizeObserver ) {
			new ResizeObserver( syncListHeight ).observe( mapEl );
		}

		// Cap each list to 5 visible items so stacked and floating lists stay compact.
		if ( items.length >= 5 ) {
			var searchWrap  = listEl.querySelector( '.bmg-location-search-wrap' );
			var searchH     = searchWrap ? searchWrap.offsetHeight : 0;
			var headerEl    = listEl.querySelector( '.bmg-location-list__header' );
			var headerH     = headerEl ? headerEl.offsetHeight : 34;
			var itemH       = items[ 0 ].offsetHeight || 36;
			listEl.style.maxHeight = ( headerH + searchH + itemH * 5 ) + 'px';
		}

		// Collapse/expand toggle.
		var toggleBtn = listEl.querySelector( '.bmg-location-list__toggle' );
		if ( toggleBtn ) {
			var savedMaxHeight = '';

			toggleBtn.addEventListener( 'click', function () {
				var collapsed = listEl.classList.toggle( 'bmg-location-list--collapsed' );
				toggleBtn.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
				toggleBtn.setAttribute( 'aria-label',    collapsed ? 'Expand location list' : 'Collapse location list' );

				if ( collapsed ) {
					savedMaxHeight = listEl.style.maxHeight;
					listEl.style.maxHeight = '';
				} else if ( savedMaxHeight ) {
					listEl.style.maxHeight = savedMaxHeight;
				}

				// Re-measure after the CSS transition completes.
				setTimeout( function () {
					syncListHeight();
					map.invalidateSize();
				}, 220 );
			} );
		}

		// Search: filter list items as the user types.  Markers stay on the
		// map — only the sidebar list is narrowed down.
		var searchInput = listEl.querySelector( '.bmg-location-search' );
		if ( searchInput ) {
			searchInput.addEventListener( 'input', function () {
				var q = searchInput.value.toLowerCase();
				items.forEach( function ( item ) {
					var titleEl = item.querySelector( '.bmg-location-list__title' );
					var title   = titleEl ? titleEl.textContent.toLowerCase() : '';
					item.style.display = ( q && title.indexOf( q ) === -1 ) ? 'none' : '';
				} );
			} );
		}

		function setActive( idx ) {
			items.forEach( function ( item ) {
				item.classList.toggle(
					'bmg-location-list__item--active',
					parseInt( item.dataset.index, 10 ) === idx
				);
			} );
		}

		// List item → map: fly to marker and open popup.
		items.forEach( function ( item ) {
			function activate() {
				var idx = parseInt( item.dataset.index, 10 );
				if ( isNaN( idx ) || ! markers[ idx ] ) return;
				setActive( idx );
				map.flyTo( markers[ idx ].getLatLng(), map.getZoom(), {
					animate : true,
					duration: 0.4,
				} );
				markers[ idx ].openPopup();
			}
			item.addEventListener( 'click', activate );
			item.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					activate();
				}
			} );
		} );

		// Map marker → list: highlight and scroll the corresponding item.
		markers.forEach( function ( marker, idx ) {
			marker.on( 'popupopen', function () {
				setActive( idx );
				var activeItem = listEl.querySelector( '.bmg-location-list__item--active' );
				// Only scroll if the item is currently visible (not filtered out).
				if ( activeItem && activeItem.style.display !== 'none' ) {
					activeItem.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
				}
			} );
			marker.on( 'popupclose', function () {
				setActive( -1 );
			} );
		} );
	}

	// ------------------------------------------------------------------
	// Area list — links sidebar list items to Leaflet polygon layers
	// ------------------------------------------------------------------

	function initAreaList( mapEl, map, polys, areas ) {
		var layoutEl = mapEl.closest( '.bmg-map-layout' );
		if ( ! layoutEl ) return;

		var listEl = layoutEl.querySelector( '.bmg-area-list' );
		if ( ! listEl ) return;

		var items = listEl.querySelectorAll( '.bmg-location-list__item' );

		function syncListHeight() {
			layoutEl.style.setProperty( '--bmg-map-height', mapEl.offsetHeight + 'px' );
		}
		syncListHeight();
		if ( window.ResizeObserver ) {
			new ResizeObserver( syncListHeight ).observe( mapEl );
		}

		if ( items.length >= 5 ) {
			var searchWrap = listEl.querySelector( '.bmg-location-search-wrap' );
			var searchH    = searchWrap ? searchWrap.offsetHeight : 0;
			var headerEl   = listEl.querySelector( '.bmg-location-list__header' );
			var headerH    = headerEl ? headerEl.offsetHeight : 34;
			var itemH      = items[ 0 ].offsetHeight || 36;
			listEl.style.maxHeight = ( headerH + searchH + itemH * 5 ) + 'px';
		}

		var toggleBtn = listEl.querySelector( '.bmg-location-list__toggle' );
		if ( toggleBtn ) {
			var savedMaxHeight = '';
			toggleBtn.addEventListener( 'click', function () {
				var collapsed = listEl.classList.toggle( 'bmg-area-list--collapsed' );
				toggleBtn.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
				toggleBtn.setAttribute( 'aria-label', collapsed ? 'Expand area list' : 'Collapse area list' );
				if ( collapsed ) {
					savedMaxHeight = listEl.style.maxHeight;
					listEl.style.maxHeight = '';
				} else if ( savedMaxHeight ) {
					listEl.style.maxHeight = savedMaxHeight;
				}
				setTimeout( function () {
					syncListHeight();
					map.invalidateSize();
				}, 220 );
			} );
		}

		var searchInput = listEl.querySelector( '.bmg-location-search' );
		if ( searchInput ) {
			searchInput.addEventListener( 'input', function () {
				var q = searchInput.value.toLowerCase();
				items.forEach( function ( item ) {
					var titleEl = item.querySelector( '.bmg-location-list__title' );
					var title   = titleEl ? titleEl.textContent.toLowerCase() : '';
					item.style.display = ( q && title.indexOf( q ) === -1 ) ? 'none' : '';
				} );
			} );
		}

		function setActive( idx ) {
			items.forEach( function ( item ) {
				item.classList.toggle(
					'bmg-location-list__item--active',
					parseInt( item.dataset.index, 10 ) === idx
				);
			} );
		}

		items.forEach( function ( item ) {
			function activate() {
				var idx = parseInt( item.dataset.index, 10 );
				if ( isNaN( idx ) || ! polys[ idx ] ) return;
				setActive( idx );
				var lls = polys[ idx ].getLatLngs()[ 0 ];
				var lat = 0, lng = 0;
				lls.forEach( function ( ll ) { lat += ll.lat; lng += ll.lng; } );
				var centroid = L.latLng( lat / lls.length, lng / lls.length );
				map.flyTo( centroid, map.getZoom(), { animate: true, duration: 0.4 } );
				polys[ idx ].openPopup( centroid );
			}
			item.addEventListener( 'click', activate );
			item.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					activate();
				}
			} );
			item.addEventListener( 'mouseenter', function () {
				var idx = parseInt( item.dataset.index, 10 );
				if ( ! isNaN( idx ) && polys[ idx ] ) {
					polys[ idx ].setStyle( { fillOpacity: areas[ idx ].fillOpacity } );
				}
			} );
			item.addEventListener( 'mouseleave', function () {
				var idx = parseInt( item.dataset.index, 10 );
				if ( ! isNaN( idx ) && polys[ idx ] && ! polys[ idx ].isPopupOpen() ) {
					if ( mapEl.dataset.areasHighlighted !== '1' ) {
						polys[ idx ].setStyle( { fillOpacity: 0 } );
					}
				}
			} );
		} );

		polys.forEach( function ( poly, idx ) {
			poly.on( 'popupopen', function () {
				setActive( idx );
				var activeItem = listEl.querySelector( '.bmg-location-list__item--active' );
				if ( activeItem && activeItem.style.display !== 'none' ) {
					activeItem.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
				}
			} );
			poly.on( 'popupclose', function () {
				setActive( -1 );
			} );
		} );
	}

	// ------------------------------------------------------------------
	// Toolbar — list toggles + fullscreen
	// ------------------------------------------------------------------

	function initToolbar( el, map, layoutEl, polys, areas ) {
		if ( ! layoutEl ) return;
		var wrapperEl = el.closest( '.bmg-map-aspect-wrapper' );
		if ( ! wrapperEl ) return;
		var toolbar = wrapperEl.querySelector( '.bmg-map-toolbar' );
		if ( ! toolbar ) return;

		function updatePanelVisibility() {
			layoutEl.querySelectorAll( '.bmg-lists-panel' ).forEach( function ( panel ) {
				var loc  = panel.querySelector( '.bmg-location-list' );
				var area = panel.querySelector( '.bmg-area-list' );
				var locHidden  = ! loc  || loc.classList.contains( 'bmg-location-list--hidden' );
				var areaHidden = ! area || area.classList.contains( 'bmg-area-list--hidden' );
				panel.style.display = ( locHidden && areaHidden ) ? 'none' : '';
			} );
		}

		// Combined lists toggle — hides/shows both location and area lists together.
		var listsBtn = toolbar.querySelector( '.bmg-toolbar-btn--lists' );
		if ( listsBtn ) {
			var locList  = layoutEl.querySelector( '.bmg-location-list' );
			var areaList = layoutEl.querySelector( '.bmg-area-list' );
			var listsHidden = false;
			listsBtn.addEventListener( 'click', function () {
				listsHidden = ! listsHidden;
				if ( locList )  locList.classList.toggle(  'bmg-location-list--hidden', listsHidden );
				if ( areaList ) areaList.classList.toggle( 'bmg-area-list--hidden',     listsHidden );
				listsBtn.setAttribute( 'aria-pressed', listsHidden ? 'true' : 'false' );
				updatePanelVisibility();
				setTimeout( function () { map.invalidateSize(); }, 50 );
			} );
		}

		// Area highlight toggle — keeps all polygon fills visible continuously.
		var highlightBtn = toolbar.querySelector( '.bmg-toolbar-btn--area-highlight' );
		if ( highlightBtn ) {
			if ( ! polys || ! polys.length ) {
				highlightBtn.style.display = 'none';
			} else {
				highlightBtn.addEventListener( 'click', function () {
					var on = el.dataset.areasHighlighted !== '1';
					el.dataset.areasHighlighted = on ? '1' : '0';
					highlightBtn.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
					polys.forEach( function ( poly, idx ) {
						poly.setStyle( { fillOpacity: on ? areas[ idx ].fillOpacity : 0 } );
					} );
				} );

				// Apply default highlight state if pre-enabled via data attribute.
				if ( el.dataset.areasHighlighted === '1' ) {
					highlightBtn.setAttribute( 'aria-pressed', 'true' );
					polys.forEach( function ( poly, idx ) {
						poly.setStyle( { fillOpacity: areas[ idx ].fillOpacity } );
					} );
				}
			}
		}

		// Full-window toggle (CSS position:fixed, no browser API).
		var fwBtn = toolbar.querySelector( '.bmg-toolbar-btn--fullwindow' );
		var inFullWindow = false;

		function enterFullWindow() {
			inFullWindow = true;
			layoutEl.classList.add( 'bmg-map-layout--full-window' );
			document.body.style.overflow = 'hidden';
			if ( fwBtn ) {
				fwBtn.setAttribute( 'aria-pressed', 'true' );
				fwBtn.setAttribute( 'aria-label', 'Exit full window' );
				fwBtn.title = 'Exit full window';
			}
			setTimeout( function () { map.invalidateSize(); }, 50 );
			document.addEventListener( 'keydown', onFwKey );
		}

		function exitFullWindow() {
			inFullWindow = false;
			layoutEl.classList.remove( 'bmg-map-layout--full-window' );
			document.body.style.overflow = '';
			if ( fwBtn ) {
				fwBtn.setAttribute( 'aria-pressed', 'false' );
				fwBtn.setAttribute( 'aria-label', 'Fill window' );
				fwBtn.title = 'Fill window';
			}
			setTimeout( function () { map.invalidateSize(); }, 50 );
			document.removeEventListener( 'keydown', onFwKey );
		}

		function onFwKey( e ) {
			if ( e.key === 'Escape' ) exitFullWindow();
		}

		if ( fwBtn ) {
			fwBtn.addEventListener( 'click', function () {
				inFullWindow ? exitFullWindow() : enterFullWindow();
			} );
		}

		// Native fullscreen toggle.
		var fsBtn     = toolbar.querySelector( '.bmg-toolbar-btn--fullscreen' );
		var fsExitBtn = wrapperEl.querySelector( '.bmg-fs-exit-btn' );

		if ( fsBtn ) {
			fsBtn.addEventListener( 'click', function () {
				if ( ! document.fullscreenElement ) {
					( layoutEl.requestFullscreen || layoutEl.webkitRequestFullscreen
						|| function () {} ).call( layoutEl );
				} else {
					( document.exitFullscreen || document.webkitExitFullscreen
						|| function () {} ).call( document );
				}
			} );

			function onFsChange() {
				var isFs = !! document.fullscreenElement;
				layoutEl.classList.toggle( 'bmg-map-layout--fullscreen', isFs );
				fsBtn.setAttribute( 'aria-pressed', isFs ? 'true' : 'false' );
				fsBtn.setAttribute( 'aria-label', isFs ? 'Exit fullscreen' : 'Enter fullscreen' );
				fsBtn.title = isFs ? 'Exit fullscreen' : 'Enter fullscreen';
				setTimeout( function () { map.invalidateSize(); }, 50 );
			}
			document.addEventListener( 'fullscreenchange',       onFsChange );
			document.addEventListener( 'webkitfullscreenchange', onFsChange );
		}

		// Shared exit button — exits whichever mode is active.
		if ( fsExitBtn ) {
			fsExitBtn.addEventListener( 'click', function () {
				if ( document.fullscreenElement ) {
					( document.exitFullscreen || document.webkitExitFullscreen
						|| function () {} ).call( document );
				} else if ( inFullWindow ) {
					exitFullWindow();
				}
			} );
		}
	}

	// ------------------------------------------------------------------
	// Minimal HTML escape helpers (for title/color; description is
	// already server-sanitised)
	// ------------------------------------------------------------------

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function escAttr( str ) {
		return escHtml( str ).replace( /'/g, '&#039;' );
	}

	// ------------------------------------------------------------------
	// Boot — separated into a function so it can be deferred safely if
	// the script somehow executes before the DOM is ready (e.g. when
	// Elementor's asset optimisation moves footer scripts to <head>).
	// ------------------------------------------------------------------

	function boot() {
		// Scan containers already in the DOM (normal shortcode / block / SSR).
		document.querySelectorAll( '.bmg-map-container' ).forEach( initMap );

		// MutationObserver: pick up containers inserted after boot — handles
		// the Elementor editor (which re-renders widgets via AJAX) and any
		// other JS-driven page builder that adds content dynamically.
		if ( window.MutationObserver && ( document.body || document.documentElement ) ) {
			new MutationObserver( function ( mutations ) {
				mutations.forEach( function ( mutation ) {
					mutation.addedNodes.forEach( function ( node ) {
						if ( node.nodeType !== 1 ) return; // text / comment nodes
						// The node itself might be the container.
						if ( node.classList && node.classList.contains( 'bmg-map-container' ) ) {
							initMap( node );
						}
						// Or it may contain containers deeper in the tree.
						node.querySelectorAll( '.bmg-map-container' ).forEach( initMap );
					} );
				} );
			} ).observe( document.body || document.documentElement, { childList: true, subtree: true } );
		}
	}

	// If the DOM is still being parsed, defer until DOMContentLoaded.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

	// window.load fires after every resource (including async/deferred scripts)
	// has finished loading.  If Leaflet arrived after DOMContentLoaded — which
	// can happen when a caching plugin adds async/defer to ALL scripts — this
	// second boot() call will find any still-uninitialised containers (those
	// where the earlier call returned early because L was undefined) and
	// initialise them now.  The bmgReady guard in initMap() makes this safe.
	window.addEventListener( 'load', boot );

	// ------------------------------------------------------------------
	// Elementor integration
	//
	// elementorFrontend.hooks fires 'frontend/element_ready' after every
	// widget render — both on the live frontend and in the editor preview.
	//
	// We intentionally do NOT force a re-init here, even in edit mode.
	// Style-only changes (colour, typography, border) use Elementor's CSS
	// injection path and do NOT replace the DOM element, so the existing
	// Leaflet map is untouched and markers remain visible.  Content changes
	// (different map ID, list position) do replace the element; the
	// MutationObserver already catches the new node and calls initMap.
	//
	// This hook therefore acts only as a belt-and-braces backup: if for any
	// reason the MutationObserver missed the element, initMap is tried here.
	// The bmgReady guard makes repeated calls a safe no-op.
	// ------------------------------------------------------------------

	function onElementorFrontendReady() {
		if ( ! window.elementorFrontend || ! window.elementorFrontend.hooks ) return;

		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/bmg-interactive-map.default',
			function ( $scope ) {
				$scope[ 0 ].querySelectorAll( '.bmg-map-container' ).forEach( initMap );
			}
		);
	}

	if ( window.elementorFrontend ) {
		onElementorFrontendReady();
	} else {
		document.addEventListener( 'elementor/frontend/init', onElementorFrontendReady );
	}

} )();
