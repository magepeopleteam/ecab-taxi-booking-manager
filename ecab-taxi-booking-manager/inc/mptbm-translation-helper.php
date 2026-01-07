<?php
/**
 * Helper for fetching translations for ECAB Taxi Booking Manager
 * Prioritizes WordPress translation system (Loco Translate, .po/.mo files) over legacy database values
 * Maintains backward compatibility with old Translation tab data for sites not using translation plugins
 */
if (!function_exists('mptbm_get_translation')) {
    function mptbm_get_translation($key, $default = '') {
        $current_locale = get_locale();
        
        // If site is using a non-English locale, prioritize WordPress translations (Loco Translate)
        // This ensures Loco Translate always works when translation files are active
        if ($current_locale !== 'en_US' && $current_locale !== 'en_GB') {
            return $default; // WordPress translation takes priority
        }
        
        // For English sites, check legacy database translations first
        // This maintains backward compatibility for existing clients using the old Translation tab
        $translations = get_option('mptbm_translations', array());
        
        if (isset($translations[$key]) && !empty($translations[$key])) {
            return esc_html($translations[$key]); // Legacy database value
        }
        
        // Final fallback to WordPress translation/default
        return $default;
    }
} 