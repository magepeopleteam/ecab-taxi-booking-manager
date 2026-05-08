<?php

class MPTBM_taxi_Date_Advanced_Settings
{
    public function __construct(){
        add_action('mptbm_date_and_advanced_settings', [ $this, 'mptbm_date_and_advanced_settings'], 10, 1 );
    }

    public function mptbm_date_and_advanced_settings( $post_id ){
        error_log( print_r( [ '$post_id' => $post_id ], true ) );
        ?>
        <div class="mptbm_taxi_advanced_wrapper">
            <header class="mptbm_taxi_advanced_main_header">
                <h1>Advanced Settings</h1>
                <p>here you can configure operational date time, driver configuration and others</p>
            </header>

            <section class="mptbm_taxi_advanced_card">
                <div class="mptbm_taxi_advanced_card_header">
                    <div class="mptbm_taxi_advanced_title_block">
                        <h3>General Date Configuration</h3>
                        <p>Here you can configure general date.</p>
                    </div>
                    <label class="mptbm_taxi_advanced_toggle">
                        <input type="checkbox" checked>
                        <span class="mptbm_taxi_advanced_slider"></span>
                    </label>
                </div>
                <div class="mptbm_taxi_advanced_card_body">
                    <div class="mptbm_taxi_advanced_form_row">
                        <div class="mptbm_taxi_advanced_field_group">
                            <label>Date Type *</label>
                            <div class="mptbm_taxi_advanced_input_wrap">
                                <select><option>Repeated</option></select>
                                <small>Specifies the date type: "Repeated" for recurring dates, or "Particular" for a specific date</small>
                            </div>
                        </div>
                    </div>
                    <div class="mptbm_taxi_advanced_form_row">
                        <div class="mptbm_taxi_advanced_field_group">
                            <label>Repeated Start Date *</label>
                            <div class="mptbm_taxi_advanced_input_wrap">
                                <input type="text" value="Thu 7 May, 2026" class="mptbm_taxi_advanced_calendar_icon">
                                <small>Sets the start date for recurring services</small>
                            </div>
                        </div>
                    </div>
                    <div class="mptbm_taxi_advanced_form_row">
                        <div class="mptbm_taxi_advanced_field_group">
                            <label>Repeated after *</label>
                            <div class="mptbm_taxi_advanced_input_wrap">
                                <input type="number" value="1">
                                <small>Defines the number of days after which the service or event will repeat</small>
                            </div>
                        </div>
                    </div>
                    <div class="mptbm_taxi_advanced_form_row">
                        <div class="mptbm_taxi_advanced_field_group">
                            <label>Maximum Advanced Day Booking *</label>
                            <div class="mptbm_taxi_advanced_input_wrap">
                                <input type="number" value="60">
                                <small>Sets the maximum number of days in advance a booking can be made</small>
                            </div>
                        </div>
                    </div>
                    <div class="mptbm_taxi_advanced_inline_toggle">
                        <div>
                            <strong>Make Transport Available For 24 Hours</strong>
                            <p>By default slider is ON but you can keep it off by switching this option</p>
                        </div>
                        <label class="mptbm_taxi_advanced_toggle">
                            <input type="checkbox" checked>
                            <span class="mptbm_taxi_advanced_slider"></span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="mptbm_taxi_advanced_card">
                <div class="mptbm_taxi_advanced_card_header">
                    <div class="mptbm_taxi_advanced_title_block">
                        <h3>Schedule Date Configuration</h3>
                        <p>Here you can configure Schedule date.</p>
                    </div>
                    <label class="mptbm_taxi_advanced_toggle">
                        <input type="checkbox" checked>
                        <span class="mptbm_taxi_advanced_slider"></span>
                    </label>
                </div>

                <div class="mptbm_taxi_advanced_table_container">
                    <div class="mptbm_taxi_advanced_table_head">
                        <div class="mptbm_taxi_advanced_col_day">Day</div>
                        <div class="mptbm_taxi_advanced_col_start">Start Time</div>
                        <div class="mptbm_taxi_advanced_col_to">To</div>
                        <div class="mptbm_taxi_advanced_col_end">End Time</div>
                    </div>
                    <div class="mptbm_taxi_advanced_table_row mptbm_taxi_advanced_row_default">
                        <div class="mptbm_taxi_advanced_col_day">Default</div>
                        <div class="mptbm_taxi_advanced_col_start"><select><option>Please select</option></select></div>
                        <div class="mptbm_taxi_advanced_col_to">To</div>
                        <div class="mptbm_taxi_advanced_col_end"><select><option>12:00 am</option></select></div>
                    </div>

                    <div class="mptbm_taxi_advanced_mid_nav">
                        <div class="mptbm_taxi_advanced_btns_left">
                            <button class="mptbm_taxi_advanced_btn_secondary">Previous Step</button>
                            <button class="mptbm_taxi_advanced_btn_secondary">Save Draft</button>
                        </div>
                        <div class="mptbm_taxi_advanced_step_text">Step 3 of 5: Operations</div>
                        <button class="mptbm_taxi_advanced_btn_primary">Next Step</button>
                    </div>

                    <div class="mptbm_taxi_advanced_weekly_list">
                        <div class="mptbm_taxi_advanced_table_row">
                            <div class="mptbm_taxi_advanced_col_day">Tuesday</div>
                            <div class="mptbm_taxi_advanced_col_start"><select><option>Default</option></select></div>
                            <div class="mptbm_taxi_advanced_col_to">To</div>
                            <div class="mptbm_taxi_advanced_col_end"><select><option>Default</option></select></div>
                        </div>
                        <div class="mptbm_taxi_advanced_table_row">
                            <div class="mptbm_taxi_advanced_col_day">Wednesday</div>
                            <div class="mptbm_taxi_advanced_col_start"><select><option>Default</option></select></div>
                            <div class="mptbm_taxi_advanced_col_to">To</div>
                            <div class="mptbm_taxi_advanced_col_end"><select><option>Default</option></select></div>
                        </div>
                        <div class="mptbm_taxi_advanced_table_row">
                            <div class="mptbm_taxi_advanced_col_day">Thursday</div>
                            <div class="mptbm_taxi_advanced_col_start"><select><option>Default</option></select></div>
                            <div class="mptbm_taxi_advanced_col_to">To</div>
                            <div class="mptbm_taxi_advanced_col_end"><select><option>Default</option></select></div>
                        </div>
                        <div class="mptbm_taxi_advanced_table_row">
                            <div class="mptbm_taxi_advanced_col_day">Friday</div>
                            <div class="mptbm_taxi_advanced_col_start"><select><option>Default</option></select></div>
                            <div class="mptbm_taxi_advanced_col_to">To</div>
                            <div class="mptbm_taxi_advanced_col_end"><select><option>Default</option></select></div>
                        </div>
                        <div class="mptbm_taxi_advanced_table_row">
                            <div class="mptbm_taxi_advanced_col_day">Saturday</div>
                            <div class="mptbm_taxi_advanced_col_start"><select><option>Default</option></select></div>
                            <div class="mptbm_taxi_advanced_col_to">To</div>
                            <div class="mptbm_taxi_advanced_col_end"><select><option>Default</option></select></div>
                        </div>
                        <div class="mptbm_taxi_advanced_table_row">
                            <div class="mptbm_taxi_advanced_col_day">Sunday</div>
                            <div class="mptbm_taxi_advanced_col_start"><select><option>Default</option></select></div>
                            <div class="mptbm_taxi_advanced_col_to">To</div>
                            <div class="mptbm_taxi_advanced_col_end"><select><option>Default</option></select></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mptbm_taxi_advanced_card">
                <div class="mptbm_taxi_advanced_card_header">
                    <div class="mptbm_taxi_advanced_title_block">
                        <h3>Off Days & Dates Configuration</h3>
                        <p>Here you can configure Off Days & Dates.</p>
                    </div>
                    <label class="mptbm_taxi_advanced_toggle">
                        <input type="checkbox" checked>
                        <span class="mptbm_taxi_advanced_slider"></span>
                    </label>
                </div>
                <div class="mptbm_taxi_advanced_card_body">
                    <div class="mptbm_taxi_advanced_offday_row">
                        <div class="mptbm_taxi_advanced_offday_labels">
                            <strong>Off Day</strong>
                            <span>Select checkbox for off day</span>
                        </div>
                        <div class="mptbm_taxi_advanced_checkbox_group">
                            <label><input type="checkbox"> Monday</label>
                            <label><input type="checkbox"> Tuesday</label>
                            <label><input type="checkbox"> Wednesday</label>
                            <label><input type="checkbox"> Thursday</label>
                            <label><input type="checkbox"> Friday</label>
                            <label><input type="checkbox"> Saturday</label>
                            <label><input type="checkbox"> Sunday</label>
                        </div>
                    </div>
                    <div class="mptbm_taxi_advanced_add_off_row">
                        <div class="mptbm_taxi_advanced_offday_labels">
                            <strong>Off Dates</strong>
                            <span>Add off dates</span>
                        </div>
                        <button class="mptbm_taxi_advanced_btn_primary_alt">+ Add New Off Date</button>
                    </div>
                </div>
            </section>

            <section class="mptbm_taxi_advanced_card">
                <div class="mptbm_taxi_advanced_card_header">
                    <div class="mptbm_taxi_advanced_title_block">
                        <h3>Driver Settings</h3>
                        <p>Here you can set a driver who's role is driver in registration.</p>
                    </div>
                    <label class="mptbm_taxi_advanced_toggle">
                        <input type="checkbox" checked>
                        <span class="mptbm_taxi_advanced_slider"></span>
                    </label>
                </div>
                <div class="mptbm_taxi_advanced_card_body">
                    <div class="mptbm_taxi_advanced_driver_select_row">
                        <label>Select Driver <br><small>Select a driver from this list.</small></label>
                        <select><option>John Conner</option></select>
                    </div>
                    <div class="mptbm_taxi_advanced_driver_info_box">
                        <div class="mptbm_taxi_advanced_info_col">
                            <label>DRIVER'S NAME</label>
                            <p>John Conner</p>
                        </div>
                        <div class="mptbm_taxi_advanced_info_col">
                            <label>USERNAME</label>
                            <p>John</p>
                        </div>
                        <div class="mptbm_taxi_advanced_info_col">
                            <label>EMAIL</label>
                            <p>eyesblade30@gmail.com</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mptbm_taxi_advanced_card">
                <div class="mptbm_taxi_advanced_card_header">
                    <div class="mptbm_taxi_advanced_title_block">
                        <h3>Tax Settings Information</h3>
                        <p>Configure and manage tax settings</p>
                    </div>
                    <label class="mptbm_taxi_advanced_toggle">
                        <input type="checkbox">
                        <span class="mptbm_taxi_advanced_slider"></span>
                    </label>
                </div>
                <div class="mptbm_taxi_advanced_tax_alert">
                    <span class="mptbm_taxi_advanced_alert_icon">i</span>
                    <p>Tax not active. Please add Tax settings from woocommerce.</p>
                </div>
            </section>
        </div>
    <?php }

}

new MPTBM_taxi_Date_Advanced_Settings();