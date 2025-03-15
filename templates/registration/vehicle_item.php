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
if (MP_Global_Function::get_settings('mptbm_general_settings', 'enable_filter_via_features') == 'yes') {
    $max_passenger = MP_Global_Function::get_post_info($post_id, 'mptbm_maximum_passenger');
    $max_bag = MP_Global_Function::get_post_info($post_id, 'mptbm_maximum_bag');
    if ($max_passenger != '' && $max_bag != '') {
        $feature_class = 'feature_passenger_'.$max_passenger.'_feature_bag_'.$max_bag.'_post_id_'.$post_id;
    }else{
        $feature_class = '';
    }
}

$fixed_time = $fixed_time ?? 0;
$start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
$start_date = $start_date ? date('Y-m-d', strtotime($start_date)) : '';
$all_dates = MPTBM_Function::get_date($post_id);
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
        //$product_id = MP_Global_Function::get_post_info($post_id, 'link_wc_product');
        $thumbnail = MP_Global_Function::get_image_url($post_id);
        $price = MPTBM_Function::get_price($post_id, $distance, $duration, $start_place, $end_place, $waiting_time, $two_way, $fixed_time);
        if(!$price || $price == 0){
            return false;
        }
        
        $wc_price = MP_Global_Function::wc_price($post_id, $price);
        $raw_price = MP_Global_Function::price_convert_raw($wc_price);
        $display_features = MP_Global_Function::get_post_info($post_id, 'display_mptbm_features', 'on');
        $all_features = MP_Global_Function::get_post_info($post_id, 'mptbm_features');
        
        // Check vehicle availability
        $return_date = isset($_POST['return_date']) ? sanitize_text_field($_POST['return_date']) : '';
        $total_quantity = get_post_meta($post_id, 'mptbm_vehicle_quantity', true) ?: 1;

        // Get booked count from MPTBM_Availability class
        $booked_count = 0;
        if (class_exists('MPTBM_Availability')) {
            $booked_count = MPTBM_Availability::get_booked_count($post_id, $start_date);
            if (!empty($return_date)) {
                // For return date, we need to check separately to avoid double counting
                $return_booked = MPTBM_Availability::get_booked_count($post_id, $return_date);
                // Take the maximum of the two counts to avoid exceeding the actual quantity
                $booked_count = max($booked_count, $return_booked);
            }
        }

        $availability = array(
            'total' => $total_quantity,
            'booked' => $booked_count,
            'available' => max(0, $total_quantity - $booked_count)
        );
?>

        <div class="_dLayout_dFlex mptbm_booking_item  <?php echo 'mptbm_booking_item_' . $post_id; ?> <?php echo $hidden_class; ?> <?php echo $feature_class; ?>" data-placeholder>
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
                    <div class="_min_150_mL_xs">
                        <h4 class="textCenter"> <?php echo wp_kses_post(wc_price($raw_price)); ?></h4>
                        <?php
                            // Calculate availability percentage
                            $availability_percentage = ($availability['available'] / $availability['total']) * 100;
                            $availability_class = '';

                            // Add class for low availability (less than 20%)
                            if ($availability_percentage > 0 && $availability_percentage <= 20) {
                                $availability_class = 'low-availability';
                            }
                        ?>

                        <?php if ($availability['available'] > 0) { ?>
                            <div class="availability-info">
                                <small class="text-success <?php echo esc_attr($availability_class); ?>">
                                    <?php echo sprintf(esc_html__('%d of %d vehicles available', 'ecab-taxi-booking-manager'), $availability['available'], $availability['total']); ?>
                                </small>
                            </div>
                            <button type="button" class="_mpBtn_xs_w_150 mptbm_transport_select" data-transport-name="<?php echo esc_attr(get_the_title($post_id)); ?>" data-transport-price="<?php echo esc_attr($raw_price); ?>" data-post-id="<?php echo esc_attr($post_id); ?>" data-open-text="<?php esc_attr_e('Select Car', 'ecab-taxi-booking-manager'); ?>" data-close-text="<?php esc_html_e('Selected', 'ecab-taxi-booking-manager'); ?>" data-open-icon="" data-close-icon="fas fa-check mR_xs">
                                <span class="" data-icon></span>
                                <span data-text><?php esc_html_e('Select Car', 'ecab-taxi-booking-manager'); ?></span>
                            </button>
                        <?php } else { ?>
                            <div class="availability-info">
                                <small class="text-danger">
                                    <?php echo sprintf(esc_html__('Fully Booked (0 of %d available)', 'ecab-taxi-booking-manager'), $availability['total']); ?>
                                </small>
                            </div>
                            <button type="button" class="_mpBtn_xs_w_150" disabled>
                                <span data-text><?php esc_html_e('Not Available', 'ecab-taxi-booking-manager'); ?></span>
                            </button>
                        <?php } ?>
                    </div>
                </div>
                <!-- poro feature used this hook for showing driver's data -->
                <?php do_action('mptbm_booking_item_after_feature',$post_id); ?>
            </div>
        </div>
<?php
    }
}
?>