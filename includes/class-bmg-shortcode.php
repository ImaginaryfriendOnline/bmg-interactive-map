<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers the [bmg_map] shortcode.
 *
 * Usage:
 *   [bmg_map id="42"]
 *   [bmg_map id="42" width="800" height="600"]
 *   [bmg_map id="42" list_position="right" area_list_position="right"]
 *
 * Attributes:
 *   id                  (required) — the ID of the bmg_map post to render.
 *   width               (optional) — explicit width in pixels.
 *   height              (optional) — explicit height in pixels.
 *   list_position       (optional) — show a location list: left | right | float-* | none (default).
 *   area_list_position  (optional) — show an area list: same values as list_position.
 */
class BMG_Shortcode {

	/**
	 * Icon HTML for the popup close button, set by the Elementor widget just
	 * before calling render() so it never travels through shortcode attributes.
	 */
	private static string $pending_close_icon_html = '';

	public static function set_close_icon_html( string $html ): void {
		self::$pending_close_icon_html = $html;
	}

	public static function init(): void {
		add_shortcode( 'bmg_map', [ __CLASS__, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
	}

	// -------------------------------------------------------------------------
	// Shortcode output
	// -------------------------------------------------------------------------

	public static function render( array $atts ): string {
		// Consume the close-icon HTML set by the Elementor widget (or empty for shortcode/block).
		$close_icon_html               = self::$pending_close_icon_html;
		self::$pending_close_icon_html = '';

		$atts   = shortcode_atts( [
			'id'                 => 0,
			'width'              => '',
			'height'             => '',
			'list_position'      => 'none',
			'zoom_position'      => '',
			'show_tooltips'      => '0',
			'list_title'         => '',
			'start_zoom'         => '',
			'start_x'            => '',
			'start_y'            => '',
			'responsive_start'   => '',
			'area_list_position' => 'none',
			'area_list_title'    => '',
			'toolbar_position'   => '',
		], $atts, 'bmg_map' );

		$map_id = absint( $atts['id'] );
		$dim_w  = self::sanitize_dimension( $atts['width'] );
		$dim_h  = self::sanitize_dimension( $atts['height'] );

		$valid_positions = [ 'left', 'right', 'float-tl', 'float-tr', 'float-bl', 'float-br', 'none' ];
		$list_position   = in_array( $atts['list_position'], $valid_positions, true ) ? $atts['list_position'] : 'none';

		$valid_zoom_pos = [ 'topleft', 'topright', 'bottomleft', 'bottomright' ];
		$zoom_position  = in_array( $atts['zoom_position'], $valid_zoom_pos, true ) ? $atts['zoom_position'] : '';
		$list_title     = sanitize_text_field( $atts['list_title'] );

		$start_zoom = $atts['start_zoom'] !== '' ? (float) $atts['start_zoom'] : '';
		$start_x    = $atts['start_x']    !== '' ? min( 100.0, max( 0.0, (float) $atts['start_x'] ) ) : '';
		$start_y    = $atts['start_y']    !== '' ? min( 100.0, max( 0.0, (float) $atts['start_y'] ) ) : '';
		$has_start  = $start_zoom !== '' && $start_x !== '' && $start_y !== '';

		$responsive_start_json = '';
		if ( $atts['responsive_start'] !== '' ) {
			$rs_decoded = json_decode( wp_unslash( $atts['responsive_start'] ), true );
			if ( is_array( $rs_decoded ) ) {
				$responsive_start_json = wp_json_encode( $rs_decoded, JSON_INVALID_UTF8_SUBSTITUTE );
			}
		}

		// Area list position.
		$area_list_position = in_array( $atts['area_list_position'], $valid_positions, true ) ? $atts['area_list_position'] : 'none';
		$area_list_title    = sanitize_text_field( $atts['area_list_title'] );

		// Toolbar position override.
		$allowed_toolbar_positions = [ 'top', 'top-left', 'top-right', 'bottom', 'bottom-left', 'bottom-right' ];
		$toolbar_position = in_array( $atts['toolbar_position'], $allowed_toolbar_positions, true )
			? $atts['toolbar_position']
			: '';

		if ( ! $map_id ) {
			return '<!-- bmg_map: no id provided -->';
		}

		$map = get_post( $map_id );
		if ( ! $map || $map->post_type !== 'bmg_map' || $map->post_status !== 'publish' ) {
			return '<!-- bmg_map: map not found -->';
		}

		$image_url = BMG_Location_CPT::get_map_image_url( $map_id );
		if ( ! $image_url ) {
			return '<!-- bmg_map: map has no image -->';
		}

		// Build the wrapper inline style.
		$explicit_size  = false;
		$spacer_img_src = '';

		$thumb_id = get_post_thumbnail_id( $map_id );
		$img_meta = wp_get_attachment_metadata( $thumb_id );
		$img_w    = ! empty( $img_meta['width'] )  ? (int) $img_meta['width']  : 16;
		$img_h    = ! empty( $img_meta['height'] ) ? (int) $img_meta['height'] : 9;

		if ( $dim_w && $dim_h ) {
			$w_is_px = substr( $dim_w, -2 ) === 'px';
			$h_is_px = substr( $dim_h, -2 ) === 'px';

			if ( $w_is_px && $h_is_px ) {
				$svg            = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . (int) $dim_w . ' ' . (int) $dim_h . '"/>';
				$spacer_img_src = 'data:image/svg+xml,' . rawurlencode( $svg );
				$wrapper_style  = 'width:100%;max-width:' . $dim_w . ';aspect-ratio:' . (int) $dim_w . '/' . (int) $dim_h . ';';
				$explicit_size  = true;
			} else {
				$w_css         = $w_is_px ? 'min(' . $dim_w . ',100%)' : $dim_w;
				$wrapper_style = 'width:' . $w_css . ';height:' . $dim_h . ';';
				$explicit_size = true;
			}
		} elseif ( $dim_h ) {
			$wrapper_style = 'width:100%;height:' . $dim_h . ';';
			$explicit_size = true;
		} else {
			if ( $dim_w && substr( $dim_w, -2 ) === 'px' ) {
				$svg            = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $img_w . ' ' . $img_h . '"/>';
				$spacer_img_src = 'data:image/svg+xml,' . rawurlencode( $svg );
				$wrapper_style  = 'width:100%;max-width:' . $dim_w . ';aspect-ratio:' . $img_w . '/' . $img_h . ';';
			} else {
				$svg            = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $img_w . ' ' . $img_h . '"/>';
				$spacer_img_src = 'data:image/svg+xml,' . rawurlencode( $svg );
				$wrapper_style  = ( $dim_w ? 'width:' . $dim_w . ';' : 'width:100%;' ) . 'aspect-ratio:' . $img_w . '/' . $img_h . ';';
			}
		}

		// Fetch all published locations for this map.
		$location_posts = get_posts( [
			'post_type'      => 'bmg_location',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => [
				[
					'key'   => '_bmg_map_id',
					'value' => $map_id,
					'type'  => 'NUMERIC',
				],
			],
		] );

		$locations_data = [];
		foreach ( $location_posts as $loc ) {
			$x     = get_post_meta( $loc->ID, '_bmg_loc_x', true );
			$y     = get_post_meta( $loc->ID, '_bmg_loc_y', true );
			$color = get_post_meta( $loc->ID, '_bmg_loc_color', true ) ?: '#e74c3c';

			if ( $x === '' || $y === '' ) {
				continue;
			}

			$locations_data[] = [
				'index'       => count( $locations_data ),
				'title'       => $loc->post_title,
				'description' => wp_kses_post( wpautop( do_shortcode( $loc->post_content ) ) ),
				'x'           => (float) $x,
				'y'           => (float) $y,
				'color'       => $color,
				'icon'        => sanitize_text_field( get_post_meta( $loc->ID, '_bmg_loc_icon', true ) ),
			];
		}

		// Fetch all published areas for this map.
		$area_posts = get_posts( [
			'post_type'      => 'bmg_area',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => [
				[
					'key'   => '_bmg_area_map_id',
					'value' => $map_id,
					'type'  => 'NUMERIC',
				],
			],
		] );

		$areas_data = [];
		foreach ( $area_posts as $area ) {
			$points_raw = get_post_meta( $area->ID, '_bmg_area_points', true );
			$points     = json_decode( $points_raw, true );
			if ( ! is_array( $points ) || count( $points ) < 3 ) {
				continue;
			}

			$areas_data[] = [
				'index'       => count( $areas_data ),
				'title'       => $area->post_title,
				'description' => wp_kses_post( wpautop( do_shortcode( $area->post_content ) ) ),
				'points'      => $points,
				'color'       => get_post_meta( $area->ID, '_bmg_area_color',        true ) ?: '#3388ff',
				'fillColor'   => get_post_meta( $area->ID, '_bmg_area_fill_color',   true ) ?: '#3388ff',
				'fillOpacity' => (float) ( get_post_meta( $area->ID, '_bmg_area_fill_opacity', true ) ?: 0.2 ),
			];
		}

		// Enqueue frontend assets.
		wp_enqueue_style( 'leaflet' );
		wp_enqueue_style( 'bmg-public' );
		wp_enqueue_script( 'bmg-public' );

		$container_id = 'bmg-map-' . $map_id;
		$map_min_zoom = get_post_meta( $map_id, '_bmg_map_min_zoom', true );
		$map_max_zoom = get_post_meta( $map_id, '_bmg_map_max_zoom', true );

		// List config.
		$show_list    = $list_position !== 'none';
		$float_positions = [ 'float-tl', 'float-tr', 'float-bl', 'float-br' ];
		$is_floating  = in_array( $list_position, $float_positions, true );
		$show_search  = count( $locations_data ) >= 5;

		// Area list config.
		$show_area_list   = $area_list_position !== 'none';
		$is_area_floating = in_array( $area_list_position, $float_positions, true );
		$show_area_search = count( $areas_data ) >= 5;

		// When both lists share the same position they are stacked in a combined panel.
		$lists_combined = $show_list && $show_area_list && $list_position === $area_list_position;
		$needs_layout   = $show_list || $show_area_list;

		// Build outer layout class string.
		$layout_classes = 'bmg-map-layout';
		if ( $show_list ) {
			$layout_classes .= ' bmg-map-layout--list-' . $list_position;
		}
		if ( $show_area_list && ! $lists_combined ) {
			$layout_classes .= ' bmg-map-layout--area-list-' . $area_list_position;
		}

		ob_start();

		if ( $needs_layout ) {
			echo '<div class="' . esc_attr( $layout_classes ) . '">' . "\n";

			// Left side panels rendered before the map wrapper.
			if ( $lists_combined && $list_position === 'left' ) {
				echo '<div class="bmg-lists-panel">' . "\n";
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo self::render_location_list( $locations_data, $map->post_title, $show_search, $list_position, $list_title );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo self::render_area_list( $areas_data, $show_area_search, $area_list_title );
				echo '</div>' . "\n";
			} elseif ( $show_list && ! $is_floating && $list_position === 'left' ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo self::render_location_list( $locations_data, $map->post_title, $show_search, $list_position, $list_title );
			} elseif ( $show_area_list && ! $is_area_floating && $area_list_position === 'left' ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo self::render_area_list( $areas_data, $show_area_search, $area_list_title );
			}
		}
		?>
		<div class="bmg-map-aspect-wrapper"<?php echo $wrapper_style ? ' style="' . esc_attr( $wrapper_style ) . '"' : ''; ?><?php echo $explicit_size ? ' data-explicit-size="1"' : ''; ?>>
			<?php if ( $spacer_img_src ) : ?>
			<img class="bmg-map-aspect-spacer" src="<?php echo esc_attr( $spacer_img_src ); ?>" alt="" aria-hidden="true">
			<?php endif; ?>
			<?php
			$toolbar_buttons = '';
			if ( $list_position !== 'none' ) {
				$toolbar_buttons .= '<button class="bmg-toolbar-btn bmg-toolbar-btn--loc-list" type="button"'
					. ' aria-pressed="false"'
					. ' title="' . esc_attr__( 'Toggle location list', 'bmg-interactive-map' ) . '"'
					. ' aria-label="' . esc_attr__( 'Toggle location list', 'bmg-interactive-map' ) . '">'
					. '<span class="bmg-toolbar-icon bmg-toolbar-icon--loc" aria-hidden="true"></span>'
					. '</button>';
			}
			if ( $area_list_position !== 'none' ) {
				$toolbar_buttons .= '<button class="bmg-toolbar-btn bmg-toolbar-btn--area-list" type="button"'
					. ' aria-pressed="false"'
					. ' title="' . esc_attr__( 'Toggle area list', 'bmg-interactive-map' ) . '"'
					. ' aria-label="' . esc_attr__( 'Toggle area list', 'bmg-interactive-map' ) . '">'
					. '<span class="bmg-toolbar-icon bmg-toolbar-icon--area" aria-hidden="true"></span>'
					. '</button>';
			}
			$toolbar_buttons .= '<button class="bmg-toolbar-btn bmg-toolbar-btn--area-highlight" type="button"'
					. ' aria-pressed="false"'
					. ' title="' . esc_attr__( 'Toggle area highlights', 'bmg-interactive-map' ) . '"'
					. ' aria-label="' . esc_attr__( 'Toggle area highlights', 'bmg-interactive-map' ) . '">'
					. '<span class="bmg-toolbar-icon bmg-toolbar-icon--highlight" aria-hidden="true"></span>'
					. '</button>';
			$toolbar_buttons .= '<button class="bmg-toolbar-btn bmg-toolbar-btn--fullwindow" type="button"'
				. ' aria-pressed="false"'
				. ' title="' . esc_attr__( 'Fill window', 'bmg-interactive-map' ) . '"'
				. ' aria-label="' . esc_attr__( 'Fill window', 'bmg-interactive-map' ) . '">'
				. '<span class="bmg-toolbar-icon bmg-toolbar-icon--fw" aria-hidden="true"></span>'
				. '</button>';
			$toolbar_buttons .= '<button class="bmg-toolbar-btn bmg-toolbar-btn--fullscreen" type="button"'
				. ' aria-pressed="false"'
				. ' title="' . esc_attr__( 'Enter fullscreen', 'bmg-interactive-map' ) . '"'
				. ' aria-label="' . esc_attr__( 'Enter fullscreen', 'bmg-interactive-map' ) . '">'
				. '<span class="bmg-toolbar-icon bmg-toolbar-icon--fs" aria-hidden="true"></span>'
				. '</button>';

			// Pick toolbar zone: use manual override if set, otherwise auto-avoid floating list corners.
			if ( $toolbar_position !== '' ) {
				$toolbar_zone = $toolbar_position;
			} else {
				$float_corners = [ 'float-tl', 'float-tr', 'float-bl', 'float-br' ];
				$occupied      = [];
				if ( in_array( $list_position, $float_corners, true ) ) {
					$occupied[] = $list_position;
				}
				if ( in_array( $area_list_position, $float_corners, true ) ) {
					$occupied[] = $area_list_position;
				}
				$top_blocked = in_array( 'float-tl', $occupied ) || in_array( 'float-tr', $occupied );
				$bot_blocked = in_array( 'float-bl', $occupied ) || in_array( 'float-br', $occupied );
				if ( ! $top_blocked ) {
					$toolbar_zone = 'top';
				} elseif ( ! $bot_blocked ) {
					$toolbar_zone = 'bottom';
				} elseif ( ! in_array( 'float-tl', $occupied ) ) {
					$toolbar_zone = 'top-left';
				} elseif ( ! in_array( 'float-tr', $occupied ) ) {
					$toolbar_zone = 'top-right';
				} elseif ( ! in_array( 'float-bl', $occupied ) ) {
					$toolbar_zone = 'bottom-left';
				} else {
					$toolbar_zone = 'bottom-right';
				}
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<div class="bmg-map-toolbar bmg-map-toolbar--' . esc_attr( $toolbar_zone ) . '">' . $toolbar_buttons . '</div>'
				. '<button class="bmg-fs-exit-btn" type="button"'
				. ' aria-label="' . esc_attr__( 'Exit fullscreen', 'bmg-interactive-map' ) . '">'
				. esc_html__( 'Exit fullscreen', 'bmg-interactive-map' )
				. '</button>';
			?>
			<div class="bmg-map-container"
				id="<?php echo esc_attr( $container_id ); ?>"
				data-image="<?php echo esc_url( $image_url ); ?>"
				data-locations="<?php echo esc_attr( wp_json_encode( $locations_data, JSON_INVALID_UTF8_SUBSTITUTE ) ?: '[]' ); ?>"
				data-areas="<?php echo esc_attr( wp_json_encode( $areas_data, JSON_INVALID_UTF8_SUBSTITUTE ) ?: '[]' ); ?>"
				<?php echo $show_list ? 'data-show-list="1"' : ''; ?>
				<?php echo $show_area_list ? 'data-show-area-list="1"' : ''; ?>
				<?php echo ! empty( $atts['show_tooltips'] ) && $atts['show_tooltips'] !== '0' ? 'data-tooltips="1"' : ''; ?>
				<?php echo $zoom_position ? 'data-zoom-position="' . esc_attr( $zoom_position ) . '"' : ''; ?>
				<?php echo $map_min_zoom !== '' ? 'data-min-zoom="' . esc_attr( $map_min_zoom ) . '"' : ''; ?>
				<?php echo $map_max_zoom !== '' ? 'data-max-zoom="' . esc_attr( $map_max_zoom ) . '"' : ''; ?>
				<?php if ( $has_start ) : ?>
				data-start-zoom="<?php echo esc_attr( $start_zoom ); ?>"
				data-start-x="<?php echo esc_attr( $start_x ); ?>"
				data-start-y="<?php echo esc_attr( $start_y ); ?>"
				<?php endif; ?>
				<?php echo $responsive_start_json ? 'data-responsive-start="' . esc_attr( $responsive_start_json ) . '"' : ''; ?>
				<?php echo $close_icon_html ? 'data-close-icon="' . esc_attr( $close_icon_html ) . '"' : ''; ?>
				aria-label="<?php echo esc_attr( $map->post_title ); ?>">
			</div>
			<?php
			// Floating lists inside the map wrapper.
			if ( $lists_combined && $is_floating ) :
				echo '<div class="bmg-lists-panel bmg-lists-panel--' . esc_attr( $list_position ) . '">' . "\n";
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo self::render_location_list( $locations_data, $map->post_title, $show_search, $list_position, $list_title );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo self::render_area_list( $areas_data, $show_area_search, $area_list_title );
				echo '</div>' . "\n";
			else :
				if ( $is_floating ) :
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo self::render_location_list( $locations_data, $map->post_title, $show_search, $list_position, $list_title );
				endif;
				if ( $show_area_list && $is_area_floating ) :
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo self::render_area_list( $areas_data, $show_area_search, $area_list_title );
				endif;
			endif;
			?>
		</div>
		<?php
		if ( $needs_layout ) {
			// Right side panels rendered after the map wrapper.
			if ( $lists_combined && $list_position === 'right' ) {
				echo '<div class="bmg-lists-panel">' . "\n";
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo self::render_location_list( $locations_data, $map->post_title, $show_search, $list_position, $list_title );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo self::render_area_list( $areas_data, $show_area_search, $area_list_title );
				echo '</div>' . "\n";
			} elseif ( $show_list && ! $is_floating && $list_position === 'right' ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo self::render_location_list( $locations_data, $map->post_title, $show_search, $list_position, $list_title );
			} elseif ( $show_area_list && ! $is_area_floating && $area_list_position === 'right' ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo self::render_area_list( $areas_data, $show_area_search, $area_list_title );
			}
			echo '</div>' . "\n"; // close .bmg-map-layout
		}

		return ob_get_clean();
	}

	private static function render_location_list( array $locations, string $map_title, bool $show_search, string $list_position = 'none', string $list_title = '' ): string {
		$label = $list_title !== '' ? $list_title : __( 'Locations', 'bmg-interactive-map' );
		$html  = '<nav class="bmg-location-list" data-list-position="' . esc_attr( $list_position ) . '" aria-label="' . esc_attr( $map_title ) . ' locations">' . "\n";
		$html .= '<div class="bmg-location-list__header">'
			. '<span class="bmg-location-list__label">' . esc_html( $label ) . '</span>'
			. '<button class="bmg-location-list__toggle" type="button" aria-expanded="true" aria-label="' . esc_attr__( 'Collapse location list', 'bmg-interactive-map' ) . '">'
			. '<span class="bmg-location-list__toggle-icon" aria-hidden="true"></span>'
			. '</button>'
			. '</div>' . "\n";

		if ( $show_search ) {
			$html .= '<div class="bmg-location-search-wrap">'
				. '<input type="search" class="bmg-location-search"'
				. ' placeholder="' . esc_attr__( 'Search locations…', 'bmg-interactive-map' ) . '"'
				. ' aria-label="' . esc_attr__( 'Search locations', 'bmg-interactive-map' ) . '">'
				. '</div>' . "\n";
		}

		$html .= '<ul class="bmg-location-list__items">' . "\n";

		if ( empty( $locations ) ) {
			$html .= '<li class="bmg-location-list__empty">No locations.</li>' . "\n";
		} else {
			foreach ( $locations as $loc ) {
				$html .= '<li class="bmg-location-list__item"'
					. ' data-index="' . (int) $loc['index'] . '"'
					. ' role="button"'
					. ' tabindex="0"'
					. ' aria-label="' . esc_attr( $loc['title'] ) . '">'
					. '<span class="bmg-location-list__swatch" style="background:' . esc_attr( $loc['color'] ) . ';"></span>'
					. '<span class="bmg-location-list__title">' . esc_html( $loc['title'] ) . '</span>'
					. '</li>' . "\n";
			}
		}

		$html .= '</ul>' . "\n" . '</nav>' . "\n";
		return $html;
	}

	private static function render_area_list( array $areas, bool $show_search, string $list_title = '' ): string {
		$label = $list_title !== '' ? $list_title : __( 'Areas', 'bmg-interactive-map' );
		$html  = '<nav class="bmg-area-list" aria-label="' . esc_attr( $label ) . '">' . "\n";
		$html .= '<div class="bmg-location-list__header">'
			. '<span class="bmg-location-list__label">' . esc_html( $label ) . '</span>'
			. '<button class="bmg-location-list__toggle" type="button" aria-expanded="true" aria-label="' . esc_attr__( 'Collapse area list', 'bmg-interactive-map' ) . '">'
			. '<span class="bmg-location-list__toggle-icon" aria-hidden="true"></span>'
			. '</button>'
			. '</div>' . "\n";

		if ( $show_search ) {
			$html .= '<div class="bmg-location-search-wrap">'
				. '<input type="search" class="bmg-location-search"'
				. ' placeholder="' . esc_attr__( 'Search areas…', 'bmg-interactive-map' ) . '"'
				. ' aria-label="' . esc_attr__( 'Search areas', 'bmg-interactive-map' ) . '">'
				. '</div>' . "\n";
		}

		$html .= '<ul class="bmg-location-list__items">' . "\n";

		if ( empty( $areas ) ) {
			$html .= '<li class="bmg-location-list__empty">No areas.</li>' . "\n";
		} else {
			foreach ( $areas as $area ) {
				$html .= '<li class="bmg-location-list__item"'
					. ' data-index="' . (int) $area['index'] . '"'
					. ' role="button"'
					. ' tabindex="0"'
					. ' aria-label="' . esc_attr( $area['title'] ) . '">'
					. '<span class="bmg-location-list__swatch bmg-location-list__swatch--area" style="background:' . esc_attr( $area['color'] ) . ';"></span>'
					. '<span class="bmg-location-list__title">' . esc_html( $area['title'] ) . '</span>'
					. '</li>' . "\n";
			}
		}

		$html .= '</ul>' . "\n" . '</nav>' . "\n";
		return $html;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Normalise a width/height value to a CSS dimension string.
	 */
	private static function sanitize_dimension( $val ): string {
		$val = trim( (string) $val );
		if ( $val === '' || $val === '0' ) return '';
		if ( preg_match( '/^(\d+(?:\.\d+)?)%$/', $val ) )     return $val;
		if ( preg_match( '/^(\d+(?:\.\d+)?)px$/i', $val ) )   return (int) $val . 'px';
		if ( preg_match( '/^\d+$/', $val ) )                   return (int) $val . 'px';
		return '';
	}

	// -------------------------------------------------------------------------
	// Assets (registered early; enqueued only when shortcode is used)
	// -------------------------------------------------------------------------

	public static function register_assets(): void {
		wp_register_style(
			'leaflet',
			bmg_map_leaflet_url( 'leaflet.css' ),
			[],
			'1.9.4'
		);

		wp_register_script(
			'leaflet',
			bmg_map_leaflet_url( 'leaflet.js' ),
			[],
			'1.9.4',
			true
		);

		wp_register_style(
			'bmg-public',
			BMG_MAP_PLUGIN_URL . 'public/css/public.css',
			[ 'leaflet' ],
			BMG_MAP_VERSION
		);

		wp_register_script(
			'bmg-public',
			BMG_MAP_PLUGIN_URL . 'public/js/public.js',
			[ 'leaflet' ],
			BMG_MAP_VERSION,
			true
		);

		$s = BMG_Settings::get();
		wp_localize_script( 'bmg-public', 'bmgMapSettings', [
			'defaultColor' => $s['default_color'],
			'minZoom'      => $s['min_zoom'],
			'maxZoom'      => $s['max_zoom'],
			'zoomPosition' => $s['zoom_position'],
		] );

	}
}
