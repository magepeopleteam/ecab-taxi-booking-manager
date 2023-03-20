<?php
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'MPTBM_Extra_Service' ) ) {
		class MPTBM_Extra_Service {
			public function __construct() {
				add_action( 'add_mptbm_settings_tab_content', [ $this, 'extra_service_settings' ], 10, 1 );
				add_action( 'mptbm_extra_service_item', array( $this, 'extra_service_item' ));
				add_action( 'mptbm_settings_save', [ $this, 'save_extra_service' ], 10, 1 );
			}
			public function extra_service_settings( $post_id ) {
				$extra_services = MPTBM_Function::get_post_info( $post_id, 'mptbm_extra_service_data', array() );
				?>
				<div class="tabsItem mp_settings_area" data-tabs="#mptbm_settings_extra_service">
					<h5><?php esc_html_e( 'Extra Service Settings', 'mptbm_plugin' ); ?></h5>
					<div class="divider"></div>
					<div class="ovAuto mt_xs">
						<table>
							<thead>
                            <style>
                                .service-hader-text
                                {
                                    font-weight: 700;
                                }
                            </style>
							<tr>
								<td><span class="service-hader-text"><?php esc_html_e( 'Service Icon', 'mptbm_plugin' ); ?></span></td>
								<td><span class="service-hader-text"><?php esc_html_e( 'Service Name', 'mptbm_plugin' ); ?></span></td>
								<td><span class="service-hader-text"><?php esc_html_e( 'Short description', 'mptbm_plugin' ); ?></span></td>
								<td><span class="service-hader-text"><?php esc_html_e( 'Service Price', 'mptbm_plugin' ); ?></span></td>
								<td><span class="service-hader-text"><?php esc_html_e( 'Qty Box Type', 'mptbm_plugin' ); ?></span></td>
                                <td><span class="service-hader-text"><?php esc_html_e( 'Action', 'mptbm_plugin' ); ?></span></td>
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
					<?php MPTBM_Layout::add_new_button( esc_html__( 'Add Extra New Service', 'mptbm_plugin' ) ); ?>
					<?php do_action( 'mptbm_hidden_item_table', 'mptbm_extra_service_item' ); ?>
				</div>
				<?php
			}
			public function extra_service_item( $field = array() ) {
				$field         = $field ?: array();
                $images            = $field && is_array( $field ) ? $field : array();
				$service_icon  = array_key_exists( 'service_icon', $field ) ? $field['service_icon'] : '';
				$service_name  = array_key_exists( 'service_name', $field ) ? $field['service_name'] : '';
				$service_price = array_key_exists( 'service_price', $field ) ? $field['service_price'] : '';
				$input_type    = array_key_exists( 'service_qty_type', $field ) ? $field['service_qty_type'] : 'inputbox';
				$description = array_key_exists( 'extra_service_description', $field ) ? $field['extra_service_description'] : '';

                $icon = $image = "";

                if ( $service_icon )
                {
                    if ( preg_match( '/\s/', $service_icon ) )
                    {
                        $icon = $service_icon;
                    }
                    else
                    {
                        $image = $service_icon;
                    }
                }

				?>
				<tr class="mp_remove_area">
					<td><?php //do_action( 'mp_input_add_icon', 'service_icon[]', $service_icon ); ?>
                        <?php do_action( 'mp_add_icon_image', $image_name='service_icon[]', $icon, $image ); ?>
                    </td>
					<td>
						<label>
							<input type="text" class="formControl mp_name_validation" name="service_name[]" placeholder="<?php esc_html_e( 'EX: Driver', 'mptbm_plugin' ); ?>" value="<?php echo esc_attr( $service_name ); ?>"/>
						</label>
					</td>
					<td>
						<label>
							<input type="text" class="formControl" name="extra_service_description[]" placeholder="<?php esc_html_e( 'EX: Description', 'mptbm_plugin' ); ?>" value="<?php echo esc_attr( $description ); ?>"/>
						</label>
					</td>
					<td>
						<label>
							<input type="number" pattern="[0-9]*" step="0.01" class="formControl mp_price_validation" name="service_price[]" placeholder="<?php esc_html_e( 'EX: 10', 'mptbm_plugin' ); ?>" value="<?php echo esc_attr( $service_price ); ?>"/>
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
					<td><?php MPTBM_Layout::move_remove_button(); ?></td>
				</tr>
				<?php
			}
			public function save_extra_service( $post_id ) {
				if ( get_post_type( $post_id ) == MPTBM_Function::get_cpt_name() ) {
					$new_extra_service = array();
					$extra_icon        = MPTBM_Function::get_submit_info( 'service_icon', array() );
					$extra_names       = MPTBM_Function::get_submit_info( 'service_name', array() );
					$extra_price       = MPTBM_Function::get_submit_info( 'service_price', array() );
					$extra_qty_type    = MPTBM_Function::get_submit_info( 'service_qty_type', array() );
					$extra_service_description    = MPTBM_Function::get_submit_info( 'extra_service_description', array() );
					$extra_count       = count( $extra_names );
					for ( $i = 0; $i < $extra_count; $i ++ ) {
						if ( $extra_names[ $i ] && $extra_price[ $i ] >= 0) {
							$new_extra_service[ $i ]['service_icon']     = $extra_icon[ $i ] ?? '';
							$new_extra_service[ $i ]['service_name']     = $extra_names[ $i ];
							$new_extra_service[ $i ]['service_price']    = $extra_price[ $i ];
							$new_extra_service[ $i ]['service_qty_type'] = $extra_qty_type[ $i ] ?? 'inputbox';
							$new_extra_service[ $i ]['extra_service_description'] = $extra_service_description[ $i ] ?? '';
						}
					}
					$extra_service_data_arr = apply_filters( 'filter_mptbm_extra_service_data', $new_extra_service );
					update_post_meta( $post_id, 'mptbm_extra_service_data', $extra_service_data_arr );
				}
			}
		}
		new MPTBM_Extra_Service();
	}