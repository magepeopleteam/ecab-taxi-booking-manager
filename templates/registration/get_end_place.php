<?php
    $start_place = MP_Global_Function::data_sanitize($_POST['start_place']);
    $price_based = MP_Global_Function::data_sanitize($_POST['price_based']);
    $post_id = MP_Global_Function::data_sanitize($_POST['post_id']);
    $end_locations = MPTBM_Function::get_manual_end_location($start_place, $post_id);
    if (sizeof($end_locations) > 0) {
        ?>
        <span class="fas fa-map-marker-alt"><?php esc_html_e(' Destination Location', 'mptbm_plugin'); ?></span>
        <select class="formControl mptbm_map_end_place" id="mptbm_manual_end_place">
            <option selected disabled><?php esc_html_e(' Select Destination Location', 'mptbm_plugin'); ?></option>
            <?php foreach ($end_locations as $location) { ?>
                <option value="<?php echo esc_attr($location); ?>"><?php echo esc_html($location); ?></option>
            <?php } ?>
        </select>
    <?php } else { ?>
        <span class="fas fa-map-marker-alt"><?php esc_html_e(' Can not find any Destination Location', 'mptbm_plugin'); ?></span><?php
    }