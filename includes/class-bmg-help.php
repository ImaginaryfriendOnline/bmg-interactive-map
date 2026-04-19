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
				<?php esc_html_e( 'Follow the three steps below to create a map, add locations to it, and display it on any page or post. Additional reference sections follow.', 'bmg-interactive-map' ); ?>
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
						<li><?php esc_html_e( 'Set a Featured Image — this is the background image markers are placed on. Use any image: a photo, a hand-drawn map, a floor plan, etc.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( '(Optional) Add a description in the content area.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Publish the map. Note the post ID in the URL bar — you will need it in Step 3.', 'bmg-interactive-map' ); ?></li>
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
						<li><?php esc_html_e( 'The map image loads in the visual editor. Click anywhere on it to drop a marker, or drag the existing marker to reposition it. You can also type X % and Y % values manually.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( '(Optional) Pick a custom marker colour. The default colour is set under Interactive Maps → Settings.', 'bmg-interactive-map' ); ?></li>
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
								<td><code>0</code></td>
								<td><?php esc_html_e( 'Explicit width in pixels. Leave 0 to fill the column width.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><code>height</code></td>
								<td><code>0</code></td>
								<td><?php esc_html_e( 'Explicit height in pixels. Leave 0 to use the image aspect ratio.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><code>list_position</code></td>
								<td><code>none</code></td>
								<td>
									<?php esc_html_e( 'Show a location list. Values:', 'bmg-interactive-map' ); ?>
									<code>none</code>, <code>left</code>, <code>right</code>, <code>above</code>, <code>below</code>,
									<code>float-tl</code>, <code>float-tr</code>, <code>float-bl</code>, <code>float-br</code>
								</td>
							</tr>
							<tr>
								<td><code>zoom_position</code></td>
								<td><em><?php esc_html_e( '(site setting)', 'bmg-interactive-map' ); ?></em></td>
								<td>
									<?php esc_html_e( 'Override the zoom control corner for this map only. Values:', 'bmg-interactive-map' ); ?>
									<code>topleft</code>, <code>topright</code>, <code>bottomleft</code>, <code>bottomright</code>
								</td>
							</tr>
						</tbody>
					</table>

					<pre class="bmg-help-code" style="margin-top:12px;"><code>[bmg_map id="42" list_position="right" zoom_position="bottomright"]</code></pre>

					<!-- Option B: Gutenberg -->
					<h3 style="margin-top:24px;"><?php esc_html_e( 'Option B — Block Editor (Gutenberg)', 'bmg-interactive-map' ); ?></h3>
					<ol>
						<li><?php esc_html_e( 'Open the page or post in the Block Editor.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Click the + button, search for "Interactive Map", and insert the block.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Choose your map from the dropdown in the block placeholder or the Settings panel on the right.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'In the Settings panel you can also set Width, Height, Zoom Control Position, and Location List Position.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Update or publish the page.', 'bmg-interactive-map' ); ?></li>
					</ol>

					<!-- Option C: Elementor -->
					<h3 style="margin-top:24px;"><?php esc_html_e( 'Option C — Elementor Widget', 'bmg-interactive-map' ); ?></h3>
					<ol>
						<li><?php esc_html_e( 'Open the page in the Elementor editor.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Search for "Interactive Map" in the widget panel and drag it onto the canvas.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'In the Content tab → Map Settings: choose a map, and optionally set Width, Height, and Zoom Control Position.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'In the Content tab → Location List: choose a list position (or leave on None).', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'In the Style tab you can customise: map background colour, list panel colours and typography, popup title typography and colour, and popup background and border.', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'Save and publish the page.', 'bmg-interactive-map' ); ?></li>
					</ol>

					<div class="bmg-help-tip">
						<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
						<?php esc_html_e( 'Tip: the map is fully responsive and adapts to any column width automatically.', 'bmg-interactive-map' ); ?>
					</div>
				</div>
			</div>

			<!-- ── Location List ──────────────────────────────────────── -->
			<div class="bmg-help-card">
				<div class="bmg-help-step-badge">
					<span class="dashicons dashicons-list-view" style="font-size:18px;line-height:28px;" aria-hidden="true"></span>
				</div>
				<div class="bmg-help-step-body">
					<h2><?php esc_html_e( 'Location List', 'bmg-interactive-map' ); ?></h2>
					<p><?php esc_html_e( 'An optional scrollable list of all locations can be shown alongside or on top of the map. The list and map stay in sync — clicking a list item pans the map to that marker and opens its popup; clicking a marker highlights and scrolls to the corresponding list item.', 'bmg-interactive-map' ); ?></p>

					<table class="bmg-help-table widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Position value', 'bmg-interactive-map' ); ?></th>
								<th><?php esc_html_e( 'Description', 'bmg-interactive-map' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr><td><code>none</code></td><td><?php esc_html_e( 'No list shown (default).', 'bmg-interactive-map' ); ?></td></tr>
							<tr><td><code>right</code></td><td><?php esc_html_e( 'List panel to the right of the map (side-by-side).', 'bmg-interactive-map' ); ?></td></tr>
							<tr><td><code>left</code></td><td><?php esc_html_e( 'List panel to the left of the map (side-by-side).', 'bmg-interactive-map' ); ?></td></tr>
							<tr><td><code>above</code></td><td><?php esc_html_e( 'List panel above the map (stacked).', 'bmg-interactive-map' ); ?></td></tr>
							<tr><td><code>below</code></td><td><?php esc_html_e( 'List panel below the map (stacked).', 'bmg-interactive-map' ); ?></td></tr>
							<tr><td><code>float-tl</code></td><td><?php esc_html_e( 'Floating overlay panel — top-left corner of the map.', 'bmg-interactive-map' ); ?></td></tr>
							<tr><td><code>float-tr</code></td><td><?php esc_html_e( 'Floating overlay panel — top-right corner of the map.', 'bmg-interactive-map' ); ?></td></tr>
							<tr><td><code>float-bl</code></td><td><?php esc_html_e( 'Floating overlay panel — bottom-left corner of the map.', 'bmg-interactive-map' ); ?></td></tr>
							<tr><td><code>float-br</code></td><td><?php esc_html_e( 'Floating overlay panel — bottom-right corner of the map.', 'bmg-interactive-map' ); ?></td></tr>
						</tbody>
					</table>

					<h3 style="margin-top:16px;"><?php esc_html_e( 'Search', 'bmg-interactive-map' ); ?></h3>
					<p><?php esc_html_e( 'A search field is automatically added to the top of the list panel in these cases:', 'bmg-interactive-map' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Always, for side and stacked positions (left / right / above / below).', 'bmg-interactive-map' ); ?></li>
						<li><?php esc_html_e( 'When there are more than 10 locations, for floating positions.', 'bmg-interactive-map' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'Typing in the search box filters the list items by title. Map markers are unaffected.', 'bmg-interactive-map' ); ?></p>

					<div class="bmg-help-tip">
						<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
						<?php esc_html_e( 'Tip: floating lists with more than 10 locations are automatically capped to show 10 items at a time and scroll for the rest.', 'bmg-interactive-map' ); ?>
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
								<td><?php esc_html_e( 'How far out the user can zoom. Negative values allow zooming beyond the image bounds (range −5 to 0, default −3).', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Max Zoom', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'How far in the user can zoom (range 1 to 5, default 3).', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Zoom Control Position', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Corner where the + / − zoom buttons appear on every map. Can be overridden per-map via the shortcode zoom_position parameter, the block setting, or the Elementor widget setting.', 'bmg-interactive-map' ); ?></td>
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
								<td><?php esc_html_e( 'Click a location list item', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Pans the map to that marker and opens its popup. The list item is highlighted.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Click a marker (with list visible)', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Highlights and scrolls to the matching list item.', 'bmg-interactive-map' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Type in the search field', 'bmg-interactive-map' ); ?></td>
								<td><?php esc_html_e( 'Filters the location list to titles that match. Map markers are not affected.', 'bmg-interactive-map' ); ?></td>
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
