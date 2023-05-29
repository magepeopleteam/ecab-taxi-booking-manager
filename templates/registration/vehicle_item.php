<?php
    $post_id = $post_id ?? '';
    $distance = $distance ?? $_COOKIE['mptbm_distance'];
    $duration = $duration ?? $_COOKIE['mptbm_duration'];
    $label = $label ?? MPTBM_Function::get_name();
    $start_place = $start_place ?? MP_Global_Function::data_sanitize($_POST['start_place']);
    $end_place = $end_place ?? MP_Global_Function::data_sanitize($_POST['end_place']);
    $location_exit = MPTBM_Function::location_exit($post_id, $start_place, $end_place);
    if ($location_exit && $post_id) {
        //$product_id = MP_Global_Function::get_post_info($post_id, 'link_wc_product');
        $thumbnail = MP_Global_Function::get_image_url($post_id);
        $price = MPTBM_Function::get_price($post_id, $distance, $duration, $start_place, $end_place);
        $wc_price = MP_Global_Function::wc_price($post_id, $price);
        $raw_price = MP_Global_Function::price_convert_raw($wc_price);
        $display_features = MP_Global_Function::get_post_info($post_id, 'display_mptbm_features', 'on');
        $all_features = MP_Global_Function::get_post_info($post_id, 'mptbm_features');
        ?>
        <div class="_dLayout_dFlex mptbm_booking_item" data-placeholder>
            <div class="_max_150_mR">
                <div class="bg_image_area" data-href="<?php echo get_the_permalink($post_id); ?>" data-placeholder>
                    <div data-bg-image="<?php echo esc_attr($thumbnail); ?>"></div>
                </div>
            </div>
            <div class="fdColumn _fullWidth mptbm_list_details">
                <h5 data-href="<?php echo get_the_permalink($post_id); ?>"><?php echo get_the_title($post_id); ?></h5>
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
                                        <?php echo esc_html($label); ?>:
                                        <?php echo esc_html($text); ?>
                                    </li>
                                <?php } ?>
                        </ul>
                    <?php } else { ?>
                        <div></div>
                    <?php } ?>
                    <div class="_min_150_mL_xs">
                        <h4 class="textCenter"> <?php echo MP_Global_Function::esc_html($wc_price); ?></h4>
                        <button type="button" class="_dButton_xs_w_150 mptbm_transport_select"
                                data-transport-name="<?php echo get_the_title($post_id); ?>"
                                data-transport-price="<?php echo esc_attr($raw_price); ?>"
                                data-post-id="<?php echo esc_attr($post_id); ?>"
                                data-open-text="<?php esc_html_e('Select Car', 'mptbm_plugin'); ?>"
                                data-close-text="<?php esc_html_e('Selected', 'mptbm_plugin'); ?>"
                                data-open-icon="" data-close-icon="fas fa-check">
                            <span class="" data-icon></span>&nbsp;&nbsp;
                            <span data-text><?php esc_html_e('Select Car', 'mptbm_plugin'); ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }