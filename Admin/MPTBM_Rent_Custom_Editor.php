<?php
/*
 * @Author 		rubelcuet10@gmail.com
 * Copyright: 	mage-people.com
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Rent_Custom_Editor')) {
    class MPTBM_Rent_Custom_Editor{
        public function __construct() {
            add_action('add_meta_boxes', [$this, 'settings_meta']);

            add_action('save_post', [ $this, 'mptbm_save_taxi_data' ] );

            add_action('admin_menu', [ $this, 'hide_all_transport_submenu'], 999);

            // Backward compatibility: the old custom editor lived at
            // admin.php?page=mptbm-rent-edit. That page is gone, but old
            // bookmarks/links (browser bookmarks, saved emails, anything
            // outside our control) may still point at it, so keep forwarding
            // them to the equivalent native screen instead of a dead link.
            add_action('admin_init', [ $this, 'redirect_legacy_editor_url' ]);

            add_action('admin_footer', [ $this, 'mute_editor_iframe_title_tooltip'] );

        }

        // TinyMCE sets a `title` attribute on its editable iframe ("Rich Text
        // Area. Press Alt-Shift-H for help.") purely for screen readers - but any
        // `title` attribute also triggers the browser's native tooltip on hover,
        // which reads as a stray text bubble to sighted users. Move it to
        // aria-label instead so it's still announced, without the visible tooltip.
        function mute_editor_iframe_title_tooltip() {
            $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
            if ( $screen && $screen->post_type === 'mptbm_rent' ) {
                ?>
                <script>
                jQuery(document).on('tinymce-editor-init', function (event, editor) {
                    if (editor.iframeElement && editor.iframeElement.hasAttribute('title')) {
                        editor.iframeElement.setAttribute('aria-label', editor.iframeElement.getAttribute('title'));
                        editor.iframeElement.removeAttribute('title');
                    }
                });
                </script>
                <?php
            }
        }
        function hide_all_transport_submenu() {
            remove_submenu_page(
                'edit.php?post_type=mptbm_rent', // Parent menu slug
                'edit.php?post_type=mptbm_rent'  // All Transport submenu slug
            );

        }

        public function redirect_legacy_editor_url() {
            if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'mptbm-rent-edit' ) {
                return;
            }

            $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;

            if ( $post_id && get_post_type( $post_id ) === 'mptbm_rent' ) {
                wp_safe_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
            } else {
                wp_safe_redirect( admin_url( 'post-new.php?post_type=mptbm_rent' ) );
            }
            exit;
        }

        // Registers the single tabbed "Information Settings" metabox on the
        // native post-new.php/post.php screen for mptbm_rent, replacing the
        // old hijacked admin.php?page=mptbm-rent-edit wizard page.
        public function settings_meta() {
            $label = MPTBM_Function::get_name();
            $cpt = MPTBM_Function::get_cpt();
            add_meta_box(
                'mptbm_rent_settings_panel',
                $label . ' ' . __('Information Settings', 'ecab-taxi-booking-manager'),
                [ $this, 'render_settings_metabox' ],
                $cpt,
                'normal',
                'high'
            );
        }

        public function render_settings_metabox( $post ) {
            $post_id = $post->ID;
            $pro_active = class_exists('MPTBM_Dependencies_Pro');
            ?>
            <?php self::pro_popup_markup(); ?>
            <div class="mpStyle mptbm_settings">
                <div class="mpTabs leftTabs">
                    <ul class="tabLists">
                        <li data-tabs-target="#mptbm_general_info">
                            <i class="fas fa-info-circle"></i> <span class="menu-text"><?php esc_html_e('General Info', 'ecab-taxi-booking-manager'); ?></span>
                        </li>
                        <li data-tabs-target="#mptbm_settings_pricing">
                            <i class="fas fa-tags"></i><span class="menu-text"><?php esc_html_e('Pricing', 'ecab-taxi-booking-manager'); ?></span>
                        </li>
                        <li data-tabs-target="#mptbm_settings_fees">
                            <i class="fas fa-receipt"></i><span class="menu-text"><?php esc_html_e('Fees & Extra Service', 'ecab-taxi-booking-manager'); ?></span>
                        </li>
                        <li data-tabs-target="#mptbm_settings_date">
                            <i class="fas fa-calendar-alt"></i><span class="menu-text"><?php esc_html_e('Operational Date & Time', 'ecab-taxi-booking-manager'); ?></span>
                        </li>
                        <li data-tabs-target="#wbtm_settings_tax">
                            <i class="fas fa-percent"></i><span class="menu-text"><?php esc_html_e('Advanced', 'ecab-taxi-booking-manager'); ?></span>
                        </li>
                    </ul>
                    <div class="mptbm-panel-row">
                    <div class="tabsContent">
                        <div class="tabsItem" data-tabs="#mptbm_general_info">
                            <?php self::general_information_set( $post_id, $pro_active ); ?>
                        </div>
                        <div class="tabsItem" data-tabs="#mptbm_settings_pricing">
                            <?php self::pricing_settings( $post_id, $pro_active ); ?>
                        </div>
                        <div class="tabsItem" data-tabs="#mptbm_settings_fees">
                            <?php
                            wp_nonce_field( 'mptbm_extra_service_nonce', 'mptbm_extra_service_nonce' );
                            ?>
                            <div class="mptbm_fees_services_workspace">
                                <section class="mptbm_fees_services_group is-fees" id="mptbm_fee_configuration">
                                    <div class="mptbm_fees_services_group_header">
                                        <div class="mptbm_fees_services_group_title">
                                            <span aria-hidden="true"><i class="fas fa-coins"></i></span>
                                            <div>
                                                <h3><?php esc_html_e('Fare Adjustments', 'ecab-taxi-booking-manager'); ?></h3>
                                                <p><?php esc_html_e('Set fixed starting charges, minimum fares and base-location costs.', 'ecab-taxi-booking-manager'); ?></p>
                                            </div>
                                        </div>
                                        <span class="mptbm_fees_services_group_tag"><?php esc_html_e('Trip pricing', 'ecab-taxi-booking-manager'); ?></span>
                                    </div>
                                    <div class="mptbm_fees_services_group_body">
                                        <?php
                                        self::initial_base_pricing( $post_id );
                                        self::enable_base_location_charges( $post_id, $pro_active );
                                        ?>
                                    </div>
                                </section>

                                <section class="mptbm_fees_services_group is-services is-minimal" id="mptbm_extra_service_configuration">
                                    <div class="mptbm_fees_services_group_header">
                                        <div class="mptbm_fees_services_group_title">
                                            <div>
                                                <h3><?php esc_html_e('Customer Add-ons', 'ecab-taxi-booking-manager'); ?></h3>
                                                <p><?php esc_html_e('Optional services customers can add to a booking.', 'ecab-taxi-booking-manager'); ?></p>
                                            </div>
                                        </div>
                                        <div class="mptbm_fees_services_group_header_actions">
                                            <?php
                                            $extra_services_display = MP_Global_Function::get_post_info( $post_id, 'display_mptbm_extra_services', 'on' );
                                            $extra_services_checked = $extra_services_display == 'off' ? '' : 'checked';
                                            ?>
                                            <div class="mptbm_taxi_ex_service_toggle_wrapper">
                                                <label class="mptbm_taxi_ex_service_switch">
                                                    <input type="checkbox" id="mptbm_taxi_ex_service_master_toggle" name="display_mptbm_extra_services" <?php echo esc_attr($extra_services_checked); ?>>
                                                    <span class="mptbm_taxi_ex_service_slider"></span>
                                                </label>
                                                <span class="mptbm_taxi_ex_service_toggle_label<?php echo esc_attr($extra_services_display === 'off' ? ' mptbm_taxi_off' : ''); ?>"><?php echo esc_html($extra_services_display === 'off' ? __('OFF', 'ecab-taxi-booking-manager') : __('ON', 'ecab-taxi-booking-manager')); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mptbm_fees_services_group_body">
                                        <?php self::extra_service_display( $post_id ); ?>
                                    </div>
                                </section>
                            </div>
                        </div>
                        <?php
                        // Not wrapped in our own tabsItem div: the action's
                        // listener (MPTBM_taxi_Date_Advanced_Settings::date_settings())
                        // already emits its own .tabsItem[data-tabs="#mptbm_settings_date"]
                        // wrapper. Nesting that inside another wrapper here would put
                        // the real content two levels deep instead of a direct child of
                        // .tabsContent, which is all the tab-switcher JS's
                        // .children('[data-tabs="..."]') selector matches — exactly the
                        // bug that made this tab render empty.
                        do_action( 'mptbm_date_and_advanced_settings', $post_id );
                        ?>
                    </div>
                    <div class="mptbm-panel-row-nav" id="mptbm-panel-row-nav">
                        <span class="mptbm-panel-row-nav__step" id="mptbm-panel-row-nav-step"></span>
                        <div class="mptbm-panel-row-nav__btns">
                            <button type="button" class="mptbm-btn mptbm-btn-secondary mptbm-panel-row-nav__prev" id="mptbm-panel-row-prev">
                                <i class="fas fa-arrow-left"></i> <?php esc_html_e( 'Previous', 'ecab-taxi-booking-manager' ); ?>
                            </button>
                            <button type="button" class="mptbm-btn mptbm-btn-primary mptbm-panel-row-nav__next" id="mptbm-panel-row-next">
                                <?php esc_html_e( 'Next', 'ecab-taxi-booking-manager' ); ?> <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
            <?php
        }


        function mptbm_save_taxi_data( $post_id ){

            if( $post_id ) {
                if (isset($_POST['mptbm_feature_image_id'])) {
                    $image_id = intval(sanitize_text_field($_POST['mptbm_feature_image_id']));
                    if ($image_id) {
                        set_post_thumbnail($post_id, $image_id);
                    } else {
                        delete_post_thumbnail($post_id);
                    }
                }

                $base_fare_pricing_display = isset($_POST['mptbm_display_taxi_base_fare_pricing']) ? sanitize_text_field( wp_unslash( $_POST['mptbm_display_taxi_base_fare_pricing'] ) ) : 'off';
                update_post_meta( $post_id, 'mptbm_display_taxi_base_fare_pricing', $base_fare_pricing_display );

                $operation_area_pricing_display = isset($_POST['mptbm_display_operation_area_pricing']) ? sanitize_text_field( wp_unslash( $_POST['mptbm_display_operation_area_pricing'] ) ) : 'off';
                update_post_meta( $post_id, 'mptbm_display_operation_area_pricing', $operation_area_pricing_display );

                $base_location_pricing_display = isset($_POST['mptbm_display_taxi_base_location_pricing']) ? sanitize_text_field( wp_unslash( $_POST['mptbm_display_taxi_base_location_pricing'] ) ) : 'off';
                update_post_meta( $post_id, 'mptbm_display_taxi_base_location_pricing', $base_location_pricing_display );

                $inclusive_manual_locations = isset($_POST['mptbm_inclusive_manual_locations']) ? 'on' : 'off';
                update_post_meta( $post_id, 'mptbm_inclusive_manual_locations', $inclusive_manual_locations );

                if ( get_post_type( $post_id ) === 'mptbm_rent' && isset( $_POST['mptbm_manual_route_map_field_present'] ) ) {
                    $manual_route_map = isset( $_POST['mptbm_manual_route_map'] ) ? 'on' : 'off';
                    update_post_meta( $post_id, 'mptbm_manual_route_map', $manual_route_map );
                }

                if ( get_post_type( $post_id ) === 'mptbm_rent' && isset( $_POST['mptbm_availability_status_field_present'] ) ) {
                    $availability_status = isset( $_POST['mptbm_availability_status'] ) ? 'unavailable' : 'available';
                    update_post_meta( $post_id, 'mptbm_availability_status', $availability_status );

                    $allowed_reasons = [ 'maintenance', 'booked', 'accident', 'repair', 'cleaning', 'driver_unavailable', 'other' ];
                    $reason = isset( $_POST['mptbm_availability_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['mptbm_availability_reason'] ) ) : 'maintenance';
                    $reason = in_array( $reason, $allowed_reasons, true ) ? $reason : 'maintenance';
                    update_post_meta( $post_id, 'mptbm_availability_reason', $reason );

                    $reason_note = $reason === 'other' && isset( $_POST['mptbm_availability_reason_note'] ) ? sanitize_text_field( wp_unslash( $_POST['mptbm_availability_reason_note'] ) ) : '';
                    update_post_meta( $post_id, 'mptbm_availability_reason_note', $reason_note );
                }
            }

        }

        public static function shortcode_description( $price_based ){
            if( $price_based === 'distance' || $price_based === 'duration' || $price_based === 'distance_duration' || $price_based === 'inclusive' ){
                $shortcode = 'dynamic';
            }else if( $price_based === 'fixed_hourly' ){
                $shortcode = 'fixed_hourly';
            }else if( $price_based === 'manual' ){
                $shortcode = 'manual';
            }else if( $price_based === 'fixed_distance' ){
                $shortcode = 'fixed_map';
            }else if( $price_based === 'fixed_zone' ){
                $shortcode = 'fixed_zone_pickup';
            }else{
                $shortcode = 'dynamic';
            }

            $title = ucwords(str_replace('_', ' ', $shortcode));
            ?>
            <div class="mptbm_shortcode_container">
                <!-- Header Section -->
                <div class="mptbm_shortcode_header">
                    <div class="mptbm_shortcode_header_left">
                        <div class="mptbm_shortcode_header_text">
                            <h3> <?php esc_html_e( 'Shortcode Usage Guide', 'ecab-taxi-booking-manager' ); ?></h3>
                            <p> <?php esc_html_e( 'Click to view the shortcode for Distance-based pricing', 'ecab-taxi-booking-manager' ); ?></p>
                        </div>
                    </div>
                    <div class="mptbm_shortcode_toggle">
                        <div class="text-xl text-gray-400 transition-transform" >▼</div>
                    </div>
                </div>

                <!-- Main Content Box -->
                <div class="mptbm_shortcode_body" style="display: none">

                    <!-- Primary Shortcode -->
                    <div class="mptbm_shortcode_section">
                        <h4 class="mptbm_shortcode_sub_title"><span id="mptbm_shortcode_title"><?php echo esc_attr( $title );?></span> <?php esc_html_e( 'Pricing Shortcode', 'ecab-taxi-booking-manager' ); ?></h4>
                        <div class="mptbm_shortcode_code_box mptbm_shortcode_primary_code" id="mptbm_shortcode_primary_code">
                            <code>[mptbm_booking price_based='<?php echo esc_attr( $shortcode );?>']</code>
                        </div>
                    </div>

                    <div class="mptbm_shortcode_divider"></div>

                    <!-- Optional Parameters -->
                    <div class="mptbm_shortcode_section">
                        <h4 class="mptbm_shortcode_sub_title">
                            <span class="mptbm_shortcode_icon_badge mptbm_shortcode_icon_badge--info">
                                <svg class="mptbm_shortcode_inline_icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                            </span>
                            <?php esc_html_e( 'Optional Parameters:', 'ecab-taxi-booking-manager' ); ?>
                        </h4>
                        <div class="mptbm_shortcode_grid">
                            <div class="mptbm_shortcode_param_item"><code>form='horizontal'</code> <span class="mptbm_shortcode_or"><?php esc_html_e( 'or', 'ecab-taxi-booking-manager' ); ?></span> <code>form='inline'</code></div>
                            <div class="mptbm_shortcode_param_item"><code>progressbar='yes'</code> <span class="mptbm_shortcode_or"><?php esc_html_e( 'or', 'ecab-taxi-booking-manager' ); ?></span> <code>progressbar='no'</code></div>
                            <div class="mptbm_shortcode_param_item"><code>map='yes'</code> <span class="mptbm_shortcode_or"><?php esc_html_e( 'or', 'ecab-taxi-booking-manager' ); ?></span> <code>map='no'</code></div>
                            <div class="mptbm_shortcode_param_item"><code>tabs='hourly,distance,manual'</code></div>
                        </div>
                    </div>

                    <!-- Example Usage -->
                    <div class="mptbm_shortcode_section">
                        <div class="mptbm_shortcode_example_wrapper">
                            <h4 class="mptbm_shortcode_sub_title">
                                <span class="mptbm_shortcode_icon_badge mptbm_shortcode_icon_badge--warning">
                                    <svg class="mptbm_shortcode_inline_icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18h6m-6-4h6m-7.5 4a6 6 0 1 1 9 0"></path></svg>
                                </span>
                                <?php esc_html_e( 'Example Usage:', 'ecab-taxi-booking-manager' ); ?>
                            </h4>
                            <div class="mptbm_shortcode_code_box mptbm_shortcode_example_code" id="mptbm_shortcode_example_code">
                                <code>[mptbm_booking price_based='<?php echo esc_attr( $shortcode );?>' form='horizontal' progressbar='yes' map='yes']</code>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        <?php }
        // Kept for reuse by render_settings_metabox(): the Pro-upsell popup
        // markup used by pricing_settings()'s locked/upsell tab panels.
        public static function pro_popup_markup() {
            ?>
            <div id="mptbm_pro_popup" class="mptbm_pro_popup">
                <div class="mptbm_pro_popup_content">
                    <span class="mptbm_pro_close_popup">&times;</span>

                    <h2><span class="dashicons dashicons-lock"></span> PRO FEATURE</h2>
                    <p>This feature is available in PRO version only.</p>

                    <a href="https://mage-people.com/product/wordpress-taxi-cab-booking-plugin-for-woocommerce/" target="_blank" class="buy-pro-btn">
                        Buy PRO Now
                    </a>
                </div>
            </div>
            <?php
        }

        // Real, fixed vehicle-identity fields (make/model/year/color/engine/plate/mileage) -
        // distinct from the free-form "Vehicle Features" chip list. Displayed in the
        // frontend "View Details" panel as a Make & Model / Year / Color / ... spec table,
        // only for whichever fields the admin actually filled in.
        public static function vehicle_specification_fields( $post_id ){
            $make_model = MP_Global_Function::get_post_info( $post_id, 'mptbm_spec_make_model', '' );
            $year       = MP_Global_Function::get_post_info( $post_id, 'mptbm_spec_year', '' );
            $color      = MP_Global_Function::get_post_info( $post_id, 'mptbm_spec_color', '' );
            $engine     = MP_Global_Function::get_post_info( $post_id, 'mptbm_spec_engine', '' );
            $plate      = MP_Global_Function::get_post_info( $post_id, 'mptbm_spec_plate', '' );
            $mileage    = MP_Global_Function::get_post_info( $post_id, 'mptbm_spec_mileage', '' );
            ?>
            <div class="mptbm_rent_editor_wrapper">

                <!-- Header -->
                <div class="mptbm_rent_editor_header">
                    <div>
                        <h2 class="mptbm_rent_editor_title"><i class="fas fa-ruler-combined"></i> <?php esc_html_e( 'Vehicle Capacity & Details', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p class="mptbm_rent_editor_subtitle">
                            <?php esc_html_e( 'Real vehicle identity details shown to customers in "View Details". Leave any field blank to omit it.', 'ecab-taxi-booking-manager' ); ?>
                        </p>
                    </div>
                </div>

                <!-- Body -->
                <div class="mptbm_rent_editor_body mptbm_field_grid_2col">

                    <div class="mptbm_rent_field_group">
                        <label><?php esc_html_e( 'Make & Model', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'The vehicle\'s manufacturer and model.', 'ecab-taxi-booking-manager' ); ?></p>
                        <input name="mptbm_spec_make_model" type="text" value="<?php echo esc_attr( $make_model );?>" placeholder="<?php esc_html_e( 'Toyota Premio', 'ecab-taxi-booking-manager' ); ?>">
                    </div>

                    <div class="mptbm_rent_field_group">
                        <label><?php esc_html_e( 'Year', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Model year of the vehicle.', 'ecab-taxi-booking-manager' ); ?></p>
                        <input name="mptbm_spec_year" type="text" value="<?php echo esc_attr( $year );?>" placeholder="<?php esc_html_e( '2023', 'ecab-taxi-booking-manager' ); ?>">
                    </div>

                    <div class="mptbm_rent_field_group">
                        <label><?php esc_html_e( 'Color', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Exterior color of the vehicle.', 'ecab-taxi-booking-manager' ); ?></p>
                        <input name="mptbm_spec_color" type="text" value="<?php echo esc_attr( $color );?>" placeholder="<?php esc_html_e( 'Pearl White', 'ecab-taxi-booking-manager' ); ?>">
                    </div>

                    <div class="mptbm_rent_field_group">
                        <label><?php esc_html_e( 'Engine', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Engine size/type.', 'ecab-taxi-booking-manager' ); ?></p>
                        <input name="mptbm_spec_engine" type="text" value="<?php echo esc_attr( $engine );?>" placeholder="<?php esc_html_e( '1.8L Hybrid', 'ecab-taxi-booking-manager' ); ?>">
                    </div>

                    <div class="mptbm_rent_field_group">
                        <label><?php esc_html_e( 'Plate Class', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Registration/plate class shown to customers (not the full plate number).', 'ecab-taxi-booking-manager' ); ?></p>
                        <input name="mptbm_spec_plate" type="text" value="<?php echo esc_attr( $plate );?>" placeholder="<?php esc_html_e( 'Dhaka Metro-GA', 'ecab-taxi-booking-manager' ); ?>">
                    </div>

                    <div class="mptbm_rent_field_group">
                        <label><?php esc_html_e( 'Mileage', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Fuel efficiency, as you\'d like it shown to customers.', 'ecab-taxi-booking-manager' ); ?></p>
                        <input name="mptbm_spec_mileage" type="text" value="<?php echo esc_attr( $mileage );?>" placeholder="<?php esc_html_e( '18 km/l', 'ecab-taxi-booking-manager' ); ?>">
                    </div>

                </div>

            </div>
        <?php }
        public static function taxi_title_description_set( $post_id, $max_passenger, $max_bag, $max_hand_luggage, $extra_info ){ ?>
            <div class="mptbm_rent_editor_wrapper">

                <!-- Header -->
                <div class="mptbm_rent_editor_header">
                    <div class="mptbm_rent_editor_title_group">
                        <span class="mptbm_rent_editor_icon"><i class="fas fa-id-card"></i></span>
                        <div>
                            <h2 class="mptbm_rent_editor_title"><?php esc_html_e( 'Basic Information', 'ecab-taxi-booking-manager' ); ?></h2>
                            <p class="mptbm_rent_editor_subtitle">
                                <?php esc_html_e( 'Give your rental a clear, descriptive name, and set its passenger/luggage capacity.', 'ecab-taxi-booking-manager' ); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Body -->
                <div class="mptbm_rent_editor_body mptbm_field_grid_2col">
                    <!-- Title -->
                    <div class="mptbm_rent_field_group">

                        <label class="mptbm_rent_label" for="mptbm_rent_title">
                            <?php esc_html_e( 'Title', 'ecab-taxi-booking-manager' ); ?> <span class="mptbm_rent_required">*</span>
                        </label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'A clear, descriptive name customers will see (e.g. the vehicle\'s make & model).', 'ecab-taxi-booking-manager' ); ?></p>

                        <input
                                type="text"
                                id="mptbm_rent_title"
                                name="post_title"
                                class="mptbm_rent_input"
                                value="<?php echo esc_attr(get_the_title($post_id)); ?>"
                                placeholder="Enter rent title"
                                required
                        >

                    </div>
                    <!-- Description -->
                    <div class="mptbm_rent_field_group mptbm_rent_field_full" style="display: none">

                        <label class="mptbm_rent_label">
                            <?php esc_html_e( 'Description', 'ecab-taxi-booking-manager' ); ?>
                        </label>

                        <div class="mptbm_rent_editor_area">

                            <?php
                            $content = $post_id ? get_post_field('post_content', $post_id) : '';

                            wp_editor(
                                $content,
                                'mptbm_rent_description',
                                array(
                                    'textarea_name' => 'post_content',
                                    'media_buttons' => true,
                                    'textarea_rows' => 10,
                                    'teeny'         => false,
                                    'quicktags'     => true,
                                )
                            );
                            ?>

                        </div>

                    </div>

                    <div class="mptbm_rent_field_group">
                        <label><?php esc_html_e( 'Maximum Passengers', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Max number of passengers this vehicle can accommodate.', 'ecab-taxi-booking-manager' ); ?></p>
                        <input name="mptbm_maximum_passenger" type="text" value="<?php echo esc_attr( $max_passenger );?>" placeholder="<?php esc_html_e( '4', 'ecab-taxi-booking-manager' ); ?>">
                    </div>

                    <div class="mptbm_rent_field_group">
                        <label><?php esc_html_e( 'Maximum Bags', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Max number of large bags/suitcases allowed.', 'ecab-taxi-booking-manager' ); ?></p>
                        <input name="mptbm_maximum_bag" type="text" value="<?php echo esc_attr( $max_bag );?>" placeholder="<?php esc_html_e( '3', 'ecab-taxi-booking-manager' ); ?>">
                    </div>

                    <div class="mptbm_rent_field_group">
                        <label><?php esc_html_e( 'Maximum Hand Luggage', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Max number of carry-on or hand luggage items.', 'ecab-taxi-booking-manager' ); ?></p>
                        <input name="mptbm_maximum_hand_luggage" type="text" value="<?php echo esc_attr( $max_hand_luggage );?>" placeholder="<?php esc_html_e( '2', 'ecab-taxi-booking-manager' ); ?>">
                    </div>

                    <div class="mptbm_rent_field_group mptbm_rent_field_full">
                        <label><?php esc_html_e( 'Description', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Additional details displayed to customers (e.g. amenities, notes).', 'ecab-taxi-booking-manager' ); ?></p>
                        <?php
                        wp_editor(
                            $extra_info,
                            'mptbm_extra_info_editor',
                            array(
                                'textarea_name' => 'mptbm_extra_info',
                                'media_buttons' => false,
                                'textarea_rows' => 6,
                                'teeny'         => false,
                                'quicktags'     => true,
                            )
                        );
                        ?>
                    </div>
                </div>

            </div>
        <?php }


        public static function general_information_set( $post_id, $pro_active ){

            $price_display_type = MP_Global_Function::get_post_info($post_id, 'mptbm_price_display_type', 'normal');
            $custom_price_message = MP_Global_Function::get_post_info($post_id, 'mptbm_custom_price_message', '');


            $max_passenger = MP_Global_Function::get_post_info($post_id, 'mptbm_maximum_passenger');
            $max_bag = MP_Global_Function::get_post_info($post_id, 'mptbm_maximum_bag');
            $max_hand_luggage = MP_Global_Function::get_post_info($post_id, 'mptbm_maximum_hand_luggage');

            $extra_info = MP_Global_Function::get_post_info($post_id, 'mptbm_extra_info', '');
            $all_features = MP_Global_Function::get_post_info($post_id, 'mptbm_features');
            if (!$all_features) {
                $all_features = array(
                    array(
                        'label' => esc_html__('Name', 'ecab-taxi-booking-manager'),
                        'icon' => 'fas fa-car-side',
                        'image' => '',
                        'text' => ''
                    ),
                    array(
                        'label' => esc_html__('Model', 'ecab-taxi-booking-manager'),
                        'icon' => 'fas fa-car',
                        'image' => '',
                        'text' => ''
                    ),
                    array(
                        'label' => esc_html__('Engine', 'ecab-taxi-booking-manager'),
                        'icon' => 'fas fa-cogs',
                        'image' => '',
                        'text' => ''
                    ),
                    array(
                        'label' => esc_html__('Fuel Type', 'ecab-taxi-booking-manager'),
                        'icon' => 'fas fa-gas-pump',
                        'image' => '',
                        'text' => ''
                    )
                );
            }
            ?>
            <div class="mptbm_taxi_container">
                <?php wp_nonce_field('mptbm_transportation_type_nonce', 'mptbm_transportation_type_nonce');

                self::taxi_title_description_set( $post_id, $max_passenger, $max_bag, $max_hand_luggage, $extra_info );

                self::vehicle_specification_fields( $post_id );

                ?>

                <div class="mptbm_rent_editor_wrapper">
                    <div class="mptbm_rent_editor_header">
                        <div>
                            <h2 class="mptbm_rent_editor_title"><i class="fas fa-eye"></i> <?php esc_html_e( 'Price Display Settings', 'ecab-taxi-booking-manager' ); ?></h2>
                            <p class="mptbm_rent_editor_subtitle"><?php esc_html_e( 'Control how fares appear to customers — show the real price, zero, or a custom message.', 'ecab-taxi-booking-manager' ); ?></p>
                        </div>
                    </div>
                    <div class="mptbm_rent_editor_body">
                        <div class="mptbm_taxi_advanced_card" style="margin-bottom: 0;">
                            <div class="mptbm_taxi_advanced_card_header">
                                <div class="mptbm_taxi_advanced_title_block">
                                    <label class="mptbm_rent_label"><?php esc_html_e('Price Display Type', 'ecab-taxi-booking-manager'); ?></label>
                                    <span class="desc"><?php esc_html_e('Choose how the price is displayed to customers', 'ecab-taxi-booking-manager'); ?></span>
                                </div>
                                <select class="formControl" name="mptbm_price_display_type" id="mptbm_price_display_type" data-collapse-target="">
                                    <option value="normal" <?php selected($price_display_type, 'normal'); ?>><?php esc_html_e('Normal Price', 'ecab-taxi-booking-manager'); ?></option>
                                    <option value="zero" <?php selected($price_display_type, 'zero'); ?>><?php esc_html_e('Show as Zero (0.00)', 'ecab-taxi-booking-manager'); ?></option>
                                    <option value="custom_message" <?php selected($price_display_type, 'custom_message'); ?>><?php esc_html_e('Show Custom Message', 'ecab-taxi-booking-manager'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="mptbm_taxi_advanced_card" id="mptbm_custom_message_show" style="display: <?php echo esc_attr($price_display_type == 'custom_message' ? 'block' : 'none'); ?>; margin-top: 0; border-top: none;">
                            <div class="mptbm_custom_message_label">
                                <div class="mptbm_custom_message_title_holder">
                                    <h6><?php esc_html_e('Custom Price Message', 'ecab-taxi-booking-manager'); ?></h6>
                                    <span class="desc"><?php esc_html_e('Message to display instead of price (e.g. "Price pending confirmation")', 'ecab-taxi-booking-manager'); ?></span>
                                </div>
                                <textarea class="mptbm_custom_message_input" name="mptbm_custom_price_message" rows="3"><?php echo esc_textarea($custom_price_message); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <?php

                if (class_exists('MPTBM_Plugin_Pro')) {
                    self::taxi_inventory_manages($post_id, $all_features);
                }

                self::taxi_feature_add_remove( $post_id, $all_features );

                self::taxi_show_reviews_toggle( $post_id );

                ?>

            </div>
        <?php }

        public static function enable_base_location_charges( $post_id, $pro_active ){
            $base_price_location = MP_Global_Function::get_post_info($post_id, 'mptbm_base_price_location', '');
            $base_price_km = MP_Global_Function::get_post_info($post_id, 'mptbm_base_price_km', '');
            $base_price_hour = MP_Global_Function::get_post_info($post_id, 'mptbm_base_price_hour', '');
            $base_min_threshold = MP_Global_Function::get_post_info($post_id, 'mptbm_base_min_threshold', '');
            $charge_base_pickup = MP_Global_Function::get_post_info($post_id, 'mptbm_charge_base_pickup', 'no');
            $charge_base_dropoff = MP_Global_Function::get_post_info($post_id, 'mptbm_charge_base_dropoff', 'no');

            $locations = get_terms(array(
                'taxonomy' => 'locations',
                'hide_empty' => false,
            ));

            $display            = MP_Global_Function::get_post_info( $post_id, 'mptbm_display_taxi_base_location_pricing', 'off' );
            $active             = $display == 'off' ? 'none' : 'block';
            $checked            = $display == 'off' ? '' : 'checked';
            ?>

            <div class="mptbm_taxi_toggle_container" id="mptbm_taxi_base_location_toggle_container">

                <div class="mptbm_taxi_ex_service_header mptbm_rent_editor_header">
                    <div class="mptbm_taxi_ex_service_title_group">
                        <h2 class="mptbm_rent_editor_title"><i class="fas fa-map-marker-alt"></i> <?php esc_html_e( 'Enable Base Location Charges', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p class="mptbm_rent_editor_subtitle"><?php esc_html_e( 'Apply additional charges based on distance between taxi base location and pickup/drop-off points.', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                    <div class="mptbm_taxi_ex_service_toggle_wrapper">

                            <label class="mptbm_taxi_ex_service_switch">
                                <input type="checkbox"
                                       id="mptbm_display_taxi_base_location_pricing"
                                       name="mptbm_display_taxi_base_location_pricing"
                                       class="mptbm_taxi_toggle_trigger"
                                    <?php echo esc_attr($checked); ?>>
                                <span class="mptbm_taxi_slider"></span>
                            </label>
                            <span class="mptbm_taxi_ex_service_toggle_label mptbm_display_taxi_base_location_pricing_level<?php echo esc_attr($display === 'off' ? ' mptbm_taxi_off' : ''); ?>">
                                <?php echo esc_html($display === 'off' ? __('OFF', 'ecab-taxi-booking-manager') : __('ON', 'ecab-taxi-booking-manager')); ?>
                            </span>
                    </div>

                </div>

                <div class="mptbm_pro_lock<?php echo $pro_active ? '' : ' is-locked'; ?>" id="mptbm_taxi_base_location_price_lock" style="display: <?php echo esc_attr( $active );?>">
                <?php if ( ! $pro_active ) : ?>
                    <div class="mptbm_pro_lock_overlay">
                        <span class="mptbm_pro_lock_badge"><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'PRO feature', 'ecab-taxi-booking-manager' ); ?></span>
                        <p><?php esc_html_e( 'Base location pricing (per-KM / per-hour charges from a fixed base) is available in the PRO version.', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                <?php endif; ?>
                <div class="mptbm_pro_lock_content">
                <div class="mptbm_taxi_ex_service_body" id="mptbm_taxi_base_location_price_body">
                    <div class="mptbm_taxi_base_price_row">
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Base Price Location', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Select the base location for price calculation', 'ecab-taxi-booking-manager' ); ?></p>
                            <select class="formControl" name="mptbm_base_price_location" <?php disabled( ! $pro_active ); ?>>
                                <option value=""><?php esc_html_e('Select Location', 'ecab-taxi-booking-manager'); ?></option>
                                <?php if (!empty($locations) && !is_wp_error($locations)) : ?>
                                    <?php foreach ($locations as $location) :
                                        $geo = get_term_meta($location->term_id, 'mptbm_geo_location', true);
                                        if (empty($geo)) {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?php echo esc_attr($location->term_id); ?>" <?php selected($base_price_location, $location->term_id); ?>>
                                            <?php echo esc_html($location->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>


                        <div class="mptbm_taxi_field">
                            <label><?php printf( esc_html__( 'Price per %s', 'ecab-taxi-booking-manager' ), esc_html( MPTBM_Function::distance_unit_label() ) ); ?></label>
                            <p class="mptbm_taxi_help"><?php printf( esc_html__( 'Enter the price per %s from base location', 'ecab-taxi-booking-manager' ), esc_html( strtolower( MPTBM_Function::distance_unit_label() ) ) ); ?></p>
                            <input
                                    name="mptbm_base_price_km"
                                    type="number"
                                    min="0"
                                    step="0.1"
                                    value="<?php echo esc_attr( $base_price_km ?: '0' ); ?>"
                                    placeholder="1.5"
                                    <?php disabled( ! $pro_active ); ?>
                            >
                        </div>
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Price per Hour', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Enter the price per hour from base location', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_base_price_hour" type="number" value="<?php echo esc_attr( $base_price_hour );?>" placeholder="10 " <?php disabled( ! $pro_active ); ?>>
                        </div>
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Minimum Threshold (Distance)', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Distance free of charge from base price location', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_base_min_threshold" type="number" value="<?php echo esc_attr( $base_min_threshold );?>" placeholder="1" <?php disabled( ! $pro_active ); ?>>
                        </div>
                    </div>

                    <div class=" mptbm_taxi_toggle_box">
                        <div class="mptbm_taxi_toggle_header">
                            <div class="mptbm_taxi_toggle_info">
                                <div class="mptbm_taxi_toggle_text">
                                    <strong><?php esc_html_e( 'Charge for Base to Pickup?', 'ecab-taxi-booking-manager' ); ?></strong>
                                    <p><?php esc_html_e( 'Enable to charge for distance/time from base location to pickup location', 'ecab-taxi-booking-manager' ); ?></p>
                                </div>
                            </div>

                            <div class="mptbm_taxi_ex_service_toggle_wrapper">
                                <label class="mptbm_taxi_ex_service_switch">
                                    <input name="mptbm_charge_base_pickup" type="checkbox" class="mptbm_taxi_toggle_trigger" <?php echo ($charge_base_pickup == 'yes') ? 'checked' : ''; ?> <?php disabled( ! $pro_active ); ?>>
                                    <span class="mptbm_taxi_slider"></span>
                                </label>
                                <?php if( $charge_base_pickup == 'yes' ){?>
                                    <span class="mptbm_taxi_status_badge"><?php esc_html_e( 'ON', 'ecab-taxi-booking-manager' ); ?></span>
                                <?php }else{?>
                                    <span class="mptbm_taxi_status_badge mptbm_taxi_off"><?php esc_html_e( 'OFF', 'ecab-taxi-booking-manager' ); ?></span>
                                <?php }?>
                            </div>


                        </div>
                    </div>

                    <div class=" mptbm_taxi_toggle_box">
                        <div class="mptbm_taxi_toggle_header">
                            <div class="mptbm_taxi_toggle_info">
                                <div class="mptbm_taxi_toggle_text">
                                    <strong><?php esc_html_e( 'Charge for Base to Drop-off?', 'ecab-taxi-booking-manager' ); ?></strong>
                                    <p><?php esc_html_e( 'Enable to charge for distance/time from drop-off location back to base location', 'ecab-taxi-booking-manager' ); ?></p>
                                </div>
                            </div>
                            <div class="mptbm_taxi_ex_service_toggle_wrapper">
                                <label class="mptbm_taxi_ex_service_switch">
                                <input name="mptbm_charge_base_dropoff" type="checkbox" class="mptbm_taxi_toggle_trigger" <?php echo ($charge_base_dropoff == 'yes') ? 'checked' : ''; ?> <?php disabled( ! $pro_active ); ?>>
                                    <span class="mptbm_taxi_slider"></span>
                                </label>
                            <?php if( $charge_base_dropoff == 'yes' ){?>
                                <span class="mptbm_taxi_status_badge"><?php esc_html_e( 'ON', 'ecab-taxi-booking-manager' ); ?></span>
                            <?php }else{?>
                                <span class="mptbm_taxi_status_badge mptbm_taxi_off"><?php esc_html_e( 'OFF', 'ecab-taxi-booking-manager' ); ?></span>
                            <?php }?>
                            </div>
                        </div>
                    </div>

                </div>
                </div><!-- .mptbm_pro_lock_content -->
                </div><!-- .mptbm_pro_lock -->
            </div>
        <?php }
        public static function features_item($features = array()) {
                $text = array_key_exists('text', $features) ? $features['text'] : '';
                $icon = array_key_exists('icon', $features) ? $features['icon'] : '';
                $image = array_key_exists('image', $features) ? $features['image'] : '';
                ?>

                <div class="mptbm_taxi_feature_row">
                    <?php do_action('mp_add_icon_image', 'mptbm_features_icon_image[]', $icon, $image); ?>
                    <input type="text" class="mptbm_taxi_feature_input" name="mptbm_features_text[]" value="<?php echo esc_attr($text); ?>"/>
                    <div class="mptbm_taxi_feature_actions">
                        <button type="button" class="mptbm_taxi_feature_btn_icon mptbm_taxi_feature_btn_del" title="<?php esc_attr_e( 'Remove', 'ecab-taxi-booking-manager' ); ?>"><span class="dashicons dashicons-trash"></span></button>
                        <button type="button" class="mptbm_taxi_feature_btn_icon mptbm_taxi_feature_btn_move" title="<?php esc_attr_e( 'Drag to reorder', 'ecab-taxi-booking-manager' ); ?>"><span class="dashicons dashicons-move"></span></button>
                    </div>
                </div>
                <?php
            }
        public static function taxi_feature_add_remove( $post_id, $all_features ){
            $display_features = MP_Global_Function::get_post_info($post_id, 'display_mptbm_features', 'on');
            $features_active = $display_features == 'off' ? 'Off' : 'On';
            $display = $display_features == 'off' ? 'none' : 'block';
            $features_checked = $display_features == 'off' ? '' : 'checked';
            ?>
            <div class="mptbm_rent_editor_wrapper mpStyle vehicle-feature">
                <div class="mptbm_taxi_feature_header mptbm_rent_editor_header">
                    <div class="mptbm_taxi_feature_title_area">
                        <h2 class="mptbm_rent_editor_title"><i class="fas fa-list-ul"></i> <?php esc_html_e( 'Vehicle Features', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p class="mptbm_rent_editor_subtitle"><?php esc_html_e( 'Add icons and labels to highlight key vehicle features shown on the booking form.', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                    <div class="mptbm_taxi_feature_switch">

                        <span class="mptbm_taxi_feature_switch_text"><?php echo esc_attr( $features_active );?></span>
                        <label class="mptbm_taxi_feature_toggle">
                            <input type="checkbox" id="mptbm_taxi_feature_master_toggle" name="display_mptbm_features" <?php echo esc_attr( $features_checked );?>>
                            <span class="mptbm_taxi_feature_slider"></span>
                        </label>
                    </div>
                </div>

                <div class="mptbm_taxi_feature_body" style="display: <?php echo esc_attr( $display );?>">
                    <div class="mptbm_taxi_feature_labels">
                        <div><?php esc_html_e( 'Icon/Image', 'ecab-taxi-booking-manager' ); ?></div>
                        <div><?php esc_html_e( 'Description', 'ecab-taxi-booking-manager' ); ?></div>
                        <div><?php esc_html_e( 'Action', 'ecab-taxi-booking-manager' ); ?></div>
                    </div>

                    <div id="mptbm_taxi_feature_list">
                        <?php

                        if (is_array($all_features) && sizeof($all_features) > 0) {
                            foreach ($all_features as $features) {
                                self::features_item($features);
                            }
                        } else {
                            self::features_item();
                        }
                        ?>
                    </div>

                    <div class="mptbm_taxi_feature_footer">
                        <button type="button" class="mptbm_taxi_feature_add_btn" id="mptbm_taxi_feature_add_row">
                            <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add New Item', 'ecab-taxi-booking-manager' ); ?>
                        </button>
                    </div>
                </div>


            </div>
        <?php }
        public static function taxi_show_reviews_toggle( $post_id ){
            $show_reviews = MP_Global_Function::get_post_info($post_id, 'mptbm_show_reviews', 'no');
            $reviews_active = $show_reviews == 'yes' ? 'On' : 'Off';
            $reviews_checked = $show_reviews == 'yes' ? 'checked' : '';
            ?>
            <div class="mptbm_rent_editor_wrapper">
                <div class="mptbm_taxi_feature_header mptbm_rent_editor_header">
                    <div class="mptbm_taxi_feature_title_area">
                        <h2 class="mptbm_rent_editor_title"><i class="fas fa-star"></i> <?php esc_html_e( 'Customer Reviews', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p class="mptbm_rent_editor_subtitle"><?php esc_html_e( 'Show the star rating in search results and let customers leave a review for this vehicle after a completed trip.', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                    <div class="mptbm_taxi_feature_switch">
                        <span class="mptbm_taxi_feature_switch_text"><?php echo esc_html( $reviews_active );?></span>
                        <label class="mptbm_taxi_feature_toggle">
                            <input type="checkbox" id="mptbm_show_reviews" name="mptbm_show_reviews" <?php echo esc_attr( $reviews_checked );?>>
                            <span class="mptbm_taxi_feature_slider"></span>
                        </label>
                    </div>
                </div>
                <?php self::render_reviews_admin_list( $post_id ); ?>
            </div>
        <?php }

        // Reviews are only manageable here while the toggle above is on. Existing reviews are
        // not rendered up front - admin clicks a button to load them (20 at a time, with Load
        // More), so a vehicle with hundreds of reviews doesn't bloat the edit page. The "Add
        // Review" form always shows (even at zero reviews) since real reviews collected outside
        // the normal completed-booking flow (phone, in person, another platform) had no way to
        // ever be entered otherwise.
        public static function render_reviews_admin_list( $post_id ){
            if ( ! class_exists( 'MPTBM_Reviews' ) || ! MPTBM_Reviews::reviews_enabled( $post_id ) ) {
                return;
            }
            $total = MPTBM_Reviews::get_average_rating( $post_id )['count'];
            ?>
            <div class="mptbm_reviews_manage_card" id="mptbm_admin_reviews_list">
                <div class="mptbm_reviews_manage_head">
                    <label class="mptbm_rent_label"><?php esc_html_e( 'Manage Reviews', 'ecab-taxi-booking-manager' ); ?></label>
                    <span class="mptbm_reviews_count_badge" id="mptbm_reviews_count_badge">
                        <?php
                        printf(
                            /* translators: %d: number of reviews */
                            esc_html( _n( '%d review', '%d reviews', $total, 'ecab-taxi-booking-manager' ) ),
                            (int) $total
                        );
                        ?>
                    </span>
                </div>

                <div class="mptbm_add_review_card">
                    <p class="mptbm_add_review_title"><span class="dashicons dashicons-star-filled"></span><?php esc_html_e( 'Add a Review', 'ecab-taxi-booking-manager' ); ?></p>
                    <p class="mptbm_add_review_subtitle"><?php esc_html_e( 'For real feedback collected outside the normal completed-booking flow (phone, in person, another platform).', 'ecab-taxi-booking-manager' ); ?></p>

                    <div class="mptbm_add_review_grid">
                        <div class="mptbm_review_field">
                            <label for="mptbm_new_review_author"><?php esc_html_e( 'Reviewer Name', 'ecab-taxi-booking-manager' ); ?></label>
                            <input type="text" id="mptbm_new_review_author" class="mptbm_review_input" placeholder="<?php esc_attr_e( 'Tasnim R.', 'ecab-taxi-booking-manager' ); ?>">
                        </div>
                        <div class="mptbm_review_field">
                            <label><?php esc_html_e( 'Rating', 'ecab-taxi-booking-manager' ); ?></label>
                            <div class="mptbm_star_picker" id="mptbm_new_review_star_picker">
                                <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                                    <span class="dashicons dashicons-star-filled is-filled" data-value="<?php echo esc_attr( $i ); ?>"></span>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" id="mptbm_new_review_rating" value="5">
                        </div>
                    </div>

                    <div class="mptbm_review_field mptbm_review_field_text">
                        <label for="mptbm_new_review_content"><?php esc_html_e( 'Review Text', 'ecab-taxi-booking-manager' ); ?></label>
                        <textarea id="mptbm_new_review_content" rows="3" class="mptbm_review_textarea" placeholder="<?php esc_attr_e( 'What did the customer say about their trip?', 'ecab-taxi-booking-manager' ); ?>"></textarea>
                    </div>

                    <div class="mptbm_add_review_actions">
                        <button type="button" class="mptbm_review_btn_primary" id="mptbm_add_review_btn"
                            data-post-id="<?php echo esc_attr( $post_id ); ?>"
                            data-nonce="<?php echo esc_attr( wp_create_nonce( 'mptbm_add_review_' . $post_id ) ); ?>">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e( 'Add Review', 'ecab-taxi-booking-manager' ); ?>
                        </button>
                        <span class="mptbm_add_review_message" id="mptbm_add_review_message"></span>
                    </div>
                </div>

                <button type="button" class="mptbm_review_btn_ghost" id="mptbm_view_reviews_btn"
                    data-post-id="<?php echo esc_attr( $post_id ); ?>"
                    data-nonce="<?php echo esc_attr( wp_create_nonce( 'mptbm_load_reviews_' . $post_id ) ); ?>"
                    <?php echo $total === 0 ? 'style="display:none;"' : ''; ?>>
                    <span class="dashicons dashicons-visibility"></span>
                    <span id="mptbm_view_reviews_btn_label">
                    <?php
                    printf(
                        /* translators: %d: number of reviews */
                        esc_html__( 'View Reviews (%d)', 'ecab-taxi-booking-manager' ),
                        (int) $total
                    );
                    ?>
                    </span>
                </button>
                <p class="mptbm_reviews_empty_state" id="mptbm_no_reviews_yet" <?php echo $total > 0 ? 'style="display:none;"' : ''; ?>>
                    <span class="dashicons dashicons-format-status"></span>
                    <?php esc_html_e( 'No reviews yet.', 'ecab-taxi-booking-manager' ); ?>
                </p>
                <div class="mptbm_reviews_list" id="mptbm_reviews_list_body"></div>
                <button type="button" class="mptbm_review_btn_ghost" id="mptbm_load_more_reviews_btn" style="display:none;" data-offset="0">
                    <?php esc_html_e( 'Load More', 'ecab-taxi-booking-manager' ); ?>
                </button>
            </div>
            <script>
            jQuery(function($){
                var mptbmReviewTotal = <?php echo (int) $total; ?>;

                function mptbmEscapeHtml(str) {
                    return $('<div>').text(str || '').html();
                }
                function mptbmInitials(name) {
                    var parts = $.trim(name || '').split(/\s+/);
                    var initials = '';
                    $.each(parts.slice(0, 2), function(i, part) { initials += part.charAt(0).toUpperCase(); });
                    return initials || '?';
                }
                function mptbmReviewRowHtml(review) {
                    var stars = '';
                    for (var i = 1; i <= 5; i++) { stars += (i <= review.rating) ? '★' : '☆'; }
                    return '<div class="mptbm_review_row" data-comment-id="' + review.id + '">' +
                        '<div class="mptbm_review_avatar">' + mptbmInitials(review.author) + '</div>' +
                        '<div class="mptbm_review_body">' +
                            '<div class="mptbm_review_row_head">' +
                                '<strong class="mptbm_review_author">' + mptbmEscapeHtml(review.author) + '</strong>' +
                                '<span class="mptbm_review_date">' + mptbmEscapeHtml(review.date) + '</span>' +
                            '</div>' +
                            '<div class="mptbm_review_stars">' + stars + '</div>' +
                            '<p class="mptbm_review_text">' + mptbmEscapeHtml(review.content) + '</p>' +
                        '</div>' +
                        '<button type="button" class="mptbm_review_delete_btn" data-comment-id="' + review.id + '" data-nonce="' + review.delete_nonce + '" title="<?php echo esc_js( __( 'Delete', 'ecab-taxi-booking-manager' ) ); ?>">' +
                            '<span class="dashicons dashicons-trash"></span>' +
                        '</button>' +
                    '</div>';
                }

                function mptbmLoadReviews(offset) {
                    var $viewBtn = $('#mptbm_view_reviews_btn');
                    var $loadMoreBtn = $('#mptbm_load_more_reviews_btn').prop('disabled', true);
                    $.post(ajaxurl, {
                        action: 'mptbm_admin_load_reviews',
                        post_id: $viewBtn.data('post-id'),
                        offset: offset,
                        nonce: $viewBtn.data('nonce')
                    }, function(response){
                        $loadMoreBtn.prop('disabled', false);
                        if (!response.success) {
                            alert((response.data && response.data.message) ? response.data.message : 'Error');
                            return;
                        }
                        var html = '';
                        $.each(response.data.reviews, function(i, review){ html += mptbmReviewRowHtml(review); });
                        $('#mptbm_reviews_list_body').append(html);
                        var newOffset = offset + response.data.reviews.length;
                        $loadMoreBtn.data('offset', newOffset).toggle(response.data.has_more);
                    }).fail(function(){
                        $loadMoreBtn.prop('disabled', false);
                        alert('Error, please try again.');
                    });
                }

                $('#mptbm_view_reviews_btn').on('click', function(){
                    $(this).prop('disabled', true).hide();
                    mptbmLoadReviews(0);
                });

                $('#mptbm_load_more_reviews_btn').on('click', function(){
                    mptbmLoadReviews($(this).data('offset') || 0);
                });

                $(document).on('click', '.mptbm_review_delete_btn', function(){
                    if (!confirm(<?php echo wp_json_encode( __( 'Delete this review? This cannot be undone.', 'ecab-taxi-booking-manager' ) ); ?>)) {
                        return;
                    }
                    var $btn = $(this).prop('disabled', true);
                    var $row = $btn.closest('.mptbm_review_row');
                    $.post(ajaxurl, {
                        action: 'mptbm_admin_delete_review',
                        comment_id: $btn.data('comment-id'),
                        post_id: <?php echo (int) $post_id; ?>,
                        nonce: $btn.data('nonce')
                    }, function(response){
                        if (response.success) {
                            $row.fadeOut(200, function(){ $(this).remove(); });
                            mptbmReviewTotal = Math.max(0, mptbmReviewTotal - 1);
                            $('#mptbm_reviews_count_badge').text(mptbmReviewTotal + (mptbmReviewTotal === 1 ? ' <?php echo esc_js( __( 'review', 'ecab-taxi-booking-manager' ) ); ?>' : ' <?php echo esc_js( __( 'reviews', 'ecab-taxi-booking-manager' ) ); ?>'));
                        } else {
                            $btn.prop('disabled', false);
                            alert((response.data && response.data.message) ? response.data.message : 'Error');
                        }
                    }).fail(function(){
                        $btn.prop('disabled', false);
                        alert('Error, please try again.');
                    });
                });

                /* ---------- Clickable star picker ---------- */
                function mptbmPaintStars($picker, value) {
                    $picker.find('.dashicons').each(function(){
                        var starVal = $(this).data('value');
                        $(this)
                            .toggleClass('dashicons-star-filled', starVal <= value)
                            .toggleClass('dashicons-star-empty', starVal > value)
                            .toggleClass('is-filled', starVal <= value);
                    });
                }
                $('#mptbm_new_review_star_picker .dashicons').on('click', function(){
                    var val = $(this).data('value');
                    $('#mptbm_new_review_rating').val(val);
                    mptbmPaintStars($('#mptbm_new_review_star_picker'), val);
                });

                $('#mptbm_add_review_btn').on('click', function(){
                    var $btn = $(this).prop('disabled', true);
                    var $msg = $('#mptbm_add_review_message').removeClass('is-error is-success').text('<?php echo esc_js( __( 'Saving…', 'ecab-taxi-booking-manager' ) ); ?>');
                    $.post(ajaxurl, {
                        action: 'mptbm_admin_add_review',
                        post_id: $btn.data('post-id'),
                        nonce: $btn.data('nonce'),
                        author: $('#mptbm_new_review_author').val(),
                        rating: $('#mptbm_new_review_rating').val(),
                        content: $('#mptbm_new_review_content').val()
                    }, function(response){
                        $btn.prop('disabled', false);
                        if (!response.success) {
                            $msg.addClass('is-error').text((response.data && response.data.message) ? response.data.message : 'Error');
                            return;
                        }
                        $msg.addClass('is-success').text(response.data.message);
                        $('#mptbm_new_review_author').val('');
                        $('#mptbm_new_review_content').val('');
                        $('#mptbm_new_review_rating').val('5');
                        mptbmPaintStars($('#mptbm_new_review_star_picker'), 5);

                        mptbmReviewTotal++;
                        $('#mptbm_no_reviews_yet').hide();
                        $('#mptbm_reviews_count_badge').text(mptbmReviewTotal + (mptbmReviewTotal === 1 ? ' <?php echo esc_js( __( 'review', 'ecab-taxi-booking-manager' ) ); ?>' : ' <?php echo esc_js( __( 'reviews', 'ecab-taxi-booking-manager' ) ); ?>'));
                        $('#mptbm_view_reviews_btn_label').text(
                            <?php echo wp_json_encode( __( 'View Reviews', 'ecab-taxi-booking-manager' ) ); ?> + ' (' + mptbmReviewTotal + ')'
                        );
                        // If the list is already open (View Reviews was clicked), show the new
                        // review immediately instead of requiring a page reload to see it.
                        if ($('#mptbm_view_reviews_btn').is(':hidden') && $('#mptbm_view_reviews_btn').data('post-id')) {
                            $('#mptbm_reviews_list_body').prepend(mptbmReviewRowHtml(response.data.review));
                        } else {
                            $('#mptbm_view_reviews_btn').show();
                        }
                    }).fail(function(){
                        $btn.prop('disabled', false);
                        $msg.addClass('is-error').text('Error, please try again.');
                    });
                });
            });
            </script>
        <?php }
        public static function taxi_availability_status( $post_id ){
            $status = MP_Global_Function::get_post_info($post_id, 'mptbm_availability_status', 'available');
            $is_unavailable = $status === 'unavailable';
            $status_text = $is_unavailable ? esc_html__('Unavailable', 'ecab-taxi-booking-manager') : esc_html__('Available', 'ecab-taxi-booking-manager');
            $checked = $is_unavailable ? 'checked' : '';
            $reason = MP_Global_Function::get_post_info($post_id, 'mptbm_availability_reason', 'maintenance');
            $reason_note = MP_Global_Function::get_post_info($post_id, 'mptbm_availability_reason_note', '');
            $reasons = MPTBM_Function::get_availability_reason_labels();
            ?>
            <div class="mptbm_rent_editor_wrapper" id="mptbm_vehicle_availability_section">
                <input type="hidden" name="mptbm_availability_status_field_present" value="1">
                <div class="mptbm_taxi_feature_header mptbm_rent_editor_header">
                    <div class="mptbm_taxi_feature_title_area">
                        <h2 class="mptbm_rent_editor_title"><i class="fas fa-toggle-on"></i> <?php esc_html_e( 'Vehicle Availability', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p class="mptbm_rent_editor_subtitle"><?php esc_html_e( 'Manually mark this vehicle unavailable (e.g. it\'s out on a long trip). While unavailable it will not appear in search results at all, until you switch it back. Only used while Inventory Management\'s Availability Check Mode is set to Manual.', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                    <div class="mptbm_taxi_feature_switch">
                        <span class="mptbm_taxi_feature_switch_text mptbm_availability_status_text" data-available-text="<?php esc_attr_e('Available', 'ecab-taxi-booking-manager'); ?>" data-unavailable-text="<?php esc_attr_e('Unavailable', 'ecab-taxi-booking-manager'); ?>"><?php echo esc_html( $status_text ); ?></span>
                        <label class="mptbm_taxi_feature_toggle">
                            <input type="checkbox" id="mptbm_availability_status" name="mptbm_availability_status" <?php echo esc_attr( $checked ); ?>>
                            <span class="mptbm_taxi_feature_slider"></span>
                        </label>
                    </div>
                </div>

                <div class="mptbm_taxi_advanced_card" id="mptbm_availability_reason_row" style="margin-bottom: 0; display: <?php echo $is_unavailable ? 'block' : 'none'; ?>;">
                    <div class="mptbm_taxi_advanced_card_header">
                        <div class="mptbm_taxi_advanced_title_block">
                            <label class="mptbm_rent_label"><?php esc_html_e( 'Reason', 'ecab-taxi-booking-manager' ); ?></label>
                            <span class="desc"><?php esc_html_e( 'Why is this vehicle unavailable? Shown to admins in the vehicle list.', 'ecab-taxi-booking-manager' ); ?></span>
                        </div>
                        <select id="mptbm_availability_reason" name="mptbm_availability_reason" class="formControl mptbm_taxi_inventory_styled_input">
                            <?php foreach ( $reasons as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $reason, $value ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mptbm_taxi_advanced_card_header" id="mptbm_availability_reason_note_row" style="display: <?php echo $reason === 'other' ? 'flex' : 'none'; ?>;">
                        <div class="mptbm_taxi_advanced_title_block">
                            <label class="mptbm_rent_label"><?php esc_html_e( 'Note', 'ecab-taxi-booking-manager' ); ?></label>
                            <span class="desc"><?php esc_html_e( 'Describe the reason.', 'ecab-taxi-booking-manager' ); ?></span>
                        </div>
                        <input type="text" id="mptbm_availability_reason_note" name="mptbm_availability_reason_note" class="mptbm_taxi_inventory_styled_input" value="<?php echo esc_attr( $reason_note ); ?>" placeholder="<?php esc_attr_e('Waiting on insurance claim', 'ecab-taxi-booking-manager'); ?>">
                    </div>
                </div>
            </div>
            <style>
                .mptbm_taxi_feature_disabled {
                    opacity: 0.5;
                    pointer-events: none;
                }
            </style>
            <script>
            jQuery(function($) {
                $('#mptbm_availability_status').on('change', function() {
                    var $text = $(this).closest('.mptbm_taxi_feature_header').find('.mptbm_availability_status_text');
                    $text.text(this.checked ? $text.data('unavailable-text') : $text.data('available-text'));
                    $('#mptbm_availability_reason_row').toggle(this.checked);
                });
                $('#mptbm_availability_reason').on('change', function() {
                    $('#mptbm_availability_reason_note_row').toggle($(this).val() === 'other');
                });
            });
            </script>
        <?php }
        public static function taxi_inventory_manages( $post_id, $all_features ){
            $display_features = MP_Global_Function::get_post_info($post_id, 'mptbm_enable_inventory', 'no');
            $features_active = $display_features == 'no' ? 'Off' : 'On';
            $display = $display_features == 'no' ? 'none' : 'block';
            $features_checked = $display_features == 'no' ? '' : 'checked';
            $availability_check_mode = MP_Global_Function::get_post_info($post_id, 'mptbm_availability_check_mode', 'automatic');
            ?>
            <div class="mptbm_rent_editor_wrapper">
                <div class="mptbm_taxi_feature_header mptbm_rent_editor_header">
                    <div class="mptbm_taxi_feature_title_area">
                        <h2 class="mptbm_rent_editor_title"><i class="fas fa-warehouse"></i> <?php esc_html_e( 'Inventory Management', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p class="mptbm_rent_editor_subtitle"><?php esc_html_e( 'Track vehicle quantity and control booking intervals to prevent double-bookings.', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                    <div class="mptbm_taxi_feature_switch">

                        <span class="mptbm_taxi_feature_switch_text"><?php echo esc_attr( $features_active );?></span>
                        <label class="mptbm_taxi_feature_toggle">
                            <input type="checkbox" id="mptbm_enable_inventory" name="mptbm_enable_inventory" <?php echo esc_attr( $features_checked );?>>
                            <span class="mptbm_taxi_feature_slider"></span>
                        </label>
                    </div>
                </div>

                <div class="mptbm_taxi_inventory_manage_body" style="display: <?php echo esc_attr( $display );?>">
                    <div class="mptbm_taxi_inventory_settings_card">
                        <div class="mptbm_taxi_advanced_card" style="margin-bottom: 0;">
                            <div class="mptbm_taxi_advanced_card_header">
                                <div class="mptbm_taxi_advanced_title_block">
                                    <label class="mptbm_rent_label"><?php esc_html_e( 'Availability Check Mode', 'ecab-taxi-booking-manager' ); ?></label>
                                    <span class="desc"><?php esc_html_e( 'Quantity/interval availability below always applies to search results. Manual also adds the Vehicle Availability toggle below on top of that. Automatic ignores the toggle and relies on quantity/interval only.', 'ecab-taxi-booking-manager' ); ?></span>
                                </div>
                                <select id="mptbm_availability_check_mode" name="mptbm_availability_check_mode" class="formControl mptbm_taxi_inventory_styled_input">
                                    <option value="automatic" <?php selected( $availability_check_mode, 'automatic' ); ?>><?php esc_html_e( 'Automatic (booking interval)', 'ecab-taxi-booking-manager' ); ?></option>
                                    <option value="manual" <?php selected( $availability_check_mode, 'manual' ); ?>><?php esc_html_e( 'Manual', 'ecab-taxi-booking-manager' ); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="mptbm_taxi_advanced_card" id="mptbm_vehicle_quantity_card" style="margin-bottom: 0;">
                            <div class="mptbm_taxi_advanced_card_header">
                                <div class="mptbm_taxi_advanced_title_block">
                                    <label class="mptbm_rent_label"><?php esc_html_e( 'Vehicle Quantity', 'ecab-taxi-booking-manager' ); ?></label>
                                    <span class="desc"><?php esc_html_e( 'Total number of this vehicle type available for simultaneous bookings.', 'ecab-taxi-booking-manager' ); ?></span>
                                </div>
                                <input
                                        type="number"
                                        id="vehicle-quantity"
                                        name="mptbm_quantity"
                                        min="1"
                                        value="<?php echo esc_attr(MP_Global_Function::get_post_info($post_id, 'mptbm_quantity', 1)); ?>"
                                        class="mptbm_taxi_inventory_styled_input"
                                        placeholder="<?php esc_attr_e('5', 'ecab-taxi-booking-manager'); ?>">
                            </div>
                        </div>

                        <div class="mptbm_taxi_advanced_card" id="mptbm_booking_interval_card" style="margin-bottom: 0;">
                            <div class="mptbm_taxi_advanced_card_header">
                                <div class="mptbm_taxi_advanced_title_block">
                                    <label class="mptbm_rent_label"><?php esc_html_e( 'Booking Interval Time (minutes)', 'ecab-taxi-booking-manager' ); ?></label>
                                    <span class="desc"><?php esc_html_e( 'Minimum gap required between consecutive bookings for this vehicle, to allow turnaround time.', 'ecab-taxi-booking-manager' ); ?></span>
                                </div>
                                <input type="number"
                                       id="interval-time"
                                       name="mptbm_booking_interval_time"
                                       min="0"
                                       value="<?php echo esc_attr(MP_Global_Function::get_post_info($post_id, 'mptbm_booking_interval_time', 0)); ?>"
                                       class="mptbm_taxi_inventory_styled_input"
                                       placeholder="<?php esc_attr_e('30', 'ecab-taxi-booking-manager'); ?>"
                                >
                            </div>
                        </div>

                        <?php self::taxi_availability_status( $post_id ); ?>
                    </div>
                </div>


            </div>
            <script>
            jQuery(function($) {
                function mptbmToggleAvailabilityCheckMode() {
                    var isManual = $('#mptbm_availability_check_mode').val() === 'manual';
                    $('#mptbm_vehicle_availability_section').toggleClass('mptbm_taxi_feature_disabled', !isManual);
                    $('#mptbm_booking_interval_card').toggleClass('mptbm_taxi_feature_disabled', isManual);
                }
                $(document).on('change', '#mptbm_availability_check_mode', mptbmToggleAvailabilityCheckMode);
                mptbmToggleAvailabilityCheckMode();
            });
            </script>
        <?php }
        public static function extra_service_display( $post_id ){

            $display            = MP_Global_Function::get_post_info( $post_id, 'display_mptbm_extra_services', 'on' );
            // No stored reference means the rows live on this vehicle, which is what
            // both the save handler and the booking form already assume - so show
            // "Custom" rather than the blank placeholder, otherwise the select
            // contradicts the rows listed right underneath it.
            $service_id         = (int) get_post_meta( $post_id, 'mptbm_extra_services_id', true) ?: (int) $post_id;
            $active             = $display == 'off' ? 'none' : 'block';
            $all_ex_services_id = MPTBM_Query::query_post_id( 'mptbm_extra_services' );
            ?>
            <div class="mptbm_taxi_ex_service_container">
                <div class="mptbm_taxi_ex_service_body" id="mptbm_taxi_ex_service_body" style="display: <?php echo esc_attr( $active );?>">
                    <div class="mptbm_taxi_ex_service_filter_row">
                        <label for="mptbm_extra_services_id"><?php esc_html_e( 'Source', 'ecab-taxi-booking-manager' ); ?></label>
                        <select class="formControl" id="mptbm_extra_services_id" name="mptbm_extra_services_id">
                            <option value=""><?php esc_html_e( 'Select option', 'ecab-taxi-booking-manager' ); ?></option>
                            <option value="<?php echo esc_attr( $post_id ); ?>" <?php echo esc_attr( $service_id == $post_id ? 'selected' : '' ); ?>><?php esc_html_e( 'Custom', 'ecab-taxi-booking-manager' ); ?></option>
                            <?php if ( sizeof( $all_ex_services_id ) > 0 ) { ?>
                                <?php foreach ( $all_ex_services_id as $ex_services_id ) { ?>
                                    <option value="<?php echo esc_attr( $ex_services_id ); ?>" <?php echo esc_attr( $service_id == $ex_services_id ? 'selected' : '' ); ?>><?php echo esc_html(get_the_title( $ex_services_id )); ?></option>
                                <?php } ?>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="mpStyle mptbm_taxi_ex_service_catalogue">
                        <div class="mptbm_taxi_ex_service_table_toolbar">
                            <div class="mptbm_taxi_ex_service_table_heading">
                                <span>
                                    <strong><?php esc_html_e( 'Services', 'ecab-taxi-booking-manager' ); ?></strong>
                                </span>
                            </div>
                            <span class="mptbm_taxi_ex_service_count" aria-live="polite">
                                <strong id="mptbm_taxi_ex_service_count_value">0</strong>
                            </span>
                        </div>

                        <div class="mptbm_taxi_ex_service_table_shell">
                            <div class="mptbm_taxi_ex_service_table_scroll">
                                <table class="mptbm_taxi_ex_service_table">
                                    <thead>
                                    <tr>
                                        <th class="is-icon"><span class="mptbm_taxi_ex_service_head_label"><?php esc_html_e( 'Icon', 'ecab-taxi-booking-manager' ); ?></span></th>
                                        <th><span class="mptbm_taxi_ex_service_head_label"><?php esc_html_e( 'Name', 'ecab-taxi-booking-manager' ); ?></span></th>
                                        <th><span class="mptbm_taxi_ex_service_head_label"><?php esc_html_e( 'Description', 'ecab-taxi-booking-manager' ); ?></span></th>
                                        <th class="is-price"><span class="mptbm_taxi_ex_service_head_label"><?php esc_html_e( 'Price', 'ecab-taxi-booking-manager' ); ?></span></th>
                                        <th class="is-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'ecab-taxi-booking-manager' ); ?></span></th>
                                    </tr>
                                    </thead>
                                    <tbody id="mptbm_taxi_ex_service_tbody">
                                    <?php
                                        self::extra_service_item( $post_id, $service_id );
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mptbm_taxi_ex_service_footer">
                            <button type="button" id="mptbm_taxi_ex_service_add_btn" class="mptbm_taxi_ex_service_add_btn"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add service', 'ecab-taxi-booking-manager' ); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <script type="text/html" id="mptbm_taxi_ex_service_custom_template">
                <?php self::extra_service_item( $post_id, $post_id ); ?>
            </script>
        <?php }

        public static function extra_service_item( $post_id, $service_id ) {

            if( $service_id && $service_id !== $post_id ){
                $extra_services = MP_Global_Function::get_post_info( $service_id, 'mptbm_extra_service_infos', array() );
            }else{
                $extra_services = MP_Global_Function::get_post_info( $post_id, 'mptbm_extra_service_infos', array() );
            }

            if ( $extra_services && is_array( $extra_services ) && sizeof( $extra_services ) > 0 ) {
                foreach ( $extra_services as $field ) {


    //        $field         = $field ?: array();
            $service_icon  = array_key_exists( 'service_icon', $field ) ? $field['service_icon'] : '';
            $service_image = array_key_exists( 'service_image', $field ) ? $field['service_image'] : '';
            $service_name  = array_key_exists( 'service_name', $field ) ? $field['service_name'] : '';
            $service_price = array_key_exists( 'service_price', $field ) ? $field['service_price'] : '';
            $description   = array_key_exists( 'extra_service_description', $field ) ? $field['extra_service_description'] : '';
            $icon          = $image = "";

            // Handle service_icon (for backward compatibility)
            if ( $service_icon ) {
                if ( preg_match( '/\s/', $service_icon ) ) {
                    $icon = $service_icon;
                } else {
                    $image = $service_icon;
                }
            }

            // Handle separate service_image field
            if ( $service_image ) {
                $image = $service_image;
            }
            ?>
            <tr class="mptbm_taxi_ex_service_row">
                <td class="mptbm_taxi_ex_service_icon_cell" data-label="<?php esc_attr_e( 'Icon', 'ecab-taxi-booking-manager' ); ?>">
                    <?php do_action('mp_add_icon_image', 'service_icon[]', $icon, $image); ?>
                </td>
                <td data-label="<?php esc_attr_e( 'Service name', 'ecab-taxi-booking-manager' ); ?>">
                    <input type="text" name="service_name[]" class="mptbm_taxi_ex_service_input" placeholder="<?php esc_attr_e( 'Child seat', 'ecab-taxi-booking-manager' ); ?>" value="<?php echo esc_attr( $service_name ); ?>">
                    <input type="hidden" name="service_qty_type[]" value="inputbox">
                </td>
                <td data-label="<?php esc_attr_e( 'Customer description', 'ecab-taxi-booking-manager' ); ?>">
                    <textarea class="mptbm_taxi_ex_service_select" name="extra_service_description[]" rows="2" placeholder="<?php esc_attr_e( 'Briefly explain what is included.', 'ecab-taxi-booking-manager' ); ?>"><?php echo esc_html( $description ); ?></textarea>
                </td>
                <td data-label="<?php esc_attr_e( 'Price', 'ecab-taxi-booking-manager' ); ?>">
                    <div class="mptbm_taxi_ex_service_price_field">
                        <span aria-hidden="true">$</span>
                        <input
                            type="number" class="mptbm_taxi_ex_service_input mptbm_center"
                            step="0.01"
                            min="0"
                            name="service_price[]"
                            placeholder="<?php esc_attr_e( '0.00', 'ecab-taxi-booking-manager' ); ?>"
                            value="<?php echo esc_attr( $service_price ); ?>"
                        >
                    </div>
                </td>
                <td class="mptbm_taxi_ex_service_actions" data-label="<?php esc_attr_e( 'Actions', 'ecab-taxi-booking-manager' ); ?>">
                    <button type="button" class="mptbm_taxi_ex_service_btn_drag" title="<?php esc_attr_e( 'Drag to reorder', 'ecab-taxi-booking-manager' ); ?>" aria-label="<?php esc_attr_e( 'Drag to reorder service', 'ecab-taxi-booking-manager' ); ?>"><span class="dashicons dashicons-move"></span></button>
                    <button type="button" class="mptbm_taxi_ex_service_btn_del" title="<?php esc_attr_e( 'Delete service', 'ecab-taxi-booking-manager' ); ?>" aria-label="<?php esc_attr_e( 'Delete service', 'ecab-taxi-booking-manager' ); ?>"><span class="dashicons dashicons-trash"></span></button>
                </td>
            </tr>
            <?php
                }
            }
        }

        public static function initial_base_pricing( $post_id ){

            $initial_price = MP_Global_Function::get_post_info($post_id, 'mptbm_initial_price');
            $min_price = MP_Global_Function::get_post_info($post_id, 'mptbm_min_price');
            $return_min_price = MP_Global_Function::get_post_info($post_id, 'mptbm_min_price_return');
            $return_discount = MP_Global_Function::get_post_info($post_id, 'mptbm_return_discount');

            $waiting_time_check = MPTBM_Function::get_general_settings('taxi_waiting_time', 'enable');
            $waiting_price = MP_Global_Function::get_post_info($post_id, 'mptbm_waiting_price');
            $stop_price = MP_Global_Function::get_post_info($post_id, 'mptbm_stop_price');
            $extra_stop_enabled = MP_Global_Function::get_settings('mptbm_general_settings', 'mptbm_extra_stop_between_pickup_dropoff');

            $display            = MP_Global_Function::get_post_info( $post_id, 'mptbm_display_taxi_base_fare_pricing', 'off' );
            $active             = $display == 'off' ? 'none' : 'block';
            $checked            = $display == 'off' ? '' : 'checked';

            ?>



            <div class="mptbm_rent_editor_wrapper" id="mptbm_taxi_base_fare_toggle_container">
                <div class="mptbm_taxi_ex_service_header mptbm_rent_editor_header">
                    <div class="mptbm_taxi_ex_service_title_group">
                        <h2 class="mptbm_rent_editor_title"><i class="fas fa-money-bill-wave"></i> <?php esc_html_e( 'Base Fare Settings', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p class="mptbm_rent_editor_subtitle"><?php esc_html_e( 'Fixed charge applied at the start of every trip, regardless of distance. Disable to remove it entirely.', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                    <div class="mptbm_taxi_ex_service_toggle_wrapper">
                        <label class="mptbm_taxi_ex_service_switch">
                            <input type="checkbox" id="mptbm_display_taxi_base_fare_pricing" name="mptbm_display_taxi_base_fare_pricing"  class="mptbm_taxi_toggle_trigger" <?php echo esc_attr( $checked );?>>
                            <span class="mptbm_taxi_slider"></span>
                        </label>
                        <span class="mptbm_taxi_ex_service_toggle_label mptbm_display_taxi_base_fare_pricing_level<?php echo esc_attr($display === 'off' ? ' mptbm_taxi_off' : ''); ?>"><?php echo esc_html($display === 'off' ? __('OFF', 'ecab-taxi-booking-manager') : __('ON', 'ecab-taxi-booking-manager')); ?></span>
                    </div>
                </div>

                <div class="mptbm_taxi_ex_service_body" id="mptbm_taxi_base_price_body" style="display: <?php echo esc_attr( $active );?>">
                    <div class="mptbm_taxi_base_price_row">
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Initial / Base Price', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Flat charge at the start of every trip, before distance or time is calculated.', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_initial_price" type="text" value="<?php echo esc_attr( $initial_price );?>" placeholder="<?php esc_attr_e( '5.00', 'ecab-taxi-booking-manager' ); ?>">
                        </div>
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Minimum Price', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'The lowest fare charged when the calculated price is below this threshold.', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_min_price" type="text" value="<?php echo esc_attr( $min_price );?>" placeholder="<?php esc_attr_e( '10.00', 'ecab-taxi-booking-manager' ); ?>">
                        </div>
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Return Minimum Price', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Minimum fare applied specifically on return trip bookings.', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_min_price_return" type="text" value="<?php echo esc_attr( $return_min_price );?>" placeholder="<?php esc_html_e( '40', 'ecab-taxi-booking-manager' ); ?>">
                        </div>
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Return Discount', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Discount applied to return trips. Enter a fixed amount or percentage (e.g. 10 or 10%).', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_return_discount" type="text" value="<?php echo esc_attr( $return_discount );?>" placeholder="<?php esc_html_e( '10 or 10%', 'ecab-taxi-booking-manager' ); ?>">
                        </div>
                        <?php if ($waiting_time_check == 'enable') { ?>
                            <div class="mptbm_taxi_field">
                                <label><?php esc_html_e( 'Waiting Time Price / Hour', 'ecab-taxi-booking-manager' ); ?></label>
                                <p class="mptbm_taxi_help"><?php esc_html_e( 'Hourly rate charged when the driver is waiting for the passenger.', 'ecab-taxi-booking-manager' ); ?></p>
                                <input name="mptbm_waiting_price" type="text" value="<?php echo esc_attr( $waiting_price );?>" placeholder="<?php esc_html_e( '10', 'ecab-taxi-booking-manager' ); ?>">
                            </div>
                        <?php }?>
                        <?php if ($extra_stop_enabled == 'yes') { ?>
                            <div class="mptbm_taxi_field">
                                <label><?php esc_html_e( 'Price Per Extra Stop', 'ecab-taxi-booking-manager' ); ?></label>
                                <p class="mptbm_taxi_help"><?php esc_html_e( 'Flat charge added for each extra stop the customer adds between pickup and drop-off.', 'ecab-taxi-booking-manager' ); ?></p>
                                <input name="mptbm_stop_price" type="text" value="<?php echo esc_attr( $stop_price );?>" placeholder="<?php esc_html_e( '5', 'ecab-taxi-booking-manager' ); ?>">
                            </div>
                        <?php }?>
                    </div>
                </div>
            </div>
        <?php }
        /** Human-readable label for a `mptbm_price_based` value — kept in sync with
         *  the equivalent JS map in mptbm_pricing_model_label() (mptbm_taxi_add_edit.js). */
        public static function price_based_label( $price_based ){
            $labels = array(
                'inclusive'         => esc_html__( 'Combined Pricing', 'ecab-taxi-booking-manager' ),
                'distance'          => esc_html__( 'Distance', 'ecab-taxi-booking-manager' ),
                'duration'          => esc_html__( 'Duration', 'ecab-taxi-booking-manager' ),
                'distance_duration' => esc_html__( 'Distance + Duration', 'ecab-taxi-booking-manager' ),
                'fixed_hourly'      => esc_html__( 'Fixed Hourly', 'ecab-taxi-booking-manager' ),
                'manual'            => esc_html__( 'Manual Routes', 'ecab-taxi-booking-manager' ),
                'fixed_distance'    => esc_html__( 'Fixed with Map', 'ecab-taxi-booking-manager' ),
                'fixed_zone'        => esc_html__( 'Fixed Zone', 'ecab-taxi-booking-manager' ),
            );
            return $labels[ $price_based ] ?? esc_html__( 'Combined Pricing', 'ecab-taxi-booking-manager' );
        }

        public static function pricing_settings( $post_id, $pro_active ){

            $price_based = MP_Global_Function::get_post_info($post_id, 'mptbm_price_based');
            if( empty( $price_based ) ){
                $price_based = 'inclusive';
            }
            $distance_price = MP_Global_Function::get_post_info($post_id, 'mptbm_km_price');
            $time_price = MP_Global_Function::get_post_info($post_id, 'mptbm_hour_price');
            $fixed_map_price = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_map_price');
            $manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);

            $fixed_zone_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_zone_price_info', []);

            $fixed_map_route_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_map_route_price_info', []);
            $fixed_map_area_to_area_route_price_info = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_map_area_to_area_price_info', []);
            $terms_location_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_terms_price_info', []);
            $selected_operation_areas = MP_Global_Function::get_post_info($post_id, 'mptbm_selected_operation_areas', []);
            $location_terms = get_terms(array('taxonomy' => 'locations', 'hide_empty' => false));

            $selected_operation_type = get_post_meta($post_id, 'mptbm_operation_area_type', true);

            $all_zones = array();
            $location_zones = array(); // Geo-located locations (term_*)
            $operation_zones = array(); // Operation areas (post_*)
            $operation_area = array(); // Operation areas (post_id*)

            if (!empty($location_terms) && !is_wp_error($location_terms)) {
                foreach ($location_terms as $term) {
                    if (get_term_meta($term->term_id, 'mptbm_geo_location', true)) {
                        $all_zones['term_' . $term->term_id] = $term->name . ' (Location)';
                        $location_zones['term_' . $term->term_id] = $term->name . ' (Location)';
                    }
                }
            }
            $op_areas = get_posts(array(
                'post_type' => 'mptbm_operate_areas',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'mptbm-operation-type',
                        'value' => 'fixed-operation-area-type'
                    )
                )
            ));
            if (!empty($op_areas)) {
                foreach ($op_areas as $area) {
                    $all_zones['post_' . $area->ID] = $area->post_title . ' (Operation Area)';
                    $operation_zones['post_' . $area->ID] = $area->post_title . ' (Operation Area)';
                    $operation_area[$area->ID] = $area->post_title;
                }
            }


            $merged_location_area = array_merge($operation_zones, $location_zones);


            $operation_area_str = '';
            if( is_array( $selected_operation_areas ) && !empty( $selected_operation_areas ) ){
                $operation_area_str = implode(',', $selected_operation_areas );
            }

            $all_operation_area_infos = MPTBM_Query::query_operation_area_list('mptbm_operate_areas');

            ?>
            <div class="mptbm_taxi_container mptbm_taxi_pricing_wrapper">
                <?php wp_nonce_field('mptbm_price_settings_action', 'mptbm_price_settings_nonce'); ?>
                <input type="hidden" name="mptbm_selected_operation_areas" id="mptbm_selected_operation_areas" value="<?php echo esc_html( $operation_area_str );?>">

                <div class="mptbm_rent_editor_wrapper" style="display: block">
                    <div class="mptbm_rent_editor_header">
                        <div>
                            <h3 class="mptbm_rent_editor_title"><i class="fas fa-tags"></i> <?php esc_html_e( 'Select Pricing Model', 'ecab-taxi-booking-manager' ); ?></h3>
                            <p class="mptbm_rent_editor_subtitle">
                                <?php esc_html_e( 'Choose how trip prices are calculated — by distance, duration, fixed routes, or a combination.', 'ecab-taxi-booking-manager' ); ?>
                            </p>
                        </div>
                    </div>
                    <div class="mptbm_taxi_pricing_tab_grid">
                        <input type="hidden" name="mptbm_price_based" value="<?php echo esc_attr( $price_based );?>" class="mptbm_taxi_pricing_input" >

                        <div class="mptbm_taxi_pricing_tab_item <?php echo esc_attr(($price_based === 'inclusive') ? 'active' : ''); ?>" data-id="mptbm_inclusive">
                            <i class="fas fa-layer-group" aria-hidden="true"></i>

                            <div class="mptbm_taxi_pricing_tab_info">
                                <h4><?php esc_html_e('Combined Pricing Model', 'ecab-taxi-booking-manager'); ?></h4>
                                <span><?php esc_html_e('Multiple Models', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                        </div>

                        <div class="mptbm_taxi_pricing_tab_item <?php echo esc_attr(($price_based === 'distance') ? 'active' : ''); ?>" data-id="mptbm_distance">
                            <i class="fas fa-route" aria-hidden="true"></i>

                            <div class="mptbm_taxi_pricing_tab_info">
                                <h4><?php esc_html_e('Distance', 'ecab-taxi-booking-manager'); ?></h4>
                                <span><?php esc_html_e('Based on KM', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                        </div>

                        <div class="mptbm_taxi_pricing_tab_item <?php echo esc_attr(($price_based === 'duration') ? 'active' : ''); ?>" data-id="mptbm_row_duration">
                            <i class="fas fa-clock" aria-hidden="true"></i>

                            <div class="mptbm_taxi_pricing_tab_info">
                                <h4><?php esc_html_e('Duration', 'ecab-taxi-booking-manager'); ?></h4>
                                <span><?php esc_html_e('Based on Time', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                        </div>

                        <div class="mptbm_taxi_pricing_tab_item <?php echo esc_attr(($price_based === 'distance_duration') ? 'active' : ''); ?>" data-id="mptbm_row_dist_dur">
                            <i class="fas fa-road" aria-hidden="true"></i>

                            <div class="mptbm_taxi_pricing_tab_info">
                                <h4><?php esc_html_e('Distance + Duration', 'ecab-taxi-booking-manager'); ?></h4>
                                <span><?php esc_html_e('Based on KM and Time', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                        </div>

                        <div class="mptbm_taxi_pricing_tab_item <?php echo esc_attr(($price_based === 'fixed_hourly') ? 'active' : ''); ?>" data-id="mptbm_row_hourly">
                            <i class="fas fa-business-time" aria-hidden="true"></i>

                            <div class="mptbm_taxi_pricing_tab_info">
                                <h4><?php esc_html_e('Fixed Hourly', 'ecab-taxi-booking-manager'); ?></h4>
                                <span><?php esc_html_e('Based on Hourly Rate', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                        </div>

                        <div class="mptbm_taxi_pricing_tab_item <?php echo esc_attr(($price_based === 'manual') ? 'active' : ''); ?>" data-id="mptbm_row_manual">
                            <i class="fas fa-map-signs" aria-hidden="true"></i>

                            <div class="mptbm_taxi_pricing_tab_info">
                                <h4><?php esc_html_e('Manual Routes', 'ecab-taxi-booking-manager'); ?></h4>
                                <span><?php esc_html_e('Based on Manual Routes', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                        </div>

                        <?php
                        $pricing_tab = 'mptbm_taxi_pricing_tab_item';

                        if( !$pro_active ){
                            $pricing_tab = 'mptbm_taxi_pricing_tab_item mptbm_taxi_pricing_tab_item_pro';
                        }
                        ?>
                        <div class=" <?php echo esc_attr( $pricing_tab );?>
                        <?php echo esc_attr(($price_based === 'fixed_distance' || $price_based === 'fixed_zone' ) ? 'active' : ''); ?>"
                             data-id="mptbm_row_operation_area"
                             style="display: none"
                        >
                            <i class="fas fa-draw-polygon" aria-hidden="true"></i>

                            <div class="mptbm_taxi_pricing_tab_info">
                                <h4><?php esc_html_e('Operation Area', 'ecab-taxi-booking-manager'); ?></h4>
                                <span><?php esc_html_e('Based on Operation Area', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                        </div>


                    </div>
                </div>
                <div class="mptbm_rent_editor_wrapper">
                    <div class="mptbm_rent_editor_header">
                        <div>
                            <h3 class="mptbm_rent_editor_title"><i class="fas fa-sliders-h"></i> <?php esc_html_e( 'Configure Pricing Rules', 'ecab-taxi-booking-manager' ); ?></h3>
                            <p class="mptbm_rent_editor_subtitle"><?php esc_html_e( 'Set the rates and route overrides for the selected pricing model above.', 'ecab-taxi-booking-manager' ); ?></p>
                        </div>
                        <span class="mptbm_pricing_model_badge" id="mptbm_selected_pricing_model_label"><?php echo self::price_based_label( $price_based ); ?></span>
                    </div>
                    <div class="mptbm_taxi_pricing_group" >
                        <div class="mptbm_taxi_pricing_row_content">

                            <div class="mptbm_taxi_pricing_field"
                                 id="mptbm_distance_price"
                                 style="display: <?php echo ( $price_based === 'inclusive' || $price_based === 'distance' || $price_based === 'distance_duration' || $price_based === 'fixed_distance' ) ? 'block' : 'none'; ?>">
                                <label><?php printf( esc_html__( 'Price per %s', 'ecab-taxi-booking-manager' ), esc_html( MPTBM_Function::distance_unit_label() ) ); ?></label>
                                <input name="mptbm_km_price" value="<?php echo esc_attr( $distance_price );?>" type="text" placeholder="1.00">

                            </div>

                            <div class="mptbm_taxi_pricing_field"
                                 id="mptbm_fixed_pricing"
                                 style="display: <?php echo ( $price_based === 'fixed_distance'  ) ? 'block' : 'none'; ?>">
                                <label><?php esc_html_e( 'Fixed with map price', 'ecab-taxi-booking-manager' ); ?> </label>
                                <span><?php esc_html_e( 'Set the fixed price for map-based trips', 'ecab-taxi-booking-manager' ); ?></span>
                                <input name="mptbm_fixed_map_price" value="<?php echo esc_attr( $fixed_map_price );?>" type="text" placeholder="<?php esc_html_e('EX: 10', 'ecab-taxi-booking-manager'); ?>">
                            </div>

                            <div class="mptbm_taxi_pricing_field"
                                 id="mptbm_price_per_hour"
                                 style="display: <?php echo ($price_based === 'inclusive' || $price_based === 'duration' || $price_based === 'distance_duration' || $price_based === 'fixed_hourly' || $price_based === 'fixed_distance' ) ? 'block' : 'none'; ?>">
                                <label><?php esc_html_e( 'Price per Hour (Price/Hour)', 'ecab-taxi-booking-manager' ); ?></label>
                                <input name="mptbm_hour_price" value="<?php echo esc_attr( $time_price );?>" type="text" placeholder="0.20">
                            </div>


                            <?php
                            $routes_and_fixed_fare = 'none';
                            if( $price_based === 'inclusive' ){
                                $routes_and_fixed_fare = 'flex';
                            }
                            $inclusive_manual_locations = MP_Global_Function::get_post_info( $post_id, 'mptbm_inclusive_manual_locations', 'off' );
                            $checked = $inclusive_manual_locations === 'on' ? 'checked' : '';
                            ?>
                            <div class="mptbm_manual_routes_and_fixed_fare_overrides" id="mptbm_manual_routes_and_fixed_fare_overrides" style="display: <?php echo esc_attr( $routes_and_fixed_fare );?>">
                                <div class="mptbm_taxi_ex_service_title_group">
                                    <h2 class="mptbm_rent_editor_title"><i class="fas fa-route"></i> <?php esc_html_e( 'Manual Pricing', 'ecab-taxi-booking-manager' ); ?></h2>
                                    <p class="mptbm_taxi_ex_service_subtitle"><?php esc_html_e( 'Manage manual routes and fixed fare overrides.', 'ecab-taxi-booking-manager' ); ?></p>
                                </div>
                                <div class="manual_routes_and_fixed_fare_toggle_wrapper">
                                    <label class="mptbm_taxi_ex_service_switch">
                                        <input type="checkbox" id="mptbm_taxi_inclusive_manual_locations" name="mptbm_inclusive_manual_locations" <?php echo esc_attr($checked); ?>>
                                        <span class="mptbm_taxi_ex_service_slider"></span>
                                    </label>
                                    <span class="mptbm_manual_routes_and_fixed_fare_toggle_label"><?php esc_html_e( 'ON', 'ecab-taxi-booking-manager' ); ?></span>
                                </div>
                            </div>

                            <?php
                            $manual_pricing_set = 'none';
                            if( $price_based === 'inclusive' && $inclusive_manual_locations === 'on' ){
                                $manual_pricing_set = 'block';
                            }

                            if( $price_based === 'manual' ){
                                $manual_pricing_set = 'block';
                            }
                            ?>
                            <div class="mptbm_taxi_pricing_field1"
                                 id="mptbm_manual_routes"
                                 style="display: <?php echo esc_attr( $manual_pricing_set ) ; ?>">
                                <div class="mptbm_taxi_pricing_row_head mptbm_manual_routes_head">
                                    <span class="mptbm_taxi_pricing_label"><i class="fas fa-route"></i> <?php esc_html_e( 'Manual Routes', 'ecab-taxi-booking-manager' ); ?></span>
                                    <?php
                                    $manual_route_map = MP_Global_Function::get_post_info( $post_id, 'mptbm_manual_route_map', 'on' );
                                    $manual_route_map_checked = $manual_route_map !== 'off' ? 'checked' : '';
                                    ?>
                                    <div class="mptbm_manual_route_map_setting">
                                        <div class="mptbm_manual_route_map_copy">
                                            <strong><?php esc_html_e( 'Frontend route map', 'ecab-taxi-booking-manager' ); ?></strong>
                                            <small><?php esc_html_e( 'Show every configured route location as a labeled map marker.', 'ecab-taxi-booking-manager' ); ?></small>
                                        </div>
                                        <div class="mptbm_taxi_ex_service_toggle_wrapper">
                                            <input type="hidden" name="mptbm_manual_route_map_field_present" value="1">
                                            <label class="mptbm_taxi_ex_service_switch">
                                                <input type="checkbox" id="mptbm_manual_route_map" name="mptbm_manual_route_map" <?php echo esc_attr( $manual_route_map_checked ); ?>>
                                                <span class="mptbm_taxi_ex_service_slider"></span>
                                            </label>
                                            <span class="mptbm_taxi_ex_service_toggle_label"><?php echo $manual_route_map !== 'off' ? esc_html__( 'ON', 'ecab-taxi-booking-manager' ) : esc_html__( 'OFF', 'ecab-taxi-booking-manager' ); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mptbm_taxi_pricing_field">
                                    <div class="mptbm_taxi_pricing_info_alert">
                                        <i class="far fa-lightbulb"></i>
                                        <span><?php esc_html_e( 'Routes not covered here fall back to the active pricing model.', 'ecab-taxi-booking-manager' ); ?></span>
                                    </div>

                                    <div class="mptbm_taxi_pricing_manual_list">
                                        <?php self::render_location_price_rows( $terms_location_prices, $location_terms );?>
                                    </div>

                                    <div class="mptbm_taxi_pricing_add_action">
                                        <button type="button" class="mptbm_taxi_pricing_add_route_full_btn">+ <?php esc_html_e( 'Add Route', 'ecab-taxi-booking-manager' ); ?></button>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>


                <div class="mptbm_rent_editor_wrapper">
                    <?php
                    $operation_area_pricing_display            = MP_Global_Function::get_post_info( $post_id, 'mptbm_display_operation_area_pricing', 'off' );
                    $operation_area_pricing_active             = $operation_area_pricing_display == 'off' ? 'none' : 'block';
                    $operation_area_pricing_checked            = $operation_area_pricing_display == 'off' ? '' : 'checked';
                    ?>
                    <div class="mptbm_taxi_ex_service_header mptbm_rent_editor_header">
                        <div class="mptbm_taxi_ex_service_title_group">
                            <h3 class="mptbm_rent_editor_title"><i class="fas fa-map-marked-alt"></i> <?php esc_html_e( 'Operation Area', 'ecab-taxi-booking-manager' ); ?></h3>
                            <p class="mptbm_rent_editor_subtitle">
                                <?php esc_html_e( 'Select operation area pricing rule for this taxi model.', 'ecab-taxi-booking-manager' ); ?>
                            </p>
                        </div>

                        <div class="mptbm_taxi_ex_service_toggle_wrapper">
                            <label class="mptbm_taxi_ex_service_switch">
                                <input type="checkbox"
                                       id="mptbm_display_operation_area_pricing"
                                       name="mptbm_display_operation_area_pricing"
                                       class="mptbm_taxi_toggle_trigger"
                                    <?php echo esc_attr( $operation_area_pricing_checked ); ?>>
                                <span class="mptbm_taxi_slider"></span>
                            </label>
                            <span class="mptbm_taxi_ex_service_toggle_label">
                <?php esc_html_e( 'ON', 'ecab-taxi-booking-manager' ); ?>
            </span>
                        </div>
                    </div>

                    <div class="mptbm_taxi_pricing_group" id="mptbm_taxi_operation_araea_pricing_group" style="display: <?php echo esc_attr( $operation_area_pricing_active );?>" >
                        <div class="mptbm_taxi_pricing_row_content">
                            <?php
                            self::manage_operation_area_pricing( $post_id, $price_based, $selected_operation_type, $all_operation_area_infos, $selected_operation_areas, $operation_area, $fixed_map_route_prices, $fixed_map_area_to_area_route_price_info, $merged_location_area, $location_zones, $fixed_zone_prices, $operation_zones );
                            ?>

                        </div>
                    </div>

                </div>

                <div class="mptbm_pricing_rules_wrapper">
                    <?php
                    self::pricing_rules_display( $price_based );
                    self::shortcode_description( $price_based );
                    ?>
                </div>

            <?php  if ( class_exists('Distance_Tier_Pricing_Addon') || function_exists('distance_tier_pricing_addon_init')) {?>
                <div class="mptbm_distance_tier_pricing_settings_holder mpStyle">
                    <?php do_action('add_mptbm_settings_tab_content_tier', $post_id); ?>
                </div>
            <?php }
            ?>

            <?php if (class_exists('Taxi_Peak_Hour_Pricing_Addon') || function_exists('taxi_peak_hour_pricing_addon_init')) { ?>
                <div class="mptbm_taxi_peak_hour_pricing_addon mpStyle">
                    <?php do_action('add_mptbm_settings_pick_hour_content', $post_id); ?>
                </div>
            <?php }?>

            </div>

        <?php }

        public static function pricing_rules_display( $price_based ){ ?>
            <div class="mptbm_pricing_rules_grid" id="mptbm_pricing_rules_grid">

                <?php
                if( $price_based === 'inclusive' ){
                    ?>
                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Combined Pricing Model', 'ecab-taxi-booking-manager' ); ?></h4>
                        <p><?php esc_html_e( 'Price is calculated using both time and distance.', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php printf( esc_html__( '(Hourly Rate × Duration) + (%s Rate × Distance)', 'ecab-taxi-booking-manager' ), esc_html( MPTBM_Function::distance_unit_label() ) ); ?>
                        </div>
                    </div>
                <?php }
                if( $price_based === 'distance' ){
                    ?>

                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Distance Based Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                        <p><?php esc_html_e( 'Only distance is used for calculation.', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php printf( esc_html__( '%s Rate × Distance', 'ecab-taxi-booking-manager' ), esc_html( MPTBM_Function::distance_unit_label() ) ); ?>
                        </div>
                    </div>
                <?php }
                if( $price_based === 'duration'){
                    ?>
                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Duration Based Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                        <p><?php esc_html_e( 'Only travel time is considered.', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php esc_html_e( 'Hourly Rate × Duration', 'ecab-taxi-booking-manager' ); ?>
                        </div>
                    </div>
                <?php }
                if( $price_based === 'distance_duration' ){
                    ?>
                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Distance + Duration Based Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                        <p><?php esc_html_e( 'Combines both distance and time pricing.', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php printf( esc_html__( '(Hourly Rate × Duration) + (%s Rate × Distance)', 'ecab-taxi-booking-manager' ), esc_html( MPTBM_Function::distance_unit_label() ) ); ?>
                        </div>
                    </div>
                <?php }
                if( $price_based === 'fixed_hourly' ){
                    ?>

                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Fixed Hourly Based Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                        <p><?php esc_html_e( 'Fixed hourly pricing applied.', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php esc_html_e( 'Hour Rate × Fixed Time', 'ecab-taxi-booking-manager' ); ?>
                        </div>
                    </div>
                <?php } if( $price_based === 'fixed_distance' ){ ?>
                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Fixed Map Zone-based Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                        <p><?php esc_html_e( 'Zone-based fixed pricing or fallback calculation.', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php esc_html_e( 'If matched → fixed route price is applied
                                        If not matched → fallback calculation:
                                        Hourly + Distance pricing OR
                                        Operation area pricing override

                                        Formula (fallback):

                                        (Hour Price × Duration) + (KM Price × Distance)', 'ecab-taxi-booking-manager' ); ?><?php esc_html_e( 'First checks predefined zone route price', 'ecab-taxi-booking-manager' ); ?>
                        </div>

                    </div>
                <?php } if( $price_based === 'fixed_zone' ){?>
                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Fixed Zone Based Pricing', 'ecab-taxi-booking-manager' ); ?> </h4>
                        <p><?php esc_html_e( 'Price depends on selected start & end zones:', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php esc_html_e( 'If pickup and dropoff zones match predefined route → fixed price applied
                                        Otherwise geo-zone matching is used
                                        Different logic for pickup vs dropoff mode
                                        Result:
                                        Fixed route price if matched', 'ecab-taxi-booking-manager' ); ?>
                        </div>
                    </div>
                <?php }
                if( $price_based === 'manual' ){
                    ?>
                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Manual Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                        <p><?php esc_html_e( 'Admin-defined exact route pricing.', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php esc_html_e( 'Fixed Route Price', 'ecab-taxi-booking-manager' ); ?>
                        </div>
                    </div>
                <?php }
                ?>
            </div>
        <?php }

        public static function manage_operation_area_pricing( $post_id, $price_based, $selected_operation_type, $all_operation_area_infos, $selected_operation_areas, $operation_area, $fixed_map_route_prices, $fixed_map_area_to_area_route_price_info, $merged_location_area, $location_zones, $fixed_zone_prices, $operation_zones ){
            // Operation Area pricing is a PRO feature — when Pro isn't active, the
            // type/area picker below is rendered disabled + blurred with a lock overlay
            // instead of being left fully interactive (matches the tab-level lock already
            // used for the "Operation Area" pricing tab via mptbm_taxi_pricing_tab_item_pro).
            $is_pro = class_exists( 'MPTBM_Dependencies_Pro' );
            $is_operation_areas = 0;
            if( is_array( $selected_operation_areas ) && !empty( $selected_operation_areas ) ){
                $is_operation_areas = 1;
            }

            $area_option = '';
            if( empty( $selected_operation_type ) ){
                $area_option = 'none';
            }else{
                if( $selected_operation_type === 'geo-fence-operation-area-type' ){
                    $area_option = 'none';
                }else{
                    if( $price_based === 'fixed_distance' || $price_based === 'fixed_zone' ){
                        $area_option = '';
                    }else{
                        if( !empty( $selected_operation_areas ) ){
                            $area_option = '';
                        }else{
                            $area_option = 'none';
                        }

                    }
                }
            }

            ?>
            <div class="mptbm_taxi_pricing_field1"
                 id="mptbm_operation_area"

            >

                <input type="hidden" id="mptbm_is_selected_operation_area" name="mptbm_is_selected_operation_area" value="<?php echo esc_attr( $is_operation_areas );?>">
                <div class="mptbm_pro_lock<?php echo $is_pro ? '' : ' is-locked'; ?>">
                <?php if ( ! $is_pro ) : ?>
                    <div class="mptbm_pro_lock_overlay">
                        <span class="mptbm_pro_lock_badge"><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'PRO feature', 'ecab-taxi-booking-manager' ); ?></span>
                        <p><?php esc_html_e( 'Operation area based pricing (fixed zone / fixed map) is available in the PRO version.', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                <?php endif; ?>
                <div class="mptbm_pro_lock_content">
                <div class="mptbm_operation_area_type_holder">
                    <div class="mptbm_settings_area " id="mptbm_operation_area_settings" >

                        <section class="mptbm-oa-section">

<!--                            <p class="mptbm-oa-label">--><?php //esc_html_e('Configuration', 'ecab-taxi-booking-manager'); ?><!--</p>-->
                            <p class="mptbm-oa-title"><?php esc_html_e('Choose the type of operation area', 'ecab-taxi-booking-manager'); ?></p>

                            <div class="mptbm-oa-grid">

                                <label class="mptbm-oa-card">
                                    <input type="radio" name="mptbm_operation_area_type" value=""
                                        <?php checked( $selected_operation_type, '' ); disabled( ! $is_pro ); ?>>
                                    <div class="mptbm-oa-card-inner">
                                        <div class="mptbm-oa-header">
                                            <span class="dashicons dashicons-location mptbm-oa-icon"></span>
                                            <div class="mptbm-oa-name"><?php esc_html_e( 'Fixed Zone Operation Area', 'ecab-taxi-booking-manager' ); ?></div>
                                        </div>
                                        <div class="mptbm-oa-desc"><?php esc_html_e( 'Pickup or drop-off is matched against a saved zone for one flat price.', 'ecab-taxi-booking-manager' ); ?></div>
                                        <div class="mptbm-oa-select-row">
                                            <div class="mptbm-oa-dot"><div class="mptbm-oa-dot-inner"></div></div>
                                            <span class="mptbm-oa-dot-label mptbm-oa-lbl-off"><?php esc_html_e( 'Click to select', 'ecab-taxi-booking-manager' ); ?></span>
                                            <span class="mptbm-oa-dot-label mptbm-oa-lbl-on"><?php esc_html_e( 'Selected', 'ecab-taxi-booking-manager' ); ?></span>
                                        </div>
                                    </div>
                                </label>

                                <label class="mptbm-oa-card">
                                    <input type="radio" name="mptbm_operation_area_type" value="fixed-operation-area-type"
                                        <?php checked( $selected_operation_type, 'fixed-operation-area-type' ); disabled( ! $is_pro ); ?>>
                                    <div class="mptbm-oa-card-inner">
                                        <div class="mptbm-oa-header">
                                            <span class="dashicons dashicons-location mptbm-oa-icon"></span>
                                            <div class="mptbm-oa-name"><?php esc_html_e( 'Fixed operation area (Both In)', 'ecab-taxi-booking-manager' ); ?></div>
                                        </div>
                                        <div class="mptbm-oa-desc"><?php esc_html_e( 'Both pickup and dropoff must be inside the zone.', 'ecab-taxi-booking-manager' ); ?></div>
                                        <div class="mptbm-oa-select-row">
                                            <div class="mptbm-oa-dot"><div class="mptbm-oa-dot-inner"></div></div>
                                            <span class="mptbm-oa-dot-label mptbm-oa-lbl-off"><?php esc_html_e( 'Click to select', 'ecab-taxi-booking-manager' ); ?></span>
                                            <span class="mptbm-oa-dot-label mptbm-oa-lbl-on"><?php esc_html_e( 'Selected', 'ecab-taxi-booking-manager' ); ?></span>
                                        </div>
                                    </div>
                                </label>

                                <label class="mptbm-oa-card">
                                    <input type="radio" name="mptbm_operation_area_type" value="fixed-map-operation-area-type"
                                        <?php checked( $selected_operation_type, 'fixed-map-operation-area-type' ); disabled( ! $is_pro ); ?>>
                                    <div class="mptbm-oa-card-inner">
                                        <div class="mptbm-oa-header">
                                            <span class="dashicons dashicons-marker mptbm-oa-icon"></span>
                                            <div class="mptbm-oa-name"><?php esc_html_e( 'Fixed Map Operation Area (Pickup In)', 'ecab-taxi-booking-manager' ); ?></div>
                                        </div>
                                        <div class="mptbm-oa-desc"><?php esc_html_e( 'Only the pickup point must be inside the zone.', 'ecab-taxi-booking-manager' ); ?></div>
                                        <div class="mptbm-oa-select-row">
                                            <div class="mptbm-oa-dot"><div class="mptbm-oa-dot-inner"></div></div>
                                            <span class="mptbm-oa-dot-label mptbm-oa-lbl-off"><?php esc_html_e( 'Click to select', 'ecab-taxi-booking-manager' ); ?></span>
                                            <span class="mptbm-oa-dot-label mptbm-oa-lbl-on"><?php esc_html_e( 'Selected', 'ecab-taxi-booking-manager' ); ?></span>
                                        </div>
                                    </div>
                                </label>

                                <label class="mptbm-oa-card">
                                    <input type="radio" name="mptbm_operation_area_type" value="geo-fence-operation-area-type"
                                        <?php checked( $selected_operation_type, 'geo-fence-operation-area-type' ); disabled( ! $is_pro ); ?>>
                                    <div class="mptbm-oa-card-inner">
                                        <div class="mptbm-oa-header">
                                            <span class="dashicons dashicons-admin-site-alt3 mptbm-oa-icon"></span>
                                            <div class="mptbm-oa-name"><?php esc_html_e( 'Geo fence area', 'ecab-taxi-booking-manager' ); ?></div>
                                        </div>
                                        <div class="mptbm-oa-desc"><?php esc_html_e( 'Draw a custom boundary to define your service region.', 'ecab-taxi-booking-manager' ); ?></div>
                                        <div class="mptbm-oa-select-row">
                                            <div class="mptbm-oa-dot"><div class="mptbm-oa-dot-inner"></div></div>
                                            <span class="mptbm-oa-dot-label mptbm-oa-lbl-off"><?php esc_html_e( 'Click to select', 'ecab-taxi-booking-manager' ); ?></span>
                                            <span class="mptbm-oa-dot-label mptbm-oa-lbl-on"><?php esc_html_e( 'Selected', 'ecab-taxi-booking-manager' ); ?></span>
                                        </div>
                                    </div>
                                </label>

                                <label class="mptbm-oa-card">
                                    <input type="radio" name="mptbm_operation_area_type" value="geo-matched-operation-area-type"
                                        <?php checked( $selected_operation_type, 'geo-matched-operation-area-type' ); disabled( ! $is_pro ); ?>>
                                    <div class="mptbm-oa-card-inner">
                                        <div class="mptbm-oa-header">
                                            <span class="dashicons dashicons-networking mptbm-oa-icon"></span>
                                            <div class="mptbm-oa-name"><?php esc_html_e( 'Geo-matched area', 'ecab-taxi-booking-manager' ); ?></div>
                                        </div>
                                        <div class="mptbm-oa-desc"><?php esc_html_e( 'Match service by overlapping geographic regions.', 'ecab-taxi-booking-manager' ); ?></div>
                                        <div class="mptbm-oa-select-row">
                                            <div class="mptbm-oa-dot"><div class="mptbm-oa-dot-inner"></div></div>
                                            <span class="mptbm-oa-dot-label mptbm-oa-lbl-off"><?php esc_html_e( 'Click to select', 'ecab-taxi-booking-manager' ); ?></span>
                                            <span class="mptbm-oa-dot-label mptbm-oa-lbl-on"><?php esc_html_e( 'Selected', 'ecab-taxi-booking-manager' ); ?></span>
                                        </div>
                                    </div>
                                </label>

                            </div>

                        </section>

                    </div>
                    <div class="mptbm_taxi_pricing_selection_group">
                        <?php
                        $show_area = '';
                        $show_area_create = 'none';

                        if( empty( $selected_operation_type ) ){
                            $show_area = 'none';
                        }

                        if( empty( $all_operation_area_infos ) ){
                            $show_area_create = '';
                        }

                        ?>
                        <label><?php esc_html_e( 'SELECT OPERATION AREAS —', 'ecab-taxi-booking-manager' ); ?><span id="mptbm_single_mul_operation_area"> <?php esc_html_e( 'multiple allowed', 'ecab-taxi-booking-manager' ); ?></span></label>

                        <div class="mptbm_taxi_pricing_area_pills" style="display: <?php echo esc_attr( $show_area )?>">
                            <?php
                            foreach ( $all_operation_area_infos as $key => $area_info ):
                                $id = $area_info['post_id'];
                                ?>

                                <?php
                                $is_selected = in_array($id, $selected_operation_areas);

                                $is_geo_fence = 0;
                                $is_geo_fence_display = 'block';
                                if ( $area_info['operation_type'] == 'geo-fence-operation-area-type') {
                                    $is_geo_fence = 1;
                                }
                                ?>

                                <button
                                        type="button"
                                        class="mptbm_taxi_pricing_pill <?php echo $is_selected ? 'selected' : ''; ?>"
                                        data-id="<?php echo esc_attr( $id ); ?>"
                                        data-geo-fance = "<?php echo esc_attr( $is_geo_fence );?>"
                                        style="display: <?php echo esc_attr( $is_geo_fence_display );?>"
                                >
                                    <?php if ($is_selected): ?>
                                        <i class="fas fa-check"></i>
                                    <?php endif; ?>
                                    <?php echo esc_attr( get_the_title($area_info['post_id'] ) ); ?>
                                </button>

                            <?php endforeach; ?>

                        </div>

                        <?php if( $is_operation_areas === 0 ){?>
                            <div class="mptbm_empty_selected_area">
                                <span class="mptbm_empty_selected_area_icon" aria-hidden="true">
                                    <span class="dashicons dashicons-location-alt"></span>
                                </span>
                                <span class="mptbm_empty_selected_area_content">
                                    <strong><?php esc_html_e( 'No operation area selected', 'ecab-taxi-booking-manager' ); ?></strong>
                                    <span class="mptbm_empty_selected_area_text"><?php esc_html_e( 'Select at least one operation area and save the settings before configuring fixed map or fixed zone pricing.', 'ecab-taxi-booking-manager' ); ?></span>
                                </span>
                            </div>
                        <?php }

                        ?>

                        <div class="mptbm_operation_area_create_link" style="display: <?php echo esc_attr( $show_area_create );?>">
                            <a href="<?php echo admin_url('edit.php?post_type=mptbm_operate_areas'); ?>" class="mptbm_create_area_btn">
                                + <?php esc_html_e( 'Create Operation Area', 'ecab-taxi-booking-manager' ); ?>
                            </a>
                        </div>
                    </div>
                </div>
                </div><!-- .mptbm_pro_lock_content -->
                </div><!-- .mptbm_pro_lock -->

                <div class="mptbm_operation_area_based"
                     id="mptbm_operation_area_based"
                     style=" display: <?php echo esc_attr( $area_option );?>"
                >
                    <div class="mptbm_operation_area_tab_holder">
                        <div class="mptbm_operation_area_based_pricing">
                            <h3 class="mptbm_rent_editor_title"><i class="fas fa-map"></i> <?php esc_html_e( 'Select Operation Area Based Pricing Model', 'ecab-taxi-booking-manager' ); ?></h3>
                        </div>

                        <div class="" style="display: flex; gap: 10px">


                            <div class="mptbm_taxi_pricing_tab_item mptbm_taxi_pricing_tab_item_area <?php echo esc_attr(($price_based === 'fixed_distance') ? 'active' : ''); ?>" id="mptbm_taxi_pricing_fixed_map" data-id="mptbm_row_operation_area">
                                <i class="fas fa-layer-group" aria-hidden="true"></i>

                                <div class="mptbm_taxi_pricing_tab_info">
                                    <h4><?php esc_html_e('Fixed With Map', 'ecab-taxi-booking-manager'); ?></h4>
                                    <span class="tab-title"><?php esc_html_e('Fixed With Map', 'ecab-taxi-booking-manager' ); ?></span>
                                </div>
                            </div>
                            <div class="mptbm_taxi_pricing_tab_item mptbm_taxi_pricing_tab_item_area <?php echo esc_attr(($price_based === 'fixed_zone') ? 'active' : ''); ?>" id="mptbm_taxi_pricing_fixed_zone" data-id="mptbm_row_zone">
                                <i class="fas fa-layer-group" aria-hidden="true"></i>

                                <div class="mptbm_taxi_pricing_tab_info">
                                    <h4><?php esc_html_e('Fixed Zone', 'ecab-taxi-booking-manager'); ?></h4>
                                    <span class="tab-title"><?php esc_html_e('Fixed With Map', 'ecab-taxi-booking-manager' ); ?></span>
                                </div>
                            </div>


                            <!--<div class="mptbm_taxi_pricing_tab_item_area <?php /*echo esc_attr( ( $price_based === 'fixed_distance' ) ? 'active' : '' ); */?>" id="mptbm_taxi_pricing_fixed_map" data-id="mptbm_row_operation_area">
                                <span class="tab-icon">🚕</span>
                                <span class="tab-title"><?php /*esc_html_e('Fixed With Map', 'ecab-taxi-booking-manager' ); */?></span>
                            </div>
                            <div class="mptbm_taxi_pricing_tab_item_area <?php /*echo esc_attr( ( $price_based === 'fixed_zone' ) ? 'active' : '' ); */?>" id="mptbm_taxi_pricing_fixed_zone" data-id="mptbm_row_zone">
                                <span class="tab-icon">📍</span>
                                <span class="tab-title"><?php /*esc_html_e('Fixed Zone', 'ecab-taxi-booking-manager'); */?></span>
                            </div>-->

                        </div>

                    </div>
                    <div class="mptbm_taxi_pricing_field">


                        <?php
                        $area_based_pricing = 'none';
                        if( !empty( $all_operation_area_infos ) && !empty( $selected_operation_areas ) ){
                            $area_based_pricing = '';
                        }

                        $operation_area_fixed_map_type = MP_Global_Function::get_post_info($post_id, 'mptbm_operation_area_fixed_map_type', 'zone_to_location');
                        ?>

                        <div class="mptbm_taxi_area_pricing">
                            <?php
                            self::render_fixed_with_map_area_based_pricing( $post_id, $operation_zones, $price_based );
                            ?>
                            <div class="mptbm_taxi_pricing_sub_section"
                                 id="mptbm_fixed_map_area_pricing"
                                 style="display: <?php echo ( $price_based === 'fixed_distance' && !empty( $selected_operation_areas ) ) ? 'block' : 'none'; ?>">

                                <div class="mptbm_taxi_pricing_sub_header">
                                    <h4><?php esc_html_e( 'Fixed Map Route Overrides', 'ecab-taxi-booking-manager' ); ?></h4>
                                    <p><?php esc_html_e( 'Define fixed prices for specific routes when using "Fixed with Map" mode.', 'ecab-taxi-booking-manager' ); ?></p>
                                </div>

                                <div class="mptbm_operation_area_fixed_map_type_container"  style="display: <?php echo esc_attr( $area_based_pricing );?>" >

                                    <div class="mptbm_operation_area_fixed_map_type_holder">

                                        <input type="hidden" name="mptbm_operation_area_fixed_map_type" value="">
                                        <div class="mptbm_operation_area_fixed_map_type_tabs">
                                            <div class="mptbm_operation_area_fixed_map_type_tab <?php echo ( $operation_area_fixed_map_type === 'zone_to_location' || empty( $operation_area_fixed_map_type ) ) ? 'active' : ''; ?>"
                                                 data-operation-area-type="zone_to_location">
                                                <span class="dashicons dashicons-location-alt"></span>
                                                <span><?php esc_html_e( 'Zone To Location', 'ecab-taxi-booking-manager' ); ?></span>
                                            </div>
                                            <div class="mptbm_operation_area_fixed_map_type_tab <?php echo ( $operation_area_fixed_map_type === 'zone_to_zone' ) ? 'active' : ''; ?>"
                                                 data-operation-area-type="zone_to_zone">
                                                <span class="dashicons dashicons-randomize"></span>
                                                <span><?php esc_html_e( 'Zone To Zone', 'ecab-taxi-booking-manager' ); ?></span>
                                            </div>
                                        </div>

                                        <div class="mptbm_operation_area_fixed_map_type_contents">
                                            <div class="mptbm_operation_area_fixed_map_type_content"
                                                 id="mptbm_operation_area_fixed_map_zone_to_location"
                                                 style="<?php echo ( $operation_area_fixed_map_type === 'zone_to_location' || empty( $operation_area_fixed_map_type ) ) ? 'display:block;' : 'display:none;'; ?>"
                                            >
                                                <?php
                                                self::render_fixed_with_map_price_rows( $fixed_map_route_prices, $merged_location_area, 'mptbm_taxi_pricing_route_list', $location_zones );
                                                ?>
                                                <button type="button" class="mptbm_taxi_pricing_pink_btn mptbm_taxi_pricing_add_route_btn">+ <?php esc_html_e( 'Add New Route', 'ecab-taxi-booking-manager' ); ?></button>

                                            </div>
                                            <div class=" mptbm_operation_area_fixed_map_type_content"
                                                 id="mptbm_operation_area_fixed_map_zone_to_zone"
                                                 style="<?php echo ( $operation_area_fixed_map_type === 'zone_to_zone' ) ? 'display:block;' : 'display:none;'; ?>"
                                            >
                                                <?php
                                                self::render_fixed_with_map_zone_zone_price( $fixed_map_area_to_area_route_price_info, $merged_location_area, 'mptbm_taxi_pricing_zone_to_zone_route_list', $operation_zones );
                                                ?>
                                                <button type="button"
                                                        class="mptbm_taxi_pricing_pink_btn mptbm_taxi_pricing_add_zone_to_zone_route_btn">
                                                    + <?php esc_html_e( 'Add New Route', 'ecab-taxi-booking-manager' ); ?>
                                                </button>

                                            </div>
                                        </div>

                                    </div>

                                </div>
                            </div>

                            <div class="mptbm_taxi_pricing_field"
                                 id="mptbm_fixed_zone_area_pricing"
                                 style="display: <?php echo ( $price_based === 'fixed_zone' && !empty( $selected_operation_areas ) ) ? 'block' : 'none'; ?>">
                                <div class="mptbm_taxi_pricing_sub_section">
                                    <div class="mptbm_taxi_pricing_sub_header">
                                        <h4><?php esc_html_e( 'Fixed Route & Zone Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                                        <p><?php esc_html_e( 'Define fixed prices for specific routes between zones or locations for "Fixed Zone" mode.', 'ecab-taxi-booking-manager' ); ?></p>
                                    </div>
                                    <div class="mptbm_selected_operation_area">
                                        <?php
                                        self::render_fixed_zone_price_rows( $fixed_zone_prices, $merged_location_area, 'mptbm_taxi_pricing_fixed_zone_route_list', $location_zones );
                                        ?>
                                    </div>

                                    <button type="button" class="mptbm_taxi_pricing_pink_btn mptbm_taxi_pricing_add_zone_btn">+ <?php esc_html_e( 'Add New Route', 'ecab-taxi-booking-manager' ); ?></button>
                                </div>
                            </div>
                        </div>


                    </div>
                </div>

            </div>
        <?php }

        public static function render_fixed_with_map_area_based_pricing($post_id, $operation_zones, $price_based ){
            if (!is_array($operation_zones) || empty($operation_zones)) {
                return;
            }

            $area_based_pricing = get_post_meta($post_id, 'mptbm_operation_area_pricing', true);

            // FIX: flatten your structure
            $area_based_pricing = is_array($area_based_pricing)
                ? ($area_based_pricing ?? [])
                : [];

            ?>

            <div class="mptbm_area_based_wrapper" id="mptbm_area_based_wrapper"
                 style="display: <?php echo ( $price_based === 'fixed_distance' ) ? 'block' : 'none'; ?>">
                <div class="bg-light mActive" style="margin-top: 20px;" data-collapse="#mp_fixed_map_routes">
                    <h4>Operation Area Based Price Set</h4>
                    <span>Set different pricing for each operation area based on transport type, distance, or time. Easily manage fixed, per km, and per hour rates without creating duplicate transports.</span>
                </div>

                <div class="motbm_area_based_items">

                    <?php if (!empty($area_based_pricing)) : ?>

                        <?php foreach ($area_based_pricing as $post_key => $values) :

                            $post_value = str_replace('post_', '', $post_key);
                            ?>

                            <div class="motbm_area_based_row">

                                <select name="mptbm_area_based_post[]" class="motbm_area_based_post">
                                    <option value="">Select Post</option>

                                    <?php foreach ($operation_zones as $key => $area) : ?>
                                        <option value="<?php echo esc_attr($key); ?>"
                                            <?php selected($post_key, $key); ?>>
                                            <?php echo esc_html($area); ?>
                                        </option>
                                    <?php endforeach; ?>

                                </select>

                                <input type="number"
                                       name="mptbm_area_based_fixed[]"
                                       class="motbm_area_based_fixed"
                                       value="<?php echo esc_attr($values['fixed'] ?? ''); ?>"
                                       placeholder="Fixed Price">

                                <input type="number"
                                       name="mptbm_area_based_per_km[]"
                                       class="motbm_area_based_per_km"
                                       value="<?php echo esc_attr($values['per_km'] ?? ''); ?>"
                                       placeholder="Per KM">

                                <input type="number"
                                       name="mptbm_area_based_per_hour[]"
                                       class="motbm_area_based_per_hour"
                                       value="<?php echo esc_attr($values['per_hour'] ?? ''); ?>"
                                       placeholder="Per Hour">

                                <button type="button" class="motbm_area_based_remove">
                                    Remove
                                </button>

                            </div>

                        <?php endforeach; ?>

                    <?php else : ?>

                        <!-- EMPTY DEFAULT ROW -->
                        <div class="motbm_area_based_row">

                            <select name="mptbm_area_based_post[]" class="motbm_area_based_post">
                                <option value="">Select Post</option>

                                <?php foreach ($operation_zones as $key => $area) : ?>
                                    <option value="<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($area); ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>

                            <input type="number" name="mptbm_area_based_fixed[]" class="motbm_area_based_fixed" placeholder="Fixed Price">
                            <input type="number" name="mptbm_area_based_per_km[]" class="motbm_area_based_per_km" placeholder="Per KM">
                            <input type="number" name="mptbm_area_based_per_hour[]" class="motbm_area_based_per_hour" placeholder="Per Hour">

                            <button type="button" class="motbm_area_based_remove">Remove</button>

                        </div>

                    <?php endif; ?>

                </div>

                <button type="button" class="motbm_area_based_add">
                    + Add More
                </button>

            </div>

            <?php
        }

        public static function render_fixed_with_map_price_rows( $fixed_map_route_prices, $merged_location_area, $append_body, $location_zones ) {
            ?>
            <table class="mptbm_taxi_pricing_table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Start Zone *', 'ecab-taxi-booking-manager' ); ?></th>
                    <th><?php esc_html_e( 'End Zone *', 'ecab-taxi-booking-manager' ); ?></th>
                    <th><?php esc_html_e( 'Price *', 'ecab-taxi-booking-manager' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'ecab-taxi-booking-manager' ); ?></th>
                </tr>
                </thead>
                <tbody class="<?php echo esc_html( $append_body );?>">
                <?php
                if( !empty( $fixed_map_route_prices ) ){
                foreach ($fixed_map_route_prices as $route):
                    ?>
                    <tr>
                        <td>
                            <select name="mptbm_fixed_map_route_start_location[]" class="mptbm_fixed_map_route_start_location">
                                <option value="">Select Start Zone</option>
                                <?php foreach ($merged_location_area as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"
                                        <?php selected($route['start_location'], $key); ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="mptbm_fixed_map_route_end_location[]" class="mptbm_fixed_map_route_end_location">
                                <option value="">Select End Zone</option>
                                <?php foreach ($merged_location_area as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"
                                        <?php selected($route['end_location'], $key); ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input
                                    name="mptbm_fixed_map_route_price[]"
                                    type="text"
                                    value="<?php echo esc_attr($route['price']); ?>"
                                    placeholder="EX: 10"
                            >
                        </td>
                        <td>
                            <div class="mptbm_taxi_pricing_table_actions">
                                <button class="mptbm_taxi_pricing_del_icon">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="mptbm_taxi_pricing_expand_icon">
                                    <i class="fas fa-expand-arrows-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach;
                }else{
                ?>
                    <tr>
                        <td>
                            <select name="mptbm_fixed_map_route_start_location[]" class="mptbm_fixed_map_route_start_location">
                                <option value="">Select Start Zone</option>
                                <?php foreach ($merged_location_area as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"
                                        >
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="mptbm_fixed_map_route_end_location[]" class="mptbm_fixed_map_route_end_location">
                                <option value="">Select End Zone</option>
                                <?php foreach ($merged_location_area as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"
                                       >
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input
                                    name="mptbm_fixed_map_route_price[]"
                                    type="text"
                                    value=""
                                    placeholder="EX: 10"
                            >
                        </td>
                        <td>
                            <div class="mptbm_taxi_pricing_table_actions">
                                <button class="mptbm_taxi_pricing_del_icon">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="mptbm_taxi_pricing_expand_icon">
                                    <i class="fas fa-expand-arrows-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php }?>
                </tbody>
            </table>
            <?php
        }

        public static function render_fixed_with_map_zone_zone_price( $fixed_map_route_prices, $merged_location_area, $append_body, $operation_zones ) {

            ?>
            <table class="mptbm_taxi_pricing_table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Start Zone *', 'ecab-taxi-booking-manager' ); ?></th>
                    <th><?php esc_html_e( 'End Zone *', 'ecab-taxi-booking-manager' ); ?></th>
                    <th><?php esc_html_e( 'Price *', 'ecab-taxi-booking-manager' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'ecab-taxi-booking-manager' ); ?></th>
                </tr>
                </thead>
                <tbody class="<?php echo esc_html( $append_body );?>">
                <?php
                if( !empty( $fixed_map_route_prices ) ){
                foreach ($fixed_map_route_prices as $route):
                    ?>
                    <tr>
                        <td>
                            <select name="mptbm_fixed_map_route_zone_to_zone_start_location[]" class="mptbm_fixed_map_route_start_location_zone_to_zone">
                                <option value="">Select Start Zone</option>
                                <?php foreach ($operation_zones as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"
                                        <?php selected($route['start_location'], $key); ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="mptbm_fixed_map_route_zone_to_zone_end_location[]" class="mptbm_fixed_map_route_end_location_zone_to_zone">
                                <option value="">Select End Zone</option>
                                <?php foreach ($operation_zones as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"
                                        <?php selected($route['end_location'], $key); ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input
                                    name="mptbm_fixed_map_route_zone_to_zone_price[]"
                                    type="text"
                                    value="<?php echo esc_attr($route['price']); ?>"
                                    placeholder="EX: 10"
                            >
                        </td>
                        <td>
                            <div class="mptbm_taxi_pricing_table_actions">
                                <button class="mptbm_taxi_pricing_del_icon">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="mptbm_taxi_pricing_expand_icon">
                                    <i class="fas fa-expand-arrows-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach;
                }else{
                ?>
                    <tr>
                        <td>
                            <select name="mptbm_fixed_map_route_zone_to_zone_start_location[]" class="mptbm_fixed_map_route_start_location_zone_to_zone">
                                <option value="">Select Start Zone</option>
                                <?php foreach ($operation_zones as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"
                                        >
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="mptbm_fixed_map_route_zone_to_zone_end_location[]" class="mptbm_fixed_map_route_end_location_zone_to_zone">
                                <option value="">Select Start Zone</option>
                                <?php foreach ($operation_zones as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"
                                       >
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input
                                    name="mptbm_fixed_map_route_zone_to_zone_price[]"
                                    type="text"
                                    value=""
                                    placeholder="EX: 10"
                            >
                        </td>
                        <td>
                            <div class="mptbm_taxi_pricing_table_actions">
                                <button class="mptbm_taxi_pricing_del_icon">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="mptbm_taxi_pricing_expand_icon">
                                    <i class="fas fa-expand-arrows-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php }?>
                </tbody>
            </table>
            <?php
        }

        public static function render_fixed_zone_price_rows( $fixed_map_route_prices, $merged_location_area, $append_body, $location_zones ) {


            ?>
            <table class="mptbm_taxi_pricing_table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Start Zone *', 'ecab-taxi-booking-manager' ); ?></th>
                    <th><?php esc_html_e( 'End Zone *', 'ecab-taxi-booking-manager' ); ?></th>
                    <th><?php esc_html_e( 'Price *', 'ecab-taxi-booking-manager' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'ecab-taxi-booking-manager' ); ?></th>
                </tr>
                </thead>
                <tbody class="<?php echo esc_html( $append_body );?>">
                <?php
                if( !empty( $fixed_map_route_prices ) ){
                foreach ($fixed_map_route_prices as $route):
                    ?>
                    <tr>
                        <td>
                            <select name="mptbm_zone_to_zone_route_start_location[]" class="mptbm_fixed_map_route_start_location">
                                <option value="">Select Start Zone</option>
                                <?php foreach ( $merged_location_area as $key => $label ): ?>
                                    <option value="<?php echo $key; ?>"
                                        <?php selected($route['start_location'], $key); ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="mptbm_zone_to_zone_route_end_location[]" class="mptbm_fixed_map_route_end_location">
                                <option value="">Select End Zone</option>
                                <?php foreach ( $merged_location_area as $key => $label ): ?>
                                    <option value="<?php echo $key; ?>"
                                        <?php selected($route['end_location'], $key); ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input
                                    name="mptbm_zone_to_zone_route_price[]"
                                    type="text"
                                    value="<?php echo esc_attr($route['price']); ?>"
                                    placeholder="EX: 10"
                            >
                        </td>
                        <td>
                            <div class="mptbm_taxi_pricing_table_actions">
                                <button class="mptbm_taxi_pricing_del_icon">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="mptbm_taxi_pricing_expand_icon">
                                    <i class="fas fa-expand-arrows-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach;
                }else{
                ?>
                    <tr>
                        <td>
                            <select name="mptbm_zone_to_zone_route_start_location[]" class="mptbm_fixed_map_route_start_location">
                                <option value="">Select Start Zone</option>
                                <?php foreach ($merged_location_area as $key => $label):
                                    ?>
                                    <option value="<?php echo $key; ?>"
                                        >
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="mptbm_zone_to_zone_route_end_location[]" class="mptbm_fixed_map_route_end_location">
                                <option value="">Select End Zone</option>
                                <?php foreach ($merged_location_area as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"
                                       >
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input
                                    name="mptbm_zone_to_zone_route_price[]"
                                    type="text"
                                    value=""
                                    placeholder="EX: 10"
                            >
                        </td>
                        <td>
                            <div class="mptbm_taxi_pricing_table_actions">
                                <button class="mptbm_taxi_pricing_del_icon">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="mptbm_taxi_pricing_expand_icon">
                                    <i class="fas fa-expand-arrows-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php }?>
                </tbody>
            </table>
            <?php
        }

        public static function render_location_price_rows($terms_location_prices, $location_terms) {

            $location_map = [];
            foreach ($location_terms as $term) {
                $location_map[$term->slug] = $term->name;
            }

            if (!empty($terms_location_prices)) {
                foreach ($terms_location_prices as $route) {
                    ?>

                    <div class="mptbm_taxi_pricing_route_row">

                        <!-- Start -->
                        <div class="mptbm_taxi_pricing_select_wrap">
                            <select name="mptbm_terms_start_location[]">
                                <?php foreach ($location_terms as $term): ?>
                                    <option value="<?php echo esc_attr($term->slug); ?>"
                                        <?php selected($route['start_location'], $term->slug); ?>>
                                        <?php echo esc_html($term->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- End -->
                        <div class="mptbm_taxi_pricing_select_wrap">
                            <select name="mptbm_terms_end_location[]">
                                <?php foreach ($location_terms as $term): ?>
                                    <option value="<?php echo esc_attr($term->slug); ?>"
                                        <?php selected($route['end_location'], $term->slug); ?>>
                                        <?php echo esc_html($term->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Price -->
                        <div class="mptbm_taxi_pricing_input_wrap">
                            <input
                                    name="mptbm_location_terms_price[]"
                                    type="text"
                                    value="<?php echo esc_attr($route['price']); ?>"
                                    placeholder="<?php esc_html_e( '250 - F', 'ecab-taxi-booking-manager' ); ?>"
                            >
                        </div>

                        <!-- Actions -->
                        <div class="mptbm_taxi_pricing_action_btns">
                            <button type="button" class="mptbm_taxi_pricing_drag_btn" title="Drag to reorder">
                                <i class="fas fa-grip-vertical"></i>
                            </button>
                            <button type="button" class="mptbm_taxi_pricing_clone_btn" title="Clone">
                                <i class="far fa-copy"></i>
                            </button>
                            <button type="button" class="mptbm_taxi_pricing_delete_btn" title="Remove">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>

                    </div>

                    <?php
                }
            } else {
                // empty row fallback
                ?>
                <div class="mptbm_taxi_pricing_route_row">

                    <div class="mptbm_taxi_pricing_select_wrap">
                        <select name="mptbm_terms_start_location[]">
                            <option value=""><?php esc_html_e( 'Start city...', 'ecab-taxi-booking-manager' ); ?></option>
                            <?php foreach ($location_terms as $term): ?>
                                <option value="<?php echo esc_attr($term->slug); ?>">
                                    <?php echo esc_html($term->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mptbm_taxi_pricing_select_wrap">
                        <select name="mptbm_terms_end_location[]">
                            <option value="">End city...</option>
                            <?php foreach ($location_terms as $term): ?>
                                <option value="<?php echo esc_attr($term->slug); ?>">
                                    <?php echo esc_html($term->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mptbm_taxi_pricing_input_wrap">
                        <input name="mptbm_location_terms_price[]" type="text" placeholder="<?php esc_html_e( '250 - F', 'ecab-taxi-booking-manager' ); ?>">
                    </div>

                    <div class="mptbm_taxi_pricing_action_btns">
                        <button type="button" class="mptbm_taxi_pricing_drag_btn" title="Drag to reorder">
                            <i class="fas fa-grip-vertical"></i>
                        </button>
                        <button type="button" class="mptbm_taxi_pricing_clone_btn" title="Clone">
                            <i class="far fa-copy"></i>
                        </button>
                        <button type="button" class="mptbm_taxi_pricing_delete_btn" title="Remove">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>

                </div>
                <?php
            }
        }

    }

    new MPTBM_Rent_Custom_Editor();
}
