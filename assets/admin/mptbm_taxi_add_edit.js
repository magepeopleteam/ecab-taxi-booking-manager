(function ($) {
    $(document).ready(function() {

        let currentStep = 1;
        let totalSteps = $('.mptbm_taxi_step').length;
        function updateStep(step) {
            if (step < 1 || step > totalSteps) return;
            currentStep = step;
            $('.mptbm_taxi_step').each(function () {

                let itemStep = $(this).data('step');
                let iconBox = $(this).find('.mptbm_taxi_icon i');
                let originalIcon = $(this).data('icon');

                $(this).removeClass('mptbm_taxi_active completed');
                if (itemStep < step) {
                    $(this).addClass('completed');
                    iconBox.removeClass().addClass('fas fa-check');
                }
                else if (itemStep == step) {
                    $(this).addClass('mptbm_taxi_active');
                    iconBox.removeClass().addClass(originalIcon);
                }
                else {
                    iconBox.removeClass().addClass(originalIcon);
                }
            });
            $('.mptbm_taxi_wrapper > [data-step]').hide();
            $('.mptbm_taxi_wrapper > [data-step="' + step + '"]').show();
            $('.mptbm_taxi_step_counter').text('Step ' + step + ' of ' + totalSteps);
            $('.mptbm_taxi_btn_prev').prop('disabled', step === 1);
            $('.mptbm_taxi_btn_next').text(step === totalSteps ? 'Submit' : 'Next →');
        }
        $('.mptbm_taxi_step').on('click', function () {
            updateStep($(this).data('step'));
        });
        $('.mptbm_taxi_btn_next').on('click', function (e) {
            e.preventDefault();

            if (currentStep < totalSteps) {
                updateStep(currentStep + 1);
            } else {
                // last step → submit form
                // $('form').submit();
                let formData = $(this).serialize();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data:  $('.mptbm_rent_form').serialize(),
                    success: function (response) {
                        // console.log('Success:', response);
                    },
                    error: function (err) {
                        console.log('Error:', err);
                    }
                });
            }
        });
        $('.mptbm_taxi_btn_prev').on('click', function (e) {
            e.preventDefault();
            updateStep(currentStep - 1);
        });
        updateStep(1);

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

        $('.mptbm_taxi_btn_prev').on('click', function() {
            console.log("Returning to previous screen...");
        });
    });


    /*$('.mptbm_taxi_btn_next').on('click', function (e) {
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
    });*/

    $('#yourFormID').on('submit', function (e) {
        e.preventDefault();

        let formData = $(this).serialize();
        console.log( formData );

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function (response) {
                console.log('Success:', response);
            },
            error: function (err) {
                console.log('Error:', err);
            }
        });

    });


    /*Pricing*/
        $('.mptbm_taxi_pricing_input').on('change', function() {
            if ($(this).is(':checked')) {
                $('.mptbm_taxi_pricing_row_content').slideUp(300);
                $('.mptbm_taxi_pricing_item').removeClass('active_row');
                $('.mptbm_taxi_pricing_status_tag').text('OFF').css('color', '#94a3b8');

                let $parent = $(this).closest('.mptbm_taxi_pricing_item');
                $parent.find('.mptbm_taxi_pricing_row_content').slideDown(300);
                $parent.addClass('active_row');
                $parent.find('.mptbm_taxi_pricing_status_tag').text('ACTIVE').css('color', '#4f46e5');
            }
        });

        $('.mptbm_taxi_pricing_tab_item').on('click', function() {
            $('.mptbm_taxi_pricing_tab_item').removeClass('active');
            $(this).addClass('active');
            console.log("Tab Switched: " + $(this).data('id'));
        });

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


        $('.mptbm_taxi_pricing_add_area_btn').on('click', function() {
            var row = $('.mptbm_taxi_pricing_area_row:first').clone();
            row.find('input').val('');
            $('.mptbm_taxi_pricing_area_list').append(row);
        });

        $(document).on('click', '.mptbm_taxi_pricing_remove_link', function() {
            if($('.mptbm_taxi_pricing_area_row').length > 1) {
                $(this).closest('.mptbm_taxi_pricing_area_row').remove();
            }
        });

        $('.mptbm_taxi_pricing_add_route_btn').on('click', function() {
            var tr = $('.mptbm_taxi_pricing_route_list tr:first').clone();
            tr.find('input').val('');
            $('.mptbm_taxi_pricing_route_list').append(tr);
        });

        $('.mptbm_taxi_pricing_add_zone_btn').on('click', function() {
            var tr = $('.mptbm_taxi_pricing_fixed_zone_list tr:first').clone();
            tr.find('input').val('');
            $('.mptbm_taxi_pricing_fixed_zone_list').append(tr);
        });


    function getOperationType() {
        return $('#mptbm_operation_area_type').val();
    }
    function updateHiddenInput() {
        let values = [];

        $('.mptbm_taxi_pricing_pill.selected').each(function () {
            values.push($(this).data('id'));
        });

        $('#mptbm_selected_operation_areas').val(values.join(','));
    }
    $('.mptbm_taxi_pricing_area_pills').on('click', '.mptbm_taxi_pricing_pill', function (e) {
        e.preventDefault();

        let $this = $(this);
        let type = getOperationType();

        // SINGLE SELECT MODE
        if (type === 'geo-matched-operation-area-type') {

            $('.mptbm_taxi_pricing_pill')
                .removeClass('selected')
                .find('i').remove();

            $this.addClass('selected');

            if ($this.find('i').length === 0) {
                $this.prepend('<i class="fas fa-check"></i> ');
            }

        }
        // MULTI SELECT MODE
        else {

            if ($this.hasClass('selected')) {
                $this.removeClass('selected');
                $this.find('i').remove();
            } else {
                $this.addClass('selected');

                if ($this.find('i').length === 0) {
                    $this.prepend('<i class="fas fa-check"></i> ');
                }
            }
        }

        // 🔥 UPDATE EVERYTHING AFTER CLICK
        updateHiddenInput();
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
        if (activeAreas.length > 0) {
            $('.mptbm_taxi_pricing_active_indicator').html('Active: ' + activeAreas.join(' '));
            $('.mptbm_taxi_pricing_active_indicator').fadeIn(200);
        } else {
            $('.mptbm_taxi_pricing_active_indicator').html('Active: <i>None selected</i>');
        }
    }
    updateActiveIndicator();

    function updatePricingContainer() {
        let container = $('.mptbm_taxi_pricing_container');
        container.find('input, select, textarea')
            .not('input[type="radio"]')
            .prop('disabled', true);

        let activeItem = container.find('input[name="mptbm_price_based"]:checked')
            .closest('.mptbm_taxi_pricing_item');
        activeItem.find('input, select, textarea')
            .prop('disabled', false);
    }
    $(document).on('change', 'input[name="mptbm_price_based"]', function () {
        updatePricingContainer();
    });
    updatePricingContainer();

    function handleGroup(selector) {
        let selectedValues = [];

        // collect selected values (only same group)
        $(selector).each(function () {
            let val = $(this).val();
            if (val) {
                selectedValues.push(val);
            }
        });

        // reset options (only same group)
        $(selector).find('option').prop('disabled', false);

        // disable duplicates inside same group
        $(selector).each(function () {
            let current = $(this);

            selectedValues.forEach(function (value) {
                current.find('option[value="' + value + '"]')
                    .not(':selected')
                    .prop('disabled', true);
            });
        });
    }

    function updateSelections() {
        handleGroup('select[name="mptbm_fixed_map_route_start_location[]"]');
        handleGroup('select[name="mptbm_fixed_map_route_end_location[]"]');
    }

    // on change
    $(document).on('change', '.mptbm_fixed_map_route_start_location', function () {
        updateSelections();
    });
    // on change
    $(document).on('change', '.mptbm_fixed_map_route_end_location', function () {
        updateSelections();
    });
    // on change
    $(document).on('change', '.mptbm_operation_area_type', function () {
        $('#mptbm_selected_operation_areas').val('');
        $('.mptbm_taxi_pricing_pill')
            .removeClass('selected')
            .find('i').remove();
        $('.mptbm_taxi_pricing_active_indicator').html('Active: ');
    });

    // on load
    updateSelections();


/*Extra Service*/
    $(document).on('change','#mptbm_taxi_ex_service_master_toggle', function(e) {
        e.preventDefault();
        const isChecked = $(this).is(':checked');
        const label = $(this).closest('.mptbm_taxi_ex_service_toggle_wrapper').find('.mptbm_taxi_ex_service_toggle_label');

        alert('clicked');
        if(isChecked) {
            label.text('ON');
            $('.mptbm_taxi_ex_service_body').removeClass('mptbm_disabled');
        } else {
            label.text('OFF');
            $('.mptbm_taxi_ex_service_body').addClass('mptbm_disabled');
        }
    });

    // 2. Delete Row Functionality
    $(document).on('click', '.mptbm_taxi_ex_service_btn_del', function(e) {
        e.preventDefault();
        if(confirm('Are you sure you want to remove this service?')) {
            $(this).closest('tr').fadeOut(300, function() { $(this).remove(); });
        }
    });

    // 3. Add New Row Functionality
    $('#mptbm_taxi_ex_service_add_btn').on('click', function(e) {
        e.preventDefault();
        const newRow = `
            <tr class="mptbm_taxi_ex_service_row">
                <td>
                    <div class="mptbm_taxi_ex_service_icon_box">
                        <span class="mptbm_taxi_ex_service_icon_placeholder">🛡️</span>
                        <span class="mptbm_taxi_ex_service_remove_icon">×</span>
                    </div>
                </td>
                <td><input type="text" class="mptbm_taxi_ex_service_input" placeholder="Service Name"></td>
                <td><select class="mptbm_taxi_ex_service_select"><option>Lore</option></select></td>
                <td><input type="number" class="mptbm_taxi_ex_service_input mptbm_center" value="0"></td>
                <td><select class="mptbm_taxi_ex_service_select"><option>Input Box</option></select></td>
                <td class="mptbm_taxi_ex_service_actions">
                    <button class="mptbm_taxi_ex_service_btn_del">🗑️</button>
                    <button class="mptbm_taxi_ex_service_btn_drag">✥</button>
                </td>
            </tr>`;
        $('#mptbm_taxi_ex_service_tbody').append(newRow);
    });

    // Function to remove a row (using delegation for dynamic elements)


    /*Date Configuration*/
    // Add new off date row
    $('#mptbm_add_off_date').click(function(e) {
        e.preventDefault();
        let newRow = `
            <div class="mptbm_date_input_row" style="display:none;">
                <input type="text" placeholder="mm/dd/yyyy">
                <button class="mptbm_btn_remove">Remove</button>
            </div>`;
        $(newRow).appendTo('#mptbm_off_dates_container').slideDown(200);
    });

    // Remove off date row
    $(document).on('click', '.mptbm_btn_remove', function() {
        $(this).parent('.mptbm_date_input_row').slideUp(200, function() {
            $(this).remove();
        });
    });



}(jQuery));