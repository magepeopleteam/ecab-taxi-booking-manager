# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

E-cab Taxi Booking Manager is a WordPress plugin for WooCommerce that provides comprehensive taxi and chauffeur booking functionality. The plugin integrates with Google Maps API for distance calculations and supports multiple pricing models (dynamic, manual, fixed hourly).

## Development Commands

### SASS Compilation
```bash
# Watch SASS files and compile to CSS
npm start
```

### REST API Testing
```bash
# Test API endpoints using curl
curl -X GET "https://your-site.com/wp-json/ecab-taxi/v1/taxis" \
  -H "X-API-Key: your-api-key"

# Generate API key via WordPress admin
# Go to Transportation > API Documentation

# Validate API response
curl -X POST "https://your-site.com/wp-json/ecab-taxi/v1/auth/validate-key" \
  -H "Content-Type: application/json" \
  -d '{"api_key":"your-api-key"}'
```

### File Structure
- `sass/` - SASS source files
- `assets/admin/` - Compiled admin CSS and JavaScript
- `assets/frontend/` - Frontend CSS and JavaScript
- `templates/` - PHP template files

### WordPress Development
```bash
# Clear rewrite rules (run after structural changes)
wp rewrite flush

# Check plugin status
wp plugin status ecab-taxi-booking-manager

# Activate/deactivate plugin for testing
wp plugin activate ecab-taxi-booking-manager
wp plugin deactivate ecab-taxi-booking-manager
```

### Testing Shortcodes
The plugin provides the `[mptbm_booking]` shortcode with various parameters:

```php
// Basic booking form
[mptbm_booking]

// Manual pricing with inline form
[mptbm_booking price_based='manual' form='inline']

// Fixed hourly pricing
[mptbm_booking price_based='fixed_hourly']

// With tabs for different booking types
[mptbm_booking tab='yes' tabs='hourly,distance,manual']
```

## Architecture Overview

### Core Plugin Structure

**Main Plugin Class**: `MPTBM_Plugin`
- Entry point in `MPTBM_Plugin.php`
- Handles activation, template assignment, and asset loading
- Manages page creation for booking forms

**Dependency Management**: `MPTBM_Dependencies`
- Located in `inc/MPTBM_Dependencies.php`
- Handles script/style enqueuing for both admin and frontend
- Manages Google Maps API integration
- Contains JavaScript constants for map configuration

### MVC-Style Architecture

**Admin Layer** (`Admin/` directory):
- `MPTBM_Admin.php` - Main admin controller
- `MPTBM_CPT.php` - Custom Post Type registration
- `settings/` subdirectory - Various settings panels
- `MPTBM_Wc_Checkout_*.php` - WooCommerce checkout integration

**Frontend Layer** (`Frontend/` directory):
- `MPTBM_Frontend.php` - Frontend controller
- `MPTBM_Shortcodes.php` - Shortcode handlers
- `MPTBM_Transport_Search.php` - Search functionality
- `MPTBM_Woocommerce.php` - WooCommerce integration

**Function Layer** (`inc/` directory):
- `MPTBM_Function.php` - Core utility functions
- `MPTBM_Query.php` - Database query functions
- `MPTBM_Layout.php` - Template and layout functions

### Key Components

**Custom Post Type**: `mptbm_rent`
- Represents transportation services/vehicles
- Uses custom fields for pricing, schedules, and features
- Supports custom taxonomies for categories and organizers

**Template System**:
- Custom templates in `templates/` directory
- Theme override capability via `mptbm_templates/` in active theme
- Template assignment via `transport_result.php` page template

**Google Maps Integration**:
- Requires Google Maps API key in plugin settings
- Supports autocomplete, distance calculation, and route mapping
- Country restriction and default coordinates configurable

**Pricing Models**:
1. **Dynamic**: Based on Google Maps distance calculation
2. **Manual**: Fixed pricing between predefined locations  
3. **Fixed Hourly**: Time-based pricing

**WooCommerce Integration**:
- Creates hidden WooCommerce products for bookings
- Custom checkout fields and validation
- Integration with WooCommerce payment gateways
- Order management through WooCommerce admin

**REST API System** (`/inc/MPTBM_REST_API.php`):
- Complete REST API for taxi booking operations independent of WooCommerce
- API key authentication with expiration and rate limiting
- Comprehensive endpoints for taxis, bookings, locations, and settings
- CORS support for web applications
- Request/response logging for debugging
- Visual API documentation with interactive key management

### Global Framework

**MP Global** (`mp_global/` directory):
- Shared framework used across MagePeople plugins
- Provides common utilities, settings API, and UI components
- Handles localization and multi-language support

### Page Builder Support
- **Gutenberg Block**: `MPTBM_Block.php`
- **Elementor Widget**: `MPTBM_Elementor_Widget.php`
- Both provide visual interfaces for inserting booking forms

## Important Settings & Configuration

### Required Setup
1. **Google Maps API Key**: Essential for plugin functionality
   - Configure in Plugin Settings > Map API Settings
   - Requires Places API and Maps JavaScript API enabled

2. **WooCommerce Dependency**: Plugin requires WooCommerce to be active
   - Booking process integrates with WooCommerce checkout
   - Hidden products created automatically for transportation bookings

### Key Plugin Settings
- **General Settings**: Label, slug, icon customization
- **Price Settings**: Pricing models and calculation rules
- **Operation Areas**: Geographic restrictions and zones
- **Date Settings**: Availability and scheduling
- **Extra Services**: Additional booking options

### Template Customization
Place custom templates in active theme:
```
wp-content/themes/your-theme/mptbm_templates/
```

The plugin will automatically use theme templates when available, falling back to plugin defaults.

## Development Notes

### Asset Management
- Admin styles compile from `sass/main.scss` to `assets/admin/admin_style.css`
- Frontend assets loaded conditionally based on page content
- Handles conflicts with other plugins (e.g., WP Travel Engine datepicker styles)

### Multi-language Support
- Plugin supports WPML and Polylang
- Text domain: `ecab-taxi-booking-manager`
- Translation files in `languages/` directory

### REST API Development
- API endpoints follow REST conventions with proper HTTP methods
- All responses include standardized JSON format with success/error states
- Authentication via X-API-Key header or api_key query parameter
- Rate limiting prevents abuse (configurable requests per minute)
- Database tables: `wp_mptbm_api_keys`, `wp_mptbm_api_logs`
- Access API documentation at: Transportation > API Documentation

### Caching Compatibility
- Version 1.3.2+ includes compatibility with major caching plugins
- Handles dynamic content and AJAX requests appropriately
- REST API responses properly cached based on authentication state

### Version Information
- Current version: 1.3.2
- Minimum WordPress: 5.3
- Minimum PHP: 7.0
- WooCommerce compatibility: 3.0+
