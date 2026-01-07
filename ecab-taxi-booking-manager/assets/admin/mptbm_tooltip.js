(function ($) {
	"use strict";
	
	$(document).ready(function () {
        // Initialize tooltips only on elements that are not Select2 related
        $(document).tooltip({
            items: "[title]:not(.select2-container *):not(.select2-selection *):not(.select2-dropdown *):not(.ui-datepicker *)",
            show: {
                effect: "fadeIn",
                duration: 200
            },
            hide: {
                effect: "fadeOut",
                duration: 200
            },
            position: {
                my: "left+5 center",
                at: "right center"
            }
        });
        
        // Completely disable tooltips for Select2 elements
        $(document).on('mouseenter', '.select2-container *, .select2-selection *, .select2-dropdown *', function(e) {
            e.stopPropagation();
            return false;
        });
        
        // Prevent tooltip initialization on Select2 elements
        $(document).on('select2:open', function() {
            // Remove any existing tooltips from Select2 elements
            $('.select2-container *').removeAttr('title');
        });
        
        // Handle Select2 choice removal
        $(document).on('click', '.select2-selection__choice__remove', function(e) {
            e.stopPropagation();
        });
    });
	 
}(jQuery));