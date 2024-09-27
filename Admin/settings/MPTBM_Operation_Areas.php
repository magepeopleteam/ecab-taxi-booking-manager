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
            if ($coordinates_three) {
?>
                <script>
                    jQuery(document).ready(function($) {
                        var coordinates = <?php echo wp_json_encode($coordinates_three); ?>;
                        var mapCanvasId = 'mptbm-map-canvas-three';
                        var mapAppendId = 'mptbm-coordinates-three';
                        iniSavedtMap(coordinates, mapCanvasId, mapAppendId);
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
                        iniSavedtMap(coordinates, mapCanvasId, mapAppendId);
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
                        iniSavedtMap(coordinates, mapCanvasId, mapAppendId);
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
                                    <span class="desc"><?php MPTBM_Settings::info_text('mptbm_price_based'); ?></span>
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
                                    <div id="mptbm-map-canvas-one"></div>

                                </div>
                                <div id="mptbm_start_location_two" class="mptbm_map_area padding">
                                    <div class="mptbm_starting_location">
                                        <h6><?php esc_html_e('Starting Location 2', 'ecab-taxi-booking-manager'); ?></h6>

                                        <input class="formControl" type="text" id="mptbm-starting-location-two" value="<?php echo esc_attr(!empty($location_two) ? $location_two : ''); ?>" autocomplete="off" placeholder="Enter a location" />
                                        <input class="formControl" type="hidden" name="mptbm-starting-location-two" id="mptbm-starting-location-two-hidden" />
                                        <input class="formControl" type="hidden" name="mptbm-coordinates-two" id="mptbm-coordinates-two" />
                                    </div>
                                    </br>
                                    <div id="mptbm-map-canvas-two"></div>

                                </div>

                            </div>
                            <div class="mptbm_geo_fence_settings_form">
                                <section>
                                    <label class="label">
                                        <div>
                                            <h6><?php esc_html_e('Increase Price By', 'ecab-taxi-booking-manager'); ?></h6>
                                            <span class="desc"><?php MPTBM_Settings::info_text('mptbm_price_based'); ?></span>
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
                                            <span class="desc"><?php MPTBM_Settings::info_text('mptbm_initial_price'); ?></span>
                                        </div>
                                        <input class="formControl mp_price_validation" name="mptbm-geo-fence-fixed-price-amount" id="mptbm-geo-fence-fixed-price-amount" value="<?php echo esc_attr(!empty($mptbm_geo_fence_fixed_price_amount) ? $mptbm_geo_fence_fixed_price_amount : ''); ?>" type="text" placeholder="<?php esc_html_e('EX:10', 'ecab-taxi-booking-manager'); ?>" />
                                    </label>
                                </section>
                                <section style="display: none;" data-collapse="#geo-fence-percentage-price">
                                    <label class="label">
                                        <div>
                                            <h6><?php esc_html_e('Percentage', 'ecab-taxi-booking-manager'); ?></h6>
                                            <span class="desc"><?php MPTBM_Settings::info_text('mptbm_initial_price'); ?></span>
                                        </div>
                                        <input class="formControl mp_price_validation" name="mptbm-geo-fence-percentage-amount" id="mptbm-geo-fence-percentage-amount" value="<?php echo esc_attr(!empty($mptbm_geo_fence_percentage_amount) ? $mptbm_geo_fence_percentage_amount : ''); ?>" type="number" min="1" max="100" placeholder="<?php esc_attr_e('EX:10', 'ecab-taxi-booking-manager'); ?>" />
                                    </label>
                                </section>
                                <section >
                                    <label class="label">
                                        <div>
                                            <h6><?php esc_html_e('Direction', 'ecab-taxi-booking-manager'); ?></h6>
                                            <span class="desc"><?php MPTBM_Settings::info_text('mptbm_price_based'); ?></span>
                                        </div>
                                        <select class="formControl" name="mptbm-geo-fence-direction" id="mptbm-geo-fence-direction">
                                            <option <?php echo esc_attr(empty($mptbm_geo_fence_direction) || $mptbm_geo_fence_direction == 'geo-fence-one-direction') ? 'selected' : ''; ?> value="geo-fence-one-direction"><?php esc_html_e('One Direction (Origin &rarr; Dest)', 'ecab-taxi-booking-manager'); ?></option>
                                            <option <?php echo esc_attr(empty($mptbm_geo_fence_direction) || $mptbm_geo_fence_direction == 'geo-fence-both-direction') ? 'selected' : ''; ?> value="geo-fence-both-direction"><?php esc_html_e('Both Direction (Origin &harr; Dest)', 'ecab-taxi-booking-manager'); ?></option>
                                        </select>
                                    </label>
                                </section>
                            </div>
                        </section>
                        
                        <section class="mptbm_geo_fixed_operation_settings <?php echo ($operation_type != 'geo-fence-operation-area-type') ? 'mActive' : '';  ?>" id="" data-collapse="#fixed-operation-area-type">
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
                    </div>
                </div>
            </div>
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
        ?>
            <div class="tabsItem " data-tabs="#mptbm_setting_operation_area">
                <?php wp_nonce_field('mptbm_operate_areas_tab', 'mptbm_operate_areas_tab'); ?>
                <h2><?php esc_html_e('Operation Area Settings', 'ecab-taxi-booking-manager'); ?></h2>
				<p><?php esc_html_e('Here you can set operation area', 'ecab-taxi-booking-manager'); ?></p>
                <div class="mp_settings_area ">
                    <section>
                        <label class="label">
                            <div>
                                <h6><?php esc_html_e('Select operation area', 'ecab-taxi-booking-manager'); ?></h6>
                                <span class="desc"><?php MPTBM_Settings::info_text('mptbm_extra_services_id'); ?></span>
                            </div>
                            <select class="formControl" name="mptbm_tranport_selected_operation_area" id="mptbm_tranport_selected_operation_area" data-collapse-target>
                                <option value=0>Please Select...</option>
                                <?php if (sizeof($all_operation_area_infos) > 0) {
                                    foreach ($all_operation_area_infos as $area_info) { ?>
                                        <option <?php echo esc_attr(get_post_meta($post_id, "mptbm_tranport_selected_operation_area", true) == $area_info['post_id'] ? 'selected' : ''); ?> data-option-target="#operation-area-type_<?php echo esc_attr($area_info['post_id']); ?>" value="<?php echo esc_attr($area_info['post_id']); ?>"><?php echo esc_html(get_the_title($area_info['post_id'])); ?></option>
                                <?php }
                                } ?>
                            </select>
                        </label>
                    </section>
                    
                    <div class="mp_settings_area_item">
                        <?php foreach ($all_operation_area_infos as $operation_info) : ?>
                            <section class="operation-info" data-collapse="#operation-area-type_<?php echo esc_attr($operation_info['post_id']); ?>">
                                <?php if ($operation_info['operation_type'] !== 'fixed-operation-area-type') : ?>
                                    <div style="display: flex; justify-content:space-around">
                                        <?php if ($operation_info['coordinates_one']) : ?>
                                            <div class="mptbm_geo_fence_settings_map" style="width: 49%; margin-right: 5px;">
                                                <div id="geo-fence-location-one_<?php echo $operation_info['post_id']; ?>" class="mptbm_map_area padding" style="height: 600px;width:100%"></div>
                                                <script>
                                                    jQuery(document).ready(function($) {
                                                        var coordinates = <?php echo wp_json_encode($operation_info['coordinates_one']); ?>;
                                                        var mapCanvasId = "geo-fence-location-one_<?php echo $operation_info['post_id']; ?>";
                                                        var mapAppendId = null;
                                                        iniSavedtMap(coordinates, mapCanvasId, mapAppendId);
                                                    });
                                                </script>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($operation_info['coordinates_two']) : ?>
                                            <div class="mptbm_geo_fence_settings_map" style="width: 49%; margin-left: 5px;">
                                                <div id="geo-fence-location-two_<?php echo $operation_info['post_id']; ?>" class="mptbm_map_area padding" style="height: 600px;width:100%"></div>
                                                <script>
                                                    jQuery(document).ready(function($) {
                                                        var coordinates = <?php echo wp_json_encode($operation_info['coordinates_two']); ?>;
                                                        var mapCanvasId = 'geo-fence-location-two_<?php echo $operation_info['post_id']; ?>';
                                                        var mapAppendId = null;
                                                        iniSavedtMap(coordinates, mapCanvasId, mapAppendId);
                                                    });
                                                </script>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                    <?php if (isset($operation_info['coordinates_three'])) : ?>
                                        <div id="<?php echo esc_attr($operation_info['post_id']); ?>" style="width: 100%; height: 600px">
                                        </div>
                                        <script>
                                            jQuery(document).ready(function($) {
                                                var coordinates = <?php echo wp_json_encode($operation_info['coordinates_three']); ?>;
                                                var mapCanvasId = <?php echo wp_json_encode($operation_info['post_id']); ?>;
                                                var mapAppendId = null;
                                                iniSavedtMap(coordinates, mapCanvasId, mapAppendId);
                                            });
                                        </script>
                                    <?php endif; ?>
                                </section>
                            <?php endforeach; ?>
                    </div>
                </div>
            </div>
    <?php
        }
        public function save_operate_areas_tab_settings($post_id)
        {

            if (!isset($_POST['mptbm_operate_areas_tab']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mptbm_operate_areas_tab'])), 'mptbm_operate_areas_tab') && defined('DOING_AUTOSAVE') && DOING_AUTOSAVE && !current_user_can('edit_post', $post_id)) {
                return;
            }
            if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
                $ex_id = isset($_POST['mptbm_tranport_selected_operation_area']) ? sanitize_text_field($_POST['mptbm_tranport_selected_operation_area']) : $post_id;
                update_post_meta($post_id, 'mptbm_tranport_selected_operation_area', $ex_id);
            }
        }
    }
    new MPTBM_Operation_Areas();
}
