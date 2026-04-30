(function ($) {
    $(document).ready(function() {

        // Toggle Section Content Visibility
        $('.mptbm_taxi_toggle_trigger').on('change', function() {
            const isChecked = $(this).is(':checked');
            const $parentSection = $(this).closest('.mptbm_taxi_toggle_box');
            const $badge = $parentSection.find('.mptbm_taxi_status_badge');
            const target = $(this).data('target');

            if (isChecked) {
                $(target).slideDown(300);
                $badge.text('ON').removeClass('mptbm_taxi_off');
            } else {
                $(target).slideUp(300);
                $badge.text('OFF').addClass('mptbm_taxi_off');
            }
        });

        // Simple Button Feedback (Mock navigation)
        /*$('.mptbm_taxi_btn_next').on('click', function() {
            console.log("Proceeding to Step 2...");
            // Logic to switch active class in stepper would go here
        });*/

        $('.mptbm_taxi_btn_prev').on('click', function() {
            console.log("Returning to previous screen...");
        });
    });



    $('.mptbm_taxi_btn_next').on('click', function (e) {
        e.preventDefault();

        let data = {
            action: 'save_mptbm_rent',
            post_id: $('input[name="post_id"]').val(),
            mptbm_maximum_passenger: $('input[name="mptbm_maximum_passenger"]').val(),
            mptbm_maximum_bag: $('input[name="mptbm_maximum_bag"]').val(),
            mptbm_extra_info: $('textarea[name="mptbm_extra_info"]').val()
        };

        let formData = $(this).serialize();


        $.post(ajaxurl, formData, function (response) {
            console.log('Saved:', response);
        });
    });

    $('#yourFormID').on('submit', function (e) {
        e.preventDefault();

        let formData = $(this).serialize(); // 🔥 collects ALL inputs by name

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData, // already includes all name fields
            success: function (response) {
                console.log('Success:', response);
            },
            error: function (err) {
                console.log('Error:', err);
            }
        });

    });

}(jQuery));