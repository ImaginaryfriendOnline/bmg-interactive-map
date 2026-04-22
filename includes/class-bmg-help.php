<?php
defined( 'ABSPATH' ) || exit;

/**
 * "How to Use" help page under Interactive Maps → How to Use.
 */
class BMG_Help {

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_page' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_styles' ] );
	}

	public static function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=bmg_map',
			__( 'How to Use', 'bmg-interactive-map' ),
			__( 'How to Use', 'bmg-interactive-map' ),
			'edit_posts',
			'bmg-map-help',
			[ __CLASS__, 'render' ]
		);
	}

	public static function enqueue_styles( string $hook ): void {
		if ( strpos( $hook, 'bmg-map-help' ) === false ) {
			return;
		}
		wp_add_inline_style( 'wp-admin', self::inline_css() );
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	public static function render(): void {
		$settings_url = admin_url( 'edit.php?post_type=bmg_map&page=bmg-map-settings' );
		?>
		<div class="wrap bmg-help-wrap">

			<h1 class="bmg-help-title">
				<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'How to Use Interactive Maps', 'bmg-interactive-map' ); ?>
			</h1>

			<p class="bmg-help-intro">
				<?php esc_html_e( 'Follow the steps below to create a map, add locations and areas to it, and display it on any page or post. Additional reference sections follow.', 'bmg-interactive-map' ); ?>
			</p>

			<!-- ── Step 1 ─────────────────────────────────────────────── -->
			<div class="bmg-help-card">
				<div class="bmg-help-step-badge">1</div>
				<div class="bmg-help-step-body">
					<h2><?php esc_html_e( 'Create a Map', 'bmg-interactive-map' ); ?></h2>
					<ol>
						<li>
							<?php
							printf(
								/* translators: %s: link to add new map */
								esc_html__( 'Go to %s.', 'bmg-interactive-map' ),
								'<a href="' . esc_url( admin_url( 'post-new.php?post_type=bmg_map' ) ) . '">'
								. esc_html__( 'Interactive Maps → Add New Map', 'bmg-interactive-map' )
								. '</a>'
							);
							?>
						</li>
						<li><?php esc_html_e( 'Give the map a title (e.g. "World Map" or "Dungeon Level 1").', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Set a Featured Image — this is the background image that markers and areas are placed on. Use any image: a photo, a hand-drawn map, a floor plan, etc.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( '(Optional) Set per-map zoom limits in the Zoom Settings panel on the right. Leave blank to use the global defaults from Settings.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( '(Optional) Generate a tileset in the Tileset panel — see the Tilesets section below for details.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Publish the map. Note the post ID in the URL bar — you will need it in Step 4.', 'bmg-interactive-map' ); ?></li>
					</ol>
					<div class="bmg-help-tip">
						<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
						<?php esc_html_e( 'Tip: the map ID appears in the address bar when editing, e.g. post=42.', 'bmg-interactive-map' ); ?>
					</div>
				</div>
			</div>

			<!-- ── Step 2 ─────────────────────────────────────────────── -->
			<div class="bmg-help-card">
				<div class="bmg-help-step-badge">2</div>
				<div class="bmg-help-step-body">
					<h2><?php esc_html_e( 'Add Locations', 'bmg-interactive-map' ); ?></h2>
					<ol>
						<li>
							<?php
							printf(
								/* translators: %s: link to add new location */
								esc_html__( 'Go to %s.', 'bmg-interactive-map' ),
								'<a href="' . esc_url( admin_url( 'post-new.php?post_type=bmg_location' ) ) . '">'
								. esc_html__( 'Interactive Maps → Locations → Add New Location', 'bmg-interactive-map' )
								. '</a>'
							);
							?>
						</li>
						<li><?php esc_html_e( 'Enter a title — shown as the heading in the marker popup.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Add a description in the content area — shown below the title in the popup.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'In the Location Settings panel, choose the Parent Map from Step 1.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'The map image loads in the visual editor. Click anywhere on it to drop a marker, or drag the marker to reposition it. You can also type X % and Y % values manually.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( '(Optional) Pick a custom marker colour and Font Awesome icon class. The default colour is set under Interactive Maps → Settings.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Publish the location. Repeat for as many locations as needed.', 'bmg-interactive-map' ); ?></li>
					</ol>
					<div class="bmg-help-tip">
						<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
						<?php esc_html_e( 'Tip: only Published locations appear on the frontend. Draft or private locations are hidden.', 'bmg-interactive-map' ); ?>
					</div>
				</div>
			</div>

			<!-- ── Step 3 ─────────────────────────────────────────────── -->
			<div class="bmg-help-card">
				<div class="bmg-help-step-badge">3</div>
				<div class="bmg-help-step-body">
					<h2><?php esc_html_e( 'Add Areas (optional)', 'bmg-interactive-map' ); ?></h2>
					<p><?php esc_html_e( 'Areas are polygon overlays — regions, zones, or districts drawn directly on the map. They render as outlines at rest, highlight with a fill on hover, and open a popup when clicked.', 'bmg-interactive-map' ); ?></p>
					<ol>
						<li>
							<?php
							printf(
								/* translators: %s: link to add new area */
								esc_html__( 'Go to %s.', 'bmg-interactive-map' ),
								'<a href="' . esc_url( admin_url( 'post-new.php?post_type=bmg_area' ) ) . '">'
								. esc_html__( 'Interactive Maps → Areas → Add New Area', 'bmg-interactive-map' )
								. '</a>'
							);
							?>
						</li>
						<li><?php esc_html_e( 'Enter a title — shown as the name tooltip on hover and the heading in the popup.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Add a description in the content area — shown in the popup body.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'In the Area Settings panel, choose the Parent Map.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'The map image loads in the visual polygon editor. Click anywhere on the image to add a vertex. Add at least 3 vertices to form a polygon. Drag any vertex to reposition it.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Use the Undo button to remove the last vertex, or Clear to start again.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( '(Optional) Adjust the stroke colour, fill colour, and fill opacity.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Publish the area. Repeat for as many areas as needed.', 'bmg-interactive-map' ); ?></li>
					</ol>
					<div class="bmg-help-tip">
						<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
						<?php esc_html_e( 'Tip: areas with fewer than 3 vertices are silently skipped on the frontend. Save them as drafts until they are complete.', 'bmg-interactive-map' ); ?>
					</div>
				</div>
			</div>

			<!-- ── Step 4 ─────────────────────────────────────────────── -->
			<div class="bmg-help-card">
				<div class="bmg-help-step-badge">4</div>
				<div class="bmg-help-step-body">
					<h2><?php esc_html_e( 'Display the Map', 'bmg-interactive-map' ); ?></h2>

					<!-- Option A: Shortcode -->
					<h3><?php esc_html_e( 'Option A — Shortcode', 'bmg-interactive-map' ); ?></h3>
					<p><?php esc_html_e( 'Works in the Classic Editor, text widgets, and any field that processes shortcodes.', 'bmg-interactive-map' ); ?></p>
					<pre class="bmg-help-code"><code>[bmg_map id="42"]</code></pre>

					<p><?php esc_html_e( 'All shortcode parameters:', 'bmg-interactive-map' ); ?></p>
					<table class="bmg-help-table widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Parameter', 'bmg-interactive-map' ); ?></th>
								<th><?php esc_html_e( 'Default', 'bmg-interactive-map' ); ?></th>
								<th><?php esc_html_e( 'Description', 'bmg-interactive-map' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>id</code></td>
								<td><em><?php esc_html_e( '(required)', 'bmg-interactive-map' ); ?></em></td>
								<td><?php esc_html_e( 'Post ID of the map to display.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><code>width</code></td>
								<td><em><?php esc_html_e( '(fill column)', 'bmg-interactive-map' ); ?></em></td>
								<td><?php esc_html_e( 'Width as pixels or percent, e.g. 800px or 100%. Leave blank to fill the column.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><code>height</code></td>
								<td><em><?php esc_html_e( '(aspect ratio)', 'bmg-interactive-map' ); ?></em></td>
								<td><?php esc_html_e( 'Height as pixels or percent, e.g. 600px. Leave blank to derive height from the image aspect ratio.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><code>list_position</code></td>
								<td><code>none</code></td>
								<td>
									<?php esc_html_e( 'Show a location list. Values:', 'bmg-interactive-map' ); ?>
									<code>none</code>, <code>left</code>, <code>right</code>,
									<code>float-tl</code>, <code>float-tr</code>, <code>float-bl</code>, <code>float-br</code>
								</td>
							</tr>
							<tr>
								<td><code>list_title</code></td>
								<td><code>Locations</code></td>
								<td><?php esc_html_e( 'Label shown in the location list header.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><code>area_list_position</code></td>
								<td><code>none</code></td>
								<td>
									<?php esc_html_e( 'Show an area list. Same values as list_position.', 'bmg-interactive-map' ); ?>
								</td>
							</tr>
							<tr>
								<td><code>area_list_title</code></td>
								<td><code>Areas</code></td>
								<td><?php esc_html_e( 'Label shown in the area list header.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><code>zoom_position</code></td>
								<td><em><?php esc_html_e( '(site setting)', 'bmg-interactive-map' ); ?></em></td>
								<td>
									<?php esc_html_e( 'Override the zoom control corner. Values:', 'bmg-interactive-map' ); ?>
									<code>topleft</code>, <code>topright</code>, <code>bottomleft</code>, <code>bottomright</code>
								</td>
							</tr>
							<tr>
								<td><code>show_tooltips</code></td>
								<td><code>0</code></td>
								<td><?php esc_html_e( 'Show location name on marker hover. Set to 1 to enable.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><code>start_zoom</code></td>
								<td><em><?php esc_html_e( '(fit all)', 'bmg-interactive-map' ); ?></em></td>
								<td><?php esc_html_e( 'Starting Leaflet zoom level, e.g. -1 or 0. Must be set together with start_x and start_y.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><code>start_x</code></td>
								<td><em><?php esc_html_e( '(fit all)', 'bmg-interactive-map' ); ?></em></td>
								<td><?php esc_html_e( 'Starting center X as a percentage of the image width (0–100). Use together with start_zoom and start_y.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><code>start_y</code></td>
								<td><em><?php esc_html_e( '(fit all)', 'bmg-interactive-map' ); ?></em></td>
								<td><?php esc_html_e( 'Starting center Y as a percentage of the image height (0–100). Use together with start_zoom and start_x.', 'bmg-interactive-map' ); ?></td>
							</tr>
						</tbody>
					</table>

					<pre class="bmg-help-code" style="margin-top:12px;"><code>[bmg_map id="42" list_position="right" area_list_position="right"]
[bmg_map id="42" list_position="float-tl" area_list_position="float-br"]
[bmg_map id="42" start_zoom="-1" start_x="30" start_y="60"]</code></pre>

					<!-- Option B: Gutenberg -->
					<h3 style="margin-top:24px;"><?php esc_html_e( 'Option B — Block Editor (Gutenberg)', 'bmg-interactive-map' ); ?></h3>
					<ol>
						<li><?php esc_html_e( 'Open the page or post in the Block Editor.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Click the + button, search for "Interactive Map", and insert the block.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Choose your map from the dropdown in the block placeholder or the Settings panel on the right.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'In the Settings panel you can also set Width, Height, Zoom Control Position, Show Name on Hover, Location List Position, and Area List Position.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Update or publish the page.', 'bmg-interactive-map' ); ?></li>
					</ol>

					<!-- Option C: Elementor -->
					<h3 style="margin-top:24px;"><?php esc_html_e( 'Option C — Elementor Widget', 'bmg-interactive-map' ); ?></h3>
					<ol>
						<li><?php esc_html_e( 'Open the page in the Elementor editor.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Search for "Interactive Map" in the widget panel and drag it onto the canvas.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Content tab → Map Settings: choose a map, set Width, Location List Position, Area List Position, and other layout options.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Map Height and Starting Zoom/Center support per-breakpoint values — switch between Desktop / Tablet / Mobile using the responsive toggle to set different values for each screen size.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Use the Hide Toolbar responsive switcher to show or hide the toolbar per breakpoint.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Style tab: customise the toolbar, map background, location list, area list, popup, tooltip, and close button.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Save and publish the page.', 'bmg-interactive-map' ); ?></li>
					</ol>

					<div class="bmg-help-tip">
						<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
						<?php esc_html_e( 'Tip: the map is fully responsive and adapts to any column width automatically. Use the Elementor responsive controls to fine-tune height and starting view for mobile visitors.', 'bmg-interactive-map' ); ?>
					</div>
				</div>
			</div>

			<!-- ── Tilesets ─────────────────────────────────────────────── -->
			<div class="bmg-help-card">
				<div class="bmg-help-step-badge">
					<span class="dashicons dashicons-grid-view" style="font-size:18px;line-height:28px;" aria-hidden="true"></span>
				</div>
				<div class="bmg-help-step-body">
					<h2><?php esc_html_e( 'Tilesets', 'bmg-interactive-map' ); ?></h2>
					<p><?php esc_html_e( 'By default each map loads its full-resolution background image in one piece. For large images this can be slow. The Tileset option pre-slices the image into 256×256 px tiles at multiple zoom levels so the browser loads only what is currently visible.', 'bmg-interactive-map' ); ?></p>

					<h3><?php esc_html_e( 'Generating a Tileset', 'bmg-interactive-map' ); ?></h3>
					<ol>
						<li><?php esc_html_e( 'Open the map in the admin editor.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Find the Tileset panel in the right-hand sidebar.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Click Generate Tileset. A progress bar shows how many zoom levels have been processed. Leave the page open until the status reads Ready.', 'bmg-interactive-map' ); ?></li>
					</ol>

					<p><?php esc_html_e( 'Generation runs one zoom level at a time via AJAX — no page-reload or server timeout required. Tiles are stored as static JPEG files in wp-content/uploads/bmg-tiles/ and served directly by the web server.', 'bmg-interactive-map' ); ?></p>

					<h3><?php esc_html_e( 'Staleness', 'bmg-interactive-map' ); ?></h3>
					<p><?php esc_html_e( 'If you replace the map\'s featured image the Tileset panel shows a warning and the frontend falls back to the full image overlay automatically. Click Regenerate Tileset to rebuild the tiles from the new image.', 'bmg-interactive-map' ); ?></p>

					<h3><?php esc_html_e( 'Deleting a Tileset', 'bmg-interactive-map' ); ?></h3>
					<p><?php esc_html_e( 'Click Delete Tileset to remove all tile files from disk. The map reverts to image-overlay mode. Tile files are also removed automatically when the map post is permanently deleted.', 'bmg-interactive-map' ); ?></p>

					<div class="bmg-help-tip">
						<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
						<?php esc_html_e( 'Tip: tilesets require the PHP GD extension (available on virtually all hosts). The map\'s featured image must be stored in the WordPress media library — external image URLs are not supported. Supported formats: JPEG, PNG, GIF, WebP.', 'bmg-interactive-map' ); ?>
					</div>
				</div>
			</div>

			<!-- ── Location & Area Lists ─────────────────────────────── -->
			<div class="bmg-help-card">
				<div class="bmg-help-step-badge">
					<span class="dashicons dashicons-list-view" style="font-size:18px;line-height:28px;" aria-hidden="true"></span>
				</div>
				<div class="bmg-help-step-body">
					<h2><?php esc_html_e( 'Location &amp; Area Lists', 'bmg-interactive-map' ); ?></h2>
					<p><?php esc_html_e( 'Both the location list and the area list are optional scrollable panels. Each can be placed independently using the same set of position values. The list and map stay in sync — clicking a list item navigates the map to that item; clicking a marker or area highlights the matching list item.', 'bmg-interactive-map' ); ?></p>

					<table class="bmg-help-table widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Position value', 'bmg-interactive-map' ); ?></th>
								<th><?php esc_html_e( 'Description', 'bmg-interactive-map' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr><td><code>none</code></td><td><?php esc_html_e( 'No list shown (default).', 'bmg-interactive-map' ); ?></td></tr>
							<tr><td><code>right</code></td><td><?php esc_html_e( 'List panel to the right of the map. Collapses to an icon strip.', 'bmg-interactive-map' ); ?></td></tr>
							<tr><td><code>left</code></td><td><?php esc_html_e( 'List panel to the left of the map. Collapses to an icon strip.', 'bmg-interactive-map' ); ?></td></tr>
							<tr><td><code>float-tl</code></td><td><?php esc_html_e( 'Floating overlay — top-left corner. Collapses to a header bar.', 'bmg-interactive-map' ); ?></td></tr>
							<tr><td><code>float-tr</code></td><td><?php esc_html_e( 'Floating overlay — top-right corner. Collapses to a header bar.', 'bmg-interactive-map' ); ?></td></tr>
							<tr><td><code>float-bl</code></td><td><?php esc_html_e( 'Floating overlay — bottom-left corner. Collapses to a header bar.', 'bmg-interactive-map' ); ?></td></tr>
							<tr><td><code>float-br</code></td><td><?php esc_html_e( 'Floating overlay — bottom-right corner. Collapses to a header bar.', 'bmg-interactive-map' ); ?></td></tr>
						</tbody>
					</table>

					<p style="margin-top:12px;"><?php esc_html_e( 'When both lists are set to the same position they stack vertically inside a single combined panel.', 'bmg-interactive-map' ); ?></p>

					<h3><?php esc_html_e( 'Search &amp; Scroll', 'bmg-interactive-map' ); ?></h3>
					<p><?php esc_html_e( 'A search field and scroll limit are added automatically to any list with 5 or more items. Typing in the search box filters items by title; map markers and polygons are unaffected.', 'bmg-interactive-map' ); ?></p>

					<h3><?php esc_html_e( 'Area List Behaviour', 'bmg-interactive-map' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'Hovering an area list item highlights the polygon fill on the map.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Clicking an area list item flies the map view to the polygon centroid and opens its popup.', 'bmg-interactive-map' ); ?></li>
					</ul>

					<div class="bmg-help-tip">
						<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
						<?php esc_html_e( 'Tip: use the toolbar\'s Locations and Areas buttons to show or hide each list at runtime without changing the embed settings.', 'bmg-interactive-map' ); ?>
					</div>
				</div>
			</div>

			<!-- ── Toolbar ───────────────────────────────────────────── -->
			<div class="bmg-help-card">
				<div class="bmg-help-step-badge">
					<span class="dashicons dashicons-button" style="font-size:18px;line-height:28px;" aria-hidden="true"></span>
				</div>
				<div class="bmg-help-step-body">
					<h2><?php esc_html_e( 'Toolbar', 'bmg-interactive-map' ); ?></h2>
					<p><?php esc_html_e( 'Every map embed displays a small icon button bar centred over the map image. The toolbar automatically moves to a free corner when floating list panels occupy the top edge.', 'bmg-interactive-map' ); ?></p>

					<table class="bmg-help-table widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Button', 'bmg-interactive-map' ); ?></th>
								<th><?php esc_html_e( 'Action', 'bmg-interactive-map' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><?php esc_html_e( 'Locations (list icon)', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Show / hide the entire location list including its header. Only shown when a location list position is configured.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Areas (polygon icon)', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Show / hide the entire area list including its header. Only shown when an area list position is configured.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Fill Window (window frame icon)', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Expand the map to fill the full browser viewport using CSS. Works inside iframes and does not require browser permission. Press Escape or click Exit to return.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Fullscreen (expand-arrows icon)', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Enter native browser fullscreen (hides browser chrome). Press Escape or click Exit to return.', 'bmg-interactive-map' ); ?></td>
							</tr>
						</tbody>
					</table>

					<p style="margin-top:12px;"><?php esc_html_e( 'An "Exit fullscreen" button appears at the bottom-centre of the map while either expanded mode is active.', 'bmg-interactive-map' ); ?></p>

					<div class="bmg-help-tip">
						<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
						<?php esc_html_e( 'Tip: in Elementor use the responsive "Hide Toolbar" switcher (Content tab → Map Settings) to suppress the toolbar on specific screen sizes. Button colours are customisable under Style tab → Toolbar.', 'bmg-interactive-map' ); ?>
					</div>
				</div>
			</div>

			<!-- ── Settings reference ─────────────────────────────────── -->
			<div class="bmg-help-card">
				<div class="bmg-help-step-badge">
					<span class="dashicons dashicons-admin-settings" style="font-size:18px;line-height:28px;" aria-hidden="true"></span>
				</div>
				<div class="bmg-help-step-body">
					<h2><?php esc_html_e( 'Global Settings', 'bmg-interactive-map' ); ?></h2>
					<p>
						<?php
						printf(
							/* translators: %s: link to settings page */
							esc_html__( 'Site-wide defaults are configured under %s.', 'bmg-interactive-map' ),
							'<a href="' . esc_url( $settings_url ) . '">'
							. esc_html__( 'Interactive Maps → Settings', 'bmg-interactive-map' )
							. '</a>'
						);
						?>
					</p>
					<table class="bmg-help-table widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Setting', 'bmg-interactive-map' ); ?></th>
								<th><?php esc_html_e( 'Description', 'bmg-interactive-map' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><?php esc_html_e( 'Default Marker Colour', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Colour used for any location that does not have its own marker colour set. Defaults to red (#e74c3c).', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Min Zoom', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Global default for how far out visitors can zoom. Can be overridden per map in the Zoom Settings panel on the map edit screen (range −5 to 0, default −3).', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Max Zoom', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Global default for how far in visitors can zoom. Can be overridden per map in the Zoom Settings panel (range 1 to 5, default 3).', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Zoom Control Position', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Corner where the + / − zoom buttons appear on every map. Can be overridden per embed via the shortcode zoom_position parameter, the block setting, or the Elementor widget setting.', 'bmg-interactive-map' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- ── Interactions reference ──────────────────────────────── -->
			<div class="bmg-help-card">
				<div class="bmg-help-step-badge">
					<span class="dashicons dashicons-info" style="font-size:18px;line-height:28px;" aria-hidden="true"></span>
				</div>
				<div class="bmg-help-step-body">
					<h2><?php esc_html_e( 'Frontend Interactions', 'bmg-interactive-map' ); ?></h2>
					<table class="bmg-help-table widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Action', 'bmg-interactive-map' ); ?></th>
								<th><?php esc_html_e( 'Result', 'bmg-interactive-map' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><?php esc_html_e( 'Click a marker', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Opens the location popup with title and description.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Tab to a marker → Enter or Space', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Opens the popup via keyboard (accessible).', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Hover over a marker (tooltips on)', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Shows a tooltip with the location name.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Hover over an area', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Highlights the polygon fill and shows a sticky name tooltip.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Click an area', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Opens the area popup with title and description at the clicked point.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Click a location list item', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Pans the map to that marker and opens its popup. The list item is highlighted.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Click a marker (with list visible)', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Highlights and scrolls to the matching list item.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Hover over an area list item', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Highlights the polygon fill on the map.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Click an area list item', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Flies the map to the polygon centroid and opens its popup.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Type in the search field', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Filters that list to titles that match. Map markers and polygons are not affected.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Toolbar — Locations / Areas button', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Shows or hides the corresponding list panel entirely.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Toolbar — Fill Window button', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Expands the map to fill the browser viewport (CSS only, no browser API).', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Toolbar — Fullscreen button', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Enters native browser fullscreen mode.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Exit fullscreen / Escape key', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Exits fill-window or fullscreen mode and restores normal view.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Scroll wheel / pinch', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Zooms the map in or out.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Click and drag', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Pans around the map.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( '+ / − zoom buttons', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Step-zoom in or out. Position is configurable (see Settings).', 'bmg-interactive-map' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Inline CSS (scoped to .bmg-help-wrap)
	// -------------------------------------------------------------------------

	private static function inline_css(): string {
		return '
		.bmg-help-wrap { max-width: 860px; }

		.bmg-help-title {
			display: flex;
			align-items: center;
			gap: 10px;
			font-size: 1.65em;
			margin-bottom: 6px;
		}
		.bmg-help-title .dashicons {
			font-size: 1.1em;
			width: auto;
			height: auto;
			color: #2271b1;
		}

		.bmg-help-intro {
			color: #50575e;
			font-size: 1.05em;
			margin-bottom: 24px;
		}

		.bmg-help-card {
			display: flex;
			gap: 20px;
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 24px;
			margin-bottom: 16px;
			box-shadow: 0 1px 3px rgba(0,0,0,.06);
		}

		.bmg-help-step-badge {
			flex-shrink: 0;
			width: 36px;
			height: 36px;
			border-radius: 50%;
			background: #2271b1;
			color: #fff;
			font-size: 1.1em;
			font-weight: 700;
			display: flex;
			align-items: center;
			justify-content: center;
			margin-top: 2px;
		}

		.bmg-help-step-body { flex: 1; min-width: 0; }

		.bmg-help-step-body h2 {
			margin: 0 0 12px;
			font-size: 1.15em;
		}
		.bmg-help-step-body h3 {
			font-size: 1em;
			margin: 18px 0 6px;
		}

		.bmg-help-step-body ol,
		.bmg-help-step-body ul {
			margin: 0 0 0 20px;
			padding: 0;
		}
		.bmg-help-step-body li { margin-bottom: 6px; line-height: 1.55; }

		.bmg-help-tip {
			display: flex;
			align-items: flex-start;
			gap: 8px;
			margin-top: 16px;
			padding: 10px 14px;
			background: #f0f6fc;
			border-left: 3px solid #2271b1;
			border-radius: 2px;
			font-size: 0.9em;
			color: #1d2327;
		}
		.bmg-help-tip .dashicons {
			color: #2271b1;
			flex-shrink: 0;
			margin-top: 1px;
		}

		.bmg-help-code {
			background: #f6f7f7;
			border: 1px solid #dcdcde;
			border-radius: 3px;
			padding: 10px 14px;
			font-size: 0.95em;
			overflow-x: auto;
			margin: 8px 0;
		}
		.bmg-help-code code { background: none; padding: 0; }

		.bmg-help-table { margin-top: 12px; }
		.bmg-help-table th { font-weight: 600; }

		.bmg-help-footer {
			color: #50575e;
			font-size: 0.95em;
			margin-top: 8px;
		}
		';
	}
}
