<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Status')) {
		class MPTBM_Status {
			public function __construct() {
				add_action('admin_menu', array($this, 'status_menu'));
			}
			public function status_menu() {
				$cpt = MPTBM_Function::get_cpt();
				add_submenu_page('edit.php?post_type=' . $cpt, esc_html__('Status', 'ecab-taxi-booking-manager'), '<span style="color:yellow">' . esc_html__('Status', 'ecab-taxi-booking-manager') . '</span>', 'manage_options', 'mptbm_status_page', array($this, 'status_page'));
			}

			/**
			 * One diagnostic row.
			 *
			 * $status is what turns this page from a list of numbers into something an
			 * admin can act on: 'good' needs no explanation, so $hint is only rendered
			 * for 'warning'/'critical'. 'info' is for context that cannot be right or
			 * wrong (server software, site language) and is deliberately left out of
			 * the pass/fail tally in the header.
			 */
			private function item($label, $value, $status = 'info', $hint = '') {
				return array(
					'label'  => $label,
					'value'  => ($value === '' || $value === null) ? __('Not set', 'ecab-taxi-booking-manager') : $value,
					'status' => $status,
					'hint'   => $hint,
				);
			}

			/**
			 * Bytes from a PHP shorthand size ("256M", "1G", "-1" for unlimited).
			 * Returns -1 for unlimited so callers can treat it as "never a problem"
			 * rather than as the smallest possible value.
			 */
			private function to_bytes($size) {
				$size = trim((string) $size);
				if ($size === '' ) {
					return 0;
				}
				if ((int) $size === -1) {
					return -1;
				}
				$unit  = strtolower(substr($size, -1));
				$bytes = (float) $size;
				switch ($unit) {
					case 'g':
						$bytes *= 1024;
						// no break - g is m*1024, m is k*1024, k is bytes*1024
					case 'm':
						$bytes *= 1024;
						// no break
					case 'k':
						$bytes *= 1024;
				}
				return (int) $bytes;
			}

			private function format_bytes($bytes) {
				if ((int) $bytes === -1) {
					return __('Unlimited', 'ecab-taxi-booking-manager');
				}
				$bytes = (float) $bytes;
				if ($bytes >= 1073741824) {
					return round($bytes / 1073741824, 2) . ' GB';
				}
				if ($bytes >= 1048576) {
					return round($bytes / 1048576, 0) . ' MB';
				}
				if ($bytes >= 1024) {
					return round($bytes / 1024, 0) . ' KB';
				}
				return (int) $bytes . ' B';
			}

			/** Warning below $warn, critical below $min. -1 (unlimited) always passes. */
			private function memory_status($bytes, $min, $warn) {
				if ((int) $bytes === -1) {
					return 'good';
				}
				if ($bytes < $min) {
					return 'critical';
				}
				if ($bytes < $warn) {
					return 'warning';
				}
				return 'good';
			}

			private function yes_no($condition) {
				return $condition ? __('Yes', 'ecab-taxi-booking-manager') : __('No', 'ecab-taxi-booking-manager');
			}

			/**
			 * Every card on the page, built as data so the tally in the header and the
			 * copy-to-clipboard support report are both derived from the same source
			 * and can never drift out of step with what is rendered.
			 */
			private function get_sections() {
				global $wpdb;

				$sections = array();

				// ---- Server ---------------------------------------------------------
				$php_version = PHP_VERSION;
				$php_status  = version_compare($php_version, '8.0', '>=') ? 'good' : (version_compare($php_version, '7.4', '>=') ? 'warning' : 'critical');
				$php_hint    = version_compare($php_version, '8.0', '>=') ? '' : __('PHP 8.0 or newer is recommended. Ask your host to upgrade — older versions no longer receive security fixes.', 'ecab-taxi-booking-manager');

				$server_software = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : '';

				// Booking times are stored and compared against the WordPress timezone.
				// A PHP default of anything other than UTC is not itself a fault, so this
				// stays informational — but it is the first thing to look at whenever a
				// customer reports pickup times drifting by a fixed number of hours.
				$wp_timezone  = wp_timezone_string();
				$php_timezone = date_default_timezone_get();

				$db_version = '';
				if (isset($wpdb)) {
					$db_version = $wpdb->get_var('SELECT VERSION()');
				}

				$sections[] = array(
					'title' => __('Server Environment', 'ecab-taxi-booking-manager'),
					'icon'  => 'fas fa-server',
					'items' => array(
						$this->item(__('PHP Version', 'ecab-taxi-booking-manager'), $php_version, $php_status, $php_hint),
						$this->item(__('Web Server', 'ecab-taxi-booking-manager'), $server_software ?: __('Unknown', 'ecab-taxi-booking-manager')),
						$this->item(__('Database Version', 'ecab-taxi-booking-manager'), $db_version ?: __('Unknown', 'ecab-taxi-booking-manager')),
						$this->item(__('cURL Version', 'ecab-taxi-booking-manager'), function_exists('curl_version') ? (curl_version()['version'] ?? __('Unknown', 'ecab-taxi-booking-manager')) : __('Not available', 'ecab-taxi-booking-manager'), function_exists('curl_version') ? 'good' : 'critical', __('cURL is required to reach the Google Maps and payment gateway APIs.', 'ecab-taxi-booking-manager')),
						$this->item(__('HTTPS', 'ecab-taxi-booking-manager'), $this->yes_no(is_ssl()), is_ssl() ? 'good' : 'warning', __('Payment gateways and the browser geolocation used by the map require HTTPS.', 'ecab-taxi-booking-manager')),
						$this->item(__('WordPress Timezone', 'ecab-taxi-booking-manager'), $wp_timezone ?: __('Not set', 'ecab-taxi-booking-manager'), $wp_timezone ? 'good' : 'warning', __('Booking dates and times follow this setting. Set it under Settings > General > Timezone.', 'ecab-taxi-booking-manager')),
						$this->item(__('PHP Default Timezone', 'ecab-taxi-booking-manager'), $php_timezone),
					),
				);

				// ---- WordPress ------------------------------------------------------
				$wp_version   = get_bloginfo('version');
				$wp_status    = version_compare($wp_version, '5.6', '>=') ? 'good' : 'warning';
				$cron_off     = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
				$permalink    = get_option('permalink_structure');
				$debug_on     = defined('WP_DEBUG') && WP_DEBUG;
				$debug_display = defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY;

				$sections[] = array(
					'title' => __('WordPress', 'ecab-taxi-booking-manager'),
					'icon'  => 'fab fa-wordpress',
					'items' => array(
						$this->item(__('WordPress Version', 'ecab-taxi-booking-manager'), $wp_version, $wp_status, __('WordPress 5.6 or newer is required by this plugin.', 'ecab-taxi-booking-manager')),
						$this->item(__('Site Language', 'ecab-taxi-booking-manager'), get_locale()),
						$this->item(__('Multisite', 'ecab-taxi-booking-manager'), $this->yes_no(is_multisite())),
						$this->item(__('Permalinks', 'ecab-taxi-booking-manager'), $permalink ? __('Pretty permalinks', 'ecab-taxi-booking-manager') : __('Plain', 'ecab-taxi-booking-manager'), $permalink ? 'good' : 'warning', __('Plain permalinks break the REST API endpoints this plugin uses. Set any other option under Settings > Permalinks.', 'ecab-taxi-booking-manager')),
						$this->item(__('WP Cron', 'ecab-taxi-booking-manager'), $cron_off ? __('Disabled', 'ecab-taxi-booking-manager') : __('Enabled', 'ecab-taxi-booking-manager'), $cron_off ? 'warning' : 'good', __('Scheduled work (reminder emails, calendar sync) will not run unless a real server cron calls wp-cron.php.', 'ecab-taxi-booking-manager')),
						// Displaying errors on a live site leaks paths and breaks AJAX
						// responses by printing notices into the JSON, so it is called out
						// separately from WP_DEBUG itself, which is harmless when logged.
						$this->item(__('Debug Mode', 'ecab-taxi-booking-manager'), $debug_on ? ($debug_display ? __('On, errors shown on screen', 'ecab-taxi-booking-manager') : __('On, errors logged only', 'ecab-taxi-booking-manager')) : __('Off', 'ecab-taxi-booking-manager'), ($debug_on && $debug_display) ? 'warning' : 'good', __('WP_DEBUG_DISPLAY is on. On a live site this prints notices into AJAX responses and can break the booking form. Log errors to a file instead.', 'ecab-taxi-booking-manager')),
					),
				);

				// ---- Memory & limits ------------------------------------------------
				$php_memory   = $this->to_bytes(ini_get('memory_limit'));
				$wp_memory    = $this->to_bytes(defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : '');
				$wp_max_mem   = $this->to_bytes(defined('WP_MAX_MEMORY_LIMIT') ? WP_MAX_MEMORY_LIMIT : '');
				$peak_usage   = memory_get_peak_usage(true);
				$exec_time    = (int) ini_get('max_execution_time');
				$input_vars   = (int) ini_get('max_input_vars');
				$post_max     = $this->to_bytes(ini_get('post_max_size'));
				$upload_max   = $this->to_bytes(ini_get('upload_max_filesize'));

				$peak_status = 'good';
				$peak_hint   = '';
				if ($php_memory > 0) {
					$peak_ratio = $peak_usage / $php_memory;
					if ($peak_ratio >= 0.9) {
						$peak_status = 'critical';
						$peak_hint   = __('This page alone is already using almost all the memory PHP allows. Importing, PDF generation or a large booking list will fail. Raise the PHP memory limit.', 'ecab-taxi-booking-manager');
					} elseif ($peak_ratio >= 0.7) {
						$peak_status = 'warning';
						$peak_hint   = __('Memory use is close to the limit. Heavier screens such as demo import or PDF generation may run out.', 'ecab-taxi-booking-manager');
					}
				}

				$sections[] = array(
					'title' => __('Memory & Limits', 'ecab-taxi-booking-manager'),
					'icon'  => 'fas fa-microchip',
					'items' => array(
						$this->item(__('PHP Memory Limit', 'ecab-taxi-booking-manager'), $this->format_bytes($php_memory), $this->memory_status($php_memory, 134217728, 268435456), __('256 MB is recommended. Below 128 MB, PDF tickets and the demo importer are likely to fail. Ask your host to raise memory_limit.', 'ecab-taxi-booking-manager')),
							// 40M is WordPress's own stock default and millions of sites run on
						// it happily, so it must never be reported as critical here - the
						// heavy screens (PDF, import) are admin-side and governed by
						// WP_MAX_MEMORY_LIMIT below, not by this value.
						$this->item(__('WP Memory Limit', 'ecab-taxi-booking-manager'), defined('WP_MEMORY_LIMIT') ? $this->format_bytes($wp_memory) : __('Not defined', 'ecab-taxi-booking-manager'), defined('WP_MEMORY_LIMIT') ? $this->memory_status($wp_memory, 41943040, 134217728) : 'info', __('This is the WordPress front-end limit and 40 MB is the WordPress default, so this is not urgent. Raising it to 128M in wp-config.php gives busy booking pages more headroom.', 'ecab-taxi-booking-manager')),
						$this->item(__('WP Admin Memory Limit', 'ecab-taxi-booking-manager'), defined('WP_MAX_MEMORY_LIMIT') ? $this->format_bytes($wp_max_mem) : __('Not defined', 'ecab-taxi-booking-manager'), defined('WP_MAX_MEMORY_LIMIT') ? $this->memory_status($wp_max_mem, 134217728, 268435456) : 'info', __('Add define(\'WP_MAX_MEMORY_LIMIT\', \'512M\'); to wp-config.php so admin screens get more headroom than the front end.', 'ecab-taxi-booking-manager')),
						$this->item(__('Peak Memory Used (this page)', 'ecab-taxi-booking-manager'), $this->format_bytes($peak_usage), $peak_status, $peak_hint),
						$this->item(__('Max Execution Time', 'ecab-taxi-booking-manager'), $exec_time === 0 ? __('Unlimited', 'ecab-taxi-booking-manager') : $exec_time . 's', ($exec_time === 0 || $exec_time >= 60) ? 'good' : 'warning', __('60 seconds or more is recommended, so imports and PDF generation are not cut off mid-run.', 'ecab-taxi-booking-manager')),
						// The vehicle editor posts one field per pricing row per day, so a
						// busy vehicle can quietly exceed max_input_vars - PHP then drops
						// the overflow silently and the save looks successful while losing
						// data. This is the check that explains that class of bug report.
						$this->item(__('Max Input Vars', 'ecab-taxi-booking-manager'), $input_vars ?: __('Unknown', 'ecab-taxi-booking-manager'), ($input_vars === 0 || $input_vars >= 3000) ? 'good' : 'warning', __('3000 or more is recommended. Below that, PHP silently discards the extra fields when saving a vehicle with many pricing rows, and the changes appear to save but do not.', 'ecab-taxi-booking-manager')),
						$this->item(__('Post Max Size', 'ecab-taxi-booking-manager'), $this->format_bytes($post_max), $this->memory_status($post_max, 8388608, 33554432), __('32 MB or more is recommended for large vehicle galleries and file uploads at checkout.', 'ecab-taxi-booking-manager')),
						$this->item(__('Max Upload Size', 'ecab-taxi-booking-manager'), $this->format_bytes($upload_max), $this->memory_status($upload_max, 2097152, 8388608), __('8 MB or more is recommended for vehicle images and checkout file uploads.', 'ecab-taxi-booking-manager')),
					),
				);

				// ---- PHP extensions -------------------------------------------------
				// Required = the plugin cannot do its job without it. Recommended = one
				// feature degrades. Anything missing gets the reason, not just a cross.
				$extensions = array(
					'curl'     => array('required' => true,  'reason' => __('Needed for Google Maps, OSRM routing and payment gateway requests.', 'ecab-taxi-booking-manager')),
					'json'     => array('required' => true,  'reason' => __('Needed for every AJAX response in the booking form.', 'ecab-taxi-booking-manager')),
					'mbstring' => array('required' => true,  'reason' => __('Needed for PDF tickets and for correct handling of accented and non-Latin text.', 'ecab-taxi-booking-manager')),
					'gd'       => array('required' => true,  'reason' => __('Needed to render images and barcodes inside PDF tickets.', 'ecab-taxi-booking-manager')),
					'dom'      => array('required' => true,  'reason' => __('Needed by the PDF generator to parse the ticket template.', 'ecab-taxi-booking-manager')),
					'xml'      => array('required' => true,  'reason' => __('Needed by the PDF generator and several payment gateways.', 'ecab-taxi-booking-manager')),
					'openssl'  => array('required' => true,  'reason' => __('Needed for secure calls to payment gateways and Google APIs.', 'ecab-taxi-booking-manager')),
					'zip'      => array('required' => false, 'reason' => __('Needed to install the PDF support dependency and to import demo data.', 'ecab-taxi-booking-manager')),
					'fileinfo' => array('required' => false, 'reason' => __('Needed to validate the file type of checkout uploads.', 'ecab-taxi-booking-manager')),
					'iconv'    => array('required' => false, 'reason' => __('Improves character conversion in PDF tickets.', 'ecab-taxi-booking-manager')),
					'intl'     => array('required' => false, 'reason' => __('Improves date, number and currency formatting for non-English sites.', 'ecab-taxi-booking-manager')),
				);
				$extension_items = array();
				foreach ($extensions as $extension => $meta) {
					$loaded = extension_loaded($extension);
					$extension_items[] = $this->item(
						$extension,
						$loaded ? __('Installed', 'ecab-taxi-booking-manager') : __('Missing', 'ecab-taxi-booking-manager'),
						$loaded ? 'good' : ($meta['required'] ? 'critical' : 'warning'),
						$meta['reason'] . ' ' . __('Ask your host to enable it.', 'ecab-taxi-booking-manager')
					);
				}
				$sections[] = array(
					'title' => __('PHP Extensions', 'ecab-taxi-booking-manager'),
					'icon'  => 'fas fa-puzzle-piece',
					'items' => $extension_items,
				);

				// ---- Plugins --------------------------------------------------------
				$wc_installed = MP_Global_Function::check_woocommerce() == 1;
				$wc_version   = ($wc_installed && function_exists('WC')) ? WC()->version : '';
				$pro_active   = class_exists('MPTBM_Dependencies_Pro');
				$from_name    = get_option('woocommerce_email_from_name');
				$from_email   = get_option('woocommerce_email_from_address');

				$hpos = __('Not applicable', 'ecab-taxi-booking-manager');
				if ($wc_installed && class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
					$hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
						? __('Enabled (HPOS)', 'ecab-taxi-booking-manager')
						: __('Legacy post storage', 'ecab-taxi-booking-manager');
				}

				$plugin_items = array(
					$this->item(__('E-cab Taxi Booking Manager', 'ecab-taxi-booking-manager'), defined('MPTBM_PLUGIN_VERSION') ? MPTBM_PLUGIN_VERSION : __('Unknown', 'ecab-taxi-booking-manager'), 'good'),
					$this->item(__('E-cab Taxi Booking Manager PRO', 'ecab-taxi-booking-manager'), $pro_active ? (defined('MPTBM_PLUGIN_VERSION_PRO') ? MPTBM_PLUGIN_VERSION_PRO : __('Active', 'ecab-taxi-booking-manager')) : __('Not installed', 'ecab-taxi-booking-manager'), $pro_active ? 'good' : 'info'),
					// WooCommerce is optional since 2.0.x (the plugin has its own
					// standalone checkout), so its absence is information, not a fault.
					$this->item(__('WooCommerce', 'ecab-taxi-booking-manager'), $wc_installed ? $wc_version : __('Not installed', 'ecab-taxi-booking-manager'), $wc_installed ? 'good' : 'info'),
				);
				if ($wc_installed) {
					$plugin_items[] = $this->item(__('WooCommerce Order Storage', 'ecab-taxi-booking-manager'), $hpos);
					$plugin_items[] = $this->item(__('Email From Name', 'ecab-taxi-booking-manager'), $from_name, $from_name ? 'good' : 'warning', __('Booking confirmation emails will be sent without a sender name. Set it under WooCommerce > Settings > Emails.', 'ecab-taxi-booking-manager'));
					$plugin_items[] = $this->item(__('Email From Address', 'ecab-taxi-booking-manager'), $from_email, $from_email ? 'good' : 'warning', __('Booking confirmation emails may be rejected as spam without a valid sender address. Set it under WooCommerce > Settings > Emails.', 'ecab-taxi-booking-manager'));
				}
				$sections[] = array(
					'title' => __('Plugins & Integrations', 'ecab-taxi-booking-manager'),
					'icon'  => 'fas fa-plug',
					'items' => $plugin_items,
				);

				// ---- Booking setup --------------------------------------------------
				$map_provider  = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'openstreetmap');
				$browser_key   = MP_Global_Function::get_settings('mptbm_map_api_settings', 'gmap_api_key');
				$server_key    = MP_Global_Function::get_settings('mptbm_map_api_settings', 'gmap_server_api_key');
				$needs_google  = ($map_provider === 'enable');
				$vehicle_count = wp_count_posts(MPTBM_Function::get_cpt());
				$vehicle_count = $vehicle_count ? (int) $vehicle_count->publish : 0;

				$map_labels = array(
					'enable'         => __('Google Maps', 'ecab-taxi-booking-manager'),
					'openstreetmap'  => __('OpenStreetMap', 'ecab-taxi-booking-manager'),
					'disable'        => __('Without map API', 'ecab-taxi-booking-manager'),
				);

				$booking_items = array(
					$this->item(__('Map Provider', 'ecab-taxi-booking-manager'), $map_labels[$map_provider] ?? $map_provider),
					$this->item(__('Published Vehicles', 'ecab-taxi-booking-manager'), $vehicle_count, $vehicle_count > 0 ? 'good' : 'warning', __('No vehicle is published yet, so a search will always return no results.', 'ecab-taxi-booking-manager')),
				);
				if ($needs_google) {
					$booking_items[] = $this->item(__('Google Maps Browser Key', 'ecab-taxi-booking-manager'), $browser_key ? __('Configured', 'ecab-taxi-booking-manager') : __('Missing', 'ecab-taxi-booking-manager'), $browser_key ? 'good' : 'critical', __('Without it the map fails to authenticate and customers see "Map Authentication Failed". Add it under Settings > Map API Settings.', 'ecab-taxi-booking-manager'));
					// Distinct from the browser key on purpose: the browser key should stay
					// HTTP-referrer restricted, which makes Google refuse the server-side
					// distance lookups that pricing depends on unless a second, IP-
					// restricted key is supplied here.
					$booking_items[] = $this->item(__('Google Maps Server Key', 'ecab-taxi-booking-manager'), $server_key ? __('Configured', 'ecab-taxi-booking-manager') : __('Missing', 'ecab-taxi-booking-manager'), $server_key ? 'good' : 'warning', __('Server-side distance lookups fall back to OpenStreetMap without it. Add a separate, IP-restricted key so the browser key can stay referrer-restricted.', 'ecab-taxi-booking-manager'));
				}
				$sections[] = array(
					'title' => __('Booking Setup', 'ecab-taxi-booking-manager'),
					'icon'  => 'fas fa-route',
					'items' => $booking_items,
				);

				// ---- Filesystem -----------------------------------------------------
				$uploads     = wp_upload_dir();
				$uploads_ok  = empty($uploads['error']) && wp_is_writable($uploads['basedir']);
				$plugin_ok   = wp_is_writable(MPTBM_PLUGIN_DIR . '/assets');

				$sections[] = array(
					'title' => __('Filesystem', 'ecab-taxi-booking-manager'),
					'icon'  => 'fas fa-folder-open',
					'items' => array(
						$this->item(__('Uploads Directory Writable', 'ecab-taxi-booking-manager'), $this->yes_no($uploads_ok), $uploads_ok ? 'good' : 'critical', __('Vehicle images, PDF tickets and checkout uploads cannot be saved. Check the folder permissions on wp-content/uploads.', 'ecab-taxi-booking-manager')),
						$this->item(__('Plugin Assets Directory Writable', 'ecab-taxi-booking-manager'), $this->yes_no($plugin_ok), $plugin_ok ? 'good' : 'warning', __('The plugin cannot create its generated asset folders. This is usually harmless but can break updates.', 'ecab-taxi-booking-manager')),
						$this->item(__('Uploads Path', 'ecab-taxi-booking-manager'), $uploads['basedir'] ?? ''),
					),
				);

				// Add-ons can append their own cards here rather than squeezing rows into
				// the legacy table. Normalised on the way back out so a malformed return
				// from a third-party filter cannot fatal the whole diagnostics screen -
				// the one page an admin reaches for when the site is already misbehaving.
				$sections = apply_filters('mptbm_status_sections', $sections);
				$clean    = array();
				foreach ((array) $sections as $section) {
					if (!is_array($section) || empty($section['title']) || empty($section['items']) || !is_array($section['items'])) {
						continue;
					}
					$items = array();
					foreach ($section['items'] as $item) {
						if (!is_array($item) || !isset($item['label'])) {
							continue;
						}
						$items[] = array(
							'label'  => $item['label'],
							'value'  => $item['value'] ?? '',
							'status' => isset($item['status']) && in_array($item['status'], array('good', 'warning', 'critical', 'info'), true) ? $item['status'] : 'info',
							'hint'   => $item['hint'] ?? '',
						);
					}
					if ($items) {
						$clean[] = array(
							'title' => $section['title'],
							'icon'  => $section['icon'] ?? 'fas fa-info-circle',
							'items' => $items,
						);
					}
				}
				return $clean;
			}

			/** Plain-text version of the same data, for the copy-to-clipboard button. */
			private function build_report(array $sections) {
				$mark  = array('good' => 'OK', 'warning' => 'WARN', 'critical' => 'FAIL', 'info' => '--');
				$lines = array('### ' . get_bloginfo('name') . ' — ' . __('E-cab System Report', 'ecab-taxi-booking-manager'), home_url(), '');
				foreach ($sections as $section) {
					$lines[] = '## ' . wp_strip_all_tags($section['title']);
					foreach ($section['items'] as $item) {
						$lines[] = sprintf('[%s] %s: %s', $mark[$item['status']] ?? '--', wp_strip_all_tags($item['label']), wp_strip_all_tags((string) $item['value']));
					}
					$lines[] = '';
				}
				return implode("\n", $lines);
			}

			public function status_page() {
				$label    = MPTBM_Function::get_name();
				$sections = $this->get_sections();

				// Tally excludes 'info' rows - they carry no verdict, and counting them
				// as passes would inflate the header into meaninglessness.
				$counts = array('good' => 0, 'warning' => 0, 'critical' => 0);
				foreach ($sections as $section) {
					foreach ($section['items'] as $item) {
						if (isset($counts[$item['status']])) {
							$counts[$item['status']]++;
						}
					}
				}
				$overall = $counts['critical'] > 0 ? 'critical' : ($counts['warning'] > 0 ? 'warning' : 'good');

				$icons = array(
					'good'     => 'fas fa-check-circle',
					'warning'  => 'fas fa-exclamation-triangle',
					'critical' => 'fas fa-times-circle',
					'info'     => 'fas fa-info-circle',
				);

				wp_enqueue_style('mptbm-status-style', MPTBM_PLUGIN_URL . '/assets/admin/css/status.css', array(), MPTBM_PLUGIN_VERSION);
				MPTBM_Admin_Shell::render_shell_open();
				?>
				<div class="mpStyle mptbm-status-page">
					<?php do_action('mp_status_notice_sec'); ?>
					<div class="mptbm-status-header">
						<div class="mptbm-status-heading">
							<span class="mptbm-status-header-icon" aria-hidden="true"><i class="fas fa-heartbeat"></i></span>
							<div>
								<p class="mptbm-status-eyebrow"><?php esc_html_e('System diagnostics', 'ecab-taxi-booking-manager'); ?></p>
								<h1><?php echo esc_html($label) . ' ' . esc_html__('Environment Status', 'ecab-taxi-booking-manager'); ?></h1>
								<p>
									<?php
										if ($overall === 'critical') {
											esc_html_e('Some checks need your attention before bookings can work reliably.', 'ecab-taxi-booking-manager');
										} elseif ($overall === 'warning') {
											esc_html_e('Everything essential is working. A few settings could be improved.', 'ecab-taxi-booking-manager');
										} else {
											esc_html_e('All checks passed. Your environment is ready to take bookings.', 'ecab-taxi-booking-manager');
										}
									?>
								</p>
							</div>
						</div>
						<div class="mptbm-status-summary">
							<div class="mptbm-status-pill is-good">
								<strong><?php echo esc_html($counts['good']); ?></strong>
								<span><?php esc_html_e('Passed', 'ecab-taxi-booking-manager'); ?></span>
							</div>
							<div class="mptbm-status-pill is-warning">
								<strong><?php echo esc_html($counts['warning']); ?></strong>
								<span><?php esc_html_e('Warnings', 'ecab-taxi-booking-manager'); ?></span>
							</div>
							<div class="mptbm-status-pill is-critical">
								<strong><?php echo esc_html($counts['critical']); ?></strong>
								<span><?php esc_html_e('Need Fixing', 'ecab-taxi-booking-manager'); ?></span>
							</div>
							<button type="button" class="mptbm-status-copy" data-status-copy>
								<i class="fas fa-clipboard" aria-hidden="true"></i>
								<span data-status-copy-label><?php esc_html_e('Copy report', 'ecab-taxi-booking-manager'); ?></span>
							</button>
						</div>
					</div>

					<textarea class="mptbm-status-report" data-status-report readonly aria-hidden="true" tabindex="-1"><?php echo esc_textarea($this->build_report($sections)); ?></textarea>

					<div class="mptbm-status-grid">
						<?php foreach ($sections as $section) : ?>
							<div class="mptbm-status-card">
								<div class="mptbm-status-card-header">
									<i class="<?php echo esc_attr($section['icon']); ?>" aria-hidden="true"></i>
									<h2><?php echo esc_html($section['title']); ?></h2>
								</div>
								<ul class="mptbm-status-list">
									<?php foreach ($section['items'] as $item) : ?>
										<li class="mptbm-status-row is-<?php echo esc_attr($item['status']); ?>">
											<div class="mptbm-status-row-main">
												<span class="mptbm-status-row-label"><?php echo esc_html($item['label']); ?></span>
												<span class="mptbm-status-badge is-<?php echo esc_attr($item['status']); ?>">
													<i class="<?php echo esc_attr($icons[$item['status']] ?? $icons['info']); ?>" aria-hidden="true"></i>
													<?php echo esc_html($item['value']); ?>
												</span>
											</div>
											<?php if ($item['hint'] && $item['status'] !== 'good' && $item['status'] !== 'info') : ?>
												<p class="mptbm-status-hint"><?php echo esc_html($item['hint']); ?></p>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endforeach; ?>

						<?php
							// Add-ons still inject plain <tr><th>label</th><th>value</th></tr>
							// rows through mp_status_table_item_sec (the Pro plugin does), so
							// that contract is kept exactly as it was - the rows just land in
							// their own card now. Buffer first so an empty hook does not print
							// a heading with nothing under it.
							ob_start();
							do_action('mp_status_table_item_sec');
							$addon_rows = trim((string) ob_get_clean());
						?>
						<?php if ($addon_rows !== '') : ?>
							<div class="mptbm-status-card">
								<div class="mptbm-status-card-header">
									<i class="fas fa-cubes" aria-hidden="true"></i>
									<h2><?php esc_html_e('Add-ons', 'ecab-taxi-booking-manager'); ?></h2>
								</div>
								<table class="mptbm-status-table">
									<tbody>
										<?php echo $addon_rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup built and escaped by the add-on that hooked in. ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>
				</div>
				<script>
					(function () {
						var button = document.querySelector('[data-status-copy]');
						var report = document.querySelector('[data-status-report]');
						if (!button || !report) {
							return;
						}
						button.addEventListener('click', function () {
							var label = button.querySelector('[data-status-copy-label]');
							var done = function () {
								if (!label) { return; }
								var original = label.textContent;
								label.textContent = <?php echo wp_json_encode(esc_html__('Copied', 'ecab-taxi-booking-manager')); ?>;
								window.setTimeout(function () { label.textContent = original; }, 2000);
							};
							// navigator.clipboard is unavailable over plain HTTP, which is
							// exactly the situation on a local or misconfigured install -
							// the very sites most likely to need this report.
							if (navigator.clipboard && window.isSecureContext) {
								navigator.clipboard.writeText(report.value).then(done);
								return;
							}
							report.removeAttribute('aria-hidden');
							report.select();
							try { document.execCommand('copy'); done(); } catch (e) { /* leave it selected to copy by hand */ }
							report.setAttribute('aria-hidden', 'true');
						});
					})();
				</script>
				<?php
				MPTBM_Admin_Shell::render_shell_close();
			}
		}
		new MPTBM_Status();
	}
