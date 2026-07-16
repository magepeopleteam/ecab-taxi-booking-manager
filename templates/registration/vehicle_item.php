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
$mptbm_unavailable = $mptbm_unavailable ?? false;
$mptbm_unavailable_reason = $mptbm_unavailable_reason ?? '';
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
    
    // Get coordinates for fixed_zone/fixed_zone_dropoff geo-fence validation
    $start_place_coordinates = isset($_POST['start_place_coordinates']) ? $_POST['start_place_coordinates'] : '';
    $end_place_coordinates = isset($_POST['end_place_coordinates']) ? $_POST['end_place_coordinates'] : '';
    $price_based = isset($_POST['price_based']) ? sanitize_text_field($_POST['price_based']) : '';
    
    // For fixed_zone_dropoff, we need to pass start_place_coordinates as geo_fence_coords
    // because the geo-fence check needs the searched pickup coordinates
    // For fixed_zone, we pass end_place_coordinates as geo_fence_coords (searched dropoff coordinates)
    $geo_fence_coords = null;
    if ($price_based === 'fixed_zone_dropoff' && !empty($start_place_coordinates)) {
        if (is_array($start_place_coordinates)) {
            $geo_fence_coords = $start_place_coordinates;
        } else {
            // Handle JSON string - WordPress may add slashes, so try both ways
            $decoded = json_decode($start_place_coordinates, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                // Try with stripslashes in case WordPress added slashes
                $decoded = json_decode(stripslashes($start_place_coordinates), true);
            }
            // Validate the decoded data has the required keys
            if ($decoded && (isset($decoded['latitude']) || isset($decoded['lat'])) && (isset($decoded['longitude']) || isset($decoded['lng']))) {
                $geo_fence_coords = $decoded;
            } else {
                // Last resort: try to extract coordinates from the string directly
                if (preg_match('/"latitude":([\d.]+).*"longitude":([\d.]+)/', $start_place_coordinates, $matches)) {
                    $geo_fence_coords = ['latitude' => floatval($matches[1]), 'longitude' => floatval($matches[2])];
                } elseif (preg_match('/"lat":([\d.]+).*"lng":([\d.]+)/', $start_place_coordinates, $matches)) {
                    $geo_fence_coords = ['lat' => floatval($matches[1]), 'lng' => floatval($matches[2])];
                }
            }
        }
    } elseif (($price_based === 'fixed_zone') && !empty($end_place_coordinates)) {
        if (is_array($end_place_coordinates)) {
            $geo_fence_coords = $end_place_coordinates;
        } else {
            // Handle JSON string - WordPress may add slashes, so try both ways
            $decoded = json_decode($end_place_coordinates, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                // Try with stripslashes in case WordPress added slashes
                $decoded = json_decode(stripslashes($end_place_coordinates), true);
            }
            // Validate the decoded data has the required keys
            if ($decoded && (isset($decoded['latitude']) || isset($decoded['lat'])) && (isset($decoded['longitude']) || isset($decoded['lng']))) {
                $geo_fence_coords = $decoded;
        } else {
                // Last resort: try to extract coordinates from the string directly
                if (preg_match('/"latitude":([\d.]+).*"longitude":([\d.]+)/', $end_place_coordinates, $matches)) {
                    $geo_fence_coords = ['latitude' => floatval($matches[1]), 'longitude' => floatval($matches[2])];
                } elseif (preg_match('/"lat":([\d.]+).*"lng":([\d.]+)/', $end_place_coordinates, $matches)) {
                    $geo_fence_coords = ['lat' => floatval($matches[1]), 'lng' => floatval($matches[2])];
                }
            }
        }
    }
    
    // location_exit is already checked in choose_vehicles.php, so we can directly display the vehicle
    // Price, price_display, and raw_price are already calculated in choose_vehicles.php
    if ($post_id) {
        $thumbnail = MP_Global_Function::get_image_url($post_id);

        $display_features = MP_Global_Function::get_post_info($post_id, 'display_mptbm_features', 'on');
        $all_features = MP_Global_Function::get_post_info($post_id, 'mptbm_features');
        
        // Get extra info for this vehicle
        $extra_info = MP_Global_Function::get_post_info($post_id, 'mptbm_extra_info', '');
        $has_extra_info = !empty(trim($extra_info));

        // Real, fixed vehicle-identity fields (Admin > Vehicle Specification). Only the
        // ones the admin actually filled in are kept/shown - never fabricated placeholders.
        $vehicle_spec_rows = array(
            'make_model' => array(esc_html__('Make & Model', 'ecab-taxi-booking-manager'), MP_Global_Function::get_post_info($post_id, 'mptbm_spec_make_model', '')),
            'year'       => array(esc_html__('Year', 'ecab-taxi-booking-manager'), MP_Global_Function::get_post_info($post_id, 'mptbm_spec_year', '')),
            'color'      => array(esc_html__('Color', 'ecab-taxi-booking-manager'), MP_Global_Function::get_post_info($post_id, 'mptbm_spec_color', '')),
            'engine'     => array(esc_html__('Engine', 'ecab-taxi-booking-manager'), MP_Global_Function::get_post_info($post_id, 'mptbm_spec_engine', '')),
            'plate'      => array(esc_html__('Plate Class', 'ecab-taxi-booking-manager'), MP_Global_Function::get_post_info($post_id, 'mptbm_spec_plate', '')),
            'mileage'    => array(esc_html__('Mileage', 'ecab-taxi-booking-manager'), MP_Global_Function::get_post_info($post_id, 'mptbm_spec_mileage', '')),
        );
        $vehicle_spec_rows = array_filter($vehicle_spec_rows, function ($row) {
            return trim((string) $row[1]) !== '';
        });

        // Real average rating (0 when reviews are disabled/absent) - exposed as a data
        // attribute so the results toolbar can offer a genuine "Highest Rated" sort.
        $vehicle_avg_rating = (class_exists('MPTBM_Reviews') && MPTBM_Reviews::reviews_enabled($post_id))
            ? MPTBM_Reviews::get_average_rating($post_id)['average']
            : 0;
?>
        <div class="mptbm-vehicle-wrapper">
            <div class="_dFlex mptbm_booking_item <?php echo 'mptbm_booking_item_' . $post_id; ?> <?php echo $hidden_class; ?> <?php echo $feature_class; ?>" data-placeholder>
                <div class="_max_200_mR_xs mptbm_vehicle_image">
                    <div class="bg_image_area"  data-placeholder>
                        <div data-bg-image="<?php echo esc_attr($thumbnail); ?>"></div>
                    </div>
                </div>
                <div class="fdColumn _fullWidth mptbm_vehicle_main">
                    <div class="mptbm_vehicle_header">
                        <h5 class="mptbm_vehicle_title"><?php echo esc_html(get_the_title($post_id)); ?></h5>
                    </div>
                    <?php if (class_exists('MPTBM_Reviews')) { echo '<div class="mptbm_vehicle_rating">' . MPTBM_Reviews::get_rating_html($post_id) . '</div>'; } ?>
                    <?php if ($mptbm_unavailable) { ?>
                        <div class="mptbm_unavailable_badge" style="display:inline-block; margin: 2px 0; padding: 2px 8px; border-radius: 3px; background: #f8d7da; color: #842029; font-size: 12px; font-weight: 600;">
                            <?php esc_html_e('Unavailable', 'ecab-taxi-booking-manager'); ?><?php echo $mptbm_unavailable_reason ? ' - ' . esc_html($mptbm_unavailable_reason) : ''; ?>
                        </div>
                    <?php } ?>
                    <div class="mptbm_vehicle_features_row">
                        <?php if ($display_features == 'on' && is_array($all_features) && sizeof($all_features) > 0) { ?>
                            <?php
                            // Build a clean, deduplicated list of meaningful features. The full list
                            // (uncapped) is reused below for the "View Details" specifications panel;
                            // only the first 6 are shown as chips here.
                            $clean_features_full = array();
                            $seen_values = array();
                            $generic_labels = array('name', 'model', 'test', 'demo', 'sample', 'cars side view', 'cars front view');
                            $meaningless_values = array('test', 'best', 'bd', 'de', 'dc', 'ad', 'nothing', 'n/a', 'na', 'sample', 'demo');
                            foreach ($all_features as $features) {
                                $f_label = isset($features['label']) ? trim(strtolower($features['label'])) : '';
                                $f_text  = isset($features['text'])  ? trim($features['text']) : '';
                                if ($f_text === '' || mb_strlen($f_text) < 2) { continue; }
                                if (in_array($f_label, $generic_labels, true)) { continue; }
                                if (in_array(strtolower($f_text), $meaningless_values, true)) { continue; }
                                if (in_array(strtolower($f_text), $seen_values, true)) { continue; }
                                $seen_values[] = strtolower($f_text);
                                $clean_features_full[] = $features;
                            }
                            $clean_features = array_slice($clean_features_full, 0, 6);
                            ?>
                            <?php if (count($clean_features) > 0) { ?>
                            <ul class="mptbm_features_grid list_inline_two">
                                <?php
                                $feature_index = 0;
                                foreach ($clean_features as $features) {
                                    $label = array_key_exists('label', $features) ? trim($features['label']) : '';
                                    $text  = array_key_exists('text',  $features) ? trim($features['text'])  : '';
                                    $icon  = array_key_exists('icon',  $features) ? $features['icon']  : '';
                                    $image = array_key_exists('image', $features) ? $features['image'] : '';
                                    $show_label = ($label !== '' && strcasecmp($label, $text) !== 0 && mb_strlen($label) <= 20);
                                    $feature_index++;
                                ?>
                                    <li class="mptbm_feature_item" title="<?php echo esc_attr($label !== '' ? $label.': '.$text : $text); ?>">
                                        <?php if ($icon) { ?>
                                            <i class="<?php echo esc_attr($icon); ?> mptbm_feature_icon"></i>
                                        <?php } ?>
                                        <span class="mptbm_feature_text">
                                            <?php if ($show_label) { ?>
                                                <span class="mptbm_feature_label"><?php echo esc_html($label); ?></span>
                                            <?php } ?>
                                            <span class="mptbm_feature_value"><?php echo esc_html( mb_strimwidth( $text, 0, 24, '...' ) ); ?></span>
                                        </span>
                                    </li>
                                <?php } ?>
                            </ul>
                            <?php } else { ?>
                                <div></div>
                            <?php } ?>
                        <?php } else { ?>
                            <div></div>
                        <?php } ?>
                    </div>
                    <!-- poro feature used this hook for showing driver's data -->
                    <?php do_action('mptbm_booking_item_after_feature',$post_id); ?>
                </div>
                <div class="fdColumn mptbm_vehicle_cta">
                    <div class="mptbm_vehicle_price_col">
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

                                    // Avoid division by zero
                                    $savings_percentage = $regular_price > 0 ? ($savings / $regular_price) * 100 : 0;

                                    if ($savings > 0) {
                                        ?>
                                        <div class="mptbm-tier-pricing-savings-ticket">
                                            <span class="mptbm-tier-pricing-savings-ticket-amount">
                                                <?php echo wp_kses_post(MP_Global_Function::format_price($savings)); ?>
                                            </span>
                                            <span class="mptbm-tier-pricing-savings-ticket-label">
                                                Save
                                            </span>
                                            <span class="mptbm-tier-pricing-savings-ticket-percent">
                                                (<?php echo round($savings_percentage, 0); ?>%)
                                            </span>
                                        </div>
                                        <?php
                                    }
                                }
                            }
                        ?>
                        </div>
                        <?php
                        // Show tier badge under price when available
                        if (class_exists('MPTBM_Distance_Tier_Pricing')) {
                            $dtp = new MPTBM_Distance_Tier_Pricing();
                            $tier_badge = $dtp->get_tier_badge($post_id, $distance);
                            if ($tier_badge) {
                                echo $tier_badge; // safe small HTML snippet generated by addon
                            }
                        }
                        ?>
                        <h4 class="mptbm_vehicle_price"> <?php echo wp_kses_post($price_display); ?></h4>
                        <?php
                        // Hook for peak hour badge display
                        do_action('mptbm_after_vehicle_price', $post_id, $price_display);
                        ?>
                    </div>
                    <?php
                    // "View Details" only appears when there is genuinely more to show than the
                    // feature chips already on the card - real specs beyond the first 6, real
                    // customer reviews (MPTBM_Reviews, gated by the per-vehicle admin toggle),
                    // and/or admin-entered extra info. Never populated with placeholder or
                    // fabricated content.
                    $clean_features_full = $clean_features_full ?? array();
                    $has_more_specs = count($clean_features_full) > 6;
                    $vehicle_reviews = (class_exists('MPTBM_Reviews') && MPTBM_Reviews::reviews_enabled($post_id))
                        ? MPTBM_Reviews::get_vehicle_reviews($post_id)
                        : array();
                    $show_details_toggle = apply_filters('mptbm_show_vehicle_details_toggle', $has_more_specs || count($vehicle_reviews) > 0 || $has_extra_info || count($vehicle_spec_rows) > 0, $post_id);
                    ?>
                    <div class="mptbm_vehicle_cta_row">
                            <?php if ($mptbm_unavailable) { ?>
                                <button type="button" class="_mpBtn_xs mptbm_out_of_stock" disabled style="background-color: #ccc; cursor: not-allowed;">
                                    <span><?php esc_html_e('Unavailable', 'ecab-taxi-booking-manager'); ?></span>
                                </button>
                            <?php } else { ?>
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
                                                       data-post-id="<?php echo esc_attr($post_id); ?>" data-tax-multiplier="<?php echo esc_attr($tax_multiplier ?? 1); ?>"
                                                       readonly />
                                                <button type="button" class="mp_quantity_plus" data-post-id="<?php echo esc_attr($post_id); ?>">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php } ?>
                                <?php } ?>
                                <?php if ($enable_inventory == 'yes' && $available_quantity > 0) { ?>
                                    <div class="mptbm-button-container">
                                        <button type="button" class="_mpBtn_xs mptbm_transport_select" data-transport-name="<?php echo esc_attr(get_the_title($post_id)); ?>" data-transport-price="<?php echo esc_attr($raw_price); ?>" data-transport-rating="<?php echo esc_attr($vehicle_avg_rating); ?>" data-post-id="<?php echo esc_attr($post_id); ?>" data-tax-multiplier="<?php echo esc_attr($tax_multiplier ?? 1); ?>" data-unit-base-price="<?php echo esc_attr($base_price_extra); ?>" data-stop-price="<?php echo esc_attr($stop_price_per_unit ?? 0); ?>" data-base-price-settings='<?php echo wp_json_encode(MPTBM_Function::get_base_price_settings($post_id)); ?>' data-fixed-map-route-found="<?php echo get_transient('mptbm_fixed_route_found_' . $post_id) === 'yes' ? 'yes' : 'no'; ?>" data-open-text="<?php esc_attr_e('Select Car', 'ecab-taxi-booking-manager'); ?>" data-close-text="<?php esc_html_e('Selected', 'ecab-taxi-booking-manager'); ?>" data-open-icon="" data-close-icon="fas fa-check mR_xs">
                                        <span class="" data-icon></span>
                                        <span data-text><?php esc_html_e('Select Car', 'ecab-taxi-booking-manager'); ?></span>
                                    </button>
                                    </div>
                                <?php } else if ($enable_inventory == 'yes' && $available_quantity <= 0) { ?>
                                    <button type="button" class="_mpBtn_xs mptbm_out_of_stock" disabled style="background-color: #ccc; cursor: not-allowed;">
                                        <span><?php esc_html_e('Out of Stock', 'ecab-taxi-booking-manager'); ?></span>
                                    </button>
                                <?php } else { ?>
                                    <div class="mptbm-button-container">
                                        <button type="button" class="_mpBtn_xs mptbm_transport_select" data-transport-name="<?php echo esc_attr(get_the_title($post_id)); ?>" data-transport-price="<?php echo esc_attr($raw_price); ?>" data-transport-rating="<?php echo esc_attr($vehicle_avg_rating); ?>" data-post-id="<?php echo esc_attr($post_id); ?>" data-tax-multiplier="<?php echo esc_attr($tax_multiplier ?? 1); ?>" data-unit-base-price="<?php echo esc_attr($base_price_extra); ?>" data-stop-price="<?php echo esc_attr($stop_price_per_unit ?? 0); ?>" data-base-price-settings='<?php echo wp_json_encode(MPTBM_Function::get_base_price_settings($post_id)); ?>' data-fixed-map-route-found="<?php echo get_transient('mptbm_fixed_route_found_' . $post_id) === 'yes' ? 'yes' : 'no'; ?>" data-open-text="<?php esc_attr_e('Select Car', 'ecab-taxi-booking-manager'); ?>" data-close-text="<?php esc_html_e('Selected', 'ecab-taxi-booking-manager'); ?>" data-open-icon="" data-close-icon="fas fa-check mR_xs">
                                        <span class="" data-icon></span>
                                        <span data-text><?php esc_html_e('Select Car', 'ecab-taxi-booking-manager'); ?></span>
                                    </button>
                                    </div>
                                <?php } ?>
                            <?php } ?>
                    </div>
                    <?php if ($show_details_toggle) { ?>
                        <button type="button" class="mptbm_view_details_toggle" data-post-id="<?php echo esc_attr($post_id); ?>" data-view-text="<?php esc_attr_e('View Details', 'ecab-taxi-booking-manager'); ?>" data-hide-text="<?php esc_attr_e('Hide Details', 'ecab-taxi-booking-manager'); ?>" aria-expanded="false">
                            <span data-label><?php esc_html_e('View Details', 'ecab-taxi-booking-manager'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    <?php } ?>
                </div>
            </div>
            <?php if ($show_details_toggle) { ?>
                <div class="mptbm_vehicle_details_panel" data-post-id="<?php echo esc_attr($post_id); ?>" style="display:none;">
                    <div class="mptbm_details_col_left">
                        <?php if ($has_extra_info) { ?>
                            <div class="mptbm_details_block">
                                <h4><?php esc_html_e('Description', 'ecab-taxi-booking-manager'); ?></h4>
                                <div class="mptbm_details_extra_info"><?php echo wp_kses_post($extra_info); ?></div>
                            </div>
                        <?php } ?>
                        <?php if (count($vehicle_spec_rows) > 0) { ?>
                            <div class="mptbm_details_block">
                                <h4><?php esc_html_e('Vehicle Specifications', 'ecab-taxi-booking-manager'); ?></h4>
                                <div class="mptbm_spec_table">
                                    <?php foreach ($vehicle_spec_rows as $row) { ?>
                                        <div class="mptbm_spec_row">
                                            <span><?php echo esc_html($row[0]); ?></span>
                                            <span><?php echo esc_html($row[1]); ?></span>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if ($has_more_specs) { ?>
                            <div class="mptbm_details_block">
                                <h4><?php esc_html_e('Additional Features', 'ecab-taxi-booking-manager'); ?></h4>
                                <ul class="mptbm_details_spec_list">
                                    <?php foreach ($clean_features_full as $features) {
                                        $label = array_key_exists('label', $features) ? trim($features['label']) : '';
                                        $text  = array_key_exists('text',  $features) ? trim($features['text'])  : '';
                                        $icon  = array_key_exists('icon',  $features) ? $features['icon']  : '';
                                        $show_label = ($label !== '' && strcasecmp($label, $text) !== 0);
                                    ?>
                                        <li>
                                            <?php if ($icon) { ?><i class="<?php echo esc_attr($icon); ?>"></i><?php } ?>
                                            <?php if ($show_label) { ?><span class="mptbm_details_spec_label"><?php echo esc_html($label); ?>:</span><?php } ?>
                                            <span class="mptbm_details_spec_value"><?php echo esc_html($text); ?></span>
                                        </li>
                                    <?php } ?>
                                </ul>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="mptbm_details_col_right">
                        <?php if (count($vehicle_reviews) > 0) {
                            $rating_data = MPTBM_Reviews::get_average_rating($post_id);
                        ?>
                            <div class="mptbm_details_block">
                                <h4>
                                    <?php esc_html_e('Customer Reviews', 'ecab-taxi-booking-manager'); ?>
                                    <span class="mptbm_details_reviews_avg"><?php echo esc_html($rating_data['average']); ?> &#9733; (<?php echo esc_html($rating_data['count']); ?>)</span>
                                </h4>
                                <div class="mptbm_details_reviews_list">
                                    <?php foreach (array_slice($vehicle_reviews, 0, 3) as $review) {
                                        $review_rating = (int) get_comment_meta($review->comment_ID, 'rating', true);
                                    ?>
                                        <div class="mptbm_details_review">
                                            <div class="mptbm_details_review_head">
                                                <span class="mptbm_details_review_author"><?php echo esc_html($review->comment_author); ?></span>
                                                <span class="mptbm_details_review_date"><?php echo esc_html(human_time_diff(strtotime($review->comment_date), current_time('timestamp'))); ?> <?php esc_html_e('ago', 'ecab-taxi-booking-manager'); ?></span>
                                            </div>
                                            <?php if ($review_rating > 0) { ?>
                                                <span class="mptbm_details_review_stars">
                                                    <?php for ($i = 1; $i <= 5; $i++) { echo $i <= $review_rating ? '&#9733;' : '&#9734;'; } ?>
                                                </span>
                                            <?php } ?>
                                            <p><?php echo esc_html($review->comment_content); ?></p>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                        <?php do_action('mptbm_vehicle_details_panel_content', $post_id); ?>
                    </div>
                </div>
            <?php } ?>
        </div>
<?php
    }
}
?>