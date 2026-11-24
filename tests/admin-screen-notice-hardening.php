<?php
/**
 * Test suite for verifying admin screen notice hardening across the 6 manager screens.
 */
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

$classes = [
    'MPTBM_Service_Status_Manager'  => 'mptbm-service-status-page',
    'MPTBM_Locations_Manager'       => 'mptbm-locations-page',
    'MPTBM_Extra_Services_Manager'  => 'mptbm-extra-services-page',
    'MPTBM_Stoppages_Manager'       => 'mptbm-stoppages-page',
    'MPTBM_Operation_Areas_Manager' => 'mptbm-operation-areas-page',
    'MPTBM_Routes_Manager'          => 'mptbm-routes-page',
];

// Ensure admin classes are loaded
foreach ($classes as $class_name => $expected_class) {
    $file_path = MPTBM_PLUGIN_DIR . '/Admin/' . $class_name . '.php';
    if (file_exists($file_path)) {
        require_once $file_path;
    }
}

$passed = 0;
$total = 0;

foreach ($classes as $class_name => $expected_class) {
    $total++;
    if (!class_exists($class_name)) {
        echo "FAIL: Class $class_name does not exist.\n";
        continue;
    }

    $ref = new ReflectionClass($class_name);
    $file_path = $ref->getFileName();
    $content = file_get_contents($file_path);

    // Check hook
    if (strpos($content, "add_action('all_admin_notices', [ \$this, 'render_screen' ], 9999999);") === false) {
        echo "FAIL: $class_name does not hook all_admin_notices at priority 9999999.\n";
        continue;
    }

    // Check no old admin_notices render_screen hook
    if (strpos($content, "add_action('admin_notices', [ \$this, 'render_screen' ]") !== false) {
        echo "FAIL: $class_name still hooks admin_notices.\n";
        continue;
    }

    // Check classes on section
    if (strpos($content, $expected_class . ' anm-display-notice leave-in-place') === false) {
        echo "FAIL: $class_name section missing anm-display-notice or leave-in-place classes.\n";
        continue;
    }

    $passed++;
}

// Check CSS files
$css_files = [
    'mptbm_service_status.css'  => '.mptbm-service-status-page',
    'mptbm_locations.css'       => '.mptbm-locations-page',
    'mptbm_extra_services.css'  => '.mptbm-extra-services-page',
    'mptbm_stoppages.css'       => '.mptbm-stoppages-page',
    'mptbm_operation_areas.css' => '.mptbm-operation-areas-page',
    'mptbm_routes.css'          => '.mptbm-routes-page',
];

foreach ($css_files as $file_name => $selector) {
    $total++;
    $css_path = MPTBM_PLUGIN_DIR . '/assets/admin/' . $file_name;
    if (!file_exists($css_path)) {
        echo "FAIL: CSS file $file_name does not exist.\n";
        continue;
    }
    $css_content = file_get_contents($css_path);
    if (strpos($css_content, 'display: block !important;') === false ||
        strpos($css_content, 'visibility: visible !important;') === false) {
        echo "FAIL: $file_name does not contain required visibility/display rules.\n";
        continue;
    }
    $passed++;
}

// Check JS file
$total++;
$js_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm-shell.js';
$js_content = file_get_contents($js_path);
if (strpos($js_content, '$noticeWrapper.before($nativePage);') === false) {
    echo "FAIL: mptbm-shell.js missing notice-wrapper hoisting protection.\n";
} else {
    $passed++;
}

if ($passed === $total) {
    echo "Success: All $passed notice-hardening checks passed.\n";
} else {
    echo "Error: Only $passed of $total notice-hardening checks passed.\n";
    exit(1);
}
