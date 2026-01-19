<?php
/**
 * Helper for fetching translations for ECAB Taxi Booking Manager
 * Prioritizes WordPress translation system (Loco Translate, .po/.mo files) over legacy database values
 * Maintains backward compatibility with old Translation tab data for sites not using translation plugins
 */
if (!function_exists('mptbm_get_translation')) {
    function mptbm_get_translation($key, $default = '') {
        $translations = get_option('mptbm_translations', array());
        $db_value = (isset($translations[$key]) && !empty($translations[$key])) ? $translations[$key] : '';

        // If WPML String Translation is active, register/retrieve the string using the code default as source.
        // This ensures the source is always English and prevents German/French leaks from the database overrides.
        if (function_exists('icl_t')) {
            return icl_t('ecab-taxi-booking-manager', $key, $default);
        }

        $current_locale = get_locale();
        
        // Handle all English variants (en_US, en_GB, en_AU, en_CA, etc.)
        $is_english = ($current_locale === 'en' || substr($current_locale, 0, 3) === 'en_');

        if ($is_english) {
            // For English, database override takes priority over the code's default string
            return !empty($db_value) ? esc_html($db_value) : $default;
        }
        
        // For non-English locales without WPML, fallback to WordPress's standard translation (__ calls)
        // which were passed as the $default argument.
        return $default;
    }
}