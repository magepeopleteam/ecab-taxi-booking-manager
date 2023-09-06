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
                $this->prepare_mptbm_pro_custom_checkout_fields();
                add_filter('woocommerce_checkout_fields' , array($this, 'get_checkout_fields_for_checkout'));                
            }

            public function prepare_mptbm_pro_custom_checkout_fields() 
            {
                self::$settings_options = get_option('mptbm_checkout_settings');
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
                                $field_array['custom_field'] = '1';

                                if ( (isset($field_array['deleted']) && $field_array['deleted'] == 'deleted') || (isset($field_array['disabled']) && $field_array['disabled'] == '1') || self::check_checkout_settings_hidden_field(self::$settings_options,$key,$name))  
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

            public static function check_checkout_settings_hidden_field($settings_options,$key,$name) 
            {
                if($key == 'billing')
                {
                    if($name == 'billing_company' && isset($settings_options['hide_checkout_billing_company']) && isset($settings_options['hide_checkout_billing_company']['yes']))
                    {
                        return true;
                    }
                    elseif($name == 'billing_country' && isset($settings_options['hide_checkout_billing_country']) && isset($settings_options['hide_checkout_billing_country']['yes']))
                    {
                        return true;
                    }
                    elseif($name == 'billing_address_1' && isset($settings_options['hide_checkout_billing_address_1']) && isset($settings_options['hide_checkout_billing_address_1']['yes']))
                    {
                        return true;
                    }
                    elseif($name == 'billing_address_2' && isset($settings_options['hide_checkout_billing_address_2']) && isset($settings_options['hide_checkout_billing_address_2']['yes']))
                    {
                        return true;
                    }
                    elseif($name == 'billing_city' && isset($settings_options['hide_checkout_billing_city']) && isset($settings_options['hide_checkout_billing_city']['yes']))
                    {
                        return true;
                    }
                    elseif($name == 'billing_state' && isset($settings_options['hide_checkout_billing_state']) && isset($settings_options['hide_checkout_billing_state']['yes']))
                    {
                        return true;
                    }
                    elseif($name == 'billing_postcode' && isset($settings_options['hide_checkout_billing_postcode']) && isset($settings_options['hide_checkout_billing_postcode']['yes']))
                    {
                        return true;
                    }
                }

                return false;            
            }

            public static function hide_checkout_order_additional_information_section($settings_options)
            {
                if(isset($settings_options['hide_checkout_order_additional_information']) && isset($settings_options['hide_checkout_order_additional_information']['yes']))
                {
                    return true;
                }
            }

            public static function hide_checkout_order_review_section($settings_options)
            {
                if(isset($settings_options['hide_checkout_order_review']) && isset($settings_options['hide_checkout_order_review']['yes']))
                {
                    return true;
                }
            }
		}

		new MPTBM_Wc_Checkout_Fields();
	}