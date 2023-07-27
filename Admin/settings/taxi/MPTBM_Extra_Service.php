<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists('MPTBM_Extra_Service') ) {
		class MPTBM_Extra_Service {
			public function __construct() {
				add_action( 'add_meta_boxes', array( $this, 'mptbm_extra_service_meta' ) );
				add_action( 'save_post', array( $this, 'save_ex_service_settings' ), 99, 1 );
				//********************//
				add_action( 'mptbm_extra_service_item', array( $this, 'extra_service_item' ) );
				//****************************//
				add_action( 'add_mptbm_settings_tab_content', [ $this, 'ex_service_settings' ], 10, 1 );
				add_action( 'mptbm_settings_save', [ $this, 'save_ex_service' ] );
				//*******************//
				add_action( 'wp_ajax_get_mptbm_ex_service', array( $this, 'get_mptbm_ex_service' ) );
				add_action( 'wp_ajax_nopriv_get_mptbm_ex_service', array( $this, 'get_mptbm_ex_service' ) );
			}
			public function mptbm_extra_service_meta() {
				add_meta_box( 'mp_meta_box_panel', '<span class="dashicons dashicons-info"></span>' . esc_html__( 'Extra Services : ', 'mptbm_plugin' ) . get_the_title( get_the_id() ), array( $this, 'mptbm_extra_service' ), 'mptbm_extra_services', 'normal', 'high' );
			}
			public function mptbm_extra_service() {
				$post_id        = get_the_id();
				$extra_services = MP_Global_Function::get_post_info( $post_id, 'mptbm_extra_service_infos', array() );
				wp_nonce_field( 'mptbm_extra_service_nonce', 'mptbm_extra_service_nonce' );
				?>
				<div class="mpStyle">
					<div class="mptbm_extra_service_settings padding">
						<h5><?php esc_html_e( 'Global Extra Service Settings', 'mptbm_plugin' ); ?></h5>
						<?php MPTBM_Settings::info_text( 'mptbm_extra_services_global' ); ?>
						<div class="mp_settings_area mT">
							<div class="divider"></div>
							<div class="_ovAuto_mT_xs">
								<table>
									<thead>
									<tr>
										<th><span><?php esc_html_e( 'Service Icon', 'mptbm_plugin' ); ?></span></th>
										<th><span><?php esc_html_e( 'Service Name', 'mptbm_plugin' ); ?></span></th>
										<th><span><?php esc_html_e( 'Short description', 'mptbm_plugin' ); ?></span></th>
										<th><span><?php esc_html_e( 'Service Price', 'mptbm_plugin' ); ?></span></th>
										<th><span><?php esc_html_e( 'Qty Box Type', 'mptbm_plugin' ); ?></span></th>
										<th><span><?php esc_html_e( 'Action', 'mptbm_plugin' ); ?></span></th>
									</tr>
									</thead>
									<tbody class="mp_sortable_area mp_item_insert">
									<?php
										if ( $extra_services && is_array( $extra_services ) && sizeof( $extra_services ) > 0 ) {
											foreach ( $extra_services as $extra_service ) {
												$this->extra_service_item( $extra_service );
											}
										}
									?>
									</tbody>
								</table>
							</div>
							<?php MP_Custom_Layout::add_new_button( esc_html__( 'Add Extra New Service', 'mptbm_plugin' ) ); ?>
							<?php do_action( 'add_mp_hidden_table', 'mptbm_extra_service_item' ); ?>
						</div>
					</div>
				</div>
				<?php
			}
			public function extra_service_item( $field = array() ) {
				$field         = $field ?: array();
				$service_icon  = array_key_exists( 'service_icon', $field ) ? $field['service_icon'] : '';
				$service_name  = array_key_exists( 'service_name', $field ) ? $field['service_name'] : '';
				$service_price = array_key_exists( 'service_price', $field ) ? $field['service_price'] : '';
				$input_type    = array_key_exists( 'service_qty_type', $field ) ? $field['service_qty_type'] : 'inputbox';
				$description   = array_key_exists( 'extra_service_description', $field ) ? $field['extra_service_description'] : '';
				$icon          = $image = "";
				if ( $service_icon ) {
					if ( preg_match( '/\s/', $service_icon ) ) {
						$icon = $service_icon;
					} else {
						$image = $service_icon;
					}
				}
				?>
				<tr class="mp_remove_area">
					<td>
						<?php do_action( 'mp_add_icon_image', 'service_icon[]', $icon, $image ); ?>
					</td>
					<td>
						<label>
							<input type="text" class="formControl mp_name_validation" name="service_name[]" placeholder="<?php esc_attr_e( 'EX: Driver', 'mptbm_plugin' ); ?>" value="<?php echo esc_attr( $service_name ); ?>"/>
						</label>
					</td>
					<td>
						<label>
							<textarea class="formControl" name="extra_service_description[]" placeholder="<?php esc_attr_e( 'EX: Description', 'mptbm_plugin' ); ?>"><?php echo esc_html( $description ); ?></textarea>
						</label>
					</td>
					<td>
						<label>
							<input type="number" pattern="[0-9]*" step="0.01" class="formControl mp_price_validation" name="service_price[]" placeholder="<?php esc_attr_e( 'EX: 10', 'mptbm_plugin' ); ?>" value="<?php echo esc_attr( $service_price ); ?>"/>
						</label>
					</td>
					<td>
						<label>
							<select name="service_qty_type[]" class='formControl'>
								<option value="inputbox" <?php echo esc_attr( $input_type == 'inputbox' ? 'selected' : '' ); ?>><?php esc_html_e( 'Input Box', 'mptbm_plugin' ); ?></option>
								<option value="dropdown" <?php echo esc_attr( $input_type == 'dropdown' ? 'selected' : '' ); ?>><?php esc_html_e( 'Dropdown List', 'mptbm_plugin' ); ?></option>
							</select>
						</label>
					</td>
					<td><?php MP_Custom_Layout::move_remove_button(); ?></td>
				</tr>
				<?php
			}
			public function save_ex_service_settings( $post_id ) {
				if ( ! isset( $_POST['mptbm_extra_service_nonce'] ) || ! wp_verify_nonce( $_POST['mptbm_extra_service_nonce'], 'mptbm_extra_service_nonce' ) && defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE && ! current_user_can( 'edit_post', $post_id ) ) {
					return;
				}
				if ( get_post_type( $post_id ) == 'mptbm_extra_services' ) {
					$extra_service_data = $this->ex_service_data( $post_id );
					update_post_meta( $post_id, 'mptbm_extra_service_infos', $extra_service_data );
				}
			}
			//**************************************//
			public function ex_service_settings( $post_id ) {
				$display            = MP_Global_Function::get_post_info( $post_id, 'display_mptbm_extra_services', 'on' );
				$service_id         = MP_Global_Function::get_post_info( $post_id, 'mptbm_extra_services_id', $post_id );
				$active             = $display == 'off' ? '' : 'mActive';
				$checked            = $display == 'off' ? '' : 'checked';
				$all_ex_services_id = MPTBM_Query::query_post_id( 'mptbm_extra_services' );
				?>
				<div class="tabsItem mptbm_extra_services_setting" data-tabs="#mptbm_settings_ex_service">
					<h5 class="dFlex">
						<span class="mR"><?php esc_html_e( 'On/Off Extra Service Settings', 'mptbm_plugin' ); ?></span>
						<?php MP_Custom_Layout::switch_button( 'display_mptbm_extra_services', $checked ); ?>
					</h5>
					<?php MPTBM_Settings::info_text( 'display_mptbm_extra_services' ); ?>
					<div data-collapse="#display_mptbm_extra_services" class="mp_settings_area mT <?php echo esc_attr( $active ); ?>">
						<div class="divider"></div>
						<label class="max_600">
							<span class="max_300"><?php esc_html_e( 'Select extra option :', 'mptbm_plugin' ); ?></span> <select class="formControl" name="mptbm_extra_services_id">
								<option value="" selected><?php esc_html_e( 'Select extra option', 'mptbm_plugin' ); ?></option>
								<option value="<?php echo esc_attr( $post_id ); ?>" <?php echo esc_attr( $service_id == $post_id ? 'selected' : '' ); ?>><?php esc_html_e( 'Custom', 'mptbm_plugin' ); ?></option>
								<?php if ( sizeof( $all_ex_services_id ) > 0 ) { ?>
									<?php foreach ( $all_ex_services_id as $ex_services_id ) { ?>
										<option value="<?php echo esc_attr( $ex_services_id ); ?>" <?php echo esc_attr( $service_id == $ex_services_id ? 'selected' : '' ); ?>><?php echo get_the_title( $ex_services_id ); ?></option>
									<?php } ?>
								<?php } ?>
							</select>
						</label>
						<?php MPTBM_Settings::info_text( 'mptbm_extra_services_id' ); ?>
						<div class="divider"></div>
						<div class="mptbm_extra_service_area">
							<?php $this->ex_service_table( $service_id, $post_id ); ?>
						</div>
					</div>
				</div>
				<?php
			}
			public function ex_service_table( $service_id, $post_id ) {
				if ( $service_id && $post_id ) {
					$extra_services = MP_Global_Function::get_post_info( $service_id, 'mptbm_extra_service_infos', [] );
					?>
					<div class="_ovAuto_mT_xs">
						<table>
							<thead>
							<tr>
								<th><span><?php esc_html_e( 'Service Icon', 'mptbm_plugin' ); ?></span></th>
								<th><span><?php esc_html_e( 'Service Name', 'mptbm_plugin' ); ?></span></th>
								<th><span><?php esc_html_e( 'Short description', 'mptbm_plugin' ); ?></span></th>
								<th><span><?php esc_html_e( 'Service Price', 'mptbm_plugin' ); ?></span></th>
								<th><span><?php esc_html_e( 'Qty Box Type', 'mptbm_plugin' ); ?></span></th>
								<th><span><?php esc_html_e( 'Action', 'mptbm_plugin' ); ?></span></th>
							</tr>
							</thead>
							<tbody class="mp_sortable_area mp_item_insert">
							<?php
								if ( sizeof( $extra_services ) > 0 ) {
									foreach ( $extra_services as $extra_service ) {
										$this->extra_service_item( $extra_service );
									}
								}
							?>
							</tbody>
						</table>
					</div>
					<?php
					if ( $service_id == $post_id ) {
						MP_Custom_Layout::add_new_button( esc_html__( 'Add Extra New Service', 'mptbm_plugin' ) );
						do_action( 'add_mp_hidden_table', 'mptbm_extra_service_item' );
					}
				}
			}
			public function save_ex_service( $post_id ) {
				if ( get_post_type( $post_id ) == MPTBM_Function::get_cpt() ) {
					$display = MP_Global_Function::get_submit_info( 'display_mptbm_extra_services' ) ? 'on' : 'off';
					update_post_meta( $post_id, 'display_mptbm_extra_services', $display );
					$ex_id = MP_Global_Function::get_submit_info( 'mptbm_extra_services_id', $post_id);
					update_post_meta( $post_id, 'mptbm_extra_services_id', $ex_id );
					if ( $ex_id == $post_id ) {
						$extra_service_data = $this->ex_service_data( $post_id );
						update_post_meta( $post_id, 'mptbm_extra_service_infos', $extra_service_data );
					}
				}
			}
			public function ex_service_data( $post_id ) {
				$new_extra_service         = array();
				$extra_icon                = MP_Global_Function::get_submit_info( 'service_icon', array() );
				$extra_names               = MP_Global_Function::get_submit_info( 'service_name', array() );
				$extra_price               = MP_Global_Function::get_submit_info( 'service_price', array() );
				$extra_qty_type            = MP_Global_Function::get_submit_info( 'service_qty_type', array() );
				$extra_service_description = MP_Global_Function::get_submit_info( 'extra_service_description', array() );
				$extra_count               = count( $extra_names );
				for ( $i = 0; $i < $extra_count; $i ++ ) {
					if ( $extra_names[ $i ] && $extra_price[ $i ] >= 0 ) {
						$new_extra_service[ $i ]['service_icon']              = $extra_icon[ $i ] ?? '';
						$new_extra_service[ $i ]['service_name']              = $extra_names[ $i ];
						$new_extra_service[ $i ]['service_price']             = $extra_price[ $i ];
						$new_extra_service[ $i ]['service_qty_type']          = $extra_qty_type[ $i ] ?? 'inputbox';
						$new_extra_service[ $i ]['extra_service_description'] = $extra_service_description[ $i ] ?? '';
					}
				}
				return apply_filters( 'filter_mptbm_extra_service_data', $new_extra_service, $post_id );
			}
			public function get_mptbm_ex_service() {
				$post_id    = $_REQUEST['post_id'] ?MP_Global_Function::data_sanitize($_REQUEST['post_id']): '';
				$service_id = $_REQUEST['ex_id'] ?MP_Global_Function::data_sanitize($_REQUEST['ex_id']): '';
				$this->ex_service_table( $service_id, $post_id );
				die();
			}
		}
		new MPTBM_Extra_Service();
	}