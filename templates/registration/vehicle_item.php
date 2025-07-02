<?php
/*
 * @Author 		engr.sumonazma@gmail.com
 * Copyright: 	mage-people.com
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly

$post_id = $post_id ?? '';
$original_price_based = $price_based ?? '';
$feature_class = ''; // Default empty value
if (MP_Global_Function::get_settings('mptbm_general_settings', 'enable_filter_via_features') == 'yes') {
    $max_passenger = MP_Global_Function::get_post_info($post_id, 'mptbm_maximum_passenger');
    $max_bag = MP_Global_Function::get_post_info($post_id, 'mptbm_maximum_bag');
    if ($max_passenger != '' && $max_bag != '') {
        $feature_class = 'feature_passenger_'.$max_passenger.'_feature_bag_'.$max_bag.'_post_id_'.$post_id;
    }else{
        $feature_class = '';
    }
}

// Get display features setting
$display_features = MP_Global_Function::get_post_info($post_id, 'display_mptbm_features', 'on');

$all_features = MP_Global_Function::get_post_info($post_id, 'mptbm_features');

$fixed_time = $fixed_time ?? 0;
$start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
$start_date = $start_date ? date('Y-m-d', strtotime($start_date)) : '';
$start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
$all_dates = MPTBM_Function::get_date($post_id);

// Check if inventory is enabled
$enable_inventory = MP_Global_Function::get_post_info($post_id, 'mptbm_enable_inventory', 'no');
$total_quantity = 1;
$available_quantity = 1;

// Check if seat plan is enabled
$enable_seat_plan = MP_Global_Function::get_post_info($post_id, 'mptbm_enable_seat_plan', 'no');
$seat_type = MP_Global_Function::get_post_info($post_id, 'mptbm_seat_type', 'without_seat_plan');
$total_seat = MP_Global_Function::get_post_info($post_id, 'mptbm_total_seat', 1);
$seat_rows = MP_Global_Function::get_post_info($post_id, 'mptbm_seat_rows', 2);
$seat_columns = MP_Global_Function::get_post_info($post_id, 'mptbm_seat_columns', 2);
$has_seat_plan = ($enable_seat_plan == 'yes' && $enable_inventory == 'no');
$has_visual_seat_plan = ($has_seat_plan && $seat_type == 'with_seat_plan');

// Skip rendering this vehicle if seat plan is enabled and it's a return trip
if ($has_seat_plan && $two_way == 2) {
    return; // Don't render this vehicle for return trips
}

if ($enable_inventory == 'yes') {
    // Get booking interval time from transport settings
    $booking_interval_time = MP_Global_Function::get_post_info($post_id, 'mptbm_booking_interval_time', 0);

    // Calculate available quantity based on overlapping bookings
    $total_quantity = MP_Global_Function::get_post_info($post_id, 'mptbm_quantity', 1);
    $available_quantity = $total_quantity;
    if ($start_date && $start_time) {
        // Format the time properly
        $hours = floor($start_time);
        $minutes = ($start_time - $hours) * 60;
        $formatted_time = sprintf('%02d:%02d', $hours, $minutes);
        
        // Convert start date and time to timestamp
        $start_datetime = strtotime($start_date . ' ' . $formatted_time);
        
        // Calculate the time range to check (interval time before and after) - now in minutes
        $interval_before = $start_datetime - ($booking_interval_time * 60); // Convert minutes to seconds
        $interval_after = $start_datetime + ($booking_interval_time * 60);
        
        

        // Get all bookings that could overlap with our time range
        $query = new WP_Query([
            'post_type' => 'mptbm_booking',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'mptbm_id',
                    'value' => $post_id,
                    'compare' => '='
                ]
            ]
        ]);

        if ($query->have_posts()) {
           
            
            while ($query->have_posts()) {
                $query->the_post();
                $booking_datetime = get_post_meta(get_the_ID(), 'mptbm_date', true);
                $booking_transport_quantity = get_post_meta(get_the_ID(), 'mptbm_transport_quantity', true);
                $booking_transport_quantity = $booking_transport_quantity ? absint($booking_transport_quantity) : 1;
                
                // Convert booking datetime to timestamp
                $booking_timestamp = strtotime($booking_datetime);
                
                
                
                // Check if booking time falls within our interval range
                $is_in_range = ($booking_timestamp >= $interval_before && $booking_timestamp <= $interval_after);
                
                
                if ($is_in_range) {
                    $available_quantity -= $booking_transport_quantity;
                    
                }
                
            }
           
        }
        wp_reset_postdata();

        
    }
}

$mptbm_enable_view_search_result_page  = MP_Global_Function::get_settings('mptbm_general_settings', 'enable_view_search_result_page');
if ($mptbm_enable_view_search_result_page == '') {
    $hidden_class = 'mptbm_booking_item_hidden';
} else {
    $hidden_class = '';
}
if (sizeof($all_dates) > 0 && in_array($start_date, $all_dates)) {
    $distance = $distance ?? (isset($_COOKIE['mptbm_distance']) ? absint($_COOKIE['mptbm_distance']) : '');
    $duration = $duration ?? (isset($_COOKIE['mptbm_duration']) ? absint($_COOKIE['mptbm_duration']) : '');
    $label = $label ?? MPTBM_Function::get_name();
    $start_place = $start_place ?? isset($_POST['start_place']) ? sanitize_text_field($_POST['start_place']) : '';
    $end_place = $end_place ?? isset($_POST['end_place']) ? sanitize_text_field($_POST['end_place']) : '';
    $two_way = $two_way ?? 1;
    $waiting_time = $waiting_time ?? 0;
    $location_exit = MPTBM_Function::location_exit($post_id, $start_place, $end_place);
    if ($location_exit && $post_id) {
        $thumbnail = MP_Global_Function::get_image_url($post_id);
        $price = MPTBM_Function::get_price($post_id, $distance, $duration, $start_place, $end_place, $waiting_time, $two_way, $fixed_time);

        // Get price display type and custom message
        $price_display_type = MP_Global_Function::get_post_info($post_id, 'mptbm_price_display_type', 'normal');
        $custom_message = MP_Global_Function::get_post_info($post_id, 'mptbm_custom_price_message', '');

        // Handle price display based on display type
        if ($price_display_type === 'custom_message' && $custom_message) {
            $price_display = '<div class="mptbm-custom-price-message">' . wp_kses_post($custom_message) . '</div>';
            $raw_price = 0; // Set raw price to 0 for custom message
        } else {
            $wc_price = MP_Global_Function::wc_price($post_id, $price);
            $raw_price = MP_Global_Function::price_convert_raw($wc_price);
            $price_display = $wc_price;
        }

        $display_features = MP_Global_Function::get_post_info($post_id, 'display_mptbm_features', 'on');
        $all_features = MP_Global_Function::get_post_info($post_id, 'mptbm_features');
        
        // Get extra info for this vehicle
        $extra_info = MP_Global_Function::get_post_info($post_id, 'mptbm_extra_info', '');
        $has_extra_info = !empty(trim($extra_info));
        
        // Check if we need seat selection or extra info dropdown
        $has_dropdown_content = $has_extra_info || ($has_seat_plan && class_exists('MPTBM_Plugin_Pro'));
?>
        <div class="mptbm-vehicle-wrapper" style="width: 100%; display: block;">
            <div class="_dLayout_dFlex mptbm_booking_item <?php echo $has_dropdown_content ? 'mptbm-has-dropdown-content' : ''; ?> <?php echo 'mptbm_booking_item_' . $post_id; ?> <?php echo $hidden_class; ?> <?php echo $feature_class; ?>" data-placeholder <?php echo $has_dropdown_content ? 'style="border-bottom: 2px solid var(--color_theme); margin-bottom: 0; border-radius: 8px 8px 0 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"' : ''; ?>>
                <div class="_max_200_mR">
                    <div class="bg_image_area"  data-placeholder>
                        <div data-bg-image="<?php echo esc_attr($thumbnail); ?>"></div>
                    </div>
                </div>
                <div class="fdColumn _fullWidth mptbm_list_details">
                    <h5><?php echo esc_html(get_the_title($post_id)); ?></h5>
                    <div class="justifyBetween _mT_xs">
                        <?php if ($display_features == 'on' && is_array($all_features) && sizeof($all_features) > 0) { ?>
                            <ul class="list_inline_two">
                                <?php
                                foreach ($all_features as $features) {
                                    $label = array_key_exists('label', $features) ? $features['label'] : '';
                                    $text = array_key_exists('text', $features) ? $features['text'] : '';
                                    $icon = array_key_exists('icon', $features) ? $features['icon'] : '';
                                    $image = array_key_exists('image', $features) ? $features['image'] : '';
                                ?>
                                    <li>
                                        <?php if ($icon) { ?>
                                            <span class="<?php echo esc_attr($icon); ?> _mR_xs"></span>
                                        <?php } ?>
                                        <?php echo esc_html($label); ?>&nbsp;:&nbsp;<?php echo esc_html($text); ?>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } else { ?>
                            <div></div>
                        <?php } ?>
                        <div class="_min_150_mL_xs" style="position:relative;">
                            <div class="mptbm-tier-pricing-savings-ticket-container">
                            <?php 
                            // Calculate and display tier pricing savings if applicable
                            if (class_exists('MPTBM_Distance_Tier_Pricing')) {
                                $tier_pricing_enabled = get_post_meta($post_id, 'mptbm_distance_tier_enabled', true);
                                if ($tier_pricing_enabled === 'on') {
                                    $regular_price = MPTBM_Distance_Tier_Pricing::calculate_regular_price(
                                        $post_id, $distance, $duration, $start_place, $end_place, $waiting_time, $two_way, $fixed_time
                                    );
                                    $savings = $regular_price - $price;
                                    $savings_percentage = ($savings / $regular_price) * 100;
                                    if ($savings > 0) {
                                    ?>
                                    <div class="mptbm-tier-pricing-savings-ticket">
                                        <span class="mptbm-tier-pricing-savings-ticket-amount">
                                            <?php echo wp_kses_post(wc_price($savings)); ?>
                                        </span>
                                        <span class="mptbm-tier-pricing-savings-ticket-label">
                                            Save
                                        </span>
                                        <span class="mptbm-tier-pricing-savings-ticket-percent">
                                            (<?php echo round($savings_percentage, 0); ?>%)
                                        </span>
                                    </div>
                                    <?php }
                                }
                            }
                            ?>
                            </div>
                            <h4 class="textCenter" style="clear:right; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; word-break: keep-all; line-height: 1.2;"> <?php echo wp_kses_post($price_display); ?></h4>
                            
                            <?php if (class_exists('MPTBM_Plugin_Pro')) { 
                                if ($enable_inventory == 'yes' && $available_quantity > 1) { ?>
                                    <div style="margin-bottom: 2px;" class="textCenter _mT_xs mptbm_quantity_selector mptbm_booking_item_hidden <?php echo 'mptbm_quantity_selector_' . $post_id; ?> ">
                                        <div class="mp_quantity_selector">
                                            <button type="button" class="mp_quantity_minus" data-post-id="<?php echo esc_attr($post_id); ?>">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input type="number" 
                                                   class="mp_quantity_input" 
                                                   name="vehicle_quantity[<?php echo esc_attr($post_id); ?>]" 
                                                   value="1" 
                                                   min="1" 
                                                   max="<?php echo esc_attr($available_quantity); ?>" 
                                                   data-post-id="<?php echo esc_attr($post_id); ?>"
                                                   readonly />
                                            <button type="button" class="mp_quantity_plus" data-post-id="<?php echo esc_attr($post_id); ?>">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php } ?>
                            <?php } ?>
                            <?php if ($enable_inventory == 'yes' && $available_quantity > 0) { ?>
                                <div class="mptbm-button-container" style="position: relative;">
                                    <button type="button" class="_mpBtn_xs_w_150 mptbm_transport_select<?php echo $has_seat_plan && class_exists('MPTBM_Plugin_Pro') ? ' mptbm-has-seat-plan' : ''; ?><?php echo $has_extra_info ? ' mptbm-has-extra-info' : ''; ?>" data-transport-name="<?php echo esc_attr(get_the_title($post_id)); ?>" data-transport-price="<?php echo esc_attr($raw_price); ?>" data-post-id="<?php echo esc_attr($post_id); ?>" data-open-text="<?php esc_attr_e('Select Car', 'ecab-taxi-booking-manager'); ?>" data-close-text="<?php esc_html_e('Selected', 'ecab-taxi-booking-manager'); ?>" data-open-icon="" data-close-icon="fas fa-check mR_xs" style="<?php echo $has_extra_info ? 'padding-right: 35px;' : ''; ?>">
                                    <span class="" data-icon></span>
                                    <span data-text><?php esc_html_e('Select Car', 'ecab-taxi-booking-manager'); ?></span>
                                </button>
                                    <?php if ($has_extra_info) { ?>
                                        <div class="mptbm-dropdown-button" style="position: absolute; right: 0; top: 0; bottom: 0; width: 30px; background: var(--color_theme); border-top-right-radius: 4px; border-bottom-right-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease;" data-post-id="<?php echo esc_attr($post_id); ?>">
                                            <i class="fas fa-info" style="color: white; font-size: 12px;"></i>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } else if ($enable_inventory == 'yes' && $available_quantity <= 0) { ?>
                                <button type="button" class="_mpBtn_xs_w_150 mptbm_out_of_stock" disabled style="background-color: #ccc; cursor: not-allowed;">
                                    <span><?php esc_html_e('Out of Stock', 'ecab-taxi-booking-manager'); ?></span>
                                </button>
                            <?php } else { ?>
                                <!-- Handle seat plan availability -->
                                <?php 
                                $button_disabled = false;
                                $button_text = esc_html__('Select Car', 'ecab-taxi-booking-manager');
                                $button_style = '';
                                $availability_display = '';
                                
                                // Check if we have availability status from choose_vehicles.php
                                if (isset($availability_status)) {
                                    if ($availability_status === 'out_of_stock') {
                                        $button_disabled = true;
                                        $button_text = $availability_message;
                                        $button_style = 'background-color: #ccc; cursor: not-allowed;';
                                    } elseif ($availability_status === 'service_started') {
                                        $button_disabled = true;
                                        $button_text = $availability_message;
                                        $button_style = 'background-color: #ccc; cursor: not-allowed;';
                                    } elseif ($availability_status === 'limited_seats') {
                                        $availability_display = '<div style="text-align: center; color: #ff6600; font-size: 12px; margin-top: 3px; font-weight: 500;">' . esc_html($availability_message) . '</div>';
                                    }
                                }
                                ?>
                                
                                <?php if ($button_disabled) { ?>
                                    <button type="button" class="_mpBtn_xs_w_150 mptbm_out_of_stock" disabled style="<?php echo esc_attr($button_style); ?>">
                                        <span><?php echo esc_html($button_text); ?></span>
                                    </button>
                                <?php } else { ?>
                                    <div class="mptbm-button-container" style="position: relative;">
                                        <button type="button" class="_mpBtn_xs_w_150 mptbm_transport_select<?php echo $has_seat_plan && class_exists('MPTBM_Plugin_Pro') ? ' mptbm-has-seat-plan' : ''; ?><?php echo $has_extra_info ? ' mptbm-has-extra-info' : ''; ?>" data-transport-name="<?php echo esc_attr(get_the_title($post_id)); ?>" data-transport-price="<?php echo esc_attr($raw_price); ?>" data-post-id="<?php echo esc_attr($post_id); ?>" data-open-text="<?php esc_attr_e('Select Car', 'ecab-taxi-booking-manager'); ?>" data-close-text="<?php esc_html_e('Selected', 'ecab-taxi-booking-manager'); ?>" data-open-icon="" data-close-icon="fas fa-check mR_xs" style="<?php echo $has_extra_info ? 'padding-right: 35px;' : ''; ?>">
                                        <span class="" data-icon></span>
                                        <span data-text><?php echo esc_html($button_text); ?></span>
                                    </button>
                                        <?php if ($has_extra_info) { ?>
                                            <div class="mptbm-dropdown-button" style="position: absolute; right: 0; top: 0; bottom: 0; width: 30px; background: var(--color_theme); border-top-right-radius: 4px; border-bottom-right-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease;" data-post-id="<?php echo esc_attr($post_id); ?>">
                                                <i class="fas fa-info" style="color: white; font-size: 12px;"></i>
                                            </div>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                                
                                <!-- Display availability message -->
                                <?php if ($availability_display) { ?>
                                    <?php echo wp_kses_post($availability_display); ?>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>
                    <!-- poro feature used this hook for showing driver's data -->
                    <?php do_action('mptbm_booking_item_after_feature',$post_id); ?>
                </div>
            </div>
            <!-- Seat Plan Dropdown Content (shown when select button is clicked) -->
            <?php if ($has_seat_plan && class_exists('MPTBM_Plugin_Pro')) { ?>
                <div class="mptbm-seat-dropdown-content" style="display: none; width: 100%; margin: 5px 0 15px 0; padding: 12px 15px; background: linear-gradient(to right, #f8f9fa 0%, #ffffff 100%); border: 1px solid #e1e5e9; border-top: 3px solid var(--color_theme); border-radius: 0 0 8px 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); font-size: 13px; line-height: 1.5; clear: both; box-sizing: border-box; position: relative;" data-post-id="<?php echo esc_attr($post_id); ?>">
                    <div style="position: absolute; top: -3px; left: 20px; width: 0; height: 0; border-left: 8px solid transparent; border-right: 8px solid transparent; border-top: 8px solid var(--color_theme);"></div>
                    
                    <?php if ($has_visual_seat_plan) { ?>
                        <!-- Visual Seat Plan -->
                        <div style="border-left: 3px solid var(--color_theme); padding-left: 12px; background: rgba(255,255,255,0.7); margin: -5px 0; padding-top: 8px; padding-bottom: 8px; border-radius: 4px;">
                            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                <i class="fas fa-car" style="color: var(--color_theme); margin-right: 6px; font-size: 14px;"></i>
                                <strong style="color: var(--color_theme); font-size: 14px;">Select Your Seats</strong>
                            </div>
                            
                            <!-- Seat Layout -->
                            <div class="mptbm-seat-layout" style="display: flex; flex-direction: column; align-items: center; margin-bottom: 15px;">
                                <!-- Seat Grid -->
                                <div class="mptbm-seat-grid" style="display: grid; grid-template-columns: repeat(<?php echo esc_attr($seat_columns); ?>, 1fr); gap: 5px; max-width: <?php echo ($seat_columns * 50 + ($seat_columns - 1) * 5); ?>px;" data-post-id="<?php echo esc_attr($post_id); ?>" data-rows="<?php echo esc_attr($seat_rows); ?>" data-columns="<?php echo esc_attr($seat_columns); ?>">
                                    <?php 
                                    // Get booked seats for this date/time to prevent double booking
                                    $booked_seats = array();
                                    if ($start_date && $start_time) {
                                        // Format the time properly for comparison
                                        $hours = floor($start_time);
                                        $minutes = ($start_time - $hours) * 60;
                                        $formatted_time = sprintf('%02d:%02d', $hours, $minutes);
                                        $booking_datetime = $start_date . ' ' . $formatted_time;
                                        
                                        // Get seat plan buffer time
                                        $buffer_time = MP_Global_Function::get_post_info($post_id, 'mptbm_seat_plan_buffer_time', 30);
                                        
                                        // Calculate buffer range
                                        $booking_timestamp = strtotime($booking_datetime);
                                        $buffer_start = $booking_timestamp - ($buffer_time * 60);
                                        $buffer_end = $booking_timestamp + ($buffer_time * 60);
                                        
                                        // Query existing bookings within buffer time
                                        $booking_query = new WP_Query([
                                            'post_type' => 'mptbm_booking',
                                            'posts_per_page' => -1,
                                            'meta_query' => [
                                                [
                                                    'key' => 'mptbm_id',
                                                    'value' => $post_id,
                                                    'compare' => '='
                                                ]
                                            ]
                                        ]);
                                        
                                        if ($booking_query->have_posts()) {
                                            while ($booking_query->have_posts()) {
                                                $booking_query->the_post();
                                                $booking_id = get_the_ID();
                                                $existing_datetime = get_post_meta($booking_id, 'mptbm_date', true);
                                                $existing_timestamp = strtotime($existing_datetime);
                                                
                                                // Check if this booking falls within buffer range
                                                if ($existing_timestamp >= $buffer_start && $existing_timestamp <= $buffer_end) {
                                                    $existing_seat_numbers = get_post_meta($booking_id, 'mptbm_seat_numbers', true);
                                                    
                                                    if ($existing_seat_numbers) {
                                                        $seat_array = explode(',', $existing_seat_numbers);
                                                        $booked_seats = array_merge($booked_seats, $seat_array);
                                                    }
                                                }
                                            }
                                        }
                                        wp_reset_postdata();
                                    }
                                    
                                    // Generate seat grid with airline-style naming (A, B, C) and column numbers (1, 2, 3)
                                    for ($row = 1; $row <= $seat_rows; $row++) {
                                        for ($col = 1; $col <= $seat_columns; $col++) {
                                            $row_letter = chr(64 + $row); // A, B, C, D, etc.
                                            $seat_id = $row . '_' . $col;
                                            $seat_display = $row_letter . $col; // A1, A2, B1, B2, etc.
                                            $is_booked = in_array($seat_id, $booked_seats);
                                            $seat_class = $is_booked ? 'mptbm-seat mptbm-seat-booked' : 'mptbm-seat';
                                            $seat_style = $is_booked ? 
                                                'width: 50px; height: 45px; background: #ffe6e6; border: 2px solid #dc3545; border-radius: 8px 8px 4px 4px; cursor: not-allowed; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: all 0.3s ease; position: relative; box-shadow: 0 2px 4px rgba(0,0,0,0.1); opacity: 0.7;' :
                                                'width: 50px; height: 45px; background: #f5f5f5; border: 2px solid #999; border-radius: 8px 8px 4px 4px; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: all 0.3s ease; position: relative; box-shadow: 0 2px 4px rgba(0,0,0,0.1);';
                                            ?>
                                            <div class="<?php echo esc_attr($seat_class); ?>" 
                                                 data-seat-id="<?php echo esc_attr($seat_id); ?>" 
                                                 data-seat-display="<?php echo esc_attr($seat_display); ?>"
                                                 data-post-id="<?php echo esc_attr($post_id); ?>"
                                                 style="<?php echo esc_attr($seat_style); ?>"
                                                 title="Seat <?php echo esc_attr($seat_display); ?><?php echo $is_booked ? ' (Booked)' : ''; ?>">
                                                <i class="fas fa-couch" style="color: <?php echo $is_booked ? '#dc3545' : '#999'; ?>; font-size: 16px; margin-bottom: 2px;"></i>
                                                <span style="font-size: 10px; color: <?php echo $is_booked ? '#dc3545' : '#999'; ?>; font-weight: bold;"><?php echo esc_html($seat_display); ?></span>
                                            </div>
                                            <?php
                                        }
                                    }
                                    ?>
                                </div>
                                
                                <!-- Selected Seats Info -->
                                <div class="mptbm-selected-seats-info" style="margin-top: 15px; text-align: center;">
                                    <div style="color: #666; font-size: 12px; margin-bottom: 5px;">
                                        <span class="mptbm-selected-count" data-post-id="<?php echo esc_attr($post_id); ?>">0</span> seat(s) selected
                                    </div>
                                    <div class="mptbm-selected-seats-list" data-post-id="<?php echo esc_attr($post_id); ?>" style="color: var(--color_theme); font-size: 12px; font-weight: bold; min-height: 16px;">
                                        <!-- Selected seat numbers will appear here -->
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Legend -->
                            <div style="display: flex; justify-content: center; gap: 15px; margin-bottom: 10px; font-size: 11px;">
                                <div style="display: flex; align-items: center;">
                                    <div style="width: 16px; height: 16px; background: #f5f5f5; border: 2px solid #999; border-radius: 3px; margin-right: 5px;"></div>
                                    <span style="color: #666;">Available</span>
                                </div>
                                <div style="display: flex; align-items: center;">
                                    <div style="width: 16px; height: 16px; background: #cce5ff; border: 2px solid #007bff; border-radius: 3px; margin-right: 5px;"></div>
                                    <span style="color: #666;">Selected</span>
                                </div>
                                <div style="display: flex; align-items: center;">
                                    <div style="width: 16px; height: 16px; background: #ffe6e6; border: 2px solid #dc3545; border-radius: 3px; margin-right: 5px;"></div>
                                    <span style="color: #666;">Booked</span>
                                </div>
                            </div>
                            
                            <!-- Hidden input for selected seat numbers -->
                            <input type="hidden" 
                                   class="mptbm-selected-seat-numbers" 
                                   name="vehicle_seat_numbers[<?php echo esc_attr($post_id); ?>]" 
                                   data-post-id="<?php echo esc_attr($post_id); ?>"
                                   value="" />
                            
                            <!-- Hidden input for seat quantity (synced with selected seats) -->
                            <input type="hidden" 
                                   class="mptbm_seat_input mp_quantity_input" 
                                   name="vehicle_quantity[<?php echo esc_attr($post_id); ?>]" 
                                   value="0" 
                                   data-post-id="<?php echo esc_attr($post_id); ?>" />
                        </div>
                    <?php } else { ?>
                        <!-- Quantity-based Seat Selection -->
                        <div style="border-left: 3px solid var(--color_theme); padding-left: 12px; background: rgba(255,255,255,0.7); margin: -5px 0; padding-top: 8px; padding-bottom: 8px; border-radius: 4px;">
                            <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                <i class="fas fa-chair" style="color: var(--color_theme); margin-right: 6px; font-size: 14px;"></i>
                                <strong style="color: var(--color_theme); font-size: 14px;">Select Number of Seats</strong>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
                                <div class="mptbm_seat_selector" style="display: flex; align-items: center; background: #fff; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
                                    <button type="button" class="mptbm_seat_minus" data-post-id="<?php echo esc_attr($post_id); ?>" style="background: #f0f0f0; border: none; padding: 8px 12px; cursor: pointer; transition: background 0.3s; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-minus" style="font-size: 12px; color: #666;"></i>
                                    </button>
                                    <?php 
                                    // Get available seats considering buffer time
                                    $max_selectable_seats = $total_seat;
                                    if (isset($seat_availability) && $seat_availability['is_available']) {
                                        $max_selectable_seats = $seat_availability['available'];
                                    }
                                    ?>
                                    <input type="number" 
                                           class="mptbm_seat_input mp_quantity_input" 
                                           name="vehicle_quantity[<?php echo esc_attr($post_id); ?>]" 
                                           value="1" 
                                           min="1" 
                                           max="<?php echo esc_attr($max_selectable_seats); ?>" 
                                           data-post-id="<?php echo esc_attr($post_id); ?>"
                                           data-max-seats="<?php echo esc_attr($max_selectable_seats); ?>"
                                           style="border: none; width: 60px; text-align: center; padding: 8px; font-size: 14px; font-weight: bold;" 
                                           readonly />
                                    <button type="button" class="mptbm_seat_plus" data-post-id="<?php echo esc_attr($post_id); ?>" style="background: #f0f0f0; border: none; padding: 8px 12px; cursor: pointer; transition: background 0.3s; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-plus" style="font-size: 12px; color: #666;"></i>
                                    </button>
                                </div>
                            </div>
                            <div style="text-align: center; color: #666; font-size: 12px;">
                                <?php if (isset($seat_availability) && !$seat_availability['is_available'] && isset($seat_availability['service_started']) && $seat_availability['service_started']) { ?>
                                    <div style="color: #ff6b6b; font-weight: 500;">Service has already started</div>
                                    <div style="color: #999; font-size: 11px; margin-top: 2px;">
                                        Cannot book after departure time
                                    </div>
                                <?php } elseif (isset($seat_availability) && $seat_availability['available'] < $seat_availability['total']) { ?>
                                    Available seats: <strong style="color: #ff6600;"><?php echo esc_html($seat_availability['available']); ?></strong> of <?php echo esc_html($seat_availability['total']); ?>
                                    <div style="color: #999; font-size: 11px; margin-top: 2px;">
                                        (<?php echo esc_html($seat_availability['booked_in_buffer']); ?> seats reserved due to buffer time)
                                    </div>
                                <?php } else { ?>
                                    Available seats: <?php echo esc_html($total_seat); ?>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
            
            <!-- Extra Info Dropdown Content (shown when info button is clicked) -->
            <?php if ($has_extra_info) { ?>
                <div class="mptbm-info-dropdown-content" style="display: none; width: 100%; margin: 5px 0 15px 0; padding: 12px 15px; background: linear-gradient(to right, #f8f9fa 0%, #ffffff 100%); border: 1px solid #e1e5e9; border-top: 3px solid var(--color_theme); border-radius: 0 0 8px 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); font-size: 13px; line-height: 1.5; clear: both; box-sizing: border-box; position: relative;" data-post-id="<?php echo esc_attr($post_id); ?>">
                    <div style="position: absolute; top: -3px; left: 20px; width: 0; height: 0; border-left: 8px solid transparent; border-right: 8px solid transparent; border-top: 8px solid var(--color_theme);"></div>
                    
                    <!-- Extra Info Only Section -->
                    <div style="border-left: 3px solid var(--color_theme); padding-left: 12px; background: rgba(255,255,255,0.7); margin: -5px 0; padding-top: 8px; padding-bottom: 8px; border-radius: 4px;">
                        <div style="display: flex; align-items: center; margin-bottom: 5px;">
                            <i class="fas fa-info-circle" style="color: var(--color_theme); margin-right: 6px; font-size: 14px;"></i>
                            <strong style="color: var(--color_theme); font-size: 14px;">Additional Information</strong>
                        </div>
                        <div style="color: #555; font-size: 13px; line-height: 1.6;">
                            <?php echo wp_kses_post(nl2br($extra_info)); ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
        
        <script>
        (function($) {
            'use strict';
            
            // Add CSS styles for seat plan
            var seatPlanStyles = `
                <style>
                .mptbm-seat {
                    transition: all 0.3s ease !important;
                    user-select: none;
                }
                .mptbm-seat:hover:not(.mptbm-seat-booked) {
                    transform: scale(1.05) !important;
                    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
                }
                .mptbm-seat-selected {
                    background: #cce5ff !important;
                    border-color: #007bff !important;
                    animation: seatSelect 0.3s ease;
                }
                .mptbm-seat-booked {
                    background: #ffe6e6 !important;
                    border-color: #dc3545 !important;
                    cursor: not-allowed !important;
                    opacity: 0.7;
                }
                .mptbm-seat-booked i,
                .mptbm-seat-booked span {
                    color: #dc3545 !important;
                }
                @keyframes seatSelect {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.1); }
                    100% { transform: scale(1); }
                }
                .mptbm-seat-grid {
                    user-select: none;
                }
                .mptbm-selected-seats-info {
                    transition: all 0.3s ease;
                }
                </style>
            `;
            
            // Append styles to head
            if (!$('#mptbm-seat-plan-styles').length) {
                $('head').append(seatPlanStyles.replace('<style>', '<style id="mptbm-seat-plan-styles">'));
            }
            
            // Namespace to avoid conflicts
            var MPTBM_SeatPlan = {
                init: function() {
                    this.bindEvents();
                },
                
                bindEvents: function() {
                    var self = this;
                    
                    // Use unique namespace for events to avoid conflicts
                    $(document).off('click.mptbm-seat-plan').on('click.mptbm-seat-plan', '.mptbm-dropdown-button', function(e) {
                        self.handleInfoButtonClick(e, this);
                    });
                    
                    $(document).off('click.mptbm-seat-select').on('click.mptbm-seat-select', '.mptbm_transport_select.mptbm-has-seat-plan', function(e) {
                        self.handleSeatPlanSelect(e, this);
                    });
                    
                    $(document).off('click.mptbm-seat-plus').on('click.mptbm-seat-plus', '.mptbm_seat_plus', function(e) {
                        self.handleSeatPlus(e, this);
                    });
                    
                    $(document).off('click.mptbm-seat-minus').on('click.mptbm-seat-minus', '.mptbm_seat_minus', function(e) {
                        self.handleSeatMinus(e, this);
                    });
                    
                    // Visual seat plan interactions
                    $(document).off('click.mptbm-visual-seat').on('click.mptbm-visual-seat', '.mptbm-seat', function(e) {
                        self.handleSeatClick(e, this);
                    });
                    
                    $(document).off('mouseenter.mptbm-seat-hover mouseleave.mptbm-seat-hover').on('mouseenter.mptbm-seat-hover', '.mptbm_seat_plus, .mptbm_seat_minus, .mptbm-seat', function() {
                        if ($(this).hasClass('mptbm-seat')) {
                            if (!$(this).hasClass('mptbm-seat-booked')) {
                                $(this).css('transform', 'scale(1.05)');
                            }
                        } else {
                            $(this).css('background', '#e0e0e0');
                        }
                    }).on('mouseleave.mptbm-seat-hover', '.mptbm_seat_plus, .mptbm_seat_minus, .mptbm-seat', function() {
                        if ($(this).hasClass('mptbm-seat')) {
                            $(this).css('transform', 'scale(1)');
                        } else {
                            $(this).css('background', '#f0f0f0');
                        }
                    });
                    
                    $(document).off('click.mptbm-outside').on('click.mptbm-outside', function(e) {
                        self.handleOutsideClick(e);
                    });
                },
                
                handleInfoButtonClick: function(e, button) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    e.stopPropagation();
                    
                    console.log('Info button clicked');
                    
                    var $button = $(button);
                    var postId = $button.data('post-id');
                    var $infoContent = $('.mptbm-info-dropdown-content[data-post-id="' + postId + '"]');
                    var $seatContent = $('.mptbm-seat-dropdown-content[data-post-id="' + postId + '"]');
                    
                    console.log('Post ID:', postId, 'Info content found:', $infoContent.length);
                    
                    // Close other dropdowns first
                    $('.mptbm-info-dropdown-content').not($infoContent).slideUp(200);
                    if ($seatContent.is(':visible')) {
                        $seatContent.slideUp(200);
                    }
                    
                    // Toggle info content with a slight delay to prevent immediate closing
                    setTimeout(function() {
                        if ($infoContent.is(':visible')) {
                            console.log('Closing info content');
                            $infoContent.slideUp(300);
                        } else {
                            console.log('Opening info content');
                            $infoContent.slideDown(300);
                        }
                    }, 10);
                    
                    return false;
                },
                
                handleSeatPlanSelect: function(e, button) {
                    var $this = $(button);
                    var postId = $this.data('post-id');
                    var $seatContent = $('.mptbm-seat-dropdown-content[data-post-id="' + postId + '"]');
                    var $infoContent = $('.mptbm-info-dropdown-content[data-post-id="' + postId + '"]');
                    var self = this;
                    
                    // Let the normal select logic run first
                    setTimeout(function() {
                        if ($infoContent.is(':visible')) {
                            $infoContent.slideUp(300);
                        }
                        
                        if ($this.hasClass('active_select')) {
                            $seatContent.slideDown(300);
                            
                            // Auto-select first available seat if no seats are already selected
                            var $selectedSeats = $seatContent.find('.mptbm-seat-selected[data-post-id="' + postId + '"]');
                            if ($selectedSeats.length === 0) {
                                var $firstAvailableSeat = $seatContent.find('.mptbm-seat[data-post-id="' + postId + '"]:not(.mptbm-seat-booked)').first();
                                if ($firstAvailableSeat.length > 0) {
                                    // Automatically select the first available seat
                                    $firstAvailableSeat.addClass('mptbm-seat-selected');
                                    $firstAvailableSeat.css({
                                        'background': '#cce5ff',
                                        'border-color': '#007bff'
                                    });
                                    $firstAvailableSeat.find('i').css('color', '#007bff');
                                    $firstAvailableSeat.find('span').css('color', '#007bff');
                                    
                                    // Update the seat selection display
                                    self.updateSelectedSeats(postId);
                                }
                            }
                            
                            self.updateSeatPlanPrice(postId);
                        } else {
                            $seatContent.slideUp(300);
                        }
                    }, 150);
                },
                
                handleSeatPlus: function(e, element) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var postId = $(element).data('post-id');
                    var input = $('.mptbm_seat_input[data-post-id="' + postId + '"]');
                    var currentValue = parseInt(input.val()) || 1;
                    var maxSeats = parseInt(input.attr('max')) || parseInt(input.data('max-seats')) || 1;
                    
                    if (currentValue < maxSeats) {
                        var newValue = currentValue + 1;
                        input.val(newValue);
                        input.trigger('change');
                        this.updateSeatPlanPrice(postId);
                    }
                },
                
                handleSeatMinus: function(e, element) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var postId = $(element).data('post-id');
                    var input = $('.mptbm_seat_input[data-post-id="' + postId + '"]');
                    var currentValue = parseInt(input.val()) || 1;
                    var minSeats = parseInt(input.attr('min')) || 1;
                    
                    if (currentValue > minSeats) {
                        var newValue = currentValue - 1;
                        input.val(newValue);
                        input.trigger('change');
                        this.updateSeatPlanPrice(postId);
                    }
                },
                
                handleSeatClick: function(e, seat) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var $seat = $(seat);
                    var postId = $seat.data('post-id');
                    var seatDisplay = $seat.data('seat-display'); // A1, A2, B1, etc.
                    var seatId = $seat.data('seat-id');
                    
                    // Don't allow selection of booked seats
                    if ($seat.hasClass('mptbm-seat-booked')) {
                        return;
                    }
                    
                    // Toggle seat selection
                    if ($seat.hasClass('mptbm-seat-selected')) {
                        // Deselect seat
                        $seat.removeClass('mptbm-seat-selected');
                        $seat.css({
                            'background': '#f5f5f5',
                            'border-color': '#999'
                        });
                        $seat.find('i').css('color', '#999');
                        $seat.find('span').css('color', '#999');
                    } else {
                        // Select seat
                        $seat.addClass('mptbm-seat-selected');
                        $seat.css({
                            'background': '#cce5ff',
                            'border-color': '#007bff'
                        });
                        $seat.find('i').css('color', '#007bff');
                        $seat.find('span').css('color', '#007bff');
                    }
                    
                    // Update selected seats display and form data
                    this.updateSelectedSeats(postId);
                },
                
                updateSelectedSeats: function(postId) {
                    var selectedSeats = [];
                    var selectedDisplayNames = [];
                    
                    $('.mptbm-seat[data-post-id="' + postId + '"].mptbm-seat-selected').each(function() {
                        var seatDisplay = $(this).data('seat-display'); // A1, A2, B1, etc.
                        var seatId = $(this).data('seat-id');
                        selectedSeats.push(seatId);
                        selectedDisplayNames.push(seatDisplay);
                    });
                    
                    // Update counter display
                    $('.mptbm-selected-count[data-post-id="' + postId + '"]').text(selectedSeats.length);
                    
                    // Update selected seats list display
                    var $seatsList = $('.mptbm-selected-seats-list[data-post-id="' + postId + '"]');
                    if (selectedDisplayNames.length > 0) {
                        $seatsList.text('Seats: ' + selectedDisplayNames.join(', '));
                    } else {
                        $seatsList.text('');
                    }
                    
                    // Update hidden form inputs - store both IDs and display names
                    $('.mptbm-selected-seat-numbers[data-post-id="' + postId + '"]').val(selectedSeats.join(','));
                    $('.mptbm_seat_input[data-post-id="' + postId + '"]').val(selectedSeats.length);
                    
                    // Store display names in a separate hidden field for easy access
                    var displayInput = $('input[name="vehicle_seat_display_names[' + postId + ']"]');
                    if (displayInput.length === 0) {
                        $('.mptbm-selected-seat-numbers[data-post-id="' + postId + '"]').after(
                            '<input type="hidden" name="vehicle_seat_display_names[' + postId + ']" value="' + selectedDisplayNames.join(',') + '">'
                        );
                    } else {
                        displayInput.val(selectedDisplayNames.join(','));
                    }
                    

                    
                    // Update pricing
                    this.updateSeatPlanPrice(postId);
                },
                
                updateSeatPlanPrice: function(postId) {
                    console.log('updateSeatPlanPrice called for postId:', postId);
                    
                    var $input = $('.mptbm_seat_input[data-post-id="' + postId + '"], .mp_quantity_input[data-post-id="' + postId + '"]');
                    var updatedVal = parseInt($input.val()) || 0; // Allow 0 for visual seat plan
                    var $parent = $('.mptbm_booking_item_' + postId);
                    var $searchArea = $parent.closest('.mptbm_transport_search_area');
                    var transportPrice = parseFloat($('.mptbm_transport_select[data-post-id="' + postId + '"]').attr('data-transport-price')) || 0;
                    var $summary = $searchArea.find('.mptbm_transport_summary');

                    console.log('Seat quantity:', updatedVal, 'Transport price:', transportPrice, 'Total:', transportPrice * updatedVal);

                    if ($summary.length === 0) {
                        console.log('Summary not found');
                        return;
                    }

                    // Check for custom message
                    var customMessage = $parent.find('.mptbm-custom-price-message').html();
                    if (customMessage) {
                        if (updatedVal > 0) {
                            $summary.find('.mptbm_product_price').html(
                                'x' + updatedVal + ' <span style="color:#000;">|&nbsp;&nbsp;</span>' + customMessage
                            );
                        } else {
                            $summary.find('.mptbm_product_price').html(customMessage);
                        }
                    } else {
                        var formattedPrice = (typeof window.mp_price_format === 'function') ? 
                            window.mp_price_format(transportPrice * updatedVal) : 
                            '$' + (transportPrice * updatedVal).toFixed(2);
                        
                        if (updatedVal > 0) {
                            $summary.find('.mptbm_product_price').html(
                                'x' + updatedVal + ' <span style="color:#000;">|&nbsp;&nbsp;</span>' + formattedPrice
                            );
                        } else {
                            $summary.find('.mptbm_product_price').html(formattedPrice);
                        }
                    }

                    // Update data-price attribute
                    $searchArea.find('[name="mptbm_post_id"]').attr('data-price', transportPrice * updatedVal);

                    // Call price calculation function
                    if (typeof window.mptbm_price_calculation === 'function') {
                        console.log('Calling mptbm_price_calculation');
                        window.mptbm_price_calculation($searchArea);
                    }
                },
                
                handleOutsideClick: function(e) {
                    if (!$(e.target).closest('.mptbm-button-container, .mptbm-seat-dropdown-content, .mptbm-info-dropdown-content, .mptbm-dropdown-button, .mptbm-seat').length) {
                        $('.mptbm-seat-dropdown-content, .mptbm-info-dropdown-content').slideUp(300);
                    }
                }
            };
            
            // Initialize when document is ready
            $(document).ready(function() {
                MPTBM_SeatPlan.init();
            });
            
        })(jQuery);
        </script>
<?php
    }
}
?>