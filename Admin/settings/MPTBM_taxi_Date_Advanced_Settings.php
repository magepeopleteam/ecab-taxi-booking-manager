<?php
/*
   * @Author 		rubelcuet10@gmail.com
   * Copyright: 	mage-people.com
   */
if ( ! defined( 'ABSPATH' ) ) {
    die;
} // Cannot access pages directly.
if ( ! class_exists('MPTBM_taxi_Date_Advanced_Settings') ) {
    class MPTBM_taxi_Date_Advanced_Settings
    {
        public function __construct(){
    //        add_action('mptbm_date_and_advanced_settings', [ $this, 'mptbm_date_and_advanced_settings'], 10, 1 );
            add_action('mptbm_date_and_advanced_settings', [$this, 'date_settings'], 10, 1);
        }


        public function date_settings($post_id) {
            $date_format = MP_Global_Function::date_picker_format();
            $now = date_i18n($date_format, strtotime(current_time('Y-m-d')));
            $date_type = MP_Global_Function::get_post_info($post_id, 'mptbm_date_type', 'repeated');


            $repeated_start_date = MP_Global_Function::get_post_info($post_id, 'mptbm_repeated_start_date');
            $hidden_repeated_start_date = $repeated_start_date ? gmdate('Y-m-d', strtotime($repeated_start_date)) : '';
            $visible_repeated_start_date = $repeated_start_date ? date_i18n($date_format, strtotime($repeated_start_date)) : '';
            $repeated_after = MP_Global_Function::get_post_info($post_id, 'mptbm_repeated_after', 1);
            $active_days = MP_Global_Function::get_post_info($post_id, 'mptbm_active_days', 60);
            $available_for_all_time = MP_Global_Function::get_post_info($post_id, 'mptbm_available_for_all_time', 'on');
            $active = $available_for_all_time == 'off' ? '' : 'mActive';
            $checked = $available_for_all_time == 'off' ? '' : 'checked';


            ?>
            <div class="tabsItem mpStyle" data-tabs="#mptbm_settings_date ">

                <div class="mptbm_rent_editor_wrapper" >
                    <div class="mptbm_rent_editor_header">
                        <h4 class="mptbm_taxi_ex_service_main_title"><?php esc_html_e('General Date Configuration', 'ecab-taxi-booking-manager'); ?></h4>
                        <p class="mptbm_rent_editor_subtitle"><?php esc_html_e('Here you can configure advanced abd general date', 'ecab-taxi-booking-manager'); ?></p>
                    </div>
                    <div class="mptbm_general_date_config" style="padding: 10px">
                        <div class="mptbm_taxi_advanced_card">
                            <div class="mptbm_taxi_advanced_card_header">
                                <div class="mptbm_taxi_advanced_title_block">
                                    <h6><?php esc_html_e('Date Type', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></h6>
                                    <span class="desc"><?php esc_html_e('Specifies the date type: "Repeated" for recurring dates, or "Particular" for a specific date', "ecab-taxi-booking-manager"); ?></span>
                                </div>
                                <div class="mptbm_taxi_advanced_toggle">
                                    <select class="formControl" name="mptbm_date_type" data-collapse-target required>
                                        <option disabled selected><?php esc_html_e('Please select ...', 'ecab-taxi-booking-manager'); ?></option>
                                        <option value="particular" data-option-target="#mp_particular" <?php echo esc_attr($date_type == 'particular' ? 'selected' : ''); ?>><?php esc_html_e('Particular', 'ecab-taxi-booking-manager'); ?></option>
                                        <option value="repeated" data-option-target="#mp_repeated" <?php echo esc_attr($date_type == 'repeated' ? 'selected' : ''); ?>><?php esc_html_e('Repeated', 'ecab-taxi-booking-manager'); ?></option>
                                    </select>
                                </div>

                            </div>
                        </div>
                        <div data-collapse="#mp_particular" class="mptbm_taxi_advanced_card <?php echo esc_attr($date_type == 'particular' ? 'mActive' : ''); ?>">
                            <div class="mptbm_taxi_advanced_card_header">
                                <div class="mptbm_taxi_advanced_title_block">
                                    <h6><?php esc_html_e('Particular Dates', 'ecab-taxi-booking-manager'); ?></h6>
                                    <span class="desc"><?php esc_html_e('Add Particular Dates', 'ecab-taxi-booking-manager'); ?></span>
                                </div>

                                <div class="mp_settings_area">
                                    <div class="mp_item_insert mp_sortable_area">
                                        <?php
                                        $particular_date_lists = MP_Global_Function::get_post_info($post_id, 'mptbm_particular_dates', array());
                                        if (sizeof($particular_date_lists)) {
                                            foreach ($particular_date_lists as $particular_date) {
                                                if ($particular_date) {
                                                    $this->particular_date_item('mptbm_particular_dates[]', $particular_date);
                                                }
                                            }
                                        }
                                        ?>
                                    </div>
                                    <?php MP_Custom_Layout::add_new_button(esc_html__('Add New Particular date', 'ecab-taxi-booking-manager')); ?>
                                    <div class="mp_hidden_content">
                                        <div class="mp_hidden_item">
                                            <?php $this->particular_date_item('mptbm_particular_dates[]'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div data-collapse="#mp_repeated" class=" mptbm_taxi_advanced_card <?php echo esc_attr($date_type == 'repeated' ? 'mActive' : ''); ?>">
                            <div class="mptbm_taxi_advanced_card_header">
                                <div class="mptbm_taxi_advanced_title_block">
                                    <h6><?php esc_html_e('Repeated Start Date', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></h6>
                                    <span class="desc"><?php esc_html_e('Sets the start date for recurring services', 'ecab-taxi-booking-manager'); ?></span>
                                </div>
                                <label class="label">
                                    <input type="hidden" name="mptbm_repeated_start_date" value="<?php echo esc_attr($hidden_repeated_start_date); ?>" required/>
                                    <input type="text" readonly required class="formControl date_type" value="<?php echo esc_attr($visible_repeated_start_date); ?>" placeholder="<?php echo esc_attr($now); ?>"/>
                                </label>
                            </div>
                        </div>

                        <div data-collapse="#mp_repeated" class=" mptbm_taxi_advanced_card <?php echo esc_attr($date_type == 'repeated' ? 'mActive' : ''); ?>">
                            <div class="mptbm_taxi_advanced_card_header">
                                <div class="mptbm_taxi_advanced_title_block">
                                    <h6><?php esc_html_e('Repeated after', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></h6>
                                    <span class="desc"><?php esc_html_e('Defines the number of days after which the service or event will repeat', 'ecab-taxi-booking-manager'); ?></span>
                                </div>
                                <input type="text" name="mptbm_repeated_after" class="formControl mp_number_validation" value="<?php echo esc_attr($repeated_after); ?>"/>
                            </div>
                        </div>

                        <div data-collapse="#mp_repeated" class="mptbm_taxi_advanced_card <?php echo esc_attr($date_type == 'repeated' ? 'mActive' : ''); ?>">
                            <div class="mptbm_taxi_advanced_card_header">
                                <div class="mptbm_taxi_advanced_title_block">
                                    <h6><?php esc_html_e('Maximum Advanced Day Booking', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></h6>
                                    <span class="desc"><?php esc_html_e('Sets the maximum number of days in advance a booking can be made', 'ecab-taxi-booking-manager'); ?></span>
                                </div>
                                <input type="text" name="mptbm_active_days" class="formControl mp_number_validation" value="<?php echo esc_attr($active_days); ?>"/>
                            </div>
                        </div>

                        <div class="mptbm_taxi_advanced_card">
                            <div class="mptbm_taxi_advanced_card_header">
                                <div class="mptbm_taxi_advanced_title_block">
                                    <h6><?php esc_html_e('Make Transport Available For 24 Hours', 'ecab-taxi-booking-manager'); ?></h6>
                                    <span class="desc"><?php MPTBM_Settings::info_text('display_mptbm_features'); ?></span>
                                </div>
                                <?php MP_Custom_Layout::switch_button('mptbm_available_for_all_time', $checked); ?>
                            </div>
                        </div>

                    </div>
                </div>


                <div class="mptbm_rent_editor_wrapper" >
                    <div class="mptbm_rent_editor_header">
                        <h4 class="mptbm_taxi_ex_service_main_title"><?php esc_html_e('Schedule Date Configuration', 'ecab-taxi-booking-manager'); ?></h4>
                        <p class="mptbm_rent_editor_subtitle"><?php esc_html_e('Here you can configure Schedule date.', 'ecab-taxi-booking-manager'); ?></p>
                    </div>
                    <div class="mptbm_taxi_advanced_card">
                        <table>
                            <thead>
                            <tr>
                                <th><?php esc_html_e('Day', 'ecab-taxi-booking-manager'); ?></th>
                                <th><?php esc_html_e('Start Time', 'ecab-taxi-booking-manager'); ?></th>
                                <th><?php esc_html_e('To', 'ecab-taxi-booking-manager'); ?></th>
                                <th><?php esc_html_e('End Time', 'ecab-taxi-booking-manager'); ?></th>

                            </tr>
                            </thead>
                            <tbody>
                            <?php $this->time_slot_tr($post_id, 'default');
                            $days = MP_Global_Function::week_day();
                            foreach ($days as $key => $day) {
                                $this->time_slot_tr($post_id, $key);
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>


                <!-- End Schedule date config -->

                <div class="mptbm_rent_editor_wrapper" >
                    <div class="mptbm_rent_editor_header">
                        <h4 class="mptbm_taxi_ex_service_main_title"><?php esc_html_e('Off Days & Dates Configuration', 'ecab-taxi-booking-manager'); ?></h4>
                        <p class="mptbm_rent_editor_subtitle"><?php esc_html_e('Here you can configure Off Days & Dates.', 'ecab-taxi-booking-manager'); ?></p>
                    </div>
                    <div data-collapse="#mp_repeated" class="mptbm_taxi_advanced_card <?php echo esc_attr($date_type == 'repeated' ? 'mActive' : ''); ?>">
                        <div class="label">
                            <div>
                                <h6><?php esc_html_e('Off Day', 'ecab-taxi-booking-manager'); ?></h6>
                                <span class="desc"><?php esc_html_e('Select checkbox for off day', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                            <div>
                                <?php

                                $off_days = MP_Global_Function::get_post_info($post_id, 'mptbm_off_days');
                                $days = MP_Global_Function::week_day();
                                $off_day_array = explode(',', $off_days);
                                ?>
                                <div class="groupCheckBox mptbm_off_days_conainer">
                                    <input type="hidden" name="mptbm_off_days" value="<?php echo esc_attr($off_days); ?>"/>
                                    <?php foreach ($days as $key => $day) { ?>
                                        <label class="customCheckboxLabel">
                                            <input type="checkbox" <?php echo esc_attr(in_array($key, $off_day_array) ? 'checked' : ''); ?> data-checked="<?php echo esc_attr($key); ?>"/>
                                            <span class="customCheckbox me-1"><?php echo esc_html($day); ?></span>
                                        </label>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="mptbm_taxi_advanced_card">
                        <h6><?php esc_html_e('Off Dates', 'ecab-taxi-booking-manager'); ?></h6>
                        <span class="desc"><?php esc_html_e('Add off dates', 'ecab-taxi-booking-manager'); ?></span>
                    </div>
                    <div class="mp_settings_area" style="padding: 10px">
                        <div class="mp_item_insert mp_sortable_area mb-1">
                            <?php
                            $off_day_lists = MP_Global_Function::get_post_info($post_id, 'mptbm_off_dates', array());
                            if (sizeof($off_day_lists)) {
                                foreach ($off_day_lists as $off_day) {
                                    if ($off_day) {
                                        $this->particular_date_item('mptbm_off_dates[]', $off_day);
                                    }
                                }
                            }
                            ?>
                        </div>
                        <?php MP_Custom_Layout::add_new_button(esc_html__('Add New Off date', 'ecab-taxi-booking-manager')); ?>
                        <div class="mp_hidden_content">
                            <div class="mp_hidden_item">
                                <?php $this->particular_date_item('mptbm_off_dates[]'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php

                $pro_active = class_exists('MPTBM_Dependencies_Pro');
                $drivers = $this->get_driver_list();
                $selected_driver = get_post_meta($post_id,'mptbm_selected_driver',true);
                $selected_driver = $selected_driver ? $selected_driver:'';

                $service_status = get_post_meta($post_id,'mptbm_service_status',true);
                $service_status = $service_status ? $service_status:'';

                wp_nonce_field( 'mptbm_transportation_type_nonce', 'mptbm_transportation_type_nonce' );
                ?>
                <div class="mptbm_rent_editor_wrapper" >
                    <div class="mptbm_rent_editor_header">
                        <h4 class="mptbm_taxi_ex_service_main_title"><?php esc_html_e('Driver Settings', 'ecab-taxi-booking-manager'); ?></h4>
                        <p class="mptbm_rent_editor_subtitle"><?php esc_html_e("Here you can set a driver who's role is driver in registration.", 'ecab-taxi-booking-manager'); ?></p>
                    </div>

                    <div class="mptbm_taxi_advanced_card_body">

                        <?php if ( $pro_active ): ?>

                            <!-- ✅ PRO VERSION FULL FEATURE -->
                            <div class="mptbm_taxi_advanced_driver_select_row">
                                <?php if (!empty($drivers)) : ?>
                                    <label><?php esc_html_e('Select Driver', 'mptbm_plugin_pro'); ?> <br><small>Select a driver from this list.</small></label>
                                    <select name="mptbm_selected_driver" id="mptbm_selected_driver">
                                        <option value=""><?php esc_html_e('Select driver', 'mptbm_plugin_pro'); ?></option>

                                        <?php foreach ($drivers as $driver): ?>
                                            <option value="<?php echo esc_attr($driver->ID); ?>"
                                                <?php selected($selected_driver, $driver->ID); ?>>
                                                <?php echo esc_html($driver->display_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <div class="mptbm_no_driver_box">
                                        <p><?php esc_html_e('No driver found.', 'mptbm_plugin_pro'); ?></p>
                                        <a class="button button-primary"
                                           href="<?php echo esc_url(admin_url('edit.php?post_type=mptbm_rent&page=mptbm_driver_list')); ?>">
                                            + <?php esc_html_e( 'Create Driver', 'ecab-taxi-booking-manager' ); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mptbm_taxi_advanced_driver_info_box">
                                <?php if (!empty($selected_driver)): ?>
                                    <?php
                                    $user = get_user_by('ID', $selected_driver);
                                    ?>
                                    <?php if ($user): ?>
                                        <div class="mptbm_taxi_advanced_info_col">
                                            <label><?php esc_html_e("DRIVER'S NAME", 'mptbm_plugin_pro'); ?></label>
                                            <p><?php echo esc_html($user->display_name); ?></p>
                                        </div>

                                        <div class="mptbm_taxi_advanced_info_col">
                                            <label><?php esc_html_e('USERNAME', 'mptbm_plugin_pro'); ?></label>
                                            <p><?php echo esc_html($user->user_login); ?></p>
                                        </div>

                                        <div class="mptbm_taxi_advanced_info_col">
                                            <label><?php esc_html_e('EMAIL', 'mptbm_plugin_pro'); ?></label>
                                            <p><?php echo esc_html($user->user_email); ?></p>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                        <?php else: ?>

                            <!-- 🔒 FREE VERSION LOCKED UI -->
                            <div class="mptbm_pro_locked_box">

                                <label><?php esc_html_e('Select Driver', 'mptbm_plugin_pro'); ?></label>

                                <select id="mptbm_pro_locked_select">
                                    <option>🔒 <?php esc_html_e('Pro Feature Only', 'mptbm_plugin_pro'); ?></option>
                                </select>

                                <p class="mptbm_pro_notice">
                                    <?php esc_html_e('This feature is available in Pro version only.', 'mptbm_plugin_pro'); ?>
                                </p>

                            </div>
                        <?php endif; ?>
                    </div>
                </div>


                 <div class="mptbm_rent_editor_wrapper" >
                    <?php
                    $tax_status = MP_Global_Function::get_post_info($post_id, '_tax_status');
                    $tax_class = MP_Global_Function::get_post_info($post_id, '_tax_class');
                    $all_tax_class = MP_Global_Function::all_tax_list();
                    $checked  = 'checked';
                    $name  = 'mptbm_taxi_enable_tax';
                    ?>
                   <div class="mptbm_rent_editor_header" style="display: flex; justify-content: space-between;">
                        <div class="mptbm_taxi_advanced_title_block">
                            <h4 class="mptbm_taxi_ex_service_main_title"><?php esc_html_e( 'Tax Settings Information', 'ecab-taxi-booking-manager' ); ?></h4>
                            <p><?php esc_html_e( 'Configure and manage tax settings', 'ecab-taxi-booking-manager' ); ?></p>
                        </div>
                        <label class="mptbm_taxi_advanced_toggle">
                            <label class="roundSwitchLabel">
                                <input type="checkbox" name="mptbm_taxi_enable_tax" <?php echo esc_attr($checked); ?>>
                                <span class="roundSwitch" data-collapse-target="#<?php echo esc_attr($name); ?>"></span>
                            </label>
                        </label>
                    </div>
                    <?php if (get_option('woocommerce_calc_taxes') == 'yes') { ?>
                        <div class="">
                            <div>
                                <div class="label">
                                    <div>
                                        <h6><?php esc_html_e('Tax status', 'ecab-taxi-booking-manager'); ?></h6>
                                        <span class="desc"><?php esc_html_e('Select tax status type.', 'ecab-taxi-booking-manager'); ?></span>
                                    </div>
                                    <select class="formControl max_300" name="_tax_status">
                                        <option disabled <?php echo esc_attr(!$tax_status ? 'selected' : ''); ?>><?php esc_html_e('Please Select', 'ecab-taxi-booking-manager');  ?></option>
                                        <option value="taxable" <?php echo esc_attr($tax_status == 'taxable' ? 'selected' : ''); ?>>
                                            <?php esc_html_e('Taxable', 'ecab-taxi-booking-manager'); ?>
                                        </option>
                                        <option value="shipping" <?php echo esc_attr($tax_status == 'shipping' ? 'selected' : ''); ?>>
                                            <?php esc_html_e('Shipping only', 'ecab-taxi-booking-manager'); ?>
                                        </option>
                                        <option value="none" <?php echo esc_attr($tax_status == 'none' ? 'selected' : ''); ?>>
                                            <?php esc_html_e('None', 'ecab-taxi-booking-manager'); ?>
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <div class="label">
                                    <div>
                                        <h6><?php esc_html_e('Tax class', 'ecab-taxi-booking-manager'); ?></h6>
                                        <span class="desc"><?php esc_html_e('Select tax class.', 'ecab-taxi-booking-manager'); ?></span>
                                    </div>
                                    <select class="formControl max_300" name="_tax_class">
                                        <option disabled <?php echo esc_attr(!$tax_class ? 'selected' : ''); ?>><?php esc_html_e('Please Select', 'ecab-taxi-booking-manager');  ?></option>
                                        <option value="standard" <?php echo esc_attr($tax_class == 'standard' ? 'selected' : ''); ?>>
                                            <?php esc_html_e('Standard', 'ecab-taxi-booking-manager'); ?>
                                        </option>
                                        <?php if (sizeof($all_tax_class) > 0) { ?>
                                            <?php foreach ($all_tax_class as $key => $class) { ?>
                                                <option value="<?php echo esc_attr($key); ?>" <?php echo esc_attr($tax_class == $key ? 'selected' : ''); ?>>
                                                    <?php echo esc_html($class); ?>
                                                </option>
                                            <?php } ?>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php }else{ ?>
                        <div class="mptbm_taxi_advanced_tax_alert">
                            <span class="mptbm_taxi_advanced_alert_icon">i</span>
                            <p><?php MPTBM_Layout::msg(esc_html__('Tax not active. Please add Tax settings from woocommerce.', 'ecab-taxi-booking-manager')); ?></p>
                        </div>
                    <?php } ?>

                </div>

                <!-- End Off days and date config -->
            </div>
            <?php
        }

        public function get_driver_list()
        {
            $args = array(
                'role'    => 'mptbm_driver_role', // The role you're looking for
                'orderby' => 'user_nicename',
                'order'   => 'ASC'
            );
            $user_query = new WP_User_Query($args);
            $drivers = $user_query->get_results();
            return  $drivers;
        }
        public function default_text($day) {
            if ($day == 'default') {
                esc_html_e('Please select', 'ecab-taxi-booking-manager');
            }
            else {
                esc_html_e('Default', 'ecab-taxi-booking-manager');
            }
        }
        public function time_slot($time, $stat_time = '', $end_time = '') {
            if ($stat_time >= 0 || $stat_time == '') {
                $time_count = $stat_time == '' ? 0 : $stat_time;
                $end_time = $end_time != '' ? $end_time : 48*30;

                for ($i = 30; $i <= $end_time; $i += 30) {
                    // Calculate hours and minutes
                    $hours = floor($i / 60);
                    $minutes = $i % 60;

                    // Generate the data-value as hours + fraction (minutes / 60)
                    $data_value = $hours + ($minutes / 100);

                    // Format the time for display
                    $time_formatted = sprintf('%02d:%02d', $hours, $minutes);
                    ?>
                    <option  value="<?php echo esc_attr($data_value);?>" <?php echo esc_attr($time != '' && $time == $data_value ? 'selected' : '');?>><?php echo esc_html(MP_Global_Function::date_format($time_formatted, 'time')); ?></option>
                <?php }
            }
        }

        /*************************************/
        public function time_slot_tr($post_id, $day) {
            $start_name = 'mptbm_' . $day . '_start_time';
            $default_start_time = $day == 'default' ? 0.5 : '';

            $start_time = MP_Global_Function::get_post_info($post_id, $start_name, $default_start_time);

            $end_name = 'mptbm_' . $day . '_end_time';
            $default_end_time = $day == 'default' ? 24 : '';

            $end_time = MP_Global_Function::get_post_info($post_id, $end_name, $default_end_time);

            ?>
            <tr>
                <th style="text-transform: capitalize;"><?php echo esc_html($day); ?></th>
                <td class="mptbm_start_time" data-day-name="<?php echo esc_attr($day); ?>">
                    <label>
                        <select class="formControl" name="<?php echo esc_attr($start_name); ?>">
                            <option value="" <?php echo esc_attr($start_time == '' ? 'selected' : ''); ?>>
                                <?php $this->default_text($day); ?>
                            </option>
                            <?php $this->time_slot($start_time); ?>
                        </select>
                    </label>
                </td>
                <td class="textCenter">
                    <strong><?php esc_html_e('To', 'ecab-taxi-booking-manager'); ?></strong>
                </td>
                <td class="mptbm_end_time">
                    <select class="formControl" name="<?php echo esc_attr($end_name); ?>">
                        <option value="" <?php echo esc_attr($end_time == '' ? 'selected' : ''); ?>>
                            <?php $this->default_text($day); ?>
                        </option>
                        <?php $this->time_slot($end_time); ?>
                    </select>

                </td>

            </tr>
            <?php
        }
        public function particular_date_item($name, $date = '') {
            $date_format = MP_Global_Function::date_picker_format();
            $now = date_i18n($date_format, strtotime(current_time('Y-m-d')));
            $hidden_date = $date ? gmdate('Y-m-d', strtotime($date)) : '';
            $visible_date = $date ? date_i18n($date_format, strtotime($date)) : '';
            ?>
            <div class="mp_remove_area my-1">
                <div class="justifyBetween bg-light p-1">
                    <label class="col_8">
                        <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($hidden_date); ?>"/>
                        <input value="<?php echo esc_attr($visible_date); ?>" class="formControl date_type" placeholder="<?php echo esc_attr($now); ?>"/>
                    </label>
                    <?php MP_Custom_Layout::move_remove_button(); ?>
                </div>

            </div>
            <?php
        }

        /*************************************/

    }

    new MPTBM_taxi_Date_Advanced_Settings();
}