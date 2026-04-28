<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers the "bmg_location" custom post type and its meta boxes.
 *
 * Each location post holds:
 *  - Title       → location label shown in the popup
 *  - Content     → description shown in the popup
 *  - _bmg_map_id → ID of the parent bmg_map post
 *  - _bmg_loc_x  → horizontal position as a percentage (0–100)
 *  - _bmg_loc_y  → vertical position as a percentage (0–100)
 *  - _bmg_loc_color → marker accent colour (hex, optional)
 */
class BMG_Location_CPT {

	public static function init(): void {
		add_action( 'init',            [ __CLASS__, 'register_post_type' ] );
		add_action( 'add_meta_boxes',  [ __CLASS__, 'add_meta_boxes' ] );
		add_action( 'save_post',       [ __CLASS__, 'save_meta' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
		add_action( 'wp_ajax_bmg_get_map_image',  [ __CLASS__, 'ajax_get_map_image' ] );
		add_filter( 'manage_bmg_location_posts_columns',       [ __CLASS__, 'add_map_column' ] );
		add_action( 'manage_bmg_location_posts_custom_column', [ __CLASS__, 'render_map_column' ], 10, 2 );
		add_action( 'restrict_manage_posts', [ __CLASS__, 'render_list_filters' ] );
		add_action( 'pre_get_posts',         [ __CLASS__, 'apply_list_filters'  ] );
		add_filter( 'bulk_actions-edit-bmg_location',        [ __CLASS__, 'register_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-bmg_location', [ __CLASS__, 'handle_bulk_actions'  ], 10, 3 );
		add_action( 'admin_notices',                         [ __CLASS__, 'bulk_action_notice'   ] );
	}

	// -------------------------------------------------------------------------
	// Post type registration
	// -------------------------------------------------------------------------

	public static function register_post_type(): void {
		$labels = [
			'name'               => __( 'Locations', 'bmg-interactive-map' ),
			'singular_name'      => __( 'Location', 'bmg-interactive-map' ),
			'add_new_item'       => __( 'Add New Location', 'bmg-interactive-map' ),
			'edit_item'          => __( 'Edit Location', 'bmg-interactive-map' ),
			'new_item'           => __( 'New Location', 'bmg-interactive-map' ),
			'search_items'       => __( 'Search Locations', 'bmg-interactive-map' ),
			'not_found'          => __( 'No locations found.', 'bmg-interactive-map' ),
			'not_found_in_trash' => __( 'No locations found in trash.', 'bmg-interactive-map' ),
			'menu_name'          => __( 'Locations', 'bmg-interactive-map' ),
		];

		register_post_type( 'bmg_location', [
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'edit.php?post_type=bmg_map',
			'supports'        => [ 'title', 'editor' ],
			'has_archive'     => false,
			'rewrite'         => false,
			'capability_type' => 'post',
			'show_in_rest'    => true,
		] );
	}

	// -------------------------------------------------------------------------
	// Meta boxes
	// -------------------------------------------------------------------------

	public static function add_meta_boxes(): void {
		add_meta_box(
			'bmg_location_settings',
			__( 'Location Settings', 'bmg-interactive-map' ),
			[ __CLASS__, 'render_meta_box' ],
			'bmg_location',
			'normal',
			'high'
		);
	}

	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'bmg_location_save', 'bmg_location_nonce' );

		$map_id        = (int) get_post_meta( $post->ID, '_bmg_map_id', true );
		$x             = get_post_meta( $post->ID, '_bmg_loc_x', true );
		$y             = get_post_meta( $post->ID, '_bmg_loc_y', true );
		$default_color = BMG_Settings::get()['default_color'];
		$color         = get_post_meta( $post->ID, '_bmg_loc_color', true ) ?: $default_color;
		// Fetch published and draft maps for the dropdown.
		$maps = get_posts( [
			'post_type'      => 'bmg_map',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		// Determine current map image URL and dimensions (if a map is already selected).
		$map_image_url = '';
		$map_image_w   = 0;
		$map_image_h   = 0;
		if ( $map_id ) {
			$map_image_url = self::get_map_image_url( $map_id );
			if ( $map_image_url ) {
				$thumb_id    = get_post_thumbnail_id( $map_id );
				$img_meta    = wp_get_attachment_metadata( $thumb_id );
				$map_image_w = ! empty( $img_meta['width'] )  ? (int) $img_meta['width']  : 0;
				$map_image_h = ! empty( $img_meta['height'] ) ? (int) $img_meta['height'] : 0;
			}
		}

		// Sibling locations for same map (shown as read-only overlays in the editor).
		$sibling_locations = [];
		if ( $map_id ) {
			$sibling_posts = get_posts( [
				'post_type'      => 'bmg_location',
				'post_status'    => [ 'publish', 'draft' ],
				'posts_per_page' => -1,
				'exclude'        => [ $post->ID ],
				'meta_query'     => [ [ 'key' => '_bmg_map_id', 'value' => $map_id, 'type' => 'NUMERIC' ] ],
			] );
			foreach ( $sibling_posts as $sp ) {
				$sx = get_post_meta( $sp->ID, '_bmg_loc_x', true );
				$sy = get_post_meta( $sp->ID, '_bmg_loc_y', true );
				if ( $sx === '' || $sy === '' ) {
					continue;
				}
				$sibling_locations[] = [
					'title' => get_the_title( $sp ),
					'x'     => (float) $sx,
					'y'     => (float) $sy,
					'color' => get_post_meta( $sp->ID, '_bmg_loc_color', true ) ?: $default_color,
				];
			}
		}

		$visible = get_post_meta( $post->ID, '_bmg_hidden', true ) !== '1';
		?>
		<div class="bmg-meta-wrap">

			<!-- Map selector -->
			<p>
				<label for="bmg_map_id"><strong><?php esc_html_e( 'Parent Map', 'bmg-interactive-map' ); ?></strong></label><br>
				<select id="bmg_map_id" name="bmg_map_id" style="width:100%;max-width:400px;">
					<option value=""><?php esc_html_e( '— Select a map —', 'bmg-interactive-map' ); ?></option>
					<?php foreach ( $maps as $map ) : ?>
						<option value="<?php echo esc_attr( $map->ID ); ?>" <?php selected( $map_id, $map->ID ); ?>>
							<?php echo esc_html( $map->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<!-- Coordinate inputs (set via click or manually) -->
			<p>
				<label><strong><?php esc_html_e( 'Position', 'bmg-interactive-map' ); ?></strong>
					<span class="description"><?php esc_html_e( 'Click the map image below to set, or enter values manually (0–100).', 'bmg-interactive-map' ); ?></span>
				</label>
			</p>
			<p style="display:flex;gap:16px;align-items:center;">
				<label for="bmg_loc_x">X&nbsp;%
					<input type="number" id="bmg_loc_x" name="bmg_loc_x"
						value="<?php echo esc_attr( $x ); ?>"
						min="0" max="100" step="0.01"
						style="width:80px;" />
				</label>
				<label for="bmg_loc_y">Y&nbsp;%
					<input type="number" id="bmg_loc_y" name="bmg_loc_y"
						value="<?php echo esc_attr( $y ); ?>"
						min="0" max="100" step="0.01"
						style="width:80px;" />
				</label>
				<label for="bmg_loc_color"><?php esc_html_e( 'Marker colour', 'bmg-interactive-map' ); ?>
					<input type="color" id="bmg_loc_color" name="bmg_loc_color"
						value="<?php echo esc_attr( $color ); ?>" />
				</label>
			</p>

		<!-- Visibility -->
		<p>
			<label>
				<input type="checkbox" name="bmg_location_visible" value="1" <?php checked( $visible ); ?> />
				<?php esc_html_e( 'Show in page view', 'bmg-interactive-map' ); ?>
			</label>
		</p>

		<!-- Popup preview -->
		<p>
			<button type="button" id="bmg-preview-toggle" class="button"><?php esc_html_e( 'Show Popup Preview', 'bmg-interactive-map' ); ?></button>
		</p>
		<div id="bmg-popup-preview-wrap" style="display:none;margin-bottom:12px;">
			<div class="bmg-admin-popup-preview">
				<h3 class="bmg-popup-title"><?php echo esc_html( $post->post_title ); ?></h3>
				<?php if ( $post->post_content ) : ?>
					<div class="bmg-popup-body"><?php echo wp_kses_post( wpautop( do_shortcode( $post->post_content ) ) ); ?></div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Visual map editor (Leaflet CRS.Simple) -->
			<div id="bmg-map-editor-wrap" style="margin-top:12px;">
				<?php if ( $map_image_url ) : ?>
					<p class="description"><?php esc_html_e( 'Click the map to place the marker. Drag to reposition.', 'bmg-interactive-map' ); ?></p>
					<div id="bmg-map-editor" class="bmg-admin-map-editor"></div>
				<?php else : ?>
					<p id="bmg-no-map-notice" class="description">
						<?php esc_html_e( 'Select a map above to enable the visual position editor.', 'bmg-interactive-map' ); ?>
					</p>
				<?php endif; ?>
			</div>

		</div>

		<script>
		window.bmgLocationMeta = {
			ajaxUrl         : <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
			nonce           : <?php echo wp_json_encode( wp_create_nonce( 'bmg_get_map_image' ) ); ?>,
			postId          : <?php echo wp_json_encode( $post->ID ); ?>,
			imageUrl        : <?php echo wp_json_encode( $map_image_url ); ?>,
			imageWidth      : <?php echo wp_json_encode( $map_image_w ); ?>,
			imageHeight     : <?php echo wp_json_encode( $map_image_h ); ?>,
			x               : <?php echo wp_json_encode( $x !== '' ? (float) $x : null ); ?>,
			y               : <?php echo wp_json_encode( $y !== '' ? (float) $y : null ); ?>,
			color           : <?php echo wp_json_encode( $color ); ?>,
			siblingLocations: <?php echo wp_json_encode( $sibling_locations ); ?>,
		};
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save meta
	// -------------------------------------------------------------------------

	public static function save_meta( int $post_id ): void {
		if (
			! isset( $_POST['bmg_location_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmg_location_nonce'] ) ), 'bmg_location_save' )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['bmg_map_id'] ) ) {
			update_post_meta( $post_id, '_bmg_map_id', absint( $_POST['bmg_map_id'] ) );
		}

		if ( isset( $_POST['bmg_loc_x'] ) ) {
			update_post_meta( $post_id, '_bmg_loc_x', min( 100.0, max( 0.0, (float) $_POST['bmg_loc_x'] ) ) );
		}

		if ( isset( $_POST['bmg_loc_y'] ) ) {
			update_post_meta( $post_id, '_bmg_loc_y', min( 100.0, max( 0.0, (float) $_POST['bmg_loc_y'] ) ) );
		}

		if ( isset( $_POST['bmg_loc_color'] ) ) {
			// Allow only valid hex colours.
			$color = sanitize_hex_color( wp_unslash( $_POST['bmg_loc_color'] ) );
			if ( $color ) {
				update_post_meta( $post_id, '_bmg_loc_color', $color );
			}
		}

		if ( isset( $_POST['bmg_location_visible'] ) ) {
			delete_post_meta( $post_id, '_bmg_hidden' );
		} else {
			update_post_meta( $post_id, '_bmg_hidden', '1' );
		}
	}

	// -------------------------------------------------------------------------
	// Location listing — Parent Map column
	// -------------------------------------------------------------------------

	public static function add_map_column( array $columns ): array {
		// Insert after the title column.
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( $key === 'title' ) {
				$new['bmg_parent_map'] = __( 'Parent Map', 'bmg-interactive-map' );
				$new['bmg_page_view']  = __( 'Page View',  'bmg-interactive-map' );
			}
		}
		return $new;
	}

	public static function render_map_column( string $column, int $post_id ): void {
		if ( $column === 'bmg_page_view' ) {
			if ( get_post_meta( $post_id, '_bmg_hidden', true ) === '1' ) {
				echo '<span class="dashicons dashicons-hidden" style="color:#999;" title="' . esc_attr__( 'Hidden from page view', 'bmg-interactive-map' ) . '"></span>';
			} else {
				echo '<span class="dashicons dashicons-visibility" style="color:#46b450;" title="' . esc_attr__( 'Shown in page view', 'bmg-interactive-map' ) . '"></span>';
			}
			return;
		}

		if ( $column !== 'bmg_parent_map' ) {
			return;
		}
		$map_id = (int) get_post_meta( $post_id, '_bmg_map_id', true );
		if ( ! $map_id ) {
			echo '—';
			return;
		}
		$map = get_post( $map_id );
		if ( ! $map ) {
			echo '—';
			return;
		}
		printf(
			'<a href="%s">%s</a>',
			esc_url( get_edit_post_link( $map_id ) ),
			esc_html( $map->post_title )
		);
	}

	// -------------------------------------------------------------------------
	// Admin assets
	// -------------------------------------------------------------------------

	public static function enqueue_admin_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'bmg_location' ) {
			return;
		}

		wp_enqueue_style(
			'leaflet',
			bmg_map_leaflet_url( 'leaflet.css' ),
			[],
			'1.9.4'
		);

		wp_enqueue_script(
			'leaflet',
			bmg_map_leaflet_url( 'leaflet.js' ),
			[],
			'1.9.4',
			true
		);

		wp_enqueue_style(
			'bmg-admin',
			BMG_MAP_PLUGIN_URL . 'admin/css/admin.css',
			[ 'leaflet' ],
			BMG_MAP_VERSION
		);

		wp_enqueue_script(
			'bmg-admin',
			BMG_MAP_PLUGIN_URL . 'admin/js/admin.js',
			[ 'leaflet' ],
			BMG_MAP_VERSION,
			true
		);

		$s = BMG_Settings::get();
		wp_localize_script( 'bmg-admin', 'bmgAdminSettings', [
			'defaultColor' => $s['default_color'],
			'minZoom'      => $s['min_zoom'],
			'maxZoom'      => $s['max_zoom'],
		] );

	}

	// -------------------------------------------------------------------------
	// AJAX: return the featured-image URL for a given map post
	// -------------------------------------------------------------------------

	public static function ajax_get_map_image(): void {
		check_ajax_referer( 'bmg_get_map_image', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$map_id = absint( $_POST['map_id'] ?? 0 );
		if ( ! $map_id ) {
			wp_send_json_error( 'Invalid map ID', 400 );
		}

		$url = self::get_map_image_url( $map_id );
		if ( ! $url ) {
			wp_send_json_error( 'No image found', 404 );
		}

		$thumb_id = get_post_thumbnail_id( $map_id );
		$img_meta = wp_get_attachment_metadata( $thumb_id );

		// Sibling locations (used by the location editor).
		$exclude_loc_id    = absint( $_POST['exclude_location_id'] ?? 0 );
		$sibling_locations = [];
		$default_color     = BMG_Settings::get()['default_color'];
		$loc_posts         = get_posts( [
			'post_type'      => 'bmg_location',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'exclude'        => $exclude_loc_id ? [ $exclude_loc_id ] : [],
			'meta_query'     => [ [ 'key' => '_bmg_map_id', 'value' => $map_id, 'type' => 'NUMERIC' ] ],
		] );
		foreach ( $loc_posts as $lp ) {
			$lx = get_post_meta( $lp->ID, '_bmg_loc_x', true );
			$ly = get_post_meta( $lp->ID, '_bmg_loc_y', true );
			if ( $lx === '' || $ly === '' ) {
				continue;
			}
			$sibling_locations[] = [
				'title' => get_the_title( $lp ),
				'x'     => (float) $lx,
				'y'     => (float) $ly,
				'color' => get_post_meta( $lp->ID, '_bmg_loc_color', true ) ?: $default_color,
			];
		}

		// Sibling areas (used by the area editor).
		$exclude_area_id = absint( $_POST['exclude_area_id'] ?? 0 );
		$sibling_areas   = [];
		$area_posts      = get_posts( [
			'post_type'      => 'bmg_area',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'exclude'        => $exclude_area_id ? [ $exclude_area_id ] : [],
			'meta_query'     => [ [ 'key' => '_bmg_area_map_id', 'value' => $map_id, 'type' => 'NUMERIC' ] ],
		] );
		foreach ( $area_posts as $ap ) {
			$pts = json_decode( get_post_meta( $ap->ID, '_bmg_area_points', true ), true );
			if ( ! is_array( $pts ) || count( $pts ) < 2 ) {
				continue;
			}
			$sibling_areas[] = [
				'title'       => get_the_title( $ap ),
				'points'      => $pts,
				'color'       => get_post_meta( $ap->ID, '_bmg_area_color',        true ) ?: '#3388ff',
				'fillColor'   => get_post_meta( $ap->ID, '_bmg_area_fill_color',   true ) ?: '#3388ff',
				'fillOpacity' => (float) ( get_post_meta( $ap->ID, '_bmg_area_fill_opacity', true ) ?: 0.2 ),
			];
		}

		wp_send_json_success( [
			'url'       => $url,
			'width'     => ! empty( $img_meta['width'] )  ? (int) $img_meta['width']  : 0,
			'height'    => ! empty( $img_meta['height'] ) ? (int) $img_meta['height'] : 0,
			'locations' => $sibling_locations,
			'areas'     => $sibling_areas,
		] );
	}

	// -------------------------------------------------------------------------
	// Admin list filters — Parent Map & Page View
	// -------------------------------------------------------------------------

	public static function render_list_filters( string $post_type ): void {
		if ( $post_type !== 'bmg_location' ) {
			return;
		}

		$maps = get_posts( [
			'post_type'      => 'bmg_map',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$selected_map = absint( $_GET['bmg_filter_map'] ?? 0 );
		echo '<select name="bmg_filter_map">';
		echo '<option value="">' . esc_html__( 'All Maps', 'bmg-interactive-map' ) . '</option>';
		foreach ( $maps as $map ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $map->ID ),
				selected( $selected_map, $map->ID, false ),
				esc_html( $map->post_title )
			);
		}
		echo '</select>';

		$selected_vis = isset( $_GET['bmg_filter_visible'] ) ? $_GET['bmg_filter_visible'] : '';
		echo '<select name="bmg_filter_visible">';
		echo '<option value="">' . esc_html__( 'All visibility', 'bmg-interactive-map' ) . '</option>';
		echo '<option value="1"' . selected( $selected_vis, '1', false ) . '>' . esc_html__( 'Shown in page view', 'bmg-interactive-map' ) . '</option>';
		echo '<option value="0"' . selected( $selected_vis, '0', false ) . '>' . esc_html__( 'Hidden from page view', 'bmg-interactive-map' ) . '</option>';
		echo '</select>';
	}

	public static function apply_list_filters( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'bmg_location' ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' ) ?: [];

		if ( ! empty( $_GET['bmg_filter_map'] ) ) {
			$meta_query[] = [
				'key'   => '_bmg_map_id',
				'value' => absint( $_GET['bmg_filter_map'] ),
				'type'  => 'NUMERIC',
			];
		}

		if ( isset( $_GET['bmg_filter_visible'] ) && $_GET['bmg_filter_visible'] !== '' ) {
			if ( $_GET['bmg_filter_visible'] === '0' ) {
				// Hidden: _bmg_hidden = '1'
				$meta_query[] = [ 'key' => '_bmg_hidden', 'value' => '1' ];
			} else {
				// Shown: _bmg_hidden absent or not '1'
				$meta_query[] = [
					'relation' => 'OR',
					[ 'key' => '_bmg_hidden', 'compare' => 'NOT EXISTS' ],
					[ 'key' => '_bmg_hidden', 'value' => '1', 'compare' => '!=' ],
				];
			}
		}

		if ( $meta_query ) {
			$query->set( 'meta_query', $meta_query );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	// -------------------------------------------------------------------------
	// Bulk actions — Hide / Unhide
	// -------------------------------------------------------------------------

	public static function register_bulk_actions( array $actions ): array {
		$actions['bmg_hide']   = __( 'Hide from page view',   'bmg-interactive-map' );
		$actions['bmg_unhide'] = __( 'Show in page view',     'bmg-interactive-map' );
		return $actions;
	}

	public static function handle_bulk_actions( string $redirect_to, string $action, array $post_ids ): string {
		if ( $action !== 'bmg_hide' && $action !== 'bmg_unhide' ) {
			return $redirect_to;
		}

		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', (int) $post_id ) ) {
				continue;
			}
			if ( $action === 'bmg_hide' ) {
				update_post_meta( (int) $post_id, '_bmg_hidden', '1' );
			} else {
				delete_post_meta( (int) $post_id, '_bmg_hidden' );
			}
		}

		return add_query_arg( [
			'bmg_bulk_action' => $action,
			'bmg_bulk_count'  => count( $post_ids ),
		], $redirect_to );
	}

	public static function bulk_action_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'edit-bmg_location' ) {
			return;
		}

		$action = sanitize_key( $_GET['bmg_bulk_action'] ?? '' );
		$count  = (int) ( $_GET['bmg_bulk_count'] ?? 0 );

		if ( ! $action || ! $count ) {
			return;
		}

		if ( $action === 'bmg_hide' ) {
			$msg = sprintf(
				/* translators: %d: number of locations */
				_n( '%d location hidden from page view.', '%d locations hidden from page view.', $count, 'bmg-interactive-map' ),
				$count
			);
		} else {
			$msg = sprintf(
				/* translators: %d: number of locations */
				_n( '%d location set to show in page view.', '%d locations set to show in page view.', $count, 'bmg-interactive-map' ),
				$count
			);
		}

		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $msg ) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	public static function get_map_image_url( int $map_id ): string {
		$thumb_id = get_post_thumbnail_id( $map_id );
		if ( ! $thumb_id ) {
			return '';
		}
		$src = wp_get_attachment_image_src( $thumb_id, 'full' );
		return $src ? $src[0] : '';
	}
}
