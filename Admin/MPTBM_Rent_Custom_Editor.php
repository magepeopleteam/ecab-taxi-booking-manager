<?php

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
            <h1><?php echo $post_id ? 'Edit Rent' : 'Add Rent'; ?></h1>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="save_mptbm_rent">
                <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">

                <?php wp_nonce_field('save_mptbm_rent_nonce'); ?>

                <div class="mptbm_taxi_wrapper">
                    <div class="mptbm_taxi_stepper">
                        <div class="mptbm_taxi_step mptbm_taxi_active" data-step="1">
                            <div class="mptbm_taxi_icon"><i class="fas fa-clipboard-list"></i></div>
                            <div class="mptbm_taxi_label">General Information</div>
                            <div class="mptbm_taxi_subtext">Step 1 of 5</div>
                        </div>
                        <div class="mptbm_taxi_line"></div>
                        <div class="mptbm_taxi_step" data-step="2">
                            <div class="mptbm_taxi_icon"><i class="fas fa-sack-dollar"></i></div>
                            <div class="mptbm_taxi_label">Pricing Configuration</div>
                            <div class="mptbm_taxi_subtext">Step 2 of 5</div>
                        </div>
                        <div class="mptbm_taxi_line"></div>
                        <div class="mptbm_taxi_step" data-step="3">
                            <div class="mptbm_taxi_icon"><i class="fas fa-wand-magic-sparkles"></i></div>
                            <div class="mptbm_taxi_label">Extra Services</div>
                            <div class="mptbm_taxi_subtext">Step 3 of 5</div>
                        </div>
                        <div class="mptbm_taxi_line"></div>
                        <div class="mptbm_taxi_step" data-step="4">
                            <div class="mptbm_taxi_icon"><i class="far fa-calendar-alt"></i></div>
                            <div class="mptbm_taxi_label">Operational Date Time</div>
                            <div class="mptbm_taxi_subtext">Step 4 of 5</div>
                        </div>
                        <div class="mptbm_taxi_line"></div>
                        <div class="mptbm_taxi_step" data-step="5">
                            <div class="mptbm_taxi_icon"><i class="fas fa-cog"></i></div>
                            <div class="mptbm_taxi_label">Advanced</div>
                            <div class="mptbm_taxi_subtext">Step 5 of 5</div>
                        </div>
                    </div>

                    <div class="mptbm_taxi_container">

                        <div class="mptbm_taxi_section">
                            <h3 class="mptbm_taxi_section_title">General Date Configuration</h3>
                            <p class="mptbm_taxi_section_desc">Here you can configure general date</p>

                            <div class="mptbm_taxi_grid">
                                <div class="mptbm_taxi_field">
                                    <label>Maximum Passenger</label>
                                    <p class="mptbm_taxi_help">Filters services by the maximum number of passengers allowed</p>
                                    <input name="mptbm_maximum_passenger" type="text" placeholder="EX:4">
                                </div>
                                <div class="mptbm_taxi_field">
                                    <label>Maximum Bag</label>
                                    <p class="mptbm_taxi_help">Filters services by the maximum number of bags allowed</p>
                                    <input name="mptbm_maximum_bag" type="text" placeholder="EX:4">
                                </div>
                                <div class="mptbm_taxi_field">
                                    <label>Extra Info</label>
                                    <p class="mptbm_taxi_help">Add any additional information about this vehicle that you want to display to customers</p>
                                    <input name="mptbm_maximum_hand_luggage" type="text" placeholder="EX:2">
                                </div>
                                <div class="mptbm_taxi_field">
                                    <label>Capacity</label>
                                    <p class="mptbm_taxi_help">Number of passengers</p>
                                    <textarea name="mptbm_extra_info" rows="4" placeholder="Enter additional information about this vehicle..."></textarea>
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
                                    <input name="mptbm_initial_price" type="text" value="50">
                                </div>
                                <div class="mptbm_taxi_field">
                                    <label>Minimum Price</label>
                                    <p class="mptbm_taxi_help">Floor fare applied when calculated price is lower</p>
                                    <input name="mptbm_min_price" type="text" value="60">
                                </div>
                                <div class="mptbm_taxi_field">
                                    <label>Return Minimum Price</label>
                                    <p class="mptbm_taxi_help">Minimum fare applied on return trips</p>
                                    <input name="mptbm_min_price_return" type="text" placeholder="e.g., 40 - Min fare for return">
                                </div>
                                <div class="mptbm_taxi_field">
                                    <label>Return Discount</label>
                                    <p class="mptbm_taxi_help">Discount applied to return trips — fixed or %</p>
                                    <input name="mptbm_return_discount" type="text" placeholder="e.g., 10 - Discount amount or %">
                                </div>
                                <div class="mptbm_taxi_field">
                                    <label>Waiting Time Price/Hour</label>
                                    <p class="mptbm_taxi_help">Specifies the price charged per hour for waiting time</p>
                                    <input name="mptbm_waiting_price" type="text" placeholder="EX:10">
                                </div>
                            </div>
                        </div>

                        <div class="mptbm_taxi_section mptbm_taxi_toggle_box">
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
                                        <option value="">Select Location</option>
                                        <option value="25">
                                            mohammad pur bus stand
                                        </option>
                                    </select>
                                </div>
                                <div class="mptbm_taxi_field">
                                    <label>Price per KM</label>
                                    <p class="mptbm_taxi_help">Enter the price per kilometer from base location</p>
                                    <input name="mptbm_base_price_km" type="number" placeholder="1.5">
                                </div>

                                <div class="mptbm_taxi_field">
                                    <label>Price per Hour</label>
                                    <p class="mptbm_taxi_help">Enter the price per hour from base location</p>
                                    <input name="mptbm_base_price_hour" type="number" placeholder="10 ">
                                </div>
                                <div class="mptbm_taxi_field">
                                    <label>Minimum Threshold (Distance)</label>
                                    <p class="mptbm_taxi_help">Distance free of charge from base price location</p>
                                    <input name="mptbm_base_min_threshold" type="number" placeholder="1">
                                </div>
                            </div>
                        </div>

                        <div class="mptbm_taxi_section mptbm_taxi_toggle_box">
                            <div class="mptbm_taxi_toggle_header">
                                <div class="mptbm_taxi_toggle_info">
                                    <label class="mptbm_taxi_switch">
                                        <input name="mptbm_charge_base_pickup" type="checkbox" class="mptbm_taxi_toggle_trigger">
                                        <span class="mptbm_taxi_slider"></span>
                                    </label>
                                    <div class="mptbm_taxi_toggle_text">
                                        <strong>Charge for Base to Pickup?</strong>
                                        <p>Enable to charge for distance/time from base location to pickup location</p>
                                    </div>
                                </div>
                                <span class="mptbm_taxi_status_badge mptbm_taxi_off">OFF</span>
                            </div>
                        </div>

                        <div class="mptbm_taxi_section mptbm_taxi_toggle_box">
                            <div class="mptbm_taxi_toggle_header">
                                <div class="mptbm_taxi_toggle_info">
                                    <label class="mptbm_taxi_switch">
                                        <input name="mptbm_charge_base_dropoff" type="checkbox" class="mptbm_taxi_toggle_trigger">
                                        <span class="mptbm_taxi_slider"></span>
                                    </label>
                                    <div class="mptbm_taxi_toggle_text">
                                        <strong>Charge for Base to Dropoff?</strong>
                                        <p>Enable to charge for distance/time from dropoff location back to base location</p>
                                    </div>
                                </div>
                                <span class="mptbm_taxi_status_badge mptbm_taxi_off">OFF</span>
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
                                    <option value="normal" selected="selected">Normal Price</option>
                                    <option value="zero">Show as Zero (0.00)</option>
                                    <option value="custom_message">Show Custom Message</option>
                                </select>
                            </div>
                        </div>

                    </div>

                    <div class="mptbm_taxi_footer">
                        <button class="mptbm_taxi_btn_prev">← Previous</button>
                        <span class="mptbm_taxi_step_counter">Step 1 of 5</span>
                        <button class="mptbm_taxi_btn_next">Next →</button>
                    </div>
                </div>

                <?php submit_button($post_id ? 'Update' : 'Publish'); ?>
            </form>
        </div>
        <?php
    }

    // 3. Save / Update post
    public function save_post() {

        if (
            !isset($_POST['_wpnonce']) ||
            !wp_verify_nonce($_POST['_wpnonce'], 'save_mptbm_rent_nonce')
        ) {
            wp_die('Security check failed');
        }

        error_log( print_r( [ '$_POST' => $_POST ], true ) );

        $post_id = intval($_POST['post_id']);
        $title   = sanitize_text_field($_POST['title']);

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