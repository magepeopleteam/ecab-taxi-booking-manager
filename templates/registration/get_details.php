<?php
    if (!defined('ABSPATH')) {
        exit;
    }
    $post_id = $post_id ?? '';
    $price_based = $price_based ?? '';
    $price_based = $post_id && $post_id > 0 ? MP_Global_Function::get_post_info($post_id, 'mptbm_price_based') : $price_based;
    $all_dates = [
        0 => date('Y-m-d'),
        1 => date('Y-m-d', strtotime(' +1 day')),
        2 => date('Y-m-d', strtotime(' +2 day')),
        3 => date('Y-m-d', strtotime(' +3 day'))
    ];
?>
    <div class="mpRow">
        <div class="col_6  mpForm">
            <div class="_dLayout_mZero">
                <h3 class="_textCenter_mB"><?php esc_html_e('Start Booking', 'mptbm_plugin'); ?></h3>
                <input type="hidden" name="mptbm_price_based" value="<?php echo esc_attr($price_based); ?>"/>
                <input type="hidden" name="mptbm_filter_post_id" value="<?php echo esc_attr($post_id); ?>"/>
                <label class="fdColumn">
                    <input type="hidden" id="mptbm_map_start_date" value=""/>
                    <span class="fas fa-calendar-alt"><?php esc_html_e('Pick-Up Date', 'mptbm_plugin'); ?></span>
                    <input type="text" id="mptbm_start_date" class="formControl date_type" placeholder="<?php esc_html_e('Select Date', 'mptbm_plugin'); ?>" value=""/>
                </label>
                <label class="fdColumn _mT_xs">
                    <span class="fas fa-clock"><?php esc_html_e('Pick-Up Time', 'mptbm_plugin'); ?></span>
                    <select id="mptbm_map_start_time" class="formControl">
                        <option selected><?php esc_html_e('Please Select Time', 'mptbm_plugin'); ?></option>
                        <option value="9:00"><?php esc_html_e('9.00 AM', 'mptbm_plugin'); ?></option>
                        <option value="9:15"><?php esc_html_e('9.15 AM', 'mptbm_plugin'); ?></option>
                        <option value="9:30"><?php esc_html_e('9.30 AM', 'mptbm_plugin'); ?></option>
                        <option value="9:45"><?php esc_html_e('9.45 AM', 'mptbm_plugin'); ?></option>
                        <option value="10:00"><?php esc_html_e('10.00 AM', 'mptbm_plugin'); ?></option>
                    </select>
                </label>
                <label class="fdColumn _mT_xs">
                    <span class="fas fa-map-marker-alt"><?php esc_html_e('Pick-Up Location', 'mptbm_plugin'); ?></span>
                    <?php if ($price_based == 'manual') { ?><?php $all_start_locations = MPTBM_Function::get_manual_start_location($post_id); ?>
                        <select id="mptbm_manual_start_place" class="formControl">
                            <option selected disabled><?php esc_html_e(' Select Pick-Up Location', 'mptbm_plugin'); ?></option>
                            <?php if (sizeof($all_start_locations) > 0) { ?><?php foreach ($all_start_locations as $start_location) { ?>
                                <option value="<?php echo esc_attr($start_location); ?>"><?php echo esc_html($start_location); ?></option>
                            <?php } ?><?php } ?>
                        </select>
                    <?php } else { ?>
                        <input type="text" id="mptbm_map_start_place" class="formControl" placeholder="<?php esc_html_e('start Location', 'mptbm_plugin'); ?>" value=""/>
                    <?php } ?>
                </label>
                <label class="fdColumn _mT_xs mptbm_manual_end_place">
                    <span class="fas fa-map-marker-alt"><?php esc_html_e('Drop-Off Location', 'mptbm_plugin'); ?></span>
                    <?php if ($price_based == 'manual') { ?>
                        <select class="formControl">
                            <option selected disabled><?php esc_html_e('Drop-Off Location', 'mptbm_plugin'); ?></option>
                        </select>
                    <?php } else { ?>
                        <input type="text" id="mptbm_map_end_place" class="formControl" placeholder="<?php esc_html_e(' Enter Drop-Off Location', 'mptbm_plugin'); ?>" value=""/>
                    <?php } ?>
                </label>
                <div class="divider"></div>
                <button type="button" class="_themeButton_fullWidth" id="mptbm_get_vehicle"><?php esc_html_e('Search', 'mptbm_plugin'); ?></button>
            </div>
        </div>
        <div class="col_6 _pL fdColumn">
            <div class="fullHeight">
                <div id="mptbm_map_area"></div>
            </div>
            <div class="_dLayout mptbm_distance_time">
                <div class="_dFlex_separatorRight">
                    <div class="_dFlex_pR_xs">
                        <h1 class="mR_xs"><span class="fas fa-route textTheme"></span></h1>
                        <div class="fdColumn">
                            <h6><?php esc_html_e('TOTAL DISTANCE', 'mptbm_plugin'); ?></h6>
                            <strong class="mptbm_total_distance"></strong>
                        </div>
                    </div>
                    <div class="dFlex">
                        <h1 class="_mLR_xs"><span class="fas fa-clock textTheme"></span></h1>
                        <div class="fdColumn">
                            <h6><?php esc_html_e('TOTAL TIME', 'mptbm_plugin'); ?></h6>
                            <strong class="mptbm_total_time"></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
    do_action('mp_load_date_picker_js', '#mptbm_start_date', $all_dates);
