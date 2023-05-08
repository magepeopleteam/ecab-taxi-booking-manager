<?php
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'MPTBM_Admin' ) ) {
		class MPTBM_Admin {
			public function __construct() {
				if ( is_admin() ) {
					$this->load_file();
					add_action( 'init', [ $this, 'add_taxonomy' ] );
				}
			}
			private function load_file(): void {
				require_once MPTBM_PLUGIN_DIR . '/Admin/MAGE_Setting_API.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Dummy_Import.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Settings_Global.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Hidden_Product.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_CPT.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Quick_Setup.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Status.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Save.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Settings.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_General_Settings.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_Price_Settings.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_Extra_Service.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_Gallery_Settings.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/MP_Select_Icon_image.php';
			}
			public function add_taxonomy() {
				new MPTBM_Dummy_Import();
			}
		}
		new MPTBM_Admin();
	}