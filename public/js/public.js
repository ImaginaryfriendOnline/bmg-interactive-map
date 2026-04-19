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
	// Initialise one map container
	// ------------------------------------------------------------------

	function initMap( el ) {
		// Guard against double-initialisation (e.g. Elementor re-renders the widget).
		if ( el.dataset.bmgReady ) return;

		// If Leaflet has not loaded yet, bail WITHOUT marking the element ready
		// so the window.load retry can pick it up once Leaflet arrives.
		if ( typeof L === 'undefined' ) return;

		el.dataset.bmgReady = '1';

		var imageUrl = el.dataset.image;
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

			// Per-map zoom position and zoom levels override global settings.
			var zoomPos = el.dataset.zoomPosition || ZOOM_POSITION;
			var minZoom = el.dataset.minZoom !== undefined && el.dataset.minZoom !== '' ? Number( el.dataset.minZoom ) : MIN_ZOOM;
			var maxZoom = el.dataset.maxZoom !== undefined && el.dataset.maxZoom !== '' ? Number( el.dataset.maxZoom ) : MAX_ZOOM;

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
			L.imageOverlay( imageUrl, bounds ).addTo( map );

			// Only fit immediately if the container has non-zero dimensions.
			// Calling fitBounds on a 0×0 container corrupts Leaflet's internal zoom
			// state; the delayed retries below recover once the container has a size.
			if ( el.offsetWidth > 0 && el.offsetHeight > 0 ) {
				map.fitBounds( bounds, { padding: [ 0, 0 ] } );
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
				if ( ! userInteracted && el.offsetWidth > 0 && el.offsetHeight > 0 ) {
					map.fitBounds( bounds, { padding: [ 0, 0 ] } );
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
					if ( ! userInteracted && el.offsetWidth > 0 && el.offsetHeight > 0 ) {
						map.fitBounds( bounds, { padding: [ 0, 0 ] } );
					}
				} );
				wrapperObserver.observe( wrapper || el );
			}

			// Prevent panning too far outside the image.
			map.setMaxBounds( [
				[ -H * 0.1, -W * 0.1 ],
				[  H * 1.1,  W * 1.1 ],
			] );

			var markers = [];

			locations.forEach( function ( loc ) {
				var ll    = toLatLng( loc.x, loc.y, W, H );
				var color = escAttr( loc.color || DEFAULT_COLOR );
				var label = escAttr( loc.title );

				var icon = L.divIcon( {
					className : 'bmg-map-marker-icon',
					iconSize  : [ 18, 18 ],
					iconAnchor: [ 9, 9 ],
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
					maxWidth : 300,
					className: 'bmg-leaflet-popup',
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

			areas.forEach( function ( area ) {
				var latlngs = area.points.map( function ( p ) {
					return [ H - ( p.y / 100 ) * H, ( p.x / 100 ) * W ];
				} );

				var poly = L.polygon( latlngs, {
					color      : area.color,
					fillColor  : area.fillColor,
					fillOpacity: area.fillOpacity,
					weight     : 2,
				} ).addTo( map );

				poly.on( 'click', function ( e ) {
					L.DomEvent.stopPropagation( e );
					var popupHtml = '<h3 class="bmg-popup-title">' + escHtml( area.title ) + '</h3>';
					if ( area.description ) {
						popupHtml += '<div class="bmg-popup-body">' + area.description + '</div>';
					}
					L.popup( { className: 'bmg-leaflet-popup', closeButton: true } )
						.setLatLng( e.latlng )
						.setContent( popupHtml )
						.openOn( map );
				} );
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

		// For floating lists with more than 10 locations: cap the panel height
		// to exactly 10 items (plus the search bar if present) so it stays
		// compact and scrollable rather than covering half the map.
		var isFloating = layoutEl.className.indexOf( 'bmg-map-layout--list-float' ) !== -1;
		if ( isFloating && markers.length > 10 && items.length ) {
			var searchWrap  = listEl.querySelector( '.bmg-location-search-wrap' );
			var searchH     = searchWrap ? searchWrap.offsetHeight : 0;
			var itemH       = items[ 0 ].offsetHeight || 36; // 36 px fallback
			listEl.style.maxHeight = ( searchH + itemH * 10 ) + 'px';
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
