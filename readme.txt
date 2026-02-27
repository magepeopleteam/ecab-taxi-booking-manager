=== E-cab Taxi Booking Manager for Woocommerce ===
Contributors: magepeopleteam, hamidxazad, aamahin
Author URI : https://mage-people.com
Tags: Taxi booking, Cab booking, Ride booking , Chauffeur service, Airport transfer, Distance based pricing, Fare calculator, Car booking, Map Booking, Limousine service, Transportation, Dispatch system
Requires at least: 5.3
Stable tag: trunk
Tested up to: 6.8.2
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
	
Taxi Booking & Cab Booking for WooCommerce. Chauffeur service with fare calculator, distance pricing, and OpenStreetMap. Perfect for airport transfers.

== Description ==

E-cab is a professional Taxi Booking and Chauffeur Service plugin for WooCommerce. Automate your business with a precise fare calculator, distance-based pricing, and integrated map support (OpenStreetMap and Google Maps).
Whether you offer airport transfers, luxury chauffeur services, or local cab bookings, this system handles everything from ride scheduling to secure checkout. Give your customers a seamless way to book rides online with real-time price estimation and automated dispatch management.

==See E-cab in Action:==
https://www.youtube.com/watch?v=N1NlvhcJ7D8
*Note: This video demonstrates the full ecosystem. Advanced features like the Driver Panel, Geo-Fencing, and Google Calendar Sync are available in the [Pro Version](https://mage-people.com/product/wordpress-taxi-cab-booking-plugin-for-woocommerce/).*

## Make Yourself Comfortable With:
🧶 [View Live Taxi Booking Demo](https://demo.ecabtaxi.com/)
👉 [Plugin Documentation](https://ecabtaxi.com/docs/)

## Why Choose E-cab? (Key Features):
**🗺️ Multiple Map Providers**
OpenStreetMap Integration (FREE): Use OpenStreetMap with no API costs or Google API key required! Includes full route mapping and distance calculation.
Google Maps API: Integration with faster place search and global address autocomplete. Choose your preferred provider in settings.

**💵 Smart Fare Calculation**
Automatic fare calculation based on distance, time, or custom criteria. Automate your pricing and eliminate manual quoting.

**⏱️ Flexible Booking Options**
Provide customers with the flexibility to choose immediate pickups or pre-scheduled rides according to their travel plans.

**🛠️ Pricing Model Tabs**
Easily switch between different pricing models (Hourly, Distance, or Manual) using a sleek tabbed interface for a better user experience.

**💰 WooCommerce Integration**
Fully integrated with WooCommerce. Securely accept payments using any gateway like Stripe, PayPal, or local providers.

**🛠️ Gutenberg & Elementor Support**
Easily add booking forms using the dedicated Site Editor (Gutenberg) block or Elementor widget. No coding required.

**📍 Google Address Autocomplete**
Enhance the booking experience with auto-suggestive address suggestions for customers to ensure location accuracy.

**📰 Customizable Rates**
Set up custom rate plans, allowing you to tailor pricing based on different zones, distances, or vehicle types.

**⌚ Establish Operating Hours**
Define specific operational schedules for your transportation services or opt for 24-hour availability.

**🤹 Efficient Booking Management**
Manage all taxi bookings directly from your WordPress dashboard, with the ability to view, modify, or cancel orders instantly.

**💦 Fully Responsive Design**
Designed to be mobile-first, offering a smooth and professional booking experience across smartphones, tablets, and desktops


## Pro Features (Available in Pro Version):

**📧 📅 Google Calendar Integration **
Automatically sync booking details to the admin’s Google Calendar. Customers also receive a link to add the trip to their own personal calendars.

**📧 Email & PDF Customization**
Receive professional order confirmations and automatically deliver PDF receipts/invoices to customers after successful payments.

**⏳ Paid Wait Time Option**
Offer extra waiting time for users with automated pricing. Perfect for airport pickups where flight delays or luggage collection take extra time.

**🛒 Advanced Checkout Fields**
Customizable checkout fields let you add, edit, or delete personal info fields, ensuring you collect specific data (like flight numbers) before the ride.

**🚩 Operation Areas & Geo-Fencing**
Designate specific transport operation areas on the map. Use Geo-Fencing to set different pricing for intercity and intracity zones.

**🚍 Driver Management Panel**
A dedicated panel for admins to assign vehicles to drivers. Drivers can track service status, and automated emails notify all parties of any changes. 

**🔢 Quantity & Interval Booking**
Set the quantity of available transport with specific booking time intervals to prevent overbooking and manage fleet availability.

**✈️ Specialized Airport Transfer Shortcodes**

Fixed Route Shortcode: Show fixed pickup and drop-off points from specific operation areas (e.g., Downtown to Airport).

Zone-to-Point Shortcode: Allow pickups from an entire operation area with drop-offs at specific designated places.

**🏷️ Hybrid Pricing Logic**
Use a specialized shortcode to charge a fixed price within an operation area, manual pricing for specific destinations, and distance/duration pricing for all other locations.

**📋 Comprehensive Order Management**
An advanced order list view that allows you to edit orders, manually change drivers, and manage the full lifecycle of every booking.

## Available Addons:

**⏰ [Peak Hour Addon](https://mage-people.com/product/taxi-peak-hour-pricing-addon/)**
Set peak hour pricing by date range and specific time range

**🚗 [Distance Based Tier Pricing Addon](https://mage-people.com/product/distance-based-tier-pricing-for-e-cab)**
Add distance-based tiered pricing to your E-Cab rides. Automatically adjust fares by trip length for flexible and fair ride costs.

**Third-Party Services:**
**OpenStreetMap (Default - FREE)**: The plugin uses OpenStreetMap by default, which is completely free and requires no API keys. OpenStreetMap provides route mapping, distance calculation, and address search functionality at no cost.

**Google Maps API (Optional)**: If you choose to use Google Maps, this plugin relies on the Google Maps API, a service provided by Google, Inc. Google Maps offers faster place search and more places than OpenStreetMap. Please note that your usage of Google Maps constitutes acceptance of Google's terms and policies.

**Link to Google Maps API:**
For more information about the Google Maps API, visit: [Google Maps API Link](https://developers.google.com/maps/documentation/javascript/get-api-key)

**Terms of Use:**
Review the Google Maps API terms of use: [Google Maps API Terms of Use Link](https://developers.google.com/maps/terms-20180207)

**Privacy Policy:**
Understand how Google handles your data through the Maps API: [Google Privacy Policy Link](https://policies.google.com/privacy)

== Installation ==
Download the ecab-taxi-booking-manager.zip file from the WordPress plugin repository.
Log in to your WordPress admin dashboard.
Navigate to "Plugins" > "Add New."
Click the "Upload Plugin" button at the top of the page.
Choose the ecab-taxi-booking-manager.zip file and click "Install Now."
Once installed, click "Activate" to enable the Ecab Taxi Booking Manager WordPress plugin.

**Note**: The plugin works out of the box with OpenStreetMap (no API key required). If you prefer to use Google Maps, you can configure your Google Maps API key in the plugin settings.

== Guideline ==
Shortcode:
[mptbm_booking price_based='dynamic' form='horizontal' progressbar='yes' map='yes']

Parameters:
- **price_based**: Determines the pricing model.
  - Options:
    - `dynamic` (default): Pricing is based on Google Map distance.
    - `manual`: Fixed pricing between two locations.
    - `fixed_hourly`: Price by hour/time.
  - Example: [mptbm_booking price_based='manual']

- **form**: Sets the form layout.
  - Options:
    - `horizontal` (default): Standard form layout.
    - `inline`: Minimal single-line form.

- **progressbar**: Controls the display of the progress bar.
  - Options:
    - `yes` (default): Progress bar is visible.
    - `no`: Progress bar is hidden.

- **map**: Toggles the map display.
  - Options:
    - `yes` (default): Map is displayed.
    - `no`: Map is hidden.

- **tab**: Enables or disables tabbed options.
  - Options:
    - `no` (default): Tabs are disabled.
    - `yes`: Displays tabs for different booking types (hourly, distance, manual).

- **tabs** (used when `tab` is set to 'yes'): Specifies which tabs to display or exclude.
  - To show all tabs: [mptbm_booking tab='yes' tabs='hourly,distance,manual']
  - To show specific tabs: [mptbm_booking tab='yes' tabs='hourly,distance'] (hides 'manual')
  - To show only one tab: [mptbm_booking tab='yes' tabs='manual'] (hides 'hourly' and 'distance')

Examples:
- Display all tabs:
  [mptbm_booking tab='yes' tabs='hourly,distance,manual']

- Display only 'hourly' and 'distance' tabs:
  [mptbm_booking tab='yes' tabs='hourly,distance']

- Display only the 'manual' tab:
  [mptbm_booking tab='yes' tabs='manual']


== Frequently Asked Questions ==

= Is an API key required? =
No. By default, the plugin uses OpenStreetMap, which is 100% free and requires no API key or credit card. If you prefer to use Google Maps for its advanced autocomplete features, you can easily switch in the settings and enter your Google API key there.

= How do I get a Google Maps API key? =
Visit the [Google Cloud Console](https://console.cloud.google.com/), create a project, enable the Google Maps JavaScript API, and generate an API key.

= Is Ecab Taxi Manager for WooCommerce Free? =
A. Yes! Ecab Taxi Manager for WooCommerce is free.

= How does it work with WooCommerce? =
E-cab works as an individual booking system where payment functionality is handled by WooCommerce. This allows you to use any payment gateway that supports WooCommerce without affecting your standard shop products.

You can check the demo of this plugin from here:
[View Live PRO Version Demo For Business](https://demo.ecabtaxi.com/)

= Q.Any Documentation? =
A. Yes! Here is the [Online Documentation](https://ecabtaxi.com/docs/).
 
= Q.I installed correctly but 404 error what can I do?  =
A. You need to Re-save permalink settings it will solve the 404. if still does not work that means your permalink not working, or you may have an access problem or you have a server permission problem. 

= Where do I report security bugs found in this plugin? =
Please report security bugs found in the source code of the Taxi Booking Manager for WooCommerce plugin through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/9e5fc03b-cce7-4df9-a6aa-a019346760d7). The Patchstack team will assist you with verification, CVE assignment, and notify the developers of this plugin.

== Legal Protection ==

This transparency is crucial for legal protection. By using this plugin, you acknowledge and accept the reliance on the Google Maps API. Review the terms of use and privacy policy for both this plugin and the Google Maps API to ensure a comprehensive understanding of the services and how your data is handled.


== Changelog ==
= 2.0.2 =
1. Taxi duplication issue fixed
2. Hidden product issue fixed
3. Fixed translations
4. Openstreet issue fixed with caching plugin
= 2.0.1 = 
1. Api issues resolved
2. buffer time for other days issue resolved 
3. tax issue fixed 
4. decimel in extra service issue fixed
5. xss vulnerbility fixed
6. dropdown issue for manual pricing fixed
7. date picker in admin issue fixed
= 2.0.0 = 
1. Openstreet map implemented
2. extra service image issue fixed 
3. Safari/iphone price not showing issue fixed
= 1.3.2 = 
1. Made compatiable with major caching plugins 
2. Manual shortcode div not properly closing issue fixed
= 1.3.1 = 
1. Drop off location can be hidden when it is hourly price
2. Minimal rental duration can be added when it is hourly price 
3. Minor bug fixes 
= 1.3.0 =
1. Added checkout disable field to disable all checkout field from this plugin
2. 0 priced transport can be booked now
3. Multi pricing tabs improved
4. Api improved
5. Transport filter improved
= 1.2.6 =
1. Added dependency for pro version 
2. Minor issues solved
3. Driver panel improved
= 1.2.5 =
1. No transport available section can be changed Now
2. Issue fixed with transport-result page slug
= 1.2.4 =
1. Documentation changed
= 1.2.3 =
1. Google calendar integration added
2. Woocommerce order analytics added
3. Rest Api documentation added
= 1.2.2 = 
1. Gutenberg block added for booking form
2. Elementor widget added for booking form
= 1.2.1 =
1. Manual Pricing Slug is fixed
2. Fixed Hourly Responsive issue Fixed
3. KM to mile feature added
= 1.2.0 =
1. Geo Fence fixed and intercity with extra pricing added for pro version 
2. Return Time and date added
3. Filter by bag and passenger added
4. Transport Schedule added
5. Initial Price added 
6. Location taxonomy added for manual pricing 
7. Manual Pricing Slug issue fixed
8. Fixed Hourly Responsive issue Fixed


