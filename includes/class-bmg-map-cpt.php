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
			'supports'            => [ 'title', 'editor', 'thumbnail' ],
			'has_archive'         => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'show_in_rest'        => true,
		] );
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
