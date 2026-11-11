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
	use BMG_CPT_Admin_List_Trait;

	protected static function post_type(): string {
		return 'bmg_area';
	}

	protected static function map_meta_key(): string {
		return '_bmg_area_map_id';
	}

	protected static function admin_script_handle(): string {
		return 'bmg-area-admin';
	}

	protected static function admin_script_file(): string {
		return 'admin/js/area-admin.js';
	}

	protected static function hide_notice_message( int $count ): string {
		return sprintf(
			/* translators: %d: number of areas */
			_n( '%d area hidden from page view.', '%d areas hidden from page view.', $count, 'bmg-interactive-map' ),
			$count
		);
	}

	protected static function unhide_notice_message( int $count ): string {
		return sprintf(
			/* translators: %d: number of areas */
			_n( '%d area set to show in page view.', '%d areas set to show in page view.', $count, 'bmg-interactive-map' ),
			$count
		);
	}

	public static function init(): void {
		add_action( 'init',            [ __CLASS__, 'register_post_type' ] );
		add_action( 'add_meta_boxes',  [ __CLASS__, 'add_meta_boxes' ] );
		add_action( 'save_post',       [ __CLASS__, 'save_meta' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
		add_filter( 'manage_bmg_area_posts_columns',       [ __CLASS__, 'add_map_column' ] );
		add_action( 'manage_bmg_area_posts_custom_column', [ __CLASS__, 'render_map_column' ], 10, 2 );
		add_action( 'restrict_manage_posts', [ __CLASS__, 'render_list_filters' ] );
		add_action( 'pre_get_posts',         [ __CLASS__, 'apply_list_filters'  ] );
		add_filter( 'bulk_actions-edit-bmg_area',        [ __CLASS__, 'register_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-bmg_area', [ __CLASS__, 'handle_bulk_actions'  ], 10, 3 );
		add_action( 'admin_notices',                     [ __CLASS__, 'bulk_action_notice'   ] );
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

		register_post_meta( 'bmg_area', '_bmg_area_styled_title', [
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => [ BMG_Text_Color::class, 'sanitize_styled_title' ],
			'auth_callback'     => static function ( bool $allowed, string $meta_key, int $post_id ): bool {
				return current_user_can( 'edit_post', $post_id );
			},
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

		$map_id           = (int) get_post_meta( $post->ID, '_bmg_area_map_id',          true );
		$color            = get_post_meta( $post->ID, '_bmg_area_color',               true ) ?: '#3388ff';
		$fill_color       = get_post_meta( $post->ID, '_bmg_area_fill_color',          true ) ?: '#3388ff';
		$fill_opacity     = get_post_meta( $post->ID, '_bmg_area_fill_opacity',        true );
		$fill_opacity     = $fill_opacity !== '' ? (float) $fill_opacity : 0.2;
		$named_color      = get_post_meta( $post->ID, '_bmg_area_named_color',         true ) ?: '';
		$named_fill_color = get_post_meta( $post->ID, '_bmg_area_named_fill_color',    true ) ?: '';
		$named_colors     = BMG_Settings::get()['named_colors'];
		$points_json  = get_post_meta( $post->ID, '_bmg_area_points',       true ) ?: '[]';
		$points_array = json_decode( $points_json, true );
		if ( ! is_array( $points_array ) ) {
			$points_array = [];
		}

		$maps = BMG_Map_CPT::get_all_for_dropdown();

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
		$sibling_areas = $map_id ? self::build_sibling_areas( $map_id, $post->ID ) : [];

		$visible = get_post_meta( $post->ID, '_bmg_hidden', true ) !== '1';
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
			<?php if ( $named_colors ) : ?>
			<p style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
				<label for="bmg_area_named_color"><?php esc_html_e( 'Stroke named colour', 'bmg-interactive-map' ); ?>
					<select id="bmg_area_named_color" name="bmg_area_named_color" style="margin-left:4px;">
						<option value=""><?php esc_html_e( '— custom —', 'bmg-interactive-map' ); ?></option>
						<?php foreach ( $named_colors as $nc ) : ?>
							<option value="<?php echo esc_attr( $nc['name'] ); ?>"
								data-hex="<?php echo esc_attr( $nc['hex'] ); ?>"
								<?php selected( $named_color, $nc['name'] ); ?>>
								<?php echo esc_html( $nc['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<label for="bmg_area_named_fill_color"><?php esc_html_e( 'Fill named colour', 'bmg-interactive-map' ); ?>
					<select id="bmg_area_named_fill_color" name="bmg_area_named_fill_color" style="margin-left:4px;">
						<option value=""><?php esc_html_e( '— custom —', 'bmg-interactive-map' ); ?></option>
						<?php foreach ( $named_colors as $nc ) : ?>
							<option value="<?php echo esc_attr( $nc['name'] ); ?>"
								data-hex="<?php echo esc_attr( $nc['hex'] ); ?>"
								<?php selected( $named_fill_color, $nc['name'] ); ?>>
								<?php echo esc_html( $nc['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			</p>
			<?php endif; ?>
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
					<div id="bmg-area-editor" class="bmg-admin-editor-canvas"></div>
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
		if ( ! BMG_Admin_Helpers::verify_save_request( $post_id, 'bmg_area_nonce', 'bmg_area_save' ) ) {
			return;
		}

		if ( isset( $_POST['bmg_area_map_id'] ) ) {
			update_post_meta( $post_id, '_bmg_area_map_id', absint( $_POST['bmg_area_map_id'] ) );
		}

		update_post_meta( $post_id, '_bmg_area_color', BMG_Admin_Helpers::sanitize_hex_with_fallback( wp_unslash( $_POST['bmg_area_color'] ?? '' ), '#3388ff' ) );
		update_post_meta( $post_id, '_bmg_area_fill_color', BMG_Admin_Helpers::sanitize_hex_with_fallback( wp_unslash( $_POST['bmg_area_fill_color'] ?? '' ), '#3388ff' ) );
		update_post_meta( $post_id, '_bmg_area_fill_opacity', BMG_Admin_Helpers::clamp_float( $_POST['bmg_area_fill_opacity'] ?? 0.2, 0.0, 1.0 ) );

		$named_color = sanitize_text_field( wp_unslash( $_POST['bmg_area_named_color'] ?? '' ) );
		update_post_meta( $post_id, '_bmg_area_named_color', $named_color );

		$named_fill_color = sanitize_text_field( wp_unslash( $_POST['bmg_area_named_fill_color'] ?? '' ) );
		update_post_meta( $post_id, '_bmg_area_named_fill_color', $named_fill_color );

		// _bmg_area_styled_title is saved via its registered REST meta field (block editor sidebar panel), not this form.

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

		if ( isset( $_POST['bmg_area_visible'] ) ) {
			delete_post_meta( $post_id, '_bmg_hidden' );
		} else {
			update_post_meta( $post_id, '_bmg_hidden', '1' );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/** Areas on the same map (excluding $exclude_id), for read-only editor overlays. Also used cross-class by BMG_Location_CPT::ajax_get_map_image(). */
	public static function build_sibling_areas( int $map_id, int $exclude_id = 0 ): array {
		$posts = get_posts( [
			'post_type'      => 'bmg_area',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'exclude'        => $exclude_id ? [ $exclude_id ] : [],
			'meta_query'     => [ [ 'key' => '_bmg_area_map_id', 'value' => $map_id, 'type' => 'NUMERIC' ] ],
		] );
		$out = [];
		foreach ( $posts as $p ) {
			$pts = json_decode( get_post_meta( $p->ID, '_bmg_area_points', true ), true );
			if ( ! is_array( $pts ) || count( $pts ) < 2 ) {
				continue;
			}
			$out[] = [
				'title'       => get_the_title( $p ),
				'points'      => $pts,
				'color'       => get_post_meta( $p->ID, '_bmg_area_color',        true ) ?: '#3388ff',
				'fillColor'   => get_post_meta( $p->ID, '_bmg_area_fill_color',   true ) ?: '#3388ff',
				'fillOpacity' => (float) ( get_post_meta( $p->ID, '_bmg_area_fill_opacity', true ) ?: 0.2 ),
			];
		}
		return $out;
	}
}
