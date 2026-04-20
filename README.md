# BMG Interactive Map

A WordPress plugin for creating interactive image-based maps with clickable location markers and polygon area overlays. Use any image — a photo, floor plan, hand-drawn map, or game map — and place markers and areas that visitors can click to reveal popups with titles and descriptions.

## Features

- **Any image as a map** — upload any image as the map background
- **Clickable markers** — place markers anywhere on the image; each opens a popup with a title and description
- **Polygon areas** — draw multi-vertex regions over the map; areas render as outlines, highlight on hover, show a sticky name tooltip, and open a popup on click
- **Zoom & pan** — scroll to zoom, drag to pan; configurable zoom limits globally and per map
- **Location list** — optional side panel or floating overlay listing all locations, with search/filter
- **Tooltips** — show location names on marker hover
- **Starting view** — set a custom starting zoom level and center point per embed; supports per-breakpoint values in Elementor
- **Keyboard accessible** — Tab to markers, Enter/Space to open popups
- **Three display methods** — shortcode, Gutenberg block, or Elementor widget
- **Elementor responsive controls** — map height, starting view, and list visibility are all configurable per breakpoint (desktop / tablet / mobile)
- **Elementor styling** — full visual control over markers, popups, list panel, and close button via Elementor style tabs
- **Cascade operations** — trashing or deleting a map automatically cascades to its locations and areas

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Elementor (optional — widget registers only when Elementor is active)

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. On activation the plugin downloads Leaflet 1.9.4 from the CDN and stores it locally. If the download fails, it falls back to the CDN at runtime.

## Quick Start

### 1 — Create a Map

Go to **Interactive Maps → Add New**. Give the map a title and set a **Featured Image** (this becomes the map background). Optionally set per-map zoom limits in the **Zoom Settings** sidebar panel. Publish.

### 2 — Add Locations

Go to **Interactive Maps → Locations → Add New**. Fill in the title and description, select the parent map, then click anywhere on the map preview to place the marker. Adjust the marker colour if needed. Publish. Repeat for as many locations as needed.

### 3 — Add Areas (optional)

Go to **Interactive Maps → Areas → Add New**. Fill in the title and description, select the parent map, then click vertices on the map preview to draw the polygon. Adjust stroke colour, fill colour, and fill opacity as needed. Publish.

### 4 — Display the Map

Use any of the three methods below.

## Display Methods

### Shortcode

```
[bmg_map id="42"]
```

Copy the map's post ID from the URL in the editor (`post=42`).

Full parameter reference:

| Parameter | Default | Description |
|-----------|---------|-------------|
| `id` | *(required)* | Map post ID |
| `width` | *(fill column)* | Width: `800`, `800px`, or `100%` |
| `height` | *(aspect ratio)* | Height: `600` or `600px`. Leave blank to derive from the image ratio. |
| `list_position` | `none` | Location list placement — see values below |
| `list_title` | `Locations` | Label shown in the list panel header |
| `zoom_position` | *(global setting)* | Zoom control corner: `topleft` `topright` `bottomleft` `bottomright` |
| `show_tooltips` | `0` | Show location name on marker hover: `0` or `1` |
| `start_zoom` | *(fit all)* | Starting Leaflet zoom level (e.g. `-1`, `0`, `2`) |
| `start_x` | *(fit all)* | Starting center X as a percentage of the image width (0–100) |
| `start_y` | *(fit all)* | Starting center Y as a percentage of the image height (0–100) |

`start_zoom`, `start_x`, and `start_y` must all be provided together or the starting view is ignored.

`list_position` values:

| Value | Effect |
|-------|--------|
| `none` | No list |
| `left` / `right` | Side panel; collapses to a narrow icon strip |
| `float-tl` / `float-tr` / `float-bl` / `float-br` | Floating overlay in a corner; collapses to a single header bar |

Examples:

```
[bmg_map id="42" width="100%" list_position="right"]
[bmg_map id="42" list_position="float-tl" zoom_position="bottomright" show_tooltips="1"]
[bmg_map id="42" start_zoom="-1" start_x="30" start_y="60"]
```

### Gutenberg Block

Search for **Interactive Map** in the block inserter. Select your map, then configure width, height, zoom position, tooltips, and list position in the sidebar Inspector Controls panel.

### Elementor Widget

Drag the **Interactive Map** widget from the General category onto your page. All layout controls support desktop / tablet / mobile breakpoints via Elementor's responsive mode.

**Content tab — Map Settings:**
- Select Map
- Width
- Map Height *(responsive — set different heights per breakpoint)*
- Zoom Control Position
- Show Name on Hover
- Location List Position, List Title, Hide List *(Hide List is responsive)*
- Starting Zoom, Starting Center X %, Starting Center Y % *(all responsive — set different starting views per breakpoint)*

**Style tab:**
- **Marker Tooltip** — typography and colours
- **Map** — background colour
- **Location List** — panel, title bar, search field, item, hover, and active colours and typography
- **Popup** — container background, border, border radius; title and body typography and colours; close button icon, colour, size, and shape

## Per-Map Zoom Limits

Each map can override the global zoom limits via the **Zoom Settings** sidebar panel on the map edit screen. Leave the fields blank to use the global defaults from Settings.

## Settings

Go to **Interactive Maps → Settings** to configure global defaults.

| Setting | Default | Description |
|---------|---------|-------------|
| Default Marker Colour | `#e74c3c` | Applied to new locations; overridable per location |
| Min Zoom | `-3` | How far out visitors can zoom (range: −5 to 0) |
| Max Zoom | `3` | How far in visitors can zoom (range: 1 to 5) |
| Zoom Control Position | `topleft` | Default corner for zoom buttons |

## Admin Reference

**Interactive Maps** — list and edit maps (`bmg_map` CPT).

**Interactive Maps → Locations** — list and edit location markers (`bmg_location` CPT).

**Interactive Maps → Areas** — list and edit polygon area overlays (`bmg_area` CPT).

**Interactive Maps → Settings** — global plugin configuration.

**Interactive Maps → How to Use** — built-in step-by-step guide and parameter reference.

### Map Meta Fields

| Field | Description |
|-------|-------------|
| Featured Image | The image used as the map background |
| Min Zoom | Per-map override for how far out the user can zoom |
| Max Zoom | Per-map override for how far in the user can zoom |

### Location Meta Fields

| Field | Description |
|-------|-------------|
| Parent Map | Which map this location belongs to |
| X / Y coordinates | Position as a percentage (0–100); set visually by clicking the map preview |
| Marker Color | Per-location hex colour (defaults to global setting) |
| Font Awesome Icon | Optional icon class displayed inside the marker dot |

### Area Meta Fields

| Field | Description |
|-------|-------------|
| Parent Map | Which map this area belongs to |
| Points | JSON array of `{x, y}` percentage coordinates; set visually by clicking vertices on the map preview |
| Stroke Color | Outline colour of the polygon |
| Fill Color | Interior fill colour shown on hover |
| Fill Opacity | Opacity of the fill (0–1) |

## Data Storage

| Item | Storage |
|------|---------|
| Map | `bmg_map` CPT; background image = featured image; per-map zoom in `_bmg_map_min_zoom` / `_bmg_map_max_zoom` |
| Location | `bmg_location` CPT; `_bmg_map_id`, `_bmg_loc_x`, `_bmg_loc_y`, `_bmg_loc_color`, `_bmg_loc_icon` |
| Area | `bmg_area` CPT; `_bmg_area_map_id`, `_bmg_area_points` (JSON), `_bmg_area_color`, `_bmg_area_fill_color`, `_bmg_area_fill_opacity` |
| Global settings | `bmg_map_settings` WordPress option (array) |
| Leaflet library | `wp-content/plugins/bmg-interactive-map/lib/leaflet/` |

## Third-Party Libraries

- [Leaflet](https://leafletjs.com/) 1.9.4 — MIT License. Downloaded on activation and served locally.

## License

GPL-2.0-or-later. See [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).
