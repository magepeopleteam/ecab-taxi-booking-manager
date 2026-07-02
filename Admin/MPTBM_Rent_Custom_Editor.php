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
            add_action('admin_menu', [$this, 'register_menu']);
            add_action('admin_post_save_mptbm_rent', [$this, 'save_post']);
            add_action('admin_init', [$this, 'redirect_default_editor']);
            add_action('admin_init', [$this, 'redirect_add_new']);


//            add_action('wp_ajax_save_mptbm_rent', [$this, 'save_mptbm_rent_callback']);

            add_action('save_post', [ $this, 'mptbm_save_taxi_data' ] );

            add_filter('redirect_post_location', [ $this, 'my_custom_post_redirect' ], 10, 2);

            add_action('edit_form_after_title', function () {
                ?>
                <input type="hidden" name="editor_type" value="old">
                <?php
            });
            add_action('admin_notices', [ $this, 'mptbm_add_custom_editor_button' ] );

            add_action('admin_menu', [ $this, 'hide_all_transport_submenu'], 999);

        }
        function hide_all_transport_submenu() {
            remove_submenu_page(
                'edit.php?post_type=mptbm_rent', // Parent menu slug
                'edit.php?post_type=mptbm_rent'  // All Transport submenu slug
            );

        }


        function mptbm_add_custom_editor_button($post) {
            global $post;

            if (!$post || $post->post_type !== 'mptbm_rent') {
                return;
            }

            $url = admin_url(
                'admin.php?page=mptbm-rent-edit&post_id=' . $post->ID
            );

            ?>
            <div id="mptbm-custom-editor-btn" style="display:none;">
                <a href="<?php echo esc_url($url); ?>" class="mptbm-add-btn">
                    Open Custom Editor
                </a>
            </div>
            <script>
            jQuery(function($) {
                var $btn = $('#mptbm-custom-editor-btn');
                if ($btn.length) {
                    var $titleActions = $('.wrap > .page-title-action');
                    if ($titleActions.length) {
                        $titleActions.last().after($btn.children());
                    } else {
                        $('.wrap > h1.wp-heading-inline').after($btn.children());
                    }
                    $btn.remove();
                }
            });
            </script>
            <?php
        }


        function my_custom_post_redirect($location, $post_id) {

            if (isset($_POST['editor_type'])) {

                if ($_POST['editor_type'] === 'old') {

                    return admin_url(
                        'post.php?post=' . $post_id . '&action=edit&editor=old'
                    );

                } elseif ($_POST['editor_type'] === 'custom') {

                    return admin_url(
                        'admin.php?page=mptbm-rent-edit&post_id=' . $post_id
                    );
                }
            }

            return $location;
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

                if ( get_post_type( $post_id ) === 'mptbm_rent' && isset( $_POST['mptbm_availability_status_field_present'] ) ) {
                    $availability_status = isset( $_POST['mptbm_availability_status'] ) ? 'unavailable' : 'available';
                    update_post_meta( $post_id, 'mptbm_availability_status', $availability_status );
                }
            }

        }

        // 1. Register submenu page
        public function register_menu() {
            add_submenu_page(
                'mptbm_rent',
                __('Edit Rent', 'ecab-taxi-booking-manager'),
                __('Edit Rent', 'ecab-taxi-booking-manager'),
                'manage_options',
                'mptbm-rent-edit',
                [$this, 'render_page']
            );
        }

        public function redirect_add_new() {

            global $pagenow;

            if (
                $pagenow === 'post-new.php' &&
                isset($_GET['post_type']) &&
                $_GET['post_type'] === 'mptbm_rent'
            ) {

                // Allow old editor
                if (isset($_GET['editor']) && $_GET['editor'] === 'old') {
                    return;
                }

                wp_redirect(
                    admin_url('admin.php?page=mptbm-rent-edit')
                );

                exit;
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
                            <svg class="mptbm_shortcode_inline_icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                            <?php esc_html_e( 'Optional Parameters:', 'ecab-taxi-booking-manager' ); ?>
                        </h4>
                        <div class="mptbm_shortcode_grid">
                            <div class="mptbm_shortcode_param_item"><code>form='horizontal'</code> or <code>form='inline'</code></div>
                            <div class="mptbm_shortcode_param_item"><code>progressbar='yes'</code> or <code>progressbar='no'</code></div>
                            <div class="mptbm_shortcode_param_item"><code>map='yes'</code> or <code>map='no'</code></div>
                            <div class="mptbm_shortcode_param_item"><code>tabs='hourly,distance,manual'</code></div>
                        </div>
                    </div>

                    <!-- Example Usage -->
                    <div class="mptbm_shortcode_section">
                        <div class="mptbm_shortcode_example_wrapper">
                            <h4 class="mptbm_shortcode_sub_title">
                                <svg class="mptbm_shortcode_inline_icon" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2"><path d="M9 18h6m-6-4h6m-7.5 4a6 6 0 1 1 9 0"></path></svg>
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
        public function render_page() {

            $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
            if (!$post_id) {
                $post_id = wp_insert_post([
                    'post_type'   => 'mptbm_rent',
                    'post_status' => 'auto-draft',
                    'post_title'  => '',
                ]);

                // redirect to same page with post_id
                wp_redirect(
                    admin_url('admin.php?page=mptbm-rent-edit&post_id=' . $post_id)
                );
                exit;
            }

            $title   = $post_id ? get_the_title($post_id) : 'New Rent';
            $pro_active = class_exists('MPTBM_Dependencies_Pro');
            $old_editor_url = admin_url(
                'post.php?post=' . $post_id . '&action=edit&editor=old'
            );
            ?>
            <div class="wrap mptbm_settings_area">


                <div id="mptbm_pro_popup" class="mptbm_pro_popup">
                    <div class="mptbm_pro_popup_content">
                        <span class="mptbm_pro_close_popup">&times;</span>

                        <h2><span class="dashicons dashicons-lock"></span> PRO FEATURE</h2>
                        <p>This feature is available in PRO version only.</p>

                        <a href="https://mage-people.com/product/wordpress-taxi-cab-booking-plugin-for-woocommerce" target="_blank" class="buy-pro-btn">
                            Buy PRO Now
                        </a>
                    </div>
                </div>

                <form class="mptbm_rent_form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">

                    <input type="hidden" name="editor_type" value="custom">

                    <input type="hidden" name="return_url" value="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
                    <input type="hidden" name="action" value="save_mptbm_rent">
                    <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">

                    <?php wp_nonce_field('save_mptbm_rent_nonce');
                    $add_url   = admin_url('admin.php?page=mptbm-rent-edit');
                    ?>

                    <!-- FIXED HEADER -->
                    <div class="mptbm_fixed_header">

                        <div class="mptbm_fixed_header_top">

                            <div class="">
                                <a class="mptbm-link" href="<?php echo admin_url('admin.php?page=mptbm_transportation_lists'); ?>">
                                    <span class="dashicons dashicons-arrow-left-alt"></span>
                                    <?php esc_html_e( 'Back to Transports', 'ecab-taxi-booking-manager' ); ?>
                                </a>

                                <a class="mptbm-add-btn" href="<?php echo esc_url($add_url); ?>">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    <?php esc_html_e('Add New Transportation', 'ecab-taxi-booking-manager'); ?>
                                </a>
                            </div>



                            <div class="mptbm_header_left">
                                <h1 class="mptbm_page_title">
                                    <?php echo esc_html($title); ?>
                                </h1>
                            </div>

                            <div class="mptbm_header_right">

                                <?php
                                submit_button($post_id ? 'Update' : 'Publish', 'primary', '', false); ?>
                                
                                <a href="<?php echo esc_url($old_editor_url); ?>" class="button">
                                    <?php esc_html_e( 'Open classic Editor', 'ecab-taxi-booking-manager' ); ?>
                                </a>

                            </div>

                        </div>

                        <div class="mptbm_taxi_header_holder">
                            <?php self::taxi_content_tabs_set($post_id); ?>
                        </div>

                    </div>
                    <!-- SCROLLABLE CONTENT -->
                    <div class="mptbm_scroll_content ">
                        <div class="mptbm_taxi_wrapper">

                            <div class="mptbm_taxi_container_holder">
                                <div class="mptbm_taxi_content_container">
                                    <?php
                                    self::general_information_set( $post_id, $pro_active );
                                    self::pricing_settings( $post_id, $pro_active );
                                    self::date_configuration_set($post_id);
                                    ?>
                                </div>
                                <div class="mptbm_right_side_section">
                                    <?php
                                    do_action( 'mptbm_right_side_section', $post_id );
//                                    self::right_side_section( $post_id );
                                    ?>
                                </div>
                            </div>

                        </div>

                    </div>

                    <!-- FIXED FOOTER -->
                    <div class="mptbm_fixed_footer">

                        <div class="mptbm_footer_right">

                            <div class="mptbm_taxi_footer">

                                <button type="button" class="mptbm_taxi_btn_prev">
                                    <?php esc_html_e( '← Previous', 'ecab-taxi-booking-manager' ); ?>
                                </button>

                                <span class="mptbm_taxi_step_counter">
                                    <?php esc_html_e( 'Step 1 of 3', 'ecab-taxi-booking-manager' ); ?>
                                </span>

                                <button type="button" class="mptbm_taxi_btn_next">
                                    <?php esc_html_e( 'Next →', 'ecab-taxi-booking-manager' ); ?>
                                </button>

                            </div>

                        </div>

                    </div>
                </form>
            </div>
            <?php
        }

        public static function general_data_configuration( $max_passenger, $max_bag, $max_hand_luggage, $extra_info ){ ?>
            <div class="mptbm_rent_editor_wrapper">

                <!-- Header -->
                <div class="mptbm_rent_editor_header">
                    <div>
                        <h2 class="mptbm_rent_editor_title"><?php esc_html_e( 'Vehicle Capacity & Details', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p class="mptbm_rent_editor_subtitle">
                            <?php esc_html_e( 'Set passenger capacity, luggage limits, and any additional vehicle information.', 'ecab-taxi-booking-manager' ); ?>
                        </p>
                    </div>
                </div>

                <!-- Body -->
                <div class="mptbm_rent_editor_body mptbm_field_grid_2col">

                    <div class="mptbm_rent_field_group">
                        <label><?php esc_html_e( 'Maximum Passengers', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Max number of passengers this vehicle can accommodate.', 'ecab-taxi-booking-manager' ); ?></p>
                        <input name="mptbm_maximum_passenger" type="text" value="<?php echo esc_attr( $max_passenger );?>" placeholder="<?php esc_html_e( 'e.g. 4', 'ecab-taxi-booking-manager' ); ?>">
                    </div>

                    <div class="mptbm_rent_field_group">
                        <label><?php esc_html_e( 'Maximum Bags', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Max number of large bags/suitcases allowed.', 'ecab-taxi-booking-manager' ); ?></p>
                        <input name="mptbm_maximum_bag" type="text" value="<?php echo esc_attr( $max_bag );?>" placeholder="<?php esc_html_e( 'e.g. 3', 'ecab-taxi-booking-manager' ); ?>">
                    </div>

                    <div class="mptbm_rent_field_group">
                        <label><?php esc_html_e( 'Maximum Hand Luggage', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Max number of carry-on or hand luggage items.', 'ecab-taxi-booking-manager' ); ?></p>
                        <input name="mptbm_maximum_hand_luggage" type="text" value="<?php echo esc_attr( $max_hand_luggage );?>" placeholder="<?php esc_html_e( 'e.g. 2', 'ecab-taxi-booking-manager' ); ?>">
                    </div>

                    <div class="mptbm_rent_field_group">
                        <label><?php esc_html_e( 'Extra Info', 'ecab-taxi-booking-manager' ); ?></label>
                        <p class="mptbm_taxi_help"><?php esc_html_e( 'Additional details displayed to customers (e.g. amenities, notes).', 'ecab-taxi-booking-manager' ); ?></p>
                        <textarea name="mptbm_extra_info" rows="3" placeholder="<?php esc_attr_e( 'e.g. WiFi available, child seat on request...', 'ecab-taxi-booking-manager' ); ?>"><?php echo esc_html( $extra_info );?></textarea>
                    </div>

                </div>

            </div>
        <?php }
        public static function taxi_title_description_set( $post_id ){ ?>
            <div class="mptbm_rent_editor_wrapper">

                <!-- Header -->
                <div class="mptbm_rent_editor_header">
                    <div>
                        <h2 class="mptbm_rent_editor_title"><?php esc_html_e( 'Basic Information', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p class="mptbm_rent_editor_subtitle">
                            <?php esc_html_e( 'Give your rental a clear, descriptive name that customers will see.', 'ecab-taxi-booking-manager' ); ?>
                        </p>
                    </div>
                </div>

                <!-- Body -->
                <div class="mptbm_rent_editor_body">
                    <!-- Title -->
                    <div class="mptbm_rent_field_group">

                        <label class="mptbm_rent_label" for="mptbm_rent_title">
                            <?php esc_html_e( 'Rent Title', 'ecab-taxi-booking-manager' ); ?> <span class="mptbm_rent_required">*</span>
                        </label>

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
                    <div class="mptbm_rent_field_group" style="display: none">

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
                </div>

            </div>
        <?php }


        public static function taxi_content_tabs_set( $post_id ) {
            $post_id = absint( $post_id );
            ?>
            <div class="mptbm_taxi_stepper">

                <!-- STEP 1 -->
                <div class="mptbm_taxi_step mptbm_taxi_active"
                     data-step="<?php echo esc_attr(1); ?>">

                    <div class="mptbm_taxi_icon">
                        1
                    </div>

                    <div class="mptbm_taxi_label">
                        <?php esc_html_e( 'General Information', 'ecab-taxi-booking-manager' ); ?>
                    </div>

                </div>

                <div class="mptbm_taxi_line"></div>

                <!-- STEP 2 -->
                <div class="mptbm_taxi_step"
                     data-step="<?php echo esc_attr(2); ?>">

                    <div class="mptbm_taxi_icon">
                        2
                    </div>

                    <div class="mptbm_taxi_label">
                        <?php esc_html_e( 'Pricing Configuration', 'ecab-taxi-booking-manager' ); ?>
                    </div>

                </div>

                <div class="mptbm_taxi_line"></div>

                <!-- STEP 3 -->
                <div class="mptbm_taxi_step"
                     data-step="<?php echo esc_attr(3); ?>">

                    <div class="mptbm_taxi_icon">
                        3
                    </div>

                    <div class="mptbm_taxi_label">
                        <?php esc_html_e( 'Operational Date Time', 'ecab-taxi-booking-manager' ); ?>
                    </div>

                </div>

            </div>

            <?php
        }

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
            <div class="mptbm_taxi_container" data-step="1" >
                <?php wp_nonce_field('mptbm_transportation_type_nonce', 'mptbm_transportation_type_nonce');

                self::taxi_title_description_set( $post_id );
                
                self::general_data_configuration( $max_passenger, $max_bag, $max_hand_luggage, $extra_info );

                ?>

                <div class="mptbm_rent_editor_wrapper">
                    <div class="mptbm_rent_editor_header">
                        <div>
                            <h2 class="mptbm_rent_editor_title"><?php esc_html_e( 'Price Display Settings', 'ecab-taxi-booking-manager' ); ?></h2>
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

                self::taxi_availability_status( $post_id );

                if (class_exists('MPTBM_Plugin_Pro')) {
                    self::taxi_inventory_manages($post_id, $all_features);
                }

                self::taxi_feature_add_remove( $post_id, $all_features );

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
                        <h2 class="mptbm_rent_editor_title"><?php esc_html_e( 'Enable Base Location Charges', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p class="mptbm_rent_editor_subtitle"><?php esc_html_e( 'Apply additional charges based on distance between taxi base location and pickup/drop-off points.', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                    <div class="mptbm_taxi_ex_service_toggle_wrapper">

                        <?php if ( $pro_active ): ?>

                            <label class="mptbm_taxi_ex_service_switch">
                                <input type="checkbox"
                                       id="mptbm_display_taxi_base_location_pricing"
                                       name="mptbm_display_taxi_base_location_pricing"
                                       class="mptbm_taxi_toggle_trigger"
                                    <?php echo esc_attr($checked); ?>>
                                <span class="mptbm_taxi_slider"></span>
                            </label>
                            <span class="mptbm_taxi_ex_service_toggle_label">
                                <?php esc_html_e('ON', 'ecab-taxi-booking-manager'); ?>
                            </span>
                        <?php else: ?>

                            <label class="mptbm_taxi_ex_service_switch mptbm_locked_switch">
                                <input type="checkbox" disabled>
                                <span class="mptbm_taxi_slider mptbm_locked"></span>
                            </label>

                            <span class="mptbm_taxi_ex_service_toggle_label mptbm_pro_locked_text">
                                🔒 Pro Feature
                            </span>
                        <?php endif; ?>
                    </div>

                </div>

                <?php if( $pro_active ){?>
                <div class="mptbm_taxi_ex_service_body" id="mptbm_taxi_base_location_price_body" style="display: <?php echo esc_attr( $active );?>">
                    <div class="mptbm_taxi_base_price_row">
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Base Price Location', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Select the base location for price calculation', 'ecab-taxi-booking-manager' ); ?></p>
                            <select class="formControl" name="mptbm_base_price_location">
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
                            <label><?php esc_html_e( 'Price per KM', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Enter the price per kilometer from base location', 'ecab-taxi-booking-manager' ); ?></p>
                            <input
                                    name="mptbm_base_price_km"
                                    type="number"
                                    min="0"
                                    step="0.1"
                                    value="<?php echo esc_attr( $base_price_km ?: '0' ); ?>"
                                    placeholder="1.5"
                            >
                        </div>
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Price per Hour', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Enter the price per hour from base location', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_base_price_hour" type="number" value="<?php echo esc_attr( $base_price_hour );?>" placeholder="10 ">
                        </div>
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Minimum Threshold (Distance)', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Distance free of charge from base price location', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_base_min_threshold" type="number" value="<?php echo esc_attr( $base_min_threshold );?>" placeholder="1">
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
                                    <input name="mptbm_charge_base_pickup" type="checkbox" class="mptbm_taxi_toggle_trigger" <?php echo ($charge_base_pickup == 'yes') ? 'checked' : ''; ?>>
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
                                <input name="mptbm_charge_base_dropoff" type="checkbox" class="mptbm_taxi_toggle_trigger" <?php echo ($charge_base_dropoff == 'yes') ? 'checked' : ''; ?>>
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

                </div>
                <?php }?>
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
            <div class="mptbm_rent_editor_wrapper mpStyle">
                <div class="mptbm_taxi_feature_header mptbm_rent_editor_header">
                    <div class="mptbm_taxi_feature_title_area">
                        <h2 class="mptbm_rent_editor_title"><?php esc_html_e( 'Vehicle Features', 'ecab-taxi-booking-manager' ); ?></h2>
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
        public static function taxi_availability_status( $post_id ){
            $status = MP_Global_Function::get_post_info($post_id, 'mptbm_availability_status', 'available');
            $is_unavailable = $status === 'unavailable';
            $status_text = $is_unavailable ? esc_html__('Unavailable', 'ecab-taxi-booking-manager') : esc_html__('Available', 'ecab-taxi-booking-manager');
            $checked = $is_unavailable ? 'checked' : '';
            ?>
            <div class="mptbm_rent_editor_wrapper">
                <input type="hidden" name="mptbm_availability_status_field_present" value="1">
                <div class="mptbm_taxi_feature_header mptbm_rent_editor_header">
                    <div class="mptbm_taxi_feature_title_area">
                        <h2 class="mptbm_rent_editor_title"><?php esc_html_e( 'Vehicle Availability', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p class="mptbm_rent_editor_subtitle"><?php esc_html_e( 'Manually mark this vehicle unavailable (e.g. it\'s out on a long trip). While unavailable it will not appear in search results at all, until you switch it back.', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                    <div class="mptbm_taxi_feature_switch">
                        <span class="mptbm_taxi_feature_switch_text mptbm_availability_status_text" data-available-text="<?php esc_attr_e('Available', 'ecab-taxi-booking-manager'); ?>" data-unavailable-text="<?php esc_attr_e('Unavailable', 'ecab-taxi-booking-manager'); ?>"><?php echo esc_html( $status_text ); ?></span>
                        <label class="mptbm_taxi_feature_toggle">
                            <input type="checkbox" id="mptbm_availability_status" name="mptbm_availability_status" <?php echo esc_attr( $checked ); ?>>
                            <span class="mptbm_taxi_feature_slider"></span>
                        </label>
                    </div>
                </div>
            </div>
            <script>
            jQuery(function($) {
                $('#mptbm_availability_status').on('change', function() {
                    var $text = $(this).closest('.mptbm_taxi_feature_header').find('.mptbm_availability_status_text');
                    $text.text(this.checked ? $text.data('unavailable-text') : $text.data('available-text'));
                });
            });
            </script>
        <?php }
        public static function taxi_inventory_manages( $post_id, $all_features ){
            $display_features = MP_Global_Function::get_post_info($post_id, 'mptbm_enable_inventory', 'no');
            $features_active = $display_features == 'no' ? 'Off' : 'On';
            $display = $display_features == 'no' ? 'none' : 'block';
            $features_checked = $display_features == 'no' ? '' : 'checked';
            ?>
            <div class="mptbm_rent_editor_wrapper">
                <div class="mptbm_taxi_feature_header mptbm_rent_editor_header">
                    <div class="mptbm_taxi_feature_title_area">
                        <h2 class="mptbm_rent_editor_title"><?php esc_html_e( 'Inventory Management', 'ecab-taxi-booking-manager' ); ?></h2>
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
                                        placeholder="<?php esc_attr_e('e.g. 5', 'ecab-taxi-booking-manager'); ?>">
                            </div>
                        </div>

                        <div class="mptbm_taxi_advanced_card" style="margin-bottom: 0;">
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
                                       placeholder="<?php esc_attr_e('e.g. 30', 'ecab-taxi-booking-manager'); ?>"
                                >
                            </div>
                        </div>
                    </div>
                </div>


            </div>
        <?php }
        public static function date_configuration_set( $post_id ){ ?>
            <div class="mptbm_taxi_container " data-step="3" style="display: none">
                <?php
                do_action( 'mptbm_date_and_advanced_settings', $post_id );
                ?>
            </div>
        <?php }
        public static function extra_service_display( $post_id ){

            $display            = MP_Global_Function::get_post_info( $post_id, 'display_mptbm_extra_services', 'on' );
            $service_id         = (int)get_post_meta( $post_id, 'mptbm_extra_services_id', true);
            $active             = $display == 'off' ? 'none' : 'block';
            $checked            = $display == 'off' ? '' : 'checked';
            $all_ex_services_id = MPTBM_Query::query_post_id( 'mptbm_extra_services' );
            ?>
            <div class="mptbm_taxi_ex_service_container">
                <div class="mptbm_taxi_ex_service_header mptbm_rent_editor_header">
                    <div class="mptbm_taxi_ex_service_title_group">
                        <h2 class="mptbm_rent_editor_title"><?php esc_html_e( 'Extra Services', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p class="mptbm_rent_editor_subtitle"><?php esc_html_e( 'Offer optional add-ons customers can choose at booking (e.g. child seat, meet &amp; greet, pet carrier).', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                    <div class="mptbm_taxi_ex_service_toggle_wrapper">
                        <label class="mptbm_taxi_ex_service_switch">
                            <input type="checkbox" id="mptbm_taxi_ex_service_master_toggle" name="display_mptbm_extra_services" <?php echo esc_attr($checked); ?>>
                            <span class="mptbm_taxi_ex_service_slider"></span>
                        </label>
                        <span class="mptbm_taxi_ex_service_toggle_label"><?php esc_html_e( 'ON', 'ecab-taxi-booking-manager' ); ?></span>
                    </div>
                </div>

                <div class="mptbm_taxi_ex_service_body" id="mptbm_taxi_ex_service_body" style="display: <?php echo esc_attr( $active );?>">
                    <div class="mptbm_taxi_ex_service_filter_row">
                        <label><?php esc_html_e( 'Select extra option:', 'ecab-taxi-booking-manager' ); ?></label>
                        <select class="formControl" id="mptbm_extra_services_id" name="mptbm_extra_services_id">
                            <option value=""><?php esc_html_e( 'Select extra option', 'ecab-taxi-booking-manager' ); ?></option>
                            <option value="<?php echo esc_attr( $post_id ); ?>" <?php echo esc_attr( $service_id == $post_id ? 'selected' : '' ); ?>><?php esc_html_e( 'Custom', 'ecab-taxi-booking-manager' ); ?></option>
                            <?php if ( sizeof( $all_ex_services_id ) > 0 ) { ?>
                                <?php foreach ( $all_ex_services_id as $ex_services_id ) { ?>
                                    <option value="<?php echo esc_attr( $ex_services_id ); ?>" <?php echo esc_attr( $service_id == $ex_services_id ? 'selected' : '' ); ?>><?php echo esc_html(get_the_title( $ex_services_id )); ?></option>
                                <?php } ?>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="mpStyle">
                    <table class="mptbm_taxi_ex_service_table">
                        <thead>
                        <tr>
                            <th><?php esc_html_e( 'Icon', 'ecab-taxi-booking-manager' ); ?></th>
                            <th><?php esc_html_e( 'Name', 'ecab-taxi-booking-manager' ); ?></th>
                            <th><?php esc_html_e( 'Description', 'ecab-taxi-booking-manager' ); ?></th>
                            <th><?php esc_html_e( 'Price ($)', 'ecab-taxi-booking-manager' ); ?></th>
                            <th><?php esc_html_e( 'Qty Box Type', 'ecab-taxi-booking-manager' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'ecab-taxi-booking-manager' ); ?></th>
                        </tr>
                        </thead>
                        <tbody id="mptbm_taxi_ex_service_tbody">
                        <?php
                            self::extra_service_item( $post_id, $service_id );
                        ?>
                        </tbody>
                    </table>

                    <div class="mptbm_taxi_ex_service_footer">
                        <button type="button" id="mptbm_taxi_ex_service_add_btn" class="mptbm_taxi_ex_service_add_btn"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add New Service', 'ecab-taxi-booking-manager' ); ?></button>
                    </div>
                    </div>
                </div>
            </div>
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
            $input_type    = array_key_exists( 'service_qty_type', $field ) ? $field['service_qty_type'] : 'inputbox';
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
                <td>
                    <?php do_action('mp_add_icon_image', 'mptbm_extra_service_icon[]', $icon, $image); ?>
                </td>
                <td>
                    <input type="text" name="service_name[]" class="mptbm_taxi_ex_service_input" value="<?php echo esc_attr( $service_name ); ?>">
                </td>
                <td>
                    <textarea class="mptbm_taxi_ex_service_select" name="extra_service_description[]"><?php echo esc_html( $description ); ?></textarea>
                </td>
                <td><input
                    type="number" class="mptbm_taxi_ex_service_input mptbm_center"
                    step="0.01"
                    min="0"
                    name="service_price[]"
                    placeholder="<?php esc_attr_e( 'EX: 10.50', 'ecab-taxi-booking-manager' ); ?>"
                    value="<?php echo esc_attr( $service_price ); ?>"
                    ></td>
                <td>
                    <select name="service_qty_type[]" class='mptbm_taxi_ex_service_select mideum'>
                        <option value="inputbox" <?php echo esc_attr( $input_type == 'inputbox' ? 'selected' : '' ); ?>><?php esc_html_e( 'Input Box', 'ecab-taxi-booking-manager' ); ?></option>
                        <option value="dropdown" <?php echo esc_attr( $input_type == 'dropdown' ? 'selected' : '' ); ?>><?php esc_html_e( 'Dropdown List', 'ecab-taxi-booking-manager' ); ?></option>
                    </select>
                </td>
                <td class="mptbm_taxi_ex_service_actions">
                    <button type="button" class="mptbm_taxi_ex_service_btn_del" title="<?php esc_attr_e( 'Delete service', 'ecab-taxi-booking-manager' ); ?>"><span class="dashicons dashicons-trash"></span></button>
                    <button type="button" class="mptbm_taxi_ex_service_btn_drag" title="<?php esc_attr_e( 'Drag to reorder', 'ecab-taxi-booking-manager' ); ?>"><span class="dashicons dashicons-move"></span></button>
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

            $display            = MP_Global_Function::get_post_info( $post_id, 'mptbm_display_taxi_base_fare_pricing', 'off' );
            $active             = $display == 'off' ? 'none' : 'block';
            $checked            = $display == 'off' ? '' : 'checked';

            ?>



            <div class="mptbm_rent_editor_wrapper" id="mptbm_taxi_base_fare_toggle_container">
                <div class="mptbm_taxi_ex_service_header mptbm_rent_editor_header">
                    <div class="mptbm_taxi_ex_service_title_group">
                        <h2 class="mptbm_rent_editor_title"><?php esc_html_e( 'Base Fare Settings', 'ecab-taxi-booking-manager' ); ?></h2>
                        <p class="mptbm_rent_editor_subtitle"><?php esc_html_e( 'Fixed charge applied at the start of every trip, regardless of distance. Disable to remove it entirely.', 'ecab-taxi-booking-manager' ); ?></p>
                    </div>
                    <div class="mptbm_taxi_ex_service_toggle_wrapper">
                        <label class="mptbm_taxi_ex_service_switch">
                            <input type="checkbox" id="mptbm_display_taxi_base_fare_pricing" name="mptbm_display_taxi_base_fare_pricing"  class="mptbm_taxi_toggle_trigger" <?php echo esc_attr( $checked );?>>
                            <span class="mptbm_taxi_slider"></span>
                        </label>
                        <span class="mptbm_taxi_ex_service_toggle_label mptbm_display_taxi_base_fare_pricing_level"><?php esc_html_e( 'ON', 'ecab-taxi-booking-manager' ); ?></span>
                    </div>
                </div>

                <div class="mptbm_taxi_ex_service_body" id="mptbm_taxi_base_price_body" style="display: <?php echo esc_attr( $active );?>">
                    <div class="mptbm_taxi_base_price_row">
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Initial / Base Price', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Flat charge at the start of every trip, before distance or time is calculated.', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_initial_price" type="text" value="<?php echo esc_attr( $initial_price );?>" placeholder="<?php esc_attr_e( 'e.g. 5.00', 'ecab-taxi-booking-manager' ); ?>">
                        </div>
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Minimum Price', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'The lowest fare charged when the calculated price is below this threshold.', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_min_price" type="text" value="<?php echo esc_attr( $min_price );?>" placeholder="<?php esc_attr_e( 'e.g. 10.00', 'ecab-taxi-booking-manager' ); ?>">
                        </div>
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Return Minimum Price', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Minimum fare applied specifically on return trip bookings.', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_min_price_return" type="text" value="<?php echo esc_attr( $return_min_price );?>" placeholder="<?php esc_html_e( 'e.g. 40', 'ecab-taxi-booking-manager' ); ?>">
                        </div>
                        <div class="mptbm_taxi_field">
                            <label><?php esc_html_e( 'Return Discount', 'ecab-taxi-booking-manager' ); ?></label>
                            <p class="mptbm_taxi_help"><?php esc_html_e( 'Discount applied to return trips. Enter a fixed amount or percentage (e.g. 10 or 10%).', 'ecab-taxi-booking-manager' ); ?></p>
                            <input name="mptbm_return_discount" type="text" value="<?php echo esc_attr( $return_discount );?>" placeholder="<?php esc_html_e( 'e.g. 10 or 10%', 'ecab-taxi-booking-manager' ); ?>">
                        </div>
                        <?php if ($waiting_time_check == 'enable') { ?>
                            <div class="mptbm_taxi_field">
                                <label><?php esc_html_e( 'Waiting Time Price / Hour', 'ecab-taxi-booking-manager' ); ?></label>
                                <p class="mptbm_taxi_help"><?php esc_html_e( 'Hourly rate charged when the driver is waiting for the passenger.', 'ecab-taxi-booking-manager' ); ?></p>
                                <input name="mptbm_waiting_price" type="text" value="<?php echo esc_attr( $waiting_price );?>" placeholder="<?php esc_html_e( 'e.g. 10', 'ecab-taxi-booking-manager' ); ?>">
                            </div>
                        <?php }?>
                    </div>
                </div>
            </div>
        <?php }
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
            <div class="mptbm_taxi_container mptbm_taxi_pricing_wrapper" data-step="2" style="display: none">
                <?php wp_nonce_field('mptbm_price_settings_action', 'mptbm_price_settings_nonce'); ?>
                <input type="hidden" name="mptbm_selected_operation_areas" id="mptbm_selected_operation_areas" value="<?php echo esc_html( $operation_area_str );?>">
                <?php
                self::initial_base_pricing( $post_id );

//                if( $pro_active ) {
                    self::enable_base_location_charges( $post_id, $pro_active );
//                }
                ?>

                <div class="mptbm_rent_editor_wrapper" style="display: block">
                    <div class="mptbm_rent_editor_header">
                        <div>
                            <h3 class="mptbm_rent_editor_title"><?php esc_html_e( 'Select Pricing Model', 'ecab-taxi-booking-manager' ); ?></h3>
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
                            <h3 class="mptbm_rent_editor_title"><?php esc_html_e( 'Configure Pricing Rules', 'ecab-taxi-booking-manager' ); ?></h3>
                            <p class="mptbm_rent_editor_subtitle"><?php esc_html_e( 'Set the rates and route overrides for the selected pricing model above.', 'ecab-taxi-booking-manager' ); ?></p>
                        </div>
                    </div>
                    <div class="mptbm_taxi_pricing_group" >
                        <div class="mptbm_taxi_pricing_row_content">

                            <div class="mptbm_taxi_pricing_field"
                                 id="mptbm_distance_price"
                                 style="display: <?php echo ($price_based === 'inclusive' || $price_based === 'distance' || $price_based === 'distance_duration' || $price_based === 'fixed_distance' ) ? 'block' : 'none'; ?>">
                                <label><?php esc_html_e( 'Price per KM', 'ecab-taxi-booking-manager' ); ?></label>
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
                                    <h2 class="mptbm_rent_editor_title"><?php esc_html_e( 'Manual Pricing', 'ecab-taxi-booking-manager' ); ?></h2>
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

                            <div class="mptbm_taxi_pricing_field1"
                                 id="mptbm_manual_routes"
                                 style="display: <?php echo ( $inclusive_manual_locations === 'on' ) ? 'block' : 'none'; ?>">
                                <div class="mptbm_taxi_pricing_row_head">
                                    <span class="mptbm_taxi_pricing_label"><i class="fas fa-route"></i> <?php esc_html_e( 'Manual Routes', 'ecab-taxi-booking-manager' ); ?></span>
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
                    if( $pro_active ){
                        $operation_area_pricing_display            = MP_Global_Function::get_post_info( $post_id, 'mptbm_display_operation_area_pricing', 'off' );
                        $operation_area_pricing_active             = $operation_area_pricing_display == 'off' ? 'none' : 'block';
                        $operation_area_pricing_checked            = $operation_area_pricing_display == 'off' ? '' : 'checked';
                    }else{
                        $operation_area_pricing_display            = 'on';
                        $operation_area_pricing_active             = $operation_area_pricing_display == 'off' ? 'none' : 'block';
                        $operation_area_pricing_checked            = $operation_area_pricing_display == 'off' ? '' : 'checked';
                    }

                    ?>
                    <div class="mptbm_taxi_ex_service_header mptbm_rent_editor_header">
                        <div class="mptbm_taxi_ex_service_title_group">
                            <h3 class="mptbm_rent_editor_title"><?php esc_html_e( 'Operation Area', 'ecab-taxi-booking-manager' ); ?></h3>
                            <p class="mptbm_rent_editor_subtitle">
                                <?php esc_html_e( 'Select operation area pricing rule for this taxi model.', 'ecab-taxi-booking-manager' ); ?>
                            </p>
                        </div>

                        <div class="mptbm_taxi_ex_service_toggle_wrapper">
                            <?php if ( $pro_active ) : ?>
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
                            <?php else : ?>
                                <span class="mptbm_pro_feature_notice">
                🔒 <?php esc_html_e( 'Pro Feature', 'ecab-taxi-booking-manager' ); ?>
            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mptbm_taxi_pricing_group" id="mptbm_taxi_operation_araea_pricing_group" style="display: <?php echo esc_attr( $operation_area_pricing_active );?>" >
                        <div class="mptbm_taxi_pricing_row_content">
                            <?php
                            if( $pro_active ){
                                self::manage_operation_area_pricing( $post_id, $price_based, $selected_operation_type, $all_operation_area_infos, $selected_operation_areas, $operation_area, $fixed_map_route_prices, $fixed_map_area_to_area_route_price_info, $merged_location_area, $location_zones, $fixed_zone_prices, $operation_zones );
                            }else{
                                self::manage_operation_area_pricing_free( $post_id, $price_based, $selected_operation_type, $all_operation_area_infos, $selected_operation_areas, $operation_area, $fixed_map_route_prices, $fixed_map_area_to_area_route_price_info, $merged_location_area, $location_zones, $fixed_zone_prices, $operation_zones );

                            }
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

                <div class="mptbm_rent_editor_wrapper">
                    <?php
                    wp_nonce_field( 'mptbm_extra_service_nonce', 'mptbm_extra_service_nonce' );
                    self::extra_service_display( $post_id );
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
                        <h4><?php esc_html_e( 'Combined (Distance + Duration) Based Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                        <p><?php esc_html_e( 'Price is calculated using both time and distance.', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php esc_html_e( '(Hourly Rate × Duration) + (KM Rate × Distance)', 'ecab-taxi-booking-manager' ); ?>
                        </div>
                    </div>
                <?php }
                if( $price_based === 'distance' ){
                    ?>

                    <div class="mptbm_pricing_rules_card">
                        <h4><?php esc_html_e( 'Distance Based Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
                        <p><?php esc_html_e( 'Only distance is used for calculation.', 'ecab-taxi-booking-manager' ); ?></p>
                        <div class="mptbm_pricing_rules_formula">
                            <?php esc_html_e( 'KM Rate × Distance', 'ecab-taxi-booking-manager' ); ?>
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
                            <?php esc_html_e( '(Hourly Rate × Duration) + (KM Rate × Distance)', 'ecab-taxi-booking-manager' ); ?>
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
                <div class="mptbm_operation_area_type_holder">
                    <div class="mptbm_settings_area " id="mptbm_operation_area_settings" >

                        <section class="mptbm-oa-section">

<!--                            <p class="mptbm-oa-label">--><?php //esc_html_e('Configuration', 'ecab-taxi-booking-manager'); ?><!--</p>-->
                            <p class="mptbm-oa-title"><?php esc_html_e('Choose the type of operation area', 'ecab-taxi-booking-manager'); ?></p>

                            <div class="mptbm-oa-grid">

                                <label class="mptbm-oa-card">
                                    <input type="radio" name="mptbm_operation_area_type" value=""
                                        <?php checked( $selected_operation_type, '' ); ?>>
                                    <div class="mptbm-oa-card-inner">
                                        <div class="mptbm-oa-info">
                                            <span class="dashicons dashicons-info"></span>
                                        </div>
                                        <div class="mptbm-oa-tooltip">
                                            <div class="mptbm-oa-tt-head"><?php esc_html_e( 'How it works', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Removes any zone restriction — this taxi accepts bookings from any location without geographic limits.', 'ecab-taxi-booking-manager' ); ?></p>
                                            <div class="mptbm-oa-tt-section"><?php esc_html_e( 'When to use', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Use when no geographic restrictions are needed and all pickups and dropoffs are allowed.', 'ecab-taxi-booking-manager' ); ?></p>
                                        </div>
                                        <div class="mptbm-oa-header">
                                            <span class="dashicons dashicons-location mptbm-oa-icon"></span>
                                            <div class="mptbm-oa-name"><?php esc_html_e( 'Unselect Operation Area Type', 'ecab-taxi-booking-manager' ); ?></div>
                                        </div>
                                        <div class="mptbm-oa-desc"><?php esc_html_e( 'Empty operation area', 'ecab-taxi-booking-manager' ); ?></div>
                                        <div class="mptbm-oa-select-row">
                                            <div class="mptbm-oa-dot"><div class="mptbm-oa-dot-inner"></div></div>
                                            <span class="mptbm-oa-dot-label mptbm-oa-lbl-off"><?php esc_html_e( 'Click to select', 'ecab-taxi-booking-manager' ); ?></span>
                                            <span class="mptbm-oa-dot-label mptbm-oa-lbl-on"><?php esc_html_e( 'Selected', 'ecab-taxi-booking-manager' ); ?></span>
                                        </div>
                                    </div>
                                </label>

                                <label class="mptbm-oa-card">
                                    <input type="radio" name="mptbm_operation_area_type" value="fixed-operation-area-type"
                                        <?php checked( $selected_operation_type, 'fixed-operation-area-type' ); ?>>
                                    <div class="mptbm-oa-card-inner">
                                        <div class="mptbm-oa-info">
                                            <span class="dashicons dashicons-info"></span>
                                        </div>
                                        <div class="mptbm-oa-tooltip">
                                            <div class="mptbm-oa-tt-head"><?php esc_html_e( 'How it works', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Both the pickup AND dropoff locations must fall within the defined operation zone.', 'ecab-taxi-booking-manager' ); ?></p>
                                            <div class="mptbm-oa-tt-section"><?php esc_html_e( 'When to use', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Best for services operating entirely within a city or district, e.g. rides within a city centre.', 'ecab-taxi-booking-manager' ); ?></p>
                                        </div>
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
                                        <?php checked( $selected_operation_type, 'fixed-map-operation-area-type' ); ?>>
                                    <div class="mptbm-oa-card-inner">
                                        <div class="mptbm-oa-info">
                                            <span class="dashicons dashicons-info"></span>
                                        </div>
                                        <div class="mptbm-oa-tooltip">
                                            <div class="mptbm-oa-tt-head"><?php esc_html_e( 'How it works', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Only the pickup location needs to be inside the zone — the dropoff can be anywhere.', 'ecab-taxi-booking-manager' ); ?></p>
                                            <div class="mptbm-oa-tt-section"><?php esc_html_e( 'When to use', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Ideal for airport transfers or city-centre pickups with open destinations.', 'ecab-taxi-booking-manager' ); ?></p>
                                        </div>
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
                                        <?php checked( $selected_operation_type, 'geo-fence-operation-area-type' ); ?>>
                                    <div class="mptbm-oa-card-inner">
                                        <div class="mptbm-oa-info">
                                            <span class="dashicons dashicons-info"></span>
                                        </div>
                                        <div class="mptbm-oa-tooltip">
                                            <div class="mptbm-oa-tt-head"><?php esc_html_e( 'How it works', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Draw a custom polygon boundary on the map. Bookings are restricted to that drawn area.', 'ecab-taxi-booking-manager' ); ?></p>
                                            <div class="mptbm-oa-tt-section"><?php esc_html_e( 'When to use', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Use when your service zone has an irregular shape that circles or rectangles cannot cover.', 'ecab-taxi-booking-manager' ); ?></p>
                                        </div>
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
                                        <?php checked( $selected_operation_type, 'geo-matched-operation-area-type' ); ?>>
                                    <div class="mptbm-oa-card-inner">
                                        <div class="mptbm-oa-info">
                                            <span class="dashicons dashicons-info"></span>
                                        </div>
                                        <div class="mptbm-oa-tooltip">
                                            <div class="mptbm-oa-tt-head"><?php esc_html_e( 'How it works', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Matches bookings using overlapping geographic zones for flexible multi-zone routing.', 'ecab-taxi-booking-manager' ); ?></p>
                                            <div class="mptbm-oa-tt-section"><?php esc_html_e( 'When to use', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Ideal for multi-zone services where coverage areas may overlap or share boundaries.', 'ecab-taxi-booking-manager' ); ?></p>
                                        </div>
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
                                ⚠ <span class="mptbm_empty_selected_area_text"><?php esc_html_e( 'You have not selected any operation area. For fixed map or fixed zone pricing setup, you need to select at least one operation area and save settings first.', 'ecab-taxi-booking-manager' ); ?></span>
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

                <div class="mptbm_operation_area_based"
                     id="mptbm_operation_area_based"
                     style=" display: <?php echo esc_attr( $area_option );?>"
                >
                    <div class="mptbm_operation_area_tab_holder">
                        <div class="mptbm_operation_area_based_pricing">
                            <h3 class="mptbm_rent_editor_title"><?php esc_html_e( 'Select Operation Area Based Pricing Model', 'ecab-taxi-booking-manager' ); ?></h3>
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

        public static function manage_operation_area_pricing_free( $post_id, $price_based, $selected_operation_type, $all_operation_area_infos, $selected_operation_areas, $operation_area, $fixed_map_route_prices, $fixed_map_area_to_area_route_price_info, $merged_location_area, $location_zones, $fixed_zone_prices, $operation_zones ){
            $is_operation_areas = 0;
            if( is_array( $selected_operation_areas ) && !empty( $selected_operation_areas ) ){
                $is_operation_areas = 1;
            }

            $all_operation_area_infos = array(
                array(
                    'post_id' => 201,
                    'operation_type' => 'fixed-operation-area-type',
                    'starting_location' => 'New York City, USA'
                ),
                array(
                    'post_id' => 202,
                    'operation_type' => 'fixed-operation-area-type',
                    'starting_location' => 'London, United Kingdom'
                ),

                array(
                    'post_id' => 203,
                    'operation_type' => 'fixed-operation-area-type',
                    'starting_location' => 'Toronto, Canada'
                ),
                array(
                    'post_id' => 204,
                    'operation_type' => 'fixed-operation-area-type',
                    'starting_location' => 'Dubai, United Arab Emirates'
                ),
                array(
                    'post_id' => 205,
                    'operation_type' => 'fixed-operation-area-type',
                    'starting_location' => 'Singapore'
                ),
                array(
                    'post_id' => 206,
                    'operation_type' => 'fixed-operation-area-type',
                    'starting_location' => 'Sydney, Australia'
                ),
            );

            $operation_zones = array(
                'post_201' => 'New York Zone (Operation Area)',
                'post_202' => 'London Central Zone (Location Area)',
                'post_203' => 'Toronto North Zone (Operation Area)',
                'post_204' => 'Dubai Business Bay Zone (Location Area)',
                'post_205' => 'Singapore Downtown Zone (Operation Area)',
                'post_206' => 'Sydney Harbour Zone (Operation Area)',
                'post_207' => 'Tokyo Metropolitan Zone (Operation Area)',
                'post_208' => 'Berlin City Zone (Location Area)',
                'post_209' => 'Paris Central Zone (Operation Area)',
                'post_210' => 'Kuala Lumpur Zone (Operation Area)',
                'post_211' => 'Doha West Bay Zone (Location Area)',
                'post_212' => 'Istanbul European Zone (Operation Area)'
            );

            $location_zones = array(
                'post_202' => 'London Central Zone (Location Area)',
                'post_204' => 'Dubai Business Bay Zone (Location Area)',
                'post_208' => 'Berlin City Zone (Location Area)',
                'post_211' => 'Doha West Bay Zone (Location Area)',
            );

            $fixed_map_route_prices = array(
                array(
                    'start_location' => 'post_208',
                    'end_location'   => 'post_202',
                    'price'          => 225
                ),

                array(
                    'start_location' => 'post_201',
                    'end_location'   => 'post_204',
                    'price'          => 70
                ),

                array(
                    'start_location' => 'post_202',
                    'end_location'   => 'post_208',
                    'price'          => 150
                ),
            );

            $area_route_prices = array(
                array(
                    'start_location' => 'post_201',
                    'end_location'   => 'post_212',
                    'price'          => 225
                ),

                array(
                    'start_location' => 'post_201',
                    'end_location'   => 'post_211',
                    'price'          => 70
                ),

                array(
                    'start_location' => 'post_203',
                    'end_location'   => 'post_205',
                    'price'          => 150
                ),
            );

            ?>
            <div class="mptbm_taxi_pricing_field_free pro-locked"
                 id="mptbm_taxi_pricing_field_free"
                 style="display: block">

                <div class="mptbm_operation_area_type_holder">
                    <div class="mptbm_taxi_operation_area_title">
                        <h3 class="mptbm_taxi_pricing_label"><i class="fas fa-pencil-alt"></i> <?php esc_html_e('Operation Area', 'ecab-taxi-booking-manager'); ?></h3>
                    </div>
                    <div class="mptbm_settings_area " id="mptbm_operation_area_settings" >
                        <section class="mptbm-oa-section">

<!--                            <p class="mptbm-oa-label">--><?php //esc_html_e('Configuration', 'ecab-taxi-booking-manager'); ?><!--</p>-->
                            <p class="mptbm-oa-title"><?php esc_html_e('Choose the type of operation area', 'ecab-taxi-booking-manager'); ?></p>

                            <div class="mptbm-oa-grid">

                                <label class="mptbm-oa-card">
                                    <input type="radio" name="mptbm_operation_area_type" value="fixed-operation-area-type"
                                        <?php checked( $selected_operation_type, 'fixed-operation-area-type' ); ?>>
                                    <div class="mptbm-oa-card-inner">
                                        <div class="mptbm-oa-info">
                                            <span class="dashicons dashicons-info"></span>
                                        </div>
                                        <div class="mptbm-oa-tooltip">
                                            <div class="mptbm-oa-tt-head"><?php esc_html_e( 'How it works', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Both the pickup AND dropoff locations must fall within the defined operation zone.', 'ecab-taxi-booking-manager' ); ?></p>
                                            <div class="mptbm-oa-tt-section"><?php esc_html_e( 'When to use', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Best for services operating entirely within a city or district, e.g. rides within a city centre.', 'ecab-taxi-booking-manager' ); ?></p>
                                        </div>
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
                                        <?php checked( $selected_operation_type, 'fixed-map-operation-area-type' ); ?>>
                                    <div class="mptbm-oa-card-inner">
                                        <div class="mptbm-oa-info">
                                            <span class="dashicons dashicons-info"></span>
                                        </div>
                                        <div class="mptbm-oa-tooltip">
                                            <div class="mptbm-oa-tt-head"><?php esc_html_e( 'How it works', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Only the pickup location needs to be inside the zone — the dropoff can be anywhere.', 'ecab-taxi-booking-manager' ); ?></p>
                                            <div class="mptbm-oa-tt-section"><?php esc_html_e( 'When to use', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Ideal for airport transfers or city-centre pickups with open destinations.', 'ecab-taxi-booking-manager' ); ?></p>
                                        </div>
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
                                        <?php checked( $selected_operation_type, 'geo-fence-operation-area-type' ); ?>>
                                    <div class="mptbm-oa-card-inner">
                                        <div class="mptbm-oa-info">
                                            <span class="dashicons dashicons-info"></span>
                                        </div>
                                        <div class="mptbm-oa-tooltip">
                                            <div class="mptbm-oa-tt-head"><?php esc_html_e( 'How it works', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Draw a custom polygon boundary on the map. Bookings are restricted to that drawn area.', 'ecab-taxi-booking-manager' ); ?></p>
                                            <div class="mptbm-oa-tt-section"><?php esc_html_e( 'When to use', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Use when your service zone has an irregular shape that circles or rectangles cannot cover.', 'ecab-taxi-booking-manager' ); ?></p>
                                        </div>
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
                                        <?php checked( $selected_operation_type, 'geo-matched-operation-area-type' ); ?>>
                                    <div class="mptbm-oa-card-inner">
                                        <div class="mptbm-oa-info">
                                            <span class="dashicons dashicons-info"></span>
                                        </div>
                                        <div class="mptbm-oa-tooltip">
                                            <div class="mptbm-oa-tt-head"><?php esc_html_e( 'How it works', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Matches bookings using overlapping geographic zones for flexible multi-zone routing.', 'ecab-taxi-booking-manager' ); ?></p>
                                            <div class="mptbm-oa-tt-section"><?php esc_html_e( 'When to use', 'ecab-taxi-booking-manager' ); ?></div>
                                            <p><?php esc_html_e( 'Ideal for multi-zone services where coverage areas may overlap or share boundaries.', 'ecab-taxi-booking-manager' ); ?></p>
                                        </div>
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
                        if( empty( $all_operation_area_infos ) ){
                            $show_area = 'none';
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
                                    <?php echo esc_attr( $area_info['starting_location'] ); ?>
                                </button>

                            <?php endforeach; ?>

                        </div>

                        <?php if( $is_operation_areas === 0 ){?>
                            <div class="mptbm_empty_selected_area">
                                ⚠ <span class="mptbm_empty_selected_area_text"><?php esc_html_e( 'You have not selected any operation area. For fixed map or fixed zone pricing setup, you need to select at least one operation area and save settings first.', 'ecab-taxi-booking-manager' ); ?></span>
                            </div>
                        <?php }?>

                        <div class="mptbm_operation_area_create_link" style="display: <?php echo esc_attr( $show_area_create );?>">
                            <button class="mptbm_create_area_btn">
                                + <?php esc_html_e( 'Create Operation Area', 'ecab-taxi-booking-manager' ); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mptbm_operation_area_tab_holder">
                    <div class="mptbm_operation_area_based_pricing">
<!--                        <span class="mptbm_operation_area_based_pricing_title" > --><?php //esc_html_e('Select Operation Area Based Pricing', 'ecab-taxi-booking-manager'); ?><!--</span>-->
                        <h3 class="mptbm_rent_editor_title"><?php esc_html_e( 'Select Operation Area Based Pricing Model', 'ecab-taxi-booking-manager' ); ?></h3>
                    </div>

                    <div class="" style="display: flex; gap: 10px">
                        <div class="mptbm_taxi_pricing_tab_item_area active" id="mptbm_taxi_pricing_fixed_map_free" >
                            <span class="tab-icon">🚕</span>
                            <span class="tab-title"><?php esc_html_e('Fixed With Map', 'ecab-taxi-booking-manager'); ?></span>
                        </div>

                        <div class="mptbm_taxi_pricing_tab_item_area " id="mptbm_taxi_pricing_fixed_zone_free" >
                            <span class="tab-icon">📍</span>
                            <span class="tab-title"><?php esc_html_e('Fixed Zone', 'ecab-taxi-booking-manager'); ?></span>
                        </div>
                    </div>

                </div>

                <div class="mptbm_taxi_pricing_field">
                    <?php
                    $area_based_pricing = 'none';
                    if( !empty( $all_operation_area_infos ) && !empty( $selected_operation_areas ) ){
                        $area_based_pricing = '';
                    }

                    $operation_area_fixed_map_type = MP_Global_Function::get_post_info($post_id, 'mptbm_operation_area_fixed_map_type', []);
                    ?>

                    <div class="mptbm_taxi_area_pricing">
                        <?php
                        self::render_fixed_with_map_area_based_pricing_free();
                        ?>
                        <div class="mptbm_taxi_pricing_sub_section"
                             id="mptbm_fixed_map_area_pricing"
                             style="display: block">

                            <div class="mptbm_taxi_pricing_sub_header">
                                <h4><?php esc_html_e( 'Fixed Map Route Overrides', 'ecab-taxi-booking-manager' ); ?></h4>
                                <p><?php esc_html_e( 'Define fixed prices for specific routes when using "Fixed with Map" mode.', 'ecab-taxi-booking-manager' ); ?></p>
                            </div>

                            <div class="">
                                <div class="mptbm_operation_area_fixed_map_type_holder">
                                    <div class="mptbm_operation_area_fixed_map_type_tabs">
                                        <div class="mptbm_operation_area_fixed_map_type_tab active"
                                             data-operation-area-type="zone_to_location">
                                            <span class="dashicons dashicons-location-alt"></span>
                                            <span><?php esc_html_e( 'Zone To Location', 'ecab-taxi-booking-manager' ); ?></span>
                                        </div>
                                        <div class="mptbm_operation_area_fixed_map_type_tab "
                                             data-operation-area-type="zone_to_zone">
                                            <span class="dashicons dashicons-randomize"></span>
                                            <span><?php esc_html_e( 'Zone To Zone', 'ecab-taxi-booking-manager' ); ?></span>
                                        </div>
                                    </div>

                                    <div class="mptbm_operation_area_fixed_map_type_contents">
                                        <div class="mptbm_operation_area_fixed_map_type_content"
                                             id="mptbm_operation_area_fixed_map_zone_to_location"
                                             style="'display:block"
                                        >
                                            <?php
                                            self::render_fixed_with_map_price_rows_free( $fixed_map_route_prices, $operation_zones, 'mptbm_taxi_pricing_route_list', $location_zones );
                                            ?>
                                            <button type="button" class="mptbm_taxi_pricing_pink_btn mptbm_taxi_pricing_add_route_btn">+ <?php esc_html_e( 'Add New Route', 'ecab-taxi-booking-manager' ); ?></button>

                                        </div>
                                        <div class=" mptbm_operation_area_fixed_map_type_content"
                                             id="mptbm_operation_area_fixed_map_zone_to_zone"
                                             style="display:none"
                                        >
                                            <?php
//                                            self::render_fixed_with_map_zone_zone_price_free( $area_route_prices, $merged_location_area, 'mptbm_taxi_pricing_zone_to_zone_route_list', $operation_zones );
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
                                 <div class="mptbm_selected_operation_area"  style="display: <?php echo esc_attr( $area_based_pricing );?>" >
                                <?php
                                    self::render_fixed_zone_price_rows( $fixed_zone_prices, $merged_location_area, 'mptbm_taxi_pricing_zone_to_zone_route_list', $location_zones );
                                ?>
                                 </div>

                                <button type="button" class="mptbm_taxi_pricing_pink_btn mptbm_taxi_pricing_add_zone_btn">+ <?php esc_html_e( 'Add New Route', 'ecab-taxi-booking-manager' ); ?></button>
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
                 style="display: <?php echo ( $price_based === 'fixed_distance' ) ? 'block' : 'block'; ?>">
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

        public static function render_fixed_with_map_area_based_pricing_free( ){

            $area_based_pricing = array(
                'New York Zone' => array(
                    'fixed'    => 120,
                    'per_km'   => 15,
                    'per_hour' => 10
                ),

                'London Zone' => array(
                    'fixed'    => 200,
                    'per_km'   => 20,
                    'per_hour' => 12
                ),

                'Dubai Zone' => array(
                    'fixed'    => 300,
                    'per_km'   => 25,
                    'per_hour' => 15
                ),
            );

            $operation_zones = array(
                'New York Zone' => array(
                    'fixed'    => 120,
                    'per_km'   => 15,
                    'per_hour' => 10
                ),

                'London Zone' => array(
                    'fixed'    => 200,
                    'per_km'   => 20,
                    'per_hour' => 12
                ),

                'Toronto Zone' => array(
                    'fixed'    => 150,
                    'per_km'   => 18,
                    'per_hour' => 9
                ),

                'Dubai Zone' => array(
                    'fixed'    => 300,
                    'per_km'   => 25,
                    'per_hour' => 15
                ),

                'Singapore Zone' => array(
                    'fixed'    => 180,
                    'per_km'   => 22,
                    'per_hour' => 11
                ),

                'Sydney Zone' => array(
                    'fixed'    => 250,
                    'per_km'   => 30,
                    'per_hour' => 14
                ),

                'Tokyo Zone' => array(
                    'fixed'    => 220,
                    'per_km'   => 19,
                    'per_hour' => 13
                ),

                'Berlin Zone' => array(
                    'fixed'    => 400,
                    'per_km'   => 35,
                    'per_hour' => 20
                ),

                'Paris Zone' => array(
                    'fixed'    => 275,
                    'per_km'   => 28,
                    'per_hour' => 16
                ),

                'Kuala Lumpur Zone' => array(
                    'fixed'    => 350,
                    'per_km'   => 40,
                    'per_hour' => 18
                )
            );

            ?>

            <div class="mptbm_area_based_wrapper" id="mptbm_area_based_wrapper_free"
                 style="display: block">
                <div class="bg-light mActive" style="margin-top: 20px;" data-collapse="#mp_fixed_map_routes">
                    <h4><?php esc_html_e( 'Operation Area Based Price Set', 'ecab-taxi-booking-manager' ); ?></h4>
                    <span><?php esc_html_e( 'Set different pricing for each operation area based on transport type, distance, or time. Easily manage fixed, per km, and per hour rates without creating duplicate transports.', 'ecab-taxi-booking-manager' ); ?></span>
                </div>

                <div class="motbm_area_based_items">
                    <?php foreach ($area_based_pricing as $post_key => $values) :
                            ?>
                            <div class="motbm_area_based_row">

                                <select class="motbm_area_based_post">
                                    <option value=""><?php esc_html_e( 'Select Post', 'ecab-taxi-booking-manager' ); ?></option>

                                    <?php foreach ($operation_zones as $key => $area) : ?>
                                        <option value="<?php echo esc_attr($key); ?>"
                                            <?php selected($post_key, $key); ?>>
                                            <?php echo esc_html($key); ?>
                                        </option>
                                    <?php endforeach; ?>

                                </select>

                                <input type="number"
                                       class="motbm_area_based_fixed"
                                       value="<?php echo esc_attr($values['fixed'] ?? ''); ?>"
                                       placeholder="<?php esc_html_e( 'Fixed Price', 'ecab-taxi-booking-manager' ); ?>">

                                <input type="number"
                                       class="motbm_area_based_per_km"
                                       value="<?php echo esc_attr($values['per_km'] ?? ''); ?>"
                                       placeholder="<?php esc_html_e( 'Per KM', 'ecab-taxi-booking-manager' ); ?>">

                                <input type="number"
                                       class="motbm_area_based_per_hour"
                                       value="<?php echo esc_attr($values['per_hour'] ?? ''); ?>"
                                       placeholder="<?php esc_html_e( 'Per Hour', 'ecab-taxi-booking-manager' ); ?>">

                                <button type="button" class="motbm_area_based_remove">
                                    <?php esc_html_e( 'Remove', 'ecab-taxi-booking-manager' ); ?>
                                </button>

                            </div>

                        <?php endforeach; ?>
                </div>

                <button type="button" class="motbm_area_based_add">
                    + <?php esc_html_e( 'Add More', 'ecab-taxi-booking-manager' ); ?>
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

        public static function render_fixed_with_map_price_rows_free( $fixed_map_route_prices, $merged_location_area, $append_body, $location_zones ) {

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
                                <select class="mptbm_fixed_map_route_start_location">
                                    <?php foreach ($merged_location_area as $key => $label): ?>
                                        <option value="<?php echo $key; ?>"
                                            <?php selected($route['start_location'], $key); ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select class="mptbm_fixed_map_route_end_location">
                                    <?php foreach ($location_zones as $key => $label): ?>
                                        <option value="<?php echo $key; ?>"
                                            <?php selected($route['end_location'], $key); ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input
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
                }?>
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
                                    placeholder="<?php esc_html_e( 'e.g., 250 - F', 'ecab-taxi-booking-manager' ); ?>"
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
                        <input name="mptbm_location_terms_price[]" type="text" placeholder="<?php esc_html_e( 'e.g., 250 - F', 'ecab-taxi-booking-manager' ); ?>">
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

        // 3. Save / Update post
        public function save_post() {

            if (
                !isset($_POST['_wpnonce']) ||
                !wp_verify_nonce($_POST['_wpnonce'], 'save_mptbm_rent_nonce')
            ) {
                wp_die('Security check failed');
            }

            $post_id = intval($_POST['post_id']);
            $title   =  isset($_POST['post_title'] ) ? sanitize_text_field($_POST['post_title']) : 'TEst';

            $data = [
                'post_title'  => $title,
                'post_type'   => 'mptbm_rent',
                'post_status' => 'publish',
            ];

            if ($post_id) {
                $data['ID'] = $post_id;
                $post_id = wp_update_post($data);
            } else {
                $post_id = wp_insert_post($data);
            }

            // Save manual routes
            $terms_price_infos = array();
            $start_terms_location = isset($_POST['mptbm_terms_start_location']) ? array_map('sanitize_text_field', wp_unslash($_POST['mptbm_terms_start_location'])) : [];
            $end_terms_location = isset($_POST['mptbm_terms_end_location']) ? array_map('sanitize_text_field', wp_unslash($_POST['mptbm_terms_end_location'])) : [];
            $terms_price = isset($_POST['mptbm_location_terms_price']) ? array_map('sanitize_text_field', wp_unslash($_POST['mptbm_location_terms_price'])) : [];

            if (sizeof($start_terms_location) > 0 && sizeof($end_terms_location) > 0 && sizeof($terms_price) > 0) {
                $count = 0;
                foreach ($start_terms_location as $key => $location) {
                    if (isset($end_terms_location[$key]) && isset($terms_price[$key]) && $location && $end_terms_location[$key] && $terms_price[$key]) {
                        $terms_price_infos[$count]['start_location'] = $location;
                        $terms_price_infos[$count]['end_location'] = $end_terms_location[$key];
                        $terms_price_infos[$count]['price'] = $terms_price[$key];
                        $count++;
                    }
                }
            }
            update_post_meta($post_id, 'mptbm_terms_price_info', $terms_price_infos);

            wp_redirect(admin_url('admin.php?page=mptbm-rent-edit&post_id=' . $post_id . '&updated=1'));
            exit;
        }

        // 4. Redirect default WP editor → custom page
        public function redirect_default_editor() {

            global $pagenow;

            if (
                $pagenow === 'post.php' &&
                isset($_GET['post']) &&
                get_post_type($_GET['post']) === 'mptbm_rent'
            ) {

                // Allow old editor
                if (isset($_GET['editor']) && $_GET['editor'] === 'old') {
                    return;
                }

                $post_id = intval($_GET['post']);

                wp_redirect(
                    admin_url(
                        'admin.php?page=mptbm-rent-edit&post_id=' . $post_id
                    )
                );
                exit;
            }
        }

    }

    new MPTBM_Rent_Custom_Editor();
}