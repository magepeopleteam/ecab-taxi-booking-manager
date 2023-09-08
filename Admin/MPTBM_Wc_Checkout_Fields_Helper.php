<?php

	if ( ! defined( 'ABSPATH' ) )
	{
		die;
	} // Cannot access pages directly.

    /**
     * Class MPTBM_Wc_Checkout_Fields_Helper
     *
     * @since 1.0
     *  
    * */
	if ( ! class_exists( 'MPTBM_Wc_Checkout_Fields_Helper' ) ) 
	{
		class MPTBM_Wc_Checkout_Fields_Helper 
		{
			private $error;
            //private $settings_options;
            public static $settings_options;
            private $allowed_extensions;
            private $allowed_mime_types;

			public function __construct()
			{
				$this->error = new WP_Error();
                add_action('init', array($this, 'register_field'));				
			}

            function register_field() 
            {
                $this->prepare_mptbm_custom_checkout_fields();
                add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 90, 3);            
            }

            public function add_cart_item_data($cart_item_data, $product_id) 
            {
				$linked_id = MP_Global_Function::get_post_info($product_id, 'link_mptbm_id', $product_id);
				$post_id = is_string(get_post_status($linked_id)) ? $linked_id : $product_id;
				if (get_post_type($post_id) == MPTBM_Function::get_cpt()) 
                {
                    //add_filter('woocommerce_checkout_fields' , array($this, 'get_checkout_fields_for_checkout'), 10);
                }
            }

            public function prepare_mptbm_custom_checkout_fields() 
            {
                self::$settings_options = get_option('mptbm_custom_checkout_fields');
            }

            public static function get_checkout_fields_for_list() 
            {
                $fields = array();
                $checkout_fields = WC()->checkout->get_checkout_fields();
                $fields['billing'] = $checkout_fields['billing'];
                $fields['shipping'] = $checkout_fields['shipping'];
                $fields['order'] = $checkout_fields['order'];

                if(isset($checkout_fields) && is_array($checkout_fields))
                {
                    foreach($checkout_fields as $key => $key_fields)
                    {
                        if(is_array($key_fields))
                        {
                            foreach($key_fields as $name => $field_array)
                            {
                                if (self::check_hidden_field_for_list('delete',self::$settings_options,$key,$name))  
                                {
                                    unset($fields[$key][$name]);
                                }
                                else if(self::check_hidden_field_for_list('disable',self::$settings_options,$key,$name))
                                {
                                    $fields[$key][$name]['disabled'] = '1';
                                }
                                else
                                {
                                    $fields[$key][$name] = $field_array;
                                }
                            }
                        }
                    }
                }

                return $fields;
            }

            public static function get_checkout_fields_for_checkout() 
            {
                $fields = array();
                $checkout_fields = WC()->checkout->get_checkout_fields();
                $fields['billing'] = $checkout_fields['billing'];
                $fields['shipping'] = $checkout_fields['shipping'];
                $fields['order'] = $checkout_fields['order'];

                if(isset($checkout_fields) && is_array($checkout_fields))
                {
                    foreach($checkout_fields as $key => $key_fields)
                    {
                        if(is_array($key_fields))
                        {
                            foreach($key_fields as $name => $field_array)
                            {
                                if (self::check_hidden_field_for_checkout(self::$settings_options,$key,$name))  
                                {
                                    unset($fields[$key][$name]);
                                }
                                else
                                {
                                    $fields[$key][$name] = $field_array;
                                }
                            }
                        }
                    }
                }

                if(self::hide_checkout_order_review_section(self::$settings_options))
                {
                    remove_action('woocommerce_checkout_order_review', 'woocommerce_order_review', 10 );
                }

                if(self::hide_checkout_order_additional_information_section(self::$settings_options) || (isset($fields['order']) && is_array($fields['order']) && count($fields['order']) == 0))
                {
                    add_filter('woocommerce_enable_order_notes_field', '__return_false');
                }

                return $fields;
            }

            public static function check_hidden_field_for_list($check_action,$settings_options,$key,$name)
            {
                if($check_action == 'delete')
                {
                    if( ( isset($settings_options[$key][$name]) && (isset($settings_options[$key][$name]['deleted']) && $settings_options[$key][$name]['deleted'] == 'deleted') ) )
                    {
                        return true;
                    }
                }

                if($check_action == 'disable')
                {                    
                    if( ( ( !isset($settings_options[$key][$name]) && self::check_default_disabled_field($key,$name) )  || ( isset($settings_options[$key][$name]) && (isset($settings_options[$key][$name]['disabled']) && $settings_options[$key][$name]['disabled'] == '1') ) ) )
                    {
                        return true;
                    }
                }

                return false;
            }

            public static function check_hidden_field_for_checkout($settings_options,$key,$name) 
            {
                if( ( ( !isset($settings_options[$key][$name]) && self::check_default_disabled_field($key,$name) )  || ( isset($settings_options[$key][$name]) && (isset($settings_options[$key][$name]['deleted']) && $settings_options[$key][$name]['deleted'] == 'deleted') || (isset($settings_options[$key][$name]['disabled']) && $settings_options[$key][$name]['disabled'] == '1') ) ) )
                {
                    return true;
                }

                return false;            
            }

            public static function hide_checkout_order_additional_information_section($settings_options)
            {
                if(!isset($settings_options['hide_checkout_order_additional_information']) || ( isset($settings_options['hide_checkout_order_additional_information']) && isset($settings_options['hide_checkout_order_additional_information']['yes'] ) ) )
                {
                    return true;
                }
            }

            public static function hide_checkout_order_review_section($settings_options)
            {
                if(!isset($settings_options['hide_checkout_order_review']) || ( isset($settings_options['hide_checkout_order_review']) && isset($settings_options['hide_checkout_order_review']['yes'] ) ) )
                {
                    return true;
                }
            }

            public static function check_default_disabled_field($key,$field_name)
            {
                $default_disabled_field = array('billing' => array('billing_company'=>'', 'billing_country'=>'', 'billing_address_1'=>'', 'billing_address_2'=>'', 'billing_city'=>'', 'billing_state'=>'', 'billing_postcode'=>''));
                
                if(isset($default_disabled_field[$key][$field_name]))
                {
                    return true;
                }
                else
                {
                    return false;
                }
            }
		}

		new MPTBM_Wc_Checkout_Fields_Helper();
	}