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
				'default_color'       => '#e74c3c',
				'min_zoom'            => -3,
				'max_zoom'            => 3,
				'zoom_position'       => 'topleft',
			];
			$cache = wp_parse_args( (array) get_option( self::OPTION_KEY, [] ), $defaults );
		}
		return $cache;
	}
}
