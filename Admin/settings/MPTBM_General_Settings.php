<?php
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'MPTBM_General_Settings' ) ) {
		class MPTBM_General_Settings {
			public function __construct() {
				add_action( 'add_mptbm_settings_tab_content', [ $this, 'general_settings' ] );
				add_action( 'add_hidden_mptbm_features_item', [ $this, 'features_item' ] );
				add_action( 'mptbm_settings_save', [ $this, 'save_general_settings' ] );
			}
			public function general_settings( $post_id ) {
				$display_features = MP_Global_Function::get_post_info( $post_id, 'display_mptbm_features', 'on' );
				$active           = $display_features == 'off' ? '' : 'mActive';
				$checked          = $display_features == 'off' ? '' : 'checked';
				$all_features     = MP_Global_Function::get_post_info( $post_id, 'mptbm_features' );
				if ( ! $all_features ) {
					$all_features = array(
						array(
							'label' => esc_html__( 'Name', 'mptbm_plugin' ),
							'icon'  => '',
							'image' => '',
							'text'  => ''
						),
						array(
							'label' => esc_html__( 'Model', 'mptbm_plugin' ),
							'icon'  => '',
							'image' => '',
							'text'  => ''
						),
						array(
							'label' => esc_html__( 'Engine', 'mptbm_plugin' ),
							'icon'  => '',
							'image' => '',
							'text'  => ''
						),
						array(
							'label' => esc_html__( 'Fuel Type', 'mptbm_plugin' ),
							'icon'  => '',
							'image' => '',
							'text'  => ''
						)
					);
				}
				?>
				<div class="tabsItem" data-tabs="#mptbm_general_info">
					<h5><?php esc_html_e( 'General Information Settings', 'mptbm_plugin' ); ?></h5>
					<div class="divider"></div>
					<div class="mp_settings_area">
						<h5 class="dFlex">
							<span class="mR"><?php esc_html_e( 'On/Off Feature', 'mptbm_plugin' ); ?></span>
							<?php MP_Custom_Layout::switch_button( 'display_mptbm_features', $checked ); ?>
						</h5>
						<?php MPTBM_Settings::info_text( 'display_mptbm_features' ); ?>
						<div class="divider"></div>
						<div data-collapse="#display_mptbm_features" class="<?php echo esc_attr( $active ); ?>">
							<table>
								<thead>
								<tr>
									<th class="w_150"><?php esc_html_e( 'Icon/Image', 'mptbm_plugin' ); ?></th>
									<th><?php esc_html_e( 'Label', 'mptbm_plugin' ); ?></th>
									<th><?php esc_html_e( 'Text', 'mptbm_plugin' ); ?></th>
									<th class="w_125"><?php esc_html_e( 'Action', 'mptbm_plugin' ); ?></th>
								</tr>
								</thead>
								<tbody class="mp_sortable_area mp_item_insert">
								<?php
									if ( is_array( $all_features ) && sizeof( $all_features ) > 0 ) {
										foreach ( $all_features as $features ) {
											$this->features_item( $features );
										}
									} else {
										$this->features_item();
									}
								?>
								</tbody>
							</table>
							<div class="divider"></div>
							<?php MP_Custom_Layout::add_new_button( esc_html__( 'Add New Item', 'mptbm_plugin' ) ); ?>

							<?php do_action( 'add_mp_hidden_table', 'add_hidden_mptbm_features_item' ); ?>
						</div>
					</div>
				</div>
				<?php
			}
			public function features_item( $features = array() ) {
				$label = array_key_exists( 'label', $features ) ? $features['label'] : '';
				$text  = array_key_exists( 'text', $features ) ? $features['text'] : '';
				$icon  = array_key_exists( 'icon', $features ) ? $features['icon'] : '';
				$image = array_key_exists( 'image', $features ) ? $features['image'] : '';
				?>
				<tr class="mp_remove_area">
					<td><?php do_action( 'mp_add_icon_image', 'mptbm_features_icon_image[]', $icon, $image ); ?></td>
					<td>
						<label>
							<input class="formControl mp_name_validation" name="mptbm_features_label[]" value="<?php echo esc_attr( $label ); ?>"/>
						</label>
					</td>
					<td>
						<label>
							<input class="formControl mp_name_validation" name="mptbm_features_text[]" value="<?php echo esc_attr( $text ); ?>"/>
						</label>
					</td>
					<td><?php MP_Custom_Layout::move_remove_button(); ?></td>
				</tr>
				<?php
			}
			public function save_general_settings( $post_id ) {
				if ( get_post_type( $post_id ) == MPTBM_Function::mp_cpt() ) {
					$all_features     = [];
					$display_features = MP_Global_Function::get_submit_info( 'display_mptbm_features' ) ? 'on' : 'off';
					update_post_meta( $post_id, 'display_mptbm_features', $display_features );
					$features_label = MP_Global_Function::get_submit_info( 'mptbm_features_label', array() );
					if ( sizeof( $features_label ) > 0 ) {
						$features_text = MP_Global_Function::get_submit_info( 'mptbm_features_text', array() );
						$features_icon = MP_Global_Function::get_submit_info( 'mptbm_features_icon_image', array() );
						$count         = 0;
						foreach ( $features_label as $label ) {
							if ( $label ) {
								$all_features[ $count ]['label'] = $label;
								$all_features[ $count ]['text']  = $features_text[ $count ];
								$all_features[ $count ]['icon']  = '';
								$all_features[ $count ]['image'] = '';
								$current_image_icon              = array_key_exists( $count, $features_icon ) ? $features_icon[ $count ] : '';
								if ( $current_image_icon ) {
									if ( preg_match( '/\s/', $current_image_icon ) ) {
										$all_features[ $count ]['icon'] = $current_image_icon;
									} else {
										$all_features[ $count ]['image'] = $current_image_icon;
									}
								}
								$count ++;
							}
						}
					}
					update_post_meta( $post_id, 'mptbm_features', $all_features );
				}
			}
		}
		new MPTBM_General_Settings();
	}