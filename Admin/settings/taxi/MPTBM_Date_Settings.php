<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.
if (!class_exists('MPTBM_Date_Settings')) {
    class MPTBM_Date_Settings {
        public function __construct() {
            add_action('add_mptbm_settings_tab_content', [$this, 'date_settings']);
        }
        public function date_settings($post_id){
            ?>
            <div class="tabsItem" data-tabs="#mptbm_settings_date">
                <h5><?php esc_html_e('Date Settings', 'mptbm_plugin'); ?></h5>
                <div class="divider"></div>
                <div class="mp_settings_area">

                </div>
            </div>
            <?php
        }
    }
    new MPTBM_Date_Settings();
}