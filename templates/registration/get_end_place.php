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
    $end_locations = MPTBM_Function::get_end_location($start_place, $post_id);
    if (sizeof($end_locations) > 0) {
        
        ?>
	    <span><i class="fas fa-map-marker-alt _textTheme_mR_xs"></i><?php esc_html_e('Drop-Off Location', 'ecab-taxi-booking-manager'); ?></span>
        <select class="formControl mptbm_map_end_place" id="mptbm_manual_end_place">
            <option selected disabled><?php esc_html_e(' Select Destination Location', 'ecab-taxi-booking-manager'); ?></option>
            <?php foreach ($end_locations as $location) { ?>
                <option value="<?php echo esc_attr($location); ?>"><?php echo esc_html(MPTBM_Function::get_taxonomy_name_by_slug( $location,'locations' )); ?></option>
            <?php } ?>
        </select>
    <?php } else { ?>
        <span class="fas fa-map-marker-alt"><?php esc_html_e(' Can not find any Destination Location', 'ecab-taxi-booking-manager'); ?></span><?php
    }