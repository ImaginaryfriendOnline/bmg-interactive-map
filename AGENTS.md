# AGENTS.md

Guidance for AI coding agents working in this repository.

## What this is

A WordPress plugin (`bmg-interactive-map.php`) with no build step — plain PHP, vanilla JS, and CSS, loaded directly by WordPress. There is no package.json, no bundler, and no test suite; verification is manual (install in a WordPress site, or test via a WordPress Playground instance) or via PHP lint.

## Layout

- `bmg-interactive-map.php` — plugin bootstrap. Defines `BMG_MAP_VERSION`, requires every file under `includes/`, and wires each class's `init()` into `plugins_loaded`.
- `includes/` — one class per concern, `class-bmg-<name>.php` (CPT registration, shortcode, settings, tileset generation, text-color/`[color=]` tags, Elementor widget, Gutenberg block, help screen) plus `trait-bmg-cpt-admin-list.php`, shared by the location/area admin list tables.
- `admin/js/`, `admin/css/` — admin-side map/area editors (click-to-place marker/vertex UI) and styling.
- `public/js/public.js`, `public/css/public.css` — the frontend Leaflet-based map renderer: markers, areas, popups, lists, toolbar, tilesets, deep linking, secret-location discovery.
- `blocks/bmg-map/editor.js` — Gutenberg block editor script.
- `repackage.py` — bumps the version in `bmg-interactive-map.php` (patch/minor/major) and builds a release zip under `releases/`.
- `.github/workflows/release.yml` — `workflow_dispatch` release workflow: runs `repackage.py`, commits the version bump to `main`, tags `vX.Y.Z`, and creates a GitHub release with the zip attached.

## Conventions

- All PHP classes, hooks, meta keys, and asset handles are prefixed `BMG`/`bmg_`/`_bmg_`. Keep new code consistent with that prefix — this plugin shares a WordPress install with other plugins.
- Custom post types: `bmg_map`, `bmg_location`, `bmg_area`. Settings are stored as a single array option, `bmg_map_settings` (see `class-bmg-settings.php`).
- Sanitize and escape at the WordPress boundary as the existing code does: `sanitize_hex_color`, `esc_attr`/`esc_html`, `wp_json_encode` for data passed to JS. User-entered title/body text flows through `[color=…]` tag parsing (`class-bmg-text-color.php`) before display — any change there must keep output escaped, since location/area titles and bodies are author-entered HTML-adjacent text that's rendered in popups.
- Percentage coordinates (`x`, `y`, polygon `points`) are stored as 0–100 floats relative to the map image, not pixels — frontend and admin JS both convert against the rendered image size.
- Each editable location/area field has a matching meta key (see the README's "Data Storage" table) and a corresponding save-time sanitizer in the CPT class's `save_post` handler. When adding a field, update: the metabox UI, the save handler, the enqueued-data array passed to `public.js`/`admin.js`, and the README tables.
- Secret-location state (`_bmg_loc_secret*`) is the most recent non-trivial feature; its discovery state is deliberately client-side only (cookie or `sessionStorage`), never stored server-side per visitor.

## Making changes

- There's no linter/test command wired up; after editing PHP, at minimum run `php -l <file>` on anything touched. After editing `public/js/public.js` or admin JS, a syntax check (`node --check <file>`) catches basic errors even without a WordPress runtime.
- To actually exercise behavior, the most reliable approach found in prior sessions is a local WordPress Playground instance (no Docker/MySQL needed on this host) with seeded map/location/area content, rather than guessing from code reading alone.
- Don't hand-edit the `Version:` header or `BMG_MAP_VERSION` constant for a release — that's `repackage.py`'s job, invoked through the GitHub Actions release workflow. Do update both together if a change genuinely requires a manual bump.
- Keep README.md's feature list and reference tables (shortcode parameters, settings, meta fields, data storage) in sync with any user-facing or storage-schema change — they're the only documentation for this plugin.
- The release zip excludes `.git`, `lib`, `releases`, and `.claude` (see `repackage.py`); don't rely on those being present when the plugin runs from a packaged zip.
