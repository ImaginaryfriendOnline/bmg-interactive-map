<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers the "bmg/interactive-map" Gutenberg block.
 *
 * Server-side rendered — no build step required. The block editor UI is
 * provided by blocks/bmg-map/editor.js using vanilla wp.* globals.
 */
class BMG_Block {

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register' ] );
	}

	public static function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'bmg-map-block-editor',
			BMG_MAP_PLUGIN_URL . 'blocks/bmg-map/editor.js',
			[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ],
			BMG_MAP_VERSION,
			true
		);

		// Provide published maps to the block editor sidebar (admin only).
		$maps = is_admin() ? get_posts( [
			'post_type'      => 'bmg_map',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] ) : [];

		wp_localize_script( 'bmg-map-block-editor', 'bmgMapBlock', [
			'maps' => array_map( function ( WP_Post $m ) {
				return [ 'value' => $m->ID, 'label' => $m->post_title ];
			}, $maps ),
		] );

		register_block_type( 'bmg/interactive-map', [
			'api_version'     => 2,
			'editor_script'   => 'bmg-map-block-editor',
			'attributes'      => [
				'mapId'        => [ 'type' => 'number',  'default' => 0      ],
				'width'        => [ 'type' => 'string',  'default' => ''     ],
				'height'       => [ 'type' => 'string',  'default' => ''     ],
				'listPosition' => [ 'type' => 'string',  'default' => 'none' ],
				'zoomPosition' => [ 'type' => 'string',  'default' => ''     ],
				'showTooltips' => [ 'type' => 'boolean', 'default' => false  ],
			],
			'render_callback' => [ __CLASS__, 'render' ],
		] );
	}

	public static function render( array $attributes ): string {
		$map_id = absint( $attributes['mapId'] ?? 0 );
		if ( ! $map_id ) {
			return '<p style="border:1px dashed #ccc;padding:12px;">'
				. esc_html__( 'Interactive Map: select a map in the block settings panel.', 'bmg-interactive-map' )
				. '</p>';
		}
		$valid_positions = [ 'left', 'right', 'above', 'below', 'float-tl', 'float-tr', 'float-bl', 'float-br', 'none' ];
		$list_position   = isset( $attributes['listPosition'] ) && in_array( $attributes['listPosition'], $valid_positions, true )
			? $attributes['listPosition']
			: 'none';

		$valid_zoom_pos = [ 'topleft', 'topright', 'bottomleft', 'bottomright' ];
		$zoom_position  = isset( $attributes['zoomPosition'] ) && in_array( $attributes['zoomPosition'], $valid_zoom_pos, true )
			? $attributes['zoomPosition']
			: '';

		return BMG_Shortcode::render( [
			'id'            => $map_id,
			'width'         => (string) ( $attributes['width']  ?? '' ),
			'height'        => (string) ( $attributes['height'] ?? '' ),
			'list_position' => $list_position,
			'zoom_position' => $zoom_position,
			'show_tooltips' => ! empty( $attributes['showTooltips'] ) ? '1' : '0',
		] );
	}
}
