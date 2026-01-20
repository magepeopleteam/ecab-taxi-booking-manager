<?php
if (!function_exists('mptbm_get_translation')) {
	require_once dirname(__DIR__, 2) . '/inc/mptbm-translation-helper.php';
}
	/*
 * @Author 		engr.sumonazma@gmail.com
 * Copyright: 	mage-people.com
 */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly
	$start_place = sanitize_text_field( $_POST['start_place']);
    $price_based = sanitize_text_field($_POST['price_based']);
    $post_id = absint($_POST['post_id']);
    
 
    
    $end_locations = MPTBM_Function::get_end_location($start_place, $post_id, $price_based);
    
   
    
    // If no end locations found from manual pricing, show all available locations
    if (sizeof($end_locations) == 0) {
        $all_locations = MPTBM_Function::get_all_start_location('', $price_based);
        // Remove the current start location from end options
        $end_locations = array_filter($all_locations, function($location) use ($start_place) {
            return $location !== $start_place;
        });
    }
    
    if (sizeof($end_locations) > 0) {
        
        ?>
	    <span><?php echo mptbm_get_translation('dropoff_location_label', __('Drop-Off Location', 'ecab-taxi-booking-manager')); ?></span>
        <select class="formControl mptbm_map_end_place" id="mptbm_manual_end_place">
            <option selected disabled><?php echo mptbm_get_translation('select_destination_location_label', __(' Select Destination Location', 'ecab-taxi-booking-manager')); ?></option>
            <?php foreach ($end_locations as $location) { ?>
                <?php
                    // Use direct DB query to resolve location label
                    $end_label = '';
                    if (strpos($location, 'term_') === 0) {
                        $term_id = absint(str_replace('term_', '', $location));
                        global $wpdb;
                        $end_label = $wpdb->get_var($wpdb->prepare(
                            "SELECT name FROM {$wpdb->terms} WHERE term_id = %d",
                            $term_id
                        ));
                    } elseif (strpos($location, 'post_') === 0) {
                        $zone_id = absint(str_replace('post_', '', $location));
                        $end_label = get_the_title($zone_id);
                    }
                    if (!$end_label) {
                        $end_label = MPTBM_Function::get_taxonomy_name_by_slug($location, 'locations');
                    }
                    if (!$end_label) {
                        $end_label = $location;
                    }
                ?>
                <option value="<?php echo esc_attr($location); ?>"><?php echo esc_html($end_label); ?></option>
            <?php } ?>
        </select>
        <i class="fas fa-map-marker-alt mptbm_left_icon allCenter"></i>
    <?php } else { ?>
        <span class="fas fa-map-marker-alt"><?php esc_html_e(' Can not find any Destination Location', 'ecab-taxi-booking-manager'); ?></span><?php
    }