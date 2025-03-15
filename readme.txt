=== Taxi Booking Manager for WooCommerce - WordPress plugin | Ecab ===
Contributors: magepeopleteam, hamidxazad, aamahin
Author URI : https://mage-people.com
Tags: Taxi Service, Chauffeur Service, Ride Booking, Cab Booking, Transportation
Requires at least: 5.3
Stable tag: trunk
Tested up to: 6.7.1
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
	
A comprehensive taxi and chauffeur booking solution with fare calculations, integrated with WooCommerce, offering automation for seamless management.

== Description ==

[Taxi and Chauffeur Booking service Manager](https://mage-people.com/product/wordpress-taxi-cab-booking-plugin-for-woocommerce/) is a dynamic WordPress taxi and Chauffeur booking plugin that enhances your website functionality to leverage your taxi booking business potential. It allows you to create a fully functional taxi booking system, providing a convenient way for customers to book rides online. Whether you run a taxi service or need to integrate booking capabilities for transportation services, this plugin has got you covered.

## Make Yourself Comfortable With:
🧶 [View Live Taxi Booking Demo](https://taxi.mage-people.com/)
👉 [Plugin Documentation](https://docs.mage-people.com/ecab-taxi-booking-manager/)

## Taxi and Chauffeur Booking Solution that Changes the Game
Since the taxi business has evolved in different shapes with time, online taxi booking has become increasingly popular. Ecab has taken your taxi booking hassle creating the taxi booking manager plugin solution for your WordPress website. It is powered by WooCommerce and known by WordPress users as a taxi booking manager for WooCommerce.
 
As a result of our efforts, we have successfully replaced a large number of manual taxi booking systems with the digital data-driven, fastest, most effective, and [easy-to-use for Chauffeur booking solution](https://mage-people.com/product/wordpress-taxi-cab-booking-plugin-for-woocommerce/). That's why it is one of the best taxi booking plugins in WordPress.


Let's look at the key features that make the plugin more acceptable in its category.

## Key Features:

**🤝 User-Friendly Interface**
Enjoy a user-friendly interface that simplifies taxi booking management for administrators and customers.

**⏱ Flexible Booking Options** 
Provide customers with the flexibility to choose immediate pickups or pre-scheduled rides according to their preferences.

💵 Fare Calculation
Automatic fare calculation based on distance, time, or any custom criteria you define.

📰 Customizable Rates
Set up custom rate plans, allowing you to tailor pricing based on different zones, distances, or other factors.

💰 Multiple Payment Gateways
Easily integrate with popular payment gateways to offer secure and diverse payment options for customers.

🤹 Booking Management
Efficiently manage all taxi bookings from your WordPress dashboard, with the ability to view, modify, or cancel bookings as needed.

🗺 Google Maps API
Google Maps API is integrated for route mapping, enhancing the navigation experience for both customers and drivers.

💦 Responsive Design
The plugin is designed to be fully responsive, offering a smooth booking experience across various devices.

🛠 Gutenberg & Elementor Support
Easily add booking forms using the Site Editor (Gutenberg) block or an Elementor widget, providing seamless customization for different page builders.



**📍 API For Autocomplete Of Google Addresses**
Enhance the booking experience with auto-suggestive address suggestions for customers during the booking process.

**⌚ Establish Operating Hours**
Define the operational schedule for transportation services, or opt for 24-hour availability.

**🛠️ Pricing Model Tabs**
Easily switch between different pricing models using tabs for a seamless booking experience.

## Pro Features (Available in Pro Version):

**📧 📅 Google Calendar Integration **
Automatically add booking details to the admin's Google Calendar.
Provide a link after booking so customers can easily add the event to their own calendar.

**📧 Email Customization**
Receive order confirmations and deliver PDF receipts easily after successful payments with dual email functions.

**⏳ Wait Time Option With Extra Payment**
Offer extra waiting time for users with convenient pricing, ensuring a smooth traveling journey.

**🛒 Customizable Checkout Field**
Add, edit, or delete necessary fields for personal information collection before boarding a car or taxi cab, ensuring all needed data is securely stored.

**🛒 WooCommerce Custom Checkout Integration** 
Create unique checkout experiences for customers, offering payment methods, order notes, discounts, and special deals without coding.

**🎟️ Ticket Confirmation In PDF Format** 
Automatically generate PDF tickets or invoices after bookings, providing customers with convenient and secure invoices for accounting purposes.

**🚩 Designating Transport Operation Areas**
Set a fixed area for transport by using  Google map

**🏬 Establish GEO FENCE Boundaries and Pricing**
Utilize Google Maps to delineate both intercity and intracity zones, along with corresponding pricing

**🧢 Driver Panel to track service status**
Driver panel feature enables administrators to assign vehicles to drivers. Additionally, email notifications are automatically sent to relevant parties whenever there is a change in the service status of an order 

## Available Addons:

**⏰ [Peak Hour Addon](https://mage-people.com/product/taxi-peak-hour-pricing-addon/)**
Set peak hour pricing by date range and specific time range

**≣ Comprehensive Order List Section
View and manage all bookings in a detailed order list

**Third-Party Service:**
This plugin relies on the Google Maps API, a service provided by Google, Inc. It is used for displaying Google Maps and finding distance. Please note that your usage of this plugin constitutes acceptance of Google's terms and policies.

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
Yes, you need to obtain a Google Maps API key. Follow the instructions in the plugin settings to set up and enter your API key.

= How do I get a Google Maps API key? =
Visit the [Google Cloud Console](https://console.cloud.google.com/), create a project, enable the Google Maps JavaScript API, and generate an API key.

= Is Ecab Taxi Manager for WooCommerce Free? =
A. Yes! Ecab Taxi Manager for WooCommerce is free.

You can check the demo of this plugin from here:
[View Live PRO Version Demo For Business](https://taxi.mage-people.com/)

= Q.Any Documentation? =
A. Yes! Here is the [Online Documentation](https://docs.mage-people.com/ecab-taxi-booking-manager/).
 
= Q.I installed correctly but 404 error what can I do?  =
A. You need to Re-save permalink settings it will solve the 404. if still does not work that means your permalink not working, or you may have an access problem or you have a server permission problem. 

= Q.How its work? =
A. Woocommerce Events Manager is one of the simple event plugins for WordPress which is based on Woocommerce. It works as an individual event and its payment functionality is handled with WooCommerce so there are no worries about the payment gateway you can use every payment gateway that supports WooCommerce. The interesting part is the event post type is completely different there is no connection with WooCommerce products so you can sell anything from WooCommerce products. 


== Legal Protection ==

This transparency is crucial for legal protection. By using this plugin, you acknowledge and accept the reliance on the Google Maps API. Review the terms of use and privacy policy for both this plugin and the Google Maps API to ensure a comprehensive understanding of the services and how your data is handled.


== Changelog ==
= 1.2.3 =
1. Ride History feature added with custom design
2. Browser Notifications system added for booking updates
3. Google calendar integration added
4. Woocommerce order analytics added
5. Rest Api documentation added
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

