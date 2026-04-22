<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers the "bmg_map" custom post type.
 *
 * Each map post holds:
 *  - Title         → the map's display name
 *  - Featured image → the map background image
 *  - Content       → optional description
 *
 * Cascade behaviour:
 *  - Trashing a map    → trashes all its locations.
 *  - Restoring a map   → restores all its locations.
 *  - Permanently deleting a map → permanently deletes all its locations.
 */
class BMG_Map_CPT {

	public static function init(): void {
		add_action( 'init',              [ __CLASS__, 'register_post_type' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
		add_action( 'add_meta_boxes',    [ __CLASS__, 'add_meta_boxes' ] );
		add_action( 'save_post',         [ __CLASS__, 'save_meta' ] );

		// Cascade operations to child locations.
		add_action( 'wp_trash_post',     [ __CLASS__, 'trash_locations' ] );
		add_action( 'untrash_post',      [ __CLASS__, 'untrash_locations' ] );
		add_action( 'before_delete_post', [ __CLASS__, 'delete_locations' ] );
	}

	// -------------------------------------------------------------------------
	// Post type registration
	// -------------------------------------------------------------------------

	public static function register_post_type(): void {
		$labels = [
			'name'               => __( 'Maps', 'bmg-interactive-map' ),
			'singular_name'      => __( 'Map', 'bmg-interactive-map' ),
			'add_new_item'       => __( 'Add New Map', 'bmg-interactive-map' ),
			'edit_item'          => __( 'Edit Map', 'bmg-interactive-map' ),
			'new_item'           => __( 'New Map', 'bmg-interactive-map' ),
			'view_item'          => __( 'View Map', 'bmg-interactive-map' ),
			'search_items'       => __( 'Search Maps', 'bmg-interactive-map' ),
			'not_found'          => __( 'No maps found.', 'bmg-interactive-map' ),
			'not_found_in_trash' => __( 'No maps found in trash.', 'bmg-interactive-map' ),
			'menu_name'          => __( 'Interactive Maps', 'bmg-interactive-map' ),
		];

		register_post_type( 'bmg_map', [
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-location-alt',
			'supports'            => [ 'title', 'thumbnail' ],
			'has_archive'         => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'show_in_rest'        => true,
		] );
	}

	// -------------------------------------------------------------------------
	// Zoom meta box
	// -------------------------------------------------------------------------

	public static function add_meta_boxes(): void {
		add_meta_box(
			'bmg_map_zoom',
			__( 'Zoom Settings', 'bmg-interactive-map' ),
			[ __CLASS__, 'render_meta_box' ],
			'bmg_map',
			'side',
			'default'
		);
		add_meta_box(
			'bmg_map_tileset',
			__( 'Tileset', 'bmg-interactive-map' ),
			[ __CLASS__, 'render_tileset_meta_box' ],
			'bmg_map',
			'side',
			'default'
		);
	}

	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'bmg_map_zoom_save', 'bmg_map_zoom_nonce' );

		$min_zoom = get_post_meta( $post->ID, '_bmg_map_min_zoom', true );
		$max_zoom = get_post_meta( $post->ID, '_bmg_map_max_zoom', true );
		$s        = BMG_Settings::get();
		?>
		<p style="margin-bottom:6px;">
			<label for="bmg_map_min_zoom"><strong><?php esc_html_e( 'Min Zoom', 'bmg-interactive-map' ); ?></strong></label><br>
			<input type="number" id="bmg_map_min_zoom" name="bmg_map_min_zoom"
				value="<?php echo esc_attr( $min_zoom ); ?>"
				min="-5" max="0" style="width:70px;"
				placeholder="<?php echo esc_attr( $s['min_zoom'] ); ?>" />
		</p>
		<p style="margin-bottom:6px;">
			<label for="bmg_map_max_zoom"><strong><?php esc_html_e( 'Max Zoom', 'bmg-interactive-map' ); ?></strong></label><br>
			<input type="number" id="bmg_map_max_zoom" name="bmg_map_max_zoom"
				value="<?php echo esc_attr( $max_zoom ); ?>"
				min="1" max="5" style="width:70px;"
				placeholder="<?php echo esc_attr( $s['max_zoom'] ); ?>" />
		</p>
		<p class="description"><?php esc_html_e( 'Leave blank to use the global default.', 'bmg-interactive-map' ); ?></p>
		<?php
	}

	public static function render_tileset_meta_box( WP_Post $post ): void {
		$status     = get_post_meta( $post->ID, '_bmg_tileset_status', true );
		$tileset_img= (int) get_post_meta( $post->ID, '_bmg_tileset_image_id', true );
		$current_img= (int) get_post_thumbnail_id( $post->ID );
		$is_ready   = $status === 'ready';
		$is_stale   = $is_ready && $tileset_img && $tileset_img !== $current_img;

		$s       = BMG_Settings::get();
		$raw_min = get_post_meta( $post->ID, '_bmg_map_min_zoom', true );
		$min_zoom= $raw_min !== '' ? (int) $raw_min : (int) $s['min_zoom'];

		if ( $status === 'ready' ) {
			$status_text = 'Ready';
		} elseif ( $status === 'generating' ) {
			$status_text = 'Generating…';
		} elseif ( $status === 'error' ) {
			$status_text = 'Error — see browser console';
		} else {
			$status_text = 'No tileset generated.';
		}

		$generate_label = $is_ready ? 'Regenerate Tileset' : 'Generate Tileset';
		?>
		<script>
		window.bmgTilesetMeta = {
			ajaxUrl:       <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
			generateNonce: <?php echo wp_json_encode( wp_create_nonce( 'bmg_generate_tiles_' . $post->ID ) ); ?>,
			deleteNonce:   <?php echo wp_json_encode( wp_create_nonce( 'bmg_delete_tileset_' . $post->ID ) ); ?>,
			mapId:         <?php echo absint( $post->ID ); ?>,
			minZoom:       <?php echo (int) $min_zoom; ?>,
		};
		</script>
		<div id="bmg-tileset-box">
			<p style="margin-bottom:4px;">
				<strong><?php esc_html_e( 'Status:', 'bmg-interactive-map' ); ?></strong>
				<span id="bmg-tileset-status"><?php echo esc_html( $status_text ); ?></span>
			</p>
			<?php if ( $is_stale ) : ?>
			<p id="bmg-tileset-stale" style="color:#b45309;margin-bottom:6px;">
				<?php esc_html_e( 'Featured image has changed — regenerate to update.', 'bmg-interactive-map' ); ?>
			</p>
			<?php else : ?>
			<p id="bmg-tileset-stale" style="display:none;color:#b45309;margin-bottom:6px;">
				<?php esc_html_e( 'Featured image has changed — regenerate to update.', 'bmg-interactive-map' ); ?>
			</p>
			<?php endif; ?>
			<p style="margin-bottom:6px;">
				<button id="bmg-tileset-generate" type="button" class="button">
					<?php echo esc_html( $generate_label ); ?>
				</button>
			</p>
			<progress id="bmg-tileset-progress" style="display:none;width:100%;margin-bottom:6px;" value="0" max="1"></progress>
			<p style="margin-bottom:0;">
				<button id="bmg-tileset-delete" type="button" class="button button-link-delete"
					<?php echo $is_ready ? '' : 'style="display:none;"'; ?>>
					<?php esc_html_e( 'Delete Tileset', 'bmg-interactive-map' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	public static function save_meta( int $post_id ): void {
		if (
			! isset( $_POST['bmg_map_zoom_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmg_map_zoom_nonce'] ) ), 'bmg_map_zoom_save' )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$min_raw = trim( wp_unslash( $_POST['bmg_map_min_zoom'] ?? '' ) );
		if ( $min_raw === '' ) {
			delete_post_meta( $post_id, '_bmg_map_min_zoom' );
		} else {
			update_post_meta( $post_id, '_bmg_map_min_zoom', max( -5, min( 0, (int) $min_raw ) ) );
		}

		$max_raw = trim( wp_unslash( $_POST['bmg_map_max_zoom'] ?? '' ) );
		if ( $max_raw === '' ) {
			delete_post_meta( $post_id, '_bmg_map_max_zoom' );
		} else {
			update_post_meta( $post_id, '_bmg_map_max_zoom', max( 1, min( 5, (int) $max_raw ) ) );
		}
	}

	/**
	 * Enqueue the admin media uploader script so the featured-image meta box works
	 * properly on the bmg_map edit screen.
	 */
	public static function enqueue_admin_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'bmg_map' ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script(
			'bmg-tileset-admin',
			BMG_MAP_PLUGIN_URL . 'admin/js/tileset-admin.js',
			[],
			BMG_MAP_VERSION,
			true
		);
	}

	// -------------------------------------------------------------------------
	// Cascade helpers
	// -------------------------------------------------------------------------

	/** Move all locations belonging to $post_id to trash. */
	public static function trash_locations( int $post_id ): void {
		if ( get_post_type( $post_id ) !== 'bmg_map' ) {
			return;
		}
		foreach ( self::get_location_ids( $post_id, 'publish' ) as $loc_id ) {
			wp_trash_post( $loc_id );
		}
	}

	/** Restore all locations that were trashed alongside this map. */
	public static function untrash_locations( int $post_id ): void {
		if ( get_post_type( $post_id ) !== 'bmg_map' ) {
			return;
		}
		foreach ( self::get_location_ids( $post_id, 'trash' ) as $loc_id ) {
			wp_untrash_post( $loc_id );
		}
	}

	/** Permanently delete all locations belonging to $post_id. */
	public static function delete_locations( int $post_id ): void {
		if ( get_post_type( $post_id ) !== 'bmg_map' ) {
			return;
		}
		foreach ( self::get_location_ids( $post_id, 'any' ) as $loc_id ) {
			wp_delete_post( $loc_id, true );
		}
	}

	/** Return location IDs for a given map, filtered by status. */
	private static function get_location_ids( int $map_id, string $status ): array {
		return get_posts( [
			'post_type'      => 'bmg_location',
			'post_status'    => $status,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'   => '_bmg_map_id',
					'value' => $map_id,
					'type'  => 'NUMERIC',
				],
			],
		] );
	}
}
