<?php
/*
 * @Author 		rubelcuet10@gmail.com
 * Copyright: 	mage-people.com
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Rent_Custom_Editor')) {
    class MPTBM_Rent_Custom_Editor{
        public function __construct() {
            add_action('admin_menu', [$this, 'register_menu']);
            add_action('admin_post_save_mptbm_rent', [$this, 'save_post']);
            add_action('admin_init', [$this, 'redirect_default_editor']);
            add_action('admin_init', [$this, 'redirect_add_new']);


//            add_action('wp_ajax_save_mptbm_rent', [$this, 'save_mptbm_rent_callback']);

            add_action('save_post', [ $this, 'mptbm_save_taxi_data' ] );
        }

        function mptbm_save_taxi_data( $post_id ){

            if( $post_id ) {
                if (isset($_POST['mptbm_feature_image_id'])) {
                    $image_id = intval(sanitize_text_field($_POST['mptbm_feature_image_id']));
                    if ($image_id) {
                        set_post_thumbnail($post_id, $image_id);
                    } else {
                        delete_post_thumbnail($post_id);
                    }
                }

                $base_fare_pricing_display = isset($_POST['mptbm_display_taxi_base_fare_pricing']) ? sanitize_text_field( wp_unslash( $_POST['mptbm_display_taxi_base_fare_pricing'] ) ) : 'off';
                update_post_meta( $post_id, 'mptbm_display_taxi_base_fare_pricing', $base_fare_pricing_display );

                $base_location_pricing_display = isset($_POST['mptbm_display_taxi_base_location_pricing']) ? sanitize_text_field( wp_unslash( $_POST['mptbm_display_taxi_base_location_pricing'] ) ) : 'off';
                update_post_meta( $post_id, 'mptbm_display_taxi_base_location_pricing', $base_location_pricing_display );
            }

        }

        // 1. Register submenu page
        public function register_menu() {
            add_submenu_page(
                'edit.php?post_type=mptbm_rent',
                __('Edit Rent', 'ecab-taxi-booking-manager'),
                __('Edit Rent', 'ecab-taxi-booking-manager'),
                'manage_options',
                'mptbm-rent-edit',
                [$this, 'render_page']
            );
        }

        public function redirect_add_new() {

            global $pagenow;

            if (
                $pagenow === 'post-new.php' &&
                isset($_GET['post_type']) &&
                $_GET['post_type'] === 'mptbm_rent'
            ) {

                // Allow old editor
                if (isset($_GET['editor']) && $_GET['editor'] === 'old') {
                    return;
                }

                wp_redirect(
                    admin_url('admin.php?page=mptbm-rent-edit')
                );

                exit;
            }
        }


        public static function shortcode_description( $price_based ){
            if( $price_based === 'distance' || $price_based === 'duration' || $price_based === 'distance_duration' || $price_based === 'inclusive' ){
                $shortcode = 'dynamic';
            }else if( $price_based === 'fixed_hourly' ){
                $shortcode = 'fixed_hourly';
            }else if( $price_based === 'manual' ){
                $shortcode = 'manual';
            }else if( $price_based === 'fixed_distance' ){
                $shortcode = 'fixed_map';
            }else if( $price_based === 'fixed_zone' ){
                $shortcode = 'fixed_zone_pickup';
            }else{
                $shortcode = 'dynamic';
            }

            $title = ucwords(str_replace('_', ' ', $shortcode));
            ?>
            <div class="mptbm_shortcode_container">
                <!-- Header Section -->
                <div class="mptbm_shortcode_header">
                    <div class="mptbm_shortcode_header_left">
                        <div class="mptbm_shortcode_header_text">
                            <h3> <?php esc_html_e( 'Shortcode Usage Guide', 'ecab-taxi-booking-manager' ); ?></h3>
                            <p> <?php esc_html_e( 'Click to view the shortcode for Distance-based pricing', 'ecab-taxi-booking-manager' ); ?></p>
                        </div>
                    </div>
                    <div class="mptbm_shortcode_toggle">
                        <div class="text-xl text-gray-400 transition-transform" >▼</div>
                    </div>
                </div>

                <!-- Main Content Box -->
                <div class="mptbm_shortcode_body" style="display: none">

                    <!-- Primary Shortcode -->
                    <div class="mptbm_shortcode_section">
                        <h4 class="mptbm_shortcode_sub_title"><span id="mptbm_shortcode_title"><?php echo esc_attr( $title );?></span> <?php esc_html_e( 'Pricing Shortcode', 'ecab-taxi-booking-manager' ); ?></h4>
                        <div class="mptbm_shortcode_code_box mptbm_shortcode_primary_code" id="mptbm_shortcode_primary_code">
                            <code>[mptbm_booking price_based='<?php echo esc_attr( $shortcode );?>']</code>
                        </div>
                    </div>

                    <div class="mptbm_shortcode_divider"></div>

                    <!-- Optional Parameters -->
                    <div class="mptbm_shortcode_section">
                        <h4 class="mptbm_shortcode_sub_title">
                            <svg class="mptbm_shortcode_inline_icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                            <?php esc_html_e( 'Optional Parameters:', 'ecab-taxi-booking-manager' ); ?>
                        </h4>
                        <div class="mptbm_shortcode_grid">
                            <div class="mptbm_shortcode_param_item"><code>form='horizontal'</code> or <code>form='inline'</code></div>
                            <div class="mptbm_shortcode_param_item"><code>progressbar='yes'</code> or <code>progressbar='no'</code></div>
                            <div class="mptbm_shortcode_param_item"><code>map='yes'</code> or <code>map='no'</code></div>
                            <div class="mptbm_shortcode_param_item"><code>tabs='hourly,distance,manual'</code></div>
                        </div>
                    </div>

                    <!-- Example Usage -->
                    <div class="mptbm_shortcode_section">
                        <div class="mptbm_shortcode_example_wrapper">
                            <h4 class="mptbm_shortcode_sub_title">
                                <svg class="mptbm_shortcode_inline_icon" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2"><path d="M9 18h6m-6-4h6m-7.5 4a6 6 0 1 1 9 0"></path></svg>
                                <?php esc_html_e( 'Example Usage:', 'ecab-taxi-booking-manager' ); ?>
                            </h4>
                            <div class="mptbm_shortcode_code_box mptbm_shortcode_example_code" id="mptbm_shortcode_example_code">
                                <code>[mptbm_booking price_based='<?php echo esc_attr( $shortcode );?>' form='horizontal' progressbar='yes' map='yes']</code>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        <?php }
        public function render_page() {

            $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
            $title   = $post_id ? get_the_title($post_id) : 'New Rent';
            $pro_active = class_exists('MPTBM_Dependencies_Pro');
            $old_editor_url = admin_url(
                'post.php?post=' . $post_id . '&action=edit&editor=old'
            );
            ?>
            <div class="wrap mp_settings_area">


                <div id="mptbm_pro_popup" class="mptbm_pro_popup">
                    <div class="mptbm_pro_popup_content">
                        <span class="mptbm_pro_close_popup">&times;</span>
                        <h3><?php esc_html_e( 'Pro Feature', 'ecab-taxi-booking-manager' ); ?></h3>
                        <p>

                            <?php esc_html_e( 'This PRO feature unlocks advanced taxi pricing options, priority support,
                            and detailed analytics tools.', 'ecab-taxi-booking-manager' ); ?>
                        </p>
                        <a target="_blank" href="https://mage-people.com/product/wordpress-taxi-cab-booking-plugin-for-woocommerce/" class="mptbm_pro_download_btn">
                            <?php esc_html_e( 'Buy Download Pro Plugin', 'ecab-taxi-booking-manager' ); ?>
                        </a>
                    </div>
                </div>

                <form class="mptbm_rent_form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">

                    <input type="hidden" name="return_url" value="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
                    <input type="hidden" name="action" value="save_mptbm_rent">
                    <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">

                    <?php wp_nonce_field('save_mptbm_rent_nonce'); ?>

                    <!-- FIXED HEADER -->
                    <div class="mptbm_fixed_header">

                        <div class="">
                            <a class="mptbm-link" href="<?php echo admin_url('edit.php?post_type=mptbm_rent'); ?>">
                                <span class="dashicons dashicons-arrow-left-alt"></span>
                                <?php esc_html_e( 'Back to Transports', 'ecab-taxi-booking-manager' ); ?>
                            </a>
                        </div>

                        <div class="mptbm_header_left">
                            <h1 class="mptbm_page_title">
                                <?php echo esc_html($title); ?>
                            </h1>
                        </div>

                        <div class="mptbm_header_right">

                            <?php submit_button($post_id ? 'Update' : 'Publish', 'primary', '', false); ?>
                            <a href="<?php echo esc_url($old_editor_url); ?>" class="button">
                                <?php esc_html_e( 'Open classic Editor', 'ecab-taxi-booking-manager' ); ?>
                            </a>

                        </div>

                    </div>
                    <!-- SCROLLABLE CONTENT -->
                    <div class="mptbm_scroll_content" style="display: flex; flex-direction: row">
                        <div class="mptbm_taxi_wrapper">

                            <div class="mptbm_taxi_header_holder">
                                <?php self::taxi_content_tabs_set($post_id); ?>
                            </div>
                            <div class="mptbm_taxi_container_holder">
                                <div class="mptbm_taxi_content_container">
                                    <?php
                                    self::general_information_set( $post_id, $pro_active );
                                    self::pricing_settings( $post_id, $pro_active );
                                    self::date_configuration_set($post_id);
                                    ?>
                                </div>
                                <div class="mptbm_right_side_section">
                                    <?php
                                    do_action( 'mptbm_right_side_section', $post_id );
//                                    self::right_side_section( $post_id );
                                    ?>
                                </div>
                            </div>

                        </div>

                    </div>

                    <!-- FIXED FOOTER -->
                    <div class="mptbm_fixed_footer">

                        <div class="mptbm_footer_right">

                            <div class="mptbm_taxi_footer">

                                <button type="button" class="mptbm_taxi_btn_prev">
                                    <?php esc_html_e( '← Previous', 'ecab-taxi-booking-manager' ); ?>
                                </button>

                                <span class="mptbm_taxi_step_counter">
                                    <?php esc_html_e( 'Step 1 of 3', 'ecab-taxi-booking-manager' ); ?>
                                </span>

                                <button type="button" class="mptbm_taxi_btn_next">
                                    <?php esc_html_e( 'Next →', 'ecab-taxi-booking-manager' ); ?>
                                </button>

                            </div>

                        </div>

                    </div>
                </form>
            </div>
            <?php
        }

        public static function general_data_configuration( $max_passenger, $max_bag, $max_hand_luggage, $extra_info ){ ?>
            <div class="mptbm_rent_editor_wrapper">

                <!-- Header -->
                <div class="mptbm_rent_editor_header">

                    <h2 class="mptbm_rent_editor_title">
                        <?php esc_html_e( 'General Data Configuration', 'ecab-taxi-booking-manager' ); ?>
                    </h2>

                    <p class="mptbm_rent_editor_subtitle">
                        <?php esc_html_e( 'Here you can configure general data', 'ecab-taxi-booking-manager' ); ?>
                    </p>

                </div>


                <!-- Body -->
                <div class="mptbm_rent_editor_body" style="display: grid;  grid-template-columns: 1fr 1fr; gap:20px">

                    <div class="mptbm_rent_field_group">
                        <label><?php esc_html_e( 'Maximum Passenger', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Filters services by the maximum number of passengers allowed', 'ecab-taxi-booking-manager' ); ?></p>
                        <input name="mptbm_maximum_passenger" type="text" value="<?php echo esc_attr( $max_passenger );?>" placeholder="<?php esc_html_e( 'EX:4', 'ecab-taxi-booking-manager' ); ?>">
                    </div>
                    <div class="mptbm_rent_field_group">
                        <label><?php esc_html_e( 'Maximum Bag', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Filters services by the maximum number of bags allowed', 'ecab-taxi-booking-manager' ); ?></p>
                        <input name="mptbm_maximum_bag" type="text" value="<?php echo esc_attr( $max_bag );?>" placeholder="<?php esc_html_e( 'EX:4', 'ecab-taxi-booking-manager' ); ?>">
                    </div>
                    <div class="mptbm_rent_field_group">
                        <label><?php esc_html_e( 'Maximum hand luggage', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Filters services by the maximum number of hand luggage allowed', 'ecab-taxi-booking-manager' ); ?></p>
                        <input name="mptbm_maximum_hand_luggage" type="text" value="<?php echo esc_attr( $max_hand_luggage );?>" placeholder="<?php esc_html_e( 'EX:2', 'ecab-taxi-booking-manager' ); ?>">
                    </div>
                    <div class="mptbm_rent_field_group">
                        <label>Extra Info</label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Add any additional information about this vehicle that you want to display to customers', 'ecab-taxi-booking-manager' ); ?></p>
                        <textarea name="mptbm_extra_info" rows="4" placeholder="Enter additional information about this vehicle..."><?php echo esc_html( $extra_info );?></textarea>
                    </div>

                </div>

            </div>
        <?php }
        public static function taxi_title_description_set( $post_id ){ ?>
            <div class="mptbm_rent_editor_wrapper">

                <!-- Header -->
                <div class="mptbm_rent_editor_header">

                    <h2 class="mptbm_rent_editor_title">
                        <?php esc_html_e( 'Basic Information', 'ecab-taxi-booking-manager' ); ?>
                    </h2>

                    <p class="mptbm_rent_editor_subtitle">
                        <?php esc_html_e( 'The core details of your rent.', 'ecab-taxi-booking-manager' ); ?>
                    </p>

                </div>


                <!-- Body -->
                <div class="mptbm_rent_editor_body">
                    <!-- Title -->
                    <div class="mptbm_rent_field_group">

                        <label class="mptbm_rent_label" for="mptbm_rent_title">
                            <?php esc_html_e( 'Rent Title', 'ecab-taxi-booking-manager' ); ?> <span class="mptbm_rent_required">*</span>
                        </label>

                        <input
                                type="text"
                                id="mptbm_rent_title"
                                name="post_title"
                                class="mptbm_rent_input"
                                value="<?php echo esc_attr(get_the_title($post_id)); ?>"
                                placeholder="Enter rent title"
                                required
                        >

                    </div>
                    <!-- Description -->
                    <div class="mptbm_rent_field_group" style="display: none">

                        <label class="mptbm_rent_label">
                            <?php esc_html_e( 'Description', 'ecab-taxi-booking-manager' ); ?>
                        </label>

                        <div class="mptbm_rent_editor_area">

                            <?php
                            $content = $post_id ? get_post_field('post_content', $post_id) : '';

                            wp_editor(
                                $content,
                                'mptbm_rent_description',
                                array(
                                    'textarea_name' => 'post_content',
                                    'media_buttons' => true,
                                    'textarea_rows' => 10,
                                    'teeny'         => false,
                                    'quicktags'     => true,
                                )
                            );
                            ?>

                        </div>

                    </div>
                </div>

            </div>
        <?php }


        public static function taxi_content_tabs_set( $post_id ) {
            $post_id = absint( $post_id );
            ?>
            <div class="mptbm_taxi_stepper">

                <!-- STEP 1 -->
                <div class="mptbm_taxi_step mptbm_taxi_active"
                     data-step="<?php echo esc_attr(1); ?>">

                    <div class="mptbm_taxi_icon">
                        1
                    </div>

                    <div class="mptbm_taxi_label">
                        <?php esc_html_e( 'General Information', 'ecab-taxi-booking-manager' ); ?>
                    </div>

                </div>

                <div class="mptbm_taxi_line"></div>

                <!-- STEP 2 -->
                <div class="mptbm_taxi_step"
                     data-step="<?php echo esc_attr(2); ?>">

                    <div class="mptbm_taxi_icon">
                        2
                    </div>

                    <div class="mptbm_taxi_label">
                        <?php esc_html_e( 'Pricing Configuration', 'ecab-taxi-booking-manager' ); ?>
                    </div>

                </div>

                <div class="mptbm_taxi_line"></div>

                <!-- STEP 3 -->
                <div class="mptbm_taxi_step"
                     data-step="<?php echo esc_attr(3); ?>">

                    <div class="mptbm_taxi_icon">
                        3
                    </div>

                    <div class="mptbm_taxi_label">
                        <?php esc_html_e( 'Operational Date Time', 'ecab-taxi-booking-manager' ); ?>
                    </div>

                </div>

            </div>

            <?php
        }

        public static function general_information_set( $post_id, $pro_active ){

            $price_display_type = MP_Global_Function::get_post_info($post_id, 'mptbm_price_display_type', 'normal');
            $custom_price_message = MP_Global_Function::get_post_info($post_id, 'mptbm_custom_price_message', '');


            $max_passenger = MP_Global_Function::get_post_info($post_id, 'mptbm_maximum_passenger');
            $max_bag = MP_Global_Function::get_post_info($post_id, 'mptbm_maximum_bag');
            $max_hand_luggage = MP_Global_Function::get_post_info($post_id, 'mptbm_maximum_hand_luggage');

            $extra_info = MP_Global_Function::get_post_info($post_id, 'mptbm_extra_info', '');
            $all_features = MP_Global_Function::get_post_info($post_id, 'mptbm_features');
            if (!$all_features) {
                $all_features = array(
                    array(
                        'label' => esc_html__('Name', 'ecab-taxi-booking-manager'),
                        'icon' => 'fas fa-car-side',
                        'image' => '',
                        'text' => ''
                    ),
                    array(
                        'label' => esc_html__('Model', 'ecab-taxi-booking-manager'),
                        'icon' => 'fas fa-car',
                        'image' => '',
                        'text' => ''
                    ),
                    array(
                        'label' => esc_html__('Engine', 'ecab-taxi-booking-manager'),
                        'icon' => 'fas fa-cogs',
                        'image' => '',
                        'text' => ''
                    ),
                    array(
                        'label' => esc_html__('Fuel Type', 'ecab-taxi-booking-manager'),
                        'icon' => 'fas fa-gas-pump',
                        'image' => '',
                        'text' => ''
                    )
                );
            }
            ?>
            <div class="mptbm_taxi_container" data-step="1" >
                <?php wp_nonce_field('mptbm_transportation_type_nonce', 'mptbm_transportation_type_nonce');

                self::taxi_title_description_set( $post_id );
                
                self::general_data_configuration( $max_passenger, $max_bag, $max_hand_luggage, $extra_info );

                ?>

                <div class="mptbm_rent_editor_wrapper">
                    <div class="mptbm_rent_editor_header" style="display: flex; justify-content: space-between">
                        <div class="mptbm_taxi_toggle_info">
                            <div class="mptbm_taxi_toggle_text">
                                <strong><?php esc_html_e( 'Price Display Settings', 'ecab-taxi-booking-manager' ); ?></strong>
                                <p><?php esc_html_e( 'Configure how fares are shown to customers', 'ecab-taxi-booking-manager' ); ?></p>
                            </div>
                        </div>
                        <select class="formControl" name="mptbm_price_display_type" id="mptbm_price_display_type" data-collapse-target="">
                            <option value="normal" <?php selected($price_display_type, 'normal'); ?>><?php esc_html_e('Normal Price', 'ecab-taxi-booking-manager'); ?></option>
                            <option value="zero" <?php selected($price_display_type, 'zero'); ?>><?php esc_html_e('Show as Zero (0.00)', 'ecab-taxi-booking-manager'); ?></option>
                            <option value="custom_message" <?php selected($price_display_type, 'custom_message'); ?>><?php esc_html_e('Show Custom Message', 'ecab-taxi-booking-manager'); ?></option>
                        </select>
                    </div>
                    <div class="mptbm_taxi_toggle_header" id="mptbm_custom_message_show" style="display: <?php echo esc_attr($price_display_type == 'custom_message' ? 'block' : 'none'); ?>; border: unset" >
                        <div class="mptbm_custom_message_label">
                            <div class="mptbm_custom_message_title_holder">
                                <h6><?php esc_html_e('Custom Price Message', 'ecab-taxi-booking-manager'); ?></h6>
                                <span class="desc"><?php esc_html_e('Message to display instead of price (e.g. "Price pending confirmation")', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                            <textarea class="mptbm_custom_message_input" name="mptbm_custom_price_message" rows="3"><?php echo esc_textarea($custom_price_message); ?></textarea>
                        </div>
                    </div>
                </div>

                <?php

                if (class_exists('MPTBM_Plugin_Pro')) {
                    self::taxi_inventory_manages($post_id, $all_features);
                }

                self::taxi_feature_add_remove( $post_id, $all_features );

                ?>

            </div>
        <?php }

        public static function enable_base_location_charges( $post_id ){
            $base_price_location = MP_Global_Function::get_post_info($post_id, 'mptbm_base_price_location', '');
            $base_price_km = MP_Global_Function::get_post_info($post_id, 'mptbm_base_price_km', '');
            $base_price_hour = MP_Global_Function::get_post_info($post_id, 'mptbm_base_price_hour', '');
            $base_min_threshold = MP_Global_Function::get_post_info($post_id, 'mptbm_base_min_threshold', '');
            $charge_base_pickup = MP_Global_Function::get_post_info($post_id, 'mptbm_charge_base_pickup', 'no');
            $charge_base_dropoff = MP_Global_Function::get_post_info($post_id, 'mptbm_charge_base_dropoff', 'no');

            $locations = get_terms(array(
                'taxonomy' => 'locations',
                'hide_empty' => false,
            ));

            $display            = MP_Global_Function::get_post_info( $post_id, 'mptbm_display_taxi_base_location_pricing', 'off' );
            $active             = $display == 'off' ? 'none' : 'block';
            $checked            = $display == 'off' ? '' : 'checked';
            ?>

            <div class="mptbm_taxi_toggle_container" id="mptbm_taxi_base_location_toggle_container">
                <div class="mptbm_taxi_ex_service_header">
                    <div class="mptbm_taxi_ex_service_title_group">
                        <h2 class="mptbm_taxi_ex_service_main_title"><?php esc_html_e( 'Enable Base Location Charges', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p><?php esc_html_e( 'Apply additional charges based on distance between taxi base location and pickup/drop-off points.', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                    <div class="mptbm_taxi_ex_service_toggle_wrapper">
                        <label class="mptbm_taxi_ex_service_switch">
                            <input type="checkbox" id="mptbm_display_taxi_base_location_pricing" name="mptbm_display_taxi_base_location_pricing"  class="mptbm_taxi_toggle_trigger" <?php echo esc_attr( $checked );?>>
                            <span class="mptbm_taxi_slider"></span>
                        </label>
                        <span class="mptbm_taxi_ex_service_toggle_label mptbm_display_taxi_base_location_pricing_level"><?php esc_html_e( 'ON', 'ecab-taxi-booking-manager' ); ?></span>
                    </div>
                </div>

                <div class="mptbm_taxi_ex_service_body" id="mptbm_taxi_base_location_price_body" style="display: <?php echo esc_attr( $active );?>">
                    <div class="mptbm_taxi_base_price_row">
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Base Price Location', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Select the base location for price calculation', 'ecab-taxi-booking-manager' ); ?></p>
                            <select class="formControl" name="mptbm_base_price_location">
                                <option value=""><?php esc_html_e('Select Location', 'ecab-taxi-booking-manager'); ?></option>
                                <?php if (!empty($locations) && !is_wp_error($locations)) : ?>
                                    <?php foreach ($locations as $location) :
                                        $geo = get_term_meta($location->term_id, 'mptbm_geo_location', true);
                                        if (empty($geo)) {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?php echo esc_attr($location->term_id); ?>" <?php selected($base_price_location, $location->term_id); ?>>
                                            <?php echo esc_html($location->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>


                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Price per KM', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Enter the price per kilometer from base location', 'ecab-taxi-booking-manager' ); ?></p>
                            <input
                                    name="mptbm_base_price_km"
                                    type="number"
                                    min="0"
                                    step="0.1"
                                    value="<?php echo esc_attr( $base_price_km ?: '0' ); ?>"
                                    placeholder="1.5"
                            >
                        </div>
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Price per Hour', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Enter the price per hour from base location', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_base_price_hour" type="number" value="<?php echo esc_attr( $base_price_hour );?>" placeholder="10 ">
                        </div>
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Minimum Threshold (Distance)', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Distance free of charge from base price location', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_base_min_threshold" type="number" value="<?php echo esc_attr( $base_min_threshold );?>" placeholder="1">
                        </div>
                    </div>

                    <div class=" mptbm_taxi_toggle_box">
                        <div class="mptbm_taxi_toggle_header">
                            <div class="mptbm_taxi_toggle_info">
                                <div class="mptbm_taxi_toggle_text">
                                    <strong><?php esc_html_e( 'Charge for Base to Pickup?', 'ecab-taxi-booking-manager' ); ?></strong>
                                    <p><?php esc_html_e( 'Enable to charge for distance/time from base location to pickup location', 'ecab-taxi-booking-manager' ); ?></p>
                                </div>
                            </div>

                            <div class="mptbm_taxi_ex_service_toggle_wrapper">
                                <label class="mptbm_taxi_ex_service_switch">
                                    <input name="mptbm_charge_base_pickup" type="checkbox" class="mptbm_taxi_toggle_trigger" <?php echo ($charge_base_pickup == 'yes') ? 'checked' : ''; ?>>
                                    <span class="mptbm_taxi_slider"></span>
                                </label>
                                <?php if( $charge_base_pickup == 'yes' ){?>
                                    <span class="mptbm_taxi_status_badge"><?php esc_html_e( 'ON', 'ecab-taxi-booking-manager' ); ?></span>
                                <?php }else{?>
                                    <span class="mptbm_taxi_status_badge mptbm_taxi_off"><?php esc_html_e( 'OFF', 'ecab-taxi-booking-manager' ); ?></span>
                                <?php }?>
                            </div>


                        </div>
                    </div>

                    <div class=" mptbm_taxi_toggle_box">
                        <div class="mptbm_taxi_toggle_header">
                            <div class="mptbm_taxi_toggle_info">
                                <div class="mptbm_taxi_toggle_text">
                                    <strong><?php esc_html_e( 'Charge for Base to Drop-off?', 'ecab-taxi-booking-manager' ); ?></strong>
                                    <p><?php esc_html_e( 'Enable to charge for distance/time from drop-off location back to base location', 'ecab-taxi-booking-manager' ); ?></p>
                                </div>
                            </div>
                            <div class="mptbm_taxi_ex_service_toggle_wrapper">
                                <label class="mptbm_taxi_ex_service_switch">
                                <input name="mptbm_charge_base_dropoff" type="checkbox" class="mptbm_taxi_toggle_trigger" <?php echo ($charge_base_dropoff == 'yes') ? 'checked' : ''; ?>>
                                    <span class="mptbm_taxi_slider"></span>
                                </label>
                            <?php if( $charge_base_pickup == 'yes' ){?>
                                <span class="mptbm_taxi_status_badge"><?php esc_html_e( 'ON', 'ecab-taxi-booking-manager' ); ?></span>
                            <?php }else{?>
                                <span class="mptbm_taxi_status_badge mptbm_taxi_off"><?php esc_html_e( 'OFF', 'ecab-taxi-booking-manager' ); ?></span>
                            <?php }?>
                            </div>
                        </div>
                    </div>

                </div>

            </div>





        <?php }
        public static function features_item($features = array()) {
                $label = array_key_exists('label', $features) ? $features['label'] : '';
                $text = array_key_exists('text', $features) ? $features['text'] : '';
                $icon = array_key_exists('icon', $features) ? $features['icon'] : '';
                $image = array_key_exists('image', $features) ? $features['image'] : '';
                ?>

                <div id="mptbm_taxi_feature_list">
                    <div class="mptbm_taxi_feature_row">
                        <div class="mptbm_taxi_feature_icon_box">
                            <i class="fas fa-car"></i>
                            <div class="mptbm_taxi_feature_remove_icon"><i class="fas fa-times"></i></div>
                        </div>
                        <input type="text" class="mptbm_taxi_feature_input" name="mptbm_features_label[]" value="<?php echo esc_attr($label); ?>"/>
                        <input type="text" class="mptbm_taxi_feature_input" name="mptbm_features_text[]" value="<?php echo esc_attr($text); ?>"/>
                        <div class="mptbm_taxi_feature_actions">
                            <button class="mptbm_taxi_feature_btn_icon mptbm_taxi_feature_btn_del">🗑️</button>
                            <button class="mptbm_taxi_feature_btn_icon mptbm_taxi_feature_btn_move">✥</button>
                        </div>
                    </div>
                </div>
                <?php
            }
        public static function taxi_feature_add_remove( $post_id, $all_features ){
            $display_features = MP_Global_Function::get_post_info($post_id, 'display_mptbm_features', 'on');
            $features_active = $display_features == 'off' ? 'Off' : 'On';
            $display = $display_features == 'off' ? 'none' : 'block';
            $features_checked = $display_features == 'off' ? '' : 'checked';
            ?>
            <div class="mptbm_taxi_feature_container">
                <div class="mptbm_taxi_feature_header">
                    <div class="mptbm_taxi_feature_title_area">
                        <h2><?php esc_html_e( 'Vehicle Features', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p><?php esc_html_e( 'Configure additional vehicle features and specifications.', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                    <div class="mptbm_taxi_feature_switch">

                        <span class="mptbm_taxi_feature_switch_text"><?php echo esc_attr( $features_active );?></span>
                        <label class="mptbm_taxi_feature_toggle">
                            <input type="checkbox" id="mptbm_taxi_feature_master_toggle" name="display_mptbm_features" <?php echo esc_attr( $features_checked );?>>
                            <span class="mptbm_taxi_feature_slider"></span>
                        </label>
                    </div>
                </div>

                <div class="mptbm_taxi_feature_body" style="display: <?php echo esc_attr( $display );?>">
                    <div class="mptbm_taxi_feature_labels">
                        <div><?php esc_html_e( 'Icon/Image', 'ecab-taxi-booking-manager' ); ?></div>
                        <div><?php esc_html_e( 'Label', 'ecab-taxi-booking-manager' ); ?></div>
                        <div><?php esc_html_e( 'Text', 'ecab-taxi-booking-manager' ); ?></div>
                        <div><?php esc_html_e( 'Action', 'ecab-taxi-booking-manager' ); ?></div>
                    </div>

                    <div id="mptbm_taxi_feature_list">
                        <?php

                        if (is_array($all_features) && sizeof($all_features) > 0) {
                            foreach ($all_features as $features) {
                                self::features_item($features);
                            }
                        } else {
                            self::features_item();
                        }
                        ?>
                    </div>

                    <div class="mptbm_taxi_feature_footer">
                        <button class="mptbm_taxi_feature_add_btn" id="mptbm_taxi_feature_add_row">
                            <i class="fas fa-plus"></i> <?php esc_html_e( 'Add New Item', 'ecab-taxi-booking-manager' ); ?>
                        </button>
                    </div>
                </div>


            </div>
        <?php }
        public static function taxi_inventory_manages( $post_id, $all_features ){
            $display_features = MP_Global_Function::get_post_info($post_id, 'mptbm_enable_inventory', 'no');
            $features_active = $display_features == 'no' ? 'Off' : 'On';
            $display = $display_features == 'no' ? 'none' : 'block';
            $features_checked = $display_features == 'no' ? '' : 'checked';
            ?>
            <div class="mptbm_taxi_feature_container">
                <div class="mptbm_taxi_feature_header">
                    <div class="mptbm_taxi_feature_title_area">
                        <h2><?php esc_html_e( 'Enable Inventory', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p><?php esc_html_e( 'Enable or disable inventory management for this vehicle', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                    <div class="mptbm_taxi_feature_switch">

                        <span class="mptbm_taxi_inventory_switch_text"><?php echo esc_attr( $features_active );?></span>
                        <label class="mptbm_taxi_feature_toggle">
                            <input type="checkbox" id="mptbm_enable_inventory" name="mptbm_enable_inventory" <?php echo esc_attr( $features_checked );?>>
                            <span class="mptbm_taxi_feature_slider"></span>
                        </label>
                    </div>
                </div>

                <div class="mptbm_taxi_inventory_manage_body" style="display: <?php echo esc_attr( $display );?>">
                    <div class="mptbm_taxi_inventory_settings_card">
                            <div class="mptbm_taxi_inventory_form_row">
                                <div class="mptbm_taxi_inventory_field_info">
                                    <label for="vehicle-quantity" class="mptbm_taxi_inventory_field_title"><?php esc_html_e( 'Quantity', 'ecab-taxi-booking-manager' ); ?></label>
                                    <p class="mptbm_taxi_inventory_field_description"><?php esc_html_e( 'Enter the quantity of vehicles available', 'ecab-taxi-booking-manager' ); ?></p>
                                </div>
                                <div class="mptbm_taxi_inventory_field_input_wrapper">
                                    <input
                                            type="number"
                                            id="vehicle-quantity"
                                            name="mptbm_quantity"
                                            min="1"
                                            value="<?php echo esc_attr(MP_Global_Function::get_post_info($post_id, 'mptbm_quantity', 1)); ?>"
                                            class="mptbm_taxi_inventory_styled_input"
                                            placeholder="<?php esc_html_e('EX:5', 'ecab-taxi-booking-manager'); ?>">
                                </div>
                            </div>

                            <div class="mptbm_taxi_inventory_form_row">
                                <div class="mptbm_taxi_inventory_field_info">
                                    <label for="interval-time" class="mptbm_taxi_inventory_field_title"><?php esc_html_e( 'Transport Booking Interval Time (minutes)', 'ecab-taxi-booking-manager' ); ?></label>
                                    <p class="mptbm_taxi_inventory_field_description"><?php esc_html_e( 'Set the interval time between bookings in minutes', 'ecab-taxi-booking-manager' ); ?></p>
                                </div>
                                <div class="mptbm_taxi_inventory_field_input_wrapper">
                                    <input type="number"
                                           id="interval-time"
                                           name="mptbm_booking_interval_time"
                                           min="0"
                                           value="<?php echo esc_attr(MP_Global_Function::get_post_info($post_id, 'mptbm_booking_interval_time', 0)); ?>"
                                           class="mptbm_taxi_inventory_styled_input"
                                           placeholder="<?php esc_html_e('EX:30', 'ecab-taxi-booking-manager'); ?>"
                                    >
                                </div>
                            </div>
                    </div>
                </div>


            </div>
        <?php }
        public static function date_configuration_set( $post_id ){ ?>
            <div class="mptbm_taxi_container " data-step="3" style="display: none">
                <?php
                do_action( 'mptbm_date_and_advanced_settings', $post_id );
                ?>
            </div>
        <?php }
        public static function extra_service_display( $post_id ){

            $display            = MP_Global_Function::get_post_info( $post_id, 'display_mptbm_extra_services', 'on' );
            $service_id         = (int)get_post_meta( $post_id, 'mptbm_extra_services_id', true);
            $active             = $display == 'off' ? 'none' : 'block';
            $checked            = $display == 'off' ? '' : 'checked';
            $all_ex_services_id = MPTBM_Query::query_post_id( 'mptbm_extra_services' );
            ?>
            <div class="mptbm_taxi_ex_service_container">
                <div class="mptbm_taxi_ex_service_header">
                    <div class="mptbm_taxi_ex_service_title_group">
                        <h2 class="mptbm_taxi_ex_service_main_title"><?php esc_html_e( 'Extra Service', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p class="mptbm_taxi_ex_service_subtitle"><?php esc_html_e( 'Manage optional services and their pricing for trips.', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                    <div class="mptbm_taxi_ex_service_toggle_wrapper">
                        <label class="mptbm_taxi_ex_service_switch">
                            <input type="checkbox" id="mptbm_taxi_ex_service_master_toggle" name="display_mptbm_extra_services" <?php echo esc_attr($checked); ?>>
                            <span class="mptbm_taxi_ex_service_slider"></span>
                        </label>
                        <span class="mptbm_taxi_ex_service_toggle_label"><?php esc_html_e( 'ON', 'ecab-taxi-booking-manager' ); ?></span>
                    </div>
                </div>

                <div class="mptbm_taxi_ex_service_body" id="mptbm_taxi_ex_service_body" style="display: <?php echo esc_attr( $active );?>">
                    <div class="mptbm_taxi_ex_service_filter_row">
                        <label><?php esc_html_e( 'Select extra option:', 'ecab-taxi-booking-manager' ); ?></label>
                        <select class="formControl" id="mptbm_extra_services_id" name="mptbm_extra_services_id">
                            <option value=""><?php esc_html_e( 'Select extra option', 'ecab-taxi-booking-manager' ); ?></option>
                            <option value="<?php echo esc_attr( $post_id ); ?>" <?php echo esc_attr( $service_id == $post_id ? 'selected' : '' ); ?>><?php esc_html_e( 'Custom', 'ecab-taxi-booking-manager' ); ?></option>
                            <?php if ( sizeof( $all_ex_services_id ) > 0 ) { ?>
                                <?php foreach ( $all_ex_services_id as $ex_services_id ) { ?>
                                    <option value="<?php echo esc_attr( $ex_services_id ); ?>" <?php echo esc_attr( $service_id == $ex_services_id ? 'selected' : '' ); ?>><?php echo esc_html(get_the_title( $ex_services_id )); ?></option>
                                <?php } ?>
                            <?php } ?>
                        </select>
                    </div>

                    <table class="mptbm_taxi_ex_service_table">
                        <thead>
                        <tr>
                            <th><?php esc_html_e( 'Icon', 'ecab-taxi-booking-manager' ); ?></th>
                            <th><?php esc_html_e( 'Name', 'ecab-taxi-booking-manager' ); ?></th>
                            <th><?php esc_html_e( 'Description', 'ecab-taxi-booking-manager' ); ?></th>
                            <th><?php esc_html_e( 'Price ($)', 'ecab-taxi-booking-manager' ); ?></th>
                            <th><?php esc_html_e( 'Qty Box Type', 'ecab-taxi-booking-manager' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'ecab-taxi-booking-manager' ); ?></th>
                        </tr>
                        </thead>
                        <tbody id="mptbm_taxi_ex_service_tbody">
                        <?php
                            self::extra_service_item( $post_id, $service_id );
                        ?>
                        </tbody>
                    </table>

                    <div class="mptbm_taxi_ex_service_footer">
                        <button id="mptbm_taxi_ex_service_add_btn" class="mptbm_taxi_ex_service_add_btn">+ <?php esc_html_e( 'Add New Service', 'ecab-taxi-booking-manager' ); ?></button>
                    </div>
                </div>
            </div>
        <?php }

        public static function extra_service_item( $post_id, $service_id ) {

            if( $service_id && $service_id !== $post_id ){
                $extra_services = MP_Global_Function::get_post_info( $service_id, 'mptbm_extra_service_infos', array() );
            }else{
                $extra_services = MP_Global_Function::get_post_info( $post_id, 'mptbm_extra_service_infos', array() );
            }

            if ( $extra_services && is_array( $extra_services ) && sizeof( $extra_services ) > 0 ) {
                foreach ( $extra_services as $field ) {


    //        $field         = $field ?: array();
            $service_icon  = array_key_exists( 'service_icon', $field ) ? $field['service_icon'] : '';
            $service_image = array_key_exists( 'service_image', $field ) ? $field['service_image'] : '';
            $service_name  = array_key_exists( 'service_name', $field ) ? $field['service_name'] : '';
            $service_price = array_key_exists( 'service_price', $field ) ? $field['service_price'] : '';
            $input_type    = array_key_exists( 'service_qty_type', $field ) ? $field['service_qty_type'] : 'inputbox';
            $description   = array_key_exists( 'extra_service_description', $field ) ? $field['extra_service_description'] : '';
            $icon          = $image = "";

            // Handle service_icon (for backward compatibility)
            if ( $service_icon ) {
                if ( preg_match( '/\s/', $service_icon ) ) {
                    $icon = $service_icon;
                } else {
                    $image = $service_icon;
                }
            }

            // Handle separate service_image field
            if ( $service_image ) {
                $image = $service_image;
            }
            ?>
            <tr class="mptbm_taxi_ex_service_row">
                <td>
                    <div class="mptbm_taxi_ex_service_icon_box">
                        <span class="mptbm_taxi_ex_service_icon_placeholder">😊</span>
                        <span class="mptbm_taxi_ex_service_remove_icon">×</span>
                    </div>
                </td>
                <td>
                    <input type="text" name="service_name[]" class="mptbm_taxi_ex_service_input" value="<?php echo esc_attr( $service_name ); ?>">
                </td>
                <td>
                    <textarea class="mptbm_taxi_ex_service_select" name="extra_service_description[]"><?php echo esc_html( $description ); ?></textarea>
                </td>
                <td><input
                    type="number" class="mptbm_taxi_ex_service_input mptbm_center"
                    step="0.01"
                    min="0"
                    name="service_price[]"
                    placeholder="<?php esc_attr_e( 'EX: 10.50', 'ecab-taxi-booking-manager' ); ?>"
                    value="<?php echo esc_attr( $service_price ); ?>"
                    ></td>
                <td>
                    <select name="service_qty_type[]" class='mptbm_taxi_ex_service_select mideum'>
                        <option value="inputbox" <?php echo esc_attr( $input_type == 'inputbox' ? 'selected' : '' ); ?>><?php esc_html_e( 'Input Box', 'ecab-taxi-booking-manager' ); ?></option>
                        <option value="dropdown" <?php echo esc_attr( $input_type == 'dropdown' ? 'selected' : '' ); ?>><?php esc_html_e( 'Dropdown List', 'ecab-taxi-booking-manager' ); ?></option>
                    </select>
                </td>
                <td class="mptbm_taxi_ex_service_actions">
                    <button class="mptbm_taxi_ex_service_btn_del">🗑️</button>
                    <button class="mptbm_taxi_ex_service_btn_drag">✥</button>
                </td>
            </tr>
            <?php
                }
            }
        }

        public static function initial_base_pricing( $post_id ){

            $initial_price = MP_Global_Function::get_post_info($post_id, 'mptbm_initial_price');
            $min_price = MP_Global_Function::get_post_info($post_id, 'mptbm_min_price');
            $return_min_price = MP_Global_Function::get_post_info($post_id, 'mptbm_min_price_return');
            $return_discount = MP_Global_Function::get_post_info($post_id, 'mptbm_return_discount');

            $waiting_time_check = MPTBM_Function::get_general_settings('taxi_waiting_time', 'enable');
            $waiting_price = MP_Global_Function::get_post_info($post_id, 'mptbm_waiting_price');

            $display            = MP_Global_Function::get_post_info( $post_id, 'mptbm_display_taxi_base_fare_pricing', 'off' );
            $active             = $display == 'off' ? 'none' : 'block';
            $checked            = $display == 'off' ? '' : 'checked';
            ?>



            <div class="mptbm_rent_editor_wrapper" id="mptbm_taxi_base_fare_toggle_container">
                <div class="mptbm_taxi_ex_service_header">
                    <div class="mptbm_taxi_ex_service_title_group">
                        <h2 class="mptbm_taxi_ex_service_main_title"><?php esc_html_e( 'Base Fare Settings', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p><?php esc_html_e( 'Starting fare added at trip start regardless of distance. Toggle off to remove the base charge entirely.', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                    <div class="mptbm_taxi_ex_service_toggle_wrapper">
                        <label class="mptbm_taxi_ex_service_switch">
                            <input type="checkbox" id="mptbm_display_taxi_base_fare_pricing" name="mptbm_display_taxi_base_fare_pricing"  class="mptbm_taxi_toggle_trigger" <?php echo esc_attr( $checked );?>>
                            <span class="mptbm_taxi_slider"></span>
                        </label>
                        <span class="mptbm_taxi_ex_service_toggle_label mptbm_display_taxi_base_fare_pricing_level"><?php esc_html_e( 'ON', 'ecab-taxi-booking-manager' ); ?></span>
                    </div>
                </div>

                <div class="mptbm_taxi_ex_service_body" id="mptbm_taxi_base_price_body" style="display: <?php echo esc_attr( $active );?>">
                    <div class="mptbm_taxi_base_price_row">
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Initial Price', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Starting fare added at trip start regardless of distance', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_initial_price" type="text"  value="<?php echo esc_attr( $initial_price );?>">
                        </div>
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Minimum Price', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Floor fare applied when calculated price is lower', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_min_price" type="text"  value="<?php echo esc_attr( $min_price );?>">
                        </div>
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Return Minimum Price', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Minimum fare applied on return trips', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_min_price_return" type="text" value="<?php echo esc_attr( $return_min_price );?>" placeholder="<?php esc_html_e( 'e.g., 40 - Min fare for return', 'ecab-taxi-booking-manager' ); ?>">
                        </div>
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Return Discount', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Discount applied to return trips — fixed or %', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_return_discount" type="text" value="<?php echo esc_attr( $return_discount );?>" placeholder="<?php esc_html_e( 'e.g., 10 - Discount amount or %', 'ecab-taxi-booking-manager' ); ?>">
                        </div>
                        <?php if ($waiting_time_check == 'enable') { ?>
                            <div class="mptbm_taxi_field">
                                <label><?php esc_html_e( 'Waiting Time Price/Hour', 'ecab-taxi-booking-manager' ); ?></label>
                                <p class="mptbm_taxi_help"><?php esc_html_e( 'Specifies the price charged per hour for waiting time', 'ecab-taxi-booking-manager' ); ?></p>
                                <input name="mptbm_waiting_price" type="text" value="<?php echo esc_attr( $waiting_price );?>" placeholder="<?php esc_html_e( 'EX:10', 'ecab-taxi-booking-manager' ); ?>">
                            </div>
                        <?php }?>
                    </div>
                </div>
            </div>
        <?php }
        public static function pricing_settings( $post_id, $pro_active ){

            $price_based = MP_Global_Function::get_post_info($post_id, 'mptbm_price_based');
            if( empty( $price_based ) ){
                $price_based = 'inclusive';
            }
            $distance_price = MP_Global_Function::get_post_info($post_id, 'mptbm_km_price');
            $time_price = MP_Global_Function::get_post_info($post_id, 'mptbm_hour_price');
            $fixed_map_price = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_map_price');
            $manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);

            $fixed_zone_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_zone_price_info', []);
//            error_log( print_r( [ '$fixed_zone_prices' => $fixed_zone_prices ], true ) );



            $fixed_map_route_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_map_route_price_info', []);
            $terms_location_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_terms_price_info', []);
            $selected_operation_areas = MP_Global_Function::get_post_info($post_id, 'mptbm_selected_operation_areas', []);
            $location_terms = get_terms(array('taxonomy' => 'locations', 'hide_empty' => false));

            $selected_operation_type = get_post_meta($post_id, 'mptbm_operation_area_type', true);

            $all_zones = array();
            $location_zones = array(); // Geo-located locations (term_*)
            $operation_zones = array(); // Operation areas (post_*)
            $operation_area = array(); // Operation areas (post_id*)

            if (!empty($location_terms) && !is_wp_error($location_terms)) {
                foreach ($location_terms as $term) {
                    if (get_term_meta($term->term_id, 'mptbm_geo_location', true)) {
                        $all_zones['term_' . $term->term_id] = $term->name . ' (Location)';
                        $location_zones['term_' . $term->term_id] = $term->name . ' (Location)';
                    }
                }
            }
            $op_areas = get_posts(array(
                'post_type' => 'mptbm_operate_areas',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'mptbm-operation-type',
                        'value' => 'fixed-operation-area-type'
                    )
                )
            ));
            if (!empty($op_areas)) {
                foreach ($op_areas as $area) {
                    $all_zones['post_' . $area->ID] = $area->post_title . ' (Operation Area)';
                    $operation_zones['post_' . $area->ID] = $area->post_title . ' (Operation Area)';
                    $operation_area[$area->ID] = $area->post_title;
                }
            }


            $merged_location_area = array_merge($operation_zones, $location_zones);


            $operation_area_str = '';
            if( is_array( $selected_operation_areas ) && !empty( $selected_operation_areas ) ){
                $operation_area_str = implode(',', $selected_operation_areas );
            }

            $all_operation_area_infos = MPTBM_Query::query_operation_area_list('mptbm_operate_areas');

            ?>
            <div class="mptbm_taxi_container mptbm_taxi_pricing_wrapper" data-step="2" style="display: none">
                <?php wp_nonce_field('mptbm_price_settings_action', 'mptbm_price_settings_nonce'); ?>
                <input type="hidden" name="mptbm_selected_operation_areas" id="mptbm_selected_operation_areas" value="<?php echo esc_html( $operation_area_str );?>">
                <?php
                self::initial_base_pricing( $post_id );

                if( $pro_active ) {
                    self::enable_base_location_charges($post_id);
                }
                ?>

                <div class="mptbm_rent_editor_wrapper" style="display: block">
                    <div class="mptbm_rent_editor_header">
                        <h3 class="mptbm_taxi_pricing_main_title">
                            <?php esc_html_e( 'Select Pricing Model', 'ecab-taxi-booking-manager' ); ?>
                        </h3>
                        <p class="mptbm_rent_editor_subtitle">
                            <?php esc_html_e( 'Choose the pricing model that applies to this service.', 'ecab-taxi-booking-manager' ); ?>
                        </p>
                    </div>
                    <div class="mptbm_taxi_pricing_tab_grid">
                        <input type="hidden" name="mptbm_price_based" value="<?php echo esc_attr( $price_based );?>" class="mptbm_taxi_pricing_input" >

                        <div class="mptbm_taxi_pricing_tab_item <?php echo esc_attr(($price_based === 'inclusive') ? 'active' : ''); ?>" data-id="mptbm_inclusive">
                            <i class="fas fa-layer-group" aria-hidden="true"></i>

                            <div class="mptbm_taxi_pricing_tab_info">
                                <h4><?php esc_html_e('Combined Pricing Model', 'ecab-taxi-booking-manager'); ?></h4>
                                <span><?php esc_html_e('Multiple Models', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                        </div>

                        <div class="mptbm_taxi_pricing_tab_item <?php echo esc_attr(($price_based === 'distance') ? 'active' : ''); ?>" data-id="mptbm_distance">
                            <i class="fas fa-route" aria-hidden="true"></i>

                            <div class="mptbm_taxi_pricing_tab_info">
                                <h4><?php esc_html_e('Distance', 'ecab-taxi-booking-manager'); ?></h4>
                                <span><?php esc_html_e('Based on KM', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                        </div>

                        <div class="mptbm_taxi_pricing_tab_item <?php echo esc_attr(($price_based === 'duration') ? 'active' : ''); ?>" data-id="mptbm_row_duration">
                            <i class="fas fa-clock" aria-hidden="true"></i>

                            <div class="mptbm_taxi_pricing_tab_info">
                                <h4><?php esc_html_e('Duration', 'ecab-taxi-booking-manager'); ?></h4>
                                <span><?php esc_html_e('Based on Time', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                        </div>

                        <div class="mptbm_taxi_pricing_tab_item <?php echo esc_attr(($price_based === 'distance_duration') ? 'active' : ''); ?>" data-id="mptbm_row_dist_dur">
                            <i class="fas fa-road" aria-hidden="true"></i>

                            <div class="mptbm_taxi_pricing_tab_info">
                                <h4><?php esc_html_e('Distance + Duration', 'ecab-taxi-booking-manager'); ?></h4>
                                <span><?php esc_html_e('Based on KM and Time', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                        </div>

                        <div class="mptbm_taxi_pricing_tab_item <?php echo esc_attr(($price_based === 'fixed_hourly') ? 'active' : ''); ?>" data-id="mptbm_row_hourly">
                            <i class="fas fa-business-time" aria-hidden="true"></i>

                            <div class="mptbm_taxi_pricing_tab_info">
                                <h4><?php esc_html_e('Fixed Hourly', 'ecab-taxi-booking-manager'); ?></h4>
                                <span><?php esc_html_e('Based on Hourly Rate', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                        </div>

                        <div class="mptbm_taxi_pricing_tab_item <?php echo esc_attr(($price_based === 'manual') ? 'active' : ''); ?>" data-id="mptbm_row_manual">
                            <i class="fas fa-map-signs" aria-hidden="true"></i>

                            <div class="mptbm_taxi_pricing_tab_info">
                                <h4><?php esc_html_e('Manual Routes', 'ecab-taxi-booking-manager'); ?></h4>
                                <span><?php esc_html_e('Based on Manual Routes', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                        </div>

                        <?php
                        $pricing_tab = 'mptbm_taxi_pricing_tab_item';

                        if( !$pro_active ){
                            $pricing_tab = 'mptbm_taxi_pricing_tab_item_pro';
                        }
//                        error_log( print_r( [ '$pricing_tab' => $pricing_tab ], true ) );
                        ?>
                        <div class=" <?php echo esc_attr( $pricing_tab );?> <?php echo esc_attr(($price_based === 'fixed_distance' || $price_based === 'fixed_zone' ) ? 'active' : ''); ?>" data-id="mptbm_row_operation_area">
                            <i class="fas fa-draw-polygon" aria-hidden="true"></i>

                            <div class="mptbm_taxi_pricing_tab_info">
                                <h4><?php esc_html_e('Operation Area', 'ecab-taxi-booking-manager'); ?></h4>
                                <span><?php esc_html_e('Based on Operation Area', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                        </div>


                    </div>
                </div>
                <div class="mptbm_rent_editor_wrapper">
                    <div class="mptbm_rent_editor_header">
                        <h3 class="mptbm_taxi_pricing_main_title"><?php esc_html_e( 'Configure Pricing Rules', 'ecab-taxi-booking-manager' ); ?></h3>
                        <p><?php esc_html_e( 'Select only one active pricing rule for this taxi model.', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                    <div class="mptbm_taxi_pricing_group" >
                        <div class="mptbm_taxi_pricing_row_content">

                            <div class="mptbm_taxi_pricing_field"
                                 id="mptbm_distance_price"
                                 style="display: <?php echo ($price_based === 'inclusive' || $price_based === 'distance' || $price_based === 'distance_duration' || $price_based === 'fixed_distance' ) ? 'block' : 'none'; ?>">
                                <label><?php esc_html_e( 'Price per KM', 'ecab-taxi-booking-manager' ); ?></label>
                                <input name="mptbm_km_price" value="<?php echo esc_attr( $distance_price );?>" type="text" placeholder="1.00">

                            </div>

                            <div class="mptbm_taxi_pricing_field"
                                 id="mptbm_fixed_pricing"
                                 style="display: <?php echo ( $price_based === 'fixed_distance'  ) ? 'block' : 'none'; ?>">
                                <label><?php esc_html_e( 'Fixed with map price', 'ecab-taxi-booking-manager' ); ?> </label>
                                <span><?php esc_html_e( 'Set the fixed price for map-based trips', 'ecab-taxi-booking-manager' ); ?></span>
                                <input name="mptbm_fixed_map_price" value="<?php echo esc_attr( $fixed_map_price );?>" type="text" placeholder="<?php esc_html_e('EX: 10', 'ecab-taxi-booking-manager'); ?>">
                            </div>

                            <div class="mptbm_taxi_pricing_field"
                                 id="mptbm_price_per_hour"
                                 style="display: <?php echo ($price_based === 'inclusive' || $price_based === 'duration' || $price_based === 'distance_duration' || $price_based === 'fixed_hourly' || $price_based === 'fixed_distance' ) ? 'block' : 'none'; ?>">
                                <label><?php esc_html_e( 'Price per Hour (Price/Hour)', 'ecab-taxi-booking-manager' ); ?></label>
                                <input name="mptbm_hour_price" value="<?php echo esc_attr( $time_price );?>" type="text" placeholder="0.20">
                            </div>


                            <?php
                            $routes_and_fixed_fare = 'none';
                            if( $price_based === 'inclusive' ){
//                                $routes_and_fixed_fare = 'flex';

                            }
                            $checked = '';
                            ?>
                            <div class="mptbm_manual_routes_and_fixed_fare_overrides" id="mptbm_manual_routes_and_fixed_fare_overrides" style="display: <?php echo esc_attr( $routes_and_fixed_fare );?>">
                                <div class="mptbm_taxi_ex_service_title_group">
                                    <h2 class="mptbm_taxi_ex_service_main_title"><?php esc_html_e( 'Manual Pricing', 'ecab-taxi-booking-manager' ); ?></h2>
                                    <p class="mptbm_taxi_ex_service_subtitle"><?php esc_html_e( 'Manage manual routes and fixed fare overrides.', 'ecab-taxi-booking-manager' ); ?></p>
                                </div>
                                <div class="manual_routes_and_fixed_fare_toggle_wrapper">
                                    <label class="mptbm_taxi_ex_service_switch">
                                        <input type="checkbox" id="mptbm_taxi_inclusive_manual_locations" <?php echo esc_attr($checked); ?>>
                                        <span class="mptbm_taxi_ex_service_slider"></span>
                                    </label>
                                    <span class="mptbm_manual_routes_and_fixed_fare_toggle_label"><?php esc_html_e( 'ON', 'ecab-taxi-booking-manager' ); ?></span>
                                </div>
                            </div>

                            <?php
                            if( $price_based === 'inclusive' ){
                                $show_manual = 'none';
                            }
                            ?>
                            <div class="mptbm_taxi_pricing_field1"
                                 id="mptbm_manual_routes"
                                 style="display: <?php echo ( $price_based === 'manual'  ) ? 'block' : 'none'; ?>">
                                <div class="mptbm_taxi_pricing_row_head">
                                    <span class="mptbm_taxi_pricing_label"><i class="fas fa-route"></i> <?php esc_html_e( 'Manual Routes', 'ecab-taxi-booking-manager' ); ?></span>
                                </div>
                                <div class="mptbm_taxi_pricing_field">
                                    <div class="mptbm_taxi_pricing_info_alert">
                                        <i class="far fa-lightbulb"></i>
                                        <span><?php esc_html_e( 'Routes not covered here fall back to the active pricing model.', 'ecab-taxi-booking-manager' ); ?></span>
                                    </div>

                                    <div class="mptbm_taxi_pricing_manual_list">
                                        <?php self::render_location_price_rows( $terms_location_prices, $location_terms );?>
                                    </div>

                                    <div class="mptbm_taxi_pricing_add_action">
                                        <button type="button" class="mptbm_taxi_pricing_add_route_full_btn">+ <?php esc_html_e( 'Add Route', 'ecab-taxi-booking-manager' ); ?></button>
                                    </div>
                                </div>
                            </div>

                           <?php
                            if( $pro_active ){
                                self::manage_operation_area_pricing( $price_based, $selected_operation_type, $all_operation_area_infos, $selected_operation_areas, $operation_area, $fixed_map_route_prices, $merged_location_area, $location_zones, $fixed_zone_prices );
                            }
                           ?>



                        </div>
                        <div class="mptbm_pricing_rules_wrapper">
                            <?php
                            self::pricing_rules_display( $price_based );
                            self::shortcode_description( $price_based );
                            ?>
                        </div>
                        <?php

                        ?>
                    </div>

                </div>


                <div class="mptbm_rent_editor_wrapper">
                    <?php
                    wp_nonce_field( 'mptbm_extra_service_nonce', 'mptbm_extra_service_nonce' );
                    self::extra_service_display( $post_id );
                    ?>
                </div>
            </div>

        <?php }

        public static function pricing_rules_display( $price_based ){ ?>
            <div class="mptbm_pricing_rules_grid" id="mptbm_pricing_rules_grid">

                <?php
                if( $price_based === 'inclusive' ){
                    ?>
                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Inclusive (Distance + Duration) Based Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                        <p><?php esc_html_e( 'Price is calculated using both time and distance.', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php esc_html_e( '(Hourly Rate × Duration) + (KM Rate × Distance)', 'ecab-taxi-booking-manager' ); ?>
                        </div>
                    </div>
                <?php }
                if( $price_based === 'distance' ){
                    ?>

                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Distance Based Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                        <p><?php esc_html_e( 'Only distance is used for calculation.', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php esc_html_e( 'KM Rate × Distance', 'ecab-taxi-booking-manager' ); ?>
                        </div>
                    </div>
                <?php }
                if( $price_based === 'duration'){
                    ?>
                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Duration Based Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                        <p><?php esc_html_e( 'Only travel time is considered.', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php esc_html_e( 'Hourly Rate × Duration', 'ecab-taxi-booking-manager' ); ?>
                        </div>
                    </div>
                <?php }
                if( $price_based === 'distance_duration' ){
                    ?>
                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Distance + Duration Based Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                        <p><?php esc_html_e( 'Combines both distance and time pricing.', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php esc_html_e( '(Hourly Rate × Duration) + (KM Rate × Distance)', 'ecab-taxi-booking-manager' ); ?>
                        </div>
                    </div>
                <?php }
                if( $price_based === 'fixed_hourly' ){
                    ?>

                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Fixed Hourly Based Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                        <p><?php esc_html_e( 'Fixed hourly pricing applied.', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php esc_html_e( 'Hour Rate × Fixed Time', 'ecab-taxi-booking-manager' ); ?>
                        </div>
                    </div>
                <?php } if( $price_based === 'fixed_distance' ){ ?>
                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Fixed Map Zone-based Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                        <p><?php esc_html_e( 'Zone-based fixed pricing or fallback calculation.', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php esc_html_e( 'If matched → fixed route price is applied
                                        If not matched → fallback calculation:
                                        Hourly + Distance pricing OR
                                        Operation area pricing override

                                        Formula (fallback):

                                        (Hour Price × Duration) + (KM Price × Distance)', 'ecab-taxi-booking-manager' ); ?>First checks predefined zone route price
                        </div>

                    </div>
                <?php } if( $price_based === 'fixed_zone' ){?>
                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Fixed Zone Based Pricing', 'ecab-taxi-booking-manager' ); ?> </h4>
                        <p><?php esc_html_e( 'Price depends on selected start & end zones:', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php esc_html_e( 'If pickup and dropoff zones match predefined route → fixed price applied
                                        Otherwise geo-zone matching is used
                                        Different logic for pickup vs dropoff mode
                                        Result:
                                        Fixed route price if matched', 'ecab-taxi-booking-manager' ); ?>
                        </div>
                    </div>
                <?php }
                if( $price_based === 'manual' ){
                    ?>
                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Manual Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                        <p><?php esc_html_e( 'Admin-defined exact route pricing.', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php esc_html_e( 'Fixed Route Price', 'ecab-taxi-booking-manager' ); ?>
                        </div>
                    </div>
                <?php }
                ?>
            </div>
        <?php }

        public static function manage_operation_area_pricing( $price_based, $selected_operation_type, $all_operation_area_infos, $selected_operation_areas, $operation_area, $fixed_map_route_prices, $merged_location_area, $location_zones, $fixed_zone_prices ){
            $is_operation_areas = 0;
            if( is_array( $selected_operation_areas ) && !empty( $selected_operation_areas ) ){
                $is_operation_areas = 1;
            }
            ?>
            <div class="mptbm_taxi_pricing_field1"
                 id="mptbm_operation_area"
                 style="display: <?php echo ( $price_based === 'fixed_distance' || $price_based === 'fixed_zone' ) ? 'block' : 'none'; ?>">

                <input type="hidden" id="mptbm_is_selected_operation_area" name="mptbm_is_selected_operation_area" value="<?php echo esc_attr( $is_operation_areas );?>">

                <div class="mptbm_operation_area_tab_holder">

                    <div class="mptbm_taxi_pricing_tab_item_area <?php echo esc_attr(($price_based === 'fixed_distance') ? 'active' : ''); ?>" id="mptbm_taxi_pricing_fixed_map" data-id="mptbm_row_operation_area">
                        <span class="tab-icon">🚕</span>
                        <span class="tab-title"><?php esc_html_e('Fixed With Map', 'ecab-taxi-booking-manager'); ?></span>
                    </div>

                    <div class="mptbm_taxi_pricing_tab_item_area <?php echo esc_attr(($price_based === 'fixed_zone') ? 'active' : ''); ?>" id="mptbm_taxi_pricing_fixed_zone" data-id="mptbm_row_zone">
                        <span class="tab-icon">📍</span>
                        <span class="tab-title"><?php esc_html_e('Fixed Zone', 'ecab-taxi-booking-manager'); ?></span>
                    </div>
                </div>


                <div class="mptbm_taxi_pricing_row_head">
                    <span class="mptbm_taxi_pricing_label"><i class="fas fa-pencil-alt"></i> <?php esc_html_e('Operation Area', 'ecab-taxi-booking-manager'); ?></span>
                </div>
                <div class="mptbm_taxi_pricing_field">
                    <div class="mp_settings_area " id="mptbm_operation_area_settings"
                         style="display: <?php echo ( $price_based === 'fixed_distance' ) ? 'block' : 'none'; ?>"
                    >
                        <section>
                            <label class="label">
                                <div>
                                    <div class="mptbm_taxi_operation_area_type_title"><?php esc_html_e('Choose the type of operation area', 'ecab-taxi-booking-manager'); ?></div>
                                </div>
                                <select class="formControl mptbm_operation_area_type" name="mptbm_operation_area_type" id="mptbm_operation_area_type" data-collapse-target>
                                    <option value=""><?php esc_html_e('Select Operation Type', 'ecab-taxi-booking-manager'); ?></option>
                                    <option value="fixed-operation-area-type" <?php selected($selected_operation_type, 'fixed-operation-area-type'); ?>><?php esc_html_e('Fixed Operation Area (Both In)', 'ecab-taxi-booking-manager'); ?></option>
                                    <option value="fixed-map-operation-area-type" <?php selected($selected_operation_type, 'fixed-map-operation-area-type'); ?>><?php esc_html_e('Fixed Map Operation Area (Pickup In)', 'ecab-taxi-booking-manager'); ?></option>
                                    <option value="geo-fence-operation-area-type" <?php selected($selected_operation_type, 'geo-fence-operation-area-type'); ?>><?php esc_html_e('Geo Fence Operation Area', 'ecab-taxi-booking-manager'); ?></option>
                                    <option value="geo-matched-operation-area-type" <?php selected($selected_operation_type, 'geo-matched-operation-area-type'); ?>><?php esc_html_e('Geo-Matched Operation Area', 'ecab-taxi-booking-manager'); ?></option>
                                </select>
                            </label>
                        </section>
                    </div>

                    <div class="mptbm_taxi_pricing_selection_group">
                        <?php
                        $show_area = '';
                        $show_area_create = 'none';
                        if( empty( $all_operation_area_infos ) ){
                            $show_area = 'none';
                            $show_area_create = '';
                        }
                        if( $selected_operation_type == 'geo-fence-operation-area-type' ){?>
                            <section id="geo-fence-operation-area-section" class="<?php echo ($selected_operation_type === 'geo-fence-operation-area-type') ? 'mActive' : ''; ?>" data-collapse="#geo-fence-operation-area-type">
                                <label class="label">
                                    <div>
                                        <h6><?php esc_html_e('Select Geo Fence Operation Area', 'ecab-taxi-booking-manager'); ?></h6>
                                        <span class="desc"><?php esc_html_e('Select a geo fence operation area', 'ecab-taxi-booking-manager'); ?></span>
                                    </div>
                                    <select class="formControl" name="mptbm_selected_operation_areas[]" id="mptbm_selected_geo_fence_area">
                                        <option value=""><?php esc_html_e('Select Geo Fence Area', 'ecab-taxi-booking-manager'); ?></option>
                                        <?php
                                        foreach ( $all_operation_area_infos as $area_info ) {
                                            if ($area_info['operation_type'] == 'geo-fence-operation-area-type') {
                                                $selected = in_array($area_info['post_id'], $selected_operation_areas) ? 'selected' : '';
                                                echo '<option value="' . esc_attr($area_info['post_id']) . '" ' . $selected . '>' . esc_html(get_the_title($area_info['post_id'])) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </label>
                            </section>
                        <?php } else{
                        ?>
                        <label><?php esc_html_e( 'SELECT OPERATION AREAS — multiple allowed', 'ecab-taxi-booking-manager' ); ?></label>

                        <div class="mptbm_taxi_pricing_area_pills" style="display: <?php echo esc_attr( $show_area )?>">
                            <?php

                            foreach ( $operation_area as $id => $name): ?>

                                <?php
                                $is_selected = in_array($id, $selected_operation_areas);
                                ?>

                                <button
                                        type="button"
                                        class="mptbm_taxi_pricing_pill <?php echo $is_selected ? 'selected' : ''; ?>"
                                        data-id="<?php echo $id; ?>"
                                >
                                    <?php if ($is_selected): ?>
                                        <i class="fas fa-check"></i>
                                    <?php endif; ?>
                                    <?php echo $name; ?>
                                </button>

                            <?php endforeach; ?>

                        </div>

                        <?php if( $is_operation_areas === 0 ){?>
                            <div class="mptbm_empty_selected_area">
                                <span class="mptbm_required">*</span>
                                <span class="mptbm_empty_selected_area_text">
                                    <?php esc_html_e( 'You have not selected any operation area.
                                    For fixed map or fixed zone pricing setup,
                                    you need to select at least one operation area and save settings first.', 'ecab-taxi-booking-manager' ); ?>
                                </span>
                            </div>
                        <?php }?>

                        <div class="mptbm_operation_area_create_link" style="display: <?php echo esc_attr( $show_area_create );?>">
                            <a href="<?php echo admin_url('edit.php?post_type=mptbm_operate_areas'); ?>" class="mptbm_create_area_btn">
                                + <?php esc_html_e( 'Create Operation Area', 'ecab-taxi-booking-manager' ); ?>
                            </a>
                        </div>
                    </div>
                    <?php }?>

                    <!--<div class="mptbm_taxi_pricing_sub_section">
                        <div class="mptbm_taxi_pricing_sub_header">
                            <h4>Operation Area Based Price Set</h4>
                            <p>Set different pricing for each operation area. Easily manage fixed, per km, and per hour rates.</p>
                        </div>
                        <div class="mptbm_taxi_pricing_area_list">
                            <div class="mptbm_taxi_pricing_area_row">
                                <select><option>dhaka jone (Operation Area)</option></select>
                                <input type="text" placeholder="20">
                                <input type="text" placeholder="1">
                                <input type="text" placeholder="44">
                                <button type="button" class="mptbm_taxi_pricing_remove_link">Remove</button>
                            </div>
                        </div>
                        <div class="mptbm_taxi_pricing_footer_actions">
                            <button type="button" class="mptbm_taxi_pricing_pink_btn mptbm_taxi_pricing_add_area_btn">+ Add Area Price</button>
                            <button type="button" class="mptbm_taxi_pricing_save_btn">Save</button>
                        </div>
                    </div>-->

                    <?php
                    $area_based_pricing = 'none';
                    if( !empty( $all_operation_area_infos ) && !empty( $selected_operation_areas ) ){
                        $area_based_pricing = '';
                    }

                    ?>

                    <div class="">
                        <div class="mptbm_taxi_pricing_sub_section"
                             id="mptbm_fixed_map_area_pricing"
                             style="display: <?php echo ( $price_based === 'fixed_distance' && !empty( $selected_operation_areas ) ) ? 'block' : 'none'; ?>">

                            <div class="mptbm_taxi_pricing_sub_header">
                                <h4><?php esc_html_e( 'Fixed Map Route Overrides', 'ecab-taxi-booking-manager' ); ?></h4>
                                <p><?php esc_html_e( 'Define fixed prices for specific routes when using "Fixed with Map" mode.', 'ecab-taxi-booking-manager' ); ?></p>
                            </div>
                            <div class=""  style="display: <?php echo esc_attr( $area_based_pricing );?>" >
                                <?php
                                self::render_fixed_with_map_price_rows( $fixed_map_route_prices, $merged_location_area, 'mptbm_taxi_pricing_route_list', $location_zones );
                                ?>
                            </div>
                            <button type="button" class="mptbm_taxi_pricing_pink_btn mptbm_taxi_pricing_add_route_btn">+ <?php esc_html_e( 'Add New Route', 'ecab-taxi-booking-manager' ); ?></button>
                        </div>

                        <div class="mptbm_taxi_pricing_field"
                             id="mptbm_fixed_zone_area_pricing"
                             style="display: <?php echo ( $price_based === 'fixed_zone' && !empty( $selected_operation_areas ) ) ? 'block' : 'none'; ?>">
                            <div class="mptbm_taxi_pricing_sub_section">
                                <div class="mptbm_taxi_pricing_sub_header">
                                    <h4><?php esc_html_e( 'Fixed Route & Zone Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                                    <p><?php esc_html_e( 'Define fixed prices for specific routes between zones or locations for "Fixed Zone" mode.', 'ecab-taxi-booking-manager' ); ?></p>
                                </div>
                                 <div class="mptbm_selected_operation_area"  style="display: <?php echo esc_attr( $area_based_pricing );?>" >
                                <?php
                                    self::render_fixed_zone_price_rows( $fixed_zone_prices, $merged_location_area, 'mptbm_taxi_pricing_zone_to_zone_route_list', $location_zones );
                                ?>
                                 </div>

                                <button type="button" class="mptbm_taxi_pricing_pink_btn mptbm_taxi_pricing_add_zone_btn">+ <?php esc_html_e( 'Add New Route', 'ecab-taxi-booking-manager' ); ?></button>
                            </div>
                        </div>
                    </div>


                </div>
            </div>
        <?php }
        public static function render_fixed_with_map_price_rows( $fixed_map_route_prices, $merged_location_area, $append_body, $location_zones ) {

            ?>
            <table class="mptbm_taxi_pricing_table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Start Zone *', 'ecab-taxi-booking-manager' ); ?></th>
                    <th><?php esc_html_e( 'End Zone *', 'ecab-taxi-booking-manager' ); ?></th>
                    <th><?php esc_html_e( 'Price *', 'ecab-taxi-booking-manager' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'ecab-taxi-booking-manager' ); ?></th>
                </tr>
                </thead>
                <tbody class="<?php echo esc_html( $append_body );?>">
                <?php
                if( !empty( $fixed_map_route_prices ) ){
                foreach ($fixed_map_route_prices as $route):
                    ?>
                    <tr>
                        <td>
                            <select name="mptbm_fixed_map_route_start_location[]" class="mptbm_fixed_map_route_start_location">
                                <?php foreach ($merged_location_area as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"
                                        <?php selected($route['start_location'], $key); ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="mptbm_fixed_map_route_end_location[]" class="mptbm_fixed_map_route_end_location">
                                <?php foreach ($location_zones as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"
                                        <?php selected($route['end_location'], $key); ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input
                                    name="mptbm_fixed_map_route_price[]"
                                    type="text"
                                    value="<?php echo esc_attr($route['price']); ?>"
                                    placeholder="EX: 10"
                            >
                        </td>
                        <td>
                            <div class="mptbm_taxi_pricing_table_actions">
                                <button class="mptbm_taxi_pricing_del_icon">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="mptbm_taxi_pricing_expand_icon">
                                    <i class="fas fa-expand-arrows-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach;
                }else{
                ?>
                    <tr>
                        <td>
                            <select name="mptbm_fixed_map_route_start_location[]" class="mptbm_fixed_map_route_start_location">
                                <?php foreach ($merged_location_area as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"
                                        >
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="mptbm_fixed_map_route_end_location[]" class="mptbm_fixed_map_route_end_location">
                                <?php foreach ($location_zones as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"
                                       >
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input
                                    name="mptbm_fixed_map_route_price[]"
                                    type="text"
                                    value=""
                                    placeholder="EX: 10"
                            >
                        </td>
                        <td>
                            <div class="mptbm_taxi_pricing_table_actions">
                                <button class="mptbm_taxi_pricing_del_icon">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="mptbm_taxi_pricing_expand_icon">
                                    <i class="fas fa-expand-arrows-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php }?>
                </tbody>
            </table>
            <?php
        }

        public static function render_fixed_zone_price_rows( $fixed_map_route_prices, $merged_location_area, $append_body, $location_zones ) {

            ?>
            <table class="mptbm_taxi_pricing_table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Start Zone *', 'ecab-taxi-booking-manager' ); ?></th>
                    <th><?php esc_html_e( 'End Zone *', 'ecab-taxi-booking-manager' ); ?></th>
                    <th><?php esc_html_e( 'Price *', 'ecab-taxi-booking-manager' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'ecab-taxi-booking-manager' ); ?></th>
                </tr>
                </thead>
                <tbody class="<?php echo esc_html( $append_body );?>">
                <?php
                if( !empty( $fixed_map_route_prices ) ){
                foreach ($fixed_map_route_prices as $route):
                    ?>
                    <tr>
                        <td>
                            <select name="mptbm_zone_to_zone_route_start_location[]" class="mptbm_fixed_map_route_start_location">
                                <?php foreach ( $merged_location_area as $key => $label ): ?>
                                    <option value="<?php echo $key; ?>"
                                        <?php selected($route['start_location'], $key); ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="mptbm_zone_to_zone_route_end_location[]" class="mptbm_fixed_map_route_end_location">
                                <?php foreach ( $merged_location_area as $key => $label ): ?>
                                    <option value="<?php echo $key; ?>"
                                        <?php selected($route['end_location'], $key); ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input
                                    name="mptbm_zone_to_zone_route_price[]"
                                    type="text"
                                    value="<?php echo esc_attr($route['price']); ?>"
                                    placeholder="EX: 10"
                            >
                        </td>
                        <td>
                            <div class="mptbm_taxi_pricing_table_actions">
                                <button class="mptbm_taxi_pricing_del_icon">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="mptbm_taxi_pricing_expand_icon">
                                    <i class="fas fa-expand-arrows-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach;
                }else{
                ?>
                    <tr>
                        <td>
                            <select name="mptbm_zone_to_zone_route_start_location[]" class="mptbm_fixed_map_route_start_location">
                                <?php foreach ($merged_location_area as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"
                                        >
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="mptbm_zone_to_zone_route_end_location[]" class="mptbm_fixed_map_route_end_location">
                                <?php foreach ($merged_location_area as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"
                                       >
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input
                                    name="mptbm_zone_to_zone_route_price[]"
                                    type="text"
                                    value=""
                                    placeholder="EX: 10"
                            >
                        </td>
                        <td>
                            <div class="mptbm_taxi_pricing_table_actions">
                                <button class="mptbm_taxi_pricing_del_icon">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="mptbm_taxi_pricing_expand_icon">
                                    <i class="fas fa-expand-arrows-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php }?>
                </tbody>
            </table>
            <?php
        }

        public static function render_location_price_rows($terms_location_prices, $location_terms) {

//            error_log( print_r( [ '$terms_location_prices' => $terms_location_prices, '$location_terms' => $location_terms ], true ) );
            $location_map = [];
            foreach ($location_terms as $term) {
                $location_map[$term->slug] = $term->name;
            }

            if (!empty($terms_location_prices)) {
                foreach ($terms_location_prices as $route) {
                    ?>

                    <div class="mptbm_taxi_pricing_route_row">

                        <!-- Start -->
                        <div class="mptbm_taxi_pricing_select_wrap">
                            <select name="mptbm_terms_start_location[]">
                                <?php foreach ($location_terms as $term): ?>
                                    <option value="<?php echo esc_attr($term->slug); ?>"
                                        <?php selected($route['start_location'], $term->slug); ?>>
                                        <?php echo esc_html($term->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- End -->
                        <div class="mptbm_taxi_pricing_select_wrap">
                            <select name="mptbm_terms_end_location[]">
                                <?php foreach ($location_terms as $term): ?>
                                    <option value="<?php echo esc_attr($term->slug); ?>"
                                        <?php selected($route['end_location'], $term->slug); ?>>
                                        <?php echo esc_html($term->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Price -->
                        <div class="mptbm_taxi_pricing_input_wrap">
                            <input
                                    name="mptbm_location_terms_price[]"
                                    type="text"
                                    value="<?php echo esc_attr($route['price']); ?>"
                                    placeholder="<?php esc_html_e( 'e.g., 250 - F', 'ecab-taxi-booking-manager' ); ?>"
                            >
                        </div>

                        <!-- Actions -->
                        <div class="mptbm_taxi_pricing_action_btns">
                            <button type="button" class="mptbm_taxi_pricing_clone_btn">
                                <i class="far fa-copy"></i>
                            </button>
                            <button type="button" class="mptbm_taxi_pricing_delete_btn">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                    </div>

                    <?php
                }
            } else {
                // empty row fallback
                ?>
                <div class="mptbm_taxi_pricing_route_row">

                    <div class="mptbm_taxi_pricing_select_wrap">
                        <select name="mptbm_terms_start_location[]">
                            <option value=""><?php esc_html_e( 'Start city...', 'ecab-taxi-booking-manager' ); ?></option>
                            <?php foreach ($location_terms as $term): ?>
                                <option value="<?php echo esc_attr($term->slug); ?>">
                                    <?php echo esc_html($term->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mptbm_taxi_pricing_select_wrap">
                        <select name="mptbm_terms_end_location[]">
                            <option value="">End city...</option>
                            <?php foreach ($location_terms as $term): ?>
                                <option value="<?php echo esc_attr($term->slug); ?>">
                                    <?php echo esc_html($term->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mptbm_taxi_pricing_input_wrap">
                        <input name="mptbm_location_terms_price[]" type="text" placeholder="<?php esc_html_e( 'e.g., 250 - F', 'ecab-taxi-booking-manager' ); ?>">
                    </div>

                </div>
                <?php
            }
        }

        // 3. Save / Update post
        public function save_post() {

            if (
                !isset($_POST['_wpnonce']) ||
                !wp_verify_nonce($_POST['_wpnonce'], 'save_mptbm_rent_nonce')
            ) {
                wp_die('Security check failed');
            }

            $post_id = intval($_POST['post_id']);
            $title   =  isset($_POST['post_title'] ) ? sanitize_text_field($_POST['post_title']) : 'TEst';

            $data = [
                'post_title'  => $title,
                'post_type'   => 'mptbm_rent',
                'post_status' => 'publish',
            ];

            if ($post_id) {
                $data['ID'] = $post_id;
                $post_id = wp_update_post($data);
            } else {
                $post_id = wp_insert_post($data);
            }

            wp_redirect(admin_url('admin.php?page=mptbm-rent-edit&post_id=' . $post_id . '&updated=1'));
            exit;
        }

        // 4. Redirect default WP editor → custom page
        public function redirect_default_editor() {

            global $pagenow;

            if (
                $pagenow === 'post.php' &&
                isset($_GET['post']) &&
                get_post_type($_GET['post']) === 'mptbm_rent'
            ) {

                // Allow old editor
                if (isset($_GET['editor']) && $_GET['editor'] === 'old') {
                    return;
                }

                $post_id = intval($_GET['post']);

                wp_redirect(
                    admin_url(
                        'admin.php?page=mptbm-rent-edit&post_id=' . $post_id
                    )
                );
                exit;
            }
        }

    }
    new MPTBM_Rent_Custom_Editor();
}