/**
 * Gravity Forms Compatibility Fix for E-cab Taxi Booking Manager
 * 
 * This script prevents JavaScript conflicts between the taxi booking manager
 * and Gravity Forms' drag-and-drop form builder functionality.
 * 
 * @version 2.0.0
 */

(function($) {
    'use strict';

    // Debug logging
    console.log('MPTBM Gravity Forms Compatibility Script v2.0 Loading...');
    console.log('Current URL:', window.location.href);
    console.log('URL Params:', window.location.search);

    // Check if we're on a Gravity Forms admin page
    function isGravityFormsAdminPage() {
        const checks = {
            url_gf: window.location.href.indexOf('page=gf_') !== -1,
            url_edit_forms: window.location.href.indexOf('gf_edit_forms') !== -1,
            url_gravityforms: window.location.href.indexOf('gravityforms') !== -1,
            body_class_toplevel: $('body').hasClass('toplevel_page_gf_edit_forms'),
            body_class_gforms: $('body').hasClass('gforms_page_gf_edit_forms'),
            gform_editor_element: $('#gform_editor').length > 0,
            gform_editor_class: $('.gform-editor').length > 0
        };
        
        console.log('Gravity Forms page checks:', checks);
        
        return Object.values(checks).some(check => check);
    }

    const isGfPage = isGravityFormsAdminPage();
    console.log('Is Gravity Forms page:', isGfPage);

    // Always apply aggressive fixes when this script loads (since it only loads on GF pages)
    if (true) {
        
        console.log('MPTBM: Gravity Forms compatibility mode activated');

        // Immediate aggressive fixes before document ready
        console.log('MPTBM: Applying immediate fixes...');
        
        // Override jQuery.fn.on to prevent problematic event handlers
        const originalOn = $.fn.on;
        $.fn.on = function(events, selector, data, handler) {
            if (typeof selector === 'string') {
                const problematicSelectors = [
                    'select2-container',
                    'select2-selection',
                    'select2-dropdown',
                    '[data-href]',
                    '[data-all-change]',
                    '[data-icon-change]',
                    '[data-text-change]',
                    '[data-class-change]',
                    '[data-value-change]',
                    '.mp_load_more_text_area'
                ];
                
                if (problematicSelectors.some(s => selector.includes(s))) {
                    console.log('MPTBM: Blocked event handler for:', selector, 'on events:', events);
                    return this; // Block the event handler
                }
            }
            return originalOn.apply(this, arguments);
        };
        
        // Override document event delegation to prevent conflicts
        const originalDocumentOn = $(document).on;
        $(document).on = function(events, selector, data, handler) {
            if (typeof events === 'string' && 
                (events.includes('mouse') || events.includes('click') || events.includes('drag'))) {
                if (typeof selector === 'string' && (
                    selector.includes('select2') ||
                    selector.includes('[data-') ||
                    selector === '*'
                )) {
                    console.log('MPTBM: Blocked document event:', events, 'for selector:', selector);
                    return $(document); // Block the event handler
                }
            }
            return originalDocumentOn.apply(this, arguments);
        };
        
        // Override problematic event handlers on document ready
        $(document).ready(function() {
            console.log('MPTBM: Document ready - applying additional fixes...');
            
            // Remove all existing problematic event handlers
            $(document).off('mouseenter', '.select2-container *');
            $(document).off('mouseenter', '.select2-selection *'); 
            $(document).off('mouseenter', '.select2-dropdown *');
            $(document).off('click', '.select2-selection__choice__remove');
            $(document).off('click', '[data-href]');
            $(document).off('click', '[data-all-change]');
            $(document).off('click', '[data-icon-change]');
            $(document).off('click', '[data-text-change]');
            $(document).off('click', '[data-class-change]');
            $(document).off('click', '[data-value-change]');
            $(document).off('click', '.mp_load_more_text_area [data-read]');
            
            console.log('MPTBM: Removed problematic event handlers');
            
            // Disable Owl Carousel if present
            if (typeof $.fn.owlCarousel !== 'undefined') {
                $('.owl-carousel').each(function() {
                    $(this).trigger('destroy.owl.carousel');
                    console.log('MPTBM: Destroyed Owl Carousel instance');
                });
            }
            
            // Disable Select2 if present
            if (typeof $.fn.select2 !== 'undefined') {
                $('.select2').each(function() {
                    $(this).select2('destroy');
                    console.log('MPTBM: Destroyed Select2 instance');
                });
            }
            
            console.log('MPTBM: All compatibility fixes applied successfully');
        });
        
        // Prevent interference with Gravity Forms drag events
        $(document).on('dragstart dragend dragover dragenter dragleave drop', function(e) {
            // Allow Gravity Forms drag events to proceed normally
            if ($(e.target).closest('#gform_editor, .gform-editor, .gfield, .gfield_admin_icons').length > 0) {
                e.stopImmediatePropagation();
                return true;
            }
        });
        
        // Re-enable handlers when leaving Gravity Forms pages
        $(window).on('beforeunload', function() {
            console.log('MPTBM: Restoring original event handlers');
            // Original handlers will be restored on page reload
        });
        
    }

})(jQuery);
