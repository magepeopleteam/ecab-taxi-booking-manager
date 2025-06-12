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
?>
        <div class="mptbm-vehicle-wrapper" style="width: 100%; display: block;">
            <div class="_dLayout_dFlex mptbm_booking_item <?php echo $has_extra_info ? 'mptbm-has-extra-info' : ''; ?> <?php echo 'mptbm_booking_item_' . $post_id; ?> <?php echo $hidden_class; ?> <?php echo $feature_class; ?>" data-placeholder <?php echo $has_extra_info ? 'style="border-bottom: 2px solid var(--color_theme); margin-bottom: 0; border-radius: 8px 8px 0 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"' : ''; ?>>
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
                                    <button type="button" class="_mpBtn_xs_w_150 mptbm_transport_select<?php echo $has_extra_info ? ' mptbm-has-extra-info' : ''; ?>" data-transport-name="<?php echo esc_attr(get_the_title($post_id)); ?>" data-transport-price="<?php echo esc_attr($raw_price); ?>" data-post-id="<?php echo esc_attr($post_id); ?>" data-open-text="<?php esc_attr_e('Select Car', 'ecab-taxi-booking-manager'); ?>" data-close-text="<?php esc_html_e('Selected', 'ecab-taxi-booking-manager'); ?>" data-open-icon="" data-close-icon="fas fa-check mR_xs" style="<?php echo $has_extra_info ? 'padding-right: 35px;' : ''; ?>">
                                    <span class="" data-icon></span>
                                    <span data-text><?php esc_html_e('Select Car', 'ecab-taxi-booking-manager'); ?></span>
                                </button>
                                    <?php if ($has_extra_info) { ?>
                                        <div class="mptbm-info-button" style="position: absolute; right: 0; top: 0; bottom: 0; width: 30px; background: var(--color_theme); border-top-right-radius: 4px; border-bottom-right-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease;" data-post-id="<?php echo esc_attr($post_id); ?>">
                                            <i class="fas fa-info" style="color: white; font-size: 12px;"></i>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } else if ($enable_inventory == 'yes' && $available_quantity <= 0) { ?>
                                <button type="button" class="_mpBtn_xs_w_150 mptbm_out_of_stock" disabled style="background-color: #ccc; cursor: not-allowed;">
                                    <span><?php esc_html_e('Out of Stock', 'ecab-taxi-booking-manager'); ?></span>
                                </button>
                            <?php } else { ?>
                                <div class="mptbm-button-container" style="position: relative;">
                                    <button type="button" class="_mpBtn_xs_w_150 mptbm_transport_select<?php echo $has_extra_info ? ' mptbm-has-extra-info' : ''; ?>" data-transport-name="<?php echo esc_attr(get_the_title($post_id)); ?>" data-transport-price="<?php echo esc_attr($raw_price); ?>" data-post-id="<?php echo esc_attr($post_id); ?>" data-open-text="<?php esc_attr_e('Select Car', 'ecab-taxi-booking-manager'); ?>" data-close-text="<?php esc_html_e('Selected', 'ecab-taxi-booking-manager'); ?>" data-open-icon="" data-close-icon="fas fa-check mR_xs" style="<?php echo $has_extra_info ? 'padding-right: 35px;' : ''; ?>">
                                    <span class="" data-icon></span>
                                    <span data-text><?php esc_html_e('Select Car', 'ecab-taxi-booking-manager'); ?></span>
                                </button>
                                    <?php if ($has_extra_info) { ?>
                                        <div class="mptbm-info-button" style="position: absolute; right: 0; top: 0; bottom: 0; width: 30px; background: var(--color_theme); border-top-right-radius: 4px; border-bottom-right-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease;" data-post-id="<?php echo esc_attr($post_id); ?>">
                                            <i class="fas fa-info" style="color: white; font-size: 12px;"></i>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                    <!-- poro feature used this hook for showing driver's data -->
                    <?php do_action('mptbm_booking_item_after_feature',$post_id); ?>
                </div>
            </div>
            <?php if ($has_extra_info) { ?>
                <div class="mptbm-extra-info-content" style="display: none; width: 100%; margin: 5px 0 15px 0; padding: 12px 15px; background: linear-gradient(to right, #f8f9fa 0%, #ffffff 100%); border: 1px solid #e1e5e9; border-top: 3px solid var(--color_theme); border-radius: 0 0 8px 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); font-size: 13px; line-height: 1.5; clear: both; box-sizing: border-box; position: relative;" data-post-id="<?php echo esc_attr($post_id); ?>">
                    <div style="position: absolute; top: -3px; left: 20px; width: 0; height: 0; border-left: 8px solid transparent; border-right: 8px solid transparent; border-top: 8px solid var(--color_theme);"></div>
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
<?php
    }
}
?>