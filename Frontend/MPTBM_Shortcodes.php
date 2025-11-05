<?php
/*
	* @Author 		magePeople
	* Copyright: 	mage-people.com
	*/
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.
if (!class_exists('MPTBM_Shortcodes')) {
	class MPTBM_Shortcodes
	{
		public function __construct()
		{
			add_shortcode('mptbm_booking', array($this, 'mptbm_booking'));
		}
		public function mptbm_booking($attribute)
		{
			$defaults = $this->default_attribute();
			$params = shortcode_atts($defaults, $attributes, 'mptbm_booking');
			foreach ($params as $key => $value) {
				// Basic sanitization — strips tags, newlines, and special chars
				$params[$key] = sanitize_text_field($value);
			}
			ob_start();
			do_action('mptbm_transport_search', $params);
			return ob_get_clean();
		}
		public function default_attribute()
		{
			return array(
				"cat" => "0",
				"org" => "0",
				"style" => 'list',
				"show" => '9',
				"pagination" => "yes",
				"city" => "",
				"country" => "",
				'sort' => 'ASC',
				'status' => '',
				"pagination-style" => "load_more",
				"column" => 3,
				"price_based" => 'dynamic',
				'progressbar' => 'yes',
				'map' => 'yes',
				'form' => 'horizontal',
				'tab' => 'no',
				'tabs' => 'distance,hourly,manual'
			);
		}
	}
	new MPTBM_Shortcodes();
}
