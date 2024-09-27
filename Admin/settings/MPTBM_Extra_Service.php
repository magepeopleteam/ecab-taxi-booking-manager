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
				add_action( 'save_post', array( $this, 'save_ex_service_settings' ));
				//********************//
				add_action( 'mptbm_extra_service_item', array( $this, 'extra_service_item' ) );
				//****************************//
				add_action( 'add_mptbm_settings_tab_content', [ $this, 'ex_service_settings' ] );
				add_action( 'save_post', [ $this, 'save_ex_service' ] );
				//*******************//
				add_action( 'wp_ajax_get_mptbm_ex_service', array( $this, 'get_mptbm_ex_service' ) );
				add_action( 'wp_ajax_nopriv_get_mptbm_ex_service', array( $this, 'get_mptbm_ex_service' ) );
			}
			public function mptbm_extra_service_meta() {
				$label = MPTBM_Function::get_name();
				add_meta_box( 'mp_meta_box_panel', $label . __( ' > Extra Services Settings' . '<span class="version"> V'.MPTBM_PLUGIN_VERSION.'</span>', 'ecab-taxi-booking-manager' ), array( $this, 'mptbm_extra_service' ), 'mptbm_extra_services', 'normal', 'high' );
			}
			public function mptbm_extra_service() {
				$post_id        = get_the_id();
				$extra_services = MP_Global_Function::get_post_info( $post_id, 'mptbm_extra_service_infos', array() );
				wp_nonce_field( 'mptbm_extra_service_nonce', 'mptbm_extra_service_nonce' );
				?>
				<div class="mpStyle mptbm_settings">
					<div class="tabsContent" style="width: 100%;">	
						<div class="mptbm_extra_service_settings tabsItem">
							<h5><?php esc_html_e( 'Global Extra Service Settings', 'ecab-taxi-booking-manager' ); ?></h5>
							<p><?php MPTBM_Settings::info_text( 'mptbm_extra_services_global' ); ?></p>
							<section class="bg-light" >
								<h6><?php esc_html_e('Extra Service Settings', 'ecab-taxi-booking-manager'); ?></h6>
								<span><?php esc_html_e('Here you can set price', 'ecab-taxi-booking-manager'); ?></span>
							</section>
							<section>
							<div class="mp_settings_area">
								<div class="_ovAuto_mT_xs">
									<table>
										<thead>
										<tr>
											<th><span><?php esc_html_e( 'Service Icon', 'ecab-taxi-booking-manager' ); ?></span></th>
											<th><span><?php esc_html_e( 'Service Name', 'ecab-taxi-booking-manager' ); ?></span></th>
											<th><span><?php esc_html_e( 'Short description', 'ecab-taxi-booking-manager' ); ?></span></th>
											<th><span><?php esc_html_e( 'Service Price', 'ecab-taxi-booking-manager' ); ?></span></th>
											<th><span><?php esc_html_e( 'Qty Box Type', 'ecab-taxi-booking-manager' ); ?></span></th>
											<th><span><?php esc_html_e( 'Action', 'ecab-taxi-booking-manager' ); ?></span></th>
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
								<?php MP_Custom_Layout::add_new_button( esc_html__( 'Add Extra New Service', 'ecab-taxi-booking-manager' ) ); ?>
								<?php do_action( 'add_mp_hidden_table', 'mptbm_extra_service_item' ); ?>
							</div>
							</section>
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
					<td class="text-center">
						<input type="text" class="small mp_name_validation" name="service_name[]" placeholder="<?php esc_attr_e( 'EX: Driver', 'ecab-taxi-booking-manager' ); ?>" value="<?php echo esc_attr( $service_name ); ?>"/>
					</td>
					<td>
						<label>
							<textarea rows="1" cols="5" class="" name="extra_service_description[]" placeholder="<?php esc_attr_e( 'EX: Description', 'ecab-taxi-booking-manager' ); ?>"><?php echo esc_html( $description ); ?></textarea>
						</label>
					</td>
					<td class="text-center">
						<input type="number" pattern="[0-9]*" step="0.01" class="small mp_price_validation" name="service_price[]" placeholder="<?php esc_attr_e( 'EX: 10', 'ecab-taxi-booking-manager' ); ?>" value="<?php echo esc_attr( $service_price ); ?>"/>
					</td>
					<td>
						<select name="service_qty_type[]" class='mideum'>
							<option value="inputbox" <?php echo esc_attr( $input_type == 'inputbox' ? 'selected' : '' ); ?>><?php esc_html_e( 'Input Box', 'ecab-taxi-booking-manager' ); ?></option>
							<option value="dropdown" <?php echo esc_attr( $input_type == 'dropdown' ? 'selected' : '' ); ?>><?php esc_html_e( 'Dropdown List', 'ecab-taxi-booking-manager' ); ?></option>
						</select>
					</td>
					<td><?php MP_Custom_Layout::move_remove_button(); ?></td>
				</tr>
				<?php
			}
			public function save_ex_service_settings( $post_id ) {
				if ( ! isset( $_POST['mptbm_extra_service_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['mptbm_extra_service_nonce'])), 'mptbm_extra_service_nonce' ) && defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE && ! current_user_can( 'edit_post', $post_id ) ) {
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
					<h2 ><?php esc_html_e( 'On/Off Extra Service Settings', 'ecab-taxi-booking-manager' ); ?></h2>
					<p ><?php esc_html_e( 'On/Off Extra Service Settings', 'ecab-taxi-booking-manager' ); ?></p>
					
					<section class="bg-light">
						<h6><?php esc_html_e( 'On/Off Extra Service Settings', 'ecab-taxi-booking-manager' ); ?></h6>
						<span><?php esc_html_e( 'On/Off Extra Service Settings', 'ecab-taxi-booking-manager' ); ?></span>
					</section>
					<section>
                        <label class="label">
							<div>
								<h6><?php esc_html_e( 'On/Off Extra Service Settings', 'ecab-taxi-booking-manager' ); ?></h6>
								<span class="desc"><?php MPTBM_Settings::info_text( 'display_mptbm_extra_services' ); ?></span>
							</div>
							<?php MP_Custom_Layout::switch_button( 'display_mptbm_extra_services', $checked ); ?>
						</label>
                    </section>
					<div data-collapse="#display_mptbm_extra_services" class="mp_settings_area <?php echo $active; ?>">
						<section>
							<label class="label">
								<div>
									<h6><?php esc_html_e( 'Select extra option :', 'ecab-taxi-booking-manager' ); ?></h6>
									<span class="desc"><?php MPTBM_Settings::info_text( 'mptbm_extra_services_id' ); ?></span>
								</div>
								<select class="formControl" name="mptbm_extra_services_id">
									<option value="" selected><?php esc_html_e( 'Select extra option', 'ecab-taxi-booking-manager' ); ?></option>
									<option value="<?php echo esc_attr( $post_id ); ?>" <?php echo esc_attr( $service_id == $post_id ? 'selected' : '' ); ?>><?php esc_html_e( 'Custom', 'ecab-taxi-booking-manager' ); ?></option>
									<?php if ( sizeof( $all_ex_services_id ) > 0 ) { ?>
										<?php foreach ( $all_ex_services_id as $ex_services_id ) { ?>
											<option value="<?php echo esc_attr( $ex_services_id ); ?>" <?php echo esc_attr( $service_id == $ex_services_id ? 'selected' : '' ); ?>><?php echo esc_html(get_the_title( $ex_services_id )); ?></option>
										<?php } ?>
									<?php } ?>
								</select>
							</label>
						</section>
						<div class="mptbm_extra_service_area ">
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
					<section>
						<div>
							<table class="mb-1">
								<thead>
								<tr>
									<th><span><?php esc_html_e( 'Icon', 'ecab-taxi-booking-manager' ); ?></span></th>
									<th><span><?php esc_html_e( 'Name', 'ecab-taxi-booking-manager' ); ?></span></th>
									<th><span><?php esc_html_e( 'Description', 'ecab-taxi-booking-manager' ); ?></span></th>
									<th><span><?php esc_html_e( 'Price', 'ecab-taxi-booking-manager' ); ?></span></th>
									<th><span><?php esc_html_e( 'Qty Box Type', 'ecab-taxi-booking-manager' ); ?></span></th>
									<th><span><?php esc_html_e( 'Action', 'ecab-taxi-booking-manager' ); ?></span></th>
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
							<?php
								if ( $service_id == $post_id ) {
									MP_Custom_Layout::add_new_button( esc_html__( 'Add Extra New Service', 'ecab-taxi-booking-manager' ) );
									do_action( 'add_mp_hidden_table', 'mptbm_extra_service_item' );
								}?>
						</div>
					</section>
				<?php
				}
			}
			public function save_ex_service( $post_id ) {
				if (!isset($_POST['mptbm_transportation_type_nonce']) || !wp_verify_nonce(sanitize_text_field( wp_unslash ($_POST['mptbm_transportation_type_nonce'])), 'mptbm_transportation_type_nonce') && defined('DOING_AUTOSAVE') && DOING_AUTOSAVE && !current_user_can('edit_post', $post_id)) {
					return;
				}
				if ( get_post_type( $post_id ) == MPTBM_Function::get_cpt() ) {
					$display = isset($_POST['display_mptbm_extra_services']) && sanitize_text_field($_POST['display_mptbm_extra_services'])? 'on' : 'off';
					update_post_meta( $post_id, 'display_mptbm_extra_services', $display );
					$ex_id = isset($_POST['mptbm_extra_services_id']) ? sanitize_text_field($_POST['mptbm_extra_services_id']) : $post_id;
					update_post_meta( $post_id, 'mptbm_extra_services_id', $ex_id );
					if ( $ex_id == $post_id ) {
						$extra_service_data = $this->ex_service_data( $post_id );
						update_post_meta( $post_id, 'mptbm_extra_service_infos', $extra_service_data );
					}
				}
			}
			public function ex_service_data( $post_id ) {
				$new_extra_service         = array();
				$extra_icon                =  isset($_POST['service_icon']) ? array_map('sanitize_text_field',$_POST['service_icon']) : [];
				$extra_names               =  isset($_POST['service_name']) ? array_map('sanitize_text_field',$_POST['service_name']) : [];
				$extra_price               =  isset($_POST['service_price']) ? array_map('sanitize_text_field',$_POST['service_price']) : [];
				$extra_qty_type            =  isset($_POST['service_qty_type']) ? array_map('sanitize_text_field',$_POST['service_qty_type']) : [];
				$extra_service_description =  isset($_POST['extra_service_description']) ? array_map('sanitize_textarea_field',$_POST['extra_service_description']) : [];
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
				$post_id    = isset($_REQUEST['post_id']) ?absint($_REQUEST['post_id']): '';
				$service_id = isset($_REQUEST['ex_id']) ?absint($_REQUEST['ex_id']): '';
				$this->ex_service_table( $service_id, $post_id );
				die();
			}
		}
		new MPTBM_Extra_Service();
	}