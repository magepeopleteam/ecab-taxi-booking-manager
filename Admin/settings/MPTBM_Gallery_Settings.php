<?php
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'MPTBM_Gallery_Settings' ) ) {
		class MPTBM_Gallery_Settings {
			public function __construct() {
				add_action( 'add_mptbm_settings_tab_content', [ $this, 'gallery_settings' ] );
				add_action( 'mptbm_settings_save', [ $this, 'save_gallery_settings' ] );
			}
			public function gallery_settings( $post_id ) {
				$display   = MP_Global_Function::get_post_info( $post_id, 'display_mp_slider', 'on' );
				$active    = $display == 'off' ? '' : 'mActive';
				$checked   = $display == 'off' ? '' : 'checked';
				$image_ids = MP_Global_Function::get_post_info( $post_id, 'mp_slider_images', array() );
				?>
				<div class="tabsItem" data-tabs="#mptbm_settings_gallery">
					<h5 class="dFlex">
						<span class="mR"><?php esc_html_e( 'On/Off Slider', 'mptbm_plugin' ); ?></span>
						<?php MP_Custom_Layout::switch_button( 'display_mp_slider', $checked ); ?>
					</h5>
					<?php MPTBM_Settings::info_text( 'display_mp_slider' ); ?>
					<div class="divider"></div>
					<div data-collapse="#display_mp_slider" class="<?php echo esc_attr( $active ); ?>">
						<table>
							<tbody>
							<tr>
								<th><?php esc_html_e( 'Gallery Images ', 'mptbm_plugin' ); ?></th>
								<td colspan="3">
									<?php do_action( 'mp_add_multi_image', 'mp_slider_images', $image_ids ); ?>
								</td>
							</tr>
							<tr>
								<td colspan="4"><?php MPTBM_Settings::info_text( 'mp_slider_images' ); ?></td>
							</tr>
							</tbody>
						</table>
					</div>
				</div>
				<?php
			}
			public function save_gallery_settings( $post_id ) {
				if ( get_post_type( $post_id ) == MPTBM_Function::mp_cpt() ) {
					$slider = MP_Global_Function::get_submit_info( 'display_mp_slider' ) ? 'on' : 'off';
					update_post_meta( $post_id, 'display_mp_slider', $slider );
					$images     = MP_Global_Function::get_submit_info( 'mp_slider_images' );
					$all_images = explode( ',', $images );
					update_post_meta( $post_id, 'mp_slider_images', $all_images );
				}
			}
		}
		new MPTBM_Gallery_Settings();
	}