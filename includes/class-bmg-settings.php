<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page for BMG Interactive Map.
 *
 * Stores: default marker colour, min/max zoom levels.
 * Access: Interactive Maps → Settings
 */
class BMG_Settings {

	const OPTION_KEY = 'bmg_map_settings';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_settings_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public static function add_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=bmg_map',
			__( 'Map Settings', 'bmg-interactive-map' ),
			__( 'Settings', 'bmg-interactive-map' ),
			'manage_options',
			'bmg-map-settings',
			[ __CLASS__, 'render_page' ]
		);
	}

	// -------------------------------------------------------------------------
	// Settings API
	// -------------------------------------------------------------------------

	public static function register_settings(): void {
		register_setting( 'bmg_map_settings_group', self::OPTION_KEY, [
			'sanitize_callback' => [ __CLASS__, 'sanitize' ],
		] );

		add_settings_section(
			'bmg_defaults',
			__( 'Defaults', 'bmg-interactive-map' ),
			null,
			'bmg-map-settings'
		);

		add_settings_field(
			'default_color',
			__( 'Default Marker Colour', 'bmg-interactive-map' ),
			[ __CLASS__, 'field_color' ],
			'bmg-map-settings',
			'bmg_defaults'
		);

		add_settings_field(
			'min_zoom',
			__( 'Min Zoom', 'bmg-interactive-map' ),
			[ __CLASS__, 'field_min_zoom' ],
			'bmg-map-settings',
			'bmg_defaults'
		);

		add_settings_field(
			'max_zoom',
			__( 'Max Zoom', 'bmg-interactive-map' ),
			[ __CLASS__, 'field_max_zoom' ],
			'bmg-map-settings',
			'bmg_defaults'
		);

		add_settings_field(
			'zoom_position',
			__( 'Zoom Control Position', 'bmg-interactive-map' ),
			[ __CLASS__, 'field_zoom_position' ],
			'bmg-map-settings',
			'bmg_defaults'
		);

		add_settings_section(
			'bmg_named_colors',
			__( 'Named Colors', 'bmg-interactive-map' ),
			null,
			'bmg-map-settings'
		);

		add_settings_field(
			'named_colors',
			__( 'Color Palette', 'bmg-interactive-map' ),
			[ __CLASS__, 'field_named_colors' ],
			'bmg-map-settings',
			'bmg_named_colors'
		);

	}

	public static function sanitize( $input ): array {
		$clean = [];
		$clean['default_color'] = sanitize_hex_color( $input['default_color'] ?? '' ) ?: '#e74c3c';
		$clean['min_zoom']      = max( -5, min( 0, (int) ( $input['min_zoom'] ?? -3 ) ) );
		$clean['max_zoom']      = max( 1,  min( 5, (int) ( $input['max_zoom'] ?? 3  ) ) );

		$valid_zoom_positions   = [ 'topleft', 'topright', 'bottomleft', 'bottomright' ];
		$clean['zoom_position'] = in_array( $input['zoom_position'] ?? '', $valid_zoom_positions, true )
			? $input['zoom_position']
			: 'topleft';

		$clean['named_colors'] = [];
		foreach ( (array) ( $input['named_colors'] ?? [] ) as $entry ) {
			$name = sanitize_text_field( $entry['name'] ?? '' );
			$hex  = sanitize_hex_color( $entry['hex'] ?? '' );
			if ( $name !== '' && $hex ) {
				$clean['named_colors'][] = [ 'name' => $name, 'hex' => $hex ];
			}
		}

		return $clean;
	}

	// -------------------------------------------------------------------------
	// Field renderers
	// -------------------------------------------------------------------------

	public static function field_zoom_position(): void {
		$opts    = self::get();
		$current = $opts['zoom_position'];
		$choices = [
			'topleft'     => __( 'Top Left (default)', 'bmg-interactive-map' ),
			'topright'    => __( 'Top Right',          'bmg-interactive-map' ),
			'bottomleft'  => __( 'Bottom Left',        'bmg-interactive-map' ),
			'bottomright' => __( 'Bottom Right',       'bmg-interactive-map' ),
		];
		echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[zoom_position]">';
		foreach ( $choices as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Can be overridden per map in the block or widget settings.', 'bmg-interactive-map' ) . '</p>';
	}

	public static function field_color(): void {
		$opts = self::get();
		printf(
			'<input type="color" name="%s[default_color]" value="%s" />',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $opts['default_color'] )
		);
	}

	public static function field_min_zoom(): void {
		$opts = self::get();
		printf(
			'<input type="number" name="%1$s[min_zoom]" value="%2$s" min="-5" max="0" style="width:60px;" />
			<p class="description">%3$s</p>',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $opts['min_zoom'] ),
			esc_html__( 'Negative values allow zooming out beyond the image bounds.', 'bmg-interactive-map' )
		);
	}

	public static function field_max_zoom(): void {
		$opts = self::get();
		printf(
			'<input type="number" name="%s[max_zoom]" value="%s" min="1" max="5" style="width:60px;" />',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $opts['max_zoom'] )
		);
	}

	public static function field_named_colors(): void {
		$colors = self::get()['named_colors'];
		$key    = esc_attr( self::OPTION_KEY );
		$label_name    = esc_attr__( 'Color name', 'bmg-interactive-map' );
		$label_remove  = esc_html__( 'Remove', 'bmg-interactive-map' );
		$label_add     = esc_html__( '+ Add Color', 'bmg-interactive-map' );
		?>
		<div id="bmg-named-colors-list">
			<?php foreach ( $colors as $i => $entry ) : ?>
			<div class="bmg-named-color-row" style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">
				<input type="text"
					name="<?php echo $key; ?>[named_colors][<?php echo (int) $i; ?>][name]"
					value="<?php echo esc_attr( $entry['name'] ); ?>"
					placeholder="<?php echo $label_name; ?>"
					style="width:200px;" />
				<input type="color"
					name="<?php echo $key; ?>[named_colors][<?php echo (int) $i; ?>][hex]"
					value="<?php echo esc_attr( $entry['hex'] ); ?>" />
				<button type="button" class="button button-link-delete bmg-remove-color"><?php echo $label_remove; ?></button>
			</div>
			<?php endforeach; ?>
		</div>
		<button type="button" id="bmg-add-named-color" class="button"><?php echo $label_add; ?></button>
		<p class="description"><?php esc_html_e( 'Define reusable named colours that editors can pick by name in the location and area editors.', 'bmg-interactive-map' ); ?></p>
		<script>
		( function () {
			var list   = document.getElementById( 'bmg-named-colors-list' );
			var addBtn = document.getElementById( 'bmg-add-named-color' );
			var optKey = <?php echo wp_json_encode( self::OPTION_KEY ); ?>;
			var phName = <?php echo wp_json_encode( __( 'Color name', 'bmg-interactive-map' ) ); ?>;
			var lblRem = <?php echo wp_json_encode( __( 'Remove', 'bmg-interactive-map' ) ); ?>;

			function reindex() {
				list.querySelectorAll( '.bmg-named-color-row' ).forEach( function ( row, i ) {
					row.querySelectorAll( 'input' ).forEach( function ( inp ) {
						inp.name = inp.name.replace( /\[named_colors\]\[\d+\]/, '[named_colors][' + i + ']' );
					} );
				} );
			}

			list.addEventListener( 'click', function ( e ) {
				if ( e.target.classList.contains( 'bmg-remove-color' ) ) {
					e.target.closest( '.bmg-named-color-row' ).remove();
					reindex();
				}
			} );

			addBtn.addEventListener( 'click', function () {
				var i   = list.querySelectorAll( '.bmg-named-color-row' ).length;
				var row = document.createElement( 'div' );
				row.className = 'bmg-named-color-row';
				row.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:6px;';
				row.innerHTML =
					'<input type="text" name="' + optKey + '[named_colors][' + i + '][name]" value="" placeholder="' + phName + '" style="width:200px;" />' +
					'<input type="color" name="' + optKey + '[named_colors][' + i + '][hex]" value="#3388ff" />' +
					'<button type="button" class="button button-link-delete bmg-remove-color">' + lblRem + '</button>';
				list.appendChild( row );
			} );
		} )();
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Interactive Map Settings', 'bmg-interactive-map' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'bmg_map_settings_group' );
				do_settings_sections( 'bmg-map-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helper: get settings with defaults
	// -------------------------------------------------------------------------

	public static function get(): array {
		static $cache = null;
		if ( $cache === null ) {
			$defaults = [
				'default_color' => '#e74c3c',
				'min_zoom'      => -3,
				'max_zoom'      => 3,
				'zoom_position' => 'topleft',
				'named_colors'  => [],
			];
			$cache = wp_parse_args( (array) get_option( self::OPTION_KEY, [] ), $defaults );
		}
		return $cache;
	}
}
