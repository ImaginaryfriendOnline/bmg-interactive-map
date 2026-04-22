/* global bmgTilesetMeta */
( function () {
	'use strict';

	var meta = window.bmgTilesetMeta;
	if ( ! meta ) return;

	var box        = document.getElementById( 'bmg-tileset-box' );
	var statusEl   = document.getElementById( 'bmg-tileset-status' );
	var generateBtn= document.getElementById( 'bmg-tileset-generate' );
	var deleteBtn  = document.getElementById( 'bmg-tileset-delete' );
	var progress   = document.getElementById( 'bmg-tileset-progress' );
	var staleNote  = document.getElementById( 'bmg-tileset-stale' );

	if ( ! box ) return;

	function post( action, data, nonce ) {
		var body = new URLSearchParams( {
			action:  action,
			map_id:  meta.mapId,
			nonce:   nonce,
		} );
		Object.keys( data ).forEach( function ( k ) { body.set( k, data[ k ] ); } );
		return fetch( meta.ajaxUrl, { method: 'POST', body: body } ).then( function ( r ) { return r.json(); } );
	}

	function setStatus( text ) {
		if ( statusEl ) statusEl.textContent = text;
	}

	function runGenerate( zoom ) {
		post( 'bmg_generate_tiles', { zoom: zoom }, meta.generateNonce )
			.then( function ( res ) {
				if ( ! res.success ) {
					setStatus( 'Error: ' + ( res.data || 'unknown error' ) );
					generateBtn.disabled = false;
					if ( progress ) progress.style.display = 'none';
					return;
				}
				var d = res.data;
				if ( d.status === 'progress' ) {
					var done    = d.next_zoom - d.zoom_min;
					var total   = 1 - d.zoom_min; // levels from zoom_min to 0 inclusive
					if ( progress ) {
						progress.max   = total;
						progress.value = done;
					}
					runGenerate( d.next_zoom );
				} else {
					// complete
					setStatus( 'Ready' );
					generateBtn.disabled = false;
					generateBtn.textContent = 'Regenerate Tileset';
					if ( deleteBtn ) deleteBtn.style.display = '';
					if ( staleNote ) staleNote.style.display = 'none';
					if ( progress ) progress.style.display = 'none';
				}
			} )
			.catch( function () {
				setStatus( 'Network error — please try again.' );
				generateBtn.disabled = false;
				if ( progress ) progress.style.display = 'none';
			} );
	}

	if ( generateBtn ) {
		generateBtn.addEventListener( 'click', function () {
			generateBtn.disabled = true;
			setStatus( 'Generating…' );
			if ( progress ) {
				progress.value        = 0;
				progress.style.display = '';
			}
			runGenerate( meta.minZoom );
		} );
	}

	if ( deleteBtn ) {
		deleteBtn.addEventListener( 'click', function () {
			if ( ! confirm( 'Delete the tileset for this map? The map will fall back to the full image.' ) ) {
				return;
			}
			deleteBtn.disabled = true;
			post( 'bmg_delete_tileset', {}, meta.deleteNonce )
				.then( function ( res ) {
					if ( res.success ) {
						setStatus( 'No tileset generated.' );
						deleteBtn.style.display = 'none';
						if ( staleNote ) staleNote.style.display = 'none';
						generateBtn.textContent  = 'Generate Tileset';
					}
					deleteBtn.disabled = false;
				} )
				.catch( function () {
					deleteBtn.disabled = false;
				} );
		} );
	}
}() );
