<?php
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
    
    // Debug logging
    error_log('MPTBM Debug - Start place: ' . $start_place);
    error_log('MPTBM Debug - Post ID: ' . $post_id);
    
    $end_locations = MPTBM_Function::get_end_location($start_place, $post_id);
    
    // Debug logging
    error_log('MPTBM Debug - End locations found: ' . print_r($end_locations, true));
    
    // If no end locations found from manual pricing, show all available locations
    if (sizeof($end_locations) == 0) {
        $all_locations = MPTBM_Function::get_all_start_location();
        // Remove the current start location from end options
        $end_locations = array_filter($all_locations, function($location) use ($start_place) {
            return $location !== $start_place;
        });
        error_log('MPTBM Debug - Using all locations as fallback: ' . print_r($end_locations, true));
    }
    
    if (sizeof($end_locations) > 0) {
        
        ?>
	    <span><?php esc_html_e('Drop-Off Location', 'ecab-taxi-booking-manager'); ?></span>
        <select class="formControl mptbm_map_end_place" id="mptbm_manual_end_place">
            <option selected disabled><?php esc_html_e(' Select Destination Location', 'ecab-taxi-booking-manager'); ?></option>
            <?php foreach ($end_locations as $location) { ?>
                <option value="<?php echo esc_attr($location); ?>"><?php echo esc_html(MPTBM_Function::get_taxonomy_name_by_slug( $location,'locations' )); ?></option>
            <?php } ?>
        </select>
        <i class="fas fa-map-marker-alt mptbm_left_icon allCenter"></i>
    <?php } else { ?>
        <span class="fas fa-map-marker-alt"><?php esc_html_e(' Can not find any Destination Location', 'ecab-taxi-booking-manager'); ?></span><?php
    }