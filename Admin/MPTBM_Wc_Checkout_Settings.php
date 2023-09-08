<?php
if (!defined('ABSPATH'))
{
    die;
} // Cannot access pages directly.

/**
 * Class MPTBM_Wc_Checkout_Default
 *
 * @since 1.0
 *  
 * */
if (!class_exists('MPTBM_Wc_Checkout_Default'))
{
    class MPTBM_Wc_Checkout_Default 
    {
        private $error;

        public function __construct()
        {
            $this->error = new WP_Error();
            add_action('mptbm_wc_checkout_tab', array($this, 'tab_item'));
            add_action('mptbm_wc_checkout_tab_content', array($this, 'tab_content'), 10, 1);
            add_action('admin_init', [ $this, 'save_mptbm_wc_checkout_settings' ]);            
            //add_action('wp_loaded', array( $this,'apply' ), 7  );
            add_action('admin_notices',array($this, 'mp_admin_notice' ) );
        }

        public function apply()
        {
            			
        }

        public function tab_item()
        {
            ?>
                <li class="tab-item" data-tabs-target="#mptbm_wc_checkout_settings"><i class="dashicons dashicons-admin-generic text-primary"></i> Checkout Settings <i class="i i-chevron-right dashicons dashicons-arrow-right-alt2"></i></li>
            <?php
        }

        public function tab_content($contents)
        {
            ?>
                <div class="tab-content" id="mptbm_wc_checkout_settings">
                    <h2>Checkout Settings</h2>
                    <!-- <table class="wc_gateways wp-list-table widefat striped"> -->
                    <div>
                    <table class="wc_gateways wp-list-table widefat striped">
						<tbody>
                            <tr>
                                <td><label for="hide_order_additional_information_section"><span class="span-checkout-setting">Hide Order Additional Information Section</span></label></td>
                                <td><input id="hide_order_additional_information_section" name="hide_order_additional_information_section" type="checkbox" /></td>
                            </tr>
                            <tr>
                                <td><label for="hide_order_review_section"><span class="span-checkout-setting">Hide Order Review Section</span></label></td>
                                <td><input id="hide_order_review_section" name="hide_order_review_section" type="checkbox" /></td>
                            </tr>
						</tbody>
					</table>
                    </div>
                </div>
            <?php
        }

        public function save_mptbm_wc_checkout_settings()
        {
            // Save the
        }

        public function mp_admin_notice()
        {				
            MPTBM_Wc_Checkout_Fields::mp_error_notice($this->error);
        }
        
    }

    new MPTBM_Wc_Checkout_Default();
}