<?php
/*
 * @Author 		rubelcuet10@gmail.com
 * Copyright: 	mage-people.com
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Rent_Custom_Editor')) {
    class MPTBM_Rent_Custom_Editor
{
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_save_mptbm_rent', [$this, 'save_post']);
        add_action('admin_init', [$this, 'redirect_default_editor']);
        add_action('admin_init', [$this, 'redirect_add_new']);


//        add_action('admin_ajax_save_mptbm_rent', [$this, 'save_mptbm_rent_callback'] );
        add_action('wp_ajax_save_mptbm_rent', [$this, 'save_mptbm_rent_callback']);
        add_action('wp_ajax_nopriv_save_mptbm_rent', [$this, 'save_mptbm_rent_callback']);
    }

    function save_mptbm_rent_callback() {

        error_log( print_r( [ '$_POST' => $_POST ], true ) );
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'save_mptbm_rent_nonce')) {
            wp_die('Security check failed');
        }

        $post_id = intval($_POST['post_id']);
        error_log( print_r( [ '$post_id' => $post_id ], true ) );

        $data = [
            'maximum_passenger' => sanitize_text_field($_POST['mptbm_maximum_passenger']),
            'maximum_bag'       => sanitize_text_field($_POST['mptbm_maximum_bag']),
            'extra_info'        => sanitize_textarea_field($_POST['mptbm_extra_info']),
        ];

        // Example: save as post meta
        foreach ($data as $key => $value) {
//            update_post_meta($post_id, $key, $value);
        }

        wp_redirect(admin_url('post.php?post=' . $post_id . '&action=edit&updated=1'));
        exit;
    }

    // 1. Register submenu page
    public function register_menu() {
        add_submenu_page(
            'edit.php?post_type=mptbm_rent',
            __('Edit Rent', 'textdomain'),
            __('Edit Rent', 'textdomain'),
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
            wp_redirect(admin_url('admin.php?page=mptbm-rent-edit'));
            exit;
        }
    }

    // 2. Render custom editor page
    public function render_page() {

        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        $title   = $post_id ? get_the_title($post_id) : '';


        ?>
        <div class="wrap">

            <form class="mptbm_rent_form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="save_mptbm_rent">
                <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">

                <?php wp_nonce_field('save_mptbm_rent_nonce'); ?>

                <div class="mptbm_taxi_wrapper">
                    <h1><?php echo $post_id ? 'Edit Rent' : 'Add Rent'; ?></h1>

                    <div class="mptbm_post_title">
                        <label for="mptbm_rent_title">Rent Title</label>
                        <input
                                type="text"
                                id="mptbm_rent_title"
                                name="post_title"
                                value="<?php echo esc_attr(get_the_title($post_id)); ?>"
                                placeholder="Enter rent title"
                                required
                        >
                    </div>

                    <?php

                    self::taxi_content_tabs_set( $post_id );

                    self::general_information_set( $post_id );

                    self::pricing_settings( $post_id );

//                    self::extra_service_settings( $post_id );

                    self::date_configuration_set( $post_id );

                    ?>

                    <div class="mptbm_taxi_advanced" data-step="4">Advanced</div>

                    <div class="mptbm_taxi_footer">
                        <button class="mptbm_taxi_btn_prev">← Previous</button>
                        <span class="mptbm_taxi_step_counter">Step 1 of 4</span>
                        <button class="mptbm_taxi_btn_next">Next →</button>
                    </div>

                    <?php submit_button($post_id ? 'Update' : 'Publish'); ?>
                </div>

            </form>
        </div>
        <?php
    }

    public static function taxi_content_tabs_set( $post_id ){ ?>
        <div class="mptbm_taxi_stepper">
            <div class="mptbm_taxi_step mptbm_taxi_active" data-step="1" data-icon="fas fa-clipboard-list">
                <div class="mptbm_taxi_icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="mptbm_taxi_label">General Information</div>
                <div class="mptbm_taxi_subtext">Step 1 of 3</div>
            </div>
            <div class="mptbm_taxi_line"></div>
            <div class="mptbm_taxi_step" data-step="2" data-icon="fas fa-dollar-sign">
                <div class="mptbm_taxi_icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="mptbm_taxi_label">Pricing Configuration</div>
                <div class="mptbm_taxi_subtext">Step 2 of 3</div>
            </div>
            <!--<div class="mptbm_taxi_line"></div>
            <div class="mptbm_taxi_step" data-step="3" data-icon="fas fa-magic">
                <div class="mptbm_taxi_icon"><i class="fas fa-magic"></i></div>
                <div class="mptbm_taxi_label">Extra Services</div>
                <div class="mptbm_taxi_subtext">Step 3 of 5</div>
            </div>-->
            <div class="mptbm_taxi_line"></div>
            <div class="mptbm_taxi_step" data-step="3" data-icon="fas fa-calendar-alt">
                <div class="mptbm_taxi_icon"><i class="far fa-calendar-alt"></i></div>
                <div class="mptbm_taxi_label">Operational Date Time</div>
                <div class="mptbm_taxi_subtext">Step 3 of 3</div>
            </div>
            <!--<div class="mptbm_taxi_line"></div>
            <div class="mptbm_taxi_step" data-step="4" data-icon="fas fa-cog">
                <div class="mptbm_taxi_icon"><i class="fas fa-cog"></i></div>
                <div class="mptbm_taxi_label">Advanced</div>
                <div class="mptbm_taxi_subtext">Step 4 of 4</div>
            </div>-->
        </div>
    <?php }

    public static function general_information_set( $post_id ){
        $initial_price = MP_Global_Function::get_post_info($post_id, 'mptbm_initial_price');
        $min_price = MP_Global_Function::get_post_info($post_id, 'mptbm_min_price');
        $return_min_price = MP_Global_Function::get_post_info($post_id, 'mptbm_min_price_return');
        $return_discount = MP_Global_Function::get_post_info($post_id, 'mptbm_return_discount');
        $display_map = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'enable');

        $price_display_type = MP_Global_Function::get_post_info($post_id, 'mptbm_price_display_type', 'normal');
        $custom_price_message = MP_Global_Function::get_post_info($post_id, 'mptbm_custom_price_message', '');

        $waiting_time_check = MPTBM_Function::get_general_settings('taxi_waiting_time', 'enable');
        $waiting_price = MP_Global_Function::get_post_info($post_id, 'mptbm_waiting_price');


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
            <?php wp_nonce_field('mptbm_transportation_type_nonce', 'mptbm_transportation_type_nonce'); ?>
            <div class="mptbm_taxi_section">
                <h3 class="mptbm_taxi_section_title">General Date Configuration</h3>
                <p class="mptbm_taxi_section_desc">Here you can configure general date</p>

                <div class="mptbm_taxi_grid">
                    <div class="mptbm_taxi_field">
                        <label>Maximum Passenger</label>
                        <p class="mptbm_taxi_help">Filters services by the maximum number of passengers allowed</p>
                        <input name="mptbm_maximum_passenger" type="text" value="<?php echo esc_attr( $max_passenger );?>" placeholder="EX:4">
                    </div>
                    <div class="mptbm_taxi_field">
                        <label>Maximum Bag</label>
                        <p class="mptbm_taxi_help">Filters services by the maximum number of bags allowed</p>
                        <input name="mptbm_maximum_bag" type="text" value="<?php echo esc_attr( $max_bag );?>" placeholder="EX:4">
                    </div>
                    <div class="mptbm_taxi_field">
                        <label>Maximum hand luggage</label>
                        <p class="mptbm_taxi_help">Filters services by the maximum number of hand luggage allowed</p>
                        <input name="mptbm_maximum_hand_luggage" type="text" value="<?php echo esc_attr( $max_hand_luggage );?>" placeholder="EX:2">
                    </div>
                    <div class="mptbm_taxi_field">
                        <label>Extra Info</label>
                        <p class="mptbm_taxi_help">Add any additional information about this vehicle that you want to display to customers</p>
                        <textarea name="mptbm_extra_info" rows="4" placeholder="Enter additional information about this vehicle..."><?php echo esc_html( $extra_info );?></textarea>
                    </div>
                </div>
            </div>

            <div class="mptbm_taxi_section mptbm_taxi_toggle_box">
                <div class="mptbm_taxi_toggle_header">
                    <div class="mptbm_taxi_toggle_info">
                        <label class="mptbm_taxi_switch">
                            <input type="checkbox" checked class="mptbm_taxi_toggle_trigger" data-target="#baseFareContent">
                            <span class="mptbm_taxi_slider"></span>
                        </label>
                        <div class="mptbm_taxi_toggle_text">
                            <strong>Base Fare Settings</strong>
                            <p>Starting fare added at trip start regardless of distance. Toggle off to remove the base charge entirely.</p>
                        </div>
                    </div>
                    <span class="mptbm_taxi_status_badge">ON</span>
                </div>

                <div id="baseFareContent" class="mptbm_taxi_grid mptbm_taxi_toggle_content">
                    <div class="mptbm_taxi_field">
                        <label>Initial Price</label>
                        <p class="mptbm_taxi_help">Starting fare added at trip start regardless of distance</p>
                        <input name="mptbm_initial_price" type="text"  value="<?php echo esc_attr( $initial_price );?>">
                    </div>
                    <div class="mptbm_taxi_field">
                        <label>Minimum Price</label>
                        <p class="mptbm_taxi_help">Floor fare applied when calculated price is lower</p>
                        <input name="mptbm_min_price" type="text"  value="<?php echo esc_attr( $min_price );?>">
                    </div>
                    <div class="mptbm_taxi_field">
                        <label>Return Minimum Price</label>
                        <p class="mptbm_taxi_help">Minimum fare applied on return trips</p>
                        <input name="mptbm_min_price_return" type="text" value="<?php echo esc_attr( $return_min_price );?>" placeholder="e.g., 40 - Min fare for return">
                    </div>
                    <div class="mptbm_taxi_field">
                        <label>Return Discount</label>
                        <p class="mptbm_taxi_help">Discount applied to return trips — fixed or %</p>
                        <input name="mptbm_return_discount" type="text" value="<?php echo esc_attr( $return_discount );?>" placeholder="e.g., 10 - Discount amount or %">
                    </div>
                    <div class="mptbm_taxi_field">
                        <label>Waiting Time Price/Hour</label>
                        <p class="mptbm_taxi_help">Specifies the price charged per hour for waiting time</p>
                        <input name="mptbm_waiting_price" type="text" value="<?php echo esc_attr( $waiting_price );?>" placeholder="EX:10">
                    </div>
                </div>
            </div>

            <div class="mptbm_taxi_section mptbm_taxi_toggle_box">

                <?php
                $base_price_location = MP_Global_Function::get_post_info($post_id, 'mptbm_base_price_location', '');
                $base_price_km = MP_Global_Function::get_post_info($post_id, 'mptbm_base_price_km', '');
                $base_price_hour = MP_Global_Function::get_post_info($post_id, 'mptbm_base_price_hour', '');
                $base_min_threshold = MP_Global_Function::get_post_info($post_id, 'mptbm_base_min_threshold', '');
                $charge_base_pickup = MP_Global_Function::get_post_info($post_id, 'mptbm_charge_base_pickup', 'no');
                $charge_base_dropoff = MP_Global_Function::get_post_info($post_id, 'mptbm_charge_base_dropoff', 'no');

//                error_log( print_r( [ '$charge_base_pickup' => $charge_base_pickup, '$charge_base_dropoff' => $charge_base_dropoff ], true ) );
                // Get Locations for dropdown
                $locations = get_terms(array(
                    'taxonomy' => 'locations',
                    'hide_empty' => false,
                ));
                ?>

                <div class="mptbm_taxi_toggle_header">
                    <div class="mptbm_taxi_toggle_info">
                        <div class="mptbm_taxi_toggle_text">
                            <strong>Enable Base Location Charges</strong>
                            <p>Apply additional charges based on distance between taxi base location and pickup/drop-off points.</p>
                        </div>
                    </div>
                </div>
                <div id="baseFareContent" class="mptbm_taxi_grid mptbm_taxi_toggle_content">
                    <div class="mptbm_taxi_field">
                        <label>Base Price Location</label>
                        <p class="mptbm_taxi_help">Select the base location for price calculation</p>

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
                        <!--<select class="formControl" name="mptbm_base_price_location">
                            <option value="">Select Location</option>
                            <option value="25">
                                mohammad pur bus stand
                            </option>
                        </select>-->
                    </div>
                    <div class="mptbm_taxi_field">
                        <label>Price per KM</label>
                        <p class="mptbm_taxi_help">Enter the price per kilometer from base location</p>
                        <input name="mptbm_base_price_km" type="number" value="<?php echo esc_attr( $base_price_km )?>" placeholder="1.5">
                    </div>

                    <div class="mptbm_taxi_field">
                        <label>Price per Hour</label>
                        <p class="mptbm_taxi_help">Enter the price per hour from base location</p>
                        <input name="mptbm_base_price_hour" type="number" value="<?php echo esc_attr( $base_price_hour );?>" placeholder="10 ">
                    </div>
                    <div class="mptbm_taxi_field">
                        <label>Minimum Threshold (Distance)</label>
                        <p class="mptbm_taxi_help">Distance free of charge from base price location</p>
                        <input name="mptbm_base_min_threshold" type="number" value="<?php echo esc_attr( $base_min_threshold );?>" placeholder="1">
                    </div>
                </div>
            </div>

            <div class="mptbm_taxi_section mptbm_taxi_toggle_box">
                <div class="mptbm_taxi_toggle_header">
                    <div class="mptbm_taxi_toggle_info">
                        <label class="mptbm_taxi_switch">
                            <input name="mptbm_charge_base_pickup" type="checkbox" class="mptbm_taxi_toggle_trigger" <?php echo ($charge_base_pickup == 'yes') ? 'checked' : ''; ?>>
                            <span class="mptbm_taxi_slider"></span>
                        </label>
                        <div class="mptbm_taxi_toggle_text">
                            <strong>Charge for Base to Pickup?</strong>
                            <p>Enable to charge for distance/time from base location to pickup location</p>
                        </div>
                    </div>
                    <?php if( $charge_base_pickup == 'yes' ){?>
                        <span class="mptbm_taxi_status_badge">On</span>
                    <?php }else{?>
                        <span class="mptbm_taxi_status_badge mptbm_taxi_off">OFF</span>
                    <?php }?>
                </div>
            </div>

            <div class="mptbm_taxi_section mptbm_taxi_toggle_box">
                <div class="mptbm_taxi_toggle_header">
                    <div class="mptbm_taxi_toggle_info">
                        <label class="mptbm_taxi_switch">
                            <input name="mptbm_charge_base_dropoff" type="checkbox" class="mptbm_taxi_toggle_trigger" <?php echo ($charge_base_dropoff == 'yes') ? 'checked' : ''; ?>>
                            <span class="mptbm_taxi_slider"></span>
                        </label>
                        <div class="mptbm_taxi_toggle_text">
                            <strong>Charge for Base to Dropoff?</strong>
                            <p>Enable to charge for distance/time from dropoff location back to base location</p>
                        </div>
                    </div>
                    <?php if( $charge_base_pickup == 'yes' ){?>
                        <span class="mptbm_taxi_status_badge">ON</span>
                    <?php }else{?>
                        <span class="mptbm_taxi_status_badge mptbm_taxi_off">OFF</span>
                    <?php }?>
                </div>
            </div>

            <div class="mptbm_taxi_section mptbm_taxi_toggle_box">
                <div class="mptbm_taxi_toggle_header">
                    <div class="mptbm_taxi_toggle_info">
                        <div class="mptbm_taxi_toggle_text">
                            <strong>Price Display Settings</strong>
                            <p>Configure how fares are shown to customers</p>
                        </div>
                    </div>
                    <select class="formControl" name="mptbm_price_display_type" data-collapse-target="">
                        <option value="normal" <?php selected($price_display_type, 'normal'); ?>><?php esc_html_e('Normal Price', 'ecab-taxi-booking-manager'); ?></option>
                        <option value="zero" <?php selected($price_display_type, 'zero'); ?>><?php esc_html_e('Show as Zero (0.00)', 'ecab-taxi-booking-manager'); ?></option>
                        <option value="custom_message" <?php selected($price_display_type, 'custom_message'); ?>><?php esc_html_e('Show Custom Message', 'ecab-taxi-booking-manager'); ?></option>
                    </select>
                </div>
                <div class="mptbm_taxi_toggle_header">
                    <section>
                        <label class="label">
                            <div>
                                <h6><?php esc_html_e('Charge for Base to Dropoff?', 'ecab-taxi-booking-manager'); ?></h6>
                                <span class="desc"><?php esc_html_e('Enable to charge for distance/time from dropoff location back to base location', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                            <?php MP_Custom_Layout::switch_button('mptbm_charge_base_dropoff', $charge_base_dropoff == 'yes' ? 'checked' : ''); ?>
                        </label>
                    </section>
                </div>
            </div>

            <?php
                self::taxi_feature_add_remove( $post_id, $all_features );
            ?>

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
                        <i class="fa-solid fa-car"></i>
                        <div class="mptbm_taxi_feature_remove_icon"><i class="fa-solid fa-xmark"></i></div>
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
                    <h2>Vehicle Features</h2>
                    <p>Configure additional vehicle features and specifications.</p>
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
                    <div>Icon/Image</div>
                    <div>Label</div>
                    <div>Text</div>
                    <div>Action</div>
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
                        <i class="fa-solid fa-plus"></i> Add New Item
                    </button>
                </div>
            </div>


        </div>
    <?php }
    public static function date_configuration_set( $post_id ){ ?>
        <div class="mptbm_taxi_datetime" data-step="3">
            <div class="mptbm_container">
                <?php
                do_action( 'mptbm_date_and_advanced_settings', $post_id );
                ?>
            </div>
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
                    <h2 class="mptbm_taxi_ex_service_main_title">Extra Service</h2>
                    <p class="mptbm_taxi_ex_service_subtitle">Manage optional services and their pricing for trips.</p>
                </div>
                <div class="mptbm_taxi_ex_service_toggle_wrapper">
                    <label class="mptbm_taxi_ex_service_switch">
                        <input type="checkbox" id="mptbm_taxi_ex_service_master_toggle" name="display_mptbm_extra_services" <?php echo esc_attr($checked); ?>>
                        <span class="mptbm_taxi_ex_service_slider"></span>
                    </label>
                    <span class="mptbm_taxi_ex_service_toggle_label">ON</span>
                </div>
            </div>

            <div class="mptbm_taxi_ex_service_body" id="mptbm_taxi_ex_service_body" style="display: <?php echo esc_attr( $active );?>">
                <div class="mptbm_taxi_ex_service_filter_row">
                    <label>Select extra option:</label>
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
                        <th>Icon</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Price ($)</th>
                        <th>Qty Box Type</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody id="mptbm_taxi_ex_service_tbody">
                    <?php
                        self::extra_service_item( $post_id, $service_id );
                    ?>
                    </tbody>
                </table>

                <div class="mptbm_taxi_ex_service_footer">
                    <button id="mptbm_taxi_ex_service_add_btn" class="mptbm_taxi_ex_service_add_btn">+ Add New Service</button>
                </div>
            </div>
        </div>
    <?php }

    public static function service_table_display(){ ?>

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
                <textarea class="mptbm_taxi_ex_service_select" name="extra_service_description[]">
                    <?php echo esc_html( $description ); ?>
                </textarea>
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
    public static function extra_service_settings( $post_id ){
        wp_nonce_field( 'mptbm_extra_service_nonce', 'mptbm_extra_service_nonce' );
        ?>
        <div class="mptbm_taxi_extra" data-step="3">
            <div class="mptbm_ex_service_setting_container">
                <?php
                self::extra_service_display( $post_id );
                ?>
            </div>
        </div>
    <?php }

    public static function pricing_settings( $post_id ){
        $price_based = MP_Global_Function::get_post_info($post_id, 'mptbm_price_based');
        $distance_price = MP_Global_Function::get_post_info($post_id, 'mptbm_km_price');
        $time_price = MP_Global_Function::get_post_info($post_id, 'mptbm_hour_price');
        $fixed_map_price = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_map_price');
        $manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
        $fixed_zone_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_zone_price_info', []);
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
        <div class="mptbm_taxi_container mptbm_taxi_pricing_wrapper" data-step="2">
            <?php wp_nonce_field('mptbm_price_settings_action', 'mptbm_price_settings_nonce'); ?>
            <input type="hidden" name="mptbm_selected_operation_areas" id="mptbm_selected_operation_areas" value="<?php echo esc_html( $operation_area_str );?>">
            <div class="mptbm_taxi_pricing_header_tabs">
                <h3 class="mptbm_taxi_pricing_main_title">Select Pricing Model</h3>
                <div class="mptbm_taxi_pricing_tab_grid">

                    <div class="mptbm_taxi_pricing_tab_item active" data-id="inclusive">
                        <i class="fas fa-random"></i>
                        <div class="mptbm_taxi_pricing_tab_info">
                            <h4>Inclusive</h4>
                            <span>Multiple Models</span>
                        </div>
                    </div>

                    <div class="mptbm_taxi_pricing_tab_item " data-id="distance">
                        <i class="fas fa-map-marker-alt"></i>
                        <div class="mptbm_taxi_pricing_tab_info">
                            <h4>Distance</h4>
                            <span>Based on KM</span>
                        </div>
                    </div>
                    <div class="mptbm_taxi_pricing_tab_item" data-id="duration">
                        <i class="fas fa-clock"></i>
                        <div class="mptbm_taxi_pricing_tab_info">
                            <h4>Duration</h4>
                            <span>Based on time</span>
                        </div>
                    </div>

                </div>
            </div>
            <div class="mptbm_taxi_pricing_container">
                <div class="mptbm_taxi_pricing_inner_header">
                    <h3>Configure Pricing Rules</h3>
                    <p>Select only one active pricing rule for this taxi model.</p>
                </div>

                <div class="mptbm_taxi_pricing_group">

                    <div class="mptbm_taxi_pricing_item" id="row_distance">
                        <div class="mptbm_taxi_pricing_row_head">
                            <label class="mptbm_taxi_pricing_radio_toggle">
                                <input type="radio" name="mptbm_price_based" value="distance" class="mptbm_taxi_pricing_input" <?php checked($price_based, 'distance'); ?>>
                                <span class="mptbm_taxi_pricing_slider"></span>
                            </label>
                            <span class="mptbm_taxi_pricing_label"><i class="fas fa-map-marker-alt"></i> Distance</span>
                            <span class="mptbm_taxi_pricing_status_tag">ACTIVE</span>
                        </div>
                        <div class="mptbm_taxi_pricing_row_content">
                            <div class="mptbm_taxi_pricing_form_grid">
                                <div class="mptbm_taxi_pricing_field">
                                    <label>Price per KM</label>
                                    <input name="mptbm_km_price" type="text" value="<?php echo esc_attr( $distance_price );?>" placeholder="1.2">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mptbm_taxi_pricing_item" id="row_duration">
                        <div class="mptbm_taxi_pricing_row_head">
                            <label class="mptbm_taxi_pricing_radio_toggle">
                                <input type="radio" name="mptbm_price_based" value="duration" class="mptbm_taxi_pricing_input" <?php checked($price_based, 'duration'); ?>>
                                <span class="mptbm_taxi_pricing_slider"></span>
                            </label>
                            <span class="mptbm_taxi_pricing_label"><i class="fas fa-clock"></i> Duration</span>
                            <span class="mptbm_taxi_pricing_status_tag">OFF</span>
                        </div>
                        <div class="mptbm_taxi_pricing_row_content">
                            <div class="mptbm_taxi_pricing_form_grid">
                                <div class="mptbm_taxi_pricing_field">
                                    <label>Price per Hour</label>
                                    <input name="mptbm_hour_price" value="<?php echo esc_attr( $time_price );?>" type="text" placeholder="0.5">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mptbm_taxi_pricing_item" id="row_dist_dur">
                        <div class="mptbm_taxi_pricing_row_head">
                            <label class="mptbm_taxi_pricing_radio_toggle">
                                <input type="radio" name="mptbm_price_based" value="distance_duration" class="mptbm_taxi_pricing_input" <?php checked($price_based, 'distance_duration'); ?>>
                                <span class="mptbm_taxi_pricing_slider"></span>
                            </label>
                            <span class="mptbm_taxi_pricing_label"><i class="fas fa-bolt"></i> Distance + Duration</span>
                            <span class="mptbm_taxi_pricing_status_tag">OFF</span>
                        </div>
                        <div class="mptbm_taxi_pricing_row_content">
                            <div class="mptbm_taxi_pricing_form_grid">
                                <div class="mptbm_taxi_pricing_field">
                                    <label>Price per KM</label>
                                    <input name="mptbm_km_price" value="<?php echo esc_attr( $distance_price );?>" type="text" placeholder="1.00">
                                </div>
                                <div class="mptbm_taxi_pricing_field">
                                    <label>Price per Hour</label>
                                    <input name="mptbm_hour_price" value="<?php echo esc_attr( $time_price );?>" type="text" placeholder="0.20">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mptbm_taxi_pricing_item" id="row_hourly">
                        <div class="mptbm_taxi_pricing_row_head">
                            <label class="mptbm_taxi_pricing_radio_toggle">
                                <input type="radio" name="mptbm_price_based" value="fixed_hourly" class="mptbm_taxi_pricing_input" <?php checked($price_based, 'fixed_hourly'); ?>>
                                <span class="mptbm_taxi_pricing_slider"></span>
                            </label>
                            <span class="mptbm_taxi_pricing_label"><i class="fas fa-history"></i> Fixed Hourly</span>
                            <span class="mptbm_taxi_pricing_status_tag">OFF</span>
                        </div>
                        <div class="mptbm_taxi_pricing_row_content">
                            <div class="mptbm_taxi_pricing_form_grid">
                                <div class="mptbm_taxi_pricing_field">
                                    <label>Hourly Rate</label>
                                    <input name="mptbm_hour_price" type="text" value="<?php echo esc_attr( $time_price );?>" placeholder="20.00">
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="mptbm_taxi_pricing_item" id="row_operation_area">
                        <div class="mptbm_taxi_pricing_row_head">
                            <label class="mptbm_taxi_pricing_radio_toggle">
                                <input type="radio" name="mptbm_price_based" value="fixed_distance" class="mptbm_taxi_pricing_input" <?php checked($price_based, 'fixed_distance'); ?>>
                                <span class="mptbm_taxi_pricing_slider"></span>
                            </label>
                            <span class="mptbm_taxi_pricing_label"><i class="fas fa-pencil-alt"></i> Operation Area</span>
                            <span class="mptbm_taxi_pricing_status_tag">OFF</span>
                        </div>

                        <div class="mptbm_taxi_pricing_row_content">
                            <div class="mp_settings_area ">
                                <section>
                                    <label class="label">
                                        <div>
                                            <h6><?php esc_html_e('Select Operation Type', 'ecab-taxi-booking-manager'); ?></h6>
                                            <span class="desc"><?php esc_html_e('Choose the type of operation area', 'ecab-taxi-booking-manager'); ?></span>
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
                                <?php if( $selected_operation_type == 'geo-fence-operation-area-type' ){?>
                                    <section id="geo-fence-operation-area-section" class="<?php echo ($selected_operation_type == 'geo-fence-operation-area-type') ? 'mActive' : ''; ?>" data-collapse="#geo-fence-operation-area-type">
                                        <label class="label">
                                            <div>
                                                <h6><?php esc_html_e('Select Geo Fence Operation Area', 'ecab-taxi-booking-manager'); ?></h6>
                                                <span class="desc"><?php esc_html_e('Select a geo fence operation area', 'ecab-taxi-booking-manager'); ?></span>
                                            </div>
                                            <select class="formControl" name="mptbm_selected_operation_areas[]" id="mptbm_selected_geo_fence_area">
                                                <option value=""><?php esc_html_e('Select Geo Fence Area', 'ecab-taxi-booking-manager'); ?></option>
                                                <?php
                                                foreach ($all_operation_area_infos as $area_info) {
                                                    if ($area_info['operation_type'] == 'geo-fence-operation-area-type') {
                                                        $selected = in_array($area_info['post_id'], $selected_operation_areas) ? 'selected' : '';
                                                        echo '<option value="' . esc_attr($area_info['post_id']) . '" ' . $selected . '>' . esc_html(get_the_title($area_info['post_id'])) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </label>
                                    </section>
                                <?php } else{?>
                                <label>SELECT OPERATION AREAS — multiple allowed</label>
                                <div class="mptbm_taxi_pricing_area_pills">
                                    <?php foreach ($operation_area as $id => $name): ?>

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
                                <div class="mptbm_taxi_pricing_active_indicator">
                                    Active:
                                </div>
                            </div>
                        <?php }?>

                            <div class="mptbm_taxi_pricing_basic_fields">
                                <div class="mptbm_taxi_pricing_field_inline">
                                    <label>Price/KM <i class="fas fa-question-circle"></i> <span>Set Price per KM</span></label>
                                    <input name="mptbm_km_price" value="<?php echo esc_attr( $distance_price );?>" type="text" >
                                </div>
                                <div class="mptbm_taxi_pricing_field_inline">
                                    <label>Fixed with map price <span>Set the fixed price for map-based trips</span></label>
                                    <input name="mptbm_fixed_map_price" value="<?php echo esc_attr( $fixed_map_price );?>" type="text" placeholder="EX: 10">
                                </div>
                                <div class="mptbm_taxi_pricing_field_inline">
                                    <label>Price/Hour <i class="fas fa-question-circle"></i> <span>Set Price per Hour</span></label>
                                    <input name="mptbm_hour_price" type="text" value="<?php echo esc_attr( $time_price );?>">
                                </div>
                            </div>

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

                            <div class="mptbm_taxi_pricing_sub_section">
                                <div class="mptbm_taxi_pricing_sub_header">
                                    <h4>Fixed Map Route Overrides</h4>
                                    <p>Define fixed prices for specific routes when using "Fixed with Map" mode.</p>
                                </div>
                                <?php
                                self::render_fixed_with_map_price_rows($fixed_map_route_prices, $merged_location_area, 'mptbm_taxi_pricing_route_list', $location_zones );
                                ?>

                                <button type="button" class="mptbm_taxi_pricing_pink_btn mptbm_taxi_pricing_add_route_btn">+ Add New Route</button>
                            </div>
                        </div>
                    </div>

                    <div class="mptbm_taxi_pricing_item" id="row_manual">
                        <div class="mptbm_taxi_pricing_row_head">
                            <label class="mptbm_taxi_pricing_radio_toggle">
                                <input type="radio" name="mptbm_price_based" value="manual" class="mptbm_taxi_pricing_input" <?php checked($price_based, 'manual'); ?>>
                                <span class="mptbm_taxi_pricing_slider"></span>
                            </label>
                            <span class="mptbm_taxi_pricing_label"><i class="fas fa-route"></i> Manual Routes</span>
                            <span class="mptbm_taxi_pricing_status_tag">OFF</span>
                        </div>

                        <div class="mptbm_taxi_pricing_row_content">
                            <div class="mptbm_taxi_pricing_info_alert">
                                <i class="far fa-lightbulb"></i>
                                <span>Routes not covered here fall back to the active pricing model.</span>
                            </div>

                            <div class="mptbm_taxi_pricing_manual_list">
                                <?php self::render_location_price_rows( $terms_location_prices, $location_terms );?>

                            </div>

                            <div class="mptbm_taxi_pricing_add_action">
                                <button type="button" class="mptbm_taxi_pricing_add_route_full_btn">+ Add Route</button>
                            </div>
                        </div>
                    </div>

                    <div class="mptbm_taxi_pricing_item" id="row_zone">
                        <div class="mptbm_taxi_pricing_row_head">
                            <label class="mptbm_taxi_pricing_radio_toggle">
                                <input type="radio" name="mptbm_price_based" value="fixed_zone" class="mptbm_taxi_pricing_input" <?php checked($price_based, 'fixed_zone'); ?>>
                                <span class="mptbm_taxi_pricing_slider"></span>
                            </label>
                            <span class="mptbm_taxi_pricing_label"><i class="fas fa-building"></i>Fixed Zone</span>
                            <span class="mptbm_taxi_pricing_status_tag">OFF</span>
                        </div>
                        <div class="mptbm_taxi_pricing_row_content">
                            <div class="mptbm_taxi_pricing_sub_section">
                                <div class="mptbm_taxi_pricing_sub_header">
                                    <h4>Fixed Map Route Overrides</h4>
                                    <p>Define fixed prices for specific routes when using "Fixed with Map" mode.</p>
                                </div>
                                <?php
                                self::render_fixed_with_map_price_rows($fixed_map_route_prices, $merged_location_area, 'mptbm_taxi_pricing_fixed_zone_list', $location_zones );
                                ?>

                                <button type="button" class="mptbm_taxi_pricing_pink_btn mptbm_taxi_pricing_add_zone_btn">+ Add New Route</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>


            <div class="mptbm_ex_service_setting_container">
                <?php
                wp_nonce_field( 'mptbm_extra_service_nonce', 'mptbm_extra_service_nonce' );
                self::extra_service_display( $post_id );
                ?>
            </div>
        </div>

    <?php }

    public static function render_fixed_with_map_price_rows( $fixed_map_route_prices, $merged_location_area, $append_body, $location_zones ) {

//        error_log( print_r( [ '$fixed_map_route_prices' =>$fixed_map_route_prices, '$merged_location_area' => $merged_location_area ], true ) );
        ?>
        <table class="mptbm_taxi_pricing_table">
            <thead>
            <tr>
                <th>Start Zone *</th>
                <th>End Zone *</th>
                <th>Price *</th>
                <th>Action</th>
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

    public static function render_location_price_rows($terms_location_prices, $location_terms) {
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
                                placeholder="e.g., 250 - F"
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
                        <option value="">Start city...</option>
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
                    <input name="mptbm_location_terms_price[]" type="text" placeholder="e.g., 250 - F">
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

        if ($pagenow === 'post.php' && isset($_GET['post'])) {

            $post_id = intval($_GET['post']);

            if (get_post_type($post_id) === 'mptbm_rent') {
                wp_redirect(admin_url('admin.php?page=mptbm-rent-edit&post_id=' . $post_id));
                exit;
            }
        }
    }

}

    new MPTBM_Rent_Custom_Editor();
}