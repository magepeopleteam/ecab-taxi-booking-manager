<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Guideline')) {
		class MPTBM_Guideline {
			public function __construct() {
				add_action('admin_menu', array($this, 'guideline_menu'));
			}
			public function guideline_menu(){
				$cpt = MPTBM_Function::get_cpt();
				add_submenu_page('edit.php?post_type=' . $cpt, __('Guideline', 'mptbm_plugin'), __('<span>Guideline</span>', 'mptbm_plugin'), 'manage_options', 'mptbm_guideline_page', array($this, 'guideline_page'));
			}
			public function guideline_page(){
				$label = MPTBM_Function::get_name();
				?>
				<div class="wrap"></div>
				<div class="mpStyle">
					<div class=_dShadow_6_adminLayout">
						<h2 class="textCenter"><?php echo esc_html($label) . '  ' . esc_html__('available Shortcode', 'mptbm_plugin'); ?></h2>
						<div class="divider"></div>
						<ul>
							<li><pre>[mptbm_booking]</pre></li>
						</ul>
					</div>
				</div>
				<?php
			}
		}
		new MPTBM_Guideline();
	}