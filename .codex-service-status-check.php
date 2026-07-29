<?php

wp_set_current_user(1);
set_current_screen('edit-mptbm_service_status');

if (!class_exists('MPTBM_Service_Status_Manager')) {
    require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Service_Status_Manager.php';
}

do_action('admin_enqueue_scripts', 'edit-tags.php');

ob_start();
do_action('admin_notices');
$screen_markup = ob_get_clean();

$terms = get_terms(
    [
        'taxonomy' => 'mptbm_service_status',
        'hide_empty' => false,
    ]
);
$term_count = is_wp_error($terms) ? 0 : count($terms);

echo wp_json_encode(
    [
        'screen_class' => strpos(apply_filters('admin_body_class', ''), 'mptbm-service-status-screen') !== false,
        'page_rendered' => strpos($screen_markup, 'mptbm-service-status-page') !== false,
        'grid_rendered' => strpos($screen_markup, 'data-service-status-grid') !== false,
        'modal_rendered' => strpos($screen_markup, 'data-service-status-modal') !== false,
        'name_field_rendered' => strpos($screen_markup, 'mptbm-service-status-name') !== false,
        'rendered_cards' => substr_count($screen_markup, 'mptbm-service-status-card is-tone-'),
        'term_count' => $term_count,
        'ajax_registered' => has_action('wp_ajax_mptbm_add_service_status') !== false,
        'style_enqueued' => wp_style_is('mptbm-service-status', 'enqueued'),
        'script_enqueued' => wp_script_is('mptbm-service-status', 'enqueued'),
        'administrator_can_manage' => current_user_can('manage_mptbm_transportation'),
    ],
    JSON_PRETTY_PRINT
);
