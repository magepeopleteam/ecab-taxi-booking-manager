<?php

class MPTBM_Transportation
{
    public function __construct(){

        add_action('admin_menu', array($this, 'taxi_admin_menu'));
    }

    public function taxi_admin_menu() {
        add_menu_page(
            'Taxi Dashboard',
            'Taxi Booking',
            'manage_options',
            'mptbm_taxi_dashboard',
            array($this, 'taxi_home_page'),
            'dashicons-car',
            6
        );
//        add_submenu_page( 'edit.php?post_type=mptbm_rent', __( 'Event Lists', 'mage-eventpress' ), __( 'Event Lists', 'mage-eventpress' ), 'manage_woocommerce', 'mep_taxi_lists', array( $this, 'display_event_list' ) );
    }

    public function taxi_home_page(){
        $counts = wp_count_posts('mptbm_rent');

        error_log( print_r( [ 'here' => '$counts' ], true ) );
    }

}
new MPTBM_Transportation();