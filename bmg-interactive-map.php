<?php
/**
 * Plugin Name: BMG Interactive Map
 * Description: Renders interactive custom image-based maps with clickable location markers, managed via custom post types.
 * Version:     4.0.6
 * Author:      BMG
 * License:     GPL-2.0-or-later
 * Text Domain: bmg-interactive-map
 */

defined( 'ABSPATH' ) || exit;

// Guard against fatal errors if an older copy of this plugin is still present.
if ( defined( 'BMG_MAP_VERSION' ) ) {
	return;
}

define( 'BMG_MAP_VERSION',    '4.0.6' );
define( 'BMG_MAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BMG_MAP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once BMG_MAP_PLUGIN_DIR . 'includes/class-bmg-settings.php';
require_once BMG_MAP_PLUGIN_DIR . 'includes/class-bmg-map-cpt.php';
require_once BMG_MAP_PLUGIN_DIR . 'includes/class-bmg-location-cpt.php';
require_once BMG_MAP_PLUGIN_DIR . 'includes/class-bmg-area-cpt.php';
require_once BMG_MAP_PLUGIN_DIR . 'includes/class-bmg-shortcode.php';
require_once BMG_MAP_PLUGIN_DIR . 'includes/class-bmg-tileset.php';
require_once BMG_MAP_PLUGIN_DIR . 'includes/class-bmg-block.php';
require_once BMG_MAP_PLUGIN_DIR . 'includes/class-bmg-help.php';

add_action( 'plugins_loaded', function () {
	BMG_Settings::init();
	BMG_Map_CPT::init();
	BMG_Location_CPT::init();
	BMG_Area_CPT::init();
	BMG_Shortcode::init();
	BMG_Tileset::init();
	BMG_Block::init();
	BMG_Help::init();
} );

// Elementor widget — class is loaded lazily; only registered when Elementor is active.
add_action( 'elementor/widgets/register', function ( \Elementor\Widgets_Manager $widgets_manager ) {
	require_once BMG_MAP_PLUGIN_DIR . 'includes/class-bmg-elementor-widget.php';
	$widgets_manager->register( new BMG_Elementor_Widget() );
} );

// Ensure BMG assets are registered during Elementor's asset-registration phase
// so get_script_depends() / get_style_depends() can resolve the handles.
add_action( 'elementor/frontend/after_register_scripts', [ 'BMG_Shortcode', 'register_assets' ] );

// Explicitly enqueue BMG assets for the Elementor preview iframe and for the
// frontend when the BMG widget is present.  This covers configurations where
// Elementor's improved/optimised asset loading is disabled and get_script_depends()
// is not used, as well as the editor preview where wp_footer() fires normally
// but only after the preview iframe's own wp_enqueue_scripts phase.
add_action( 'elementor/preview/enqueue_styles', function () {
	BMG_Shortcode::register_assets();
	wp_enqueue_style( 'leaflet' );
	wp_enqueue_style( 'bmg-public' );
} );
add_action( 'elementor/preview/enqueue_scripts', function () {
	BMG_Shortcode::register_assets();
	wp_enqueue_script( 'bmg-public' );
} );

// -------------------------------------------------------------------------
// Activation: download and bundle Leaflet locally so the plugin does not
// depend on an external CDN at runtime.
// -------------------------------------------------------------------------

register_activation_hook( __FILE__, 'bmg_map_activate' );

function bmg_map_activate(): void {
	$dir = BMG_MAP_PLUGIN_DIR . 'lib/leaflet/';
	wp_mkdir_p( $dir );

	$files = [
		'leaflet.css' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
		'leaflet.js'  => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
	];

	foreach ( $files as $filename => $url ) {
		$local_path = $dir . $filename;
		if ( file_exists( $local_path ) ) {
			continue;
		}
		$response = wp_remote_get( $url, [ 'timeout' => 20 ] );
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $local_path, wp_remote_retrieve_body( $response ) );
		}
	}
}

// -------------------------------------------------------------------------
// Helper: return local Leaflet asset URL, falling back to CDN if the file
// was not downloaded during activation.
// -------------------------------------------------------------------------

function bmg_map_leaflet_url( string $file ): string {
	$local_path = BMG_MAP_PLUGIN_DIR . 'lib/leaflet/' . $file;
	if ( file_exists( $local_path ) ) {
		return BMG_MAP_PLUGIN_URL . 'lib/leaflet/' . $file;
	}
	return 'https://unpkg.com/leaflet@1.9.4/dist/' . $file;
}

// -------------------------------------------------------------------------
// Self-update helper: allow zip-upload updates without a "Destination folder
// already exists" error.  These hooks fire during the same admin request as
// the zip upload and are only triggered when the uploaded package belongs to
// this plugin.
// -------------------------------------------------------------------------

/**
 * Detect when THIS plugin's zip is being installed and stash the destination
 * path in a short-lived transient so the pre-install hook can clear it.
 *
 * @param string $source      Extracted temp directory path.
 * @param string $remote_source Unused.
 * @param object $upgrader    WP_Upgrader instance.
 * @return string $source unchanged.
 */
add_filter( 'upgrader_source_selection', function ( $source, $remote_source, $upgrader ) {
	if ( false !== strpos( $source, 'bmg-interactive-map' ) ) {
		$plugin_dir = WP_PLUGIN_DIR . '/bmg-interactive-map';
		set_transient( 'bmg_map_upgrading', $plugin_dir, 60 );
	}
	return $source;
}, 10, 3 );

/**
 * Just before WordPress moves the extracted files into place, remove the
 * existing plugin folder so WordPress does not complain it already exists.
 *
 * @param bool|WP_Error $result   Current result (passed through unchanged).
 * @param array         $hook_extra Extra data about the upgrade.
 * @return bool|WP_Error $result unchanged.
 */
add_filter( 'upgrader_pre_install', function ( $result, $hook_extra ) {
	$plugin_dir = get_transient( 'bmg_map_upgrading' );
	if ( ! $plugin_dir ) {
		return $result;
	}

	delete_transient( 'bmg_map_upgrading' );

	global $wp_filesystem;
	if ( ! $wp_filesystem ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	}

	if ( $wp_filesystem && $wp_filesystem->is_dir( $plugin_dir ) ) {
		$wp_filesystem->delete( $plugin_dir, true );
	}

	return $result;
}, 10, 2 );

// -------------------------------------------------------------------------
// On every load: silently remove any .git directory left inside the plugin
// folder (e.g. from a git-clone install).  The is_dir() check is cheap so
// WP_Filesystem is only initialised when the folder actually exists.
// -------------------------------------------------------------------------

add_action( 'plugins_loaded', function () {
	$git_dir = BMG_MAP_PLUGIN_DIR . '.git';
	if ( ! is_dir( $git_dir ) ) {
		return;
	}
	global $wp_filesystem;
	if ( ! $wp_filesystem ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	}
	if ( $wp_filesystem ) {
		$wp_filesystem->delete( $git_dir, true );
	}
} );
