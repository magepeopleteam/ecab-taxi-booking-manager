<?php

wp_set_current_user(1);

if (!class_exists('MPTBM_Service_Status_Manager')) {
    require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Service_Status_Manager.php';
}

$test_name = 'Codex AJAX Status ' . wp_generate_password(8, false);
register_shutdown_function(
    static function () use ($test_name) {
        $term = get_term_by('name', $test_name, 'mptbm_service_status');
        if ($term) {
            wp_delete_term($term->term_id, 'mptbm_service_status');
        }
    }
);

$_POST = [
    'nonce' => wp_create_nonce('mptbm_service_status_nonce'),
    'name' => $test_name,
    'description' => 'Temporary AJAX validation status.',
];
$_REQUEST = $_POST;

$manager = new MPTBM_Service_Status_Manager();
$manager->ajax_add_service_status();
