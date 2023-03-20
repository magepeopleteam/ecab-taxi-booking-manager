<?php
/*
* @Author 		magePeople
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.
if (!class_exists('MPTBM_Transport_Search')) {
    class MPTBM_Transport_Search {
        public function __construct() {
            add_action('mptbm_transport_search', [$this, 'transport_search'], 10, 1);
            add_action('mptbm_transport_search_form', [$this, 'transport_search_form'], 10, 2);
            add_action('wp_ajax_get_mptbm_map_search_result', [$this, 'get_mptbm_map_search_result']);
            add_action('wp_ajax_nopriv_get_mptbm_map_search_result', [$this, 'get_mptbm_map_search_result']);
            add_action('wp_ajax_get_mptbm_end_place', [$this, 'get_mptbm_end_place']);
            add_action('wp_ajax_nopriv_get_mptbm_end_place', [$this, 'get_mptbm_end_place']);
        }

        public function transport_search($params) {
            $price_based = $params['price_based'] ?: 'distance';
            ?>
            <div class="mpStyle ">
                <?php $this->transport_search_form('', $price_based); ?>
            </div>
            <?php
        }

        public function transport_search_form($post_id = '', $price_based = '') {
            $price_based = $post_id && $post_id > 0 ? MPTBM_Function::get_post_info($post_id, 'mptbm_price_based') : $price_based;
            ?>
            <div class="mpRow mptbm_map_form_area dLayout_xs">
                <div class="col_6  mpForm">
                    <input type="hidden" name="mptbm_price_based" value="<?php echo esc_attr($price_based); ?>"/>
                    <input type="hidden" name="mptbm_filter_post_id" value="<?php echo esc_attr($post_id); ?>"/>
                    <label class="fdColumn">
                        <input type="hidden" id="mptbm_map_start_date" value=""/>
                        <span class="fas fa-calendar-alt"><?php esc_html_e(' Select Date', 'mptbm_plugin'); ?></span>
                        <input type="text" class="formControl date_type" placeholder="<?php esc_html_e(' Select Date', 'mptbm_plugin'); ?>" value=""/>
                    </label>
                    <label class="fdColumn">
                        <span class="far fa-clock"><?php esc_html_e(' Select Time', 'mptbm_plugin'); ?></span>
                        <select id="mptbm_map_start_time" class="formControl">
                            <option selected><?php esc_html_e('Please Select Time', 'mptbm_plugin'); ?></option>
                            <option value="9:00"><?php esc_html_e('9.00 AM', 'mptbm_plugin'); ?></option>
                            <option value="9:15"><?php esc_html_e('9.15 AM', 'mptbm_plugin'); ?></option>
                            <option value="9:30"><?php esc_html_e('9.30 AM', 'mptbm_plugin'); ?></option>
                            <option value="9:45"><?php esc_html_e('9.45 AM', 'mptbm_plugin'); ?></option>
                            <option value="10:00"><?php esc_html_e('10.00 AM', 'mptbm_plugin'); ?></option>
                        </select>
                    </label>
                    <label class="fdColumn">
                        <span class="fas fa-map-marker-alt"><?php esc_html_e(' Start Location', 'mptbm_plugin'); ?></span>
                        <?php if ($price_based == 'manual') { ?>
                            <?php $all_start_locations = MPTBM_Function::get_manual_start_location($post_id); ?>
                            <select id="mptbm_manual_start_place" class="formControl mptbm_map_start_place">
                                <option selected disabled><?php esc_html_e(' Select start Location', 'mptbm_plugin'); ?></option>
                                <?php if (sizeof($all_start_locations) > 0) {
                                    foreach ($all_start_locations as $start_location) {
                                        ?>
                                        <option value="<?php echo esc_attr($start_location); ?>"><?php echo esc_html($start_location); ?></option>
                                        <?php
                                    }
                                } ?>
                            </select>
                            <?php //echo '<pre>';print_r();echo '</pre>'; ?>
                        <?php } else { ?>
                            <input type="text" id="mptbm_map_start_place" class="formControl mptbm_map_start_place" placeholder="<?php esc_html_e(' Enter start Location', 'mptbm_plugin'); ?>" value=""/>
                        <?php } ?>
                    </label>
                    <label class="fdColumn mptbm_manual_end_place">
                        <span class="fas fa-map-marker-alt"><?php esc_html_e(' Destination Location', 'mptbm_plugin'); ?></span>
                        <?php if ($price_based == 'manual') { ?>
                            <select class="formControl mptbm_map_end_place">
                                <option selected disabled><?php esc_html_e(' Select Destination Location', 'mptbm_plugin'); ?></option>
                            </select>
                            <?php //echo '<pre>';print_r();echo '</pre>'; ?>
                        <?php } else { ?>
                            <input type="text" id="mptbm_map_end_place" class="formControl mptbm_map_end_place" placeholder="<?php esc_html_e(' Enter end Location', 'mptbm_plugin'); ?>" value=""/>
                        <?php } ?>
                    </label>
                    <div class="divider"></div>
                    <button type="button" class="_themeButton_fullWidth" id="mptbm_get_vehicle"><?php esc_html_e(' Search', 'mptbm_plugin'); ?></button>
                </div>
                <div class="col_6 _pL">
                    <div id="mptbm_map_area"></div>
                </div>
            </div>
            <div class="mptbm_map_search_result mT">
            </div>
            <?php
        }

        public function get_mptbm_map_search_result() {
            $distance = $_COOKIE['mptbm_distance'] ?? '';
            $duration = $_COOKIE['mptbm_duration'] ?? '';
            if ($distance && $duration) {
                ?>
                <div class="all_filter_item">
                    <div class="flexWrap modern">
                        <?php
                        $post_id = MPTBM_Function::data_sanitize($_POST['post_id']);
                        if ($post_id > 0) {
                            //$this->product_item( $post_id );
                            $this->product_item_modify($post_id);
                        } else {
                            $price_based = MPTBM_Function::data_sanitize($_POST['price_based']);
                            $all_posts = MPTBM_Query::query_transport_list($price_based);
                            if ($all_posts->found_posts > 0) {
                                $posts = $all_posts->posts;
                                foreach ($posts as $post) {
                                    $post_id = $post->ID;
                                    $this->product_item($post_id);
                                }
                            }
                        }
                        ?>
                    </div>
                </div>
                <?php
            }
            die();
        }

        public function product_item($post_id) {
            $distance = $_COOKIE['mptbm_distance'] ?? '';
            $duration = $_COOKIE['mptbm_duration'] ?? '';
            $start_date = MPTBM_Function::data_sanitize($_POST['start_date']);
            $start_time = MPTBM_Function::data_sanitize($_POST['start_time']);
            $date = $start_date . ' ' . $start_time;
            $start_place = MPTBM_Function::data_sanitize($_POST['start_place']);
            $end_place = MPTBM_Function::data_sanitize($_POST['end_place']);
            $location_exit = MPTBM_Function::location_exit($post_id, $start_place, $end_place);
            if ($location_exit) {
                $product_id = MPTBM_Function::get_post_info($post_id, 'link_wc_product');
                $thumbnail = MPTBM_Function::get_image_url($post_id);
                $price = MPTBM_Function::get_price($post_id, $distance, $duration, $start_place, $end_place);
                $wc_price = MPTBM_Function::wc_price($post_id, $price);
                $raw_price = MPTBM_Function::price_convert_raw($wc_price);
                ?>
                <div class="filter_item mptbm_booking_item" data-placeholder>
                    <div class="bg_image_area" data-href="<?php echo get_the_permalink($post_id); ?>" data-placeholder>
                        <div data-bg-image="<?php echo esc_attr($thumbnail); ?>"></div>
                    </div>
                    <div class="fdColumn ttbm_list_details">
                        <h5 data-href="<?php echo get_the_permalink($post_id); ?>"><?php echo get_the_title($post_id); ?></h5>
                        <div class="divider"></div>
                        <p><span class="fas fa-map-marker-alt"></span>&nbsp;&nbsp;<strong><?php esc_html_e('Start Location', 'mptbm_plugin'); ?> : </strong><?php echo esc_html($start_place); ?></p>
                        <p><span class="fas fa-map-marker-alt"></span>&nbsp;&nbsp;<strong><?php esc_html_e('Destination Location', 'mptbm_plugin'); ?> : </strong> <?php echo esc_html($end_place); ?></p>
                        <h2 class="textTheme" data-main-price="<?php echo esc_attr($raw_price); ?>"> <?php echo MPTBM_Function::esc_html($wc_price); ?></h2>
                        <div class="dLayout_xs bgLight" data-collapse="#mptbm_collape_show_info_<?php echo esc_attr($post_id); ?>">
                            <ul class="list_inline_two">
                                <li class="justifyBetween"><h6><?php esc_html_e('Engine', 'mptbm_plugin'); ?> : </h6> <?php echo MPTBM_Function::get_post_info($post_id, 'mptbm_engine'); ?></li>
                                <li class="justifyBetween"><h6><?php esc_html_e('Length', 'mptbm_plugin'); ?> : </h6><?php echo MPTBM_Function::get_post_info($post_id, 'mptbm_length'); ?></li>
                                <li class="justifyBetween"><h6><?php esc_html_e('Interior Color', 'mptbm_plugin'); ?> : </h6><?php echo MPTBM_Function::get_post_info($post_id, 'mptbm_interior_color'); ?></li>
                                <li class="justifyBetween"><h6><?php esc_html_e('Exterior Color', 'mptbm_plugin'); ?> : </h6><?php echo MPTBM_Function::get_post_info($post_id, 'mptbm_exterior_color'); ?></li>
                                <li class="justifyBetween"><h6><?php esc_html_e('Power', 'mptbm_plugin'); ?> : </h6><?php echo MPTBM_Function::get_post_info($post_id, 'mptbm_power'); ?></li>
                                <li class="justifyBetween"><h6><?php esc_html_e('Transmission', 'mptbm_plugin'); ?> : </h6><?php echo MPTBM_Function::get_post_info($post_id, 'mptbm_transmission'); ?></li>
                                <li class="justifyBetween"><h6><?php esc_html_e('Fuel Type', 'mptbm_plugin'); ?> : </h6><?php echo MPTBM_Function::get_post_info($post_id, 'mptbm_fuel_type'); ?></li>
                                <li class="justifyBetween"><h6><?php esc_html_e('Extras', 'mptbm_plugin'); ?> : </h6><?php echo MPTBM_Function::get_post_info($post_id, 'mptbm_extras'); ?></li>
                            </ul>
                        </div>
                        <div class="divider"></div>
                        <form method="post" action="">
                            <input type="hidden" name="mptbm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
                            <input type="hidden" name="mptbm_start_place" value="<?php echo esc_attr($start_place); ?>"/>
                            <input type="hidden" name="mptbm_end_place" value="<?php echo esc_attr($end_place); ?>"/>
                            <input type="hidden" name="mptbm_date" value="<?php echo esc_attr($date); ?>"/>
                            <div class="justifyBetween">
                                <button type="button"
                                        class="_themeButton_xs w_150"
                                        data-collapse-target="#mptbm_collape_show_info_<?php echo esc_attr($post_id); ?>"
                                        data-open-text="<?php esc_html_e('Show Info', 'mptbm_plugin'); ?>"
                                        data-close-text="<?php esc_html_e('Less Info', 'mptbm_plugin'); ?>"
                                        data-open-icon="fa-angle-down"
                                        data-close-icon="fa-angle-up"
                                >
                                    <span class="fas fa-angle-down" data-icon></span>&nbsp;&nbsp;
                                    <span data-text><?php esc_html_e('Show Info', 'mptbm_plugin'); ?></span>
                                </button>
                                <button type="button" class="dButton_xs w_150" data-collapse-target="#mptbm_collape_select_<?php echo esc_attr($post_id); ?>"><span><?php esc_html_e('Select', 'mptbm_plugin'); ?></span></button>
                            </div>
                            <div data-collapse="#mptbm_collape_select_<?php echo esc_attr($post_id); ?>">
                                <?php $extra_services = MPTBM_Function::get_post_info($post_id, 'mptbm_extra_service_data', array()); ?>
                                <div class="mptbm_extra_service_area" data-placeholder>
                                    <table class="noShadow">
                                        <tbody>
                                        <?php foreach ($extra_services as $service) { ?>
                                            <?php
                                            $service_icon = array_key_exists('service_icon', $service) ? $service['service_icon'] : '';
                                            $service_name = array_key_exists('service_name', $service) ? $service['service_name'] : '';
                                            $service_price = array_key_exists('service_price', $service) ? $service['service_price'] : 0;
                                            $service_price = MPTBM_Function::wc_price($post_id, $service_price);
                                            $service_price_raw = MPTBM_Function::price_convert_raw($service_price);
                                            $description = array_key_exists('extra_service_description', $service) ? $service['extra_service_description'] : '';
                                            ?>
                                            <tr>
                                                <th>
                                                    <h4>
                                                        <?php if ($service_icon) { ?>
                                                            <span class="<?php echo esc_attr($service_icon); ?>"></span>
                                                        <?php } ?>
                                                        <?php echo MPTBM_Function::esc_html($service_name); ?>
                                                    </h4>
                                                    <?php
                                                    if ($description) {
                                                        $word_count = str_word_count($description);
                                                        if ($word_count > 16) {
                                                            $message = implode(" ", array_slice(explode(" ", $description), 0, 16));
                                                            $more_message = implode(" ", array_slice(explode(" ", $description), 16, $word_count));
                                                            $name_text = preg_replace("/[{}()<>+ ]/", '_', $service_name) . '_' . $post_id;
                                                            ?>
                                                            <p style="margin-top: 5px;">
                                                                <small>
                                                                    <?php echo esc_html($message); ?>
                                                                    <span data-collapse='#<?php echo esc_attr($name_text); ?>'><?php echo esc_html($more_message); ?></span>
                                                                    <span class="load_more_text" data-collapse-target="#<?php echo esc_attr($name_text); ?>">
															<?php esc_html_e('view more ', 'mptbm_plugin'); ?>
														</span>
                                                                </small>
                                                            </p>
                                                            <?php
                                                        } else {
                                                            ?>
                                                            <p style="margin-top: 5px;"><small><?php echo esc_html($description); ?></small></p>
                                                            <?php
                                                        }
                                                    }
                                                    ?>
                                                </th>
                                                <td class="textCenter"><?php echo MPTBM_Function::esc_html($service_price); ?></td>
                                                <td>
                                                    <label class="_allCenter_fRight selectCheckbox">
                                                        <input type="hidden" name="mptbm_extra_service[]" value=""/>
                                                        <input type="checkbox" data-extra-service-price="<?php echo esc_attr($service_price_raw); ?>" value="<?php echo MPTBM_Function::esc_html($service_name); ?>"/>
                                                        <span class="customCheckbox"><?php esc_html_e('Select', 'mptbm_plugin'); ?></span>
                                                    </label>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button class="_dButton_fRight mptbm_book_now" type="button">
                                    <span class="fas fa-cart-plus"></span>
                                    <?php esc_html_e('Add to Cart', 'mptbm_plugin'); ?>
                                </button>
                                <button type="submit" name="add-to-cart" value="<?php echo esc_html($product_id); ?>" class="dNone mptbm_add_to_cart">
                                    <?php esc_html_e('Add to Cart', 'mptbm_plugin'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php
            }
        }

        public function product_item_modify($post_id) {
            $distance = $_COOKIE['mptbm_distance'] ?? '';
            $duration = $_COOKIE['mptbm_duration'] ?? '';
            $start_date = MPTBM_Function::data_sanitize($_POST['start_date']);
            $start_time = MPTBM_Function::data_sanitize($_POST['start_time']);
            $date = $start_date . ' ' . $start_time;
            $start_place = MPTBM_Function::data_sanitize($_POST['start_place']);
            $end_place = MPTBM_Function::data_sanitize($_POST['end_place']);
            $location_exit = MPTBM_Function::location_exit($post_id, $start_place, $end_place);
            if ($location_exit) {
                $product_id = MPTBM_Function::get_post_info($post_id, 'link_wc_product');
                $thumbnail = MPTBM_Function::get_image_url($post_id);
                $price = MPTBM_Function::get_price($post_id, $distance, $duration, $start_place, $end_place);
                $wc_price = MPTBM_Function::wc_price($post_id, $price);
                $raw_price = MPTBM_Function::price_convert_raw($wc_price);

                ?>

                <div id="searched-item-details" class="mptbm_booking_item">

                    <div class="search-item">

                        <div class="step-container">
                            <div class="step">
                                <div class="step-circle completed"><span class="dashicons dashicons-yes"></span></div>
                                <!--<div class="step-label">Enter Ride Details</div>-->
                            </div>
                            <div class="connector"></div>
                            <div class="step">
                                <div class="step-circle active">2</div>
                                <!--<div class="step-label">Choose a vehicle</div>-->
                            </div>
                            <div class="connector"></div>
                            <div class="step">
                                <div class="step-circle default">3</div>
                                <!--<div class="step-label">Booking Cart</div>-->
                            </div>
                        </div>
                        <div class="step-container">
                            <div class="step">
                                <div class="step-label">Enter Ride Details</div>
                            </div>
                            <div class="step-label-connector"></div>
                            <div class="step">
                                <div class="step-label">Choose a vehicle</div>
                            </div>
                            <div class="step-label-connector"></div>
                            <div class="step">
                                <div class="step-label">Booking Cart</div>
                            </div>
                        </div>

                    </div>
                    <div class="search-item">
                        <div class="sidebar">
                            <div class="sidebar-header">
                                <?php esc_html_e('SUMMARY', 'mptbm_plugin'); ?>
                            </div>
                            <div class="divider"></div>
                            <div class="sidebar-topic">
                                <?php esc_html_e('Pick-Up Date', 'mptbm_plugin'); ?>
                            </div>
                            <div class="sidebar-topic-value">
                                <?php echo date(MPTBM_Function::date_format(), strtotime($start_date)); ?>
                            </div>
                            <div class="divider"></div>
                            <div class="sidebar-topic">
                                <?php esc_html_e('Pick-Up Time', 'mptbm_plugin'); ?>
                            </div>
                            <div class="sidebar-topic-value">
                                <?php echo $start_time . ' AM'; ?>
                            </div>
                            <div class="divider"></div>
                            <div class="sidebar-topic">
                                <?php esc_html_e('Pick-Up Location', 'mptbm_plugin'); ?>
                            </div>
                            <div class="sidebar-topic-value">
                                <?php echo $start_place; ?>
                            </div>
                            <div class="divider"></div>
                            <div class="sidebar-topic">
                                <?php esc_html_e('Drop-Off Location', 'mptbm_plugin'); ?>
                            </div>
                            <div class="sidebar-topic-value">
                                <?php echo $end_place; ?>
                            </div>
                            <div class="divider"></div>
                        </div>

<!--                        <div class="filter_item">-->
<!--                            <ul class="border-list">-->
<!--                                <li><span class="span-text"><h4>--><?php //esc_html_e('SUMMARY', 'mptbm_plugin'); ?><!--</h4></span></li>-->
<!--                                <li>-->
<!--                                    <div class="divider"></div>-->
<!--                                </li>-->
<!--                                <li><span class="span-text">--><?php //esc_html_e('Pick-Up Date', 'mptbm_plugin'); ?><!--</span><br>--><?php //echo date(MPTBM_Function::date_format(), strtotime($start_date)); ?><!--</li>-->
<!--                                <li>-->
<!--                                    <div class="divider"></div>-->
<!--                                </li>-->
<!--                                <li><span class="span-text">--><?php //esc_html_e('Pick-Up Time', 'mptbm_plugin'); ?><!--</span><br>--><?php //echo $start_time . ' AM'; ?><!--</li>-->
<!--                                <li>-->
<!--                                    <div class="divider"></div>-->
<!--                                </li>-->
<!--                                <li><span class="span-text">--><?php //esc_html_e('Pick-Up Location', 'mptbm_plugin'); ?><!--</span><br>--><?php //echo $start_place; ?><!--</li>-->
<!--                                <li>-->
<!--                                    <div class="divider"></div>-->
<!--                                </li>-->
<!--                                <li><span class="span-text">--><?php //esc_html_e('Drop-Off Location', 'mptbm_plugin'); ?><!--</span><br>--><?php //echo $end_place; ?><!--</li>-->
<!--                            </ul>-->
<!--                        </div>-->
                    </div>
                    <div class="search-item">

                        <div id="product-details"  class="">
                            <div class="details-item">
                                <div class="bg-image" data-href="<?php echo get_the_permalink($post_id); ?>" data-placeholder>
                                    <div data-bg-image="<?php echo esc_attr($thumbnail); ?>"></div>
                                </div>
                            </div>
                            <div class="details-item">
                                <div class="bordered" data-placeholder>

                                    <div id="icon-details">
                                        <div class="icon-item">
                                            <span class="car-title" data-href="<?php echo get_the_permalink($post_id); ?>"><?php echo get_the_title($post_id); ?></span>
                                        </div>
                                        <div class="icon-item">
                                            <table class="table-1">
                                                <tr>
                                                    <td><span><?php echo MPTBM_Function::get_vehicle_details_image('engine_icon', 'car-engine.png'); ?></span></td>
                                                    <td><?php echo MPTBM_Function::get_post_info($post_id, 'mptbm_engine'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><span><?php echo MPTBM_Function::get_vehicle_details_image('interior_color_icon', 'paint.png'); ?></span></td>
                                                    <td><?php echo MPTBM_Function::get_post_info($post_id, 'mptbm_interior_color'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><span><?php echo MPTBM_Function::get_vehicle_details_image('power_icon', 'wireless-charging.png'); ?></span></td>
                                                    <td><?php echo MPTBM_Function::get_post_info($post_id, 'mptbm_power'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><span><?php echo MPTBM_Function::get_vehicle_details_image('fuel_type_icon', 'group.png'); ?></span></td>
                                                    <td><?php echo MPTBM_Function::get_post_info($post_id, 'mptbm_fuel_type'); ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="icon-item">
                                            <table class="table-2">
                                                <tr>
                                                    <td><span><?php echo MPTBM_Function::get_vehicle_details_image('length_icon', 'ruler-icon.png'); ?></span></td>
                                                    <td><?php echo MPTBM_Function::get_post_info($post_id, 'mptbm_length'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><span><?php echo MPTBM_Function::get_vehicle_details_image('exterior_color_icon', 'varnish.png'); ?></span></td>
                                                    <td><?php echo MPTBM_Function::get_post_info($post_id, 'mptbm_exterior_color'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><span><?php echo MPTBM_Function::get_vehicle_details_image('transmission_icon', 'gears-icon.png'); ?></span></td>
                                                    <td><?php echo MPTBM_Function::get_post_info($post_id, 'mptbm_transmission'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><span><?php echo MPTBM_Function::get_vehicle_details_image('extras_icon', 'extra-icon.png'); ?></span></td>
                                                    <td><?php echo MPTBM_Function::get_post_info($post_id, 'mptbm_extras'); ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>

                                </div>

                            </div>
                            <div class="details-item">
                                <div class="select-div">
                                    <h2 class="textTheme"> <?php echo MPTBM_Function::esc_html($wc_price); ?></h2>
                                    <button type="button" class="dButton_xs w_150 car-select" data-collapse-target="#mptbm_collape_select_<?php echo esc_attr($post_id); ?>"><span><?php esc_html_e('Select', 'mptbm_plugin'); ?></span></button>
                                </div>
                            </div>
                            <div class="details-item">
                                <div style="margin:5px;">
                                    <form method="post" action="">
                                        <input type="hidden" name="mptbm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
                                        <input type="hidden" name="mptbm_start_place" value="<?php echo esc_attr($start_place); ?>"/>
                                        <input type="hidden" name="mptbm_end_place" value="<?php echo esc_attr($end_place); ?>"/>
                                        <input type="hidden" name="mptbm_date" value="<?php echo esc_attr($date); ?>"/>
                                        <div class="">
                                            <div data-collapse="#mptbm_collape_select_<?php echo esc_attr($post_id); ?>">
                                                <?php $extra_services = MPTBM_Function::get_post_info($post_id, 'mptbm_extra_service_data', array()); ?>
                                                <div class="mptbm_extra_service_area" data-placeholder>
                                                    <div>
                                                        <h5 class="extra-service-container-header"><span class="dashicons dashicons-cart" style="color:var(--button-bg);"></span> Extra Options</h5>
                                                    </div>
                                                    <div class="divider"></div>
                                                    <div>
                                                        <table class="noShadow bordered extra-service-table">
                                                            <!--                                                                <thead class="extra-service-table-header"><th>Service</th><th>Quantity</th><th>Select</th></thead>-->
                                                            <tbody>
                                                            <?php foreach ($extra_services as $service) { ?>
                                                                <?php
                                                                $service_icon = array_key_exists('service_icon', $service) ? $service['service_icon'] : '';
                                                                $service_name = array_key_exists('service_name', $service) ? $service['service_name'] : '';
                                                                $service_price = array_key_exists('service_price', $service) ? $service['service_price'] : 0;
                                                                $service_price = MPTBM_Function::wc_price($post_id, $service_price);
                                                                $service_price_raw = MPTBM_Function::price_convert_raw($service_price);
                                                                $description = array_key_exists('extra_service_description', $service) ? $service['extra_service_description'] : '';

                                                                $icon = $image = "";

                                                                if ($service_icon) {
                                                                    if (preg_match('/\s/', $service_icon)) {
                                                                        $icon = $service_icon;
                                                                    } else {
                                                                        $image = wp_get_attachment_image_url($service_icon);
                                                                    }
                                                                }
                                                                ?>
                                                                <tr>
                                                                    <th>
                                                                        <h6>
                                                                            <?php if ($service_icon && $icon) { ?>
                                                                                <span class="<?php echo esc_attr($icon); ?> extra-service-icon"></span>
                                                                            <?php } else { ?>
                                                                                <img class="extra-service-icon-image" src="<?php echo esc_attr($image); ?>"></img>
                                                                            <?php } ?>
                                                                            <?php echo MPTBM_Function::esc_html($service_name); ?>
                                                                            <span> - </span>
                                                                            <span class="price-text"><?php echo MPTBM_Function::esc_html($service_price); ?></span>

                                                                        </h6>
                                                                        <?php
                                                                        if ($description) {
                                                                            $word_count = str_word_count($description);
                                                                            if ($word_count > 16) {
                                                                                $message = implode(" ", array_slice(explode(" ", $description), 0, 16));
                                                                                $more_message = implode(" ", array_slice(explode(" ", $description), 16, $word_count));
                                                                                $name_text = preg_replace("/[{}()<>+ ]/", '_', $service_name) . '_' . $post_id;
                                                                                ?>
                                                                                <p class="service-description" style="margin-top: 5px;">
                                                                                    <small>
                                                                                        <?php echo esc_html($message); ?>
                                                                                        <span data-collapse='#<?php echo esc_attr($name_text); ?>'><?php echo esc_html($more_message); ?></span>
                                                                                        <span class="load_more_text" data-collapse-target="#<?php echo esc_attr($name_text); ?>">
                                                                    <?php esc_html_e('view more ', 'mptbm_plugin'); ?>
                                                                </span>
                                                                                    </small>
                                                                                </p>
                                                                                <?php
                                                                            } else {
                                                                                ?>
                                                                                <p class="service-description" style="margin-top: 5px;"><small><?php echo esc_html($description); ?></small></p>
                                                                                <?php
                                                                            }
                                                                        }
                                                                        ?>
                                                                    </th>
                                                                    <td class="textCenter">
                                                                        <div id="quantity_<?php echo str_replace(' ', '_', MPTBM_Function::esc_html($service_name)); ?>" class="quantity-class hide-quantity-box">
                                                                            <?php echo MPTBM_Layout::quantity_box('mptbm_extra_service_quantity[]'); ?>
                                                                        </div>

                                                                    </td>
                                                                    <td>
                                                                        <label class="_allCenter_fRight selectCheckbox" data-extra-service-id="<?php echo str_replace(' ', '_', MPTBM_Function::esc_html($service_name)); ?>">
                                                                            <input type="hidden" name="mptbm_extra_service[]" value=""/>
                                                                            <input type="checkbox" data-extra-service-price="<?php echo esc_attr($service_price_raw); ?>" value="<?php echo MPTBM_Function::esc_html($service_name); ?>"/>
                                                                            <span class="customCheckbox"><?php esc_html_e('Select', 'mptbm_plugin'); ?></span>
                                                                        </label>
                                                                    </td>
                                                                </tr>
                                                            <?php } ?>

                                                            </tbody>
                                                        </table>

                                                    </div>

                                                </div>



                                                <div class="custom-form">

                                                    <div class="aCsJod oJeWuf">
                                                        <div class="Xb9hP">
                                                            <input type="email" class="whsOnd zHQkBf" jsname="YPqjbf" autocomplete="username" spellcheck="false" tabindex="0" aria-label="Email or phone" name="identifier" autocapitalize="none" id="identifierId" dir="ltr" data-initial-dir="ltr" data-initial-value="">
                                                            <div jsname="YRMmle" class="AxOyFc snByac" aria-hidden="true">Email or phone</div>
                                                        </div>
                                                    </div>



                                                    <div class="custom-form-item">
                                                        <div class="mpStyle"><?php MPTBM_Function::get_custom_form(); ?></div>
                                                    </div>
                                                    <div class="cart-add-item">
                                                        <?php //MPTBM_Function::get_custom_form_inputs(); ?>
                                                    </div>
                                                </div>

                                                <div class="booking-part">
                                                    <div class="divider"></div>
                                                    <div class="cart-add-item">

                                                        <table id="booking-total">
                                                            <tr>
                                                                <td>
                                                                    <h4 class="textTheme" data-main-price="<?php echo esc_attr($raw_price); ?>">Total Amount :  <?php echo MPTBM_Function::esc_html($wc_price); ?></h4>
                                                                </td>
                                                                <td>
                                                                    <button class="_dButton_fRight mptbm_book_now" type="button">
                                                                        <span class="fas fa-cart-plus"></span>
                                                                        <?php esc_html_e('Add to Cart', 'mptbm_plugin'); ?>
                                                                    </button>
                                                                    <button type="submit" name="add-to-cart" value="<?php echo esc_html($product_id); ?>" class="dNone mptbm_add_to_cart">
                                                                        <?php esc_html_e('Add to Cart', 'mptbm_plugin'); ?>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="details-item">


                            </div>
                        </div>

                    </div>

                </div>


                <?php
            }
        }

        public function get_mptbm_end_place() {
            $start_place = MPTBM_Function::data_sanitize($_POST['start_place']);
            $price_based = MPTBM_Function::data_sanitize($_POST['price_based']);
            $post_id = MPTBM_Function::data_sanitize($_POST['post_id']);
            $end_locations = MPTBM_Function::get_manual_end_location($start_place, $post_id);
            if (sizeof($end_locations) > 0) {
                ?>
                <span class="fas fa-map-marker-alt"><?php esc_html_e(' Destination Location', 'mptbm_plugin'); ?></span>
                <select class="formControl mptbm_map_end_place" id="mptbm_manual_end_place">
                    <option selected disabled><?php esc_html_e(' Select Destination Location', 'mptbm_plugin'); ?></option>
                    <?php
                    foreach ($end_locations as $location) {
                        ?>
                        <option value="<?php echo esc_attr($location); ?>"><?php echo esc_html($location); ?></option>
                        <?php
                    }
                    ?>
                </select>
                <?php
            } else {
                ?><span class="fas fa-map-marker-alt"><?php esc_html_e(' Can not find any Destination Location', 'mptbm_plugin'); ?></span><?php
            }
            die();
        }
    }

    new MPTBM_Transport_Search();
}