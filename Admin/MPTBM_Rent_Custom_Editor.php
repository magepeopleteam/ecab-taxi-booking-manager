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

                    self::pricing_set( $post_id );
                    ?>

                    <div class="mptbm_taxi_extra" data-step="3">
                        <div class="mptbm_ex_service_setting_container">
                            <div class="mptbm_ex_service_setting_header">
                                <h2>Extra Services</h2>
                                <p>Add additional services that customers can book with this taxi</p>
                            </div>

                            <div id="mptbm_ex_service_setting_list">
                                <div class="mptbm_ex_service_setting_row">
                                    <div class="mptbm_ex_service_setting_field">
                                        <label>Service Name</label>
                                        <input type="text" placeholder="e.g., Airport Assistance - Service name">
                                    </div>
                                    <div class="mptbm_ex_service_setting_field mptbm_flex_grow">
                                        <label>Description</label>
                                        <input type="text" placeholder="e.g., Help with luggage and directions">
                                    </div>
                                    <div class="mptbm_ex_service_setting_field mptbm_small">
                                        <label>Price</label>
                                        <input type="text" placeholder="e.g., 50 - Service">
                                    </div>
                                    <div class="mptbm_ex_service_setting_field mptbm_qty">
                                        <label>Qty</label>
                                        <input type="number" value="1">
                                    </div>
                                    <button type="button" class="mptbm_ex_service_setting_remove">&times;</button>
                                </div>
                            </div>

                            <div class="mptbm_ex_service_setting_footer">
                                <button type="button" id="mptbm_ex_service_setting_add_btn">+ Add Extra Service</button>
                            </div>
                        </div>
                    </div>


                    <div class="mptbm_taxi_datetime" data-step="4">
                        <div class="mptbm_container">
                            <div class="mptbm_card">
                                <div class="mptbm_section_header">
                                    <h3>General Date Configuration</h3>
                                    <p>Here you can configure general date</p>
                                </div>

                                <div class="mptbm_form_group">
                                    <label>Date Type <span>*</span></label>
                                    <small>Specifies the date type: "Repeated" for recurring dates, or "Particular" for a specific date</small>
                                    <div class="mptbm_select_wrapper">
                                        <select>
                                            <option>Repeated</option>
                                            <option>Particular</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mptbm_form_group">
                                    <label>Repeated Start Date <span>*</span></label>
                                    <small>Sets the start date for recurring services</small>
                                    <input type="text" placeholder="mm/dd/yyyy">
                                </div>

                                <div class="mptbm_form_group">
                                    <label>Repeated after <span>*</span></label>
                                    <small>Defines the number of days after which the service or event will repeat</small>
                                    <input type="number" value="1">
                                </div>

                                <div class="mptbm_form_group">
                                    <label>Maximum Advanced Day Booking <span>*</span></label>
                                    <small>Sets the maximum number of days in advance a booking can be made</small>
                                    <input type="number" value="60">
                                </div>

                                <div class="mptbm_switch_wrapper">
                                    <label class="mptbm_switch">
                                        <input type="checkbox" checked>
                                        <span class="mptbm_slider"></span>
                                    </label>
                                    <div class="mptbm_switch_text">
                                        <strong>Make Transport Available For 24 Hours</strong>
                                        <p>By default slider is ON but you can keep it off by switching this option</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mptbm_card">
                                <div class="mptbm_section_header">
                                    <h3>Schedule Date Configuration</h3>
                                    <p>Here you can configure Schedule date.</p>
                                </div>
                                <div class="mptbm_schedule_container">
                                    <div class="mptbm_schedule_header">
                                        <h3>Schedule Date Configuration</h3>
                                        <p>Here you can configure Schedule date.</p>
                                    </div>

                                    <table class="mptbm_schedule_table">
                                        <thead>
                                        <tr>
                                            <th style="width: 20%;">Day</th>
                                            <th style="width: 35%;">Start Time</th>
                                            <th style="width: 10%; text-align: center;">To</th>
                                            <th style="width: 35%;">End Time</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr>
                                            <td>Monday</td>
                                            <td>
                                                <div class="mptbm_custom_select">
                                                    <select>
                                                        <option>Default</option>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="mptbm_to_text">To</td>
                                            <td>
                                                <div class="mptbm_custom_select">
                                                    <select>
                                                        <option>Default</option>
                                                    </select>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr class="mptbm_row_active">
                                            <td>Tuesday</td>
                                            <td>
                                                <div class="mptbm_custom_select mptbm_select_focused">
                                                    <select>
                                                        <option>Select...</option>
                                                        <option>Default</option>
                                                        <option>Please select</option>
                                                        <option>12:00 am</option>
                                                        <option>1:00 am</option>
                                                        <option>2:00 am</option>
                                                        <option>6:00 am</option>
                                                        <option>12:00 pm</option>
                                                        <option>6:00 pm</option>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="mptbm_to_text">To</td>
                                            <td>
                                                <div class="mptbm_custom_select">
                                                    <select><option>Select...</option></select>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr>
                                            <td>Wednesday</td>
                                            <td><div class="mptbm_custom_select"><select><option>Select...</option></select></div></td>
                                            <td class="mptbm_to_text">To</td>
                                            <td><div class="mptbm_custom_select"><select><option>Select...</option></select></div></td>
                                        </tr>
                                        <tr>
                                            <td>Thursday</td>
                                            <td><div class="mptbm_custom_select"><select><option>Select...</option></select></div></td>
                                            <td class="mptbm_to_text">To</td>
                                            <td><div class="mptbm_custom_select"><select><option>Select...</option></select></div></td>
                                        </tr>
                                        <tr>
                                            <td>Friday</td>
                                            <td><div class="mptbm_custom_select"><select><option>Select...</option></select></div></td>
                                            <td class="mptbm_to_text">To</td>
                                            <td><div class="mptbm_custom_select"><select><option>Select...</option></select></div></td>
                                        </tr>
                                        <tr>
                                            <td>Saturday</td>
                                            <td><div class="mptbm_custom_select"><select><option>Select...</option></select></div></td>
                                            <td class="mptbm_to_text">To</td>
                                            <td><div class="mptbm_custom_select"><select><option>Select...</option></select></div></td>
                                        </tr>
                                        <tr>
                                            <td>Sunday</td>
                                            <td><div class="mptbm_custom_select"><select><option>Select...</option></select></div></td>
                                            <td class="mptbm_to_text">To</td>
                                            <td><div class="mptbm_custom_select"><select><option>Select...</option></select></div></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="mptbm_card">
                                <div class="mptbm_section_header">
                                    <h3>Off Days & Dates Configuration</h3>
                                    <p>Here you can configure Off Days & Dates.</p>
                                </div>

                                <div class="mptbm_form_group">
                                    <label>Off Day</label>
                                    <small>Select checkbox for off day</small>
                                    <div class="mptbm_checkbox_row">
                                        <label><input type="checkbox"> Monday</label>
                                        <label><input type="checkbox"> Tuesday</label>
                                        <label><input type="checkbox"> Wednesday</label>
                                        <label><input type="checkbox"> Thursday</label>
                                        <label><input type="checkbox"> Friday</label>
                                        <label><input type="checkbox"> Saturday</label>
                                        <label><input type="checkbox"> Sunday</label>
                                    </div>
                                </div>

                                <div class="mptbm_form_group">
                                    <label>Off Dates</label>
                                    <small>Add off dates</small>
                                    <div id="mptbm_off_dates_container">
                                        <div class="mptbm_date_input_row">
                                            <input type="text" placeholder="mm/dd/yyyy">
                                            <button class="mptbm_btn_remove">Remove</button>
                                        </div>
                                    </div>
                                    <div class="mptbm_btn_container">
                                        <button id="mptbm_add_off_date" class="mptbm_btn_orange">+ Add New Off Date</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="mptbm_taxi_advanced" data-step="5">Advanced</div>

                    <div class="mptbm_taxi_footer">
                        <button class="mptbm_taxi_btn_prev">← Previous</button>
                        <span class="mptbm_taxi_step_counter">Step 1 of 5</span>
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
                <div class="mptbm_taxi_subtext">Step 1 of 5</div>
            </div>
            <div class="mptbm_taxi_line"></div>
            <div class="mptbm_taxi_step" data-step="2" data-icon="fas fa-dollar-sign">
                <div class="mptbm_taxi_icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="mptbm_taxi_label">Pricing Configuration</div>
                <div class="mptbm_taxi_subtext">Step 2 of 5</div>
            </div>
            <div class="mptbm_taxi_line"></div>
            <div class="mptbm_taxi_step" data-step="3" data-icon="fas fa-magic">
                <div class="mptbm_taxi_icon"><i class="fas fa-magic"></i></div>
                <div class="mptbm_taxi_label">Extra Services</div>
                <div class="mptbm_taxi_subtext">Step 3 of 5</div>
            </div>
            <div class="mptbm_taxi_line"></div>
            <div class="mptbm_taxi_step" data-step="4" data-icon="fas fa-calendar-alt">
                <div class="mptbm_taxi_icon"><i class="far fa-calendar-alt"></i></div>
                <div class="mptbm_taxi_label">Operational Date Time</div>
                <div class="mptbm_taxi_subtext">Step 4 of 5</div>
            </div>
            <div class="mptbm_taxi_line"></div>
            <div class="mptbm_taxi_step" data-step="5" data-icon="fas fa-cog">
                <div class="mptbm_taxi_icon"><i class="fas fa-cog"></i></div>
                <div class="mptbm_taxi_label">Advanced</div>
                <div class="mptbm_taxi_subtext">Step 5 of 5</div>
            </div>
        </div>
    <?php }

    public static function general_information_set( $post_id ){ ?>
        <div class="mptbm_taxi_container" data-step="1" >

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
    <?php }
    public static function pricing_set( $post_id ){
        $price_based = MP_Global_Function::get_post_info( $post_id, 'mptbm_price_based');
//        error_log( print_r( [ '$post_id' => $post_id ], true ) );
        ?>
        <div class="mptbm_taxi_container mptbm_taxi_pricing_wrapper" data-step="2">
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
                                    <input name="mptbm_km_price" type="text" placeholder="1.2">
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
                                    <input name="mptbm_hour_price" type="text" placeholder="0.5">
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
                                    <input name="mptbm_km_price" type="text" placeholder="1.00">
                                </div>
                                <div class="mptbm_taxi_pricing_field">
                                    <label>Price per Hour</label>
                                    <input name="mptbm_hour_price" type="text" placeholder="0.20">
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
                                    <input name="mptbm_hour_price" type="text" placeholder="20.00">
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

                            <div class="mptbm_taxi_pricing_selection_group">
                                <label>SELECT OPERATION AREAS — multiple allowed</label>
                                <div class="mptbm_taxi_pricing_area_pills">
                                    <button class="mptbm_taxi_pricing_pill selected"><i class="fas fa-check"></i> Dhaka</button>
                                    <button class="mptbm_taxi_pricing_pill selected"><i class="fas fa-check"></i> Chittagong</button>
                                    <button class="mptbm_taxi_pricing_pill">Sylhet</button>
                                    <button class="mptbm_taxi_pricing_pill">Rajshahi</button>
                                    <button class="mptbm_taxi_pricing_pill">Khulna</button>
                                    <button class="mptbm_taxi_pricing_pill">Barisal</button>
                                </div>
                                <div class="mptbm_taxi_pricing_active_indicator">
                                    Active: <span>Dhaka</span> <span>Chittagong</span>
                                </div>
                            </div>

                            <div class="mptbm_taxi_pricing_basic_fields">
                                <div class="mptbm_taxi_pricing_field_inline">
                                    <label>Price/KM <i class="fas fa-question-circle"></i> <span>Set Price per KM</span></label>
                                    <input name="mptbm_km_price" type="text" value="1.2">
                                </div>
                                <div class="mptbm_taxi_pricing_field_inline">
                                    <label>Fixed with map price <span>Set the fixed price for map-based trips</span></label>
                                    <input name="mptbm_fixed_map_price" type="text" placeholder="EX: 10">
                                </div>
                                <div class="mptbm_taxi_pricing_field_inline">
                                    <label>Price/Hour <i class="fas fa-question-circle"></i> <span>Set Price per Hour</span></label>
                                    <input name="mptbm_hour_price" type="text" value="10">
                                </div>
                            </div>

                            <div class="mptbm_taxi_pricing_sub_section">
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
                            </div>

                            <div class="mptbm_taxi_pricing_sub_section">
                                <div class="mptbm_taxi_pricing_sub_header">
                                    <h4>Fixed Map Route Overrides</h4>
                                    <p>Define fixed prices for specific routes when using "Fixed with Map" mode.</p>
                                </div>
                                <table class="mptbm_taxi_pricing_table">
                                    <thead>
                                    <tr>
                                        <th>Start Zone *</th>
                                        <th>End Zone *</th>
                                        <th>Price *</th>
                                        <th>Action</th>
                                    </tr>
                                    </thead>
                                    <tbody class="mptbm_taxi_pricing_route_list">
                                    <tr>
                                        <td><select name="mptbm_fixed_map_route_start_location[]"><option>mohammad pur bus stand (Location)</option></select></td>
                                        <td><select name="mptbm_fixed_map_route_end_location[]"><option>dhaka jone (Operation Area)</option></select></td>
                                        <td><input name="mptbm_fixed_map_route_price[]" type="text" placeholder="EX: 10"></td>
                                        <td>
                                            <div class="mptbm_taxi_pricing_table_actions">
                                                <button class="mptbm_taxi_pricing_del_icon"><i class="fas fa-trash"></i></button>
                                                <button class="mptbm_taxi_pricing_expand_icon"><i class="fas fa-expand-arrows-alt"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
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
                                <div class="mptbm_taxi_pricing_route_row">
                                    <div class="mptbm_taxi_pricing_select_wrap">
                                        <select name="mptbm_terms_start_location[]"><option>Chittagong</option></select>
                                    </div>
                                    <div class="mptbm_taxi_pricing_select_wrap">
                                        <select name="mptbm_terms_end_location[]"><option>End city...</option></select>
                                    </div>
                                    <div class="mptbm_taxi_pricing_input_wrap">
                                        <input name="mptbm_location_terms_price[]" type="text" placeholder="e.g., 250 - F">
                                    </div>
                                    <div class="mptbm_taxi_pricing_action_btns">
                                        <button type="button" class="mptbm_taxi_pricing_clone_btn"><i class="far fa-copy"></i></button>
                                        <button type="button" class="mptbm_taxi_pricing_delete_btn"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>

                                <div class="mptbm_taxi_pricing_route_row">
                                    <div class="mptbm_taxi_pricing_select_wrap">
                                        <select name="mptbm_terms_start_location[]"><option>Chittagong</option></select>
                                    </div>
                                    <div class="mptbm_taxi_pricing_select_wrap">
                                        <select name="mptbm_terms_end_location[]"><option>Rajshahi</option></select>
                                    </div>
                                    <div class="mptbm_taxi_pricing_input_wrap">
                                        <input name="mptbm_location_terms_price[]" type="text" value="111">
                                    </div>
                                    <div class="mptbm_taxi_pricing_action_btns">
                                        <button type="button" class="mptbm_taxi_pricing_clone_btn"><i class="far fa-copy"></i></button>
                                        <button type="button" class="mptbm_taxi_pricing_delete_btn"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
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
                            <div class="mptbm_taxi_pricing_form_grid">
                                <div class="mptbm_taxi_pricing_field">
                                    <label>Flat Fare per Zone</label>
                                    <input type="text" placeholder="10.00">
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    <?php }

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