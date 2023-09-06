<?php
if (!defined('ABSPATH'))
{
    die;
} // Cannot access pages directly.

/**
 * Class MPTBM_Wc_Checkout
 *
 * @since 1.0
 *  
 * */
if (!class_exists('MPTBM_Wc_Checkout'))
{
    class MPTBM_Wc_Checkout 
    {
        private $error;

        public function __construct()
        {
            $this->error = new WP_Error();
            add_filter('mp_settings_sec_reg', array($this, 'settings_sec_reg'), 10);
			add_filter('mp_settings_sec_fields', array($this, 'settings_sec_fields'), 10);
            add_action('admin_notices',array($this, 'mp_admin_notice' ) );
        }

        public function settings_sec_reg($default_sec): array 
        {
            $sections = array(
                array(
                    'id' => 'mptbm_checkout_settings',
                    'title' => __('Checkout Settings', 'mptbm_plugin')
                ),
            );

            return array_merge($default_sec, $sections);
        }

        public function settings_sec_fields($default_fields): array
        {
            $settings_fields = array(
                'mptbm_checkout_settings' => apply_filters('filter_mptbm_checkout_settings', 
                    array(
                        array(
							'name' => 'hide_checkout_order_additional_information',
							'label' => esc_html__('Hide Order Additional Information Section', 'mptbm_plugin'),
							'desc' => esc_html__('Please check if you want to Hide Order Additional Information Section.', 'mptbm_plugin'),
							'type' => 'multicheck',
							'default' => array(
								'yes' => 'yes',
							),
							'options' => array(
								'yes' => esc_html__(' Yes', 'mptbm_plugin'),
							)
						),
                        array(
							'name' => 'hide_checkout_order_review',
							'label' => esc_html__('Hide Order Review Section', 'mptbm_plugin'),
							'desc' => esc_html__('Please check if you want to Hide Order Review Section.', 'mptbm_plugin'),
							'type' => 'multicheck',
							'default' => array(
								'yes' => 'yes',
							),
							'options' => array(
								'yes' => esc_html__(' Yes', 'mptbm_plugin'),
							)
						),
                        array(
							'name' => 'hide_checkout_billing_company',
							'label' => esc_html__('Hide "Company name" Field', 'mptbm_plugin'),
							'desc' => esc_html__('Please check if you want to  Hide "Company name" Field.', 'mptbm_plugin'),
							'type' => 'multicheck',
							'default' => array(
								'yes' => 'yes',
							),
							'options' => array(
								'yes' => esc_html__(' Yes', 'mptbm_plugin'),
							)
						),
                        array(
							'name' => 'hide_checkout_billing_country',
							'label' => esc_html__('Hide "Country / Region" Field', 'mptbm_plugin'),
							'desc' => esc_html__('Please check if you want to  Hide "Country / Region" Field.', 'mptbm_plugin'),
							'type' => 'multicheck',
							'default' => array(
								'yes' => 'yes',
							),
							'options' => array(
								'yes' => esc_html__(' Yes', 'mptbm_plugin'),
							)
						),
                        array(
							'name' => 'hide_checkout_billing_address_1',
							'label' => esc_html__('Hide "Street address 1" Field', 'mptbm_plugin'),
							'desc' => esc_html__('Please check if you want to  Hide "Street address 1" Field.', 'mptbm_plugin'),
							'type' => 'multicheck',
							'default' => array(
								'yes' => 'yes',
							),
							'options' => array(
								'yes' => esc_html__(' Yes', 'mptbm_plugin'),
							)
						),
                        array(
							'name' => 'hide_checkout_billing_address_2',
							'label' => esc_html__('Hide "Street address 2" Field', 'mptbm_plugin'),
							'desc' => esc_html__('Please check if you want to  Hide "Street address 2" Field.', 'mptbm_plugin'),
							'type' => 'multicheck',
							'default' => array(
								'yes' => 'yes',
							),
							'options' => array(
								'yes' => esc_html__(' Yes', 'mptbm_plugin'),
							)
						),
                        array(
							'name' => 'hide_checkout_billing_city',
							'label' => esc_html__('Hide "Town / City" Field', 'mptbm_plugin'),
							'desc' => esc_html__('Please check if you want to  Hide "Town / City" Field.', 'mptbm_plugin'),
							'type' => 'multicheck',
							'default' => array(
								'yes' => 'yes',
							),
							'options' => array(
								'yes' => esc_html__(' Yes', 'mptbm_plugin'),
							)
						),
                        array(
							'name' => 'hide_checkout_billing_state',
							'label' => esc_html__('Hide "District" Field', 'mptbm_plugin'),
							'desc' => esc_html__('Please check if you want to  Hide "District" Field.', 'mptbm_plugin'),
							'type' => 'multicheck',
							'default' => array(
								'yes' => 'yes',
							),
							'options' => array(
								'yes' => esc_html__(' Yes', 'mptbm_plugin'),
							)
						),
                        array(
							'name' => 'hide_checkout_billing_postcode',
							'label' => esc_html__('Hide "Postcode / ZIP" Field', 'mptbm_plugin'),
							'desc' => esc_html__('Please check if you want to  Hide "Postcode / ZIP" Field.', 'mptbm_plugin'),
							'type' => 'multicheck',
							'default' => array(
								'yes' => 'yes',
							),
							'options' => array(
								'yes' => esc_html__(' Yes', 'mptbm_plugin'),
							)
						),
                    )
                )
            );

            return array_merge($default_fields, $settings_fields);
        }

        public function save_mptbm_pro_wc_account_field_settings()
        {
            // Save the
        }

        public function mp_admin_notice()
        {				
            //MPTBM_Pro_Wc_Checkout_Fields::mp_error_notice($this->error);
        }
        
    }

    new MPTBM_Wc_Checkout();
}