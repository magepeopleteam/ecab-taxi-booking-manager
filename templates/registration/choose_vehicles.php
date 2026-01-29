<?php
/*
 * @Author 		engr.sumonazma@gmail.com
 * Copyright: 	mage-people.com
*/
if (!defined("ABSPATH")) {
    die();
} // Cannot access pages directly

// Clear only pricing-related cache to ensure fresh pricing on transport result page
$current_page_template = get_page_template_slug();
$is_transport_result_page = ($current_page_template === 'transport_result.php') || 
                           (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'transport-result') !== false);

if ($is_transport_result_page) {
    // Clear only pricing-specific caches, not essential search data
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_weather_pricing_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_traffic_data_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_weather_pricing_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_traffic_data_%'");
}
$label = MPTBM_Function::get_name();
$days = MP_Global_Function::week_day();
$days_name = array_keys($days);
$schedule = [];



if (!function_exists('pointInPolygon')) {
    function pointInPolygon($point, $polygon) {
        $x = isset($point['latitude']) ? floatval($point['latitude']) : (isset($point['lat']) ? floatval($point['lat']) : (isset($point[0]) ? floatval($point[0]) : 0));
        $y = isset($point['longitude']) ? floatval($point['longitude']) : (isset($point['lng']) ? floatval($point['lng']) : (isset($point[1]) ? floatval($point[1]) : 0));
        $inside = false;
        $n = count($polygon);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = floatval($polygon[$i]['latitude']);
            $yi = floatval($polygon[$i]['longitude']);
            $xj = floatval($polygon[$j]['latitude']);
            $yj = floatval($polygon[$j]['longitude']);
            $intersect = (($yi > $y) != ($yj > $y)) &&
                ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi + 0.0000001) + $xi);
            if ($intersect) $inside = !$inside;
        }
        return $inside;
    }
}

function mptbm_check_transport_area_geo_fence($post_id, $operation_area_id, $start_place_coordinates, $end_place_coordinates) {
    $operation_area_type = get_post_meta($operation_area_id, "mptbm-operation-type", true);

    if ($operation_area_type === "fixed-operation-area-type") {
        $flat_operation_area_coordinates = get_post_meta($operation_area_id, "mptbm-coordinates-three", true);
        
        // Ensure it's an array before processing
        if (!is_array($flat_operation_area_coordinates)) {
            return;
        }

        // Convert flat array into array of associative arrays
        $operation_area_coordinates = [];
        for ($i = 0; $i < count($flat_operation_area_coordinates); $i += 2) {
            $operation_area_coordinates[] = ["latitude" => $flat_operation_area_coordinates[$i], "longitude" => $flat_operation_area_coordinates[$i + 1]];
        }
        ?>
        <script>
            var operation_area_coordinates = <?php echo wp_json_encode($operation_area_coordinates); ?>;
            var post_id = <?php echo wp_json_encode($post_id); ?>;
            var start_place_coordinates = <?php echo wp_json_encode($start_place_coordinates); ?>;
            var end_place_coordinates = <?php echo wp_json_encode($end_place_coordinates); ?>;
            var startInArea = geolib.isPointInPolygon(start_place_coordinates, operation_area_coordinates);
            var endInArea = geolib.isPointInPolygon(end_place_coordinates, operation_area_coordinates);
            if (startInArea && endInArea) {
                var selectorClass = `.mptbm_booking_item_${post_id}`;
                jQuery(selectorClass).removeClass('mptbm_booking_item_hidden');
                document.cookie = selectorClass + '=' + selectorClass + ";path=/";
            }
        </script>
        <?php
    } else if ($operation_area_type === "geo-matched-operation-area-type") {
        $flat_operation_area_coordinates = get_post_meta($operation_area_id, "mptbm-coordinates-three", true);
        if (!is_array($flat_operation_area_coordinates)) {
            return;
        }
        $operation_area_coordinates = [];
        for ($i = 0; $i < count($flat_operation_area_coordinates); $i += 2) {
            $operation_area_coordinates[] = ["latitude" => $flat_operation_area_coordinates[$i], "longitude" => $flat_operation_area_coordinates[$i + 1]];
        }
        ?>
        <script>
            var operation_area_coordinates = <?php echo wp_json_encode($operation_area_coordinates); ?>;
            var post_id = <?php echo wp_json_encode($post_id); ?>;
            var start_place_coordinates = <?php echo wp_json_encode($start_place_coordinates); ?>;
            var end_place_coordinates = <?php echo wp_json_encode($end_place_coordinates); ?>;
            var startInArea = geolib.isPointInPolygon(start_place_coordinates, operation_area_coordinates);
            var endInArea = geolib.isPointInPolygon(end_place_coordinates, operation_area_coordinates);
            if (startInArea || endInArea) {
                var selectorClass = `.mptbm_booking_item_${post_id}`;
                jQuery(selectorClass).removeClass('mptbm_booking_item_hidden');
                document.cookie = selectorClass + '=' + selectorClass + ";path=/";
            }
        </script>
        <?php
        // PHP-side: try to check if pickup or dropoff is inside polygon for logging
        $php_start_in_area = false;
        $php_end_in_area = false;
        if (is_array($start_place_coordinates) && isset($start_place_coordinates['latitude']) && isset($start_place_coordinates['longitude'])) {
            $php_start_in_area = pointInPolygon($start_place_coordinates, $operation_area_coordinates);
        }
        if (is_array($end_place_coordinates) && isset($end_place_coordinates['latitude']) && isset($end_place_coordinates['longitude'])) {
            $php_end_in_area = pointInPolygon($end_place_coordinates, $operation_area_coordinates);
        }
    } else {
        $flat_operation_area_coordinates_one = get_post_meta($operation_area_id, "mptbm-coordinates-one", true);
        $flat_operation_area_coordinates_two = get_post_meta($operation_area_id, "mptbm-coordinates-two", true);
        $operation_area_geo_direction = get_post_meta($operation_area_id, "mptbm-geo-fence-direction", true);

        // Ensure both arrays are valid before processing
        if (!is_array($flat_operation_area_coordinates_one) || !is_array($flat_operation_area_coordinates_two)) {
            return;
        }

        $operation_area_coordinates_one = [];
        $operation_area_coordinates_two = [];

        for ($i = 0; $i < count($flat_operation_area_coordinates_one); $i += 2) {
            $latitude = $flat_operation_area_coordinates_one[$i];
            $longitude = $flat_operation_area_coordinates_one[$i + 1];
            $operation_area_coordinates_one[] = $latitude . " " . $longitude;
        }

        for ($i = 0; $i < count($flat_operation_area_coordinates_two); $i += 2) {
            $latitude = $flat_operation_area_coordinates_two[$i];
            $longitude = $flat_operation_area_coordinates_two[$i + 1];
            $operation_area_coordinates_two[] = $latitude . " " . $longitude;
        }

        $new_start_place_coordinates = [];
        $new_end_place_coordinates = [];
        $new_start_place_coordinates[] = $start_place_coordinates["latitude"] . " " . $start_place_coordinates["longitude"];
        $new_end_place_coordinates[] = $end_place_coordinates["latitude"] . " " . $end_place_coordinates["longitude"];

        $pointLocation = new pointLocation();
        $startInAreaOne = $pointLocation->pointInPolygon($new_start_place_coordinates[0], $operation_area_coordinates_one) !== "outside";
        $endInAreaOne = $pointLocation->pointInPolygon($new_end_place_coordinates[0], $operation_area_coordinates_one) !== "outside";
        $startInAreaTwo = $pointLocation->pointInPolygon($new_start_place_coordinates[0], $operation_area_coordinates_two) !== "outside";
        $endInAreaTwo = $pointLocation->pointInPolygon($new_end_place_coordinates[0], $operation_area_coordinates_two) !== "outside";

        $startInAreaOne = $startInAreaOne ? "true" : "false";
        $endInAreaOne = $endInAreaOne ? "true" : "false";
        $startInAreaTwo = $startInAreaTwo ? "true" : "false";
        $endInAreaTwo = $endInAreaTwo ? "true" : "false";

        if ($operation_area_geo_direction == "geo-fence-one-direction") {
            if ($startInAreaOne == "true" && $endInAreaTwo == "true") {
                session_start();
                $mptbm_geo_fence_increase_price_by = get_post_meta($operation_area_id, "mptbm-geo-fence-increase-price-by", true);
                if ($mptbm_geo_fence_increase_price_by == "geo-fence-fixed-price") {
                    $mptbm_geo_fence_price_amount = get_post_meta($operation_area_id, "mptbm-geo-fence-fixed-price-amount", true);
                    $_SESSION["geo_fence_post_" . $post_id] = [$mptbm_geo_fence_price_amount, $mptbm_geo_fence_increase_price_by];
                } else {
                    $mptbm_geo_fence_price_amount = get_post_meta($operation_area_id, "mptbm-geo-fence-percentage-amount", true);
                    $_SESSION["geo_fence_post_" . $post_id] = [$mptbm_geo_fence_price_amount, $mptbm_geo_fence_increase_price_by];
                }
                ?>
                <script>
                    var post_id = <?php echo wp_json_encode($post_id); ?>;
                    var selectorClass = `.mptbm_booking_item_${post_id}`;
                    jQuery(selectorClass).removeClass('mptbm_booking_item_hidden');
                    document.cookie = selectorClass + '=' + selectorClass + ";path=/";
                </script>
                <?php session_write_close();
            } elseif ($startInAreaOne == "true" && $endInAreaOne == "true") { ?>
                <script>
                    var post_id = <?php echo wp_json_encode($post_id); ?>;
                    var selectorClass = `.mptbm_booking_item_${post_id}`;
                    jQuery(selectorClass).removeClass('mptbm_booking_item_hidden');
                    document.cookie = selectorClass + '=' + selectorClass + ";path=/";
                </script>
            <?php }
        } else {
            if ($startInAreaOne == "true" && $endInAreaTwo == "true") {
                session_start();
                $mptbm_geo_fence_increase_price_by = get_post_meta($operation_area_id, "mptbm-geo-fence-increase-price-by", true);
                if ($mptbm_geo_fence_increase_price_by == "geo-fence-fixed-price") {
                    $mptbm_geo_fence_price_amount = get_post_meta($operation_area_id, "mptbm-geo-fence-fixed-price-amount", true);
                    $_SESSION["geo_fence_post_" . $post_id] = [$mptbm_geo_fence_price_amount, $mptbm_geo_fence_increase_price_by];
                } else {
                    $mptbm_geo_fence_price_amount = get_post_meta($operation_area_id, "mptbm-geo-fence-percentage-amount", true);
                    $_SESSION["geo_fence_post_" . $post_id] = [$mptbm_geo_fence_price_amount, $mptbm_geo_fence_increase_price_by];
                }
                ?>
                <script>
                    var post_id = <?php echo wp_json_encode($post_id); ?>;
                    var selectorClass = `.mptbm_booking_item_${post_id}`;
                    jQuery(selectorClass).removeClass('mptbm_booking_item_hidden');
                    document.cookie = selectorClass + '=' + selectorClass + ";path=/";
                </script>
                <?php session_write_close();
            } elseif ($startInAreaTwo == "true" && $endInAreaOne == "true") {
                session_start();
                $mptbm_geo_fence_increase_price_by = get_post_meta($operation_area_id, "mptbm-geo-fence-increase-price-by", true);
                if ($mptbm_geo_fence_increase_price_by == "geo-fence-fixed-price") {
                    $mptbm_geo_fence_price_amount = get_post_meta($operation_area_id, "mptbm-geo-fence-fixed-price-amount", true);
                    $_SESSION["geo_fence_post_" . $post_id] = [$mptbm_geo_fence_price_amount, $mptbm_geo_fence_increase_price_by];
                } else {
                    $mptbm_geo_fence_price_amount = get_post_meta($operation_area_id, "mptbm-geo-fence-percentage-amount", true);
                    $_SESSION["geo_fence_post_" . $post_id] = [$mptbm_geo_fence_price_amount, $mptbm_geo_fence_increase_price_by];
                }
                ?>
                <script>
                    var post_id = <?php echo wp_json_encode($post_id); ?>;
                    var selectorClass = `.mptbm_booking_item_${post_id}`;
                    jQuery(selectorClass).removeClass('mptbm_booking_item_hidden');
                    document.cookie = selectorClass + '=' + selectorClass + ";path=/";
                </script>
                <?php session_write_close();
            } elseif ($startInAreaOne == "true" && $endInAreaOne == "true") {
                // Show transport when both start and end are in area one
                ?>
                <script>
                    var post_id = <?php echo wp_json_encode($post_id); ?>;
                    var selectorClass = `.mptbm_booking_item_${post_id}`;
                    jQuery(selectorClass).removeClass('mptbm_booking_item_hidden');
                    document.cookie = selectorClass + '=' + selectorClass + ";path=/";
                </script>
                <?php
            } elseif ($startInAreaTwo == "true" && $endInAreaTwo == "true") {
                // Show transport when both start and end are in area two
                ?>
                <script>
                    var post_id = <?php echo wp_json_encode($post_id); ?>;
                    var selectorClass = `.mptbm_booking_item_${post_id}`;
                    jQuery(selectorClass).removeClass('mptbm_booking_item_hidden');
                    document.cookie = selectorClass + '=' + selectorClass + ";path=/";
                </script>
                <?php
            }
        }
    }
}
function mptbm_check_fixed_distance_area($post_id, $operation_area_id, $start_place_coordinates, $end_place_coordinates, $price_based) {
    // Only apply for fixed_distance pricing
    if ($price_based !== 'fixed_distance' && $price_based !== 'fixed_map') {
        return false;
    }
    $operation_area_type = get_post_meta($operation_area_id, "mptbm-operation-type", true);
    
    // Determine meta key based on operation type
    $coord_key = '';
    if ($operation_area_type === "geo-matched-operation-area-type") {
        $coord_key = "mptbm-coordinates-four";
    } elseif ($operation_area_type === "fixed-operation-area-type") {
        $coord_key = "mptbm-coordinates-three";
    } elseif ($operation_area_type === "geo-fence-operation-area-type") {
        $coord_key = "mptbm-coordinates-one";
    }

    if (!$coord_key) {
        return false;
    }

    $flat_operation_area_coordinates = get_post_meta($operation_area_id, $coord_key, true);
    if (!is_array($flat_operation_area_coordinates)) {
        return false;
    }
    // Convert to polygon format
    $operation_area_coordinates = [];
    for ($i = 0; $i < count($flat_operation_area_coordinates); $i += 2) {
        $operation_area_coordinates[] = ["latitude" => $flat_operation_area_coordinates[$i], "longitude" => $flat_operation_area_coordinates[$i + 1]];
    }
    // Check if BOTH pickup and dropoff are in polygon

    $start_coords = is_array($start_place_coordinates) ? $start_place_coordinates : json_decode($start_place_coordinates, true);
    $end_coords = is_array($end_place_coordinates) ? $end_place_coordinates : json_decode($end_place_coordinates, true);

    $start_in_area = false;
    $end_in_area = false;
    if (is_array($start_coords)) {
        $start_in_area = pointInPolygon($start_coords, $operation_area_coordinates);
    }
    if (is_array($end_coords)) {
        $end_in_area = pointInPolygon($end_coords, $operation_area_coordinates);
    }

    if ($start_in_area && $end_in_area) {
        return 'full';
    } elseif ($start_in_area || $end_in_area) {
        return 'partial';
    }

    return false;
}



function wptbm_get_schedule($post_id, $days_name, $selected_day,$start_time_schedule, $return_time_schedule, $start_place_coordinates, $end_place_coordinates, $price_based) {
    
    $timestamp = strtotime($selected_day);
    $selected_day = date('l', $timestamp);
    
    // Check & destroy transport session if exist
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (isset($_SESSION["mptbm_fixed_distance_match_" . $post_id])) {
        unset($_SESSION["mptbm_fixed_distance_match_" . $post_id]);
    }
    
    //Get operation area id
    $operation_area_ids = get_post_meta($post_id, "mptbm_selected_operation_areas", true);

    //Schedule array
    $schedule = [];
    
    if ($operation_area_ids && $price_based !== "manual" && $price_based !== "fixed_zone" && $price_based !== "fixed_zone_dropoff") {
        // Handle multiple operation areas
        if (is_array($operation_area_ids)) {
            $is_in_any_area = false;
            $transport_operation_type = get_post_meta($post_id, 'mptbm_operation_area_type', true);
            foreach ($operation_area_ids as $operation_area_id) {
                if ($transport_operation_type === 'fixed-map-operation-area-type' && $price_based === 'fixed_map') {
                    // Get coordinates for this area dynamically
                    $area_type = get_post_meta($operation_area_id, 'mptbm-operation-type', true);
                    $coord_key = 'mptbm-coordinates-three';
                    if ($area_type === 'geo-matched-operation-area-type') {
                        $coord_key = 'mptbm-coordinates-four';
                    } elseif ($area_type === 'geo-fence-operation-area-type') {
                        $coord_key = 'mptbm-coordinates-one'; // Use the first one for intercity
                    }
                    
                    $flat_operation_area_coordinates = get_post_meta($operation_area_id, $coord_key, true);
                    
                    if (is_array($flat_operation_area_coordinates)) {
                        $operation_area_coordinates = [];
                        for ($i = 0; $i < count($flat_operation_area_coordinates); $i += 2) {
                            $operation_area_coordinates[] = ["latitude" => $flat_operation_area_coordinates[$i], "longitude" => $flat_operation_area_coordinates[$i + 1]];
                        }
                        
                        $start_coords = is_array($start_place_coordinates) ? $start_place_coordinates : json_decode($start_place_coordinates, true);
                        $end_coords = is_array($end_place_coordinates) ? $end_place_coordinates : json_decode($end_place_coordinates, true);
                        
                        $start_in_area = false;
                        $end_in_area = false;
                        if (is_array($start_coords)) { $start_in_area = pointInPolygon($start_coords, $operation_area_coordinates); }
                        if (is_array($end_coords)) { $end_in_area = pointInPolygon($end_coords, $operation_area_coordinates); }
                        
                        if ($start_in_area) {
                            $is_in_any_area = true;
                            $_SESSION["mptbm_fixed_distance_match_" . $post_id] = $end_in_area ? 'full' : 'partial';
                            ?>
                            <script>
                                var selectorClass = `.mptbm_booking_item_<?php echo $post_id; ?>`;
                                jQuery(selectorClass).removeClass('mptbm_booking_item_hidden');
                                document.cookie = selectorClass + '=' + selectorClass + ";path=/";
                            </script>
                            <?php
                            break; 
                        }
                    }
                    continue;
                }
                if ($price_based === 'fixed_distance' || $price_based === 'fixed_map') {
                    $match_type = mptbm_check_fixed_distance_area($post_id, $operation_area_id, $start_place_coordinates, $end_place_coordinates, $price_based);
                    if ($match_type) {
                        $is_in_any_area = true;
                        $_SESSION["mptbm_fixed_distance_match_" . $post_id] = $match_type;
                        ?>
                        <script>
                            var selectorClass = `.mptbm_booking_item_<?php echo $post_id; ?>`;
                            jQuery(selectorClass).removeClass('mptbm_booking_item_hidden');
                            document.cookie = selectorClass + '=' + selectorClass + ";path=/";
                        </script>
                        <?php
                        break;
                    }
                    continue;
                }
                $operation_area_type = get_post_meta($operation_area_id, "mptbm-operation-type", true);
                if ($transport_operation_type === "geo-matched-operation-area-type") {
                    // Geo-matched logic: show if either pickup or dropoff is in the area
                    $flat_operation_area_coordinates = get_post_meta($operation_area_id, "mptbm-coordinates-three", true);
                    if (is_array($flat_operation_area_coordinates)) {
                        $operation_area_coordinates = [];
                        for ($i = 0; $i < count($flat_operation_area_coordinates); $i += 2) {
                            $operation_area_coordinates[] = ["latitude" => $flat_operation_area_coordinates[$i], "longitude" => $flat_operation_area_coordinates[$i + 1]];
                        }
                        $php_start_in_area = false;
                        $php_end_in_area = false;
                        if (is_array($start_place_coordinates) && isset($start_place_coordinates['latitude']) && isset($start_place_coordinates['longitude'])) {
                            $php_start_in_area = pointInPolygon($start_place_coordinates, $operation_area_coordinates);
                        }
                        if (is_array($end_place_coordinates) && isset($end_place_coordinates['latitude']) && isset($end_place_coordinates['longitude'])) {
                            $php_end_in_area = pointInPolygon($end_place_coordinates, $operation_area_coordinates);
                        }
                        if ($php_start_in_area || $php_end_in_area) {
                            $is_in_any_area = true;
                            ?>
                            <script>
                                var selectorClass = `.mptbm_booking_item_<?php echo $post_id; ?>`;
                                jQuery(selectorClass).removeClass('mptbm_booking_item_hidden');
                                document.cookie = selectorClass + '=' + selectorClass + ";path=/";
                            </script>
                            <?php
                        }
                    }
                } elseif ($operation_area_type === "geo-fence-operation-area-type") {
                    mptbm_check_transport_area_geo_fence($post_id, $operation_area_id, $start_place_coordinates, $end_place_coordinates);
                    $is_in_any_area = true;
                } else {
                    $flat_operation_area_coordinates = get_post_meta($operation_area_id, "mptbm-coordinates-three", true);
                    if (is_array($flat_operation_area_coordinates)) {
                        $operation_area_coordinates = [];
                        for ($i = 0; $i < count($flat_operation_area_coordinates); $i += 2) {
                            $operation_area_coordinates[] = ["latitude" => $flat_operation_area_coordinates[$i], "longitude" => $flat_operation_area_coordinates[$i + 1]];
                        }
                        ?>
                        <script>
                            var operation_area_coordinates = <?php echo wp_json_encode($operation_area_coordinates); ?>;
                            var post_id = <?php echo wp_json_encode($post_id); ?>;
                            var start_place_coordinates = <?php echo wp_json_encode($start_place_coordinates); ?>;
                            var end_place_coordinates = <?php echo wp_json_encode($end_place_coordinates); ?>;
                            var startInArea = geolib.isPointInPolygon(start_place_coordinates, operation_area_coordinates);
                            var endInArea = geolib.isPointInPolygon(end_place_coordinates, operation_area_coordinates);
                            if (startInArea && endInArea) {
                                var selectorClass = `.mptbm_booking_item_${post_id}`;
                                jQuery(selectorClass).removeClass('mptbm_booking_item_hidden');
                                document.cookie = selectorClass + '=' + selectorClass + ";path=/";
                                <?php $is_in_any_area = true; ?>
                            }
                        </script>
                        <?php
                    }
                }
            }

            if (!$is_in_any_area) {
                if ($price_based === 'fixed_distance') {
                    return false;
                }
                ?>
                <script>
                    var post_id = <?php echo wp_json_encode($post_id); ?>;
                    var selectorClass = `.mptbm_booking_item_${post_id}`;
                    jQuery(selectorClass).addClass('mptbm_booking_item_hidden');
                </script>
                <?php
            }
        } else {
            // Single operation area
            if ($price_based === 'fixed_distance') {
                $match_type = mptbm_check_fixed_distance_area($post_id, $operation_area_ids, $start_place_coordinates, $end_place_coordinates, $price_based);
                if ($match_type) {
                    $is_in_any_area = true;
                    $_SESSION["mptbm_fixed_distance_match_" . $post_id] = $match_type;
                } else {
                    return false;
                }
            } else {
                mptbm_check_transport_area_geo_fence($post_id, $operation_area_ids, $start_place_coordinates, $end_place_coordinates);
            }
        }
    } else {
        if ($price_based === 'fixed_distance') {
            return false;
        }
        ?>
        <script>
            var post_id = <?php echo wp_json_encode($post_id); ?>;
            var selectorClass = `.mptbm_booking_item_${post_id}`;
            jQuery(selectorClass).removeClass('mptbm_booking_item_hidden');
            var vehicaleItemClass = `.mptbm_booking_item_${post_id}`;
            document.cookie = vehicaleItemClass +'='+  vehicaleItemClass+";path=/";
        </script>
        <?php
    }
    
    // Check if transport is available for all time
    $available_all_time = get_post_meta($post_id, 'mptbm_available_for_all_time');
    if($available_all_time[0] == 'on'){
        return true;
    }
    
    // Get default times
    $default_start_time = get_post_meta($post_id, "mptbm_default_start_time", true);
    $default_end_time = get_post_meta($post_id, "mptbm_default_end_time", true);
    
    
    // Build schedule array with proper default handling
    foreach ($days_name as $name) {
        $start_time = get_post_meta($post_id, "mptbm_" . $name . "_start_time", true);
        $end_time = get_post_meta($post_id, "mptbm_" . $name . "_end_time", true);
        
        // If day-specific times are empty, use default times
        if($start_time == '' || $start_time == 'default'){
            $start_time = $default_start_time;
        } else {
        }
        
        if($end_time == '' || $end_time == 'default'){
            $end_time = $default_end_time;
        } else {
        }
        
        // Only add to schedule if we have valid times
        if ($start_time !== "" && $end_time !== "" && $start_time !== null && $end_time !== null) {
            $schedule[$name] = [floatval($start_time), floatval($end_time)];
        }
        
    }
    
    // Check if the selected day matches any schedule
    $selected_day_lower = strtolower($selected_day);
    
    foreach ($schedule as $day => $times) {
        $day_start_time = $times[0];
        $day_end_time = $times[1];
        $day_lower = strtolower($day);
        
        if($selected_day_lower == $day_lower){ 
            $is_overnight = $day_start_time > $day_end_time;
            $start_valid = false;

            if ($is_overnight) {
                $start_valid = ($start_time_schedule >= $day_start_time) || ($start_time_schedule <= $day_end_time);
            } else {
                $start_valid = ($start_time_schedule >= $day_start_time) && ($start_time_schedule <= $day_end_time);
            }

            // Check if start time is within the schedule
            if ($start_valid) {
                // If return time is specified, check it too
                if (isset($return_time_schedule) && $return_time_schedule !== "" && $return_time_schedule !== null) {
                    $return_valid = false;
                    if ($is_overnight) {
                        $return_valid = ($return_time_schedule >= $day_start_time) || ($return_time_schedule <= $day_end_time);
                    } else {
                        $return_valid = ($return_time_schedule >= $day_start_time) && ($return_time_schedule <= $day_end_time);
                    }

                    if ($return_valid) {
                        return true;
                    }
                } else {
                    return true;
                }
            }
        }
    }
    
    // If no specific day schedule found, check if we should use default times
    if (empty($schedule) || !isset($schedule[$selected_day_lower])) {
        if ($default_start_time !== "" && $default_end_time !== "" && $default_start_time !== null && $default_end_time !== null) {
            $def_start = floatval($default_start_time);
            $def_end = floatval($default_end_time);
            
            $is_overnight = $def_start > $def_end;
            $start_valid = false;
            
            if ($is_overnight) {
                $start_valid = ($start_time_schedule >= $def_start) || ($start_time_schedule <= $def_end);
            } else {
                $start_valid = ($start_time_schedule >= $def_start) && ($start_time_schedule <= $def_end);
            }

            if ($start_valid) {
                 if (isset($return_time_schedule) && $return_time_schedule !== "" && $return_time_schedule !== null) {
                    $return_valid = false;
                    if ($is_overnight) {
                        $return_valid = ($return_time_schedule >= $def_start) || ($return_time_schedule <= $def_end);
                    } else {
                        $return_valid = ($return_time_schedule >= $def_start) && ($return_time_schedule <= $def_end);
                    }
                    
                    if ($return_valid) {
                        return true;
                    }
                } else {
                    return true;
                }
            }
        }
    }
    
    return false;
}
$start_date = isset($_POST["start_date"]) ? sanitize_text_field($_POST["start_date"]) : "";

$start_time_schedule = isset($_POST["start_time"]) ? sanitize_text_field($_POST["start_time"]) : "";
$start_time = isset($_POST["start_time"]) ? sanitize_text_field($_POST["start_time"]) : "";

// Define unique keys for each transient
$transient_key_schedule = 'start_time_schedule_transient';
$transient_key_date = 'start_date_transient';
// Check and set the transient for start_time_schedule
if (get_transient($transient_key_schedule)) {
    delete_transient($transient_key_schedule); // Delete existing transient if found
}
set_transient($transient_key_schedule, $start_time); // Set new transient


// Check and set the transient for start_time
if (get_transient($transient_key_date)) {
    delete_transient($transient_key_date); // Delete existing transient if found
}
set_transient($transient_key_date, $start_date); // Set new transient

if ($start_time !== "") {
    if ($start_time !== "0") {
        
        // Convert start time to hours and minutes
        $time_parts = explode('.', $start_time);
        $hours = isset($time_parts[0]) ? $time_parts[0] : 0;
        $decimal_part = isset($time_parts[1]) ? $time_parts[1] : 0;
        $interval_time = MPTBM_Function::get_general_settings('mptbm_pickup_interval_time');
        
        if ($interval_time == "5" || $interval_time == "15") {
                if($decimal_part != 3){
                    $minutes = isset($decimal_part) ? (int) $decimal_part * 1 : 0; // Multiply by 1 to convert to minutes
                }else{
                    $minutes = isset($decimal_part) ? (int) $decimal_part * 10 : 0; // Multiply by 1 to convert to minutes
                }
                
                
            
        }else {
            $minutes = isset($decimal_part) ? (int) $decimal_part * 1 : 0; // Multiply by 10 to convert to minutes
        }
        
    } else {
        $hours = 0;
        $minutes = 0;
    }
} else {
    $hours = 0;
    $minutes = 0;
}

// Format hours and minutes
$start_time_formatted = sprintf('%02d:%02d', $hours, $minutes);


// Combine date and time if both are available
$date = $start_date ? gmdate("Y-m-d", strtotime($start_date)) : "";
if ($date && $start_time !== "") {
    $date .= " " . $start_time_formatted;
}

$start_place = isset($_POST["start_place"]) ? sanitize_text_field($_POST["start_place"]) : "";
$start_place_coordinates = isset($_POST["start_place_coordinates"]) ? $_POST["start_place_coordinates"] : "";
$end_place_coordinates = isset($_POST["end_place_coordinates"]) ? $_POST["end_place_coordinates"] : "";
$end_place = isset($_POST["end_place"]) ? sanitize_text_field($_POST["end_place"]) : "";
$mptbm_original_price_base = isset($_POST["mptbm_original_price_base"]) ? sanitize_text_field($_POST["mptbm_original_price_base"]) : "";



// Parse and store coordinates for weather and traffic pricing
if (!empty($start_place_coordinates)) {
    // Handle both array and JSON string formats
    if (is_array($start_place_coordinates)) {
        $start_coords = $start_place_coordinates;
    } else {
        $start_coords = json_decode($start_place_coordinates, true);
    }
    
    if (is_array($start_coords) && ((isset($start_coords['lat']) && isset($start_coords['lng'])) || (isset($start_coords['latitude']) && isset($start_coords['longitude'])))) {
        // Handle both lat/lng and latitude/longitude formats
        $lat = isset($start_coords['lat']) ? $start_coords['lat'] : $start_coords['latitude'];
        $lng = isset($start_coords['lng']) ? $start_coords['lng'] : $start_coords['longitude'];
        
        set_transient('pickup_lat_transient', floatval($lat), HOUR_IN_SECONDS);
        set_transient('pickup_lng_transient', floatval($lng), HOUR_IN_SECONDS);
    }
}

if (!empty($end_place_coordinates)) {
    // Handle both array and JSON string formats
    if (is_array($end_place_coordinates)) {
        $end_coords = $end_place_coordinates;
    } else {
        $end_coords = json_decode($end_place_coordinates, true);
    }
    
    if (is_array($end_coords) && ((isset($end_coords['lat']) && isset($end_coords['lng'])) || (isset($end_coords['latitude']) && isset($end_coords['longitude'])))) {
        // Handle both lat/lng and latitude/longitude formats
        $lat = isset($end_coords['lat']) ? $end_coords['lat'] : $end_coords['latitude'];
        $lng = isset($end_coords['lng']) ? $end_coords['lng'] : $end_coords['longitude'];
        
        set_transient('drop_lat_transient', floatval($lat), HOUR_IN_SECONDS);
        set_transient('drop_lng_transient', floatval($lng), HOUR_IN_SECONDS);
    }
}

$two_way = isset($_POST["two_way"]) ? absint($_POST["two_way"]) : 1;
$waiting_time = isset($_POST["waiting_time"]) ? sanitize_text_field($_POST["waiting_time"]) : 0;
$fixed_time = isset($_POST["fixed_time"]) ? sanitize_text_field($_POST["fixed_time"]) : "";
$return_time_schedule=null;

$price_based = sanitize_text_field($_POST["price_based"]);

// For fixed_zone_dropoff, we need to pass start_place_coordinates as geo_fence_coords to get_price/location_exit
// because the geo-fence check needs the searched pickup coordinates
// For fixed_zone, we pass end_place_coordinates as geo_fence_coords (searched dropoff coordinates)
$geo_fence_coords = null;
if ($price_based === 'fixed_zone_dropoff' && !empty($start_place_coordinates)) {
    if (is_array($start_place_coordinates)) {
        $geo_fence_coords = $start_place_coordinates;
    } else {
        // Handle JSON string - WordPress may add slashes, so try both ways
        $decoded = json_decode($start_place_coordinates, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            // Try with stripslashes in case WordPress added slashes
            $decoded = json_decode(stripslashes($start_place_coordinates), true);
        }
        // Validate the decoded data has the required keys
        if ($decoded && (isset($decoded['latitude']) || isset($decoded['lat'])) && (isset($decoded['longitude']) || isset($decoded['lng']))) {
            $geo_fence_coords = $decoded;
        } else {
            // Last resort: try to extract coordinates from the string directly
            if (preg_match('/"latitude":([\d.]+).*"longitude":([\d.]+)/', $start_place_coordinates, $matches)) {
                $geo_fence_coords = ['latitude' => floatval($matches[1]), 'longitude' => floatval($matches[2])];
            } elseif (preg_match('/"lat":([\d.]+).*"lng":([\d.]+)/', $start_place_coordinates, $matches)) {
                $geo_fence_coords = ['lat' => floatval($matches[1]), 'lng' => floatval($matches[2])];
            }
        }
    }
} elseif (($price_based === 'fixed_zone') && !empty($end_place_coordinates)) {
    if (is_array($end_place_coordinates)) {
        $geo_fence_coords = $end_place_coordinates;
    } else {
        // Handle JSON string - WordPress may add slashes, so try both ways
        $decoded = json_decode($end_place_coordinates, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            // Try with stripslashes in case WordPress added slashes
            $decoded = json_decode(stripslashes($end_place_coordinates), true);
        }
        // Validate the decoded data has the required keys
        if ($decoded && (isset($decoded['latitude']) || isset($decoded['lat'])) && (isset($decoded['longitude']) || isset($decoded['lng']))) {
            $geo_fence_coords = $decoded;
        } else {
            // Last resort: try to extract coordinates from the string directly
            if (preg_match('/"latitude":([\d.]+).*"longitude":([\d.]+)/', $end_place_coordinates, $matches)) {
                $geo_fence_coords = ['latitude' => floatval($matches[1]), 'longitude' => floatval($matches[2])];
            } elseif (preg_match('/"lat":([\d.]+).*"lng":([\d.]+)/', $end_place_coordinates, $matches)) {
                $geo_fence_coords = ['lat' => floatval($matches[1]), 'lng' => floatval($matches[2])];
            }
        }
    }
}


if ($two_way > 1 && MP_Global_Function::get_settings("mptbm_general_settings", "enable_return_in_different_date") == "yes") {
    $return_date = isset($_POST["return_date"]) ? sanitize_text_field($_POST["return_date"]) : "";
    $return_time = isset($_POST["return_time"]) ? sanitize_text_field($_POST["return_time"]): "";
    
    $return_time_schedule = isset($_POST["return_time"]) ? sanitize_text_field($_POST["return_time"]) : "";
    
    if ($return_time !== "") {
        if ($return_time !== "0") {
    
            // Convert return time to hours and minutes
            $time_parts = explode('.', $return_time);
            $hours = isset($time_parts[0]) ? $time_parts[0] : 0;
            $decimal_part = isset($time_parts[1]) ? $time_parts[1] : 0;
            $interval_time = MPTBM_Function::get_general_settings('mptbm_pickup_interval_time');
    
            if ($interval_time == "5" || $interval_time == "15") {
                if ($decimal_part != 3) {
                    $minutes = isset($decimal_part) ? (int) $decimal_part * 1 : 0; // Multiply by 1 to convert to minutes
                } else {
                    $minutes = isset($decimal_part) ? (int) $decimal_part * 10 : 0; // Multiply by 10 to convert to minutes
                }
            } else {
                $minutes = isset($decimal_part) ? (int) $decimal_part * 1 : 0; // Multiply by 1 to convert to minutes
            }
    
        } else {
            $hours = 0;
            $minutes = 0;
        }
    } else {
        $hours = 0;
        $minutes = 0;
    }
    // Format hours and minutes
    $return_time_formatted = sprintf('%02d:%02d', $hours, $minutes);
    
    
    // Combine date and time if both are available
    $return_date_time = $return_date ? gmdate("Y-m-d", strtotime($return_date)) : "";
    if ($return_date_time && $return_time !== "") {
        $return_date_time .= " " . $return_time_formatted;
    }

    
}
if (MP_Global_Function::get_settings("mptbm_general_settings", "enable_filter_via_features") == "yes") {
    $feature_passenger_number = isset($_POST["feature_passenger_number"]) ? sanitize_text_field($_POST["feature_passenger_number"]) : "";
    $feature_bag_number = isset($_POST["feature_bag_number"]) ? sanitize_text_field($_POST["feature_bag_number"]) : "";
    $feature_hand_luggage_number = isset($_POST["feature_hand_luggage_number"]) ? sanitize_text_field($_POST["feature_hand_luggage_number"]) : "";
}
$mptbm_bags = [];
$mptbm_passengers = [];
$mptbm_hand_luggage = [];
$mptbm_all_transport_id = MP_Global_Function::get_all_post_id('mptbm_rent');
foreach ($mptbm_all_transport_id as $key => $value) {
	array_push($mptbm_bags, MPTBM_Function::get_feature_bag($value));
	array_push($mptbm_passengers, MPTBM_Function::get_feature_passenger($value));
	$hand_luggage = (int) get_post_meta($value, 'mptbm_maximum_hand_luggage', true);
	if ($hand_luggage > 0) {
		array_push($mptbm_hand_luggage, $hand_luggage);
	}
}
$mptbm_bags =  max($mptbm_bags);
$mptbm_passengers = max($mptbm_passengers);
$mptbm_hand_luggage_max = !empty($mptbm_hand_luggage) ? max($mptbm_hand_luggage) : 0;

$selected_max_passenger = isset($_POST['mptbm_max_passenger']) ? intval($_POST['mptbm_max_passenger']) : 0;
$selected_max_bag = isset($_POST['mptbm_max_bag']) ? intval($_POST['mptbm_max_bag']) : 0;
$selected_max_hand_luggage = isset($_POST['mptbm_max_hand_luggage']) ? intval($_POST['mptbm_max_hand_luggage']) : 0;


// Values to show in summary box (prefer user selections from form)
$summary_passenger = $selected_max_passenger ?: (isset($_POST['mptbm_passengers']) ? absint($_POST['mptbm_passengers']) : '');
$summary_bag = $selected_max_bag ?: '';
$summary_hand_luggage = $selected_max_hand_luggage ?: '';


// Get distance and duration from POST (if sent via AJAX fallback) or cookies
$distance = isset($_POST['mptbm_distance']) && !empty($_POST['mptbm_distance']) ? absint($_POST['mptbm_distance']) : (isset($_COOKIE['mptbm_distance']) ? absint($_COOKIE['mptbm_distance']) : '');
$duration = isset($_POST['mptbm_duration']) && !empty($_POST['mptbm_duration']) ? absint($_POST['mptbm_duration']) : (isset($_COOKIE['mptbm_duration']) ? absint($_COOKIE['mptbm_duration']) : '');


// Fallback values for object caching compatibility
if (empty($distance)) {
    $distance = 1000; // Default 1km in meters
}
if (empty($duration)) {
    $duration = 3600; // Default 1 hour in seconds
}
?>
<div data-tabs-next="#mptbm_search_result" class="mptbm_map_search_result">
	<input type="hidden" name="mptbm_post_id" value="" data-price="" />
	<input type="hidden" name="mptbm_start_place" value="<?php echo esc_attr($start_place); ?>" />
	<input type="hidden" name="mptbm_end_place" value="<?php echo esc_attr($end_place); ?>" />
	<input type="hidden" name="mptbm_start_place_coordinates" value="<?php echo esc_attr(is_array($start_place_coordinates) ? json_encode($start_place_coordinates) : $start_place_coordinates); ?>" />
	<input type="hidden" name="mptbm_end_place_coordinates" value="<?php echo esc_attr(is_array($end_place_coordinates) ? json_encode($end_place_coordinates) : $end_place_coordinates); ?>" />
	<input type="hidden" name="mptbm_date" value="<?php echo esc_attr($date); ?>" />
	<input type="hidden" name="mptbm_time" value="<?php echo esc_attr($start_time); ?>"/>
	<input type="hidden" name="mptbm_taxi_return" value="<?php echo esc_attr($two_way); ?>" />
	<input type="hidden" name="mptbm_waiting_time" value="<?php echo esc_attr($waiting_time); ?>" />
	<input type="hidden" name="mptbm_fixed_hours" value="<?php echo esc_attr($fixed_time); ?>" />
	<input type="hidden" name="mptbm_max_passenger" value="<?php echo esc_attr($selected_max_passenger); ?>"/>
	<input type="hidden" name="mptbm_max_bag" value="<?php echo esc_attr($selected_max_bag); ?>"/>
	<input type="hidden" name="mptbm_max_hand_luggage" value="<?php echo esc_attr($selected_max_hand_luggage); ?>"/>
	<input type="hidden" name="mptbm_original_price_base" value="<?php echo esc_attr($mptbm_original_price_base); ?>" />
	<?php if ($two_way > 1 && MP_Global_Function::get_settings("mptbm_general_settings", "enable_return_in_different_date") == "yes") { ?>
				<input type="hidden" name="mptbm_map_return_date" id="mptbm_map_return_date" value="<?php echo esc_attr($return_date); ?>" />
				<input type="hidden" name="mptbm_map_return_time" id="mptbm_map_return_time" value="<?php echo esc_attr($return_time); ?>" />

			<?php
} ?>
	<input type="hidden" name="mptbm_waiting_time" value="<?php echo esc_attr($waiting_time); ?>" />
	<input type="hidden" name="mptbm_fixed_hours" value="<?php echo esc_attr($fixed_time); ?>" />
	<?php 
	// Pass selected filter values to next page
	$pro_active = class_exists('MPTBM_Dependencies_Pro');
	$search_filter_settings = $pro_active ? get_option('mptbm_search_filter_settings', array()) : array();
	$enable_max_passenger_filter = isset($search_filter_settings['enable_max_passenger_filter']) ? $search_filter_settings['enable_max_passenger_filter'] : 'no';
	$enable_max_bag_filter = isset($search_filter_settings['enable_max_bag_filter']) ? $search_filter_settings['enable_max_bag_filter'] : 'no';
	$enable_max_hand_luggage_filter = isset($search_filter_settings['enable_max_hand_luggage_filter']) ? $search_filter_settings['enable_max_hand_luggage_filter'] : 'no';
	
	if ($pro_active && $enable_max_passenger_filter === 'yes') {
		$selected_passenger = isset($_POST['mptbm_max_passenger']) ? absint($_POST['mptbm_max_passenger']) : '';
		if ($selected_passenger) {
			echo '<input type="hidden" name="mptbm_max_passenger" value="' . esc_attr($selected_passenger) . '" />';
		}
	}
	
	if ($pro_active && $enable_max_bag_filter === 'yes') {
		$selected_bag = isset($_POST['mptbm_max_bag']) ? absint($_POST['mptbm_max_bag']) : '';
		if ($selected_bag !== '') {
			echo '<input type="hidden" name="mptbm_max_bag" value="' . esc_attr($selected_bag) . '" />';
		}
	}
	
	if ($pro_active && $enable_max_hand_luggage_filter === 'yes') {
		$selected_hand_luggage = isset($_POST['mptbm_max_hand_luggage']) ? absint($_POST['mptbm_max_hand_luggage']) : '';
		if ($selected_hand_luggage !== '') {
			echo '<input type="hidden" name="mptbm_max_hand_luggage" value="' . esc_attr($selected_hand_luggage) . '" />';
		}
	}
	?>
	<div class="mp_sticky_section">
		<div class="flexWrap">
            
			<?php include MPTBM_Function::template_path("registration/summary.php"); ?>
			<div class="mainSection ">
				<div class="mp_sticky_depend_area fdColumn">
				<!-- Filter area start -->
				<?php if (MP_Global_Function::get_settings("mptbm_general_settings", "enable_filter_via_features") == "yes") { ?>
				<div class="_dLayout_dFlex_fdColumn_btLight_2 mptbm-filter-feature">
					<div class="mptbm-filter-feature-input">
						<span><i class="fas fa-users _textTheme_mR_xs"></i><?php esc_html_e("Number Of Passengers", "ecab-taxi-booking-manager"); ?></span>
                        <label>
								<select id ="mptbm_passenger_number" class="formControl" name="mptbm_passenger_number">
								<?php
                                    for ($i = 0; $i <= $mptbm_passengers[0]; $i++) {
                                        echo '<option value="' . esc_html($i) . '">' .  esc_html($i) . '</option>';
                                    }
                                ?>
								</select>
								
							</label>
						</div>
						<div class="mptbm-filter-feature-input">
						<span><i class="fa  fa-shopping-bag _textTheme_mR_xs"></i><?php esc_html_e("Number Of Bags", "ecab-taxi-booking-manager"); ?></span>
                        <label>
								<select id ="mptbm_shopping_number" class="formControl" name="mptbm_shopping_number">
                                    <?php
                                        for ($i = 0; $i <= $mptbm_bags[0]; $i++) {
                                            echo '<option value="' . esc_html($i) . '">' .  esc_html($i) . '</option>';
                                        }
                                    ?>
								</select>
							</label>
						</div>
						
					</div>
				<?php
} ?>
				<!-- Filter area end -->
					<?php

$all_posts = MPTBM_Query::query_transport_list($price_based);

 
if ($all_posts->found_posts > 0) {
    $posts = $all_posts->posts;
    $vehicle_item_count = 0;
    foreach ($posts as $post) {
        $post_id = $post->ID;
        $taxi_max_passenger = (int) get_post_meta($post_id, 'mptbm_maximum_passenger', true);
        $taxi_max_bag = (int) get_post_meta($post_id, 'mptbm_maximum_bag', true);
        $taxi_max_hand_luggage = (int) get_post_meta($post_id, 'mptbm_maximum_hand_luggage', true);
        
        
        if (
            ($selected_max_passenger && $taxi_max_passenger < $selected_max_passenger) ||
            ($selected_max_bag && $taxi_max_bag < $selected_max_bag) ||
            ($selected_max_hand_luggage && $taxi_max_hand_luggage < $selected_max_hand_luggage)
        ) {
            continue; // Skip this taxi, it doesn't meet the filter
        }
        
        $check_schedule = wptbm_get_schedule($post_id, $days_name, $start_date,$start_time_schedule, $return_time_schedule, $start_place_coordinates, $end_place_coordinates, $price_based);
        
        if ($check_schedule) {
            // Check location_exit for manual/fixed_zone/fixed_zone_dropoff pricing modes
            $location_exit = MPTBM_Function::location_exit($post_id, $start_place, $end_place, $geo_fence_coords);
            
            if (!$location_exit) {
                error_log("MPTBM Debug Filter: Skipping taxi ID " . $post_id . " due to location exit filter.");
                continue;
            }
            
            $vehicle_item_count = $vehicle_item_count + 1;
            $price_display_type = MP_Global_Function::get_post_info($post_id, 'mptbm_price_display_type', 'normal');
            $custom_message = MP_Global_Function::get_post_info($post_id, 'mptbm_custom_price_message', '');
            
            
            // Get the price (pass geo_fence_coords for fixed_zone/fixed_zone_dropoff geo-fence validation)
            $price = MPTBM_Function::get_price($post_id, $distance, $duration, $start_place, $end_place, $waiting_time, $two_way, $fixed_time, $geo_fence_coords);
            
            // Only skip display if price is 0 and we're not in zero or custom message mode
            if (!$price && $price_display_type === 'normal') {
                continue;
            }
            
            // Handle price display
            if ($price_display_type === 'custom_message' && $custom_message) {
                $price_display = '<div class="mptbm-custom-price-message" style="font-size: 15px;">' . wp_kses_post($custom_message) . '</div>';
                $raw_price = 0; // Set raw price to 0 for custom message
            } else {
                $wc_price = MP_Global_Function::wc_price($post_id, $price);
                $raw_price = MP_Global_Function::price_convert_raw($wc_price);
                $price_display = $wc_price;
            }
            
            include MPTBM_Function::template_path("registration/vehicle_item.php");
        }
    }
    
} else {
?>
						<div class="_dLayout_mT_bgWarning">
							<h3><?php esc_html_e("No Transport Available !", "ecab-taxi-booking-manager"); ?></h3>
						</div>
					<?php
}
?>
					<script>
						jQuery(document).ready(function () {
							var allHidden = true;
							jQuery(".mptbm_booking_item").each(function() {
								if (!jQuery(this).hasClass("mptbm_booking_item_hidden")) {
									allHidden = false;
									return false; // Exit the loop early if any item is not hidden
								}
							});

							// If all items have the hidden class, log them
							if (allHidden) {
								jQuery('.geo-fence-no-transport').show(300);
							}
						});
					</script>
					<div class="geo-fence-no-transport">
						<?php 
							$custom_message = MP_Global_Function::get_settings('mptbm_general_settings', 'no_transport_message', '<h3>' . esc_html__("No Transport Available !", "ecab-taxi-booking-manager") . '</h3>');
							echo wp_kses_post($custom_message);
						?>
					</div>
					<div class="mptbm_extra_service"></div>
				</div>
			</div>
		</div>
	</div>
</div>
<div data-tabs-next="#mptbm_order_summary" class="mptbm_order_summary">
	<div class="mp_sticky_section">
		<div class="flexWrap">
			<?php include MPTBM_Function::template_path("registration/summary.php"); ?>
			<div class="mainSection ">
				<div class="mp_sticky_depend_area fdColumn mptbm_checkout_area">
				</div>
			</div>
		</div>
	</div>
</div>
<?php

?>