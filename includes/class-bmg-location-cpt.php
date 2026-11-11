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
 *  - _bmg_loc_styled_title → optional bold/italic/underline title, replaces Title in popup/tooltip
 */
class BMG_Location_CPT {
	use BMG_CPT_Admin_List_Trait;

	protected static function post_type(): string {
		return 'bmg_location';
	}

	protected static function map_meta_key(): string {
		return '_bmg_map_id';
	}

	protected static function admin_script_handle(): string {
		return 'bmg-admin';
	}

	protected static function admin_script_file(): string {
		return 'admin/js/admin.js';
	}

	protected static function hide_notice_message( int $count ): string {
		return sprintf(
			/* translators: %d: number of locations */
			_n( '%d location hidden from page view.', '%d locations hidden from page view.', $count, 'bmg-interactive-map' ),
			$count
		);
	}

	protected static function unhide_notice_message( int $count ): string {
		return sprintf(
			/* translators: %d: number of locations */
			_n( '%d location set to show in page view.', '%d locations set to show in page view.', $count, 'bmg-interactive-map' ),
			$count
		);
	}

	protected static function extra_columns(): array {
		return [
			'bmg_secret'    => __( 'Secret',    'bmg-interactive-map' ),
			'bmg_temporary' => __( 'Temporary', 'bmg-interactive-map' ),
		];
	}

	protected static function extra_column_renderer( string $column, int $post_id ): bool {
		if ( $column === 'bmg_secret' ) {
			if ( get_post_meta( $post_id, '_bmg_loc_secret', true ) === '1' ) {
				echo '<span class="dashicons dashicons-lock" style="color:#d63638;" title="' . esc_attr__( 'Secret location', 'bmg-interactive-map' ) . '"></span>';
			} else {
				echo '<span class="dashicons dashicons-unlock" style="color:#999;" title="' . esc_attr__( 'Not secret', 'bmg-interactive-map' ) . '"></span>';
			}
			return true;
		}

		if ( $column === 'bmg_temporary' ) {
			$is_secret    = get_post_meta( $post_id, '_bmg_loc_secret', true ) === '1';
			$is_temporary = get_post_meta( $post_id, '_bmg_loc_secret_temporary', true ) === '1';
			if ( $is_secret && $is_temporary ) {
				echo '<span class="dashicons dashicons-clock" style="color:#dba617;" title="' . esc_attr__( 'Temporary reveal', 'bmg-interactive-map' ) . '"></span>';
			} else {
				echo '—';
			}
			return true;
		}

		return false;
	}

	protected static function extra_list_filters(): void {
		$selected_secret = isset( $_GET['bmg_filter_secret'] ) ? $_GET['bmg_filter_secret'] : '';
		echo '<select name="bmg_filter_secret">';
		echo '<option value="">' . esc_html__( 'All locations', 'bmg-interactive-map' ) . '</option>';
		echo '<option value="1"' . selected( $selected_secret, '1', false ) . '>' . esc_html__( 'Secret only', 'bmg-interactive-map' ) . '</option>';
		echo '<option value="0"' . selected( $selected_secret, '0', false ) . '>' . esc_html__( 'Not secret', 'bmg-interactive-map' ) . '</option>';
		echo '</select>';
	}

	protected static function extra_meta_query_filters( array $meta_query ): array {
		if ( isset( $_GET['bmg_filter_secret'] ) && $_GET['bmg_filter_secret'] !== '' ) {
			if ( $_GET['bmg_filter_secret'] === '1' ) {
				$meta_query[] = [ 'key' => '_bmg_loc_secret', 'value' => '1' ];
			} else {
				$meta_query[] = [
					'relation' => 'OR',
					[ 'key' => '_bmg_loc_secret', 'compare' => 'NOT EXISTS' ],
					[ 'key' => '_bmg_loc_secret', 'value' => '1', 'compare' => '!=' ],
				];
			}
		}
		return $meta_query;
	}

	protected static function extra_bulk_actions(): array {
		return [ 'bmg_copy_to_map' => __( 'Copy to map…', 'bmg-interactive-map' ) ];
	}

	protected static function handle_extra_bulk_action( string $action, array $post_ids ): ?string {
		if ( $action !== 'bmg_copy_to_map' ) {
			return null;
		}
		$valid_ids = [];
		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( current_user_can( 'edit_post', $post_id ) ) {
				$valid_ids[] = $post_id;
			}
		}
		if ( ! $valid_ids ) {
			return null; // Nothing valid selected — fall back to the normal list redirect.
		}
		$token = wp_generate_password( 12, false );
		set_transient( 'bmg_copy_ids_' . get_current_user_id() . '_' . $token, $valid_ids, 5 * MINUTE_IN_SECONDS );
		return add_query_arg( 'bmg_copy_token', $token, admin_url( 'admin.php?page=bmg-copy-locations' ) );
	}

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
		add_action( 'admin_menu',                            [ __CLASS__, 'add_copy_page'         ] );
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
		$settings      = BMG_Settings::get();
		$default_color = $settings['default_color'];
		$color         = get_post_meta( $post->ID, '_bmg_loc_color',       true ) ?: $default_color;
		$named_color   = get_post_meta( $post->ID, '_bmg_loc_named_color', true ) ?: '';
		$named_colors  = $settings['named_colors'];
		// Fetch published and draft maps for the dropdown.
		$maps = BMG_Map_CPT::get_all_for_dropdown();

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
		$sibling_locations = $map_id ? self::build_sibling_locations( $map_id, $post->ID ) : [];

		$styled_title     = get_post_meta( $post->ID, '_bmg_loc_styled_title', true );
		$visible          = get_post_meta( $post->ID, '_bmg_hidden', true ) !== '1';
		$secret           = get_post_meta( $post->ID, '_bmg_loc_secret', true ) === '1';
		$secret_temporary = get_post_meta( $post->ID, '_bmg_loc_secret_temporary', true ) === '1';
		$secret_reach_val = get_post_meta( $post->ID, '_bmg_loc_secret_reach_value', true );
		$secret_reach_unit = get_post_meta( $post->ID, '_bmg_loc_secret_reach_unit', true ) ?: 'px';
		$global_reach      = $settings['secret_reach_value'] ?? 40;
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
				<?php if ( $named_colors ) : ?>
				<label for="bmg_loc_named_color"><?php esc_html_e( 'Named colour', 'bmg-interactive-map' ); ?>
					<select id="bmg_loc_named_color" name="bmg_loc_named_color" style="margin-left:4px;">
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
			<?php endif; ?>
				<label for="bmg_loc_color"><?php esc_html_e( 'Marker colour', 'bmg-interactive-map' ); ?>
					<input type="color" id="bmg_loc_color" name="bmg_loc_color"
						value="<?php echo esc_attr( $color ); ?>" />
				</label>
			</p>

		<!-- Styled Title -->
		<p>
			<label for="bmglocstyledtitle"><strong><?php esc_html_e( 'Styled Title (optional)', 'bmg-interactive-map' ); ?></strong></label><br>
			<span class="description"><?php esc_html_e( 'Add bold, italic, underline, or letter colour for the popup and hover tooltip. Leave blank to use the plain title above (which supports [color=…] tags).', 'bmg-interactive-map' ); ?></span>
		</p>
		<?php
		wp_editor( $styled_title, 'bmglocstyledtitle', [
			'textarea_name' => 'bmg_loc_styled_title',
			'textarea_rows' => 2,
			'media_buttons' => false,
			'teeny'         => true,
			'quicktags'     => false,
			'tinymce'       => [
				'toolbar1'  => 'bold,italic,underline,forecolor',
				'plugins'   => 'textcolor',
				'statusbar' => false,
				'resize'    => false,
			],
		] );
		?>

		<!-- Visibility -->
		<p>
			<label>
				<input type="checkbox" name="bmg_location_visible" value="1" <?php checked( $visible ); ?> />
				<?php esc_html_e( 'Show in page view', 'bmg-interactive-map' ); ?>
			</label>
		</p>

		<!-- Secret -->
		<p>
			<label>
				<input type="checkbox" id="bmg_location_secret" name="bmg_location_secret" value="1" <?php checked( $secret ); ?> />
				<?php esc_html_e( 'Secret (hidden until discovered by visitors)', 'bmg-interactive-map' ); ?>
			</label>
		</p>
		<div id="bmg-secret-options" style="margin-left:24px;<?php echo $secret ? '' : 'display:none;'; ?>">
			<p>
				<label>
					<input type="checkbox" name="bmg_location_secret_temporary" value="1" <?php checked( $secret_temporary ); ?> />
					<?php esc_html_e( 'Temporary discovery (only remembered for the visitor\'s current browser session, not permanently)', 'bmg-interactive-map' ); ?>
				</label>
			</p>
			<p>
				<label for="bmg_loc_secret_reach_value"><?php esc_html_e( 'Reveal reach override', 'bmg-interactive-map' ); ?></label><br>
				<input type="number" id="bmg_loc_secret_reach_value" name="bmg_loc_secret_reach_value"
					value="<?php echo esc_attr( $secret_reach_val ); ?>"
					placeholder="<?php echo esc_attr( $global_reach ); ?>"
					min="<?php echo $secret_reach_unit === '%' ? '0.1' : '1'; ?>" max="500"
					step="<?php echo $secret_reach_unit === '%' ? '0.1' : '1'; ?>" style="width:70px;" />
				<select id="bmg_loc_secret_reach_unit" name="bmg_loc_secret_reach_unit">
					<option value="px" <?php selected( $secret_reach_unit, 'px' ); ?>><?php esc_html_e( 'px', 'bmg-interactive-map' ); ?></option>
					<option value="%" <?php selected( $secret_reach_unit, '%' ); ?>>%</option>
				</select>
				<br><span class="description"><?php esc_html_e( 'Leave blank to use the global default set in Interactive Maps → Settings. Fractional values are only allowed for percentages.', 'bmg-interactive-map' ); ?></span>
			</p>
		</div>
		<script>
		( function () {
			var cb  = document.getElementById( 'bmg_location_secret' );
			var opt = document.getElementById( 'bmg-secret-options' );
			if ( cb && opt ) {
				cb.addEventListener( 'change', function () {
					opt.style.display = cb.checked ? '' : 'none';
				} );
			}

			var reachVal  = document.getElementById( 'bmg_loc_secret_reach_value' );
			var reachUnit = document.getElementById( 'bmg_loc_secret_reach_unit' );
			if ( reachVal && reachUnit ) {
				reachUnit.addEventListener( 'change', function () {
					var isPercent = reachUnit.value === '%';
					reachVal.step = isPercent ? '0.1' : '1';
					reachVal.min  = isPercent ? '0.1' : '1';
					if ( ! isPercent && reachVal.value !== '' ) {
						reachVal.value = Math.round( parseFloat( reachVal.value ) || 0 );
					}
				} );
			}
		} )();
		</script>

		<!-- Popup preview -->
		<p>
			<button type="button" id="bmg-preview-toggle" class="button"><?php esc_html_e( 'Show Popup Preview', 'bmg-interactive-map' ); ?></button>
		</p>
		<div id="bmg-popup-preview-wrap" style="display:none;margin-bottom:12px;">
			<div class="bmg-admin-popup-preview">
				<h3 class="bmg-popup-title"><?php echo BMG_Text_Color::display_title_html( $post->post_title, $styled_title ); ?></h3>
				<?php if ( $post->post_content ) : ?>
					<div class="bmg-popup-body"><?php echo wp_kses_post( wpautop( do_shortcode( BMG_Text_Color::body_html( $post->post_content ) ) ) ); ?></div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Visual map editor (Leaflet CRS.Simple) -->
			<div id="bmg-map-editor-wrap" style="margin-top:12px;">
				<?php if ( $map_image_url ) : ?>
					<p class="description"><?php esc_html_e( 'Click the map to place the marker. Drag to reposition.', 'bmg-interactive-map' ); ?></p>
					<div id="bmg-map-editor" class="bmg-admin-editor-canvas"></div>
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
		if ( ! BMG_Admin_Helpers::verify_save_request( $post_id, 'bmg_location_nonce', 'bmg_location_save' ) ) {
			return;
		}

		if ( isset( $_POST['bmg_map_id'] ) ) {
			update_post_meta( $post_id, '_bmg_map_id', absint( $_POST['bmg_map_id'] ) );
		}

		if ( isset( $_POST['bmg_loc_x'] ) ) {
			update_post_meta( $post_id, '_bmg_loc_x', BMG_Admin_Helpers::clamp_float( $_POST['bmg_loc_x'], 0.0, 100.0 ) );
		}

		if ( isset( $_POST['bmg_loc_y'] ) ) {
			update_post_meta( $post_id, '_bmg_loc_y', BMG_Admin_Helpers::clamp_float( $_POST['bmg_loc_y'], 0.0, 100.0 ) );
		}

		if ( isset( $_POST['bmg_loc_color'] ) ) {
			// Allow only valid hex colours; leave the existing value untouched if invalid.
			$color = sanitize_hex_color( wp_unslash( $_POST['bmg_loc_color'] ) );
			if ( $color ) {
				update_post_meta( $post_id, '_bmg_loc_color', $color );
			}
		}

		$named_color = sanitize_text_field( wp_unslash( $_POST['bmg_loc_named_color'] ?? '' ) );
		update_post_meta( $post_id, '_bmg_loc_named_color', $named_color );

		if ( isset( $_POST['bmg_loc_styled_title'] ) ) {
			update_post_meta( $post_id, '_bmg_loc_styled_title', BMG_Text_Color::sanitize_styled_title( wp_unslash( $_POST['bmg_loc_styled_title'] ) ) );
		}

		if ( isset( $_POST['bmg_location_visible'] ) ) {
			delete_post_meta( $post_id, '_bmg_hidden' );
		} else {
			update_post_meta( $post_id, '_bmg_hidden', '1' );
		}

		if ( isset( $_POST['bmg_location_secret'] ) ) {
			update_post_meta( $post_id, '_bmg_loc_secret', '1' );
		} else {
			delete_post_meta( $post_id, '_bmg_loc_secret' );
		}

		if ( isset( $_POST['bmg_location_secret_temporary'] ) ) {
			update_post_meta( $post_id, '_bmg_loc_secret_temporary', '1' );
		} else {
			delete_post_meta( $post_id, '_bmg_loc_secret_temporary' );
		}

		if ( isset( $_POST['bmg_loc_secret_reach_value'] ) && $_POST['bmg_loc_secret_reach_value'] !== '' ) {
			$reach_unit = ( $_POST['bmg_loc_secret_reach_unit'] ?? '' ) === '%' ? '%' : 'px';
			update_post_meta( $post_id, '_bmg_loc_secret_reach_value', BMG_Admin_Helpers::sanitize_reach_value( $_POST['bmg_loc_secret_reach_value'], $reach_unit ) );
			update_post_meta( $post_id, '_bmg_loc_secret_reach_unit', $reach_unit );
		} else {
			delete_post_meta( $post_id, '_bmg_loc_secret_reach_value' );
			delete_post_meta( $post_id, '_bmg_loc_secret_reach_unit' );
		}
	}

	// -------------------------------------------------------------------------
	// AJAX: return the featured-image URL for a given map post
	// -------------------------------------------------------------------------

	public static function ajax_get_map_image(): void {
		check_ajax_referer( 'bmg_get_map_image', 'nonce' );

		$map_id = absint( $_POST['map_id'] ?? 0 );
		if ( ! $map_id ) {
			wp_send_json_error( 'Invalid map ID', 400 );
		}

		// Check capability against this specific map, not a blanket edit_posts —
		// otherwise any Author/Contributor could read any map's image/sibling data.
		if ( ! current_user_can( 'edit_post', $map_id ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$url = self::get_map_image_url( $map_id );
		if ( ! $url ) {
			wp_send_json_error( 'No image found', 404 );
		}

		$thumb_id = get_post_thumbnail_id( $map_id );
		$img_meta = wp_get_attachment_metadata( $thumb_id );

		$exclude_loc_id  = absint( $_POST['exclude_location_id'] ?? 0 );
		$exclude_area_id = absint( $_POST['exclude_area_id'] ?? 0 );

		wp_send_json_success( [
			'url'       => $url,
			'width'     => ! empty( $img_meta['width'] )  ? (int) $img_meta['width']  : 0,
			'height'    => ! empty( $img_meta['height'] ) ? (int) $img_meta['height'] : 0,
			'locations' => self::build_sibling_locations( $map_id, $exclude_loc_id ),
			'areas'     => BMG_Area_CPT::build_sibling_areas( $map_id, $exclude_area_id ),
		] );
	}

	// -------------------------------------------------------------------------
	// Bulk action notice — the Hide/Unhide half is provided by the trait;
	// this override adds the Copy-to-map "N location(s) copied" notice.
	// -------------------------------------------------------------------------

	public static function bulk_action_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'edit-bmg_location' ) {
			return;
		}

		if ( isset( $_GET['bmg_copied_count'] ) ) {
			$copied_count = (int) $_GET['bmg_copied_count'];
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sprintf(
					/* translators: %d: number of locations copied */
					_n( '%d location copied.', '%d locations copied.', $copied_count, 'bmg-interactive-map' ),
					$copied_count
				) )
			);
		}

		self::render_hide_unhide_notice();
	}

	// -------------------------------------------------------------------------
	// Bulk action: Copy to map
	// -------------------------------------------------------------------------

	public static function add_copy_page(): void {
		add_submenu_page(
			null, // Hidden — not attached to any menu, reachable only by URL.
			__( 'Copy Locations to Map', 'bmg-interactive-map' ),
			__( 'Copy Locations to Map', 'bmg-interactive-map' ),
			'edit_posts',
			'bmg-copy-locations',
			[ __CLASS__, 'render_copy_page' ]
		);
	}

	public static function render_copy_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'bmg-interactive-map' ) );
		}

		// Token binds this page load to one specific bulk-copy request, so two
		// concurrent "Copy to map" actions (e.g. two browser tabs) by the same
		// admin can't clobber or cross-submit each other's selection.
		$token = sanitize_key( $_GET['bmg_copy_token'] ?? ( $_POST['bmg_copy_token'] ?? '' ) );

		echo '<div class="wrap"><h1>' . esc_html__( 'Copy Locations to Map', 'bmg-interactive-map' ) . '</h1>';

		if ( ! $token ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div><p><a href="%s">%s</a></p></div>',
				esc_html__( 'Your selection has expired or is no longer available. Please select locations again.', 'bmg-interactive-map' ),
				esc_url( admin_url( 'edit.php?post_type=bmg_location' ) ),
				esc_html__( 'Back to Locations', 'bmg-interactive-map' )
			);
			return;
		}

		$transient_key = 'bmg_copy_ids_' . get_current_user_id() . '_' . $token;
		$post_ids      = get_transient( $transient_key );

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div><p><a href="%s">%s</a></p></div>',
				esc_html__( 'Your selection has expired or is no longer available. Please select locations again.', 'bmg-interactive-map' ),
				esc_url( admin_url( 'edit.php?post_type=bmg_location' ) ),
				esc_html__( 'Back to Locations', 'bmg-interactive-map' )
			);
			return;
		}

		if ( isset( $_POST['bmg_copy_submit'] ) ) {
			check_admin_referer( 'bmg_copy_locations', 'bmg_copy_locations_nonce' );
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_die( esc_html__( 'You do not have permission to do this.', 'bmg-interactive-map' ) );
			}
			$target_map_id     = absint( $_POST['bmg_target_map_id'] ?? 0 );
			$target_status_ok  = in_array( get_post_status( $target_map_id ), [ 'publish', 'draft' ], true );
			if (
				$target_map_id
				&& get_post_type( $target_map_id ) === 'bmg_map'
				&& $target_status_ok
				&& current_user_can( 'edit_post', $target_map_id )
			) {
				$count = self::copy_locations_to_map( $post_ids, $target_map_id );
				delete_transient( $transient_key );
				wp_safe_redirect( add_query_arg(
					[ 'bmg_filter_map' => $target_map_id, 'bmg_copied_count' => $count ],
					admin_url( 'edit.php?post_type=bmg_location' )
				) );
				exit;
			}
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Please choose a valid target map.', 'bmg-interactive-map' ) . '</p></div>';
		}

		echo '<p>' . esc_html__( 'The following locations will be copied:', 'bmg-interactive-map' ) . '</p><ul style="list-style:disc;margin-left:2em;">';
		foreach ( $post_ids as $pid ) {
			$p = get_post( $pid );
			if ( $p && $p->post_type === 'bmg_location' ) {
				printf( '<li>%s</li>', esc_html( get_the_title( $p ) ) );
			}
		}
		echo '</ul>';

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'The destination map should use the same underlying map image as the source. Location position is stored as X/Y percentages relative to a specific image\'s dimensions, so copying to a map with a different image will place the copies incorrectly.', 'bmg-interactive-map' )
		);

		// Only offer maps the current user can actually edit — otherwise a lower-
		// privileged user could attach copies to a map they don't control.
		$maps = array_values( array_filter(
			BMG_Map_CPT::get_all_for_dropdown(),
			static function ( WP_Post $map ): bool {
				return current_user_can( 'edit_post', $map->ID );
			}
		) );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=bmg-copy-locations' ) ) . '">';
		wp_nonce_field( 'bmg_copy_locations', 'bmg_copy_locations_nonce' );
		echo '<input type="hidden" name="bmg_copy_token" value="' . esc_attr( $token ) . '" />';
		echo '<table class="form-table"><tr><th><label for="bmg_target_map_id">' . esc_html__( 'Copy to map', 'bmg-interactive-map' ) . '</label></th><td>';
		echo '<select name="bmg_target_map_id" id="bmg_target_map_id" required>';
		echo '<option value="">' . esc_html__( '— Select a map —', 'bmg-interactive-map' ) . '</option>';
		foreach ( $maps as $map ) {
			printf( '<option value="%s">%s</option>', esc_attr( $map->ID ), esc_html( $map->post_title ) );
		}
		echo '</select></td></tr></table>';
		submit_button( __( 'Copy', 'bmg-interactive-map' ), 'primary', 'bmg_copy_submit' );
		echo '</form></div>';
	}

	private static function copy_locations_to_map( array $post_ids, int $target_map_id ): int {
		$meta_keys_to_copy = [
			'_bmg_loc_x',
			'_bmg_loc_y',
			'_bmg_loc_color',
			'_bmg_loc_named_color',
			'_bmg_loc_styled_title',
			'_bmg_hidden',
			'_bmg_loc_secret',
			'_bmg_loc_secret_temporary',
			'_bmg_loc_secret_reach_value',
			'_bmg_loc_secret_reach_unit',
		];

		$copied = 0;

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			$source = get_post( $post_id );
			if ( ! $source || $source->post_type !== 'bmg_location' ) {
				continue;
			}

			$new_id = wp_insert_post( [
				'post_type'    => 'bmg_location',
				'post_title'   => $source->post_title,
				'post_content' => $source->post_content,
				'post_status'  => $source->post_status,
				// post_name intentionally omitted — let WP auto-generate a unique slug.
			], true );

			if ( is_wp_error( $new_id ) || ! $new_id ) {
				continue;
			}

			foreach ( $meta_keys_to_copy as $key ) {
				$value = get_post_meta( $post_id, $key, true );
				if ( $value !== '' ) {
					update_post_meta( $new_id, $key, $value );
				}
			}

			// Always point the copy at the chosen target map, never the source's map.
			update_post_meta( $new_id, '_bmg_map_id', $target_map_id );

			$copied++;
		}

		return $copied;
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

	/** Locations on the same map (excluding $exclude_id), for read-only editor overlays and the map-image AJAX response. */
	private static function build_sibling_locations( int $map_id, int $exclude_id = 0 ): array {
		$default_color = BMG_Settings::get()['default_color'];
		$posts         = get_posts( [
			'post_type'      => 'bmg_location',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'exclude'        => $exclude_id ? [ $exclude_id ] : [],
			'meta_query'     => [ [ 'key' => '_bmg_map_id', 'value' => $map_id, 'type' => 'NUMERIC' ] ],
		] );
		$out = [];
		foreach ( $posts as $p ) {
			$x = get_post_meta( $p->ID, '_bmg_loc_x', true );
			$y = get_post_meta( $p->ID, '_bmg_loc_y', true );
			if ( $x === '' || $y === '' ) {
				continue;
			}
			$out[] = [
				'title' => get_the_title( $p ),
				'x'     => (float) $x,
				'y'     => (float) $y,
				'color' => get_post_meta( $p->ID, '_bmg_loc_color', true ) ?: $default_color,
			];
		}
		return $out;
	}
}
