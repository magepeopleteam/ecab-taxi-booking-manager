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
            add_action('wp_ajax_mptbm_get_driver_info', [$this, 'ajax_get_driver_info']);
            add_action('wp_ajax_mptbm_create_driver', [$this, 'ajax_create_driver']);
            add_action('save_post', [$this, 'save_driver_settings'], 99, 1);
        }

        public function ajax_get_driver_info()
        {
            check_ajax_referer('mptbm_transportation_type_nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error(array('message' => esc_html__('You do not have permission to do this.', 'ecab-taxi-booking-manager')));
            }

            $driver_id = isset($_POST['driver_id']) ? absint($_POST['driver_id']) : 0;

            if (!$driver_id) {
                wp_send_json_error(array('message' => esc_html__('No driver selected.', 'ecab-taxi-booking-manager')));
            }

            $user = get_user_by('ID', $driver_id);

            if (!$user || !in_array('mptbm_driver_role', (array) $user->roles, true)) {
                wp_send_json_error(array('message' => esc_html__('Driver not found.', 'ecab-taxi-booking-manager')));
            }

            wp_send_json_success(array(
                'name'     => $user->display_name,
                'username' => $user->user_login,
                'email'    => $user->user_email,
                'phone'    => get_user_meta($user->ID, 'user_phone', true),
            ));
        }

        public function ajax_create_driver()
        {
            check_ajax_referer('mptbm_create_driver', 'nonce');

            if (!current_user_can('create_users')) {
                wp_send_json_error(
                    array('message' => esc_html__('You do not have permission to add drivers.', 'ecab-taxi-booking-manager')),
                    403
                );
            }

            $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
            $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
            $username = isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username']), true) : '';
            $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
            $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
            $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
            $send_notification = !empty($_POST['send_notification']);

            if ($first_name === '' || $username === '' || $email === '') {
                wp_send_json_error(array('message' => esc_html__('First name, username, and email are required.', 'ecab-taxi-booking-manager')));
            }

            if (!is_email($email)) {
                wp_send_json_error(array('message' => esc_html__('Enter a valid email address.', 'ecab-taxi-booking-manager')));
            }

            if (username_exists($username)) {
                wp_send_json_error(array('message' => esc_html__('This username is already in use.', 'ecab-taxi-booking-manager')));
            }

            if (email_exists($email)) {
                wp_send_json_error(array('message' => esc_html__('This email address is already registered.', 'ecab-taxi-booking-manager')));
            }

            if ($password !== '' && strlen($password) < 8) {
                wp_send_json_error(array('message' => esc_html__('The password must contain at least 8 characters.', 'ecab-taxi-booking-manager')));
            }

            $display_name = trim($first_name . ' ' . $last_name);
            $user_id = wp_insert_user(array(
                'user_login'   => $username,
                'user_email'   => $email,
                'user_pass'    => $password !== '' ? $password : wp_generate_password(20, true, true),
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => $display_name,
                'role'         => 'mptbm_driver_role',
            ));

            if (is_wp_error($user_id)) {
                wp_send_json_error(array('message' => $user_id->get_error_message()));
            }

            if ($phone !== '') {
                update_user_meta($user_id, 'user_phone', $phone);
            }

            if ($send_notification && function_exists('wp_send_new_user_notifications')) {
                wp_send_new_user_notifications($user_id, 'user');
            }

            wp_send_json_success(array(
                'id'       => $user_id,
                'name'     => $display_name,
                'username' => $username,
                'email'    => $email,
                'phone'    => $phone,
                'message'  => esc_html__('Driver added successfully.', 'ecab-taxi-booking-manager'),
            ));
        }

        public function save_driver_settings($post_id)
        {
            if (get_post_type($post_id) !== MPTBM_Function::get_cpt()
                || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
                || wp_is_post_revision($post_id)
                || !current_user_can('edit_post', $post_id)
                || !isset($_POST['mptbm_transportation_type_nonce'])
                || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mptbm_transportation_type_nonce'])), 'mptbm_transportation_type_nonce')
                || !isset($_POST['mptbm_driver_settings_field_present'])) {
                return;
            }

            $driver_id = isset($_POST['mptbm_selected_driver']) ? absint(wp_unslash($_POST['mptbm_selected_driver'])) : 0;

            if (!$driver_id) {
                delete_post_meta($post_id, 'mptbm_selected_driver');
                return;
            }

            $driver = get_user_by('ID', $driver_id);
            if (!$driver || !in_array('mptbm_driver_role', (array) $driver->roles, true)) {
                return;
            }

            update_post_meta($post_id, 'mptbm_selected_driver', $driver_id);
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
            $checked = $available_for_all_time == 'off' ? '' : 'checked';
            $particular_date_lists = (array) MP_Global_Function::get_post_info($post_id, 'mptbm_particular_dates', array());
            $off_days = MP_Global_Function::get_post_info($post_id, 'mptbm_off_days');
            $days = MP_Global_Function::week_day();
            $off_day_array = array_filter(explode(',', $off_days));
            $off_day_lists = (array) MP_Global_Function::get_post_info($post_id, 'mptbm_off_dates', array());


            ?>
            <div class="tabsItem mpStyle" data-tabs="#mptbm_settings_date">

                <div class="mptbm_rent_editor_wrapper mptbm_schedule_section mptbm_date_config_section">
                    <div class="mptbm_rent_editor_header mptbm_schedule_section_header">
                        <div class="mptbm_schedule_heading">
                            <span class="mptbm_schedule_heading_icon mptbm_date_config_icon" aria-hidden="true">
                                <i class="fas fa-calendar-alt"></i>
                            </span>
                            <div>
                                <h4 class="mptbm_rent_editor_title"><?php esc_html_e('General Date Configuration', 'ecab-taxi-booking-manager'); ?></h4>
                                <p class="mptbm_rent_editor_subtitle"><?php esc_html_e('Control when this transportation becomes available and how far ahead customers can book.', 'ecab-taxi-booking-manager'); ?></p>
                            </div>
                        </div>
                        <span
                            class="mptbm_date_mode_badge"
                            data-particular-label="<?php esc_attr_e('Particular dates', 'ecab-taxi-booking-manager'); ?>"
                            data-repeated-label="<?php esc_attr_e('Recurring schedule', 'ecab-taxi-booking-manager'); ?>"
                        >
                            <i class="<?php echo esc_attr($date_type === 'particular' ? 'fas fa-calendar-day' : 'fas fa-sync-alt'); ?>" aria-hidden="true"></i>
                            <span><?php echo esc_html($date_type === 'particular' ? __('Particular dates', 'ecab-taxi-booking-manager') : __('Recurring schedule', 'ecab-taxi-booking-manager')); ?></span>
                        </span>
                    </div>

                    <div class="mptbm_date_config_body">
                        <div class="mptbm_date_type_card">
                            <div class="mptbm_date_field_intro">
                                <span class="mptbm_date_field_icon" aria-hidden="true"><i class="fas fa-calendar-check"></i></span>
                                <div>
                                    <label for="mptbm_date_type"><?php esc_html_e('Availability Type', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></label>
                                    <p><?php esc_html_e('Choose recurring availability or publish only selected dates.', 'ecab-taxi-booking-manager'); ?></p>
                                </div>
                            </div>
                            <div class="mptbm_date_select_wrap">
                                <i class="fas fa-chevron-down" aria-hidden="true"></i>
                                <select id="mptbm_date_type" class="formControl" name="mptbm_date_type" data-collapse-target required>
                                    <option disabled <?php echo esc_attr(!in_array($date_type, array('particular', 'repeated'), true) ? 'selected' : ''); ?>><?php esc_html_e('Please select ...', 'ecab-taxi-booking-manager'); ?></option>
                                    <option value="particular" data-option-target="#mp_particular" <?php echo esc_attr($date_type == 'particular' ? 'selected' : ''); ?>><?php esc_html_e('Particular Dates', 'ecab-taxi-booking-manager'); ?></option>
                                    <option value="repeated" data-option-target="#mp_repeated" <?php echo esc_attr($date_type == 'repeated' ? 'selected' : ''); ?>><?php esc_html_e('Recurring Schedule', 'ecab-taxi-booking-manager'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div data-collapse="#mp_particular" class="mptbm_taxi_advanced_card mptbm_date_subpanel <?php echo esc_attr($date_type == 'particular' ? 'mActive' : ''); ?>">
                            <div class="mptbm_date_subpanel_header">
                                <div class="mptbm_date_field_intro">
                                    <span class="mptbm_date_field_icon is-amber" aria-hidden="true"><i class="fas fa-calendar-day"></i></span>
                                    <div>
                                        <h6><?php esc_html_e('Particular Dates', 'ecab-taxi-booking-manager'); ?></h6>
                                        <p><?php esc_html_e('Add each date on which this transportation can be booked.', 'ecab-taxi-booking-manager'); ?></p>
                                    </div>
                                </div>
                                <span class="mptbm_date_count_badge">
                                    <?php echo esc_html(sprintf(_n('%d date', '%d dates', count($particular_date_lists), 'ecab-taxi-booking-manager'), count($particular_date_lists))); ?>
                                </span>
                            </div>
                            <div class="mp_settings_area mptbm_modern_date_list">
                                <div class="mp_item_insert mp_sortable_area">
                                    <?php
                                    if (sizeof($particular_date_lists)) {
                                        foreach ($particular_date_lists as $particular_date) {
                                            if ($particular_date) {
                                                $this->particular_date_item('mptbm_particular_dates[]', $particular_date);
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                                <?php MP_Custom_Layout::add_new_button(esc_html__('Add Particular Date', 'ecab-taxi-booking-manager')); ?>
                                <div class="mp_hidden_content">
                                    <div class="mp_hidden_item">
                                        <?php $this->particular_date_item('mptbm_particular_dates[]'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div data-collapse="#mp_repeated" class="mptbm_date_repeated_grid <?php echo esc_attr($date_type == 'repeated' ? 'mActive' : ''); ?>">
                            <div class="mptbm_date_field_card">
                                <div class="mptbm_date_field_intro">
                                    <span class="mptbm_date_field_icon" aria-hidden="true"><i class="fas fa-play"></i></span>
                                    <div>
                                        <label for="mptbm_repeated_start_date_display"><?php esc_html_e('Recurring Start Date', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></label>
                                        <p><?php esc_html_e('First day the recurring schedule becomes active.', 'ecab-taxi-booking-manager'); ?></p>
                                    </div>
                                </div>
                                <label class="mptbm_date_input_wrap">
                                    <i class="far fa-calendar-alt" aria-hidden="true"></i>
                                    <input type="hidden" name="mptbm_repeated_start_date" value="<?php echo esc_attr($hidden_repeated_start_date); ?>" required/>
                                    <input id="mptbm_repeated_start_date_display" type="text" readonly required class="formControl date_type" value="<?php echo esc_attr($visible_repeated_start_date); ?>" placeholder="<?php echo esc_attr($now); ?>"/>
                                </label>
                            </div>

                            <div class="mptbm_date_field_card">
                                <div class="mptbm_date_field_intro">
                                    <span class="mptbm_date_field_icon is-violet" aria-hidden="true"><i class="fas fa-redo"></i></span>
                                    <div>
                                        <label for="mptbm_repeated_after"><?php esc_html_e('Repeat Interval', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></label>
                                        <p><?php esc_html_e('How often the availability cycle repeats.', 'ecab-taxi-booking-manager'); ?></p>
                                    </div>
                                </div>
                                <label class="mptbm_number_input_wrap">
                                    <input id="mptbm_repeated_after" type="text" name="mptbm_repeated_after" class="formControl mp_number_validation" value="<?php echo esc_attr($repeated_after); ?>"/>
                                    <span><?php esc_html_e('Days', 'ecab-taxi-booking-manager'); ?></span>
                                </label>
                            </div>

                            <div class="mptbm_date_field_card">
                                <div class="mptbm_date_field_intro">
                                    <span class="mptbm_date_field_icon is-green" aria-hidden="true"><i class="fas fa-hourglass-half"></i></span>
                                    <div>
                                        <label for="mptbm_active_days"><?php esc_html_e('Advance Booking Window', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></label>
                                        <p><?php esc_html_e('Maximum number of days customers can book ahead.', 'ecab-taxi-booking-manager'); ?></p>
                                    </div>
                                </div>
                                <label class="mptbm_number_input_wrap">
                                    <input id="mptbm_active_days" type="text" name="mptbm_active_days" class="formControl mp_number_validation" value="<?php echo esc_attr($active_days); ?>"/>
                                    <span><?php esc_html_e('Days', 'ecab-taxi-booking-manager'); ?></span>
                                </label>
                            </div>
                        </div>

                        <div class="mptbm_date_always_card">
                            <div class="mptbm_date_field_intro">
                                <span class="mptbm_date_field_icon is-green" aria-hidden="true"><i class="fas fa-business-time"></i></span>
                                <div>
                                    <h6><?php esc_html_e('24-Hour Availability', 'ecab-taxi-booking-manager'); ?></h6>
                                    <p><?php esc_html_e('Allow bookings at any time of day without restricting operating hours.', 'ecab-taxi-booking-manager'); ?></p>
                                </div>
                            </div>
                            <div
                                class="mptbm_date_toggle_control"
                                data-enabled-label="<?php esc_attr_e('Always open', 'ecab-taxi-booking-manager'); ?>"
                                data-disabled-label="<?php esc_attr_e('Disabled', 'ecab-taxi-booking-manager'); ?>"
                            >
                                <span class="mptbm_date_toggle_status"><?php echo esc_html($available_for_all_time === 'off' ? __('Disabled', 'ecab-taxi-booking-manager') : __('Always open', 'ecab-taxi-booking-manager')); ?></span>
                                <?php MP_Custom_Layout::switch_button('mptbm_available_for_all_time', $checked); ?>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="mptbm_rent_editor_wrapper mptbm_schedule_section">
                    <div class="mptbm_rent_editor_header mptbm_schedule_section_header">
                        <div class="mptbm_schedule_heading">
                            <span class="mptbm_schedule_heading_icon" aria-hidden="true">
                                <i class="fas fa-calendar-week"></i>
                            </span>
                            <div>
                                <h4 class="mptbm_rent_editor_title"><?php esc_html_e('Schedule Date Configuration', 'ecab-taxi-booking-manager'); ?></h4>
                                <p class="mptbm_rent_editor_subtitle"><?php esc_html_e('Set the default operating window, then customize individual days when needed.', 'ecab-taxi-booking-manager'); ?></p>
                            </div>
                        </div>
                        <span class="mptbm_schedule_header_badge">
                            <span class="mptbm_schedule_header_badge_dot" aria-hidden="true"></span>
                            <?php esc_html_e('Weekly availability', 'ecab-taxi-booking-manager'); ?>
                        </span>
                    </div>
                    <div class="mptbm_schedule_card">
                        <div class="mptbm_schedule_table_wrap">
                        <table class="mptbm_schedule_table">
                            <thead>
                            <tr>
                                <th scope="col">
                                    <span class="mptbm_schedule_column_heading">
                                        <span class="mptbm_schedule_column_icon is-day" aria-hidden="true"><i class="far fa-calendar-alt"></i></span>
                                        <span class="mptbm_schedule_column_copy">
                                            <strong><?php esc_html_e('Day', 'ecab-taxi-booking-manager'); ?></strong>
                                            <small><?php esc_html_e('Operating schedule', 'ecab-taxi-booking-manager'); ?></small>
                                        </span>
                                    </span>
                                </th>
                                <th scope="col">
                                    <span class="mptbm_schedule_column_heading">
                                        <span class="mptbm_schedule_column_icon is-start" aria-hidden="true"><i class="far fa-clock"></i></span>
                                        <span class="mptbm_schedule_column_copy">
                                            <strong><?php esc_html_e('Start Time', 'ecab-taxi-booking-manager'); ?></strong>
                                            <small><?php esc_html_e('Service opens', 'ecab-taxi-booking-manager'); ?></small>
                                        </span>
                                    </span>
                                </th>
                                <th scope="col">
                                    <span class="mptbm_schedule_column_heading">
                                        <span class="mptbm_schedule_column_icon is-end" aria-hidden="true"><i class="fas fa-flag-checkered"></i></span>
                                        <span class="mptbm_schedule_column_copy">
                                            <strong><?php esc_html_e('End Time', 'ecab-taxi-booking-manager'); ?></strong>
                                            <small><?php esc_html_e('Service closes', 'ecab-taxi-booking-manager'); ?></small>
                                        </span>
                                    </span>
                                </th>
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
                        <div class="mptbm_schedule_hint">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            <span><?php esc_html_e('Days left as “Default” automatically follow the daily default hours.', 'ecab-taxi-booking-manager'); ?></span>
                        </div>
                    </div>
                </div>


                <!-- End Schedule date config -->

                <div class="mptbm_rent_editor_wrapper mptbm_schedule_section mptbm_off_dates_section">
                    <div class="mptbm_rent_editor_header mptbm_schedule_section_header">
                        <div class="mptbm_schedule_heading">
                            <span class="mptbm_schedule_heading_icon mptbm_off_dates_icon" aria-hidden="true">
                                <i class="fas fa-calendar-times"></i>
                            </span>
                            <div>
                                <h4 class="mptbm_rent_editor_title"><?php esc_html_e('Off Days & Dates Configuration', 'ecab-taxi-booking-manager'); ?></h4>
                                <p class="mptbm_rent_editor_subtitle"><?php esc_html_e('Block recurring weekdays or individual dates when this transportation is unavailable.', 'ecab-taxi-booking-manager'); ?></p>
                            </div>
                        </div>
                        <span class="mptbm_off_dates_summary">
                            <i class="fas fa-ban" aria-hidden="true"></i>
                            <?php
                            echo esc_html(
                                sprintf(
                                    _n('%d closure configured', '%d closures configured', count($off_day_array) + count($off_day_lists), 'ecab-taxi-booking-manager'),
                                    count($off_day_array) + count($off_day_lists)
                                )
                            );
                            ?>
                        </span>
                    </div>

                    <div class="mptbm_off_dates_body">
                        <div data-collapse="#mp_repeated" class="mptbm_off_days_card <?php echo esc_attr($date_type == 'repeated' ? 'mActive' : ''); ?>">
                            <div class="mptbm_off_card_header">
                                <div class="mptbm_date_field_intro">
                                    <span class="mptbm_date_field_icon is-red" aria-hidden="true"><i class="fas fa-calendar-minus"></i></span>
                                    <div>
                                        <h6><?php esc_html_e('Weekly Off Days', 'ecab-taxi-booking-manager'); ?></h6>
                                        <p><?php esc_html_e('Choose weekdays that should always be unavailable.', 'ecab-taxi-booking-manager'); ?></p>
                                    </div>
                                </div>
                                <span
                                    class="mptbm_off_count"
                                    data-singular="<?php echo esc_attr(_n('%d selected', '%d selected', 1, 'ecab-taxi-booking-manager')); ?>"
                                    data-plural="<?php echo esc_attr(_n('%d selected', '%d selected', 2, 'ecab-taxi-booking-manager')); ?>"
                                >
                                    <?php echo esc_html(sprintf(_n('%d selected', '%d selected', count($off_day_array), 'ecab-taxi-booking-manager'), count($off_day_array))); ?>
                                </span>
                            </div>
                            <div class="groupCheckBox mptbm_off_days_conainer">
                                <input type="hidden" name="mptbm_off_days" value="<?php echo esc_attr($off_days); ?>"/>
                                <?php foreach ($days as $key => $day) { ?>
                                    <label class="customCheckboxLabel">
                                        <input type="checkbox" <?php echo esc_attr(in_array($key, $off_day_array) ? 'checked' : ''); ?> data-checked="<?php echo esc_attr($key); ?>"/>
                                        <span class="customCheckbox me-1">
                                            <i class="fas fa-check" aria-hidden="true"></i>
                                            <?php echo esc_html($day); ?>
                                        </span>
                                    </label>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="mptbm_taxi_advanced_card mptbm_off_dates_card">
                            <div class="mptbm_off_card_header">
                                <div class="mptbm_date_field_intro">
                                    <span class="mptbm_date_field_icon is-amber" aria-hidden="true"><i class="fas fa-calendar-day"></i></span>
                                    <div>
                                        <h6><?php esc_html_e('Specific Off Dates', 'ecab-taxi-booking-manager'); ?></h6>
                                        <p><?php esc_html_e('Add holidays, maintenance dates, or one-time closures.', 'ecab-taxi-booking-manager'); ?></p>
                                    </div>
                                </div>
                                <span class="mptbm_date_count_badge">
                                    <?php echo esc_html(sprintf(_n('%d date', '%d dates', count($off_day_lists), 'ecab-taxi-booking-manager'), count($off_day_lists))); ?>
                                </span>
                            </div>
                            <div class="mp_settings_area mptbm_modern_date_list">
                                <div class="mp_item_insert mp_sortable_area mb-1">
                                    <?php
                                    if (sizeof($off_day_lists)) {
                                        foreach ($off_day_lists as $off_day) {
                                            if ($off_day) {
                                                $this->particular_date_item('mptbm_off_dates[]', $off_day);
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                                <?php MP_Custom_Layout::add_new_button(esc_html__('Add Off Date', 'ecab-taxi-booking-manager')); ?>
                                <div class="mp_hidden_content">
                                    <div class="mp_hidden_item">
                                        <?php $this->particular_date_item('mptbm_off_dates[]'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mptbm_off_dates_hint">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        <span><?php esc_html_e('Specific off dates override the regular weekly schedule for the selected date.', 'ecab-taxi-booking-manager'); ?></span>
                    </div>
                </div>
            </div>

            <div class="tabsItem mpStyle" data-tabs="#wbtm_settings_tax">

                <?php

                $drivers = $this->get_driver_list();
                $selected_driver = get_post_meta($post_id,'mptbm_selected_driver',true);
                $selected_driver = $selected_driver ? $selected_driver:'';
                // Driver assignment is a PRO feature — locked (blurred + disabled behind
                // an overlay) when Pro isn't active, same treatment as Operation Area /
                // Base Location Charges in the vehicle editor.
                $is_pro = class_exists('MPTBM_Dependencies_Pro');

                $service_status = get_post_meta($post_id,'mptbm_service_status',true);
                $service_status = $service_status ? $service_status:'';

                wp_nonce_field( 'mptbm_transportation_type_nonce', 'mptbm_transportation_type_nonce' );
                ?>
                <div class="mptbm_rent_editor_wrapper">
                    <div class="mptbm_rent_editor_header">
                        <h4 class="mptbm_rent_editor_title"><?php esc_html_e('Driver Settings', 'ecab-taxi-booking-manager'); ?></h4>
                        <p class="mptbm_rent_editor_subtitle"><?php esc_html_e("Here you can set a driver who's role is driver in registration.", 'ecab-taxi-booking-manager'); ?></p>
                    </div>

                    <div class="mptbm_taxi_advanced_card_body">

                        <div class="mptbm_pro_lock<?php echo $is_pro ? '' : ' is-locked'; ?>">
                        <?php if ( ! $is_pro ) : ?>
                            <div class="mptbm_pro_lock_overlay">
                                <span class="mptbm_pro_lock_badge"><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'PRO feature', 'ecab-taxi-booking-manager' ); ?></span>
                                <p><?php esc_html_e( 'Assigning a driver to this vehicle is available in the PRO version.', 'ecab-taxi-booking-manager' ); ?></p>
                            </div>
                        <?php endif; ?>
                        <div class="mptbm_pro_lock_content">

                            <input type="hidden" name="mptbm_driver_settings_field_present" value="1">

                            <div class="mptbm_taxi_advanced_card_header">
                                <div class="mptbm_taxi_advanced_title_block">
                                    <h6><?php esc_html_e('Select Driver', 'ecab-taxi-booking-manager'); ?></h6>
                                    <span class="desc"><?php esc_html_e('Assign a registered driver to this transportation.', 'ecab-taxi-booking-manager'); ?></span>
                                </div>
                                <div class="mptbm_driver_selector_actions">
                                    <select class="formControl max_300" name="mptbm_selected_driver" id="mptbm_selected_driver" <?php disabled( ! $is_pro ); ?>>
                                        <option value=""><?php esc_html_e('Select driver', 'ecab-taxi-booking-manager'); ?></option>
                                        <?php foreach ($drivers as $driver): ?>
                                            <option value="<?php echo esc_attr($driver->ID); ?>"
                                                <?php selected($selected_driver, $driver->ID); ?>>
                                                <?php echo esc_html($driver->display_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (current_user_can('create_users')) : ?>
                                        <button type="button" class="button button-primary mptbm_open_driver_modal">
                                            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                                            <?php esc_html_e('Add Driver', 'ecab-taxi-booking-manager'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mptbm_driver_ajax_notice" role="status" aria-live="polite"></div>

                            <?php $driver_user = !empty($selected_driver) ? get_user_by('ID', $selected_driver) : false; ?>
                            <div class="mptbm_taxi_advanced_driver_info_box" style="<?php echo esc_attr($driver_user ? '' : 'display:none;'); ?>">
                                <div class="mptbm_taxi_advanced_info_col">
                                    <label><?php esc_html_e("DRIVER'S NAME", 'ecab-taxi-booking-manager'); ?></label>
                                    <p><?php echo esc_html($driver_user ? $driver_user->display_name : ''); ?></p>
                                </div>
                                <div class="mptbm_taxi_advanced_info_col">
                                    <label><?php esc_html_e('USERNAME', 'ecab-taxi-booking-manager'); ?></label>
                                    <p><?php echo esc_html($driver_user ? $driver_user->user_login : ''); ?></p>
                                </div>
                                <div class="mptbm_taxi_advanced_info_col">
                                    <label><?php esc_html_e('EMAIL', 'ecab-taxi-booking-manager'); ?></label>
                                    <p><?php echo esc_html($driver_user ? $driver_user->user_email : ''); ?></p>
                                </div>
                                <div class="mptbm_taxi_advanced_info_col">
                                    <label><?php esc_html_e('PHONE', 'ecab-taxi-booking-manager'); ?></label>
                                    <p><?php echo esc_html($driver_user ? get_user_meta($driver_user->ID, 'user_phone', true) : ''); ?></p>
                                </div>
                            </div>

                        </div><!-- .mptbm_pro_lock_content -->
                        </div><!-- .mptbm_pro_lock -->

                    </div>
                </div>

                <?php if (current_user_can('create_users')) : ?>
                    <?php wp_nonce_field('mptbm_create_driver', 'mptbm_create_driver_nonce'); ?>
                    <div class="mptbm_driver_modal" id="mptbm_driver_modal" aria-hidden="true">
                        <div class="mptbm_driver_modal_backdrop" data-driver-modal-close></div>
                        <div class="mptbm_driver_modal_dialog" role="dialog" aria-modal="true" aria-labelledby="mptbm_driver_modal_title">
                            <div class="mptbm_driver_modal_header">
                                <div class="mptbm_driver_modal_heading">
                                    <span class="mptbm_driver_modal_icon" aria-hidden="true">
                                        <i class="fas fa-id-card"></i>
                                    </span>
                                    <div>
                                        <h3 id="mptbm_driver_modal_title"><?php esc_html_e('Add a new driver', 'ecab-taxi-booking-manager'); ?></h3>
                                        <p><?php esc_html_e('Create the driver account without leaving this transportation.', 'ecab-taxi-booking-manager'); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="mptbm_driver_modal_close" data-driver-modal-close aria-label="<?php esc_attr_e('Close modal', 'ecab-taxi-booking-manager'); ?>">
                                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                                </button>
                            </div>

                            <div class="mptbm_driver_modal_body">
                                <div class="mptbm_driver_modal_error" role="alert" aria-live="assertive"></div>
                                <div class="mptbm_driver_form_grid">
                                    <label class="mptbm_driver_form_field">
                                        <span><?php esc_html_e('First name', 'ecab-taxi-booking-manager'); ?> <em>*</em></span>
                                        <input type="text" id="mptbm_driver_first_name" autocomplete="given-name">
                                    </label>
                                    <label class="mptbm_driver_form_field">
                                        <span><?php esc_html_e('Last name', 'ecab-taxi-booking-manager'); ?></span>
                                        <input type="text" id="mptbm_driver_last_name" autocomplete="family-name">
                                    </label>
                                    <label class="mptbm_driver_form_field">
                                        <span><?php esc_html_e('Username', 'ecab-taxi-booking-manager'); ?> <em>*</em></span>
                                        <input type="text" id="mptbm_driver_username" autocomplete="username">
                                    </label>
                                    <label class="mptbm_driver_form_field">
                                        <span><?php esc_html_e('Email address', 'ecab-taxi-booking-manager'); ?> <em>*</em></span>
                                        <input type="email" id="mptbm_driver_email" autocomplete="email">
                                    </label>
                                    <label class="mptbm_driver_form_field">
                                        <span><?php esc_html_e('Phone number', 'ecab-taxi-booking-manager'); ?></span>
                                        <input type="tel" id="mptbm_driver_phone" autocomplete="tel">
                                    </label>
                                    <label class="mptbm_driver_form_field">
                                        <span><?php esc_html_e('Password', 'ecab-taxi-booking-manager'); ?></span>
                                        <input type="password" id="mptbm_driver_password" autocomplete="new-password" minlength="8">
                                        <small><?php esc_html_e('Leave blank to generate a secure password.', 'ecab-taxi-booking-manager'); ?></small>
                                    </label>
                                </div>
                                <label class="mptbm_driver_notification_option">
                                    <input type="checkbox" id="mptbm_driver_send_notification" value="1" checked>
                                    <span>
                                        <strong><?php esc_html_e('Email account details to the driver', 'ecab-taxi-booking-manager'); ?></strong>
                                        <small><?php esc_html_e('The driver will receive an email to set or reset their password.', 'ecab-taxi-booking-manager'); ?></small>
                                    </span>
                                </label>
                            </div>

                            <div class="mptbm_driver_modal_footer">
                                <button type="button" class="button mptbm_driver_modal_cancel" data-driver-modal-close>
                                    <?php esc_html_e('Cancel', 'ecab-taxi-booking-manager'); ?>
                                </button>
                                <button type="button" class="button button-primary mptbm_create_driver_button">
                                    <span class="mptbm_create_driver_button_text"><?php esc_html_e('Add Driver', 'ecab-taxi-booking-manager'); ?></span>
                                    <span class="spinner" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php MPTBM_Tax_Settings::tab_content($post_id, false); ?>

                <!-- End Advanced settings -->
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
            $is_default = $day === 'default';
            $is_inherited = !$is_default && $start_time === '' && $end_time === '';
            $days = MP_Global_Function::week_day();
            $day_label = $is_default
                ? esc_html__('Daily Default', 'ecab-taxi-booking-manager')
                : (isset($days[$day]) ? $days[$day] : ucfirst($day));
            $day_short = $is_default
                ? ''
                : (function_exists('mb_substr') ? mb_substr($day_label, 0, 2) : substr($day_label, 0, 2));

            ?>
            <tr
                class="<?php echo esc_attr($is_default ? 'mptbm_schedule_default_row' : 'mptbm_schedule_day_row'); ?>"
                <?php if (!$is_default) { ?>
                    data-inherited-label="<?php esc_attr_e('Uses default hours', 'ecab-taxi-booking-manager'); ?>"
                    data-custom-label="<?php esc_attr_e('Custom hours', 'ecab-taxi-booking-manager'); ?>"
                <?php } ?>
            >
                <th scope="row">
                    <div class="mptbm_schedule_day">
                        <span class="mptbm_schedule_day_badge <?php echo esc_attr($is_default ? 'is-default' : ''); ?>" aria-hidden="true">
                            <?php if ($is_default) { ?>
                                <i class="fas fa-layer-group"></i>
                            <?php } else { ?>
                                <?php echo esc_html(strtoupper($day_short)); ?>
                            <?php } ?>
                        </span>
                        <span class="mptbm_schedule_day_copy">
                            <strong><?php echo esc_html($day_label); ?></strong>
                            <small class="mptbm_schedule_day_state">
                                <?php
                                echo esc_html(
                                    $is_default
                                        ? __('Fallback for every day', 'ecab-taxi-booking-manager')
                                        : ($is_inherited
                                            ? __('Uses default hours', 'ecab-taxi-booking-manager')
                                            : __('Custom hours', 'ecab-taxi-booking-manager'))
                                );
                                ?>
                            </small>
                        </span>
                        <?php if ($is_default) { ?>
                            <span class="mptbm_schedule_status is-primary"><i class="fas fa-check" aria-hidden="true"></i><?php esc_html_e('Default', 'ecab-taxi-booking-manager'); ?></span>
                        <?php } else { ?>
                            <span class="mptbm_schedule_status<?php echo esc_attr($is_inherited ? ' is-hidden' : ''); ?>"><?php esc_html_e('Custom', 'ecab-taxi-booking-manager'); ?></span>
                        <?php } ?>
                    </div>
                </th>
                <td class="mptbm_start_time" data-day-name="<?php echo esc_attr($day); ?>">
                    <label class="mptbm_schedule_time_field">
                        <span class="screen-reader-text">
                            <?php echo esc_html(sprintf(__('Start time for %s', 'ecab-taxi-booking-manager'), $day_label)); ?>
                        </span>
                        <i class="far fa-clock" aria-hidden="true"></i>
                        <select class="formControl" name="<?php echo esc_attr($start_name); ?>">
                            <option value="" <?php echo esc_attr($start_time == '' ? 'selected' : ''); ?>>
                                <?php $this->default_text($day); ?>
                            </option>
                            <?php $this->time_slot($start_time); ?>
                        </select>
                    </label>
                </td>
                <td class="mptbm_end_time">
                    <label class="mptbm_schedule_time_field">
                        <span class="screen-reader-text">
                            <?php echo esc_html(sprintf(__('End time for %s', 'ecab-taxi-booking-manager'), $day_label)); ?>
                        </span>
                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        <select class="formControl" name="<?php echo esc_attr($end_name); ?>">
                            <option value="" <?php echo esc_attr($end_time == '' ? 'selected' : ''); ?>>
                                <?php $this->default_text($day); ?>
                            </option>
                            <?php $this->time_slot($end_time); ?>
                        </select>
                    </label>
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
