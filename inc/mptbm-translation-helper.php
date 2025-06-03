<?php
// Helper for fetching admin-defined translations for ECAB Taxi Booking Manager
if (!function_exists('mptbm_get_translation')) {
    function mptbm_get_translation($key, $default = '') {
        $translations = get_option('mptbm_translations', array());
        if (isset($translations[$key]) && !empty($translations[$key])) {
            return esc_html($translations[$key]);
        }
        return $default;
    }
} 