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


    /*Pricing*/

        // ১. হ্যান্ডেল রেডিও ক্লিক (শুধুমাত্র একটি ওপেন হবে)
        $('.mptbm_taxi_pricing_input').on('change', function() {
            if ($(this).is(':checked')) {
                // সব রৌ কন্টেন্ট হাইড করো
                $('.mptbm_taxi_pricing_row_content').slideUp(300);
                $('.mptbm_taxi_pricing_item').removeClass('active_row');
                $('.mptbm_taxi_pricing_status_tag').text('OFF').css('color', '#94a3b8');

                // বর্তমানটি শো করো
                let $parent = $(this).closest('.mptbm_taxi_pricing_item');
                $parent.find('.mptbm_taxi_pricing_row_content').slideDown(300);
                $parent.addClass('active_row');
                $parent.find('.mptbm_taxi_pricing_status_tag').text('ACTIVE').css('color', '#4f46e5');
            }
        });

        // ২. হেডার ট্যাব ক্লিক লজিক
        $('.mptbm_taxi_pricing_tab_item').on('click', function() {
            $('.mptbm_taxi_pricing_tab_item').removeClass('active');
            $(this).addClass('active');

            // এখানে আপনি ট্যাব অনুযায়ী আলাদা কন্টেন্ট গ্রুপ শো/হাইড করতে পারেন
            console.log("Tab Switched: " + $(this).data('id'));
        });

        // ৩. ডিফল্ট স্টেট সেটআপ
        $('.mptbm_taxi_pricing_input:checked').each(function() {
            let $parent = $(this).closest('.mptbm_taxi_pricing_item');
            $parent.find('.mptbm_taxi_pricing_row_content').show();
            $parent.addClass('active_row');
            $parent.find('.mptbm_taxi_pricing_status_tag').text('ACTIVE').css('color', '#4f46e5');
        });


        $('.mptbm_taxi_pricing_add_route_full_btn').on('click', function() {
            var rowHtml = $('.mptbm_taxi_pricing_route_row:first').clone();
            rowHtml.find('input').val('');
            $('.mptbm_taxi_pricing_manual_list').append(rowHtml);
        });

        // Delete Row
        $(document).on('click', '.mptbm_taxi_pricing_delete_btn', function() {
            if($('.mptbm_taxi_pricing_route_row').length > 1) {
                $(this).closest('.mptbm_taxi_pricing_route_row').fadeOut(300, function() {
                    $(this).remove();
                });
            }
        });

        // Clone Row
        $(document).on('click', '.mptbm_taxi_pricing_clone_btn', function() {
            var $row = $(this).closest('.mptbm_taxi_pricing_route_row');
            var $clone = $row.clone();
            $row.after($clone.hide().fadeIn(300));
        });


        // Operation Area Add Area Price Row
        $('.mptbm_taxi_pricing_add_area_btn').on('click', function() {
            var row = $('.mptbm_taxi_pricing_area_row:first').clone();
            row.find('input').val('');
            $('.mptbm_taxi_pricing_area_list').append(row);
        });

        // Remove Area Price Row
        $(document).on('click', '.mptbm_taxi_pricing_remove_link', function() {
            if($('.mptbm_taxi_pricing_area_row').length > 1) {
                $(this).closest('.mptbm_taxi_pricing_area_row').remove();
            }
        });

        // Add New Route Table Row
        $('.mptbm_taxi_pricing_add_route_btn').on('click', function() {
            var tr = $('.mptbm_taxi_pricing_route_list tr:first').clone();
            tr.find('input').val('');
            $('.mptbm_taxi_pricing_route_list').append(tr);
        });


    $('.mptbm_taxi_pricing_area_pills').on('click', '.mptbm_taxi_pricing_pill', function(e) {
        e.preventDefault();

        var $this = $(this);
        var areaName = $this.text().trim();
        if ($this.hasClass('selected')) {
            $this.removeClass('selected');
            $this.find('i').remove();
        } else {
            $this.addClass('selected');
            // চেক আইকন যোগ করা (যদি আগে না থাকে)
            if ($this.find('i').length === 0) {
                $this.prepend('<i class="fas fa-check"></i> ');
            }
        }

        updateActiveIndicator();
    });

    function updateActiveIndicator() {
        var activeAreas = [];

        $('.mptbm_taxi_pricing_pill.selected').each(function() {
            var name = $(this).contents().filter(function() {
                return this.nodeType === 3;
            }).text().trim();
            if (name) {
                activeAreas.push('<span>' + name + '</span>');
            }
        });

        // যদি কোনো এরিয়া সিলেক্ট করা থাকে তবে সেটি দেখানো, নাহলে খালি রাখা
        if (activeAreas.length > 0) {
            $('.mptbm_taxi_pricing_active_indicator').html('Active: ' + activeAreas.join(' '));
            $('.mptbm_taxi_pricing_active_indicator').fadeIn(200);
        } else {
            $('.mptbm_taxi_pricing_active_indicator').html('Active: <i>None selected</i>');
        }
    }

    // পেজ লোড হওয়ার সময় ডিফল্ট স্টেট চেক করা
    updateActiveIndicator();


}(jQuery));