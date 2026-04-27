<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers the "bmg_area" custom post type and its meta boxes.
 *
 * Each area post holds:
 *  - Title              → label shown in the popup
 *  - Content            → description shown in the popup
 *  - _bmg_area_map_id   → ID of the parent bmg_map post
 *  - _bmg_area_points   → JSON array of {x, y} percentage coordinates
 *  - _bmg_area_color    → stroke colour (hex)
 *  - _bmg_area_fill_color   → fill colour (hex)
 *  - _bmg_area_fill_opacity → fill opacity (0–1)
 */
class BMG_Area_CPT {

	public static function init(): void {
		add_action( 'init',            [ __CLASS__, 'register_post_type' ] );
		add_action( 'add_meta_boxes',  [ __CLASS__, 'add_meta_boxes' ] );
		add_action( 'save_post',       [ __CLASS__, 'save_meta' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
		add_filter( 'manage_bmg_area_posts_columns',       [ __CLASS__, 'add_map_column' ] );
		add_action( 'manage_bmg_area_posts_custom_column', [ __CLASS__, 'render_map_column' ], 10, 2 );
		add_action( 'restrict_manage_posts', [ __CLASS__, 'render_list_filters' ] );
		add_action( 'pre_get_posts',         [ __CLASS__, 'apply_list_filters'  ] );
	}

	// -------------------------------------------------------------------------
	// Post type registration
	// -------------------------------------------------------------------------

	public static function register_post_type(): void {
		$labels = [
			'name'               => __( 'Areas', 'bmg-interactive-map' ),
			'singular_name'      => __( 'Area', 'bmg-interactive-map' ),
			'add_new_item'       => __( 'Add New Area', 'bmg-interactive-map' ),
			'edit_item'          => __( 'Edit Area', 'bmg-interactive-map' ),
			'new_item'           => __( 'New Area', 'bmg-interactive-map' ),
			'search_items'       => __( 'Search Areas', 'bmg-interactive-map' ),
			'not_found'          => __( 'No areas found.', 'bmg-interactive-map' ),
			'not_found_in_trash' => __( 'No areas found in trash.', 'bmg-interactive-map' ),
			'menu_name'          => __( 'Areas', 'bmg-interactive-map' ),
		];

		register_post_type( 'bmg_area', [
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
			'bmg_area_settings',
			__( 'Area Settings', 'bmg-interactive-map' ),
			[ __CLASS__, 'render_meta_box' ],
			'bmg_area',
			'normal',
			'high'
		);
	}

	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'bmg_area_save', 'bmg_area_nonce' );

		$map_id       = (int) get_post_meta( $post->ID, '_bmg_area_map_id', true );
		$color        = get_post_meta( $post->ID, '_bmg_area_color',        true ) ?: '#3388ff';
		$fill_color   = get_post_meta( $post->ID, '_bmg_area_fill_color',   true ) ?: '#3388ff';
		$fill_opacity = get_post_meta( $post->ID, '_bmg_area_fill_opacity', true );
		$fill_opacity = $fill_opacity !== '' ? (float) $fill_opacity : 0.2;
		$points_json  = get_post_meta( $post->ID, '_bmg_area_points',       true ) ?: '[]';
		$points_array = json_decode( $points_json, true );
		if ( ! is_array( $points_array ) ) {
			$points_array = [];
		}

		$maps = get_posts( [
			'post_type'      => 'bmg_map',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$map_image_url = '';
		$map_image_w   = 0;
		$map_image_h   = 0;
		if ( $map_id ) {
			$map_image_url = BMG_Location_CPT::get_map_image_url( $map_id );
			if ( $map_image_url ) {
				$thumb_id    = get_post_thumbnail_id( $map_id );
				$img_meta    = wp_get_attachment_metadata( $thumb_id );
				$map_image_w = ! empty( $img_meta['width'] )  ? (int) $img_meta['width']  : 0;
				$map_image_h = ! empty( $img_meta['height'] ) ? (int) $img_meta['height'] : 0;
			}
		}

		// Sibling areas for same map (shown as read-only overlays in the editor).
		$sibling_areas = [];
		if ( $map_id ) {
			$sibling_posts = get_posts( [
				'post_type'      => 'bmg_area',
				'post_status'    => [ 'publish', 'draft' ],
				'posts_per_page' => -1,
				'exclude'        => [ $post->ID ],
				'meta_query'     => [ [ 'key' => '_bmg_area_map_id', 'value' => $map_id, 'type' => 'NUMERIC' ] ],
			] );
			foreach ( $sibling_posts as $sp ) {
				$pts = json_decode( get_post_meta( $sp->ID, '_bmg_area_points', true ), true );
				if ( ! is_array( $pts ) || count( $pts ) < 2 ) {
					continue;
				}
				$sibling_areas[] = [
					'title'       => get_the_title( $sp ),
					'points'      => $pts,
					'color'       => get_post_meta( $sp->ID, '_bmg_area_color',        true ) ?: '#3388ff',
					'fillColor'   => get_post_meta( $sp->ID, '_bmg_area_fill_color',   true ) ?: '#3388ff',
					'fillOpacity' => (float) ( get_post_meta( $sp->ID, '_bmg_area_fill_opacity', true ) ?: 0.2 ),
				];
			}
		}

		$visible = get_post_meta( $post->ID, '_bmg_visible', true ) !== '0';
		?>
		<div class="bmg-meta-wrap">

			<!-- Map selector -->
			<p>
				<label for="bmg_area_map_id"><strong><?php esc_html_e( 'Parent Map', 'bmg-interactive-map' ); ?></strong></label><br>
				<select id="bmg_area_map_id" name="bmg_area_map_id" style="width:100%;max-width:400px;">
					<option value=""><?php esc_html_e( '— Select a map —', 'bmg-interactive-map' ); ?></option>
					<?php foreach ( $maps as $map ) : ?>
						<option value="<?php echo esc_attr( $map->ID ); ?>" <?php selected( $map_id, $map->ID ); ?>>
							<?php echo esc_html( $map->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<!-- Appearance -->
			<p style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
				<label for="bmg_area_color"><?php esc_html_e( 'Stroke colour', 'bmg-interactive-map' ); ?>
					<input type="color" id="bmg_area_color" name="bmg_area_color"
						value="<?php echo esc_attr( $color ); ?>" />
				</label>
				<label for="bmg_area_fill_color"><?php esc_html_e( 'Fill colour', 'bmg-interactive-map' ); ?>
					<input type="color" id="bmg_area_fill_color" name="bmg_area_fill_color"
						value="<?php echo esc_attr( $fill_color ); ?>" />
				</label>
				<label for="bmg_area_fill_opacity"><?php esc_html_e( 'Fill opacity (0–1)', 'bmg-interactive-map' ); ?>
					<input type="number" id="bmg_area_fill_opacity" name="bmg_area_fill_opacity"
						value="<?php echo esc_attr( $fill_opacity ); ?>"
						min="0" max="1" step="0.05" style="width:70px;" />
				</label>
			</p>

			<!-- Visibility -->
			<p>
				<label>
					<input type="checkbox" name="bmg_area_visible" value="1" <?php checked( $visible ); ?> />
					<?php esc_html_e( 'Show in page view', 'bmg-interactive-map' ); ?>
				</label>
			</p>

			<!-- Polygon controls -->
			<p>
				<strong><?php esc_html_e( 'Polygon', 'bmg-interactive-map' ); ?></strong>
				&nbsp;<span id="bmg-area-vertex-count" style="color:#666;">
					<?php echo count( $points_array ); ?> <?php esc_html_e( 'vertices', 'bmg-interactive-map' ); ?>
				</span>
			</p>
			<p style="display:flex;gap:8px;">
				<button type="button" id="bmg-area-undo" class="button"><?php esc_html_e( 'Undo last vertex', 'bmg-interactive-map' ); ?></button>
				<button type="button" id="bmg-area-clear" class="button"><?php esc_html_e( 'Clear polygon', 'bmg-interactive-map' ); ?></button>
			</p>

			<!-- Hidden JSON store — JS writes here before form submit -->
			<textarea id="bmg_area_points" name="bmg_area_points" style="display:none;"><?php echo esc_textarea( $points_json ); ?></textarea>

			<!-- Visual polygon editor -->
			<div id="bmg-area-editor-wrap" style="margin-top:12px;">
				<?php if ( $map_image_url ) : ?>
					<p class="description"><?php esc_html_e( 'Click the map to add vertices. Drag a vertex to reposition it.', 'bmg-interactive-map' ); ?></p>
					<div id="bmg-area-editor" class="bmg-admin-area-editor"></div>
				<?php else : ?>
					<p id="bmg-no-map-notice" class="description">
						<?php esc_html_e( 'Select a map above to enable the visual polygon editor.', 'bmg-interactive-map' ); ?>
					</p>
				<?php endif; ?>
			</div>

		</div>

		<script>
		window.bmgAreaMeta = {
			ajaxUrl     : <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
			nonce       : <?php echo wp_json_encode( wp_create_nonce( 'bmg_get_map_image' ) ); ?>,
			postId      : <?php echo wp_json_encode( $post->ID ); ?>,
			imageUrl    : <?php echo wp_json_encode( $map_image_url ); ?>,
			imageWidth  : <?php echo wp_json_encode( $map_image_w ); ?>,
			imageHeight : <?php echo wp_json_encode( $map_image_h ); ?>,
			points      : <?php echo wp_json_encode( $points_array ?: null ); ?>,
			color       : <?php echo wp_json_encode( $color ); ?>,
			fillColor   : <?php echo wp_json_encode( $fill_color ); ?>,
			fillOpacity : <?php echo wp_json_encode( $fill_opacity ); ?>,
			siblingAreas: <?php echo wp_json_encode( $sibling_areas ); ?>,
		};
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save meta
	// -------------------------------------------------------------------------

	public static function save_meta( int $post_id ): void {
		if (
			! isset( $_POST['bmg_area_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bmg_area_nonce'] ) ), 'bmg_area_save' )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['bmg_area_map_id'] ) ) {
			update_post_meta( $post_id, '_bmg_area_map_id', absint( $_POST['bmg_area_map_id'] ) );
		}

		$color = sanitize_hex_color( wp_unslash( $_POST['bmg_area_color'] ?? '' ) );
		update_post_meta( $post_id, '_bmg_area_color', $color ?: '#3388ff' );

		$fill_color = sanitize_hex_color( wp_unslash( $_POST['bmg_area_fill_color'] ?? '' ) );
		update_post_meta( $post_id, '_bmg_area_fill_color', $fill_color ?: '#3388ff' );

		update_post_meta( $post_id, '_bmg_area_fill_opacity', min( 1.0, max( 0.0, (float) ( $_POST['bmg_area_fill_opacity'] ?? 0.2 ) ) ) );

		$raw = wp_unslash( $_POST['bmg_area_points'] ?? '' );
		$pts = json_decode( $raw, true );
		if ( is_array( $pts ) ) {
			$clean = array_values( array_filter( array_map( function ( $p ) {
				if ( ! isset( $p['x'], $p['y'] ) ) {
					return null;
				}
				return [ 'x' => round( (float) $p['x'], 4 ), 'y' => round( (float) $p['y'], 4 ) ];
			}, $pts ) ) );
			update_post_meta( $post_id, '_bmg_area_points', wp_json_encode( $clean ) );
		}

		update_post_meta( $post_id, '_bmg_visible', isset( $_POST['bmg_area_visible'] ) ? '1' : '0' );
	}

	// -------------------------------------------------------------------------
	// Area listing — Parent Map column
	// -------------------------------------------------------------------------

	public static function add_map_column( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( $key === 'title' ) {
				$new['bmg_parent_map'] = __( 'Parent Map', 'bmg-interactive-map' );
			}
		}
		return $new;
	}

	public static function render_map_column( string $column, int $post_id ): void {
		if ( $column !== 'bmg_parent_map' ) {
			return;
		}
		$map_id = (int) get_post_meta( $post_id, '_bmg_area_map_id', true );
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
	// Admin list filters — Parent Map & Page View
	// -------------------------------------------------------------------------

	public static function render_list_filters( string $post_type ): void {
		if ( $post_type !== 'bmg_area' ) {
			return;
		}

		$maps = get_posts( [
			'post_type'      => 'bmg_map',
			'post_status'    => 'publish',
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
		if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'bmg_area' ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' ) ?: [];

		if ( ! empty( $_GET['bmg_filter_map'] ) ) {
			$meta_query[] = [
				'key'   => '_bmg_area_map_id',
				'value' => absint( $_GET['bmg_filter_map'] ),
				'type'  => 'NUMERIC',
			];
		}

		if ( isset( $_GET['bmg_filter_visible'] ) && $_GET['bmg_filter_visible'] !== '' ) {
			if ( $_GET['bmg_filter_visible'] === '0' ) {
				$meta_query[] = [ 'key' => '_bmg_visible', 'value' => '0' ];
			} else {
				$meta_query[] = [
					'relation' => 'OR',
					[ 'key' => '_bmg_visible', 'compare' => 'NOT EXISTS' ],
					[ 'key' => '_bmg_visible', 'value' => '0', 'compare' => '!=' ],
				];
			}
		}

		if ( $meta_query ) {
			$query->set( 'meta_query', $meta_query );
		}
	}

	// -------------------------------------------------------------------------
	// Admin assets
	// -------------------------------------------------------------------------

	public static function enqueue_admin_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'bmg_area' ) {
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
			'bmg-area-admin',
			BMG_MAP_PLUGIN_URL . 'admin/js/area-admin.js',
			[ 'leaflet' ],
			BMG_MAP_VERSION,
			true
		);

		$s = BMG_Settings::get();
		wp_localize_script( 'bmg-area-admin', 'bmgAdminSettings', [
			'defaultColor' => $s['default_color'],
			'minZoom'      => $s['min_zoom'],
			'maxZoom'      => $s['max_zoom'],
		] );
	}
}
