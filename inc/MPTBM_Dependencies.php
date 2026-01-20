<?php
/*
 * @Author 		engr.sumonazma@gmail.com
 * Copyright: 	mage-people.com
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.
if (!class_exists('MPTBM_Dependencies')) {
    class MPTBM_Dependencies
    {
	public function __construct()
	{
		add_action('init', array($this, 'language_load'));
		$this->load_file();
		$this->init_rest_api();
		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue'), 80);
		add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue'), 80);
		add_action('admin_head', array($this, 'js_constant'), 5);
		add_action('wp_head', array($this, 'js_constant'), 5);
		
		// Add AJAX handler for OpenStreetMap search
		add_action('wp_ajax_mptbm_osm_search', array($this, 'osm_search_proxy'));
		add_action('wp_ajax_nopriv_mptbm_osm_search', array($this, 'osm_search_proxy'));
		
		// Whitelist Google Maps script from CookieAdmin
		add_filter('script_loader_tag', array('MPTBM_Function', 'whitelist_google_maps_script'), 20, 3);
	}
        public function language_load(): void
        {
            $plugin_dir = basename(dirname(__DIR__)) . "/languages/";
            load_plugin_textdomain('ecab-taxi-booking-manager', false, $plugin_dir);
        }
		private function load_file(): void
		{
			require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Function.php';
			require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Query.php';
			require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Layout.php';
		require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Rest_Api.php';
			require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Admin.php';
			require_once MPTBM_PLUGIN_DIR . '/Frontend/MPTBM_Frontend.php';
		}
		
		private function init_rest_api(): void
		{
			// Initialize REST API only if enabled
			$api_enabled = MP_Global_Function::get_settings('mptbm_rest_api_settings', 'enable_rest_api', 'no');
			if ($api_enabled === 'yes') {
				new MPTBM_REST_API();
			}
		}
        public function global_enqueue()
        {
            $api_key = MP_Global_Function::get_settings('mptbm_map_api_settings', 'gmap_api_key');
            $map_type = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'openstreetmap');
            
            // Check map type FIRST, then decide what to load
            if ($map_type === 'openstreetmap') {
                // OpenStreetMap is selected - load only the map JS without Google Maps API
                wp_enqueue_script('mptbm_admin_map', MPTBM_PLUGIN_URL . '/assets/admin/mptbm_map.js', array('jquery'), time(), true);
            } elseif ($map_type === 'enable' && $api_key) {
                // Google Maps is selected and API key exists
                wp_enqueue_script('mptbm_map_api', 'https://maps.googleapis.com/maps/api/js?libraries=places,drawing&language=en&v=weekly&key=' . $api_key, array(), null, true);
                wp_enqueue_script('mptbm_geoLib', MPTBM_PLUGIN_URL . '/assets/admin/geolib.js', array(), null, true);
                wp_enqueue_script('mptbm_admin_map', MPTBM_PLUGIN_URL . '/assets/admin/mptbm_map.js', array('mptbm_map_api'), time(), true);
            } elseif ($map_type === 'enable' && !$api_key) {
                // Google Maps is selected but no API key
                add_action('admin_notices', [$this, 'map_api_not_active']);
            }
            // If map_type is 'disable', don't load anything
            
            do_action('add_mptbm_common_script');
            wp_enqueue_style('mage-icons', MPTBM_PLUGIN_URL . '/assets/mage-icon/css/mage-icon.css', array(), time());
        }

        public function admin_enqueue()
        {
            $this->global_enqueue();
            // custom
            wp_enqueue_style('mptbm_admin', MPTBM_PLUGIN_URL . '/assets/admin/mptbm_admin.css', array(), time());
            wp_enqueue_style('admin_style', MPTBM_PLUGIN_URL . '/assets/admin/admin_style.css', array(), time());
            wp_enqueue_script('mptbm_admin', MPTBM_PLUGIN_URL . '/assets/admin/mptbm_admin.js', array('jquery'), time(), true);
            wp_enqueue_script('mptbm_tooltip', MPTBM_PLUGIN_URL . '/assets/admin/mptbm_tooltip.js', array('jquery'), time(), true);
            
            // No transport templates
            wp_enqueue_script('mptbm-no-transport-templates', MPTBM_PLUGIN_URL . '/assets/admin/js/no-transport-templates.js', array('jquery'), time(), true);
            
            // Enqueue Leaflet.draw for OpenStreetMap polygon drawing on operation areas page AND settings page
            $screen = get_current_screen();
            $map_type = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'openstreetmap');
            
            // Load Leaflet on operation areas edit page OR on settings page where operation areas are selected
            // Also load on mptbm_rent edit pages (which include the ex_opration_setting tab)
            $is_operation_areas_page = ($screen && $screen->post_type === 'mptbm_operate_areas');
            $is_settings_page = ($screen && isset($_GET['page']) && $_GET['page'] === 'mptbm_settings_page');
            $is_rent_page = ($screen && $screen->post_type === 'mptbm_rent');
            $is_locations_screen = ($screen && ($screen->id === 'edit-locations' || $screen->taxonomy === 'locations'));
            
            if (($is_operation_areas_page || $is_settings_page || $is_rent_page || $is_locations_screen)) {
                if ($map_type === 'openstreetmap') {
                // Leaflet core - must load BEFORE mptbm_admin_map
                wp_enqueue_style('leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css', array(), '1.9.4');
                wp_enqueue_script('leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js', array('jquery'), '1.9.4', false);
                
                // Leaflet.draw for polygon drawing - must load AFTER leaflet but BEFORE mptbm_admin_map
                wp_enqueue_style('leaflet-draw', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css', array('leaflet'), '1.0.4');
                wp_enqueue_script('leaflet-draw', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js', array('leaflet'), '1.0.4', false);
                
                // Re-enqueue mptbm_admin_map with Leaflet dependencies
                wp_deregister_script('mptbm_admin_map');
                wp_enqueue_script('mptbm_admin_map', MPTBM_PLUGIN_URL . '/assets/admin/mptbm_map.js', array('jquery', 'leaflet', 'leaflet-draw'), time(), true);
            }
        }
           
            // Trigger the action hook to add additional scripts if needed
            do_action('add_mptbm_admin_script');
        }

        public function frontend_enqueue()
        {
            $this->global_enqueue();
            wp_enqueue_script('wc-checkout');
            //
            wp_enqueue_style('mptbm_style', MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_style.css', array(), time());
            wp_enqueue_script('mptbm_script', MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_script.js', array('jquery'), time(), true);
            wp_enqueue_script('mptbm_registration', MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_registration.js', array('jquery'), time(), true);
            wp_enqueue_style('mptbm_registration', MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_registration.css', array(), time());
			
			// Localize script for AJAX
			wp_localize_script('mptbm_registration', 'mptbm_ajax', array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'osm_nonce' => wp_create_nonce('mptbm_osm_search')
			));
            
            // Font Awesome for template icons
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4');
            
            // No transport templates styles
            wp_enqueue_style('mptbm-no-transport-templates', MPTBM_PLUGIN_URL . '/assets/frontend/css/no-transport-templates.css', array(), time());
            
            // Enqueue selectWoo (searchable dropdown) if WooCommerce is active
            if (function_exists('WC')) {
                wp_enqueue_script('selectWoo');
                wp_enqueue_style('select2');
            }
            
            do_action('add_mptbm_frontend_script');
        }
        public function js_constant()
        {
?>
            <script type="text/javascript">
                let mp_lat_lng = {
                    lat: <?php echo esc_js(MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_latitude', '23.81234828905659')); ?>,
                    lng: <?php echo esc_js(MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_longitude', '90.41069652669002')); ?>
                };
                const mp_map_options = {
                    componentRestrictions: {
                        country: "<?php echo esc_js(MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_country', 'BD')); ?>"
                    },
                    fields: ["address_components", "geometry"],
                    types: ["address"],
                }
            </script>
            <?php
        }
		
        public function map_api_not_active()
        {
            // Only show warning if Google Maps is selected (not for OpenStreetMap)
            $display_map = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'openstreetmap');
            
            // Only show the warning if Google Maps is specifically enabled
            if ($display_map == 'enable') {
                $gm_api_url = admin_url('edit.php?post_type=mptbm_rent&page=mptbm_settings_page');
                $label = MPTBM_Function::get_name();
            ?>
                <div class="error" style="background:red; color:#fff;">
                    <p>
                        <?php esc_html_e('You Must Add Google Map Api key for E-cab taxi booking manager, Because It is dependent on Google Map. Please enter your Google Maps API key in Plugin Options.', 'ecab-taxi-booking-manager'); ?>
                        <strong style="font-size: 17px;"><?php echo esc_html($label) . '>' . esc_html($label) . ' ' . esc_html__('Settings>Map Api Settings', 'ecab-taxi-booking-manager'); ?></strong>
                        <a class="btn button" href="<?php echo esc_attr($gm_api_url); ?>" target="_blank"><?php esc_html_e('Click Here to get google api key', 'ecab-taxi-booking-manager'); ?></a>
                    </p>
                </div>
<?php
            }
        }
		
		public function osm_search_proxy() {
			
			// Check nonce for security
			check_ajax_referer('mptbm_osm_search', 'nonce');
			
			$query = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
			
			if (empty($query)) {
				wp_send_json_error('No search query provided');
				return;
			}
			
			// Get country restriction settings
			$restrict_to_country = MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_country_restriction', 'no');
			$country_code = MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_country', 'BD');
			
			// Build search parameters
			$search_params = array(
				'q' => $query,
				'limit' => 5,
				'lang' => 'en'
			);
			
			// Add country restriction if enabled
			if ($restrict_to_country === 'yes' && !empty($country_code)) {
				// For Bangladesh, we'll rely on server-side filtering since osm_tag might be too restrictive
				// The osm_tag parameter can be too restrictive and block valid results
				// We'll filter results server-side instead
			}
			
			// Use Photon API (OpenStreetMap-based, more lenient)
			$url = 'https://photon.komoot.io/api/?' . http_build_query($search_params);
			
			$response = wp_remote_get($url, array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/json'
				)
			));
			
			if (is_wp_error($response)) {
				wp_send_json_error($response->get_error_message());
				return;
			}
			
			$status_code = wp_remote_retrieve_response_code($response);
			
			// Check if status is not 200
			if ($status_code !== 200) {
				$body = wp_remote_retrieve_body($response);
				wp_send_json_error('OpenStreetMap returned status ' . $status_code . ': ' . $body);
				return;
			}
			
			$body = wp_remote_retrieve_body($response);
			
			// Check if response looks like HTML (error page)
			if (stripos($body, '<html') !== false || stripos($body, '<!DOCTYPE') !== false) {
				wp_send_json_error('OpenStreetMap returned an error page. Please try again later.');
				return;
			}
			
			$data = json_decode($body, true);
			$json_error = json_last_error();
			
			
			if ($json_error !== JSON_ERROR_NONE) {
				wp_send_json_error('Invalid response from OpenStreetMap: ' . json_last_error_msg());
				return;
			}
			
			if (!is_array($data)) {
				wp_send_json_error('Invalid response format from OpenStreetMap');
				return;
			}
			
			// Convert Photon GeoJSON format to Nominatim-compatible format
			$results = array();
			
			
			if (isset($data['features']) && is_array($data['features'])) {
				foreach ($data['features'] as $feature) {
					$properties = isset($feature['properties']) ? $feature['properties'] : array();
					$coordinates = isset($feature['geometry']['coordinates']) ? $feature['geometry']['coordinates'] : array();
					
					// Additional country filtering if restriction is enabled
					if ($restrict_to_country === 'yes' && !empty($country_code)) {
						$feature_country = isset($properties['countrycode']) ? strtoupper($properties['countrycode']) : '';
						$feature_country_name = isset($properties['country']) ? strtoupper($properties['country']) : '';
						$feature_state = isset($properties['state']) ? strtoupper($properties['state']) : '';
						$feature_city = isset($properties['city']) ? strtoupper($properties['city']) : '';
						
						// Check both country code and country name for better matching
						$country_matches = false;
						
						// Direct country code match
						if (!empty($feature_country) && $feature_country === strtoupper($country_code)) {
							$country_matches = true;
						}
						
						// Country name matching for Bangladesh
						if (!$country_matches && strtoupper($country_code) === 'BD') {
							$bangladesh_names = ['BANGLADESH', 'BD'];
							if (!empty($feature_country_name) && in_array($feature_country_name, $bangladesh_names)) {
								$country_matches = true;
							}
							
							// Also check for Bangladesh cities and states
							$bangladesh_cities = ['DHAKA', 'CHITTAGONG', 'SYLHET', 'RAJSHAHI', 'KHULNA', 'BARISAL', 'RANGPUR', 'COMILLA', 'NARAYANGANJ', 'GAZIPUR'];
							$bangladesh_states = ['DHAKA', 'CHITTAGONG', 'SYLHET', 'RAJSHAHI', 'KHULNA', 'BARISAL', 'RANGPUR', 'DIVISION'];
							
							if (!$country_matches) {
								if (!empty($feature_city) && in_array($feature_city, $bangladesh_cities)) {
									$country_matches = true;
								} elseif (!empty($feature_state) && in_array($feature_state, $bangladesh_states)) {
									$country_matches = true;
								}
							}
						}
						
						// If no country information is available, allow the result for now
						// This helps with cases where the API doesn't provide country info
						if (empty($feature_country) && empty($feature_country_name) && empty($feature_city) && empty($feature_state)) {
							$country_matches = true;
						}
						
						if (!$country_matches) {
							continue; // Skip results from other countries
						}
					}
					
					// Build display name from properties
					$name_parts = array();
					if (!empty($properties['name'])) $name_parts[] = $properties['name'];
					if (!empty($properties['city'])) $name_parts[] = $properties['city'];
					if (!empty($properties['state'])) $name_parts[] = $properties['state'];
					if (!empty($properties['country'])) $name_parts[] = $properties['country'];
					
					$display_name = !empty($name_parts) ? implode(', ', $name_parts) : 'Unknown Location';
					
					// Photon uses [lon, lat] format, we need to convert to lat/lon
					$results[] = array(
						'display_name' => $display_name,
						'lat' => isset($coordinates[1]) ? $coordinates[1] : 0,
						'lon' => isset($coordinates[0]) ? $coordinates[0] : 0,
						'address' => $properties
					);
				}
			}
			
			wp_send_json_success($results);
		}
    }
    new MPTBM_Dependencies();
}
