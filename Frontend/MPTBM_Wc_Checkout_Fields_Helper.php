<?php
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	/**
	 * Class MPTBM_Wc_Checkout_Fields_Helper
	 *
	 * @since 1.0
	 *
	 * */
	if (!class_exists('MPTBM_Wc_Checkout_Fields_Helper')) {
		class MPTBM_Wc_Checkout_Fields_Helper {
			private $error;
			public static $settings_options;
			public static $default_woocommerce_checkout_fields;
			public static $default_woocommerce_checkout_required_fields;
			public static $default_app_required_fields;
			private $allowed_extensions;
			private $allowed_mime_types;
			public function __construct() {
				$this->error = new WP_Error();
				$this->init();
				
				// Check if custom checkout system is disabled
				if (self::disable_custom_checkout_system()) {
					// If disabled, don't add any filters or actions that modify checkout
					return;
				}
				
				// Always inject our fields, even if Pro is active - use high priority to run last
				add_filter('woocommerce_checkout_fields', array($this, 'inject_checkout_fields'), 999);
				
				// Render file fields after WooCommerce fields in each section
				add_action('woocommerce_after_checkout_billing_form', function() { $this->output_file_fields_for_section('billing'); });
				add_action('woocommerce_after_checkout_shipping_form', function() { $this->output_file_fields_for_section('shipping'); });
				add_action('woocommerce_after_checkout_order_form', function() { $this->output_file_fields_for_section('order'); });
				
				// Add CSS for hidden fields
				add_action('wp_head', array($this, 'add_hidden_field_css'));
			}
			public static function woocommerce_default_checkout_fields() {
				// Use a static variable to cache the fields once translations are available
				static $cached_fields = null;
				
				if ($cached_fields === null) {
					$cached_fields = array
					(
						"billing" => array(
							"billing_first_name" => array(
								"label" => __("First name",'ecab-taxi-booking-manager'),
								"required" => "1",
								"class" => array(
									"0" => "form-row-first"
								),
								"autocomplete" => "given-name",
								"priority" => "10",
							),
							"billing_last_name" => array(
								"label" => __("Last name",'ecab-taxi-booking-manager'),
								"required" => "1",
								"class" => array(
									"0" => "form-row-last"
								),
								"autocomplete" => "family-name",
								"priority" => "20",
							),
							"billing_company" => array(
								"label" => __("Company name",'ecab-taxi-booking-manager'),
								"class" => array(
									"0" => "form-row-wide",
								),
								"autocomplete" => "organization",
								"priority" => "30",
								"required" => '',
							),
							"billing_country" => array(
								"type" => "country",
								"label" => __("Country / Region",'ecab-taxi-booking-manager'),
								"required" => "1",
								"class" => array(
									"0" => "form-row-wide",
									"1" => "address-field",
									"2" => "update_totals_on_change",
								),
								"autocomplete" => "country",
								"priority" => "40",
							),
							"billing_address_1" => array(
								"label" => __("Street address",'ecab-taxi-booking-manager'),
								"placeholder" => __("House number and street name",'ecab-taxi-booking-manager'),
								"required" => "1",
								"class" => array(
									"0" => "form-row-wide",
									"1" => "address-field"
								),
								"autocomplete" => "address-line1",
								"priority" => "50"
							),
							"billing_address_2" => array(
								"label" => __("Apartment, suite, unit, etc.",'ecab-taxi-booking-manager'),
								"label_class" => array(
									"0" => "screen-reader-text",
								),
								"placeholder" => __("Apartment, suite, unit, etc. (optional)",'ecab-taxi-booking-manager'),
								"class" => array(
									"0" => "form-row-wide",
									"1" => "address-field"
								),
								"autocomplete" => "address-line2",
								"priority" => "60",
								"required" => "",
							),
							"billing_city" => array(
								"label" => __("Town / City",'ecab-taxi-booking-manager'),
								"required" => "1",
								"class" => array(
									"0" => "form-row-wide",
									"1" => "address-field",
								),
								"autocomplete" => "address-level2",
								"priority" => "70",
							),
							"billing_state" => array(
								"type" => "state",
								"label" => __("State / County",'ecab-taxi-booking-manager'),
								"required" => "",
								"class" => array(
									"0" => "form-row-wide",
									"1" => "address-field"
								),
								"validate" => array(
									"0" => "state"
								),
								"autocomplete" => "address-level1",
								"priority" => "80",
								"country_field" => "billing_country",
								"country" => "AF"
							),
							"billing_postcode" => array(
								"label" => __("Postcode / ZIP",'ecab-taxi-booking-manager'),
								"required" => "1",
								"class" => array(
									"0" => "form-row-wide",
									"1" => "address-field"
								),
								"validate" => array(
									"0" => "postcode",
								),
								"autocomplete" => "postal-code",
								"priority" => "90"
							),
							"billing_phone" => array(
								"label" => __("Phone",'ecab-taxi-booking-manager'),
								"required" => "1",
								"type" => "tel",
								"class" => array(
									"0" => "form-row-wide",
								),
								"validate" => array(
									"0" => "phone",
								),
								"autocomplete" => "tel",
								"priority" => "100"
							),
							'billing_email' => array(
								"label" => __("Email address",'ecab-taxi-booking-manager'),
								"required" => "1",
								"type" => "email",
								"class" => array(
									"0" => "form-row-wide",
								),
								"validate" => array(
									"0" => "email",
								),
								"autocomplete" => "email username",
								"priority" => "110",
							)
						),
						'shipping' => array(
							'shipping_first_name' => array(
								"label" => __("First name",'ecab-taxi-booking-manager'),
								"required" => "1",
								"class" => array(
									"0" => "form-row-first",
								),
								"autocomplete" => "given-name",
								"priority" => "10",
							),
							"shipping_last_name" => array(
								"label" => __("Last name",'ecab-taxi-booking-manager'),
								"required" => "1",
								"class" => array(
									"0" => "form-row-last",
								),
								"autocomplete" => "family-name",
								"priority" => "20",
							),
							"shipping_company" => array(
								"label" => __("Company name",'ecab-taxi-booking-manager'),
								"class" => array(
									"0" => "form-row-wide",
								),
								"autocomplete" => "organization",
								"priority" => "30",
								"required" => "",
							),
							"shipping_country" => array(
								"type" => "country",
								"label" => __("Country / Region",'ecab-taxi-booking-manager'),
								"required" => "1",
								"class" => array(
									"0" => "form-row-wide",
									"1" => "address-field",
									"2" => "update_totals_on_change",
								),
								"autocomplete" => "country",
								"priority" => "40",
							),
							"shipping_address_1" => array(
								"label" => __("Street address",'ecab-taxi-booking-manager'),
								"placeholder" => __("House number and street name",'ecab-taxi-booking-manager'),
								"required" => "1",
								"class" => array(
									"0" => "form-row-wide",
									"1" => "address-field"
								),
								"autocomplete" => "address-line1",
								"priority" => "50"
							),
							"shipping_address_2" => array(
								"label" => __("Apartment, suite, unit, etc.",'ecab-taxi-booking-manager'),
								"label_class" => array(
									"0" => "screen-reader-text",
								),
								"placeholder" => __("Apartment, suite, unit, etc. (optional)",'ecab-taxi-booking-manager'),
								"class" => array(
									"0" => "form-row-wide",
									"1" => "address-field"
								),
								"autocomplete" => "address-line2",
								"priority" => "60",
								"required" => "",
							),
							"shipping_city" => array(
								"label" => __("Town / City",'ecab-taxi-booking-manager'),
								"required" => "1",
								"class" => array(
									"0" => "form-row-wide",
									"1" => "address-field",
								),
								"autocomplete" => "address-level2",
								"priority" => "70",
							),
							"shipping_state" => array(
								"type" => "state",
								"label" => __("State / County",'ecab-taxi-booking-manager'),
								"required" => "",
								"class" => array(
									"0" => "form-row-wide",
									"1" => "address-field"
								),
								"validate" => array(
									"0" => "state"
								),
								"autocomplete" => "address-level1",
								"priority" => "80",
								"country_field" => "shipping_country",
								"country" => "AF",
							),
							"shipping_postcode" => array(
								"label" => __("Postcode / ZIP",'ecab-taxi-booking-manager'),
								"required" => "1",
								"class" => array(
									"0" => "form-row-wide",
									"1" => "address-field"
								),
								"validate" => array(
									"0" => "postcode",
								),
								"autocomplete" => "postal-code",
								"priority" => "90",
							),
						),
						'order' => array(
							'order_comments' => array(
								"type" => "textarea",
								"label" => __("Order notes",'ecab-taxi-booking-manager'),
								"placeholder" => __("Notes about your order, e.g. special notes for delivery.",'ecab-taxi-booking-manager'),
								"class" => array(
									"0" => "notes",
								),
								"priority" => "",
								"required" => "",
							),
						),
					);
				}
				
				return $cached_fields;
			}
			public function init() {
				self::$settings_options = get_option('mptbm_custom_checkout_fields');
				self::$default_woocommerce_checkout_fields = self::woocommerce_default_checkout_fields();
				$this->allowed_extensions = array('jpg', 'jpeg', 'png', 'pdf');
				$this->allowed_mime_types = array(
					"jpg|jpeg|jpe" => "image/jpeg",
					"png" => "image/png",
					"pdf" => "application/pdf"
				);

				// Only add checkout-related actions if custom checkout system is not disabled
				if (!self::disable_custom_checkout_system()) {
					// Remove Pro check, always inject fields
					// (No need to add woocommerce_checkout_fields filter here)
					add_action('woocommerce_checkout_update_order_meta', array($this, 'save_custom_checkout_fields_to_order'), 99, 2);
					add_action('woocommerce_before_order_details', array($this, 'order_details'), 99, 1);
					add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'order_details'), 99, 1);
					add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'order_details'), 99, 1);
				}
			}
			public static function get_checkout_fields_for_list() {
				$fields = array();
				$checkout_fields = self::$default_woocommerce_checkout_fields;
				$fields['billing'] = $checkout_fields['billing'];
				$fields['shipping'] = $checkout_fields['shipping'];
				$fields['order'] = $checkout_fields['order'];
				if (isset(self::$settings_options) && is_array(self::$settings_options)) {
					foreach (self::$settings_options as $key => $key_fields) {
						if (is_array($key_fields)) {
							foreach ($key_fields as $name => $field_array) {
								if (self::check_deleted_field($key, $name)) {
									unset($fields[$key][$name]);
								} else {
									$fields[$key][$name] = $field_array;
								}
							}
						}
					}
				}
				if (isset($checkout_fields) && is_array($checkout_fields)) {
					foreach ($checkout_fields as $key => $key_fields) {
						if (is_array($key_fields)) {
							foreach ($key_fields as $name => $field_array) {
								if (self::check_disabled_field($key, $name)) {
									$fields[$key][$name]['disabled'] = '1';
								}
							}
						}
					}
				}
				return $fields;
			}
			public function get_checkout_fields_for_checkout() {
				$fields = array();
				$checkout_fields = WC()->checkout->get_checkout_fields();
				
				// Initialize sections
				$fields['billing'] = isset($checkout_fields['billing']) ? $checkout_fields['billing'] : array();
				$fields['shipping'] = isset($checkout_fields['shipping']) ? $checkout_fields['shipping'] : array();
				$fields['order'] = isset($checkout_fields['order']) ? $checkout_fields['order'] : array();
				
				// Remove deleted or disabled fields
				foreach ($fields as $key => $section_fields) {
					if (is_array($section_fields)) {
						foreach ($section_fields as $field_key => $field) {
							if (self::check_deleted_field($key, $field_key) || self::check_disabled_field($key, $field_key)) {
								unset($fields[$key][$field_key]);
							}
						}
					}
				}
				
				// Add custom fields from settings
				if (isset(self::$settings_options) && is_array(self::$settings_options)) {
					foreach (self::$settings_options as $key => $section_fields) {
						if (is_array($section_fields)) {
							foreach ($section_fields as $field_key => $field) {
								if (!self::check_deleted_field($key, $field_key) && !self::check_disabled_field($key, $field_key)) {
									$fields[$key][$field_key] = $field;
								}
							}
						}
					}
				}
				
				// Handle section visibility
				if (self::hide_checkout_order_review_section()) {
					remove_action('woocommerce_checkout_order_review', 'woocommerce_order_review', 10);
				}
				
				if (self::hide_checkout_order_additional_information_section() || (isset($fields['order']) && is_array($fields['order']) && count($fields['order']) == 0)) {
					add_filter('woocommerce_enable_order_notes_field', '__return_false');
				}
				
				return $fields;
			}
			public static function hide_checkout_order_additional_information_section() {
				if (!self::$settings_options || (is_array(self::$settings_options) && ((!array_key_exists('hide_checkout_order_additional_information', self::$settings_options)) || (array_key_exists('hide_checkout_order_additional_information', self::$settings_options) && self::$settings_options['hide_checkout_order_additional_information'] == 'on')))) {
					return true;
				}
			}
			public static function hide_checkout_order_review_section() {
				if ((is_array(self::$settings_options) && ((array_key_exists('hide_checkout_order_review', self::$settings_options) && self::$settings_options['hide_checkout_order_review'] == 'on')))) {
					return true;
				}
			}
			public static function disable_custom_checkout_system() {
				if ((is_array(self::$settings_options) && ((array_key_exists('disable_custom_checkout_system', self::$settings_options) && self::$settings_options['disable_custom_checkout_system'] == 'on')))) {
					return true;
				}
				return false;
			}
			public static function check_deleted_field($key, $name) {
				if ((isset(self::$settings_options[$key][$name]) && (isset(self::$settings_options[$key][$name]['deleted']) && self::$settings_options[$key][$name]['deleted'] == 'deleted'))) {
					return true;
				} else {
					return false;
				}
			}
			public static function check_disabled_field($key, $name) {
				$default_disabled_field = array('billing' => array('billing_company' => '', 'billing_country' => '', 'billing_address_1' => '', 'billing_address_2' => '', 'billing_city' => '', 'billing_state' => '', 'billing_postcode' => ''));
				if ((!isset(self::$settings_options[$key][$name]) && isset($default_disabled_field[$key][$name])) || (isset(self::$settings_options[$key][$name]) && (isset(self::$settings_options[$key][$name]['disabled']) && self::$settings_options[$key][$name]['disabled'] == '1'))) {
					return true;
				} else {
					return false;
				}
			}
			public static function default_woocommerce_checkout_required_fields() {
				return array(
					'billing' => array('billing_first_name' => array('required' => true), 'billing_last_name' => array('required' => '1'), 'billing_country' => array('required' => '1'), 'billing_address_1' => array('required' => '1'), 'billing_city' => array('required' => '1'), 'billing_state' => array('required' => '1'), 'billing_postcode' => array('required' => '1'), 'billing_phone' => array('required' => '1'), 'billing_email' => array('required' => '1')),
					'shipping' => array('shipping_first_name' => array('required' => '1'), 'shipping_last_name' => array('required' => '1'), 'shipping_country' => array('required' => '1'), 'shipping_address_1' => array('required' => '1'), 'shipping_city' => array('required' => '1'), 'shipping_state' => array('required' => '1')),
				);
			}
			public static function default_app_required_fields() {
				return array(
					'billing' => array('billing_first_name' => array('required' => true), 'billing_last_name' => array('required' => '1'), 'billing_phone' => array('required' => '1'), 'billing_email' => array('required' => '1')),
					'shipping' => array(),
				);
			}
			public function file_upload_field() {
				$checkout_fields = $this->get_checkout_fields_for_checkout();
				$current_action = current_filter();
				
				
				switch ($current_action) {
					case 'woocommerce_after_checkout_billing_form':
						$section = 'billing';
						break;
					case 'woocommerce_after_checkout_shipping_form':
						$section = 'shipping';
						break;
					case 'woocommerce_after_checkout_order_form':
						$section = 'order';
						break;
					default:
						return;
				}
				
				if (isset($checkout_fields[$section]) && is_array($checkout_fields[$section])) {
					$fields = $checkout_fields[$section];
				
					
					if (in_array('file', array_column($fields, 'type'))) {
						$file_fields = array_filter($fields, array($this, 'get_file_fields'));
						
						if (!empty($file_fields)) {
							$this->file_upload_field_element($file_fields);
						}
					}
				}
			}
			public function get_other_fields($field) {
				return (is_array($field) && isset($field['custom_field']) && $field['custom_field'] == '1' && isset($field['type']) && $field['type'] != 'file');
			}
			public function get_file_fields($field) {
				$is_file_field = (is_array($field) && 
					isset($field['custom_field']) && 
					$field['custom_field'] == '1' && 
					isset($field['type']) && 
					$field['type'] == 'file'
				);

				
				return $is_file_field;
			}
			public static function file_upload_field_element($fields) {
				foreach ($fields as $key => $field) {
					$label = isset($field['label']) ? $field['label'] : $key;
					$required = !empty($field['required']) ? 'required' : '';
					echo '<p class="form-row form-row-wide mptbm-file-upload-field">';
					echo '<label for="' . esc_attr($key) . '">' . esc_html($label);
					if ($required) echo ' <span class="required">*</span>';
					echo '</label>';
					echo '<input type="file" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" ' . $required . ' data-mptbm-file-upload />';
					// Hidden field for AJAX URL
					echo '<input type="hidden" name="' . esc_attr($key) . '_url" />';
					echo '<div class="mptbm-file-preview"></div>';
					echo '</p>';
				}
			}
			function save_custom_checkout_fields_to_order($order_id, $data) {
					
				$checkout_key_fields = $this->get_checkout_fields_for_checkout();				
				foreach ($checkout_key_fields as $key => $checkout_fields) {
					if (is_array($checkout_fields) && count($checkout_fields)) {
						$checkout_other_fields = array_filter($checkout_fields, array($this, 'get_other_fields'));
						foreach ($checkout_other_fields as $key => $field) {
							if (isset($_POST[$key])) {
								update_post_meta($order_id, sanitize_text_field('_' . $key), sanitize_text_field($_POST[$key]));
							}
						}
						
						if (in_array('file', array_column($checkout_fields, 'type'))) {
							
							$checkout_file_fields = array_filter($checkout_fields, array($this, 'get_file_fields'));
							
							
							foreach ($checkout_file_fields as $key => $field) {
								
								$image_url = isset($_POST[$key . '_url']) ? esc_url_raw($_POST[$key . '_url']) : '';
								
								
								if ($image_url) {
									update_post_meta($order_id, '_' . $key, $image_url);
									// Also save to short key if billing_
									if (strpos($key, 'billing_') === 0) {
										$short_key = substr($key, strlen('billing_'));
										update_post_meta($order_id, '_' . $short_key, $image_url);
										
									}
	
								} else {
									
									if (isset($field['required']) && $field['required'] == '1') {
									
										wc_add_notice(sprintf(__('Please upload a file for %s', 'ecab-taxi-booking-manager'), $field['label']), 'error');
									}
								}
							}
						}
					}
				}
			}
			function get_post($order_id) {
				$args = array(
					'post_type' => 'mptbm_booking',
					'posts_per_page' => -1,
					'meta_query' => array(
						array(
							'key' => 'link_mptbm_id',
							'value' => $order_id,
							'compare' => '='
						),
					)
				);
				$query = new WP_Query($args);
				$post_ids = array();
				if ($query->have_posts()) {
					while ($query->have_posts()) {
						$query->the_post();
						$post_ids[] = get_the_ID();
					}
					wp_reset_postdata();
				}
				return $post_ids;
			}
			function get_uploaded_image_link($file_field_name) {
			
				
				$file_field_name = sanitize_key($file_field_name);
				$upload_dir = wp_upload_dir();
				$image_url = '';
								
				if (isset($_FILES[$file_field_name]) && !empty($_FILES[$file_field_name]['name'])) {
					$file = $_FILES[$file_field_name];
				
					
					// Basic error checking
					if ($file['error'] !== UPLOAD_ERR_OK) {
						$error_message = $this->get_file_error_message($file['error']);
						
						wc_add_notice($error_message, 'error');
						return false;
					}
					
					$file_name = sanitize_file_name($file['name']);
					$file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
					$file_type = wp_check_filetype($file_name, $this->allowed_mime_types);
					
					
					if (!in_array($file_extension, $this->allowed_extensions)) {
						$error_message = sprintf(__('Invalid file type. Allowed types are: %s', 'ecab-taxi-booking-manager'), implode(', ', $this->allowed_extensions));
					
						wc_add_notice($error_message, 'error');
						return false;
					}
					
					if (!$file_type['type']) {
						
						wc_add_notice(__('Invalid file type.', 'ecab-taxi-booking-manager'), 'error');
						return false;
					}
					
					// Generate unique filename
					$file_name = wp_unique_filename($upload_dir['path'], $file_name);
					$path = $upload_dir['path'] . '/' . $file_name;
					
				
					
					if (!move_uploaded_file($file['tmp_name'], $path)) {
						
						wc_add_notice(__('Error saving file. Please try again.', 'ecab-taxi-booking-manager'), 'error');
						return false;
					}
					
					$image_url = $upload_dir['url'] . '/' . $file_name;
				
				} else {
					
				}
				
				return $image_url ? $image_url : false;
			}
			private function get_file_error_message($error_code) {
				switch ($error_code) {
					case UPLOAD_ERR_INI_SIZE:
						return __('The uploaded file exceeds the upload_max_filesize directive in php.ini', 'ecab-taxi-booking-manager');
					case UPLOAD_ERR_FORM_SIZE:
						return __('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form', 'ecab-taxi-booking-manager');
					case UPLOAD_ERR_PARTIAL:
						return __('The uploaded file was only partially uploaded', 'ecab-taxi-booking-manager');
					case UPLOAD_ERR_NO_FILE:
						return __('No file was uploaded', 'ecab-taxi-booking-manager');
					case UPLOAD_ERR_NO_TMP_DIR:
						return __('Missing a temporary folder', 'ecab-taxi-booking-manager');
					case UPLOAD_ERR_CANT_WRITE:
						return __('Failed to write file to disk', 'ecab-taxi-booking-manager');
					case UPLOAD_ERR_EXTENSION:
						return __('File upload stopped by extension', 'ecab-taxi-booking-manager');
					default:
						return __('Unknown upload error', 'ecab-taxi-booking-manager');
				}
			}
			function order_details($order_id) {
				$order = wc_get_order($order_id);
				$checkout_fields = $this->get_checkout_fields_for_checkout();
				$billing_fields = $checkout_fields['billing'];
				$shipping_fields = $checkout_fields['shipping'];
				$order_fields = $checkout_fields['order'];
				$current_action = current_filter();
				if ($current_action == 'woocommerce_admin_order_data_after_billing_address') {
					$checkout_billing_other_fields = array_filter($billing_fields, array($this, 'get_other_fields'));
					$this->prepare_other_field($checkout_billing_other_fields, 'billing', $order);
					if (in_array('file', array_column($billing_fields, 'type'))) {
						$checkout_billing_file_fields = array_filter($billing_fields, array($this, 'get_file_fields'));
						$this->prepare_file_field($checkout_billing_file_fields, 'billing', $order);
					}
				} else if ($current_action == 'woocommerce_admin_order_data_after_shipping_address') {
					$checkout_shipping_other_fields = array_filter($shipping_fields, array($this, 'get_other_fields'));
					$this->prepare_other_field($checkout_shipping_other_fields, 'shipping', $order);
					if (in_array('file', array_column($shipping_fields, 'type'))) {
						$checkout_shipping_file_fields = array_filter($shipping_fields, array($this, 'get_file_fields'));
						$this->prepare_file_field($checkout_shipping_file_fields, 'shipping', $order);
					}
				} else if ($current_action == 'woocommerce_admin_order_data_after_order_address') {
					$checkout_order_other_fields = array_filter($order_fields, array($this, 'get_other_fields'));
					$this->prepare_other_field($checkout_order_other_fields, 'order', $order);
					if (in_array('file', array_column($order_fields, 'type'))) {
						$checkout_order_file_fields = array_filter($order_fields, array($this, 'get_file_fields'));
						$this->prepare_file_field($checkout_order_file_fields, 'order', $order);
					}
				}
			}
			function prepare_other_field($custom_fields, $key, $order) {
				?>
                <div class="order_data_column_container">
					<?php if (is_array($custom_fields) && count($custom_fields)) : ?>
                        <div class="order_data_column">
                            <h3><?php echo esc_html('Custom ' . $key); ?></h3>
							<?php foreach ($custom_fields as $name => $field_array): ?>
								<?php
								unset($key_value);
								$key_value = get_post_meta($order->get_id(), '_' . $name, true);
								$key_value = esc_html($key_value);
								$field_label = isset($field_array['label']) ? esc_html($field_array['label'] . ' :') : '';
								$field_name = esc_attr($name);
								?>
                                <p class="form-field form-field-wide">
                                    <strong><?php echo $field_label; ?></strong>
                                    <label for="<?php echo $field_name; ?>"><?php echo $key_value; ?></label>
                                </p>
							<?php endforeach; ?>
                        </div>
					<?php endif; ?>
                </div>
				<?php
			}
			function prepare_file_field($custom_fields, $key, $order) {
				?>
                <div class="order_data_column_container">
					<?php if (is_array($custom_fields) && count($custom_fields)) : ?>
                        <div class="order_data_column">
                            <h3><?php echo esc_html('Custom ' . $key . ' File'); ?></h3>
							<?php foreach ($custom_fields as $name => $field_array) : ?>
								<?php
								unset($key_value);
								$key_value = get_post_meta($order->get_id(), '_' . $name, true);
								$key_value = esc_url($key_value);
								$field_label = isset($field_array['label']) ? esc_html($field_array['label'] . ' :') : '';
								$field_name = esc_attr($name);
								$file_extension = strtolower(pathinfo($key_value, PATHINFO_EXTENSION));
								$file_type = wp_check_filetype($key_value, $this->allowed_mime_types);
								?>
                                <p class="form-field form-field-wide">
                                    <strong><?php echo $field_label; ?></strong>
									<?php if (in_array($file_extension, $this->allowed_extensions) && $file_type['type']) : ?>
										<?php if ($file_extension !== 'pdf') : ?>
                                            <img src="<?php echo $key_value; ?>" alt="<?php echo $field_name; ?> image" width="100" height="100">
                                            <a class="button button-tiny button-primary" href="<?php echo $key_value; ?>" download>Download</a>
										<?php else : ?>
                                            <a class="button button-tiny button-primary" href="<?php echo $key_value; ?>" download>Download PDF</a>
										<?php endif; ?>
									<?php endif; ?>
                                </p>
							<?php endforeach; ?>
                        </div>
					<?php endif; ?>
                </div>
				<?php
			}
			// Prevent WooCommerce from rendering file fields
			public function prevent_default_file_field_render($field, $key, $args, $value) {
				if (isset($args['type']) && $args['type'] === 'file') {
					return ''; // Return empty string to prevent default rendering
				}
				return $field;
			}
			public function remove_file_fields_from_checkout($fields) {
				foreach ($fields as $section => $section_fields) {
					if (is_array($section_fields)) {
						foreach ($section_fields as $key => $field) {
							if (isset($field['type']) && $field['type'] === 'file') {
								unset($fields[$section][$key]);
							}
						}
					}
				}
				return $fields;
			}
			/**
			 * Inject merged default and custom fields into WooCommerce checkout
			 */
			public function inject_checkout_fields($fields) {
				// Get default fields
				$default = self::woocommerce_default_checkout_fields();
				// Get custom fields from admin/pro
				$custom = get_option('mptbm_custom_checkout_fields', array());
				
				// Core WooCommerce fields that should not be completely removed
				$core_fields = array(
					'billing' => array('billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone'),
					'shipping' => array('shipping_first_name', 'shipping_last_name'),
					'order' => array()
				);
				
				// Merge for each section
				foreach (['billing','shipping','order'] as $section) {
					$section_fields = isset($default[$section]) ? $default[$section] : array();
					if (isset($custom[$section]) && is_array($custom[$section])) {
						foreach ($custom[$section] as $key => $field) {
							if ((empty($field['deleted']) || $field['deleted'] !== 'deleted')) {
								if ((empty($field['disabled']) || $field['disabled'] !== '1')) {
									// Field is enabled, merge custom settings with default field
									if (isset($section_fields[$key])) {
										// Merge custom settings with existing default field, but preserve important defaults
										$merged_field = array_merge($section_fields[$key], $field);
										
										// Preserve important default values if custom field has empty values
										foreach (['type', 'autocomplete'] as $important_key) {
											if (isset($section_fields[$key][$important_key]) && 
												(!isset($field[$important_key]) || $field[$important_key] === '')) {
												$merged_field[$important_key] = $section_fields[$key][$important_key];
											}
										}
										
										// Handle required field properly - use custom setting when available
										if (isset($field['required'])) {
											// Custom setting exists, use it (could be '1', '', or '0')
											$merged_field['required'] = ($field['required'] === '1') ? true : false;
										} else {
											// No custom setting, keep default
											$merged_field['required'] = isset($section_fields[$key]['required']) ? $section_fields[$key]['required'] : false;
										}
										
										// Ensure validate array is properly handled
										if (isset($field['validate']) && is_array($field['validate']) && 
											isset($field['validate'][0]) && $field['validate'][0] === '') {
											if (isset($section_fields[$key]['validate'])) {
												$merged_field['validate'] = $section_fields[$key]['validate'];
											}
										}
										
										$section_fields[$key] = $merged_field;
									} else {
										// It's a completely new custom field
										// Handle required field for new custom fields
										if (isset($field['required'])) {
											$field['required'] = ($field['required'] === '1') ? true : false;
										}
										$section_fields[$key] = $field;
									}
								} else {
									// Field is disabled
									if (in_array($key, $core_fields[$section])) {
										// For core fields, keep them but mark as not required and hidden via CSS
										$section_fields[$key] = array_merge($section_fields[$key] ?? array(), $field);
										$section_fields[$key]['required'] = false;
										
										// Handle class as array
										$existing_classes = isset($section_fields[$key]['class']) ? $section_fields[$key]['class'] : array();
										if (!is_array($existing_classes)) {
											$existing_classes = array($existing_classes);
										}
										$existing_classes[] = 'mptbm-hidden-field';
										$section_fields[$key]['class'] = $existing_classes;
									} else {
										// For custom fields, remove them completely
										unset($section_fields[$key]);
									}
								}
							} else {
								// Field is deleted, remove it
								unset($section_fields[$key]);
							}
						}
					}
					
					// Ensure core fields are always present with default values if missing
					foreach ($core_fields[$section] as $core_field) {
						if (!isset($section_fields[$core_field]) && isset($default[$section][$core_field])) {
							$section_fields[$core_field] = $default[$section][$core_field];
						}
					}
					
					// Remove file fields from the array so WooCommerce doesn't render them
					foreach ($section_fields as $k => $f) {
						if (isset($f['type']) && $f['type'] === 'file') {
							unset($section_fields[$k]);
						}
					}
					// Sort by priority if set
					uasort($section_fields, function($a, $b) {
						return ($a['priority'] ?? 0) <=> ($b['priority'] ?? 0);
					});
					$fields[$section] = $section_fields;
				}
				
				// Force ensure these critical fields are properly structured and present
				$critical_fields = ['billing_first_name', 'billing_last_name', 'billing_phone', 'billing_email'];
				foreach ($critical_fields as $critical_field) {
					if (!isset($fields['billing'][$critical_field])) {
						if (isset($default['billing'][$critical_field])) {
							$fields['billing'][$critical_field] = $default['billing'][$critical_field];
						}
					} else {
						// Ensure the field has the minimum required structure
						$field = &$fields['billing'][$critical_field];
						
						// Ensure it's not marked as deleted or disabled improperly
						if (isset($field['deleted']) && $field['deleted'] === 'deleted') {
							unset($field['deleted']);
						}
						
						if (isset($field['disabled']) && $field['disabled'] === '1') {
							$field['disabled'] = '';
						}
						
						// Ensure required structure with proper defaults
						if (!isset($field['label']) || $field['label'] === '') {
							$field['label'] = $default['billing'][$critical_field]['label'];
						}
						if (!isset($field['type']) || $field['type'] === '') {
							$field['type'] = $default['billing'][$critical_field]['type'] ?? 'text';
						}
						// Handle required field - keep custom setting if it exists, otherwise use default
						if (!isset($field['required'])) {
							$field['required'] = $default['billing'][$critical_field]['required'];
						} else {
							// Ensure boolean value
							$field['required'] = ($field['required'] === '1' || $field['required'] === true) ? true : false;
						}
						if (!isset($field['class']) || !is_array($field['class'])) {
							$field['class'] = $default['billing'][$critical_field]['class'];
						}
						if (!isset($field['priority']) || $field['priority'] === '') {
							$field['priority'] = $default['billing'][$critical_field]['priority'];
						}
						if (!isset($field['autocomplete']) || $field['autocomplete'] === '') {
							$field['autocomplete'] = $default['billing'][$critical_field]['autocomplete'];
						}
						// Fix validate array if it's empty or malformed
						if (!isset($field['validate']) || 
							(is_array($field['validate']) && count($field['validate']) === 1 && $field['validate'][0] === '')) {
							if (isset($default['billing'][$critical_field]['validate'])) {
								$field['validate'] = $default['billing'][$critical_field]['validate'];
							} else {
								unset($field['validate']); // Remove empty validate array
							}
						}
					}
				}
				
				return $fields;
			}
			/**
			 * Output file fields for a given section on the checkout page
			 */
			public function output_file_fields_for_section($section) {
				
				$all_fields = $this->inject_checkout_fields([]);
				
				$fields = $this->get_file_fields_for_section($section);
				
				if (!empty($fields)) {
					$this->file_upload_field_element($fields);
				}
			}
			/**
			 * Get file-type fields for a section
			 */
			public function get_file_fields_for_section($section) {
				// Use the raw custom fields option to get file fields
				$custom = get_option('mptbm_custom_checkout_fields', array());
				$fields = isset($custom[$section]) ? $custom[$section] : [];
				return array_filter($fields, function($field) {
					return isset($field['type']) && $field['type'] === 'file' && (empty($field['deleted']) || $field['deleted'] !== 'deleted') && (empty($field['disabled']) || $field['disabled'] !== '1');
				});
			}
			// Enqueue JS for file upload on checkout
			public static function enqueue_file_upload_js() {
				if (is_checkout()) {
					wp_enqueue_script('mptbm-file-upload', plugins_url('assets/frontend/js/mptbm-file-upload.js', dirname(__FILE__)), array('jquery'), '1.0', true);
					wp_localize_script('mptbm-file-upload', 'mptbmFileUpload', array(
						'ajax_url' => admin_url('admin-ajax.php'),
						'nonce' => wp_create_nonce('mptbm_file_upload'),
					));
				}
			}
			// Add CSS for hidden fields
			public function add_hidden_field_css() {
				?>
				<style>
				.mptbm-hidden-field {
					display: none;
				}
				</style>
				<?php
			}
		}
		
		// Initialize the class after 'init' to ensure textdomain is loaded
		add_action('init', function() {
			$instance = new MPTBM_Wc_Checkout_Fields_Helper();
			
			// Only add these if custom checkout system is not disabled
			if (!MPTBM_Wc_Checkout_Fields_Helper::disable_custom_checkout_system()) {
				add_action('wp_enqueue_scripts', array('MPTBM_Wc_Checkout_Fields_Helper', 'enqueue_file_upload_js'));
				// Register AJAX actions outside the class
				add_action('wp_ajax_mptbm_file_upload', array('MPTBM_Wc_Checkout_Fields_Helper', 'ajax_file_upload'));
				add_action('wp_ajax_nopriv_mptbm_file_upload', array('MPTBM_Wc_Checkout_Fields_Helper', 'ajax_file_upload'));
			}
		});
	}