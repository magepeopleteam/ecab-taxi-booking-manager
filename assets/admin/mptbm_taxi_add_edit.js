(function ($) {
    $(document).ready(function() {

        let currentStep = 1;
        let totalSteps = $('.mptbm_taxi_step').length;
        function updateStep(step) {

            if (step < 1 || step > totalSteps) return;

            currentStep = step;

            $('.mptbm_taxi_step').each(function () {

                let itemStep = $(this).data('step');

                $(this).removeClass('mptbm_taxi_active completed');

                if (itemStep < step) {
                    $(this).addClass('completed');
                }
                else if (itemStep == step) {
                    $(this).addClass('mptbm_taxi_active');
                }
            });

            $('.mptbm_taxi_content_container > [data-step]').hide();
            $('.mptbm_taxi_content_container > [data-step="' + step + '"]').show();

            $('.mptbm_taxi_step_counter').text('Step ' + step + ' of ' + totalSteps);

            $('.mptbm_taxi_btn_prev').prop('disabled', step === 1);

            if (step === totalSteps) {

                $('.mptbm_taxi_btn_next')
                    .text('Submit')
                    .attr('type', 'submit')
                    .removeClass('button-next')
                    .addClass('button-submit');

            } else {

                $('.mptbm_taxi_btn_next')
                    .text('Next →')
                    .attr('type', 'button')
                    .removeClass('button-submit')
                    .addClass('button-next');
            }
        }

        $('.mptbm_taxi_step').on('click', function () {
            updateStep($(this).data('step'));
        });
        $('.mptbm_taxi_btn_next').on('click', function (e) {

            if (currentStep < totalSteps) {
                e.preventDefault();
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

        // 1. Master Toggle Feature Functionality
        $('#mptbm_taxi_feature_master_toggle').on('change', function(e) {
            e.preventDefault();
            const isChecked = $(this).is(':checked');
            if( isChecked ){
                $('.mptbm_taxi_feature_body').fadeIn();
                $('.mptbm_taxi_feature_switch_text').text('On');
            }else{
                $('.mptbm_taxi_feature_body').fadeOut();
                $('.mptbm_taxi_feature_switch_text').text('Off');
            }

            // $(this).closest('.mptbm_taxi_feature_switch').find('span').text(isChecked ? 'ON' : 'OFF');
        });

        // 1. Custom Message Toggle
        $(document).on('change', '#mptbm_price_display_type', function(e) {
            let changeValue = $(this).val();
            if( changeValue === 'custom_message' ){
                $("#mptbm_custom_message_show").fadeIn();
            }else{
                $("#mptbm_custom_message_show").fadeOut();
            }

        });

        // 1. Inventory Toggle Functionality
        $('#mptbm_enable_inventory').on('change', function(e) {
            e.preventDefault();
            const isChecked = $(this).is(':checked');
            if( isChecked ){
                $('.mptbm_taxi_inventory_manage_body').fadeIn();
                $('.mptbm_taxi_inventory_switch_text').text('On');
            }else{
                $('.mptbm_taxi_inventory_manage_body').fadeOut();
                $('.mptbm_taxi_inventory_switch_text').text('Off');
            }

            // $(this).closest('.mptbm_taxi_feature_switch').find('span').text(isChecked ? 'ON' : 'OFF');
        });

        $(document).on('click', '.mptbm_taxi_feature_btn_del', function(e) {
            e.preventDefault();
            if(confirm('Are you sure you want to remove this feature?')) {
                $(this).closest('.mptbm_taxi_feature_row').fadeOut(300, function() {
                    $(this).remove();
                });
            }
        });

        $(document).on('click', '.mptbm_taxi_feature_remove_icon', function(e) {
            e.preventDefault();
            $(this).siblings('i').attr('class', 'fas fa-image'); // Reset to placeholder
        });

        $('#mptbm_taxi_feature_add_row').on('click', function(e) {
            e.preventDefault();
            const newRow = `
                <div class="mptbm_taxi_feature_row" style="display:none;">
                    <div class="mptbm_taxi_feature_icon_box">
                        <i class="fas fa-gear"></i>
                        <div class="mptbm_taxi_feature_remove_icon"><i class="fas fa-times"></i></div>
                    </div>
                    <input type="text" class="mptbm_taxi_feature_input" name="mptbm_features_label[]" placeholder="Label">
                    <input type="text" class="mptbm_taxi_feature_input" name="mptbm_features_text[]" placeholder="Value">
                    <div class="mptbm_taxi_feature_actions">
                        <button class="mptbm_taxi_feature_btn_icon mptbm_taxi_feature_btn_del">🗑️</button>
                            <button class="mptbm_taxi_feature_btn_icon mptbm_taxi_feature_btn_move">✥</button>
                    </div>
                </div>`;

            $(newRow).appendTo('#mptbm_taxi_feature_list').fadeIn(300);
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

        $(document).on('click','.mptbm_taxi_pricing_add_route_btn', function() {
            var tr = $('.mptbm_taxi_pricing_route_list tr:first').clone();
            tr.find('input').val('');
            $('.mptbm_taxi_pricing_route_list').append(tr);
        });

        $(document).on('click','.mptbm_taxi_pricing_add_zone_to_zone_route_btn', function() {
            var tr = $('.mptbm_taxi_pricing_zone_to_zone_route_list tr:first').clone();
            tr.find('input').val('');
            $('.mptbm_taxi_pricing_zone_to_zone_route_list').append(tr);
        });

        $(document).on('click', '.mptbm_taxi_pricing_add_zone_btn', function() {
            var tr = $('.mptbm_taxi_pricing_zone_to_zone_route_list tr:first').clone();
            tr.find('input').val('');
            $('.mptbm_taxi_pricing_zone_to_zone_route_list').append(tr);
        });


        function getOperationType() {
            return $('#mptbm_operation_area_type').val();
        }
        function updateHiddenInput() {
            let values = [];

            $('.mptbm_taxi_pricing_pill.selected').each(function () {
                values.push($(this).data('id'));
            });

            if (values.length > 0) {
               $("#mptbm_taxi_operation_area_pricing_section").fadeIn();
            } else {
                $("#mptbm_taxi_operation_area_pricing_section").fadeOut();
            }

            $('#mptbm_selected_operation_areas').val(values.join(','));
        }
        $('.mptbm_taxi_pricing_area_pills').on('click', '.mptbm_taxi_pricing_pill', function (e) {
            e.preventDefault();

            let $this = $(this);
            let type = getOperationType();

            // SINGLE SELECT MODE
            if (type === 'geo-matched-operation-area-type' || type === 'geo-fence-operation-area-type' ) {

                $('.mptbm_taxi_pricing_pill')
                    .removeClass('selected')
                    .find('i').remove();

                $this.addClass('selected');

                if ($this.find('i').length === 0) {
                    $this.prepend('<i class="fas fa-check"></i> ');
                }

            }else {

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

            $("#mptbm_operation_area_settings").fadeIn();
            // 🔥 UPDATE EVERYTHING AFTER CLICK
            updateHiddenInput();
            updateActiveIndicator();
        });

        function togglePricingAreaButtons() {
            let operationType = $('#mptbm_operation_area_type').val();
            $('.mptbm_taxi_pricing_pill').fadeOut();
            if (operationType === 'geo-fence-operation-area-type') {
                $('.mptbm_taxi_pricing_pill[data-geo-fance="1"]').fadeIn();
            } else {
                $('.mptbm_taxi_pricing_pill[data-geo-fance="0"]').fadeIn();
            }

            if (operationType === 'geo-matched-operation-area-type' || operationType === 'geo-fence-operation-area-type' ) {
                $("#mptbm_single_mul_operation_area").text( 'single allowed' );
            }else{
                $("#mptbm_single_mul_operation_area").text( 'multiple allowed' );
            }
        }
        togglePricingAreaButtons();

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

        function mptbm_hide_all_pricing_content(){
            $("#mptbm_distance_price").fadeOut();
            $("#mptbm_fixed_pricing").fadeOut();
            $("#mptbm_price_per_hour").fadeOut();
            $("#mptbm_manual_routes").fadeOut();
            $("#mptbm_operation_area").fadeOut();
            $("#mptbm_row_zone").fadeOut();
        }

        $(document).on('click', '.mptbm_taxi_pricing_tab_item_area', function () {
            let clicked_tab_id = $(this).data('id');
            let price_based = '';
            let rules = '';
            let is_operation_selected = $("#mptbm_is_selected_operation_area").val();
            // mptbm_hide_all_pricing_content();
            $('.mptbm_taxi_pricing_tab_item_area').removeClass('active');
            $(this).addClass('active');
            if(clicked_tab_id === 'mptbm_row_operation_area' ){
                price_based = 'fixed_distance';
                if( is_operation_selected == 1 ){
                    $("#mptbm_fixed_map_area_pricing").fadeIn();
                    $("#mptbm_operation_area_settings").fadeIn();
                    $("#mptbm_fixed_zone_area_pricing").fadeOut();

                    $("#mptbm_distance_price").fadeIn();
                    $("#mptbm_fixed_pricing").fadeIn();
                    $("#mptbm_price_per_hour").fadeIn();
                }
                $("#mptbm_operation_area_settings").fadeIn();
                $("#mptbm_area_based_wrapper").fadeIn();

                let shortcode = "<code>[mptbm_booking price_based='fixed_map' form='horizontal' progressbar='yes' map='yes']</code>";
                $("#mptbm_shortcode_example_code").html(shortcode);

                let primary_shortcode = "<code>[mptbm_booking price_based='fixed_map']</code>";
                $("#mptbm_shortcode_primary_code").html(primary_shortcode);

                rules = `<div class="mptbm_pricing_rules_card">
                                <h4>Fixed Map Zone-based Pricing</h4>
                                <p>Zone-based fixed pricing or fallback calculation.</p>
                                <div class="mptbm_pricing_rules_formula">
                                    First checks predefined zone route price
                                    If matched → fixed route price is applied
                                    If not matched → fallback calculation:
                                    Hourly + Distance pricing OR
                                    Operation area pricing override
    
                                    Formula (fallback):
    
                                    (Hour Price × Duration) + (KM Price × Distance)
                                </div>
                            </div>`;

            }else if(clicked_tab_id === 'mptbm_row_zone' ){
                price_based = 'fixed_zone';
                if( is_operation_selected == 1 ) {
                    $("#mptbm_fixed_zone_area_pricing").fadeIn();
                    $("#mptbm_fixed_map_area_pricing").fadeOut();
                    $("#mptbm_operation_area_settings").fadeOut();
                }
                $("#mptbm_operation_area_settings").fadeOut();
                $("#mptbm_area_based_wrapper").fadeOut();

                $("#mptbm_distance_price").fadeOut();
                $("#mptbm_fixed_pricing").fadeOut();
                $("#mptbm_price_per_hour").fadeOut();

                let shortcode = "<code>[mptbm_booking price_based='fixed_zone_pickup' form='horizontal' progressbar='yes' map='yes']</code>";
                $("#mptbm_shortcode_example_code").html(shortcode);
                let primary_shortcode = "<code>[mptbm_booking price_based='fixed_zone_pickup']</code>";
                $("#mptbm_shortcode_primary_code").html(primary_shortcode);

                rules = `<div class="mptbm_pricing_rules_card">
                                <h4>Fixed Zone Based Pricing </h4>
                                <p>Price depends on selected start & end zones:</p>
                                <div class="">
                                    If pickup and dropoff zones match predefined route → fixed price applied
                                    Otherwise geo-zone matching is used
                                    Different logic for pickup vs dropoff mode
                                    Result:
                                    Fixed route price if matched
                                </div>
                            </div>`;
            }
            $('input[name="mptbm_price_based"]').val(price_based);

            $("#mptbm_pricing_rules_grid").html(rules);
        });

        $(document).on('click', '.mptbm_taxi_pricing_tab_item', function () {
            let clicked_tab_id = $(this).data('id');
            let price_based = '';
            let rules = '';
            mptbm_hide_all_pricing_content();
            $('.mptbm_taxi_pricing_tab_item').removeClass('active');
            $(this).addClass('active');
            if( clicked_tab_id === 'mptbm_inclusive' ){
                price_based = 'inclusive';
                $('input[name="mptbm_price_based"]').val(price_based);
                $("#mptbm_distance_price").fadeIn();
                $("#mptbm_price_per_hour").fadeIn();
                // $("#mptbm_manual_routes").fadeIn();
               /* $("#mptbm_manual_routes_and_fixed_fare_overrides").fadeIn();
                $('#mptbm_taxi_inclusive_manual_locations').prop('checked', false);*/

                let shortcode = "<code>[mptbm_booking price_based='dynamic' form='horizontal' progressbar='yes' map='yes']</code>";
                $("#mptbm_shortcode_example_code").html(shortcode);

                let primary_shortcode = "<code>[mptbm_booking price_based='dynamic']</code>";
                $("#mptbm_shortcode_primary_code").html(primary_shortcode);

                rules = `<div class="mptbm_pricing_rules_card">
                                    <h4>Inclusive (Distance + Duration) Based Pricing</h4>
                                    <p>Price is calculated using both time and distance.</p>
                                    <div class="mptbm_pricing_rules_formula">
                                        (Hourly Rate × Duration) + (KM Rate × Distance)
                                    </div>
                                </div>`;

            }
            else if(clicked_tab_id === 'mptbm_distance' ){
                price_based = 'distance';
                $('input[name="mptbm_price_based"]').val(price_based);
                $("#mptbm_distance_price").fadeIn();
                let shortcode = "<code>[mptbm_booking price_based='dynamic' form='horizontal' progressbar='yes' map='yes']</code>";
                $("#mptbm_shortcode_example_code").html(shortcode);

                let primary_shortcode = "<code>[mptbm_booking price_based='dynamic']</code>";
                $("#mptbm_shortcode_primary_code").html(primary_shortcode);
                $("#mptbm_manual_routes_and_fixed_fare_overrides").fadeOut();

                rules = `<div class="mptbm_pricing_rules_card">
                                    <h4>Distance Based Pricing</h4>
                                    <p>Only distance is used for calculation.</p>
                                    <div class="mptbm_pricing_rules_formula">
                                        KM Rate × Distance
                                    </div>
                                </div>`;
            }
            else if(clicked_tab_id === 'mptbm_row_duration' ){
                price_based = 'duration';
                $('input[name="mptbm_price_based"]').val(price_based);
                $("#mptbm_price_per_hour").fadeIn();
                let shortcode = "<code>[mptbm_booking price_based='dynamic' form='horizontal' progressbar='yes' map='yes']</code>";
                $("#mptbm_shortcode_example_code").html(shortcode);

                let primary_shortcode = "<code>[mptbm_booking price_based='dynamic']</code>";
                $("#mptbm_shortcode_primary_code").html(primary_shortcode);
                $("#mptbm_manual_routes_and_fixed_fare_overrides").fadeOut();

                rules = `<div class="mptbm_pricing_rules_card">
                            <h4>Duration Based Pricing</h4>
                            <p>Only travel time is considered.</p>
                            <div class="mptbm_pricing_rules_formula">
                                Hourly Rate × Duration
                            </div>
                        </div>`;

            }
            else if(clicked_tab_id === 'mptbm_row_dist_dur' ){
                price_based = 'distance_duration';
                $('input[name="mptbm_price_based"]').val(price_based);
                $("#mptbm_distance_price").fadeIn();
                $("#mptbm_price_per_hour").fadeIn();
                let shortcode = "<code>[mptbm_booking price_based='dynamic' form='horizontal' progressbar='yes' map='yes']</code>";
                $("#mptbm_shortcode_example_code").html(shortcode);

                let primary_shortcode = "<code>[mptbm_booking price_based='dynamic']</code>";
                $("#mptbm_shortcode_primary_code").html(primary_shortcode);
                $("#mptbm_manual_routes_and_fixed_fare_overrides").fadeOut();

                rules = `<div class="mptbm_pricing_rules_card">
                            <h4> Distance + Duration Based Pricing</h4>
                            <p>Combines both distance and time pricing.</p>
                            <div class="mptbm_pricing_rules_formula">
                                (Hourly Rate × Duration) + (KM Rate × Distance)
                            </div>
                        </div>`;

            }
            else if(clicked_tab_id === 'mptbm_row_hourly' ){
                price_based = 'fixed_hourly';
                $("#mptbm_price_per_hour").fadeIn();
                let shortcode = "<code>[mptbm_booking price_based='fixed_hourly' form='horizontal' progressbar='yes' map='yes']</code>";
                $("#mptbm_shortcode_example_code").html(shortcode);

                let primary_shortcode = "<code>[mptbm_booking price_based='fixed_hourly']</code>";
                $("#mptbm_shortcode_primary_code").html(primary_shortcode);
                $("#mptbm_manual_routes_and_fixed_fare_overrides").fadeOut();

                rules = `<div class="mptbm_pricing_rules_card">
                            <h4>Fixed Hourly Based Pricing</h4>
                            <p>Fixed hourly pricing applied.</p>
                            <div class="mptbm_pricing_rules_formula">
                                Hour Rate × Fixed Time
                            </div>
                        </div>`;
                $('input[name="mptbm_price_based"]').val(price_based);
            }
            else if(clicked_tab_id === 'mptbm_row_operation_area' ){
                price_based = 'fixed_distance';
                $("#mptbm_operation_area").fadeIn();
                $("#mptbm_area_based_wrapper").fadeIn();
                let shortcode = "<code>[mptbm_booking price_based='fixed_map' form='horizontal' progressbar='yes' map='yes']</code>";
                $("#mptbm_shortcode_example_code").html(shortcode);

                let primary_shortcode = "<code>[mptbm_booking price_based='fixed_map']</code>";
                $("#mptbm_shortcode_primary_code").html(primary_shortcode);
                $("#mptbm_manual_routes_and_fixed_fare_overrides").fadeOut();

                let activeDataId = $('.mptbm_taxi_pricing_tab_item_area.active').data('id');



                if( activeDataId === 'mptbm_row_operation_area' ){
                    $("#mptbm_distance_price").fadeIn();
                    $("#mptbm_fixed_pricing").fadeIn();
                    $("#mptbm_price_per_hour").fadeIn();



                    rules = `<div class="mptbm_pricing_rules_card">
                                <h4>Fixed Map Zone-based Pricing</h4>
                                <p>Zone-based fixed pricing or fallback calculation.</p>
                                <div class="mptbm_pricing_rules_formula">
                                    First checks predefined zone route price
                                    If matched → fixed route price is applied
                                    If not matched → fallback calculation:
                                    Hourly + Distance pricing OR
                                    Operation area pricing override
    
                                    Formula (fallback):
    
                                    (Hour Price × Duration) + (KM Price × Distance)
                                </div>
                            </div>`;
                }else if( activeDataId === 'mptbm_row_zone' ){
                    $("#mptbm_distance_price").fadeOut();
                    $("#mptbm_fixed_pricing").fadeOut();
                    $("#mptbm_price_per_hour").fadeOut();
                    rules = `<div class="mptbm_pricing_rules_card">
                                <h4>Fixed Zone Based Pricing </h4>
                                <p>Price depends on selected start & end zones:</p>
                                <div class="">
                                    If pickup and dropoff zones match predefined route → fixed price applied
                                    Otherwise geo-zone matching is used
                                    Different logic for pickup vs dropoff mode
                                    Result:
                                    Fixed route price if matched
                                </div>
                            </div>`;
                }else{
                    $("#mptbm_distance_price").fadeIn();
                    $("#mptbm_fixed_pricing").fadeIn();
                    $("#mptbm_price_per_hour").fadeIn();

                    let is_operation_selected = $("#mptbm_is_selected_operation_area").val();
                    if( is_operation_selected == 1 ) {
                        $("#mptbm_fixed_map_area_pricing").fadeIn();
                    }

                    $('.mptbm_taxi_pricing_tab_item_area').removeClass( 'active');
                    rules = `<div class="mptbm_pricing_rules_card">
                                <h4>Fixed Map Zone-based Pricing</h4>
                                <p>Zone-based fixed pricing or fallback calculation.</p>
                                <div class="mptbm_pricing_rules_formula">
                                    First checks predefined zone route price
                                    If matched → fixed route price is applied
                                    If not matched → fallback calculation:
                                    Hourly + Distance pricing OR
                                    Operation area pricing override
    
                                    Formula (fallback):
    
                                    (Hour Price × Duration) + (KM Price × Distance)
                                </div>
                            </div>`;

                    // $('#mptbm_taxi_pricing_fixed_map').addClass('active');
                }
            }
            else if(clicked_tab_id === 'mptbm_row_manual' ){
                price_based = 'manual';
                $("#mptbm_manual_routes").fadeIn();
                let shortcode = "<code>[mptbm_booking price_based='manual' form='horizontal' progressbar='yes' map='yes']</code>";
                $("#mptbm_shortcode_example_code").html(shortcode);

                let primary_shortcode = "<code>[mptbm_booking price_based='manual']</code>";
                $("#mptbm_shortcode_primary_code").html(primary_shortcode);
                $("#mptbm_manual_routes_and_fixed_fare_overrides").fadeOut();

                rules = `<div class="mptbm_pricing_rules_card">
                            <h4>Manual Pricing </h4>
                            <p>Admin-defined exact route pricing.</p>
                            <div class="mptbm_pricing_rules_formula">
                                Fixed Route Price
                            </div>
                        </div>`;
                $('input[name="mptbm_price_based"]').val(price_based);

            }
            else if(clicked_tab_id === 'mptbm_row_zone' ){
                price_based = 'fixed_zone';
                $("#mptbm_row_zone").fadeIn();
                let shortcode = "<code>[mptbm_booking price_based='fixed_zone_pickup' form='horizontal' progressbar='yes' map='yes']</code>";
                $("#mptbm_shortcode_example_code").html(shortcode);

                let primary_shortcode = "<code>[mptbm_booking price_based='fixed_zone_pickup']</code>";
                $("#mptbm_shortcode_primary_code").html(primary_shortcode);
                $("#mptbm_taxi_operation_area_pricing_section").fadeOut();
                $('input[name="mptbm_price_based"]').val(price_based);
            }
            else{
                price_based = 'inclusive';
                $("#mptbm_distance_price").fadeIn();
                $("#mptbm_fixed_pricing").fadeIn();
                $("#mptbm_price_per_hour").fadeIn();
                // $("#mptbm_manual_routes").fadeIn();
                let shortcode = "<code>[mptbm_booking price_based='dynamic' form='horizontal' progressbar='yes' map='yes']</code>";
                $("#mptbm_shortcode_example_code").html(shortcode);
                $("#mptbm_manual_routes_and_fixed_fare_overrides").fadeIn();

                rules = `<div class="mptbm_pricing_rules_card">
                                <h4>Inclusive (Distance + Duration) Based Pricing</h4>
                                <p>Price is calculated using both time and distance.</p>
                                <div class="mptbm_pricing_rules_formula">
                                    (Hourly Rate × Duration) + (KM Rate × Distance)
                                </div>
                            </div>`;
                $('input[name="mptbm_price_based"]').val(price_based);
            }



            $("#mptbm_pricing_rules_grid").html(rules);
            // alert(clicked_tab_id );
        });

        // updatePricingContainer();

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
            // handleGroup('select[name="mptbm_fixed_map_route_end_location[]"]');
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

            togglePricingAreaButtons();
        });

        // on load
        updateSelections();

        $(document).on('click', '.mptbm_taxi_pricing_del_icon', function (e) {

            e.preventDefault();

            $(this).closest('tr').remove();

        });

        $(document).on('change','#mptbm_taxi_inclusive_manual_locations', function(e) {
            e.preventDefault();
            const isChecked = $(this).is(':checked');
            const label = $(this).closest('.manual_routes_and_fixed_fare_toggle_wrapper').find('.mptbm_manual_routes_and_fixed_fare_toggle_label');

            if(isChecked) {
                label.text('ON');
                $("#mptbm_manual_routes").fadeIn();
            } else {
                label.text('OFF');
                $("#mptbm_manual_routes").fadeOut();
            }
        });

    /*Extra Service*/

        $(document).on('change','#mptbm_taxi_ex_service_master_toggle', function(e) {
            e.preventDefault();
            const isChecked = $(this).is(':checked');
            const label = $(this).closest('.mptbm_taxi_ex_service_toggle_wrapper').find('.mptbm_taxi_ex_service_toggle_label');

            if(isChecked) {
                label.text('ON');
                $('.mptbm_taxi_ex_service_body').removeClass('mptbm_disabled');
                $('#mptbm_taxi_ex_service_body').fadeIn();
            } else {
                label.text('OFF');
                $('.mptbm_taxi_ex_service_body').addClass('mptbm_disabled');
                $('#mptbm_taxi_ex_service_body').fadeOut();
            }
        });


    /*Base Fare Settings*/
        $(document).on('change','#mptbm_display_taxi_base_fare_pricing', function(e) {
            e.preventDefault();
            const isChecked = $(this).is(':checked');
            const label = $(this).closest('#mptbm_taxi_base_fare_toggle_container').find('.mptbm_display_taxi_base_fare_pricing_level');

            if(isChecked) {
                label.text('ON');
                $('.mptbm_taxi_base_price_body').removeClass('mptbm_disabled');
                $('#mptbm_taxi_base_price_body').fadeIn();
            } else {
                label.text('OFF');
                $('.mptbm_taxi_base_price_body').addClass('mptbm_disabled');
                $('#mptbm_taxi_base_price_body').fadeOut();
            }
        });

    /*Base Location Settings*/
        $(document).on('change','#mptbm_display_taxi_base_location_pricing', function(e) {
            e.preventDefault();
            const isChecked = $(this).is(':checked');
            const label = $(this).closest('#mptbm_taxi_base_location_toggle_container').find('.mptbm_display_taxi_base_location_pricing_level');

            if(isChecked) {
                label.text('ON');
                $('.mptbm_taxi_base_location_price_body').removeClass('mptbm_disabled');
                $('#mptbm_taxi_base_location_price_body').fadeIn();
            } else {
                label.text('OFF');
                $('.mptbm_taxi_base_location_price_body').addClass('mptbm_disabled');
                $('#mptbm_taxi_base_location_price_body').fadeOut();
            }
        });

        // 2. Delete Row Functionality
        $(document).on('change', '#mptbm_extra_services_id', function(e) {
            let service_id = $(this).val();
            let nonce = $('#mptbm_extra_service_nonce').val();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mptbm_get_services_data',
                    service_id: service_id,
                    post_id: 37,
                    nonce: nonce
                },
                success: function (response) {
                    console.log(response);
                    $("#mptbm_taxi_ex_service_tbody").html(response.data.service_date);
                }
            });

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
                            <span class="mptbm_taxi_ex_service_icon_placeholder">😊</span>
                            <span class="mptbm_taxi_ex_service_remove_icon">×</span>
                        </div>
                    </td>
                    <td><input type="text" name="service_name[]" placeholder="Service Name" class="mptbm_taxi_ex_service_input" value=""></td>
                    <td>
                        <textarea class="mptbm_taxi_ex_service_select" name="extra_service_description[]" placeholder="Desc.."></textarea>
                    </td>
                    <td><input type="number" class="mptbm_taxi_ex_service_input mptbm_center" value="0"></td>
                    <td>
                        <select class="mptbm_taxi_ex_service_select">
                             <option value="inputbox">Input Box</option>
                            <option value="dropdown">Dropdown List</option>
                        </select>
                    </td>
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


        /*Date And Advanced*/
        /**
         * 1. Section Toggle Logic
         * Disables/Enables entire sections based on the header switch.
         */
        $('.mptbm_taxi_advanced_toggle input').on('change', function(e) {
            e.preventDefault();
            const isChecked = $(this).is(':checked');
            const $card = $(this).closest('.mptbm_taxi_advanced_card');
            const $content = $card.find('.mptbm_taxi_advanced_card_body, .mptbm_taxi_advanced_table_container, .mptbm_taxi_advanced_tax_alert');

            if (!isChecked) {
                $content.css({
                    'opacity': '0.5',
                    'pointer-events': 'none',
                    'filter': 'grayscale(1)'
                });
                $content.find('input, select, button').prop('disabled', true);
            } else {
                $content.css({
                    'opacity': '1',
                    'pointer-events': 'auto',
                    'filter': 'none'
                });
                $content.find('input, select, button').prop('disabled', false);
            }
        });

        /**
         * 2. Schedule "Default" Syncing
         * If the "Default" row is changed, update all sub-rows that are set to "Default".
         */
        $('.mptbm_taxi_advanced_row_default select').on('change', function(e) {
            e.preventDefault();
            const type = $(this).parent().attr('class'); // Detect if it's start or end col
            const val = $(this).val();

            $('.mptbm_taxi_advanced_weekly_list .mptbm_taxi_advanced_table_row').each(function(e) {
                e.preventDefault();
                const $targetSelect = $(this).find('.' + type.split(' ')[0] + ' select');
                if ($targetSelect.val() === "Default") {
                    // In a real app, you might trigger a visual flash or just update data
                    console.log("Syncing default values...");
                }
            });
        });

        /**
         * 3. Driver Selection Update
         * Updates the info box when a different driver is selected.
         */
        $('.mptbm_taxi_advanced_driver_select_row select').on('change', function(e) {
            e.preventDefault();
            const selectedDriver = $(this).val();
            const $infoBox = $('.mptbm_taxi_advanced_driver_info_box');

            // Example data mapping
            const driverData = {
                "John Conner": { username: "John", email: "eyesblade30@gmail.com" },
                "Sarah Connor": { username: "SarahC", email: "sarah.c@sky.net" }
            };

            if (driverData[selectedDriver]) {
                $infoBox.find('.mptbm_taxi_advanced_info_col:eq(0) p').text(selectedDriver);
                $infoBox.find('.mptbm_taxi_advanced_info_col:eq(1) p').text(driverData[selectedDriver].username);
                $infoBox.find('.mptbm_taxi_advanced_info_col:eq(2) p').text(driverData[selectedDriver].email);
            }
        });

        /**
         * 4. Navigation Button Actions
         */
        $('.mptbm_taxi_advanced_btn_primary').on('click', function(e) {
            e.preventDefault();
            alert("Proceeding to Step 4...");
        });

        $('.mptbm_taxi_advanced_btn_secondary:contains("Save Draft")').on('click', function(e) {
            e.preventDefault();
            $(this).text("Saving...").prop('disabled', true);
            setTimeout(() => {
                $(this).text("Save Draft").prop('disabled', false);
                alert("Draft Saved Successfully!");
            }, 1000);
        });

        /**
         * 5. Add New Off Date (Dynamic Row Example)
         */
        $('.mptbm_taxi_advanced_btn_primary_alt').on('click', function(e) {
            e.preventDefault();
            const newDateRow = `
                <div class="mptbm_taxi_advanced_dynamic_date" style="margin-top:10px; display:flex; gap:10px;">
                    <input type="date" class="mptbm_taxi_advanced_input_wrap" style="width:200px">
                    <button class="mptbm_taxi_advanced_remove_date" style="color:red; border:none; background:none; cursor:pointer;">&times; Remove</button>
                </div>`;
            $(this).parent().append(newDateRow);
        });

        $(document).on('click', '.mptbm_taxi_advanced_remove_date', function(e) {
            e.preventDefault();
            $(this).parent().remove();
        });

        $(document).on('click', '.mptbm_shortcode_header', function (e) {
            e.preventDefault();
            let container = $(this).closest('.mptbm_shortcode_container');
            let body = container.find('.mptbm_shortcode_body');
            let arrow = container.find('.mptbm_shortcode_toggle div');
            body.stop(true, true).slideToggle(300);
            arrow.toggleClass('mptbm_rotate');
        });

        $(document).on('click', '.mptbm_taxi_pricing_tab_item_pro', function(){
            $('#mptbm_pro_popup').fadeIn();
        });

        $(document).on( 'click','.mptbm_pro_close_popup', function(){
            $('#mptbm_pro_popup').fadeOut();
        });

        $(document).on('click', '#mptbm_pro_popup',function(e){
            if (e.target === this) {
                $(this).fadeOut();
            }
        });

        /*Feature Images*/
        let mptbm_feature_image_frame;
        // Select Image
        $('.mptbm_feature_image_select').on('click', function (e) {
            e.preventDefault();
            if (mptbm_feature_image_frame) {
                mptbm_feature_image_frame.open();
                return;
            }
            mptbm_feature_image_frame = wp.media({
                title: 'Select Featured Image',
                button: {
                    text: 'Use Image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            mptbm_feature_image_frame.on('select', function () {
                let attachment = mptbm_feature_image_frame
                    .state()
                    .get('selection')
                    .first()
                    .toJSON();
                // Save Image ID
                $('#mptbm_feature_image_id').val(attachment.id);

                // Preview Image
                $('.mptbm_feature_image_preview').html(
                    '<img src="' + attachment.url + '" alt="">'
                );
                $('.mptbm_feature_image_wrapper')
                    .attr('data-has-image', '1');
            });
            mptbm_feature_image_frame.open();
        });
        // Remove Image
        $('.mptbm_feature_image_remove').on('click', function (e) {
            e.preventDefault();
            $('#mptbm_feature_image_id').val('');

            $('.mptbm_feature_image_preview').html(
                '<div class="mptbm_feature_image_placeholder">Select Event Image</div>'
            );
            $('.mptbm_feature_image_wrapper')
                .attr('data-has-image', '0');
        });

    });


    $('.mptbm_operation_area_fixed_map_type_tab').on('click', function () {
        let type = $(this).data('operation-area-type');
        $('.mptbm_operation_area_fixed_map_type_tab').removeClass('active');
        $(this).addClass('active');

        $('input[name="mptbm_operation_area_fixed_map_type"]').val(type);

        $('.mptbm_operation_area_fixed_map_type_content').hide();

        $('#mptbm_operation_area_fixed_map_' + type).fadeIn(200);
    });
    let activeType = $('.mptbm_operation_area_fixed_map_type_tab.active')
        .data('operation-area-type');
    $('input[name="mptbm_operation_area_fixed_map_type"]').val(activeType);



    /*Area Based Pricing*/
    // Add row
    $('.motbm_area_based_add').on('click', function () {

        let $clone = $('.motbm_area_based_row:first').clone();

        $clone.find('select').val('');
        $clone.find('input').val('');

        $('.motbm_area_based_items').append($clone);

        motbm_area_based_refresh_options();
    });
    // Remove row
    $(document).on('click', '.motbm_area_based_remove', function () {

        if ($('.motbm_area_based_row').length > 1) {
            $(this).closest('.motbm_area_based_row').remove();
            motbm_area_based_refresh_options();
        }

    });
    // Change dropdown
    $(document).on('change', '.motbm_area_based_post', function () {
        motbm_area_based_refresh_options();
    });
    function motbm_area_based_refresh_options() {

        let selectedValues = [];

        $('.motbm_area_based_post').each(function () {

            let value = $(this).val();

            if (value) {
                selectedValues.push(value);
            }
        });
        $('.motbm_area_based_post').each(function () {

            let currentValue = $(this).val();

            $(this).find('option').prop('disabled', false);

            $(this).find('option').each(function () {

                let optionValue = $(this).val();

                if (
                    optionValue &&
                    optionValue !== currentValue &&
                    selectedValues.includes(optionValue)
                ) {
                    $(this).prop('disabled', true);
                }

            });

        });

    }
    // Initial load (for edit screen)
    motbm_area_based_refresh_options();

    $(document).on('click', '.mptbm_locked_switch', function(e) {
        e.preventDefault();

        // avoid duplicate button
        if ($('.mptbm_pro_upgrade_btn').length) return;

        const btn = `
            <div class="mptbm_pro_upgrade_wrap">
                <a href="https://mage-people.com/product/wordpress-taxi-cab-booking-plugin-for-woocommerce/"
                   target="_blank"
                   class="button button-primary mptbm_pro_upgrade_btn">
                    Upgrade to Pro
                </a>
            </div>
        `;

        $(this).closest('.mptbm_taxi_ex_service_toggle_wrapper')
            .append(btn);
    });



    $(document).on('click', '#mptbm_taxi_pricing_field_free',function( e ){
        e.preventDefault();
        if( $(this).hasClass('pro-locked') ){
            $('.mptbm_pro_popup').fadeIn();
        }
    });

    $(document).on('click', '.pro-feature-popup', function(e){
        if($(e.target).is('.pro-feature-popup') || $(e.target).is('.close-pro-popup')){
            $('.pro-feature-popup').fadeOut();
        }
    });
    $(document).on('click','close-pro-popup', function(e){
        e.preventDefault();
        if($(e.target).is('.pro-feature-popup') || $(e.target).is('.close-pro-popup')){
            $('.pro-feature-popup').fadeOut();
        }
    });

    function mptbm_disable_pro_feature_in_free(){
        $('#mptbm_taxi_pricing_field_free')
            .find('input, textarea, button')
            .prop('disabled', true);
    }
    mptbm_disable_pro_feature_in_free();


}(jQuery));