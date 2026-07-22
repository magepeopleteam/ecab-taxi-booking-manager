<?php
/*
   * @Author 		rubelcuet10@gmail.com
   * Copyright: 	mage-people.com
   */
if ( ! defined( 'ABSPATH' ) ) {
    die;
} // Cannot access pages directly.
if ( ! class_exists('MPTBM_Right_Side_Content_Settings') ) {
    class MPTBM_Right_Side_Content_Settings{
        public function __construct(){
            add_action('mptbm_right_side_section', [ $this, 'mptbm_right_side_section'], 10, 1 );

            add_action('wp_ajax_mptbm_taxi_save_category', [ $this, 'mptbm_taxi_save_category' ] );
            add_action('wp_ajax_mptbm_taxi_save_post_category', [ $this, 'mptbm_taxi_save_post_category' ]);

            add_action('wp_ajax_mptbm_taxi_add_tag',  [ $this, 'mptbm_taxi_add_tag' ] );
            add_action('wp_ajax_mptbm_taxi_remove_tag', [ $this, 'mptbm_taxi_remove_tag' ] );


            add_action('wp_ajax_mptbm_add_edit_taxi_category',  [ $this, 'mptbm_add_edit_taxi_category' ] );
            add_action('wp_ajax_mptbm_get_all_categories',  [ $this, 'mptbm_get_all_categories' ] );
            add_action('wp_ajax_mptbm_remove_category_from_cat_list',  [ $this, 'mptbm_remove_category_from_cat_list' ] );


        }


        public function mptbm_right_side_section( $post_id ) {

            self::mptbm_right_feature_image( $post_id );

            self::mptbm_right_pro_features_card();

            self::mptbm_right_quick_tipcs( $post_id );

            self::category_tag_add( $post_id );

        }

        public static function category_tag_add( $post_id ){
            $saved_category = get_post_meta($post_id, 'mptbm_taxi_category_id', true);

            $categories = get_option('mptbm_taxi_categories', []);
            $nonce = wp_create_nonce('mptbm_taxi_nonce');
            ?>
            <div class="mptbm_taxi_category_container">
                <input type="hidden"
                       id="mptbm_taxi_nonce"
                       value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden"
                       id="mptbm_taxi_post_id"
                       value="<?php echo esc_attr($post_id); ?>">

                <div class="mptbm_taxi_category_card">
                    <div class="mptbm_taxi_card_head">
                        <span class="mptbm_taxi_card_icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                        </span>
                        <div class="mptbm_taxi_card_head_text">
                            <label class="mptbm_taxi_category_label"><?php esc_html_e( 'Categories', 'ecab-taxi-booking-manager' ); ?></label>
                            <span class="mptbm_taxi_category_subtext"><?php esc_html_e( 'Select vehicle category', 'ecab-taxi-booking-manager' ); ?></span>
                        </div>
                        <button type="button" class="mptbm_taxi_all_category_label">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                            <?php esc_html_e( 'All Categories', 'ecab-taxi-booking-manager' ); ?>
                        </button>
                    </div>

                    <div class="mptbm_taxi_category_flex_group" id="mptbm_taxi_category_flex_group">

                        <div class="mptbm_taxi_select_wrap">
                            <select id="mptbm_taxi_category_dropdown"
                                    class="mptbm_taxi_category_select">

                                <option value="" disabled <?php selected($saved_category, ''); ?>><?php esc_html_e( 'Select Category', 'ecab-taxi-booking-manager' ); ?></option>

                                <?php foreach ($categories as $cat) : ?>

                                    <option value="<?php echo esc_attr($cat['id']); ?>"
                                        <?php selected($saved_category, $cat['id']); ?>>

                                        <?php echo esc_html($cat['name']); ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>
                            <svg class="mptbm_taxi_select_chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>

                        <button type="button"
                                id="mptbm_taxi_category_open_popup"
                                class="mptbm_taxi_category_btn_add"
                                title="<?php esc_attr_e( 'Add new category', 'ecab-taxi-booking-manager' ); ?>"
                                aria-label="<?php esc_attr_e( 'Add new category', 'ecab-taxi-booking-manager' ); ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        </button>

                    </div>
                    <p class="mptbm_taxi_category_helptext">
                        <?php esc_html_e( 'Choose the category that best fits this transport.', 'ecab-taxi-booking-manager' ); ?>
                    </p>
                </div>



                <?php
                $tags = get_post_meta($post_id, 'mptbm_taxi_tags', true);
                if (!is_array($tags)) {
                    $tags = [];
                }
                ?>

                <div class="mptbm_taxi_category_card">
                    <div class="mptbm_taxi_card_head">
                        <span class="mptbm_taxi_card_icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                        </span>
                        <div class="mptbm_taxi_card_head_text">
                            <label class="mptbm_taxi_category_label"><?php esc_html_e( 'Tags', 'ecab-taxi-booking-manager' ); ?></label>
                            <span class="mptbm_taxi_category_subtext"><?php esc_html_e( 'Add keywords for searching', 'ecab-taxi-booking-manager' ); ?></span>
                        </div>
                    </div>
                    <div class="mptbm_taxi_category_tag_input_wrapper">
                        <svg class="mptbm_taxi_tag_input_icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text"
                               id="mptbm_taxi_category_tag_input"
                               class="mptbm_taxi_category_input"
                               placeholder="<?php esc_attr_e( 'Add a tag and press Enter', 'ecab-taxi-booking-manager' ); ?>">
                    </div>
                    <div id="mptbm_taxi_category_tags_list"
                         class="mptbm_taxi_category_tags_container">
                        <?php foreach ($tags as $tag) : ?>
                            <span class="mptbm_taxi_category_badge"
                                  data-tag="<?php echo esc_attr($tag); ?>">
                                <?php echo esc_html($tag); ?>
                                <i class="mptbm_taxi_category_remove_tag">&times;</i>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php self::category_add_popup();?>
            </div>
        <?php }

        public static function mptbm_right_pro_features_card() {
            if ( class_exists( 'MPTBM_Dependencies_Pro' ) ) {
                return;
            }
            ?>
            <div class="mptbm_pro_card_wrapper">

                <div class="mptbm_pro_card_header">
                    <span class="mptbm_pro_card_badge">
                        &#9733; <?php esc_html_e( 'Pro', 'ecab-taxi-booking-manager' ); ?>
                    </span>
                    <span class="mptbm_pro_card_status_inactive">
                        <?php esc_html_e( 'Not Active', 'ecab-taxi-booking-manager' ); ?>
                    </span>
                </div>

                <div class="mptbm_pro_card_title">
                    <?php esc_html_e( 'Pro Features', 'ecab-taxi-booking-manager' ); ?>
                </div>

                <div class="mptbm_pro_card_desc">
                    <?php esc_html_e( 'Unlock powerful tools to supercharge your taxi booking system. Upgrade to Pro and get access to:', 'ecab-taxi-booking-manager' ); ?>
                </div>

                <ul class="mptbm_pro_card_list">
                    <li><?php esc_html_e( 'Provide Intercity Ride Services Like Uber By Selecting Pickup And Destination Locations On The Map', 'ecab-taxi-booking-manager' ); ?></li>
                    <li><?php esc_html_e( 'Set Up Smart Service Zones With Advanced Geofencing Technology', 'ecab-taxi-booking-manager' ); ?></li>
                    <li><?php esc_html_e( 'Manage Order Lists and Order Details Efficiently', 'ecab-taxi-booking-manager' ); ?></li>
                    <li><?php esc_html_e( 'Add a dedicated driver management panel', 'ecab-taxi-booking-manager' ); ?></li>
                    <li><?php esc_html_e( 'Distance-based Tier Pricing ( Addon )', 'ecab-taxi-booking-manager' ); ?></li>
                    <li><?php esc_html_e( 'Peak Hour Pricing Rules ( Addon )', 'ecab-taxi-booking-manager' ); ?></li>
                    <li><?php esc_html_e( 'Custom Checkout Fields', 'ecab-taxi-booking-manager' ); ?></li>
                    <li><?php esc_html_e( 'Priority Email And PDF Support', 'ecab-taxi-booking-manager' ); ?></li>
                </ul>

                <a href="https://mage-people.com/" class="mptbm_pro_card_btn" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'Upgrade to Pro', 'ecab-taxi-booking-manager' ); ?>
                </a>

            </div>
        <?php }

        public static function mptbm_right_quick_tipcs( $post_id ){ ?>
            <div class="mptbm_quick_tips_card">

                <div class="mptbm_quick_tips_badge">
                    <?php esc_html_e( 'Quick Help', 'ecab-taxi-booking-manager' ); ?>
                </div>

                <div class="mptbm_quick_tips_title">
                    <?php esc_html_e( 'Quick Tips & Documentation', 'ecab-taxi-booking-manager' ); ?>
                </div>

                <div class="mptbm_quick_tips_desc">
                    <?php esc_html_e( 'Configure your taxi booking settings easily using our step-by-step documentation and setup guide.', 'ecab-taxi-booking-manager' ); ?>
                </div>

                <ul class="mptbm_quick_tips_list">
                    <li><?php esc_html_e( 'How do extra service settings in transport be set up?', 'ecab-taxi-booking-manager' ); ?></li>
                    <li><?php esc_html_e( 'Transport Booking ShortCode', 'ecab-taxi-booking-manager' ); ?></li>
                    <li><?php esc_html_e( 'How does the price-based model work on transportation?', 'ecab-taxi-booking-manager' ); ?></li>
                    <li><?php esc_html_e( 'What is the initial price in transport?', 'ecab-taxi-booking-manager' ); ?></li>
                </ul>

                <a href="https://docs.mage-people.com/plugins/ecab/overview" class="mptbm_quick_tips_btn">
                    <?php esc_html_e( 'View Documentation', 'ecab-taxi-booking-manager' ); ?>
                </a>

            </div>
        <?php }
        public static function mptbm_right_feature_image( $post_id ) {
            $image_id = get_post_thumbnail_id( $post_id );
            $image_url = '';
            if ( $image_id ) {
                $image_url = wp_get_attachment_image_url( $image_id, 'medium' );
            }
            ?>
            <div class="mptbm_feature_image_card">
                <div class="mptbm_rent_editor_header ">
                    <h2 class="mptbm_rent_editor_title"><?php esc_html_e( 'Featured Image', 'ecab-taxi-booking-manager' ); ?></h2>
                </div>
                <div class="mptbm_feature_image_body">
                    <input
                        type="hidden"
                        id="mptbm_feature_image_id"
                        name="mptbm_feature_image_id"
                        value="<?php echo esc_attr( $image_id ); ?>"
                    >
                    <div
                        class="mptbm_feature_image_wrapper"
                        data-has-image="<?php echo $image_id ? '1' : '0'; ?>"
                    >
                        <div class="mptbm_feature_image_preview">

                            <?php if ( $image_url ) : ?>
                                <img
                                    src="<?php echo esc_url( $image_url ); ?>"
                                    alt=""
                                >
                            <?php else : ?>
                                <div class="mptbm_feature_image_placeholder">
                                    <?php esc_html_e( 'Select Transportation Image', 'ecab-taxi-booking-manager' ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mptbm_feature_image_actions">
                            <button
                                type="button"
                                class="button button-primary mptbm_feature_image_select"
                            >
                                <?php esc_html_e( 'Select Image', 'ecab-taxi-booking-manager' ); ?>
                            </button>
                            <button
                                type="button"
                                class="button mptbm_feature_image_remove"
                            >
                                <?php esc_html_e( 'Remove', 'ecab-taxi-booking-manager' ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
        public function mptbm_taxi_save_post_category() {
            if (
                !isset($_POST['nonce']) ||
                !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mptbm_taxi_nonce')
            ) {
                wp_send_json_error([
                    'message' => 'Security check failed'
                ]);
            }

            $post_id = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : "" ;
            $category_id = isset( $_POST['category_id'] ) ? sanitize_text_field( wp_unslash( $_POST['category_id'] ) ) : '' ;

            if (!$post_id || !$category_id) {
                wp_send_json_error(['message' => 'Invalid data']);
            }

            update_post_meta(
                $post_id,
                'mptbm_taxi_category_id',
                $category_id
            );

            wp_send_json_success(['message' => 'Category saved']);
        }
        function mptbm_taxi_save_category_old() {
            if (
                !isset($_POST['nonce']) ||
                !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mptbm_taxi_nonce')
            ) {
                wp_send_json_error([
                    'message' => 'Security check failed'
                ]);
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error([
                    'message' => 'Permission denied'
                ]);
            }

            $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
            $type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
            $desc = isset( $_POST['desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['desc'] ) ) : '';

            $cat_id = isset( $_POST['cat_id'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cat_id'] ) ) : '';

            if (empty($name)) {
                wp_send_json_error([
                    'message' => 'Category name is required'
                ]);
            }
            $categories = get_option('mptbm_taxi_categories', []);
            if (!is_array($categories)) {
                $categories = [];
            }
            $categories[] = [
                'id'   => time(),
                'name' => $name,
                'type' => $type,
                'desc' => $desc,
            ];


            update_option('mptbm_taxi_categories', $categories);

            ob_start();
            ?>
                <option value=""><?php esc_attr_e( 'Select Category', 'ecab-taxi-booking-manager' );?></option>
                <?php foreach ($categories as $category) : ?>
                    <option value="<?php echo esc_attr($category['id']); ?>">
                        <?php echo esc_html($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            <?php
            $html = ob_get_clean();

            wp_send_json_success([
                'message' => 'Category saved successfully',
                'data'    => $categories,
                'category_html_data'    => $html
            ]);
        }

        function mptbm_taxi_save_category() {

            if (
                !isset($_POST['nonce']) ||
                !wp_verify_nonce(
                    sanitize_text_field(wp_unslash($_POST['nonce'])),
                    'mptbm_taxi_nonce'
                )
            ) {
                wp_send_json_error(['message' => 'Security check failed']);
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Permission denied']);
            }

            $name   = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
            $type   = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';
            $desc   = isset($_POST['desc']) ? sanitize_textarea_field(wp_unslash($_POST['desc'])) : '';
            $cat_id = isset($_POST['cat_id']) ? sanitize_text_field(wp_unslash($_POST['cat_id'])) : '';

            if (empty($name)) {
                wp_send_json_error(['message' => 'Category name is required']);
            }

            $categories = get_option('mptbm_taxi_categories', []);

            if (!is_array($categories)) {
                $categories = [];
            }
            if (!empty($cat_id)) {

                foreach ($categories as &$category) {
                    if ($category['id'] == $cat_id) {
                        $category['name'] = $name;
                        $category['type'] = $type;
                        $category['desc'] = $desc;
                        break;
                    }
                }

                unset($category);
            }
            else {

                $categories[] = [
                    'id'   => time(),
                    'name' => $name,
                    'type' => $type,
                    'desc' => $desc,
                ];
            }

            update_option('mptbm_taxi_categories', $categories);

            ob_start();
            ?>
            <option value=""><?php esc_attr_e('Select Category', 'ecab-taxi-booking-manager'); ?></option>

            <?php foreach ($categories as $category) : ?>
                <option value="<?php echo esc_attr($category['id']); ?>">
                    <?php echo esc_html($category['name']); ?>
                </option>
            <?php endforeach; ?>
            <?php

            $html = ob_get_clean();

            wp_send_json_success([
                'message' => !empty($cat_id) ? 'Category updated successfully' : 'Category added successfully',
                'data'    => $categories,
                'category_html_data' => $html
            ]);
        }

        function mptbm_remove_category_from_cat_list() {

            if (
                !isset($_POST['nonce']) ||
                !wp_verify_nonce(
                    sanitize_text_field(wp_unslash($_POST['nonce'])),
                    'mptbm_taxi_nonce'
                )
            ) {
                wp_send_json_error(['message' => 'Security check failed']);
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Permission denied']);
            }
            $cat_id = isset($_POST['category_id']) ? sanitize_text_field(wp_unslash($_POST['category_id'])) : '';

            $categories = get_option('mptbm_taxi_categories', []);

            if (!is_array($categories)) {
                $categories = [];
            }
            if (!empty($cat_id)) {

                foreach ($categories as $key => $category) {
                    if ($category['id'] == $cat_id) {
                        unset($categories[$key]);
                        break;
                    }
                }
                $categories = array_values($categories);
            }
            update_option('mptbm_taxi_categories', $categories);

            ob_start();
            ?>
            <option value=""><?php esc_attr_e('Select Category', 'ecab-taxi-booking-manager'); ?></option>

            <?php foreach ($categories as $category) : ?>
                <option value="<?php echo esc_attr($category['id']); ?>">
                    <?php echo esc_html($category['name']); ?>
                </option>
            <?php endforeach; ?>
            <?php

            $html = ob_get_clean();

            wp_send_json_success([
                'message' => !empty($cat_id) ? 'Category updated successfully' : 'Category added successfully',
                'data'    => $categories,
                'category_html_data' => $html
            ]);
        }

        public static function category_add_popup(){ ?>
            <div id="mptbm_taxi_category_modal" class="mptbm_taxi_category_modal_overlay">
                <span>Loading...</span>
            </div>
        <?php }

        public static function add_edit_category( $category ) {

            $is_edit = ! empty($category);

            $id          = $category['id'] ?? '';
            $name        = $category['name'] ?? '';
            $type        = $category['type'] ?? 'standard';
            $desc        = $category['desc'] ?? '';

            ob_start();
            ?>
            <div class="mptbm_taxi_category_modal_content" id="mptbm_taxi_category_modal_content">

                <div class="mptbm_taxi_category_modal_header">
                    <h3>
                        <?php echo $is_edit
                            ? esc_html__( 'Edit Category', 'ecab-taxi-booking-manager' )
                            : esc_html__( 'Create New Category', 'ecab-taxi-booking-manager' );
                        ?>
                    </h3>

                    <span id="mptbm_taxi_category_close_popup"
                          class="mptbm_taxi_category_modal_close">&times;</span>
                </div>

                <div class="mptbm_taxi_category_modal_body">

                    <?php if ( $is_edit ) : ?>
                        <input type="hidden" id="mptbm_taxi_category_id" value="<?php echo esc_attr($id); ?>">
                    <?php endif; ?>

                    <div class="mptbm_taxi_category_form_group">
                        <label><?php esc_html_e( 'Category Name', 'ecab-taxi-booking-manager' ); ?></label>
                        <input type="text"
                               id="mptbm_taxi_category_new_name"
                               class="mptbm_taxi_category_input"
                               value=" <?php echo esc_attr( $name ); ?>"
                               placeholder="<?php esc_html_e( 'e.g., Electric Van', 'ecab-taxi-booking-manager' ); ?>">
                    </div>

                    <div class="mptbm_taxi_category_form_group">
                        <label><?php esc_html_e( 'Category Type', 'ecab-taxi-booking-manager' ); ?></label>
                        <select id="mptbm_taxi_category_type" class="mptbm_taxi_category_input">
                            <option value="standard" <?php selected($type, 'standard'); ?>>Standard</option>
                            <option value="premium" <?php selected($type, 'premium'); ?>>Premium</option>
                            <option value="luxury" <?php selected($type, 'luxury'); ?>>Luxury</option>
                        </select>
                    </div>

                    <div class="mptbm_taxi_category_form_group">
                        <label><?php esc_html_e( 'Description', 'ecab-taxi-booking-manager' ); ?></label>
                        <textarea id="mptbm_taxi_category_desc"
                                  class="mptbm_taxi_category_input"
                                  placeholder="<?php esc_html_e( 'Write description...', 'ecab-taxi-booking-manager' ); ?>"><?php echo esc_textarea($desc); ?></textarea>
                    </div>

                </div>

                <div class="mptbm_taxi_category_modal_footer">
                    <button type="button"
                            id="mptbm_taxi_category_save_btn"
                            class="mptbm_taxi_category_btn_save">

                        <?php echo $is_edit
                            ? esc_html__( 'Update Category', 'ecab-taxi-booking-manager' )
                            : esc_html__( 'Save Category', 'ecab-taxi-booking-manager' );
                        ?>

                    </button>
                </div>

            </div>
            <?php

            return ob_get_clean();
        }

        public function mptbm_add_edit_taxi_category(){
            if (
                !isset($_POST['nonce']) ||
                !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mptbm_taxi_nonce')
            ) {
                wp_send_json_error(['message' => 'Security failed']);
            }

            $category_id = isset( $_POST['category_id'] ) ? sanitize_text_field( wp_unslash( $_POST['category_id'] ) ) : '';

            $categories = get_option('mptbm_taxi_categories', []);
            if (!is_array($categories)) {
                $categories = [];
            }

            $category = [];
            if( $category_id && !empty( $categories ) ){
                $indexed = array_column($categories, null, 'id');
                $category = $indexed[$category_id] ?? null;
            }


            $html = self::add_edit_category( $category );
            wp_send_json_success([
                'message' => 'Add Edit Popup',
                'add_edit_html'    => $html
            ]);

        }

        public function mptbm_get_all_categories(){
            if (
                !isset($_POST['nonce']) ||
                !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mptbm_taxi_nonce')
            ) {
                wp_send_json_error(['message' => 'Security failed']);
            }


            $categories = get_option('mptbm_taxi_categories', []);
            if (!is_array($categories)) {
                $categories = [];
            }

            $html = self::get_all_category( $categories );
            wp_send_json_success([
                'message' => 'Add Edit Popup',
                'all_cat_html'    => $html
            ]);

        }

        public static function get_all_category( $categories ){
            ob_start(); ?>
                <!-- Modal -->
                <div class="mptbm_all_categories_modal">

                    <div class="mptbm_all_categories_header">
                        <h3>All Categories</h3>
                        <span class="mptbm_all_categories_close" id="mptbm_all_categories_close">&times;</span>
                    </div>

                    <div class="mptbm_all_categories_wrapper">

                        <?php if ( ! empty( $categories ) ) : ?>

                            <?php foreach ( $categories as $cat ) : ?>

                                <div class="mptbm_all_categories_card">

                                    <div class="mptbm_all_categories_title">
                                        <?php echo esc_html( $cat['name'] ); ?>
                                    </div>

                                    <div class="mptbm_all_categories_actions">
                                        <button class="mptbm_all_categories_edit_btn"
                                                data-id="<?php echo esc_attr( $cat['id'] ); ?>">
                                            ✏️
                                        </button>

                                        <button class="mptbm_all_categories_delete_btn"
                                                data-id="<?php echo esc_attr( $cat['id'] ); ?>">
                                            🗑
                                        </button>
                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php else : ?>

                            <div class="mptbm_all_categories_empty">
                                No categories found
                            </div>

                        <?php endif; ?>

                    </div>

                </div>
            <?php
            return ob_get_clean();

        }
        public function mptbm_taxi_add_tag() {
            if (
                !isset($_POST['nonce']) ||
                !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mptbm_taxi_nonce')
            ) {
                wp_send_json_error(['message' => 'Security failed']);
            }
            $post_id = isset( $_POST['post_id'] ) ? intval($_POST['post_id']) : '';
            $tag = isset( $_POST['tag'] ) ?sanitize_text_field( wp_unslash( $_POST['tag'] ) ) : '';
            if (!$post_id || !$tag) {
                wp_send_json_error(['message' => 'Invalid data']);
            }
            $tags = get_post_meta($post_id, 'mptbm_taxi_tags', true);
            if (!is_array($tags)) {
                $tags = [];
            }
            if (!in_array($tag, $tags)) {
                $tags[] = $tag;
            }
            update_post_meta($post_id, 'mptbm_taxi_tags', $tags);
            wp_send_json_success([
                'message' => 'Tag saved successfully',
                'tags'    => $tags
            ]);
        }

        function mptbm_taxi_remove_tag() {
            if (
                !isset($_POST['nonce']) ||
                !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mptbm_taxi_nonce')
            ) {
                wp_send_json_error(['message' => 'Security failed']);
            }

            $post_id = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : '';
            $tag = isset( $_POST['tag'] ) ? sanitize_text_field( wp_unslash(  $_POST['tag'] ) ) : '';

            if (!$post_id || !$tag) {
                wp_send_json_error(['message' => 'Invalid data']);
            }
            $tags = get_post_meta($post_id, 'mptbm_taxi_tags', true);
            if (!is_array($tags)) {
                $tags = [];
            }
            $tags = array_values(array_filter($tags, function ($t) use ($tag) {
                return $t !== $tag;
            }));
            update_post_meta($post_id, 'mptbm_taxi_tags', $tags);
            wp_send_json_success([
                'message' => 'Tag removed',
                'tags' => $tags
            ]);
        }

    }

    new MPTBM_Right_Side_Content_Settings();
}

