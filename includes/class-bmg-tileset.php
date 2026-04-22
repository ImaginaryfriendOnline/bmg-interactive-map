<?php
defined( 'ABSPATH' ) || exit;

class BMG_Tileset {

	const TILE_SIZE       = 256;
	const MAX_NATIVE_ZOOM = 0;

	public static function init(): void {
		add_action( 'wp_ajax_bmg_generate_tiles',  [ __CLASS__, 'ajax_generate_tiles' ] );
		add_action( 'wp_ajax_bmg_tileset_status',  [ __CLASS__, 'ajax_tileset_status' ] );
		add_action( 'wp_ajax_bmg_delete_tileset',  [ __CLASS__, 'ajax_delete_tileset' ] );
		add_action( 'before_delete_post',           [ __CLASS__, 'delete_tiles_for_map' ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: generate one zoom level per call
	// -------------------------------------------------------------------------

	public static function ajax_generate_tiles(): void {
		$map_id = absint( $_POST['map_id'] ?? 0 );

		if (
			! $map_id ||
			! isset( $_POST['nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'bmg_generate_tiles_' . $map_id ) ||
			! current_user_can( 'edit_post', $map_id )
		) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$thumb_id = (int) get_post_thumbnail_id( $map_id );
		if ( ! $thumb_id ) {
			wp_send_json_error( 'No featured image set for this map.' );
		}

		$file = get_attached_file( $thumb_id );
		if ( ! $file || ! file_exists( $file ) ) {
			wp_send_json_error( 'Image file not found on disk.' );
		}

		$mime = get_post_mime_type( $thumb_id );
		$src  = self::load_gd_image( $file, $mime );
		if ( ! $src ) {
			wp_send_json_error( 'Could not load image (unsupported format or GD not available).' );
		}

		$W = imagesx( $src );
		$H = imagesy( $src );

		$s        = BMG_Settings::get();
		$raw_min  = get_post_meta( $map_id, '_bmg_map_min_zoom', true );
		$zoom_min = $raw_min !== '' ? (int) $raw_min : (int) $s['min_zoom'];

		$z = isset( $_POST['zoom'] ) ? (int) $_POST['zoom'] : $zoom_min;

		$upload   = wp_upload_dir();
		$base_dir = trailingslashit( $upload['basedir'] ) . 'bmg-tiles/' . $map_id;
		$base_url = trailingslashit( $upload['baseurl'] ) . 'bmg-tiles/' . $map_id;

		// Mark as generating on the first call.
		if ( $z === $zoom_min ) {
			update_post_meta( $map_id, '_bmg_tileset_status', 'generating' );
			// Remove tiles from a previous generation so there are no stale files.
			self::remove_tile_dir( $map_id );
		}

		$err = self::generate_zoom_level( $src, $W, $H, $z, $base_dir );
		imagedestroy( $src );

		if ( $err ) {
			update_post_meta( $map_id, '_bmg_tileset_status', 'error' );
			wp_send_json_error( $err );
		}

		$next = $z + 1;

		if ( $next <= self::MAX_NATIVE_ZOOM ) {
			wp_send_json_success( [
				'status'    => 'progress',
				'next_zoom' => $next,
				'zoom_min'  => $zoom_min,
			] );
		}

		// All zoom levels done.
		update_post_meta( $map_id, '_bmg_tileset_status',   'ready' );
		update_post_meta( $map_id, '_bmg_tileset_zoom_min', $zoom_min );
		update_post_meta( $map_id, '_bmg_tileset_image_id', $thumb_id );
		update_post_meta( $map_id, '_bmg_tileset_url_base', trailingslashit( $base_url ) );

		wp_send_json_success( [
			'status'   => 'complete',
			'url_base' => trailingslashit( $base_url ),
			'zoom_min' => $zoom_min,
		] );
	}

	// -------------------------------------------------------------------------
	// AJAX: current tileset status
	// -------------------------------------------------------------------------

	public static function ajax_tileset_status(): void {
		$map_id = absint( $_POST['map_id'] ?? 0 );

		if (
			! $map_id ||
			! isset( $_POST['nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'bmg_tileset_status_' . $map_id ) ||
			! current_user_can( 'edit_post', $map_id )
		) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		wp_send_json_success( [
			'status'       => get_post_meta( $map_id, '_bmg_tileset_status', true ),
			'tileset_img'  => (int) get_post_meta( $map_id, '_bmg_tileset_image_id', true ),
			'current_img'  => (int) get_post_thumbnail_id( $map_id ),
		] );
	}

	// -------------------------------------------------------------------------
	// AJAX: delete tileset
	// -------------------------------------------------------------------------

	public static function ajax_delete_tileset(): void {
		$map_id = absint( $_POST['map_id'] ?? 0 );

		if (
			! $map_id ||
			! isset( $_POST['nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'bmg_delete_tileset_' . $map_id ) ||
			! current_user_can( 'edit_post', $map_id )
		) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		self::remove_tile_dir( $map_id );
		delete_post_meta( $map_id, '_bmg_tileset_status' );
		delete_post_meta( $map_id, '_bmg_tileset_zoom_min' );
		delete_post_meta( $map_id, '_bmg_tileset_image_id' );
		delete_post_meta( $map_id, '_bmg_tileset_url_base' );

		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// Cleanup on map deletion
	// -------------------------------------------------------------------------

	public static function delete_tiles_for_map( int $post_id ): void {
		if ( get_post_type( $post_id ) !== 'bmg_map' ) {
			return;
		}
		self::remove_tile_dir( $post_id );
	}

	// -------------------------------------------------------------------------
	// Staleness check helper (used by shortcode)
	// -------------------------------------------------------------------------

	public static function get_tileset_data( int $map_id ): ?array {
		$status   = get_post_meta( $map_id, '_bmg_tileset_status', true );
		$url_base = get_post_meta( $map_id, '_bmg_tileset_url_base', true );
		$img_id   = (int) get_post_meta( $map_id, '_bmg_tileset_image_id', true );
		$zoom_min = (int) get_post_meta( $map_id, '_bmg_tileset_zoom_min', true );

		if (
			$status !== 'ready' ||
			! $url_base ||
			$img_id !== (int) get_post_thumbnail_id( $map_id )
		) {
			return null;
		}

		return [
			'url'      => trailingslashit( $url_base ) . '{z}/{x}/{y}.jpg',
			'zoom_min' => $zoom_min,
		];
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private static function generate_zoom_level( $src, int $W, int $H, int $z, string $base_dir ): string {
		// At zoom z, each tile represents this many source image pixels.
		// For z=0: 256. For z=-1: 512. For z=-3: 2048.
		$ppt  = (int) round( self::TILE_SIZE / pow( 2, $z ) );
		$cols = (int) ceil( $W / $ppt );
		$rows = (int) ceil( $H / $ppt );

		for ( $tx = 0; $tx < $cols; $tx++ ) {
			for ( $tr = 0; $tr < $rows; $tr++ ) {
				// TMS convention: file y=0 is the image bottom row.
				$file_y = ( $rows - 1 ) - $tr;

				$src_x = $tx * $ppt;
				$src_y = $tr * $ppt;
				$src_w = min( $ppt, $W - $src_x );
				$src_h = min( $ppt, $H - $src_y );

				$tile = imagecreatetruecolor( self::TILE_SIZE, self::TILE_SIZE );
				if ( ! $tile ) {
					return 'imagecreatetruecolor() failed.';
				}

				// White background for partial edge tiles.
				$white = imagecolorallocate( $tile, 255, 255, 255 );
				imagefill( $tile, 0, 0, $white );

				imagecopyresampled(
					$tile, $src,
					0, 0,
					$src_x, $src_y,
					self::TILE_SIZE, self::TILE_SIZE,
					$src_w, $src_h
				);

				$tile_dir = $base_dir . '/' . $z . '/' . $tx;
				if ( ! wp_mkdir_p( $tile_dir ) ) {
					imagedestroy( $tile );
					return 'Could not create tile directory: ' . $tile_dir;
				}

				$tile_path = $tile_dir . '/' . $file_y . '.jpg';
				imagejpeg( $tile, $tile_path, 85 );
				imagedestroy( $tile );
			}
		}

		return '';
	}

	private static function load_gd_image( string $file, string $mime ) {
		if ( ! function_exists( 'imagecreatefromjpeg' ) ) {
			return null;
		}
		switch ( $mime ) {
			case 'image/jpeg':
				return imagecreatefromjpeg( $file );
			case 'image/png':
				return imagecreatefrompng( $file );
			case 'image/gif':
				return imagecreatefromgif( $file );
			case 'image/webp':
				return function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $file ) : null;
			default:
				return null;
		}
	}

	private static function remove_tile_dir( int $map_id ): void {
		$upload  = wp_upload_dir();
		$dir     = trailingslashit( $upload['basedir'] ) . 'bmg-tiles/' . $map_id;

		if ( ! is_dir( $dir ) ) {
			return;
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		if ( $wp_filesystem ) {
			$wp_filesystem->delete( $dir, true );
		}
	}
}
