# Changelog

## 0.1.7

- Add a robust GLPI bootstrap for direct plugin front/ajax entrypoints without emitting PHP warnings.
- Prevent BrandPulse JavaScript from running Pulse calls outside real HTML header pages.
- Align GLPI 11 public asset URLs by removing `/public` from CSS, JavaScript and SVG web paths.
- Declare legacy script firewall strategies for the configuration page and AJAX endpoints.
- Split the Solar icon picker into a lightweight index and lazy category chunks instead of loading the whole icon database at popup opening.

## 0.1.6

- Keep Pulse save actions visible at the top of the tab and as a floating bottom action.
- Simplify Pulse rows to `Source` plus one contextual `Target` column.
- Reduce icon picker work with a slimmer manifest, 24 icons per page and debounced search rendering.

## 0.1.5

- Replace the inline icon grid with a compact modal picker with search and pagination.
- Generate a recursive manifest for the Solar outline SVG icon subset copied into `public/icons/pulse`.
- Keep the Pulse save button visible with a sticky action bar on long configuration screens.
- Store local icon choices as stable `pulse:Category/Icon.svg` values.

## 0.1.4

- Count saved-search Pulse counters through GLPI `Search` parameters instead of calling `SavedSearch::execute()` directly.
- Protect BrandPulse JSON endpoints from PHP warnings or incidental output corrupting AJAX responses.
- Render Pulse SVG icons as CSS masks so they adapt to light, dark and custom header colours.
- Add a visual icon picker, custom SVG URL/path support and a broader local SVG icon pack.

## 0.1.3

- Use GLPI native SavedSearch and Search mechanisms for Pulse counter criteria.
- Let Pulse counters target GLPI saved ticket searches, preserving native AND/OR criteria and metacriteria.
- Run standard presets through the GLPI search engine instead of custom SQL counters.
- Replace category/group-specific Pulse settings with a saved-search selector.

## 0.1.2

- Fix GLPI 11 marketplace front/ajax bootstrap by removing legacy `inc/includes.php` includes.
- Render Pulse counters in the top header navbar instead of the left sidebar menu.
- Replace raw JSON configuration with Brand and Pulse tabs.
- Add Brand settings for title, favicon, login logo, left menu logo, login background and login alert message.
- Add Pulse rows with icon, color, thresholds and selectable ticket category/group scope.
- Remove non-standard historical presets and legacy hard-coded exclusions.

## 0.1.1

- Restrict Pulse counters to the GLPI central interface only.
- Keep Pulse away from the helpdesk/self-service and catalogue portal.
- Add optional compact global search mode with a magnifier trigger.
- Add the local `pulse:search` SVG icon.
- Update GLPI catalog metadata for the `v0.1.1` release archive.

## 0.1.0

- Initial GLPI BrandPulse plugin skeleton.
- Add GLPI 11 plugin declaration and hooks.
- Add header counters endpoint and frontend renderer.
- Add historical counter presets from the previous local Modifications fork.
- Add configuration defaults for future branding support.
- Add GitHub tag-based release workflow and installable ZIP packaging.
- Add schema version tracking and idempotent plugin migration mechanism.
- Add GLPI catalog metadata file for Marketplace/update publication readiness.
- Add local SVG Pulse icon pack and default counter icon mapping.
- Add gettext FR/EN catalogs using the `brandpulse` translation domain.
