<?php

	if ( ! defined( 'ABSPATH' ) )
	{
		die;
	} // Cannot access pages directly.

    /**
     * Class MPTBM_Wc_Checkout_Fields
     *
     * @since 1.0
     *  
    * */
	if ( ! class_exists( 'MPTBM_Wc_Checkout_Fields' ) ) 
	{
		class MPTBM_Wc_Checkout_Fields 
		{
			private $error;
            private $options;
            private $allowed_extensions;
            private $allowed_mime_types;

			public function __construct()
			{
				$this->error = new WP_Error();
                
                add_action('add_mptbm_admin_script', array($this,'admin_enqueue'));
                add_action('add_mptbm_frontend_script', array($this,'frontend_enqueue'),99);
				
				add_action('admin_menu', array($this,'checkout_menu'));
				add_action('admin_notices',array($this, 'mp_admin_notice' ) );				
			}

            public function admin_enqueue()
            {
                //wp_enqueue_style('mptbm_checkout_common', MPTBM_PLUGIN_URL . '/assets/checkout/css/mptbm-pro-styles.css', array(), time());
                //wp_enqueue_script('mptbm_checkout_common', MPTBM_PLUGIN_URL . '/assets/checkout/js/mptbm-pro-styles.js', array('jquery'), time(), true);

                wp_enqueue_style('mptbm_checkout', MPTBM_PLUGIN_URL . '/assets/checkout/css/mptbm-pro-checkout.css', array(), time());
                wp_enqueue_script('mptbm_checkout', MPTBM_PLUGIN_URL . '/assets/checkout/js/mptbm-pro-checkout.js', array('jquery'), time(), true);
                
                wp_enqueue_script('mptbm_checkout_custom_script', 'https://code.jquery.com/ui/1.12.1/jquery-ui.js', array('jquery', 'jquery-ui-core', 'jquery-ui-sortable'), time(), true);
            
            }

            public function frontend_enqueue()
            {
                wp_enqueue_style('mptbm_checkout_front_style', MPTBM_PLUGIN_URL . '/assets/checkout/front/css/mptbm-pro-checkout-front-style.css', array(), time());
                wp_enqueue_script('mptbm_checkout_front_script', MPTBM_PLUGIN_URL . '/assets/checkout/front/js/mptbm-pro-checkout-front-script.js', array('jquery'), time(), true);
            }

			public function checkout_menu() 
			{
				$cpt = MPTBM_Function::get_cpt();
                add_submenu_page('edit.php?post_type=' . $cpt, esc_html__('Checkout Fields', 'mptbm-rent'), esc_html__('Checkout Fields', 'mptbm-rent'), 'manage_options', 'mptbm_wc_checkout_fields', array($this, 'wc_checkout_fields'));
			}

			public function wc_checkout_fields()
			{
                if (!current_user_can('administrator')) 
				{
					wp_die(__('You do not have sufficient permissions to access this page.'));
				}

                do_action('wc_checkout_fields');
                
                self::checkout_field_list();

			}

            public function checkout_field_list()
            {
                ?>
                <div class="mpStyles">
                    <div class="checkout">
                        <div class="modal-container">
                            <div class="modal" id="field-modal">
                                <div class="modal-content">
                                    <span class="close">&times;</span>
                                    <div class="custom-form-container">
                                        <div class="custom-form">
                                            <h2>Checkout Field</h2>
                                            <form method="post">

                                                <input type="hidden" name="action" required>
                                                <input type="hidden" name="key" required>
                                                <input type="hidden" name="old_name">
                                                <input type="hidden" name="new_name">
                                                <input type="hidden" name="new_type">

                                                <label for="type">Type:</label>
                                                <select name="type" id="type" required>
                                                    <option value="" disabled>Select an option</option>
                                                    <option value="text">Text</option>
                                                    <option value="select">Select</option>
                                                    <option value="file">Image</option>
                                                </select>

                                                <label for="name">Name:</label>
                                                <input type="text" name="name" id="name" required>

                                                <label for="label">Label:</label>
                                                <input type="text" name="label" id="label" required>

                                                <label for="priority">Position:( >= 0 )</label>
                                                <input type="text" pattern="[0-9]+" name="priority" id="priority">

                                                <label for="name">Class:</label>
                                                <input type="text" name="class" id="class">

                                                <label for="name">Validation:</label>
                                                <input type="text" name="validate" id="validate">

                                                <div class="custom-var-attr-section">
                                                    <label for="placeholder">Placeholder:</label>
                                                    <input type="text" name="placeholder" id="placeholder">                                                    
                                                </div>
                                                
                                                <label><input type="checkbox" name="required"> Required</label>

                                                <label><input type="checkbox" name="disabled"> Disabled</label>
                                                
                                                <p class="add-nonce"><?php wp_nonce_field( 'mptbm_checkout_field_add', 'mptbm_checkout_field_add_nonce' ); ?></p>
                                                <p class="edit-nonce"><?php wp_nonce_field( 'mptbm_checkout_field_edit', 'mptbm_checkout_field_edit_nonce' ); ?></p>
                                                <button type="submit">Submit</button>
                                            </form>
                                        </div>                
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mpStyles">
                    <div class="checkout">
                        <div class="tab-container">
                            <ul class="tab-menu">
                                <h3>CHECKOUT FIELDS</h3>
                                <!-- <div class="hl"></div> -->
                                <?php do_action('mptbm_wc_checkout_tab'); ?>
                            </ul>

                            <div class="tab-content-container">
                                <?php do_action('mptbm_wc_checkout_tab_content',MPTBM_Wc_Checkout_Fields_Helper::get_checkout_fields_for_list()); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
            }
            
			public function mp_admin_notice()
			{				
				self::mp_error_notice($this->error);
			}

            public static function mp_error_notice($error)
			{				
				if($error->has_errors())
				{
					foreach($error->get_error_messages() as $error)
					{
						$class = 'notice notice-error';
						printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), wp_kses_post( $error ) );
					}					
				}
			}

		}

		new MPTBM_Wc_Checkout_Fields();
	}