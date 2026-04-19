# BMG Interactive Map

A WordPress plugin for creating interactive image-based maps with clickable location markers. Use any image — a photo, floor plan, hand-drawn map, or game map — and place markers that visitors can click to reveal popups with titles and descriptions.

## Features

- **Any image as a map** — upload any image as the map background
- **Clickable markers** — place markers anywhere on the image; each opens a popup with a title and description
- **Zoom & pan** — scroll to zoom, drag to pan; configurable zoom limits
- **Location list** — optional side panel or floating overlay listing all locations, with search/filter
- **Tooltips** — show location names on marker hover
- **Keyboard accessible** — Tab to markers, Enter/Space to open popups
- **Three display methods** — shortcode, Gutenberg block, or Elementor widget
- **Elementor styling** — full visual control over markers, popups, list panel, and close button via Elementor style tabs
- **Cascade operations** — trashing or deleting a map automatically cascades to its locations

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

Go to **Interactive Maps → Add New**. Give the map a title, set a **Featured Image** (this becomes the map background), and publish.

### 2 — Add Locations

Go to **Interactive Maps → Locations → Add New**. Fill in the title and description, select the parent map, then click anywhere on the map preview to place the marker (or drag it). Adjust the marker color if needed. Publish.

Repeat for as many locations as needed.

### 3 — Display the Map

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
| `height` | *(aspect ratio)* | Height: `600` or `600px` |
| `list_position` | `none` | Location list placement (see values below) |
| `zoom_position` | *(global setting)* | Zoom control corner: `topleft` `topright` `bottomleft` `bottomright` |
| `show_tooltips` | `0` | Show location name on marker hover: `0` or `1` |

`list_position` values:

| Value | Effect |
|-------|--------|
| `none` | No list |
| `left` / `right` | Side panel (220 px); collapses to a narrow icon strip |
| `float-tl` / `float-tr` / `float-bl` / `float-br` | Floating overlay in a corner; collapses to a single header bar |

Examples:

```
[bmg_map id="42" width="100%" list_position="right"]
[bmg_map id="42" list_position="float-tl" zoom_position="bottomright" show_tooltips="1"]
[bmg_map id="42" width="800" height="600"]
```

### Gutenberg Block

Search for **Interactive Map** in the block inserter (under the Embed category). Select your map, then configure width, height, zoom position, tooltips, and list position in the sidebar Inspector Controls panel.

### Elementor Widget

Drag the **Interactive Map** widget from the General category onto your page. The **Content** tab has map selection and layout controls. The **Style** tab gives full control over:

- Marker tooltip typography and colors
- Map background color
- Location list panel, item, hover, and active styles
- Popup title and body typography and colors
- Popup background, border, and border radius
- Close button icon, color, size, and shape

## Settings

Go to **Interactive Maps → Settings** to configure global defaults.

| Setting | Default | Description |
|---------|---------|-------------|
| Default Marker Colour | `#e74c3c` | Applied to new locations; overridable per location |
| Min Zoom | `-3` | How far out visitors can zoom (range: −5 to 0) |
| Max Zoom | `3` | How far in visitors can zoom (range: 1 to 5) |
| Zoom Control Position | `topleft` | Default corner for zoom buttons |

## Admin Reference

**Interactive Maps** — list and edit maps (backed by the `bmg_map` custom post type).

**Interactive Maps → Locations** — list and edit location markers (backed by `bmg_location`).

**Interactive Maps → Settings** — global plugin configuration.

**Interactive Maps → How to Use** — built-in step-by-step guide and parameter reference.

### Location Meta Fields

| Field | Description |
|-------|-------------|
| Parent Map | Which map this location belongs to |
| X / Y coordinates | Position as a percentage (0–100); set visually by clicking the map preview |
| Marker Color | Per-location hex color (defaults to global setting) |

## Data Storage

| Item | Storage |
|------|---------|
| Map | `bmg_map` custom post type; background image = featured image |
| Location | `bmg_location` CPT; metadata: `_bmg_map_id`, `_bmg_loc_x`, `_bmg_loc_y`, `_bmg_loc_color` |
| Global settings | `bmg_map_settings` WordPress option (array) |
| Leaflet library | `wp-content/plugins/bmg-interactive-map/lib/leaflet/` |

## Third-Party Libraries

- [Leaflet](https://leafletjs.com/) 1.9.4 — MIT License. Downloaded on activation and served locally.

## License

GPL-2.0-or-later. See [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).
