<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Admin')) {
		class MPTBM_Admin {
			public function __construct() {
				if (is_admin()) {
					$this->load_file();
					add_action('init', [$this, 'add_dummy_data']);
					add_filter('use_block_editor_for_post_type', [$this, 'disable_gutenberg'], 10, 2);
					add_action('admin_action_mptbm_rent_duplicate', [$this, 'mptbm_rent_duplicate']);
					add_filter('post_row_actions', [$this, 'post_duplicator'], 10, 2);
					add_filter('wp_mail_content_type', array($this, 'email_content_type'));
					add_action('upgrader_process_complete', [$this, 'flush_rewrite'], 0);
				}
			}
			public function flush_rewrite() {
				flush_rewrite_rules();
			}
			private function load_file(): void {
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Dummy_Import.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Hidden_Product.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_CPT.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Quick_Setup.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Status.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Guideline.php';
				//****************Global settings************************//
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Settings_Global.php';
				//****************Taxi settings************************//
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Settings.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_General_Settings.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_Price_Settings.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_Extra_Service.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_Date_Settings.php';
				//require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_Gallery_Settings.php';
			}
			public function add_dummy_data() {
				new MPTBM_Dummy_Import();
			}
			//************Disable Gutenberg************************//
			public function disable_gutenberg($current_status, $post_type) {
				$user_status = MP_Global_Function::get_settings('mp_global_settings', 'disable_block_editor', 'yes');
				if ($post_type === MPTBM_Function::get_cpt() && $user_status == 'yes') {
					return false;
				}
				return $current_status;
			}
			//**************Post duplicator*********************//
			public function mptbm_rent_duplicate() {
				global $wpdb;
				if (!(isset($_GET['post']) || isset($_POST['post']) || (isset($_REQUEST['action']) && 'mptbm_rent_duplicate' == $_REQUEST['action']))) {
					wp_die('No post to duplicate has been supplied!');
				}
				if (!isset($_GET['duplicate_nonce']) || !wp_verify_nonce($_GET['duplicate_nonce'], basename(__FILE__))) {
					return;
				}
				$post_id = (isset($_GET['post']) ? absint($_GET['post']) : absint($_POST['post']));
				$post = get_post($post_id);
				$current_user = wp_get_current_user();
				$new_post_author = $current_user->ID;
				if (isset($post) && $post != null) {
					$args = array('comment_status' => $post->comment_status, 'ping_status' => $post->ping_status, 'post_author' => $new_post_author, 'post_content' => $post->post_content, 'post_excerpt' => $post->post_excerpt, 'post_name' => $post->post_name, 'post_parent' => $post->post_parent, 'post_password' => $post->post_password, 'post_status' => 'draft', 'post_title' => $post->post_title, 'post_type' => $post->post_type, 'to_ping' => $post->to_ping, 'menu_order' => $post->menu_order);
					$new_post_id = wp_insert_post($args);
					$taxonomies = get_object_taxonomies($post->post_type); // returns array of taxonomy names for post type, ex array("category", "post_tag");
					foreach ($taxonomies as $taxonomy) {
						$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
						wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
					}
					$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id AND meta_key !='total_booking'");
					if (count($post_meta_infos) != 0) {
						$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
						foreach ($post_meta_infos as $meta_info) {
							$meta_key = $meta_info->meta_key;
							if ($meta_key == '_wp_old_slug') {
								continue;
							}
							$meta_value = addslashes($meta_info->meta_value);
							$sql_query_sel[] = "SELECT $new_post_id, '$meta_key', '$meta_value'";
						}
						$sql_query .= implode(" UNION ALL ", $sql_query_sel);
						$wpdb->query($sql_query);
						$table_name = $wpdb->prefix . 'postmeta';
						$bi = $wpdb->insert($table_name, array('post_id' => $new_post_id, 'meta_key' => 'total_booking', 'meta_value' => 0), array('%d', '%s', '%d'));
					}
					wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
					exit;
				}
				else {
					wp_die('Post creation failed, could not find original post: ' . $post_id);
				}
			}
			public function post_duplicator($actions, $post) {
				if (current_user_can('edit_posts')) {
					$actions['duplicate'] = '<a href="' . wp_nonce_url('admin.php?action=mptbm_rent_duplicate&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce') . '" title="' . esc_html__('Duplicate Post', 'ecab-taxi-booking-manager') . '" rel="permalink">' . esc_html__('Duplicate', 'ecab-taxi-booking-manager') . '</a>';
				}
				return $actions;
			}
			//*************************//
			public function email_content_type() {
				return "text/html";
			}
		}
		new MPTBM_Admin();
	}