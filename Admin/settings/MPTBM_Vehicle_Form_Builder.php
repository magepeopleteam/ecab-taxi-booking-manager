<?php
/**
 * Vehicle Custom Form Builder - Free Version
 * Allows up to 2 custom fields per vehicle (post meta).
 * PRO version unlocks unlimited fields + import/copy between vehicles.
 *
 * Data stored as: post_meta key = 'mptbm_vehicle_form_fields'
 * Value: serialized array of field definitions
 *
 * @package ecab-taxi-booking-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'MPTBM_Vehicle_Form_Builder' ) ) {
	class MPTBM_Vehicle_Form_Builder {

		const META_KEY       = 'mptbm_vehicle_form_fields';
		const FREE_FIELD_MAX = 2;

		public function __construct() {
			add_action( 'add_mptbm_settings_tab_after_ex_service', array( $this, 'register_tab_item' ) );
			add_action( 'add_mptbm_settings_tab_content', array( $this, 'render_tab_content' ) );
			add_action( 'save_post', array( $this, 'save_fields' ), 20, 1 );

			// AJAX: add / remove / reorder fields (free)
			add_action( 'wp_ajax_mptbm_vfb_save', array( $this, 'ajax_save' ) );
		}

		/* ------------------------------------------------------------------ */
		/* Helpers                                                              */
		/* ------------------------------------------------------------------ */

		public static function get_fields( $post_id ) {
			$raw = get_post_meta( $post_id, self::META_KEY, true );
			return is_array( $raw ) ? $raw : array();
		}

		public static function is_pro() {
			return class_exists( 'MPTBM_Plugin_Pro' ) || class_exists( 'MPTBM_Vehicle_Form_Builder_Pro' );
		}

		public static function get_field_types() {
			return array(
				'text'           => __( 'Text', 'ecab-taxi-booking-manager' ),
				'textarea'       => __( 'Textarea', 'ecab-taxi-booking-manager' ),
				'number'         => __( 'Number', 'ecab-taxi-booking-manager' ),
				'email'          => __( 'Email', 'ecab-taxi-booking-manager' ),
				'tel'            => __( 'Phone', 'ecab-taxi-booking-manager' ),
				'date'           => __( 'Date', 'ecab-taxi-booking-manager' ),
				'select'         => __( 'Dropdown (Select)', 'ecab-taxi-booking-manager' ),
				'radio'          => __( 'Radio Buttons', 'ecab-taxi-booking-manager' ),
				'checkbox'       => __( 'Checkboxes', 'ecab-taxi-booking-manager' ),
				'file'           => __( 'File Upload', 'ecab-taxi-booking-manager' ),
			);
		}

		/* ------------------------------------------------------------------ */
		/* Admin Tab                                                            */
		/* ------------------------------------------------------------------ */

		public function register_tab_item() {
			?>
			<li data-tabs-target="#mptbm_vehicle_form_builder">
				<span class="pe-1 fas fa-wpforms"></span>
				<?php esc_html_e( 'Custom Form', 'ecab-taxi-booking-manager' ); ?>
				<?php if ( ! self::is_pro() ) : ?>
					<span class="mptbm-pro-badge" title="<?php esc_attr_e( 'Free: max 2 fields. Upgrade to PRO for unlimited fields + copy between vehicles.', 'ecab-taxi-booking-manager' ); ?>">FREE</span>
				<?php endif; ?>
			</li>
			<?php
		}

		public function render_tab_content( $post_id ) {
			$fields    = self::get_fields( $post_id );
			$is_pro    = self::is_pro();
			$max       = $is_pro ? 9999 : self::FREE_FIELD_MAX;
			$types     = self::get_field_types();
			$nonce     = wp_create_nonce( 'mptbm_vfb_nonce_' . $post_id );
			?>
			<div id="mptbm_vehicle_form_builder" class="tabContent">
				<div class="mptbm-vfb-wrap">

					<?php if ( ! $is_pro ) : ?>
					<div class="mptbm-vfb-notice mptbm-vfb-notice--info">
						<span class="fas fa-info-circle"></span>
						<?php printf(
							/* translators: %d: max free fields */
							esc_html__( 'Free version: up to %d custom fields per vehicle. Activate PRO for unlimited fields, copy/import between vehicles and more.', 'ecab-taxi-booking-manager' ),
							self::FREE_FIELD_MAX
						); ?>
					</div>
					<?php endif; ?>

					<div class="mptbm-vfb-header">
						<h3><span class="fas fa-wpforms"></span> <?php esc_html_e( 'Custom Form Fields', 'ecab-taxi-booking-manager' ); ?></h3>
						<div class="mptbm-vfb-header-actions">
							<?php if ( $is_pro ) : ?>
							<button type="button" class="button mptbm-vfb-import-btn" data-post-id="<?php echo esc_attr( $post_id ); ?>">
								<span class="fas fa-file-import"></span> <?php esc_html_e( 'Copy from Vehicle', 'ecab-taxi-booking-manager' ); ?>
							</button>
							<?php endif; ?>
							<button type="button" class="button button-primary mptbm-vfb-add-btn"
								data-post-id="<?php echo esc_attr( $post_id ); ?>"
								data-max="<?php echo esc_attr( $max ); ?>"
								data-nonce="<?php echo esc_attr( $nonce ); ?>">
								<span class="fas fa-plus"></span> <?php esc_html_e( 'Add Field', 'ecab-taxi-booking-manager' ); ?>
							</button>
						</div>
					</div>

					<div class="mptbm-vfb-fields-list" id="mptbm_vfb_fields_<?php echo esc_attr( $post_id ); ?>"
						data-post-id="<?php echo esc_attr( $post_id ); ?>"
						data-nonce="<?php echo esc_attr( $nonce ); ?>"
						data-max="<?php echo esc_attr( $max ); ?>">

						<?php if ( empty( $fields ) ) : ?>
						<div class="mptbm-vfb-empty" id="mptbm_vfb_empty_<?php echo esc_attr( $post_id ); ?>">
							<span class="fas fa-layer-group"></span>
							<p><?php esc_html_e( 'No fields yet. Click "Add Field" to create your first custom field.', 'ecab-taxi-booking-manager' ); ?></p>
						</div>
						<?php else : ?>
						<div class="mptbm-vfb-empty" id="mptbm_vfb_empty_<?php echo esc_attr( $post_id ); ?>" style="display:none;"></div>
						<?php endif; ?>

						<?php foreach ( $fields as $index => $field ) :
							$this->render_field_row( $post_id, $index, $field, $types );
						endforeach; ?>
					</div>

					<input type="hidden" name="mptbm_vfb_nonce" value="<?php echo esc_attr( $nonce ); ?>" />
					<input type="hidden" name="mptbm_vfb_post_id" value="<?php echo esc_attr( $post_id ); ?>" />

					<?php if ( $is_pro ) : ?>
					<!-- Copy/Import Modal (PRO) -->
					<div id="mptbm_vfb_import_modal" class="mptbm-vfb-modal" style="display:none;">
						<div class="mptbm-vfb-modal-inner">
							<div class="mptbm-vfb-modal-header">
								<h3><?php esc_html_e( 'Copy Form from Another Vehicle', 'ecab-taxi-booking-manager' ); ?></h3>
								<button type="button" class="mptbm-vfb-modal-close"><span class="fas fa-times"></span></button>
							</div>
							<div class="mptbm-vfb-modal-body">
								<p><?php esc_html_e( 'Select a vehicle to copy its custom form fields to this vehicle. Existing fields will be replaced.', 'ecab-taxi-booking-manager' ); ?></p>
								<select id="mptbm_vfb_source_vehicle">
									<option value=""><?php esc_html_e( '— Select Vehicle —', 'ecab-taxi-booking-manager' ); ?></option>
									<?php
									$vehicles = get_posts( array(
										'post_type'      => MPTBM_Function::get_cpt(),
										'posts_per_page' => -1,
										'post_status'    => 'publish',
										'exclude'        => array( $post_id ),
										'orderby'        => 'title',
										'order'          => 'ASC',
									) );
									foreach ( $vehicles as $v ) {
										$fcount = count( self::get_fields( $v->ID ) );
										printf(
											'<option value="%d">%s (%d %s)</option>',
											esc_attr( $v->ID ),
											esc_html( $v->post_title ),
											$fcount,
											esc_html( _n( 'field', 'fields', $fcount, 'ecab-taxi-booking-manager' ) )
										);
									}
									?>
								</select>
							</div>
							<div class="mptbm-vfb-modal-footer">
								<button type="button" class="button button-primary" id="mptbm_vfb_do_import"
									data-target-id="<?php echo esc_attr( $post_id ); ?>"
									data-nonce="<?php echo esc_attr( wp_create_nonce( 'mptbm_vfb_import_' . $post_id ) ); ?>">
									<?php esc_html_e( 'Copy Fields', 'ecab-taxi-booking-manager' ); ?>
								</button>
								<button type="button" class="button mptbm-vfb-modal-close"><?php esc_html_e( 'Cancel', 'ecab-taxi-booking-manager' ); ?></button>
							</div>
						</div>
					</div>
					<?php endif; ?>

				</div><!-- /.mptbm-vfb-wrap -->
			</div><!-- /#mptbm_vehicle_form_builder -->

			<!-- Field row template (hidden, cloned by JS) -->
			<script type="text/html" id="mptbm_vfb_field_template">
				<?php $this->render_field_row( '__POST_ID__', '__INDEX__', array(), $types, true ); ?>
			</script>

			<?php $this->enqueue_scripts( $post_id, $is_pro ); ?>
			<?php
		}

		/* ------------------------------------------------------------------ */
		/* Render a single field row                                           */
		/* ------------------------------------------------------------------ */

		private function render_field_row( $post_id, $index, $field, $types, $is_template = false ) {
		$uid             = $is_template ? '__UID__' : ( isset( $field['uid'] ) ? $field['uid'] : 'f' . $index );
		$label           = $is_template ? '' : ( isset( $field['label'] ) ? $field['label'] : '' );
		$type            = $is_template ? 'text' : ( isset( $field['type'] ) ? $field['type'] : 'text' );
		$placeholder     = $is_template ? '' : ( isset( $field['placeholder'] ) ? $field['placeholder'] : '' );
		$required        = $is_template ? false : ! empty( $field['required'] );
		// Legacy: if 'show' is set (old data), default both checkboxes to its value
		$legacy_show     = $is_template ? true : ( isset( $field['show'] ) ? (bool) $field['show'] : true );
		$show_on_checkout = $is_template ? true  : ( isset( $field['show_on_checkout'] ) ? (bool) $field['show_on_checkout'] : $legacy_show );
		$show_on_select   = $is_template ? true  : ( isset( $field['show_on_select'] )   ? (bool) $field['show_on_select']   : true );
		$options_raw     = $is_template ? '' : ( isset( $field['options'] ) ? ( is_array( $field['options'] ) ? implode( "\n", $field['options'] ) : $field['options'] ) : '' );
		$desc            = $is_template ? '' : ( isset( $field['description'] ) ? $field['description'] : '' );
		$name_prefix     = "mptbm_vfb_fields[{$index}]";
			?>
			<div class="mptbm-vfb-field-row" data-uid="<?php echo esc_attr( $uid ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
				<div class="mptbm-vfb-field-handle" title="<?php esc_attr_e( 'Drag to reorder', 'ecab-taxi-booking-manager' ); ?>">
					<span class="fas fa-grip-vertical"></span>
				</div>

				<div class="mptbm-vfb-field-body">
					<div class="mptbm-vfb-field-header">
						<span class="mptbm-vfb-field-label-preview">
							<?php echo $label ? esc_html( $label ) : esc_html__( '(no label)', 'ecab-taxi-booking-manager' ); ?>
						</span>
						<span class="mptbm-vfb-field-type-badge"><?php echo isset( $types[ $type ] ) ? esc_html( $types[ $type ] ) : esc_html( $type ); ?></span>
						<div class="mptbm-vfb-field-actions">
							<button type="button" class="mptbm-vfb-toggle-field" title="<?php esc_attr_e( 'Toggle expand', 'ecab-taxi-booking-manager' ); ?>">
								<span class="fas fa-chevron-down"></span>
							</button>
							<button type="button" class="mptbm-vfb-remove-field" title="<?php esc_attr_e( 'Remove field', 'ecab-taxi-booking-manager' ); ?>">
								<span class="fas fa-trash-alt"></span>
							</button>
						</div>
					</div>

					<div class="mptbm-vfb-field-content" style="display:none;">
						<input type="hidden" name="<?php echo esc_attr( $name_prefix ); ?>[uid]" class="mptbm-vfb-uid" value="<?php echo esc_attr( $uid ); ?>" />

						<div class="mptbm-vfb-row">
							<div class="mptbm-vfb-col">
								<label><?php esc_html_e( 'Field Label', 'ecab-taxi-booking-manager' ); ?> <span class="required">*</span></label>
								<input type="text" name="<?php echo esc_attr( $name_prefix ); ?>[label]"
									class="widefat mptbm-vfb-label-input" value="<?php echo esc_attr( $label ); ?>"
									placeholder="<?php esc_attr_e( 'e.g. Flight Number', 'ecab-taxi-booking-manager' ); ?>" required />
							</div>
							<div class="mptbm-vfb-col">
								<label><?php esc_html_e( 'Field Type', 'ecab-taxi-booking-manager' ); ?></label>
								<select name="<?php echo esc_attr( $name_prefix ); ?>[type]" class="widefat mptbm-vfb-type-select">
									<?php foreach ( $types as $tval => $tlabel ) : ?>
									<option value="<?php echo esc_attr( $tval ); ?>" <?php selected( $type, $tval ); ?>><?php echo esc_html( $tlabel ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<div class="mptbm-vfb-row">
							<div class="mptbm-vfb-col">
								<label><?php esc_html_e( 'Placeholder', 'ecab-taxi-booking-manager' ); ?></label>
								<input type="text" name="<?php echo esc_attr( $name_prefix ); ?>[placeholder]"
									class="widefat" value="<?php echo esc_attr( $placeholder ); ?>"
									placeholder="<?php esc_attr_e( 'Optional placeholder text', 'ecab-taxi-booking-manager' ); ?>" />
							</div>
							<div class="mptbm-vfb-col">
								<label><?php esc_html_e( 'Description / Help Text', 'ecab-taxi-booking-manager' ); ?></label>
								<input type="text" name="<?php echo esc_attr( $name_prefix ); ?>[description]"
									class="widefat" value="<?php echo esc_attr( $desc ); ?>"
									placeholder="<?php esc_attr_e( 'Short description shown below field', 'ecab-taxi-booking-manager' ); ?>" />
							</div>
						</div>

						<div class="mptbm-vfb-options-row" style="<?php echo in_array( $type, array( 'select', 'radio', 'checkbox' ) ) ? '' : 'display:none;'; ?>">
							<label><?php esc_html_e( 'Options (one per line)', 'ecab-taxi-booking-manager' ); ?></label>
							<textarea name="<?php echo esc_attr( $name_prefix ); ?>[options]" class="widefat mptbm-vfb-options-textarea" rows="4"
								placeholder="<?php esc_attr_e( "Option 1\nOption 2\nOption 3", 'ecab-taxi-booking-manager' ); ?>"><?php echo esc_textarea( $options_raw ); ?></textarea>
							<span class="description"><?php esc_html_e( 'Each line becomes a selectable option.', 'ecab-taxi-booking-manager' ); ?></span>
						</div>

					<div class="mptbm-vfb-row mptbm-vfb-row--checkboxes">
						<label class="mptbm-vfb-check-label">
							<input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[required]" value="1" <?php checked( $required ); ?> />
							<?php esc_html_e( 'Required', 'ecab-taxi-booking-manager' ); ?>
						</label>
						<label class="mptbm-vfb-check-label">
							<input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[show_on_checkout]" value="1" <?php checked( $show_on_checkout ); ?> />
							<?php esc_html_e( 'Show on Checkout', 'ecab-taxi-booking-manager' ); ?>
						</label>
						<label class="mptbm-vfb-check-label">
							<input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[show_on_select]" value="1" <?php checked( $show_on_select ); ?> />
							<?php esc_html_e( 'Show on Vehicle Select', 'ecab-taxi-booking-manager' ); ?>
						</label>
					</div>
					</div><!-- /.mptbm-vfb-field-content -->
				</div><!-- /.mptbm-vfb-field-body -->
			</div><!-- /.mptbm-vfb-field-row -->
			<?php
		}

		/* ------------------------------------------------------------------ */
		/* Save (on post save)                                                 */
		/* ------------------------------------------------------------------ */

		public function save_fields( $post_id ) {
			if ( ! isset( $_POST['mptbm_vfb_nonce'] ) ) {
				return;
			}
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mptbm_vfb_nonce'] ) ), 'mptbm_vfb_nonce_' . $post_id ) ) {
				return;
			}
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
			if ( get_post_type( $post_id ) !== MPTBM_Function::get_cpt() ) {
				return;
			}

			$raw    = isset( $_POST['mptbm_vfb_fields'] ) ? (array) $_POST['mptbm_vfb_fields'] : array();
			$is_pro = self::is_pro();
			$max    = $is_pro ? 9999 : self::FREE_FIELD_MAX;
			$fields = array();

			foreach ( array_values( $raw ) as $i => $f ) {
				if ( $i >= $max ) {
					break;
				}
				$label = isset( $f['label'] ) ? sanitize_text_field( $f['label'] ) : '';
				if ( empty( $label ) ) {
					continue;
				}

				// Sanitize options (textarea → array of non-empty lines)
				$options = array();
				if ( isset( $f['options'] ) && ! empty( $f['options'] ) ) {
					$lines = explode( "\n", sanitize_textarea_field( $f['options'] ) );
					foreach ( $lines as $line ) {
						$line = trim( $line );
						if ( $line !== '' ) {
							$options[] = $line;
						}
					}
				}

				$uid = isset( $f['uid'] ) && ! empty( $f['uid'] ) ? sanitize_key( $f['uid'] ) : 'field_' . $i;

			$fields[] = array(
				'uid'              => $uid,
				'label'            => $label,
				'type'             => isset( $f['type'] ) ? sanitize_key( $f['type'] ) : 'text',
				'placeholder'      => isset( $f['placeholder'] ) ? sanitize_text_field( $f['placeholder'] ) : '',
				'description'      => isset( $f['description'] ) ? sanitize_text_field( $f['description'] ) : '',
				'required'         => ! empty( $f['required'] ),
				'show'             => ! empty( $f['show_on_checkout'] ), // legacy compat
				'show_on_checkout' => ! empty( $f['show_on_checkout'] ),
				'show_on_select'   => ! empty( $f['show_on_select'] ),
				'options'          => $options,
				'order'            => $i,
			);
			}

			update_post_meta( $post_id, self::META_KEY, $fields );
		}

		/* ------------------------------------------------------------------ */
		/* AJAX save (inline, used by import feature)                          */
		/* ------------------------------------------------------------------ */

		public function ajax_save() {
			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			if ( ! $post_id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mptbm_vfb_nonce_' . $post_id ) ) {
				wp_send_json_error( 'Invalid nonce' );
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( 'Insufficient permissions' );
			}

			$fields = isset( $_POST['fields'] ) ? (array) $_POST['fields'] : array();
			$clean  = array();
			foreach ( $fields as $i => $f ) {
				$label = isset( $f['label'] ) ? sanitize_text_field( $f['label'] ) : '';
				if ( empty( $label ) ) {
					continue;
				}
				$options = array();
				if ( isset( $f['options'] ) && is_array( $f['options'] ) ) {
					$options = array_filter( array_map( 'sanitize_text_field', $f['options'] ) );
				}
			$clean[] = array(
				'uid'              => sanitize_key( $f['uid'] ?? 'field_' . $i ),
				'label'            => $label,
				'type'             => sanitize_key( $f['type'] ?? 'text' ),
				'placeholder'      => sanitize_text_field( $f['placeholder'] ?? '' ),
				'description'      => sanitize_text_field( $f['description'] ?? '' ),
				'required'         => ! empty( $f['required'] ),
				'show'             => ! empty( $f['show_on_checkout'] ),
				'show_on_checkout' => ! empty( $f['show_on_checkout'] ),
				'show_on_select'   => ! empty( $f['show_on_select'] ),
				'options'          => array_values( $options ),
				'order'            => (int) $i,
			);
			}
			update_post_meta( $post_id, self::META_KEY, $clean );
			wp_send_json_success( array( 'count' => count( $clean ) ) );
		}

		/* ------------------------------------------------------------------ */
		/* Enqueue inline admin CSS + JS                                       */
		/* ------------------------------------------------------------------ */

		private function enqueue_scripts( $post_id, $is_pro ) {
			?>
		<style>
		/* ============================================================
		   Vehicle Form Builder – Admin Styles
		   ============================================================ */

		/* Wrapper */
		.mptbm-vfb-wrap {
			padding: 16px 0 8px;
		}

		/* Info notice */
		.mptbm-vfb-notice {
			padding: 10px 14px;
			margin-bottom: 18px;
			border-radius: 4px;
			font-size: 13px;
			line-height: 1.5;
		}
		.mptbm-vfb-notice--info {
			background: #e8f4fd;
			border-left: 4px solid #2196f3;
			color: #1a5276;
		}
		.mptbm-pro-badge {
			display: inline-block;
			padding: 2px 6px;
			font-size: 10px;
			font-weight: 700;
			background: #f39c12;
			color: #fff;
			border-radius: 3px;
			margin-left: 6px;
			vertical-align: middle;
		}

		/* Header row */
		.mptbm-vfb-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-bottom: 16px;
			flex-wrap: wrap;
			gap: 8px;
		}
		.mptbm-vfb-header h3 {
			margin: 0;
			font-size: 15px;
			font-weight: 600;
		}
		.mptbm-vfb-header-actions {
			display: flex;
			gap: 8px;
			flex-wrap: wrap;
		}

		/* Empty state */
		.mptbm-vfb-empty {
			text-align: center;
			padding: 40px 20px;
			color: #aaa;
			border: 2px dashed #ddd;
			border-radius: 6px;
			margin-bottom: 12px;
		}
		.mptbm-vfb-empty .fas {
			font-size: 30px;
			display: block;
			margin-bottom: 10px;
		}

		/* Fields list container */
		.mptbm-vfb-fields-list {
			margin-top: 4px;
		}

		/* ---- Individual field row ---- */
		.mptbm-vfb-field-row {
			background: #fff;
			border: 1px solid #dcdcdc;
			border-radius: 6px;
			margin-bottom: 10px;
			display: flex;
			align-items: flex-start;
			box-shadow: 0 1px 3px rgba(0,0,0,.07);
			transition: box-shadow .15s, border-color .15s;
			overflow: hidden;
		}
		.mptbm-vfb-field-row:hover {
			border-color: #b0b0b0;
			box-shadow: 0 2px 8px rgba(0,0,0,.11);
		}
		.mptbm-vfb-field-row.ui-sortable-helper {
			box-shadow: 0 6px 20px rgba(0,0,0,.18);
			opacity: .92;
		}

		/* Drag handle */
		.mptbm-vfb-field-handle {
			padding: 16px 10px;
			color: #c0c0c0;
			cursor: grab;
			flex-shrink: 0;
			align-self: stretch;
			display: flex;
			align-items: flex-start;
			padding-top: 17px;
		}
		.mptbm-vfb-field-handle:active { cursor: grabbing; }
		.mptbm-vfb-field-handle:hover { color: #888; }

		/* Field body */
		.mptbm-vfb-field-body {
			flex: 1;
			min-width: 0;
			padding: 12px 14px 14px 6px;
		}

		/* Collapsed header */
		.mptbm-vfb-field-header {
			display: flex;
			align-items: center;
			gap: 10px;
			cursor: pointer;
			user-select: none;
		}
		.mptbm-vfb-field-label-preview {
			font-weight: 600;
			font-size: 13px;
			flex: 1;
			min-width: 0;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
			color: #23282d;
		}
		.mptbm-vfb-field-type-badge {
			font-size: 11px;
			background: #eef2ff;
			color: #4f46e5;
			padding: 2px 9px;
			border-radius: 20px;
			font-weight: 500;
			flex-shrink: 0;
		}
		.mptbm-vfb-field-actions {
			display: flex;
			gap: 4px;
			flex-shrink: 0;
		}
		.mptbm-vfb-toggle-field,
		.mptbm-vfb-remove-field {
			background: none;
			border: none;
			cursor: pointer;
			font-size: 13px;
			padding: 5px 7px;
			border-radius: 4px;
			line-height: 1;
			transition: background .12s;
		}
		.mptbm-vfb-toggle-field { color: #555; }
		.mptbm-vfb-toggle-field:hover { background: #f0f0f0; color: #222; }
		.mptbm-vfb-remove-field { color: #c0392b; }
		.mptbm-vfb-remove-field:hover { background: #fef2f2; }

	/* Expanded content area */
	.mptbm-vfb-field-content {
		padding: 16px 2px 4px 0;
		margin-top: 10px;
		border-top: 1px solid #ebebeb;
	}

	/* 2-column grid row inside a field */
	.mptbm-vfb-row {
		display: flex;
		gap: 16px;
		margin-bottom: 18px;
		flex-wrap: wrap;
	}
		.mptbm-vfb-col {
			flex: 1 1 200px;
			min-width: 0;
		}

	/* Labels */
	.mptbm-vfb-col label,
	.mptbm-vfb-field-content > label {
		display: block;
		font-weight: 600;
		font-size: 12px;
		color: #3c434a;
		margin-bottom: 8px;
		margin-top: 2px;
		line-height: 1.4;
	}
		.mptbm-vfb-col label .required,
		.mptbm-vfb-field-content label .required {
			color: #d63638;
			margin-left: 2px;
		}

		/* Inputs inside expanded content */
		.mptbm-vfb-field-content input[type="text"],
		.mptbm-vfb-field-content input[type="number"],
		.mptbm-vfb-field-content input[type="email"],
		.mptbm-vfb-field-content select,
		.mptbm-vfb-field-content textarea {
			width: 100%;
			box-sizing: border-box;
			margin: 0;
			padding: 7px 10px;
			border: 1px solid #8c8f94 !important;
			border-radius: 4px;
			background: #fff;
			font-size: 13px;
			line-height: 1.4;
			color: #2c3338;
			box-shadow: none !important;
			transition: border-color .12s, box-shadow .12s;
		}
		.mptbm-vfb-field-content input[type="text"]:focus,
		.mptbm-vfb-field-content input[type="number"]:focus,
		.mptbm-vfb-field-content input[type="email"]:focus,
		.mptbm-vfb-field-content select:focus,
		.mptbm-vfb-field-content textarea:focus {
			border-color: #2271b1 !important;
			box-shadow: 0 0 0 1px #2271b1 !important;
			outline: none;
		}
		/* Suppress browser/WP red border on :invalid inputs before user interaction */
		.mptbm-vfb-field-content input:invalid,
		.mptbm-vfb-field-content select:invalid,
		.mptbm-vfb-field-content textarea:invalid {
			border-color: #8c8f94 !important;
			box-shadow: none !important;
			outline: none;
		}
		.mptbm-vfb-field-content select {
			height: 36px;
		}
		/* Remove red outline from field row itself */
		.mptbm-vfb-field-row:focus-within {
			border-color: #dcdcdc;
			box-shadow: 0 1px 3px rgba(0,0,0,.07);
		}

		/* Options textarea row */
		.mptbm-vfb-options-row {
			margin-bottom: 14px;
		}
		.mptbm-vfb-options-textarea {
			resize: vertical;
		}

		/* Checkboxes row */
		.mptbm-vfb-row--checkboxes {
			gap: 24px;
			align-items: center;
			margin-bottom: 6px;
		}
		.mptbm-vfb-check-label {
			display: flex;
			align-items: center;
			gap: 7px;
			font-size: 13px;
			cursor: pointer;
			font-weight: 500;
			color: #2c3338;
		}
		.mptbm-vfb-check-label input[type="checkbox"] {
			margin: 0;
			width: 15px;
			height: 15px;
			flex-shrink: 0;
		}

		/* ---- Modal (PRO import) ---- */
		.mptbm-vfb-modal {
			position: fixed;
			top: 0; left: 0; right: 0; bottom: 0;
			background: rgba(0,0,0,.55);
			z-index: 99999;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.mptbm-vfb-modal-inner {
			background: #fff;
			border-radius: 8px;
			width: 480px;
			max-width: 95vw;
			box-shadow: 0 12px 40px rgba(0,0,0,.22);
			overflow: hidden;
		}
		.mptbm-vfb-modal-header {
			background: #f6f7f7;
			padding: 15px 20px;
			border-bottom: 1px solid #e5e5e5;
			display: flex;
			align-items: center;
			justify-content: space-between;
		}
		.mptbm-vfb-modal-header h3 {
			margin: 0;
			font-size: 15px;
			font-weight: 600;
		}
		.mptbm-vfb-modal-close {
			background: none;
			border: none;
			cursor: pointer;
			font-size: 18px;
			color: #999;
			padding: 4px;
			line-height: 1;
		}
		.mptbm-vfb-modal-close:hover { color: #444; }
		.mptbm-vfb-modal-body {
			padding: 20px;
		}
		.mptbm-vfb-modal-body select {
			width: 100%;
			margin-top: 10px;
			padding: 7px 10px;
			border: 1px solid #8c8f94;
			border-radius: 4px;
			font-size: 13px;
		}
		.mptbm-vfb-modal-footer {
			padding: 14px 20px;
			border-top: 1px solid #eee;
			display: flex;
			gap: 10px;
			justify-content: flex-end;
		}
		</style>

			<script>
			(function($){
				var POST_ID  = <?php echo (int) $post_id; ?>;
				var IS_PRO   = <?php echo $is_pro ? 'true' : 'false'; ?>;
				var MAX_FIELDS = <?php echo $is_pro ? '9999' : self::FREE_FIELD_MAX; ?>;
				var TYPES    = <?php echo wp_json_encode( self::get_field_types() ); ?>;
				var fieldIndex = <?php echo max( count( self::get_fields( $post_id ) ), 0 ); ?>;

				function uid() {
					return 'field_' + Date.now() + '_' + Math.random().toString(36).substr(2,5);
				}

				function countFields() {
					return $('#mptbm_vfb_fields_' + POST_ID + ' .mptbm-vfb-field-row').length;
				}

				function checkEmpty() {
					var $empty = $('#mptbm_vfb_empty_' + POST_ID);
					if (countFields() === 0) { $empty.show(); } else { $empty.hide(); }
				}

				function buildFieldRow(idx, uid) {
					var tmpl = $('#mptbm_vfb_field_template').html();
					tmpl = tmpl.replace(/__POST_ID__/g, POST_ID).replace(/__INDEX__/g, idx).replace(/__UID__/g, uid);
					return $(tmpl);
				}

				// Add field
				$(document).on('click', '.mptbm-vfb-add-btn[data-post-id="' + POST_ID + '"]', function(){
					if (countFields() >= MAX_FIELDS) {
						<?php if ( ! $is_pro ) : ?>
						alert('<?php echo esc_js( sprintf( __( 'Free version is limited to %d fields per vehicle. Please upgrade to PRO for unlimited fields.', 'ecab-taxi-booking-manager' ), self::FREE_FIELD_MAX ) ); ?>');
						<?php endif; ?>
						return;
					}
					var newUid = uid();
					var $row = buildFieldRow(fieldIndex++, newUid);
					$('#mptbm_vfb_fields_' + POST_ID).append($row);
					$row.find('.mptbm-vfb-field-content').show();
					$row.find('.mptbm-vfb-label-input').focus();
					checkEmpty();
					reindexFields();
				});

				// Toggle expand/collapse
				$(document).on('click', '.mptbm-vfb-toggle-field, .mptbm-vfb-field-label-preview, .mptbm-vfb-field-type-badge', function(e){
					if ($(this).hasClass('mptbm-vfb-remove-field')) return;
					var $content = $(this).closest('.mptbm-vfb-field-row').find('.mptbm-vfb-field-content');
					$content.slideToggle(180);
					var $icon = $(this).closest('.mptbm-vfb-field-row').find('.mptbm-vfb-toggle-field .fas');
					$icon.toggleClass('fa-chevron-down fa-chevron-up');
				});

				// Update label preview on type
				$(document).on('input', '.mptbm-vfb-label-input', function(){
					var val = $(this).val() || '<?php esc_html_e( "(no label)", "ecab-taxi-booking-manager" ); ?>';
					$(this).closest('.mptbm-vfb-field-row').find('.mptbm-vfb-field-label-preview').text(val);
				});

				// Update type badge on type change
				$(document).on('change', '.mptbm-vfb-type-select', function(){
					var tval = $(this).val();
					var tlabel = TYPES[tval] || tval;
					$(this).closest('.mptbm-vfb-field-row').find('.mptbm-vfb-field-type-badge').text(tlabel);
					// Toggle options textarea
					var $optRow = $(this).closest('.mptbm-vfb-field-row').find('.mptbm-vfb-options-row');
					if (['select','radio','checkbox'].indexOf(tval) !== -1) { $optRow.show(); } else { $optRow.hide(); }
				});

				// Remove field
				$(document).on('click', '.mptbm-vfb-remove-field', function(){
					if (!confirm('<?php esc_html_e( 'Remove this field?', 'ecab-taxi-booking-manager' ); ?>')) return;
					$(this).closest('.mptbm-vfb-field-row').remove();
					reindexFields();
					checkEmpty();
				});

				// Reindex name attributes after add/remove/sort
				function reindexFields() {
					$('#mptbm_vfb_fields_' + POST_ID + ' .mptbm-vfb-field-row').each(function(i){
						$(this).attr('data-index', i);
						$(this).find('[name]').each(function(){
							var name = $(this).attr('name');
							name = name.replace(/mptbm_vfb_fields\[\d+\]/, 'mptbm_vfb_fields[' + i + ']');
							$(this).attr('name', name);
						});
					});
				}

				// Sortable drag & drop
				if ($.fn.sortable) {
					$('#mptbm_vfb_fields_' + POST_ID).sortable({
						handle: '.mptbm-vfb-field-handle',
						axis: 'y',
						tolerance: 'pointer',
						stop: function(){ reindexFields(); }
					});
				}

				checkEmpty();

				// PRO: import/copy modal
				if (IS_PRO) {
					$(document).on('click', '.mptbm-vfb-import-btn', function(){
						$('#mptbm_vfb_import_modal').show();
					});
					$(document).on('click', '.mptbm-vfb-modal-close', function(){
						$('#mptbm_vfb_import_modal').hide();
					});
					$(document).on('click', '#mptbm_vfb_do_import', function(){
						var sourceId = $('#mptbm_vfb_source_vehicle').val();
						if (!sourceId) { alert('<?php esc_html_e( 'Please select a source vehicle.', 'ecab-taxi-booking-manager' ); ?>'); return; }
						var $btn = $(this);
						$btn.prop('disabled', true).text('<?php esc_html_e( 'Copying...', 'ecab-taxi-booking-manager' ); ?>');
						$.post(ajaxurl, {
							action: 'mptbm_vfb_import',
							source_id: sourceId,
							target_id: POST_ID,
							nonce: $btn.data('nonce')
						}, function(res) {
							$btn.prop('disabled', false).text('<?php esc_html_e( 'Copy Fields', 'ecab-taxi-booking-manager' ); ?>');
							if (res.success) {
								$('#mptbm_vfb_import_modal').hide();
								// Reload page to show imported fields
								location.reload();
							} else {
								alert(res.data || '<?php esc_html_e( 'Import failed.', 'ecab-taxi-booking-manager' ); ?>');
							}
						});
					});
				}

			})(jQuery);
			</script>
			<?php
		}
	}

	new MPTBM_Vehicle_Form_Builder();
}
