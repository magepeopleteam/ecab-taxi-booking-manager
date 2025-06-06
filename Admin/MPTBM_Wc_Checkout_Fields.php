<?php
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	/**
	 * Class MPTBM_Wc_Checkout_Fields
	 *
	 * @since 1.0
	 *
	 * */
	if (!class_exists('MPTBM_Wc_Checkout_Fields')) {
		class MPTBM_Wc_Checkout_Fields {
			private $error;
			private $settings_options;
			private $allowed_extensions;
			private $allowed_mime_types;
			public function __construct() {
				$this->error = new WP_Error();
				add_action('init', array($this, 'get_settings_options'));
				add_action('add_mptbm_admin_script', array($this, 'admin_enqueue'));
				add_action('add_mptbm_frontend_script', array($this, 'frontend_enqueue'), 99);
				add_action('admin_menu', array($this, 'checkout_menu'));
				add_action('admin_notices', array($this, 'mp_admin_notice'));
				add_action('add_switch_button', array($this, 'switch_button'), 10, 3);
				add_action('wp_ajax_mptbm_disable_field', [$this, 'mptbm_disable_field']);
				add_action('wp_ajax_nopriv_mptbm_disable_field', [$this, 'mptbm_disable_field']);
			}
			public function mptbm_disable_field() {
				// Verify nonce
				if ( !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mptbm_checkout_nonce') ) {
					return;
				}
				// Check if user is logged in and has the correct capability
				if ( !current_user_can( 'manage_options' ) ) {
					wp_send_json_error( array( 'message' => 'Unauthorized action' ), 403 );
					wp_die();
				}
				$response = 'failed';
				$key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : null;
				$name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : null;
				$isChecked = isset($_POST['isChecked']) ? sanitize_text_field($_POST['isChecked']) : null;
				$checkout_fields = MPTBM_Wc_Checkout_Fields_Helper::get_checkout_fields_for_list();
				$custom_checkout_fields = array();
				$custom_checkout_fields = get_option('mptbm_custom_checkout_fields');
				if (isset($checkout_fields[$key][$name])) {
					unset($custom_checkout_fields[$key][$name]);
					if ($isChecked == 'true') {
						$custom_checkout_fields[$key][$name] = $checkout_fields[$key][$name];
						$custom_checkout_fields[$key][$name]['disabled'] = '';
					} elseif ($isChecked == 'false') {
						$custom_checkout_fields[$key][$name] = $checkout_fields[$key][$name];
						$custom_checkout_fields[$key][$name]['disabled'] = '1';
					}
					update_option('mptbm_custom_checkout_fields', $custom_checkout_fields);
					$response = 'success';
				}
				echo esc_html($response);
				die();
			}
			public static function switch_button($id, $class, $name, $status, $data) {
				$str_data = '';
                //echo '<pre>';print_r($data);echo '</pre>';
                $key=is_array($data) && array_key_exists('key',$data) ? $data['key'] : '';
                $_name=is_array($data) && array_key_exists('name',$data) ? $data['name'] : '';
				?>
                <label class="switch">
                    <input type="checkbox" id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($class); ?>" name="<?php echo esc_attr($name); ?>" <?php echo esc_attr($status); ?>  data-key="<?php echo esc_attr($key); ?>" data-name="<?php echo esc_attr($_name); ?>">
                    <span class="slider"></span>
                </label>
				<?php
			}
			public function get_settings_options() {
				$this->settings_options = get_option('mptbm_custom_checkout_fields');
			}
			public function admin_enqueue() {
				//wp_enqueue_style('mptbm_checkout_common', MPTBM_PLUGIN_URL . '/assets/checkout/css/mptbm-pro-styles.css', array(), time());
				//wp_enqueue_script('mptbm_checkout_common', MPTBM_PLUGIN_URL . '/assets/checkout/js/mptbm-pro-styles.js', array('jquery'), time(), true);
				wp_enqueue_style('mptbm_checkout', MPTBM_PLUGIN_URL . '/assets/checkout/css/mptbm-pro-checkout.css', array(), time());
				wp_enqueue_script('mptbm_checkout', MPTBM_PLUGIN_URL . '/assets/checkout/js/mptbm-pro-checkout.js', array('jquery'), time(), true);
				wp_enqueue_script('mptbm_checkout_custom_script', 'https://code.jquery.com/ui/1.12.1/jquery-ui.js', array('jquery', 'jquery-ui-core', 'jquery-ui-sortable'), time(), true);
				// Create a nonce
				$nonce = wp_create_nonce('mptbm_checkout_nonce');

				// Pass the nonce to JavaScript
				wp_localize_script('mptbm_checkout', 'mptbm_checkout_object', array(
					'nonce' => $nonce
				));
			}
			public function frontend_enqueue() {
				wp_enqueue_style('mptbm_checkout_front_style', MPTBM_PLUGIN_URL . '/assets/checkout/front/css/mptbm-pro-checkout-front-style.css', array(), time());
				wp_enqueue_script('mptbm_checkout_front_script', MPTBM_PLUGIN_URL . '/assets/checkout/front/js/mptbm-pro-checkout-front-script.js', array('jquery'), time(), true);
			}
			public function checkout_menu() {
				$cpt = MPTBM_Function::get_cpt();
				add_submenu_page('edit.php?post_type=' . $cpt, esc_html__('Checkout Fields', 'ecab-taxi-booking-manager'), esc_html__('Checkout Fields', 'ecab-taxi-booking-manager'), 'manage_options', 'mptbm_wc_checkout_fields', array($this, 'wc_checkout_fields'));
			}
			public function wc_checkout_fields() {
				if (!current_user_can('administrator')) {
					wp_die(esc_html__('You do not have sufficient permissions to access this page.'));
				}
				
				// Handle form submissions
				$post_action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : null;
				$get_action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : null;
				
				if ($post_action == 'add' || $post_action == 'edit') {
					$this->process_checkout_field_form();
				} else if ($get_action == 'delete') {
					$this->process_checkout_field_delete();
				}
				
				do_action('mptbm_save_checkout_fields_settings');
				do_action('mptbm_wc_checkout_fields');
				self::checkout_field_list();
			}
			
			/**
			 * Process the checkout field form (add/edit)
			 */
			private function process_checkout_field_form() {
				$action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';
				
				if ($action == 'add') {
					// Check nonce for security
					if (!isset($_POST['mptbm_checkout_field_add_nonce']) || !wp_verify_nonce($_POST['mptbm_checkout_field_add_nonce'], 'mptbm_checkout_field_add')) {
						return;
					}
					
					$key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : null;
					$type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : null;
					$name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : null;
					
					if (isset($name)) {
						$options = get_option('mptbm_custom_checkout_fields');
						
						if (!is_array($options)) {
							$options = array();
						}
						
						if (substr($name, 0, strlen($key . '_')) !== $key . '_') {
							$name = $key . '_' . $name;
						}
						
						if (!isset(MPTBM_Wc_Checkout_Fields_Helper::$default_woocommerce_checkout_fields[$key][$name]) && 
							(!isset($options[$key][$name]) || (isset($options[$key][$name]) && $options[$key][$name]['deleted'] == 'deleted'))) {
							$options[$key][$name] = $this->prepare_field_data($type);
							update_option('mptbm_custom_checkout_fields', $options);
						}
					}
				} else if ($action == 'edit') {
					// Check nonce for security
					if (!isset($_POST['mptbm_checkout_field_edit_nonce']) || !wp_verify_nonce($_POST['mptbm_checkout_field_edit_nonce'], 'mptbm_checkout_field_edit')) {
						return;
					}
					
					$key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : null;
					$type = isset($_POST['new_type']) ? sanitize_text_field($_POST['new_type']) : null;
					$name = isset($_POST['new_name']) ? sanitize_text_field($_POST['new_name']) : null;
					$old_name = isset($_POST['old_name']) ? sanitize_text_field($_POST['old_name']) : null;
					
					if (isset($name)) {
						$options = get_option('mptbm_custom_checkout_fields');
						
						if (!is_array($options)) {
							$options = array();
						}
						
						if (substr($name, 0, strlen($key . '_')) !== $key . '_') {
							$name = $key . '_' . $name;
						}
						
						if (isset($options[$key][$old_name]) && $old_name != $name) {
							unset($options[$key][$old_name]);
						}
						
						$options[$key][$name] = $this->prepare_field_data($type);
						update_option('mptbm_custom_checkout_fields', $options);
					}
				}
			}
			
			/**
			 * Process checkout field delete action
			 */
			private function process_checkout_field_delete() {
				// Check nonce for security
				if (!isset($_GET['mptbm_checkout_field_delete_nonce']) || !wp_verify_nonce($_GET['mptbm_checkout_field_delete_nonce'], 'mptbm_checkout_field_delete')) {
					return;
				}
				
				$key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : null;
				$name = isset($_GET['name']) ? sanitize_text_field($_GET['name']) : null;
				
				if (isset($name)) {
					$options = get_option('mptbm_custom_checkout_fields');
					
					if (!is_array($options)) {
						$options = array();
					}
					
					$new_options = array(
						'deleted' => 'deleted',
					);
					
					if (isset($options[$key][$name])) {
						$options[$key][$name] = array_merge($options[$key][$name], $new_options);
					}
					
					update_option('mptbm_custom_checkout_fields', $options);
				}
			}
			
			/**
			 * Prepare field data based on type
			 */
			private function prepare_field_data($type) {
				$label = isset($_POST['label']) ? sanitize_text_field($_POST['label']) : null;
				$class = isset($_POST['class']) ? sanitize_text_field($_POST['class']) : null;
				$validate = isset($_POST['validate']) ? sanitize_text_field($_POST['validate']) : null;
				$placeholder = isset($_POST['placeholder']) ? sanitize_text_field($_POST['placeholder']) : null;
				$priority = isset($_POST['priority']) ? sanitize_text_field($_POST['priority']) : null;
				$required = isset($_POST['required']) ? sanitize_text_field($_POST['required']) : null;
				$disabled = isset($_POST['disabled']) ? sanitize_text_field($_POST['disabled']) : null;
				
				$field_data = array(
					'type' => $type,
					'label' => $label,
					'class' => explode(',', $class),
					'validate' => explode(',', $validate),
					'placeholder' => $placeholder,
					'priority' => $priority,
					'required' => $required == 'on' ? '1' : '',
					'disabled' => $disabled == 'on' ? '1' : '',
					'custom_field' => '1',
				);
				
				// Add options if type is select
				if ($type == 'select' && isset($_POST['option_value']) && isset($_POST['option_text'])) {
					$array_option_text = array_map('sanitize_text_field', $_POST['option_text']);
					$array_option_value = array_map('sanitize_text_field', $_POST['option_value']);
					
					if (is_array($array_option_value)) {
						$select_options = array();
						foreach ($array_option_value as $index => $value) {
							$select_options[$value] = isset($array_option_text[$index]) ? $array_option_text[$index] : '';
						}
						$field_data['options'] = $select_options;
					}
				}
				
				return $field_data;
			}
			public function checkout_field_list() {
				?>
                <div class="mpStyles">
                    <div class="checkout">
                        <div class="modal-container">
                            <div class="modal" id="field-modal">
                                <div class="modal-content">
                                    <span class="close">&times;</span>
                                    <div class="custom-form-container">
                                        <div class="custom-form">
                                            <h2>Checkout Field</h2>
                                            <form method="post">
                                                <input type="hidden" name="action" required>
                                                <input type="hidden" name="key" required>
                                                <input type="hidden" name="old_name">
                                                <input type="hidden" name="new_name">
                                                <input type="hidden" name="new_type">
                                                <label for="type">Type:</label>
                                                <select name="type" id="type" required>
                                                    <option value="" disabled>Select an option</option>
                                                    <option value="text">Text</option>
                                                    <option value="select">Select</option>
                                                    <option value="file">Image</option>
                                                </select>
                                                <label for="name">Name:</label>
                                                <input type="text" name="name" id="name" required>
                                                <label for="label">Label:</label>
                                                <input type="text" name="label" id="label" required>
                                                <label for="priority">Position:( >= 0 )</label>
                                                <input type="text" pattern="[0-9]+" name="priority" id="priority">
                                                <label for="name">Class:</label>
                                                <input type="text" name="class" id="class">
                                                <label for="name">Validation:</label>
                                                <input type="text" name="validate" id="validate">
                                                <div class="custom-var-attr-section">
                                                    <label for="placeholder">Placeholder:</label>
                                                    <input type="text" name="placeholder" id="placeholder">
                                                </div>
                                                <label><input type="checkbox" name="required"> Required</label>
                                                <label><input type="checkbox" name="disabled"> Disabled</label>
                                                <p class="add-nonce"><?php wp_nonce_field('mptbm_checkout_field_add', 'mptbm_checkout_field_add_nonce'); ?></p>
                                                <p class="edit-nonce"><?php wp_nonce_field('mptbm_checkout_field_edit', 'mptbm_checkout_field_edit_nonce'); ?></p>
                                                <button type="submit">Submit</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mpStyles">
                    <div class="checkout">
                        <div class="tab-container">
                            <ul class="tab-menu">
                                <h3>CHECKOUT FIELDS</h3>
                                <li class="tab-item" data-tabs-target="#mptbm_wc_checkout_settings"><i class="dashicons dashicons-admin-generic text-primary"></i> Checkout Settings <i class="i i-chevron-right dashicons dashicons-arrow-right-alt2"></i></li>
                                <li class="tab-item active" data-tabs-target="#mptbm_wc_billing_field_settings"><i class="dashicons dashicons-clipboard text-primary"></i> Billing Fields <i class="i i-chevron-right dashicons dashicons-arrow-right-alt2"></i></li>
                                <!-- <li class="tab-item" data-tabs-target="#mptbm_wc_shipping_field_settings"><i class="dashicons dashicons-airplane text-primary"></i> Shipping Fields <i class="i i-chevron-right dashicons dashicons-arrow-right-alt2"></i></li>
                                <li class="tab-item" data-tabs-target="#mptbm_wc_order_field_settings"><i class="dashicons dashicons-format-status text-primary"></i> Order Fields <i class="i i-chevron-right dashicons dashicons-arrow-right-alt2"></i></li> -->
                            </ul>
                            <div class="tab-content-container">
								<?php do_action('mptbm_wc_checkout_tab_content', MPTBM_Wc_Checkout_Fields_Helper::get_checkout_fields_for_list()); ?>
                            </div>
                        </div>
                    </div>
                </div>
				<?php
			}
			public function mp_admin_notice() {
				self::mp_error_notice($this->error);
			}
			public static function mp_error_notice($error) {
				if ($error->has_errors()) {
					foreach ($error->get_error_messages() as $error) {
						$class = 'notice notice-error';
						printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($error));
					}
				}
			}
		}
		new MPTBM_Wc_Checkout_Fields();
	}