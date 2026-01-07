jQuery(document).ready(function($) {
    // Templates content
    const templates = {
        default: '<h3>No Transport Available !</h3>',
        template1: '<div class="no-transport-message"><i class="fas fa-car-alt"></i><h3>No Transport Available</h3></div>',
        template2: '<div class="no-transport-message"><h3>No Transport Available</h3><p>We apologize, but there are no vehicles available for your selected route and time. Please try different timings or contact us for assistance.</p></div>',
        template3: '<div class="no-transport-message"><h3>No Transport Available</h3><p>No vehicles found for your request. Need help?</p><div class="contact-info"><i class="fas fa-phone"></i> Call us: <a href="tel:+1234567890">123-456-7890</a></div></div>'
    };

    // When template dropdown changes
    $(document).on('change', 'select[name="mptbm_general_settings[no_transport_templates]"]', function() {
        const selectedTemplate = $(this).val();
        if (templates[selectedTemplate]) {
            $('textarea[name="mptbm_general_settings[no_transport_message]"]').val(templates[selectedTemplate]);
        }
    });
}); 