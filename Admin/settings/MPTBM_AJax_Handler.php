<?php
/*
 * @Author 		rubelcuet10@gmail.com
 * Copyright: 	mage-people.com
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_AJax_Handler')) {
    class MPTBM_AJax_Handler
    {
        public function __construct(){

            add_action('wp_ajax_mptbm_get_services_data', [$this, 'mptbm_get_services_data']);
            add_action('wp_ajax_nopriv_mptbm_get_services_data', [$this, 'mptbm_get_services_data']);
        }

        public function mptbm_get_services_data() {

            // Verify nonce
            check_ajax_referer( 'mptbm_extra_service_nonce', 'nonce' );

            // Get service ID
            $service_id = isset( $_POST['service_id'] )
                ? absint( $_POST['service_id'] )
                : '';
            // Get service ID
            $post_id = isset( $_POST['post_id'] )
                ? absint( $_POST['post_id'] )
                : '';

            if ( ! $service_id ) {

                wp_send_json_error( [
                    'message' => 'Invalid service ID',
                ] );
            }

            ob_start();
            MPTBM_Rent_Custom_Editor::extra_service_item( $post_id, $service_id );
            $service_date = ob_get_clean();

            wp_send_json_success( [
                'service_date'    => $service_date,
            ] );
        }

    }

    new MPTBM_AJax_Handler();
}