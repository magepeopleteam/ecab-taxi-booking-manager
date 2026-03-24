<?php
/*
* @Author 		hamidxazad@gmail.com
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.
if (!class_exists('MPTBM_Operation_Areas')) {
    class MPTBM_Operation_Areas
    {
        public function __construct()
        {
            add_action('add_meta_boxes', array($this, 'mptbm_operation_area_meta'));
            add_action('save_post', array($this, 'save_operate_areas_settings'));

            add_action('add_mptbm_settings_tab_content', [$this, 'ex_opration_setting']);
            add_action('save_post', [$this, 'save_operate_areas_tab_settings']);
        }
        public function mptbm_operation_area_meta()
        {
            $label = MPTBM_Function::get_name();
            add_meta_box('mp_meta_box_panel', $label . __(' > Operation Area' . '<span class="version"> V'.MPTBM_PLUGIN_VERSION.'</span>', 'ecab-taxi-booking-manager'), array($this, 'mptbm_operation_area'), 'mptbm_operate_areas', 'normal', 'high');           
        }
        public function mptbm_operation_area()
        {
            $post_id        = get_the_id();
            $location_three = MP_Global_Function::get_post_info($post_id, 'mptbm-starting-location-three', array());
            $coordinates_three = MP_Global_Function::get_post_info($post_id, 'mptbm-coordinates-three', array());
            $coordinates_two = MP_Global_Function::get_post_info($post_id, 'mptbm-coordinates-two', array());
            $coordinates_one = MP_Global_Function::get_post_info($post_id, 'mptbm-coordinates-one', array());
            $location_one = MP_Global_Function::get_post_info($post_id, 'mptbm-starting-location-one', array());
            $location_two = MP_Global_Function::get_post_info($post_id, 'mptbm-starting-location-two', array());
            $operation_type = MP_Global_Function::get_post_info($post_id, 'mptbm-operation-type');
            $mptbm_geo_fence_increase_price_by = MP_Global_Function::get_post_info($post_id, 'mptbm-geo-fence-increase_price_by');
            $mptbm_geo_fence_fixed_price_amount = MP_Global_Function::get_post_info($post_id, 'mptbm-geo-fence-fixed-price-amount');
            $mptbm_geo_fence_percentage_amount = MP_Global_Function::get_post_info($post_id, 'mptbm-geo-fence-percentage-amount');
            $mptbm_geo_fence_direction = MP_Global_Function::get_post_info($post_id, 'mptbm-geo-fence-direction');
            
            // Get map type setting
            $map_type = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'openstreetmap');
            if ($coordinates_three) {
?>
                <script>
                    jQuery(document).ready(function($) {
                        var coordinates = <?php echo wp_json_encode($coordinates_three); ?>;
                        var mapCanvasId = 'mptbm-map-canvas-three';
                        var mapAppendId = 'mptbm-coordinates-three';
                        <?php if ($map_type === 'openstreetmap'): ?>
                        iniOSMSavedMap(coordinates, mapCanvasId, mapAppendId);
                        <?php else: ?>
                        iniSavedtMap(coordinates, mapCanvasId, mapAppendId);
                        <?php endif; ?>
                    });
                </script>


            <?php
            }
            if ($coordinates_two) {
            ?>
                <script>
                    jQuery(document).ready(function($) {
                        var coordinates = <?php echo wp_json_encode($coordinates_two); ?>;
                        var mapCanvasId = 'mptbm-map-canvas-two';
                        var mapAppendId = 'mptbm-coordinates-two';
                        <?php if ($map_type === 'openstreetmap'): ?>
                        iniOSMSavedMap(coordinates, mapCanvasId, mapAppendId);
                        <?php else: ?>
                        iniSavedtMap(coordinates, mapCanvasId, mapAppendId);
                        <?php endif; ?>
                    });
                </script>


            <?php
            }
            if ($coordinates_one) {
            ?>
                <script>
                    jQuery(document).ready(function($) {
                        var coordinates = <?php echo wp_json_encode($coordinates_one); ?>;
                        var mapCanvasId = 'mptbm-map-canvas-one';
                        var mapAppendId = 'mptbm-coordinates-one';
                        <?php if ($map_type === 'openstreetmap'): ?>
                        iniOSMSavedMap(coordinates, mapCanvasId, mapAppendId);
                        <?php else: ?>
                        iniSavedtMap(coordinates, mapCanvasId, mapAppendId);
                        <?php endif; ?>
                    });
                </script>


            <?php
            }

            wp_nonce_field('mptbm_operate_areas', 'mptbm_operate_areas');
            ?>
            <div class="mpStyle mptbm_settings" id="mptbm_map_opperation_area">
                <div class="tabsContent" style="width: 100%;">
                    <div class="tabsItem">	
                        <section class="bg-light" >
                            <h6><?php esc_html_e('Operation Area Settings', 'ecab-taxi-booking-manager'); ?></h6>
                            <span><?php esc_html_e('Here you can set operation area', 'ecab-taxi-booking-manager'); ?></span>
                        </section>
                        
                        <section id="mptbm-operation-type-section">
                            <label class="label">
                                <div>
                                    <h6><?php esc_html_e('Select Operation Type', 'ecab-taxi-booking-manager'); ?></h6>
                                    <span class="desc"><?php MPTBM_Settings::info_text('mptbm_operation_area_type'); ?></span>
                                </div>
                                <select class="formControl" name="mptbm-operation-type" id="mptbm-operation-type" data-collapse-target>
                                    <option <?php echo esc_attr(empty($operation_type) || $operation_type == 'fixed-operation-area-type') ? 'selected' : ''; ?> data-option-target="#fixed-operation-area-type" value="fixed-operation-area-type"><?php esc_html_e('Single Operation Area', 'ecab-taxi-booking-manager'); ?></option>
                                    <option <?php echo esc_attr($operation_type == 'geo-fence-operation-area-type') ? 'selected' : ''; ?> data-option-target="#geo-fence-operation-area-type" value="geo-fence-operation-area-type"><?php esc_html_e('Intercity Operation Area', 'ecab-taxi-booking-manager'); ?></option>
                                </select>
                            </label>
                        </section>

                        
                        <section class="mptbm_geo_fence_settings <?php echo ($operation_type == 'geo-fence-operation-area-type') ? 'mActive' : '';  ?>" data-collapse="#geo-fence-operation-area-type">
                            <div class="mptbm_geo_fence_settings_map">
                                <div id="mptbm_start_location_one" class="mptbm_map_area padding">
                                    <div class="mptbm_starting_location">
                                        <h6><?php esc_html_e('Starting Location 1', 'ecab-taxi-booking-manager'); ?></h6>
                                        <input class="formControl" type="text" id="mptbm-starting-location-one" value="<?php echo esc_attr(!empty($location_one) ? $location_one : ''); ?>" autocomplete="off" placeholder="Enter a location" />
                                        <input class="formControl" type="hidden" name="mptbm-starting-location-one" id="mptbm-starting-location-one-hidden" />
                                        <input class="formControl" type="hidden" name="mptbm-coordinates-one" id="mptbm-coordinates-one" />
                                    </div>
                                    </br>
                                    <div id="mptbm-map-canvas-one" style="width: 100%; height: 600px;"></div>

                                </div>
                                <div id="mptbm_start_location_two" class="mptbm_map_area padding">
                                    <div class="mptbm_starting_location">
                                        <h6><?php esc_html_e('Starting Location 2', 'ecab-taxi-booking-manager'); ?></h6>

                                        <input class="formControl" type="text" id="mptbm-starting-location-two" value="<?php echo esc_attr(!empty($location_two) ? $location_two : ''); ?>" autocomplete="off" placeholder="Enter a location" />
                                        <input class="formControl" type="hidden" name="mptbm-starting-location-two" id="mptbm-starting-location-two-hidden" />
                                        <input class="formControl" type="hidden" name="mptbm-coordinates-two" id="mptbm-coordinates-two" />
                                    </div>
                                    </br>
                                    <div id="mptbm-map-canvas-two" style="width: 100%; height: 600px;"></div>

                                </div>

                            </div>
                            <div class="mptbm_geo_fence_settings_form">
                                <section>
                                    <label class="label">
                                        <div>
                                            <h6><?php esc_html_e('Increase Price By', 'ecab-taxi-booking-manager'); ?></h6>
                                            <span class="desc"><?php MPTBM_Settings::info_text('mptbm_operation_area_increase_price_by'); ?></span>
                                        </div>
                                        <select class="formControl" name="mptbm-geo-fence-increase-price-by" id="mptbm-geo-fence-increase-price-by" data-collapse-target>
                                            
                                            <option <?php echo esc_attr(empty($mptbm_geo_fence_increase_price_by) || $mptbm_geo_fence_increase_price_by == 'geo-fence-fixed-price') ? 'selected' : ''; ?> data-option-target data-option-target-multi="#geo-fence-fixed-price" value="geo-fence-fixed-price"><?php esc_html_e('Fixed Price', 'ecab-taxi-booking-manager'); ?></option>
                                            <option <?php echo esc_attr($mptbm_geo_fence_increase_price_by == 'geo-fence-percentage-price') ? 'selected' : ''; ?> data-option-target data-option-target-multi="#geo-fence-percentage-price" value="geo-fence-percentage-price"><?php esc_html_e('Percentage', 'ecab-taxi-booking-manager'); ?></option>
                                        </select>
                                    </label>
                                </section>
                                <section  data-collapse="#geo-fence-fixed-price">
                                    <label class="label">
                                        <div>
                                            <h6><?php esc_html_e('Fixed Price', 'ecab-taxi-booking-manager'); ?></h6>
                                            <span class="desc"><?php MPTBM_Settings::info_text('mptbm_increase_price_fixed'); ?></span>
                                        </div>
                                        <input class="formControl mp_price_validation" name="mptbm-geo-fence-fixed-price-amount" id="mptbm-geo-fence-fixed-price-amount" value="<?php echo esc_attr(!empty($mptbm_geo_fence_fixed_price_amount) ? $mptbm_geo_fence_fixed_price_amount : ''); ?>" type="text" placeholder="<?php esc_html_e('EX:10', 'ecab-taxi-booking-manager'); ?>" />
                                    </label>
                                </section>
                                <section style="display: none;" data-collapse="#geo-fence-percentage-price">
                                    <label class="label">
                                        <div>
                                            <h6><?php esc_html_e('Percentage', 'ecab-taxi-booking-manager'); ?></h6>
                                            <span class="desc"><?php MPTBM_Settings::info_text('mptbm_increase_price_percentage'); ?></span>
                                        </div>
                                        <input class="formControl mp_price_validation" name="mptbm-geo-fence-percentage-amount" id="mptbm-geo-fence-percentage-amount" value="<?php echo esc_attr(!empty($mptbm_geo_fence_percentage_amount) ? $mptbm_geo_fence_percentage_amount : ''); ?>" type="number" min="1" max="100" placeholder="<?php esc_attr_e('EX:10', 'ecab-taxi-booking-manager'); ?>" />
                                    </label>
                                </section>
                                <section >
                                    <label class="label">
                                        <div>
                                            <h6><?php esc_html_e('Direction', 'ecab-taxi-booking-manager'); ?></h6>
                                            <span class="desc"><?php MPTBM_Settings::info_text('mptbm_increase_price_direction'); ?></span>
                                        </div>
                                        <select class="formControl" name="mptbm-geo-fence-direction" id="mptbm-geo-fence-direction">
                                            <option <?php echo esc_attr(empty($mptbm_geo_fence_direction) || $mptbm_geo_fence_direction == 'geo-fence-one-direction') ? 'selected' : ''; ?> value="geo-fence-one-direction"><?php esc_html_e('One Direction (Origin &rarr; Dest)', 'ecab-taxi-booking-manager'); ?></option>
                                            <option <?php echo esc_attr(empty($mptbm_geo_fence_direction) || $mptbm_geo_fence_direction == 'geo-fence-both-direction') ? 'selected' : ''; ?> value="geo-fence-both-direction"><?php esc_html_e('Both Direction (Origin &harr; Dest)', 'ecab-taxi-booking-manager'); ?></option>
                                        </select>
                                    </label>
                                </section>
                            </div>
                        </section>
                        
                        <section class="mptbm_geo_fixed_operation_settings <?php echo ($operation_type != 'geo-fence-operation-area-type' && $operation_type != 'geo-matched-operation-area-type') ? 'mActive' : '';  ?>" id="" data-collapse="#fixed-operation-area-type">
                            <div id="mptbm_start_location_three" class="mptbm_map_area">
                                <label class="label mptbm_starting_location">
                                    <div>
                                        <h6><?php esc_html_e('Starting Location', 'ecab-taxi-booking-manager'); ?></h6>
                                        <span class="desc"><?php esc_html_e('Type here to get location name from the map', 'ecab-taxi-booking-manager'); ?></span>
                                    </div>
                                    <input class="formControl" type="text" id="mptbm-starting-location-three" value="<?php echo esc_attr(!empty($location_three) ? $location_three : ''); ?>" autocomplete="on" placeholder="Enter a location" />
                                    <input class="formControl" type="hidden" name="mptbm-starting-location-three" id="mptbm-starting-location-three-hidden" value="<?php echo esc_attr(!empty($location_three) ? $location_three : ''); ?>" />
                                    <input class="formControl" type="hidden" name="mptbm-coordinates-three" id="mptbm-coordinates-three" />
                                </label>
                                </br>
                                <div id="mptbm-map-canvas-three" style="width: 100%; height: 600px"></div>
                            </div>
                        </section>
                        
                        <section class="mptbm_geo_matched_operation_settings <?php echo ($operation_type == 'geo-matched-operation-area-type') ? 'mActive' : '';  ?>" data-collapse="#geo-matched-operation-area-type">
                            <div id="mptbm_start_location_four" class="mptbm_map_area">
                                <label class="label mptbm_starting_location">
                                    <div>
                                        <h6><?php esc_html_e('Operation Area', 'ecab-taxi-booking-manager'); ?></h6>
                                        <span class="desc"><?php esc_html_e('Draw the operation area on the map', 'ecab-taxi-booking-manager'); ?></span>
                                    </div>
                                    <input class="formControl" type="text" id="mptbm-starting-location-four" value="<?php echo esc_attr(!empty($location_four) ? $location_four : ''); ?>" autocomplete="on" placeholder="Enter a location" />
                                    <input class="formControl" type="hidden" name="mptbm-starting-location-four" id="mptbm-starting-location-four-hidden" value="<?php echo esc_attr(!empty($location_four) ? $location_four : ''); ?>" />
                                    <input class="formControl" type="hidden" name="mptbm-coordinates-four" id="mptbm-coordinates-four" />
                                </label>
                                </br>
                                <div id="mptbm-map-canvas-four" style="width: 100%; height: 600px"></div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
            
            <?php if ($map_type === 'openstreetmap'): ?>
            <!-- OpenStreetMap Initialization -->
            <style>
                #mptbm-map-canvas-one,
                #mptbm-map-canvas-two,
                #mptbm-map-canvas-three {
                    width: 100% !important;
                    height: 600px !important;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }
            </style>
            <script>
                jQuery(document).ready(function($) {
                    var geoLocationOne = { lat: 23.8103, lng: 90.4125 };
                    
                    // Wait for operation type selection to initialize maps
                    function initializeMapsBasedOnType() {
                        var selectedType = $('#mptbm-operation-type').val();
                        
                        // Small delay to ensure containers are visible
                        setTimeout(function() {
                            if (selectedType === 'geo-fence-operation-area-type') {
                                // Initialize intercity maps
                                <?php if (!$coordinates_one): ?>
                                if (document.getElementById('mptbm-map-canvas-one')) {
                                    InitOSMMapOne(geoLocationOne);
                                }
                                <?php endif; ?>
                                
                                <?php if (!$coordinates_two): ?>
                                if (document.getElementById('mptbm-map-canvas-two')) {
                                    InitOSMMapTwo(geoLocationOne);
                                }
                                <?php endif; ?>
                            } else if (selectedType === 'fixed-operation-area-type') {
                                // Initialize single operation area map
                                <?php if (!$coordinates_three): ?>
                                if (document.getElementById('mptbm-map-canvas-three')) {
                                    InitOSMMapFixed(geoLocationOne, '');
                                }
                                <?php endif; ?>
                            }
                        }, 300);
                    }
                    
                    // Initialize on page load
                    initializeMapsBasedOnType();
                    
                    // Re-initialize when operation type changes
                    $('#mptbm-operation-type').on('change', function() {
                        initializeMapsBasedOnType();
                    });
                });
            </script>
            <?php else: ?>
            <!-- Google Maps Initialization -->
            <script>
                jQuery(document).ready(function($) {
                    var geoLocationOne = new google.maps.LatLng(23.8103, 90.4125);
                    
                    // Wait for operation type selection to initialize maps
                    function initializeGoogleMapsBasedOnType() {
                        var selectedType = $('#mptbm-operation-type').val();
                        
                        // Small delay to ensure containers are visible
                        setTimeout(function() {
                            if (selectedType === 'geo-fence-operation-area-type') {
                                // Initialize intercity maps only if no saved coordinates
                                <?php if (!$coordinates_one): ?>
                                if (document.getElementById('mptbm-map-canvas-one')) {
                                    InitMapOne(geoLocationOne);
                                }
                                <?php endif; ?>
                                
                                <?php if (!$coordinates_two): ?>
                                if (document.getElementById('mptbm-map-canvas-two')) {
                                    InitMapTwo(geoLocationOne);
                                }
                                <?php endif; ?>
                            } else if (selectedType === 'fixed-operation-area-type') {
                                // Initialize single operation area map only if no saved coordinates
                                <?php if (!$coordinates_three): ?>
                                if (document.getElementById('mptbm-map-canvas-three')) {
                                    InitMapFixed(geoLocationOne, '');
                                }
                                <?php endif; ?>
                            }
                        }, 300);
                    }
                    
                    // Initialize on page load
                    initializeGoogleMapsBasedOnType();
                    
                    // Re-initialize when operation type changes
                    $('#mptbm-operation-type').on('change', function() {
                        initializeGoogleMapsBasedOnType();
                    });
                });
            </script>
            <?php endif; ?>
        <?php
        }



        public function save_operate_areas_settings($post_id)
        {
            if (!isset($_POST['mptbm_operate_areas']) || !wp_verify_nonce($_POST['mptbm_operate_areas'], 'mptbm_operate_areas') || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) {
                return;
            }

            if ('mptbm_operate_areas' !== get_post_type($post_id)) {
                return;
            }

            // Retrieve and sanitize the data



            $mptbm_operation_type = isset($_POST['mptbm-operation-type']) ? sanitize_text_field($_POST['mptbm-operation-type']) : '';

            if ($mptbm_operation_type === 'fixed-operation-area-type') {

                $mptbm_starting_location_three = isset($_POST['mptbm-starting-location-three']) ? sanitize_text_field($_POST['mptbm-starting-location-three']) : '';
                $mptbm_coordinates_three = isset($_POST['mptbm-coordinates-three']) ? sanitize_text_field($_POST['mptbm-coordinates-three']) : '';

                if (!empty($mptbm_coordinates_three) && !empty($mptbm_starting_location_three)) {
                    $mptbm_coordinates_three = explode(',', $mptbm_coordinates_three);

                    update_post_meta($post_id, 'mptbm-starting-location-three', $mptbm_starting_location_three);
                    update_post_meta($post_id, 'mptbm-coordinates-three', $mptbm_coordinates_three);
                    update_post_meta($post_id, 'mptbm-operation-type', $mptbm_operation_type);
                }
            } elseif ($mptbm_operation_type === 'geo-matched-operation-area-type') {

                $mptbm_starting_location_four = isset($_POST['mptbm-starting-location-four']) ? sanitize_text_field($_POST['mptbm-starting-location-four']) : '';
                $mptbm_coordinates_four = isset($_POST['mptbm-coordinates-four']) ? sanitize_text_field($_POST['mptbm-coordinates-four']) : '';

                if (!empty($mptbm_coordinates_four) && !empty($mptbm_starting_location_four)) {
                    $mptbm_coordinates_four = explode(',', $mptbm_coordinates_four);

                    update_post_meta($post_id, 'mptbm-starting-location-four', $mptbm_starting_location_four);
                    update_post_meta($post_id, 'mptbm-coordinates-four', $mptbm_coordinates_four);
                    update_post_meta($post_id, 'mptbm-operation-type', $mptbm_operation_type);
                }
            } else {

                $mptbm_starting_location_one = isset($_POST['mptbm-starting-location-one']) ? sanitize_text_field($_POST['mptbm-starting-location-one']) : '';

                $mptbm_coordinates_one = isset($_POST['mptbm-coordinates-one']) ? sanitize_text_field($_POST['mptbm-coordinates-one']) : '';

                $mptbm_starting_location_two = isset($_POST['mptbm-starting-location-two']) ? sanitize_text_field($_POST['mptbm-starting-location-two']) : '';

                $mptbm_coordinates_two = isset($_POST['mptbm-coordinates-two']) ? sanitize_text_field($_POST['mptbm-coordinates-two']) : '';
                update_post_meta($post_id, 'mptbm-operation-type', $mptbm_operation_type);
                if (!empty($mptbm_starting_location_one) && !empty($mptbm_starting_location_two) && !empty($mptbm_coordinates_one)  && !empty($mptbm_coordinates_two)) {
                    $mptbm_coordinates_one = explode(',', $mptbm_coordinates_one);
                    $mptbm_coordinates_two = explode(',', $mptbm_coordinates_two);
                    update_post_meta($post_id, 'mptbm-starting-location-one', $mptbm_starting_location_one);
                    update_post_meta($post_id, 'mptbm-starting-location-two', $mptbm_starting_location_two);

                    update_post_meta($post_id, 'mptbm-coordinates-one', $mptbm_coordinates_one);
                    update_post_meta($post_id, 'mptbm-coordinates-two', $mptbm_coordinates_two);
                }

                $mptbm_geo_fence_increase_price_by = isset($_POST['mptbm-geo-fence-increase-price-by']) ? sanitize_text_field($_POST['mptbm-geo-fence-increase-price-by']) : '';
                update_post_meta($post_id, 'mptbm-geo-fence-increase-price-by', $mptbm_geo_fence_increase_price_by);
                if ($mptbm_geo_fence_increase_price_by == "geo-fence-fixed-price") {
                    $mptbm_geo_fence_fixed_price_amount = isset($_POST['mptbm-geo-fence-fixed-price-amount']) ? sanitize_text_field($_POST['mptbm-geo-fence-fixed-price-amount']) : '';
                    update_post_meta($post_id, 'mptbm-geo-fence-fixed-price-amount', $mptbm_geo_fence_fixed_price_amount);
                } else {
                    $mptbm_geo_fence_percentage_amount = isset($_POST['mptbm-geo-fence-percentage-amount']) ? sanitize_text_field($_POST['mptbm-geo-fence-percentage-amount']) : '';
                    update_post_meta($post_id, 'mptbm-geo-fence-percentage-amount', $mptbm_geo_fence_percentage_amount);
                }
                $mptbm_geo_fence_direction = isset($_POST['mptbm-geo-fence-direction']) ? sanitize_text_field($_POST['mptbm-geo-fence-direction']) : '';
                update_post_meta($post_id, 'mptbm-geo-fence-direction', $mptbm_geo_fence_direction);
            }
        }

        public function ex_opration_setting($post_id)
        {
            $all_operation_area_infos = MPTBM_Query::query_operation_area_list('mptbm_operate_areas');
            $selected_operation_type = get_post_meta($post_id, 'mptbm_operation_area_type', true);
            $selected_operation_areas = get_post_meta($post_id, 'mptbm_selected_operation_areas', true);
            if (!is_array($selected_operation_areas)) {
                $selected_operation_areas = array();
            }
            
            // Get map type setting
            $map_type = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'openstreetmap');
        ?>
            <div class="tabsItem " data-tabs="#mptbm_setting_operation_area">
                <?php wp_nonce_field('mptbm_operate_areas_tab', 'mptbm_operate_areas_tab'); ?>
                <h2><?php esc_html_e('Operation Area Settings', 'ecab-taxi-booking-manager'); ?></h2>
                <p><?php esc_html_e('Here you can set operation area', 'ecab-taxi-booking-manager'); ?></p>
                <div class="mp_settings_area ">
                    <section>
                        <label class="label">
                            <div>
                                <h6><?php esc_html_e('Select Operation Type', 'ecab-taxi-booking-manager'); ?></h6>
                                <span class="desc"><?php esc_html_e('Choose the type of operation area', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                            <select class="formControl" name="mptbm_operation_area_type" id="mptbm_operation_area_type" data-collapse-target>
                                <option value=""><?php esc_html_e('Select Operation Type', 'ecab-taxi-booking-manager'); ?></option>
                                <option value="fixed-operation-area-type" <?php selected($selected_operation_type, 'fixed-operation-area-type'); ?>><?php esc_html_e('Fixed Operation Area (Both In)', 'ecab-taxi-booking-manager'); ?></option>
                                <option value="fixed-map-operation-area-type" <?php selected($selected_operation_type, 'fixed-map-operation-area-type'); ?>><?php esc_html_e('Fixed Map Operation Area (Pickup In)', 'ecab-taxi-booking-manager'); ?></option>
                                <option value="geo-fence-operation-area-type" <?php selected($selected_operation_type, 'geo-fence-operation-area-type'); ?>><?php esc_html_e('Geo Fence Operation Area', 'ecab-taxi-booking-manager'); ?></option>
                                <option value="geo-matched-operation-area-type" <?php selected($selected_operation_type, 'geo-matched-operation-area-type'); ?>><?php esc_html_e('Geo-Matched Operation Area', 'ecab-taxi-booking-manager'); ?></option>
                            </select>
                        </label>
                    </section>

                    <section id="fixed-operation-area-section" class="<?php echo ($selected_operation_type == 'fixed-operation-area-type') ? 'mActive' : ''; ?>" data-collapse="#fixed-operation-area-type">
                        <label class="label">
                            <div>
                                <h6><?php esc_html_e('Select Fixed Operation Areas', 'ecab-taxi-booking-manager'); ?></h6>
                                <span class="desc"><?php esc_html_e('Select multiple fixed operation areas (Both pickup and dropoff must be inside)', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                            <select class="formControl" name="mptbm_selected_operation_areas[]" id="mptbm_selected_operation_areas" multiple>
                                <?php
                                foreach ($all_operation_area_infos as $area_info) {
                                    if ($area_info['operation_type'] == 'fixed-operation-area-type') {
                                        $selected = in_array($area_info['post_id'], $selected_operation_areas) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($area_info['post_id']) . '" ' . $selected . '>' . esc_html(get_the_title($area_info['post_id'])) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </label>
                    </section>
                    
                    <section id="fixed-map-operation-area-section" class="<?php echo ($selected_operation_type == 'fixed-map-operation-area-type') ? 'mActive' : ''; ?>" data-collapse="#fixed-map-operation-area-type">
                        <label class="label">
                            <div>
                                <h6><?php esc_html_e('Select Fixed Map Operation Areas', 'ecab-taxi-booking-manager'); ?></h6>
                                <span class="desc"><?php esc_html_e('Select multiple fixed maps (Only pickup must be inside. If dropoff is also inside, fixed price/override applies)', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                            <select class="formControl" name="mptbm_selected_operation_areas[]" id="mptbm_selected_fixed_map_areas" multiple>
                                <?php
                                foreach ($all_operation_area_infos as $area_info) {
                                    if ($area_info['operation_type'] == 'fixed-operation-area-type') {
                                        $selected = in_array($area_info['post_id'], $selected_operation_areas) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($area_info['post_id']) . '" ' . $selected . '>' . esc_html(get_the_title($area_info['post_id'])) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </label>
                    </section>

                    <section id="geo-fence-operation-area-section" class="<?php echo ($selected_operation_type == 'geo-fence-operation-area-type') ? 'mActive' : ''; ?>" data-collapse="#geo-fence-operation-area-type">
                        <label class="label">
                            <div>
                                <h6><?php esc_html_e('Select Geo Fence Operation Area', 'ecab-taxi-booking-manager'); ?></h6>
                                <span class="desc"><?php esc_html_e('Select a geo fence operation area', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                            <select class="formControl" name="mptbm_selected_operation_areas[]" id="mptbm_selected_geo_fence_area">
                                <option value=""><?php esc_html_e('Select Geo Fence Area', 'ecab-taxi-booking-manager'); ?></option>
                                <?php
                                foreach ($all_operation_area_infos as $area_info) {
                                    if ($area_info['operation_type'] == 'geo-fence-operation-area-type') {
                                        $selected = in_array($area_info['post_id'], $selected_operation_areas) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($area_info['post_id']) . '" ' . $selected . '>' . esc_html(get_the_title($area_info['post_id'])) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </label>
                    </section>

                    <section id="geo-matched-operation-area-section" class="<?php echo ($selected_operation_type == 'geo-matched-operation-area-type') ? 'mActive' : ''; ?>" data-collapse="#geo-matched-operation-area-type">
                        <label class="label">
                            <div>
                                <h6><?php esc_html_e('Select Geo-Matched Operation Area', 'ecab-taxi-booking-manager'); ?></h6>
                                <span class="desc"><?php esc_html_e('Select a geo-matched operation area', 'ecab-taxi-booking-manager'); ?></span>
                            </div>
                            <select class="formControl" name="mptbm_selected_operation_areas[]" id="mptbm_selected_geo_matched_area">
                                <option value=""><?php esc_html_e('Select Fixed Operation Area', 'ecab-taxi-booking-manager'); ?></option>
                                <?php
                                foreach ($all_operation_area_infos as $area_info) {
                                    if ($area_info['operation_type'] == 'fixed-operation-area-type') {
                                        $selected = in_array($area_info['post_id'], $selected_operation_areas) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($area_info['post_id']) . '" ' . $selected . '>' . esc_html(get_the_title($area_info['post_id'])) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </label>
                    </section>

                    <div class="mp_settings_area_item">
                        <div id="selected-operation-area-maps">
                            <!-- Maps will be displayed here when operation areas are selected -->
                        </div>
                    </div>
                </div>
            </div>

            <script>
                jQuery(document).ready(function($) {
                    // Get map type from PHP
                    var mapType = '<?php echo esc_js($map_type); ?>';
                    
                    // Handle operation type change
                    $('#mptbm_operation_area_type').on('change', function() {
                        var selectedType = $(this).val();
                        if (selectedType == 'fixed-operation-area-type') {
                            $('#fixed-operation-area-section').addClass('mActive');
                            $('#fixed-map-operation-area-section').removeClass('mActive');
                            $('#geo-fence-operation-area-section').removeClass('mActive');
                            $('#geo-matched-operation-area-section').removeClass('mActive');
                            $('#mptbm_selected_geo_fence_area').prop('disabled', true);
                            $('#mptbm_selected_geo_matched_area').prop('disabled', true);
                            $('#mptbm_selected_fixed_map_areas').prop('disabled', true);
                            $('#mptbm_selected_operation_areas').prop('disabled', false);
                        } else if (selectedType == 'fixed-map-operation-area-type') {
                            $('#fixed-operation-area-section').removeClass('mActive');
                            $('#fixed-map-operation-area-section').addClass('mActive');
                            $('#geo-fence-operation-area-section').removeClass('mActive');
                            $('#geo-matched-operation-area-section').removeClass('mActive');
                            $('#mptbm_selected_geo_fence_area').prop('disabled', true);
                            $('#mptbm_selected_geo_matched_area').prop('disabled', true);
                            $('#mptbm_selected_operation_areas').prop('disabled', true);
                            $('#mptbm_selected_fixed_map_areas').prop('disabled', false);
                        } else if (selectedType == 'geo-fence-operation-area-type') {
                            $('#fixed-operation-area-section').removeClass('mActive');
                            $('#fixed-map-operation-area-section').removeClass('mActive');
                            $('#geo-fence-operation-area-section').addClass('mActive');
                            $('#geo-matched-operation-area-section').removeClass('mActive');
                            $('#mptbm_selected_operation_areas').prop('disabled', true);
                            $('#mptbm_selected_fixed_map_areas').prop('disabled', true);
                            $('#mptbm_selected_geo_matched_area').prop('disabled', true);
                            $('#mptbm_selected_geo_fence_area').prop('disabled', false);
                        } else if (selectedType == 'geo-matched-operation-area-type') {
                            $('#fixed-operation-area-section').removeClass('mActive');
                            $('#fixed-map-operation-area-section').removeClass('mActive');
                            $('#geo-fence-operation-area-section').removeClass('mActive');
                            $('#geo-matched-operation-area-section').addClass('mActive');
                            $('#mptbm_selected_operation_areas').prop('disabled', true);
                            $('#mptbm_selected_fixed_map_areas').prop('disabled', true);
                            $('#mptbm_selected_geo_fence_area').prop('disabled', true);
                            $('#mptbm_selected_geo_matched_area').prop('disabled', false);
                        } else {
                            $('#fixed-operation-area-section').removeClass('mActive');
                            $('#fixed-map-operation-area-section').removeClass('mActive');
                            $('#geo-fence-operation-area-section').removeClass('mActive');
                            $('#geo-matched-operation-area-section').removeClass('mActive');
                            $('#mptbm_selected_operation_areas').prop('disabled', true);
                            $('#mptbm_selected_fixed_map_areas').prop('disabled', true);
                            $('#mptbm_selected_geo_fence_area').prop('disabled', true);
                            $('#mptbm_selected_geo_matched_area').prop('disabled', true);
                        }
                        // Clear maps when operation type changes
                        $('#selected-operation-area-maps').empty();
                    }).trigger('change');

                    // Handle geo fence operation area selection
                    $('#mptbm_selected_geo_fence_area').on('change', function() {
                        var selectedAreaId = $(this).val();
                        var mapsContainer = $('#selected-operation-area-maps');
                        mapsContainer.empty();

                        if (selectedAreaId) {
                            var areaInfo = <?php echo wp_json_encode($all_operation_area_infos); ?>.find(function(area) {
                                return area.post_id == selectedAreaId;
                            });

                            if (areaInfo) {
                                var mapsWrapper = $('<div style="display: flex; justify-content: space-around;"></div>');
                                var hasMapOne = false;
                                var hasMapTwo = false;
                                var mapOneId, mapTwoId;
                                var formattedCoordinatesOne = [];
                                var formattedCoordinatesTwo = [];
                                
                                // Prepare Map One
                                if (areaInfo.coordinates_one && areaInfo.coordinates_one.length > 0) {
                                    var mapOneContainer = $('<div class="mptbm_geo_fence_settings_map" style="width: 49%; margin-right: 5px;"></div>');
                                    mapOneId = 'geo_fence_map_one_' + selectedAreaId;
                                    mapOneContainer.append($('<div class="mptbm_map_area padding" style="height: 600px; width: 100%;"></div>').attr('id', mapOneId));
                                    mapsWrapper.append(mapOneContainer);

                                    // Convert coordinates
                                    var coordinates = areaInfo.coordinates_one;
                                    for (var i = 0; i < coordinates.length; i += 2) {
                                        var lat = parseFloat(coordinates[i]);
                                        var lng = parseFloat(coordinates[i + 1]);
                                        if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                                            formattedCoordinatesOne.push(lat);
                                            formattedCoordinatesOne.push(lng);
                                        }
                                    }
                                    if (formattedCoordinatesOne.length > 0) {
                                        hasMapOne = true;
                                    }
                                }

                                // Prepare Map Two
                                if (areaInfo.coordinates_two && areaInfo.coordinates_two.length > 0) {
                                    var mapTwoContainer = $('<div class="mptbm_geo_fence_settings_map" style="width: 49%; margin-left: 5px;"></div>');
                                    mapTwoId = 'geo_fence_map_two_' + selectedAreaId;
                                    mapTwoContainer.append($('<div class="mptbm_map_area padding" style="height: 600px; width: 100%;"></div>').attr('id', mapTwoId));
                                    mapsWrapper.append(mapTwoContainer);

                                    // Convert coordinates
                                    var coordinates = areaInfo.coordinates_two;
                                    for (var i = 0; i < coordinates.length; i += 2) {
                                        var lat = parseFloat(coordinates[i]);
                                        var lng = parseFloat(coordinates[i + 1]);
                                        if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                                            formattedCoordinatesTwo.push(lat);
                                            formattedCoordinatesTwo.push(lng);
                                        }
                                    }
                                    if (formattedCoordinatesTwo.length > 0) {
                                        hasMapTwo = true;
                                    }
                                }
                                
                                // Append wrapper to container ONCE
                                if (hasMapOne || hasMapTwo) {
                                    mapsContainer.append(mapsWrapper);
                                    
                                    // Wait until containers have actual dimensions before initializing
                                    function waitForContainerAndInit(mapId, coordinates, isMapOne) {
                                        var attempts = 0;
                                        var maxAttempts = 50; // Increased for hidden tabs
                                        var initDone = false;
                                        
                                        function checkAndInit() {
                                            if (initDone) return;
                                            
                                            var elem = document.getElementById(mapId);
                                            if (elem && elem.offsetWidth > 0 && elem.offsetHeight > 0) {
                                                initDone = true;
                                                try {
                                                    if (mapType === 'openstreetmap') {
                                                        iniOSMSavedMap(coordinates, mapId, null);
                                                    } else {
                                                        iniSavedtMap(coordinates, mapId, null);
                                                    }
                                                } catch (e) {
                                                    console.error('Error initializing map', mapId, ':', e);
                                                }
                                            } else {
                                                attempts++;
                                                if (attempts < maxAttempts) {
                                                    setTimeout(checkAndInit, 200);
                                                } else {
                                                    var observer = new MutationObserver(function() {
                                                        checkAndInit();
                                                    });
                                                    if (elem && elem.parentElement) {
                                                        observer.observe(elem.parentElement, { attributes: true, childList: true, subtree: true });
                                                    }
                                                    // Also try on next user interaction
                                                    $(document).one('click', function() {
                                                        setTimeout(checkAndInit, 300);
                                                    });
                                                }
                                            }
                                        }
                                        
                                        setTimeout(checkAndInit, 100);
                                    }
                                    
                                    if (hasMapOne) {
                                        waitForContainerAndInit(mapOneId, formattedCoordinatesOne, true);
                                    }
                                    
                                    if (hasMapTwo) {
                                        waitForContainerAndInit(mapTwoId, formattedCoordinatesTwo, false);
                                    }
                                }
                            }
                        }
                    });

                    // Handle fixed and fixed map operation area selection
                    $('#mptbm_selected_operation_areas, #mptbm_selected_fixed_map_areas').on('change', function() {
                        var selectedAreas = $(this).val();
                        var mapsContainer = $('#selected-operation-area-maps');
                        mapsContainer.empty();

                        if (selectedAreas && selectedAreas.length > 0) {
                            selectedAreas.forEach(function(areaId) {
                                var areaInfo = <?php echo wp_json_encode($all_operation_area_infos); ?>.find(function(area) {
                                    return area.post_id == areaId;
                                });

                                if (areaInfo && areaInfo.coordinates_three && areaInfo.coordinates_three.length > 0) {
                                    var mapContainer = $('<div class="mptbm_map_area padding" style="width: 100%; height: 600px; margin-bottom: 20px;"></div>');
                                    var mapId = 'map_' + areaId;
                                    mapContainer.attr('id', mapId);
                                    mapsContainer.append(mapContainer);

                                    // Convert coordinates to the format expected by iniSavedtMap
                                    var coordinates = areaInfo.coordinates_three;
                                    var formattedCoordinates = [];
                                    for (var i = 0; i < coordinates.length; i += 2) {
                                        var lat = parseFloat(coordinates[i]);
                                        var lng = parseFloat(coordinates[i + 1]);
                                        if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                                            formattedCoordinates.push(lat);
                                            formattedCoordinates.push(lng);
                                        }
                                    }

                                    if (formattedCoordinates.length > 0) {
                                        // Wait until container has actual dimensions before initializing
                                        (function(id, coords) {
                                            var attempts = 0;
                                            var maxAttempts = 50;
                                            var initDone = false;
                                            
                                            function checkAndInit() {
                                                if (initDone) return;
                                                
                                                var elem = document.getElementById(id);
                                                if (elem && elem.offsetWidth > 0 && elem.offsetHeight > 0) {
                                                    initDone = true;
                                                    try {
                                                        if (mapType === 'openstreetmap') {
                                                            iniOSMSavedMap(coords, id, null);
                                                        } else {
                                                            iniSavedtMap(coords, id, null);
                                                        }
                                                    } catch (e) {
                                                        console.error('Error initializing map', id, ':', e);
                                                    }
                                                } else {
                                                    attempts++;
                                                    if (attempts < maxAttempts) {
                                                        setTimeout(checkAndInit, 200);
                                                    } else {
                                                        var observer = new MutationObserver(function() {
                                                            checkAndInit();
                                                        });
                                                        if (elem && elem.parentElement) {
                                                            observer.observe(elem.parentElement, { attributes: true, childList: true, subtree: true });
                                                        }
                                                        $(document).one('click', function() {
                                                            setTimeout(checkAndInit, 300);
                                                        });
                                                    }
                                                }
                                            }
                                            
                                            setTimeout(checkAndInit, 100);
                                        })(mapId, formattedCoordinates);
                                    }
                                }
                            });
                        }
                    });

                    // Handle geo-matched operation area selection
                    $('#mptbm_selected_geo_matched_area').on('change', function() {
                        var selectedAreaId = $(this).val();
                        var mapsContainer = $('#selected-operation-area-maps');
                        mapsContainer.empty();

                        if (selectedAreaId) {
                            var areaInfo = <?php echo wp_json_encode($all_operation_area_infos); ?>.find(function(area) {
                                return area.post_id == selectedAreaId;
                            });

                            if (areaInfo && areaInfo.coordinates_three && areaInfo.coordinates_three.length > 0) {
                                var mapContainer = $('<div class="mptbm_map_area padding" style="width: 100%; height: 600px; margin-bottom: 20px;"></div>');
                                var mapId = 'geo_matched_map_' + selectedAreaId;
                                mapContainer.attr('id', mapId);
                                mapsContainer.append(mapContainer);

                                // Convert coordinates to the format expected by iniSavedtMap
                                var coordinates = areaInfo.coordinates_three;
                                var formattedCoordinates = [];
                                for (var i = 0; i < coordinates.length; i += 2) {
                                    var lat = parseFloat(coordinates[i]);
                                    var lng = parseFloat(coordinates[i + 1]);
                                    if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                                        formattedCoordinates.push(lat);
                                        formattedCoordinates.push(lng);
                                    }
                                }

                                    if (formattedCoordinates.length > 0) {
                                        // Wait until container has actual dimensions before initializing
                                        (function(id, coords) {
                                            var attempts = 0;
                                            var maxAttempts = 50;
                                            var initDone = false;
                                            
                                            function checkAndInit() {
                                                if (initDone) return;
                                                
                                                var elem = document.getElementById(id);
                                                if (elem && elem.offsetWidth > 0 && elem.offsetHeight > 0) {
                                                    initDone = true;
                                                    try {
                                                        if (mapType === 'openstreetmap') {
                                                            iniOSMSavedMap(coords, id, null);
                                                        } else {
                                                            iniSavedtMap(coords, id, null);
                                                        }
                                                    } catch (e) {
                                                        console.error('Error initializing map', id, ':', e);
                                                    }
                                                } else {
                                                    attempts++;
                                                    if (attempts < maxAttempts) {
                                                        setTimeout(checkAndInit, 200);
                                                    } else {
                                                        var observer = new MutationObserver(function() {
                                                            checkAndInit();
                                                        });
                                                        if (elem && elem.parentElement) {
                                                            observer.observe(elem.parentElement, { attributes: true, childList: true, subtree: true });
                                                        }
                                                        $(document).one('click', function() {
                                                            setTimeout(checkAndInit, 300);
                                                        });
                                                    }
                                                }
                                            }
                                            
                                            setTimeout(checkAndInit, 100);
                                        })(mapId, formattedCoordinates);
                                    }
                            }
                        }
                    });

                    // Trigger change events to show initial maps if there are selected areas
                    if ($('#mptbm_operation_area_type').val() === 'fixed-operation-area-type') {
                        $('#mptbm_selected_operation_areas').trigger('change');
                    } else if ($('#mptbm_operation_area_type').val() === 'fixed-map-operation-area-type') {
                        $('#mptbm_selected_fixed_map_areas').trigger('change');
                    } else if ($('#mptbm_operation_area_type').val() === 'geo-fence-operation-area-type') {
                        $('#mptbm_selected_geo_fence_area').trigger('change');
                    } else if ($('#mptbm_operation_area_type').val() === 'geo-matched-operation-area-type') {
                        $('#mptbm_selected_geo_matched_area').trigger('change');
                    }
                });
            </script>
        <?php
        }
        public function save_operate_areas_tab_settings($post_id)
        {
            if (!isset($_POST['mptbm_operate_areas_tab']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mptbm_operate_areas_tab'])), 'mptbm_operate_areas_tab') && defined('DOING_AUTOSAVE') && DOING_AUTOSAVE && !current_user_can('edit_post', $post_id)) {
                return;
            }
            if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
                // Save operation type
                $operation_type = isset($_POST['mptbm_operation_area_type']) ? sanitize_text_field($_POST['mptbm_operation_area_type']) : '';
                update_post_meta($post_id, 'mptbm_operation_area_type', $operation_type);

                // Save selected operation areas
                $selected_areas = isset($_POST['mptbm_selected_operation_areas']) ? array_map('intval', $_POST['mptbm_selected_operation_areas']) : array();
                
                // For geo-matched operation area, ensure only one area is selected
                if ($operation_type === 'geo-matched-operation-area-type' && count($selected_areas) > 1) {
                    $selected_areas = array_slice($selected_areas, 0, 1); // Keep only the first selected area
                }
                
                update_post_meta($post_id, 'mptbm_selected_operation_areas', $selected_areas);
            }
        }
    }
    new MPTBM_Operation_Areas();
}
