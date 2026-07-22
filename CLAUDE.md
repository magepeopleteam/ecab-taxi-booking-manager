# CLAUDE.md

Guidance for working in this repository.

## What this is

**E-cab Taxi Booking Manager for WooCommerce** — a WordPress plugin (free/lite version)
by **MagePeople Team** that turns a WooCommerce store into a full transportation /
taxi / chauffeur booking system. Customers pick up & drop-off locations on a map, the
plugin calculates a fare (distance / time / zone / fixed), they choose a vehicle and
extra services, and the booking is checked out as a normal WooCommerce order.

- **Main file:** `MPTBM_Plugin.php` (Plugin header, version 2.0.2)
- **Text domain:** `ecab-taxi-booking-manager`
- **Code prefix:** `MPTBM_` (classes), `mptbm_` (functions, meta keys, hooks, options)
- **WooCommerce is OPTIONAL (auto-detected).** The core plugin (CPT, settings, booking
  search & pricing) always loads. WooCommerce-specific integration loads only when WC is
  active. Without WC the plugin runs in **standalone mode** (custom booking + payment flow
  — *not yet built*, see "WooCommerce-optional status" below). Mode check:
  `MPTBM_Function::is_wc_active()` (thin wrapper over
  `MP_Global_Function::check_woocommerce() === 1`). When WC is absent and in admin, the
  non-blocking installer popup (`Admin/MPTBM_Woo_Installer.php`) is still offered.
- **Requires:** WP 5.3+, PHP 7.0+. PHP type hints (`: void`, `: string`) are used, so
  keep PHP 7.0 compatibility in mind.
- **Pro version** exists separately (class checks like `MPTBM_Plugin_Pro` /
  `MPTBM_Dependencies_Pro` gate Pro-only features such as Operation Areas, geo-fencing,
  driver panel). This repo is the free plugin only.

## Bootstrap & load order

1. `MPTBM_Plugin.php` → `new MPTBM_Plugin()`. Defines constants
   (`MPTBM_PLUGIN_DIR`, `MPTBM_PLUGIN_URL`, `MPTBM_PLUGIN_VERSION`), loads the shared
   framework `mp_global/MP_Global_File_Load.php`, creates default pages on activation, and
   **always** requires `inc/MPTBM_Dependencies.php` + `inc/MPTBM_Geo_Lib.php` + Block/
   Elementor integration. Only the WC checkout-fields helper (or the installer popup) is
   gated on `check_woocommerce()`.
2. `inc/MPTBM_Dependencies.php` → `new MPTBM_Dependencies()` is the real loader. It
   `require`s the core (`MPTBM_Function`, `MPTBM_Query`, `MPTBM_Layout`,
   `MPTBM_Rest_Api`), Admin (`MPTBM_Admin`), Frontend (`MPTBM_Frontend`), enqueues all
   admin/frontend assets, sets up map JS, and registers the OSM search AJAX proxy.
   `MPTBM_Hidden_Product` (WC product mirror) is required **only when WC is active**.
3. `Admin/MPTBM_Admin.php` (admin only) requires every Admin/* class — CPT, settings
   tabs, custom editor, analytics, etc. The `MPTBM_Wc_Checkout_*` classes are required
   **only when WC is active**.
4. `Frontend/MPTBM_Frontend.php` requires shortcodes + the AJAX search controller always;
   `MPTBM_Woocommerce` + `MPTBM_Wc_Checkout_Fields_Helper` (cart/checkout glue) **only
   when WC is active**.

### WooCommerce-optional status (in progress)

WC was made optional by inverting the boot gate and conditionally loading all WC
integration. **Done:** the plugin loads, the admin works, and the booking search/vehicle/
extra-service UI renders without WC (price display falls back via
`MP_Global_Function::format_price()`, which uses `wc_price()` when WC is active and
`number_format()` otherwise). **Still TODO (deferred by design):** the actual standalone
booking submission + custom payment methods. Today the final "add to cart → checkout"
step still depends on WC (`Frontend/MPTBM_Woocommerce.php`, `inc/MPTBM_Rest_Api.php` order
creation). The plan is a custom `mptbm_booking` CPT to store bookings + custom checkout/
payment when WC is absent. When adding any new WC call to always-loaded code, guard it
with `MPTBM_Function::is_wc_active()` / `function_exists()` or route price output through
`MP_Global_Function::format_price()`.

Most classes self-instantiate at the bottom of their own file (`new MPTBM_Xxx();`), so
including the file is what wires up its hooks. Every file starts with an
`if (!defined('ABSPATH')) die;` guard and a `class_exists` wrapper.

## Directory map

| Path | Purpose |
|------|---------|
| `MPTBM_Plugin.php` | Plugin entry: constants, activation, page creation, custom DB tables, Block/Elementor registration, template assignment. |
| `inc/` | Core engine (loaded on frontend + admin). |
| `inc/MPTBM_Function.php` | **The heart of the plugin.** Static helpers: `get_cpt()`, `get_price()` (the fare engine), location/zone helpers, schedule, distance via maps, weather/traffic pricing hooks. ~60 KB. |
| `inc/MPTBM_Dependencies.php` | Master loader + asset enqueue + map setup + OSM AJAX proxy. |
| `inc/MPTBM_Query.php` | WP_Query wrappers for the CPT. |
| `inc/MPTBM_Rest_Api.php` | Optional REST API (`ecab-taxi/v1`). ~160 KB. Off by default. |
| `inc/MPTBM_Geo_Lib.php` / `MPTBM_Layout.php` | Geo helpers, layout helpers. |
| `Admin/` | All wp-admin functionality. |
| `Admin/MPTBM_CPT.php` | Registers post types & taxonomy (see below) + transportation list columns. |
| `Admin/MPTBM_Rent_Custom_Editor.php` | Replaces the default Gutenberg/classic editor for `mptbm_rent` with a custom add/edit screen (`admin.php?page=mptbm-rent-edit`). |
| `Admin/MPTBM_Transportation.php` | The "Transportation Lists" admin page (custom listing with trash/restore/delete + sales summary). |
| `Admin/MPTBM_Hidden_Product.php` | Mirrors each `mptbm_rent` vehicle to a hidden WooCommerce product so it can be added to cart / checked out. Critical glue — see "Booking → order flow". |
| `Admin/settings/` | Individual settings tab renderers (General, Price, Extra Service, Operation Areas, Date, Base Price, Tax, Advanced, Right-Side content, AJAX handler). |
| `Admin/MPTBM_Settings.php` / `MPTBM_Settings_Global.php` | Per-vehicle settings metabox tabs / global plugin settings page (uses `MAGE_Setting_API`). |
| `Admin/MPTBM_Wc_Checkout_*.php` | WooCommerce checkout field/billing/shipping/order customization (admin side). |
| `Admin/MPTBM_Analytics_Dashboard.php` | Bookings analytics submenu (AJAX-fed charts). |
| `Admin/MPTBM_Dummy_Import.php` | Demo data importer popup. Self-instantiates — do **not** instantiate twice. |
| `Admin/MPTBM_API_Documentation.php` / `MPTBM_License.php` / `MPTBM_Guideline.php` / `MPTBM_Status.php` | API docs page, licensing, help, system status. |
| `Frontend/` | Customer-facing booking experience. |
| `Frontend/MPTBM_Shortcodes.php` | Registers `[mptbm_booking]` → fires `do_action('mptbm_transport_search', $params)`. |
| `Frontend/MPTBM_Transport_Search.php` | The AJAX state machine for the booking flow (search result, end place, extra services, details, redirect). Distance/duration computed **server-side** from coordinates for security. |
| `Frontend/MPTBM_Woocommerce.php` | Cart item data, price injection (`before_calculate_totals`), order line items, custom checkout fields, file uploads. The bridge from booking → WC order. |
| `Frontend/MPTBM_Block.php` / `MPTBM_Elementor_Widget.php` | Gutenberg block + Elementor widget that emit the shortcode. |
| `templates/` | Overridable PHP templates. |
| `templates/registration/` | The multi-step booking UI partials (`registration_layout.php`, `choose_vehicles.php`, `vehicle_item.php`, `extra_service.php`, `get_details.php`, `summary.php`, …). |
| `templates/single_page/` & `templates/themes/` | Single vehicle page + selectable result "themes" (`default.php`). |
| `transport_result.php` | Page template ("Transport Result") auto-assigned to the search-result page. |
| `mp_global/` | **Shared MagePeople framework**, reused across their plugins. Don't treat as plugin-specific. |
| `mp_global/class/MP_Global_Function.php` | Ubiquitous static helpers: `get_settings()`, `get_post_info()`, `wc_price()`, `check_woocommerce()`, `week_day()`, etc. Used everywhere. |
| `mp_global/class/MAGE_Setting_API.php` | WP Settings API wrapper powering the global settings tabs. |
| `assets/` | CSS/JS/images. `assets/admin/` and `assets/frontend/` hold the bulk. Bundled addon assets live in `assets/admin/distance_tier_pricing/` and `assets/admin/peak_hour_pricing_addon/`. |
| `old_version.php` | **Dead legacy file** (288 KB, UTF-16, not referenced anywhere). Ignore it. |

## Custom post types & taxonomy (`Admin/MPTBM_CPT.php`)

- **`mptbm_rent`** — the transportation unit / vehicle ("Transportation"). Labels, slug,
  and icon are user-configurable via general settings (`MPTBM_Function::get_name()` /
  `get_slug()` / `get_icon()`). `public=false`, supports title + thumbnail. This is the
  main CPT.
- **`mptbm_extra_services`** — add-on services (child orders, luggage, etc.).
- **`mptbm_operate_areas`** — operation areas; **registered only when
  `MPTBM_Plugin_Pro` exists** (Pro feature).
- **Taxonomy `locations`** — attached to `mptbm_rent` for fixed/manual location pricing.

## Pricing engine

`MPTBM_Function::get_price($post_id, $distance, $duration, $start, $dest, $waiting_time,
$two_way, $fixed_time, $end_coords)` is the single source of truth for fares. The pricing
model per vehicle is stored in post meta `mptbm_price_based`. Supported models:

- `distance`, `duration`, `distance_duration`, `inclusive` — dynamic (map-calculated).
- `manual` — fixed price per chosen start/destination location pair.
- `fixed_hourly` — hourly packages.
- `fixed_distance` / `fixed_map` — fixed price tied to a map route.
- `fixed_zone` / `fixed_zone_pickup` / `fixed_zone_dropoff` — zone-based pricing.

The "original" model is tracked through a `original_price_based` transient because the UI
tabs can switch model at runtime. Base fare components live in meta:
`mptbm_km_price`, `mptbm_hour_price`, `mptbm_initial_price`, `mptbm_min_price`,
`mptbm_waiting_price`, `mptbm_display_taxi_base_fare_pricing`, etc. There are optional
weather- and traffic-based price modifiers (Google APIs).

Bundled pricing **addons** (loaded if their classes exist): Distance Tier Pricing
(`MPTBM_Distance_Tier_Pricing`) and Peak Hour Pricing — both have admin assets under
`assets/admin/`.

## Maps

Two providers, chosen in settings (`mptbm_map_api_settings.display_map`):

- **`openstreetmap`** (default, free) — Leaflet + Leaflet.draw (CDN) for display/polygons;
  geocoding/autocomplete via the **Photon** API proxied through the server-side AJAX
  handler `MPTBM_Dependencies::osm_search_proxy()` (`wp_ajax_mptbm_osm_search`).
- **`enable`** (Google Maps) — requires `gmap_api_key`; loads Google Maps JS + geolib.
- **`disable`** — no map; pricing is forced to `manual`.

Distance/duration for fares are computed **server-side from coordinates**
(`MPTBM_Function::get_server_distance()`), not trusted from the client, then cached in
`$_SESSION` for cart validation.

## Booking → order flow (important)

1. `[mptbm_booking]` renders the search form (`registration_layout.php`).
2. Customer enters pickup/dropoff → AJAX (`get_mptbm_map_search_result`) computes
   distance/duration server-side → renders `choose_vehicles.php` with priced vehicles.
3. Vehicle + extra services + passenger details collected via further AJAX steps.
4. Add to cart (`mptbm_add_to_cart`): each `mptbm_rent` has a **mirror hidden WooCommerce
   product** (`Admin/MPTBM_Hidden_Product.php`, meta `link_wc_product` /
   `link_mptbm_id`). The hidden product is added to the cart; the real price + booking
   metadata are injected via `woocommerce_add_cart_item_data` and
   `woocommerce_before_calculate_totals` in `Frontend/MPTBM_Woocommerce.php`.
5. Standard WooCommerce checkout completes the booking as an order; custom checkout
   fields and uploads are saved to order meta.

When touching pricing or cart behavior, remember the hidden-product indirection — the
customer never sees/buys the `mptbm_rent` directly.

## Settings & data storage

- Global settings: stored under WP options consumed via
  `MP_Global_Function::get_settings($section, $key, $default)`. Key sections:
  `mptbm_general_settings`, `mptbm_map_api_settings`, `mptbm_price_settings`,
  `mptbm_driver_settings`, `mptbm_rest_api_settings`, `mp_global_settings`.
- Per-vehicle config: post meta on `mptbm_rent` (`mptbm_*`), read via
  `MP_Global_Function::get_post_info()`.
- Custom DB tables (created on activation in `MPTBM_Plugin::create_api_tables()`):
  `{prefix}mptbm_api_keys`, `{prefix}mptbm_api_logs` (only used by the REST API).
- Pages auto-created on activation: `transport_booking`, `transport_booking_manual`,
  `transport_booking_fixed_hourly`, `transport-result`, (`transport-tabs`).

## REST API

`inc/MPTBM_Rest_Api.php`, namespace **`ecab-taxi/v1`**, instantiated **only if**
`mptbm_rest_api_settings.enable_rest_api === 'yes'`. API-key auth (keys match
`/^etbm_[a-zA-Z0-9]{32}$/`), logged to the custom tables. Routes cover taxis, bookings,
price calculation, location autocomplete/distance/routes, and settings. Admin UI for keys
& docs in `Admin/MPTBM_API_Documentation.php`.

## Integrations

- **Gutenberg block** — `Frontend/MPTBM_Block.php`, assets `assets/js/block.js` +
  `assets/css/block-editor.css`.
- **Elementor widget** — `Frontend/MPTBM_Elementor_Widget.php`, category `mptbm`.
- **Multilingual** — WPML / Polylang aware (`MPTBM_Function::post_id_multi_language()`).

## Conventions & gotchas

- **Template overrides:** themes can override any `templates/` file by copying it to
  `wp-content/themes/<theme>/mptbm_templates/...`. Resolution is via
  `MPTBM_Function::template_path()` / `details_template_path()` — always render templates
  through these helpers, never hardcode the plugin path.
- **Assets are cache-busted with `time()`** as the version on enqueue (dev convenience);
  no build step for PHP. The only npm script is a Sass watcher
  (`npm start` → compiles `sass/main.scss`, though the `sass/` dir isn't committed here).
- **i18n:** wrap strings in `__()/esc_html__()` with domain `ecab-taxi-booking-manager`.
  Note some Pro-oriented strings use the `mptbm_plugin_pro` domain — preserve as-is.
- **Self-instantiation:** adding a new class file means `require_once` it in the right
  loader (`MPTBM_Admin::load_file()` or `MPTBM_Frontend::load_file()`) **and** add a
  `new Class();` at the bottom, matching the existing pattern.
- **Don't double-instantiate** `MPTBM_Dummy_Import` (causes duplicate DOM IDs — there's a
  comment about this in `MPTBM_Admin.php`).
- **Security:** AJAX handlers use nonces (`check_ajax_referer`) and post-access
  validation (`validate_post_access`); fares are recomputed server-side. Keep that
  posture when extending the booking endpoints.
- The custom vehicle editor replaces the native editor; new vehicle meta fields belong in
  the `Admin/settings/` tab renderers and are saved through `MPTBM_Rent_Custom_Editor`.
