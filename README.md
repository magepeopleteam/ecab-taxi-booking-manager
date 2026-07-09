# E-cab Taxi Booking Manager

**Plugin Name:** E-cab Taxi Booking Manager for Woocommerce
**Version:** 2.0.2
**Requires PHP:** 7.4
**Requires WordPress:** 5.6+
**Requires Plugins:** WooCommerce
**Text Domain:** `ecab-taxi-booking-manager`
**Vendor:** https://github.com/magepeopleteam/ecab-taxi-booking-manager.git

A complete transportation solution for WordPress by MagePeople.
Provides distance-based, manual, and fixed-hourly taxi booking modes
backed by a custom post type for vehicles and Google Maps
distance/duration lookup.

---

## Installation

1. Upload the `ecab-taxi-booking-manager` folder to
   `wp-content/plugins/`, **or** install the zip via
   *Plugins → Add New → Upload Plugin*.
2. Install and activate **WooCommerce**.
3. Activate **E-cab Taxi Booking Manager** from the *Plugins* screen.
4. Configure the plugin under
   `E-cab Taxi Booking → Settings`.

If WooCommerce is missing, the plugin shows a chunked, memory-safe
installer popup (`Admin/MPTBM_Woo_Installer.php`) instead of failing.

---

## Custom Post Types

| CPT slug | Purpose |
|----------|---------|
| `mptbm_rent` | Vehicles (taxi inventory) |

---

## Custom Database Tables

| Table | Purpose |
|-------|---------|
| `wp_mptbm_api_keys` | External REST API key store |
| `wp_mptbm_api_logs` | External REST API request log |

---

## Constants

| Constant | Purpose |
|----------|---------|
| `MPTBM_PLUGIN_DIR` | Absolute path to the plugin directory |
| `MPTBM_PLUGIN_URL` | Public URL of the plugin |
| `MPTBM_PLUGIN_VERSION` | Plugin version string |

---

## Booking Modes

The plugin ships three booking modes that can be used individually or
side-by-side through a tabbed interface:

- **Distance-based** — fare calculated from km (Google Maps distance
  matrix + the plugin's own `MPTBM_Geo_Lib`).
- **Manual price** — operator sets a fixed price per vehicle.
- **Fixed hourly** — flat hourly rate with configurable hours.

---

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[mptbm_booking]` | Renders the booking form |
| `[mptbm_booking price_based="manual"]` | Manual-price variant |
| `[mptbm_booking price_based="fixed_hourly"]` | Fixed-hourly variant |
| `[mptbm_booking form="inline"]` | Inline form layout |
| `[mptbm_booking tab="yes" tabs="hourly,distance,manual"]` | Tabbed comparison layout |

---

## Auto-Created Pages

The plugin creates the following pages on activation:

- `transport_booking` — main booking page
- `transport_booking_manual` — manual-price booking page
- `transport_booking_fixed_hourly` — fixed-hourly booking page
- `transport-result` — booking result / summary page
- `transport-tabs` — tabbed booking page

The result page is automatically assigned the
`transport_result.php` template via the
`wptbm_assign_template_to_page()` callback.

---

## Settings Page

`edit.php?post_type=mptbm_rent&page=mptbm_settings_page`

Common options used by the plugin:

- `mptbm_general_settings`
- `mp_global_settings`

---

## Key Files

- `MPTBM_Plugin.php` — entry file; defines constants, loads
  dependencies, registers the result-page template.
- `inc/MPTBM_Dependencies.php` — admin/frontend asset loader.
- `inc/MPTBM_Geo_Lib.php` — own geo / haversine helpers (used as
  fallback / supplement to Google).
- `Frontend/MPTBM_Block.php` — Gutenberg block for the booking form.
- `Frontend/MPTBM_Elementor_Widget.php` — Elementor widget for the
  booking form.
- `Frontend/MPTBM_Wc_Checkout_Fields_Helper.php` — WooCommerce
  checkout field injection.
- `Admin/MPTBM_Woo_Installer.php` — chunked WooCommerce installer
  popup.
- `transport_result.php` — custom page template for the result page.
- `mp_global/MP_Global_File_Load.php` — global helper functions
  loader.
- `sass/main.scss` — admin SASS source
  (compiled to `assets/admin/admin_style.css`).

---

## Block Editor & Elementor

- **Gutenberg block** registered by `Frontend/MPTBM_Block.php`.
- **Elementor widget** registered by
  `Frontend/MPTBM_Elementor_Widget.php`.

---

## Build Commands

```bash
# Watch admin SASS (sass --watch sass/main.scss assets/admin/admin_style.css)
cd wp-content/plugins/ecab-taxi-booking-manager
npm start
```

`package.json` only drives the SASS watcher; there is no full JS
bundling pipeline.

---

## External REST API

The plugin ships its own API-key gated REST surface backed by the
`wp_mptbm_api_keys` and `wp_mptbm_api_logs` tables. Keys are issued
and rotated from the admin; requests are written to the log table for
auditing.

---

## Compatibility Notes

- The plugin dequeues **WP Travel Engine** styles on booking pages to
  avoid datepicker conflicts.
- Result-page template assignment is automatic on activation and
  re-asserted on `update_option_mp_global_settings` and
  `save_post_page`.

---

## PRO Add-On

For driver management, frontend driver panel, customer dashboard, and
PDF tickets, install
[ecab-taxi-booking-manager-pro](https://github.com/magepeopleteam/ecab-taxi-booking-manager-pro).

Related add-ons:

- [distance-base-tier-pricing-addon-for-taxi-booking](https://github.com/magepeopleteam/distance-base-tier-pricing-addon-for-taxi-booking)

---

## Security

- Every PHP file begins with `if (!defined('ABSPATH')) { die; }`.
- Inputs are sanitised; outputs are escaped on render.
- License keys are stored in `wp_options` — sanitise output when
  echoing.

---

## Development Notes

- Entry file: `MPTBM_Plugin.php`.
- Vendor: https://github.com/magepeopleteam/ecab-taxi-booking-manager.git
