/**
 * Admin Peak Hour Pricing JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize peak hour pricing functionality
    initPeakHourPricing();

    function initPeakHourPricing() {
        // Handle collapse/expand functionality
        handleCollapseExpand();

        // Handle form validation
        handleFormValidation();

        // Handle dynamic rule addition
        handleDynamicRuleAddition();

        // Handle sortable functionality
        handleSortableFunctionality();
    }

    function handleCollapseExpand() {
        // Handle peak hour enabled/disabled toggle
        $('select[name="mptbm_peak_hour_enabled"]').on('change', function() {
            var isEnabled = $(this).val() === 'enable';

            if (isEnabled) {
                $('[data-collapse="#peak_hour_rules_section"]').addClass('mActive');
                $('[data-collapse="#day_wise_pricing_section"]').addClass('mActive');
            } else {
                $('[data-collapse="#peak_hour_rules_section"]').removeClass('mActive');
                $('[data-collapse="#day_wise_pricing_section"]').removeClass('mActive');
            }
        });

        // Trigger change event on page load
        $('select[name="mptbm_peak_hour_enabled"]').trigger('change');
    }

    function handleFormValidation() {
        // Validate peak hour rules
        $('input[name="mptbm_peak_hour_rule_name[]"]').on('blur', function() {
            var value = $(this).val().trim();
            if (value === '') {
                $(this).addClass('error');
                showValidationMessage($(this), 'Rule name is required');
            } else {
                $(this).removeClass('error');
                hideValidationMessage($(this));
            }
        });

        // Validate date ranges
        $('input[name="mptbm_peak_hour_start_date[]"]').on('change', function() {
            var startDate = $(this).val();
            var endDate = $(this).closest('tr').find('input[name="mptbm_peak_hour_end_date[]"]').val();

            if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
                showValidationMessage($(this), 'Start date must be before end date');
            } else {
                hideValidationMessage($(this));
            }
        });

        $('input[name="mptbm_peak_hour_end_date[]"]').on('change', function() {
            var endDate = $(this).val();
            var startDate = $(this).closest('tr').find('input[name="mptbm_peak_hour_start_date[]"]').val();

            if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
                showValidationMessage($(this), 'End date must be after start date');
            } else {
                hideValidationMessage($(this));
            }
        });

        // Validate time ranges
        $('input[name="mptbm_peak_hour_start_time[]"]').on('change', function() {
            var startTime = $(this).val();
            var endTime = $(this).closest('tr').find('input[name="mptbm_peak_hour_end_time[]"]').val();

            if (startTime && endTime && startTime >= endTime) {
                showValidationMessage($(this), 'Start time must be before end time');
            } else {
                hideValidationMessage($(this));
            }
        });

        $('input[name="mptbm_peak_hour_end_time[]"]').on('change', function() {
            var endTime = $(this).val();
            var startTime = $(this).closest('tr').find('input[name="mptbm_peak_hour_start_time[]"]').val();

            if (startTime && endTime && startTime >= endTime) {
                showValidationMessage($(this), 'End time must be after start time');
            } else {
                hideValidationMessage($(this));
            }
        });

        // Validate price adjustments
        $('input[name="mptbm_peak_hour_price_adjustment[]"]').on('blur', function() {
            var value = parseFloat($(this).val());
            if (isNaN(value)) {
                $(this).addClass('error');
                showValidationMessage($(this), 'Please enter a valid number');
            } else {
                $(this).removeClass('error');
                hideValidationMessage($(this));
            }
        });
    }

    function handleDynamicRuleAddition() {
        // Handle add new rule button (align with main plugin add class)
        $(document).on('click', '.mp_add_new_button, .mp_add_item', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') { e.stopImmediatePropagation(); }
            addNewPeakHourRule();
        });

        // Handle remove rule button using main plugin class
        $(document).on('click keypress', '.mp_peak_hour_rules_insert .mp_item_remove', function(e) {
            var $btn = $(this);
            // Only act if this looks like a Remove button
            var label = ($btn.text() || '').toLowerCase().trim();
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') { e.stopImmediatePropagation(); }
            var $row = $btn.closest('tr');
            if ($row.length) {
                // Some themes/plugins attach jQuery UI tooltips to buttons; destroy safely before removing
                try {
                    if ($.fn.tooltip) {
                        $row.find('[title]').each(function(){
                            try { $(this).tooltip('destroy'); } catch(err) { /* ignore */ }
                        });
                    }
                } catch (errOuter) { /* ignore */ }
                $row.remove();
                return false;
            }
        });

        // Handle clone/duplicate rule (duplicate date range, clear times)
        $(document).on('click', '.mp_peak_hour_rules_insert .mp_clone_rule', function(e) {
            e.preventDefault();
            var $row = $(this).closest('tr');
            var $clone = $row.clone(false, false);
            // Clear time fields only
            $clone.find('input[name="mptbm_peak_hour_start_time[]"]').val('');
            $clone.find('input[name="mptbm_peak_hour_end_time[]"]').val('');
            // Keep other values (name, dates, price, type)
            $row.after($clone);
        });
    }

    function addNewPeakHourRule() {
        // Scope to the Peak Hour Pricing settings container to avoid picking other hidden templates
        var $scope = $('.mptbm_peak_hour_pricing_settings');
        var $hidden = $scope.find('.mp_hidden_content .mp_hidden_item').first();
        if ($hidden.length === 0) {
            return; // safety guard
        }

        // Clone the template row (<tr> inside the hidden tbody)
        var $row = $hidden.children('tr').first().clone(true, true);

        // Determine next rule index
        var currentIndex = $('.mp_peak_hour_rules_insert > tr').length;

        // Replace __IDX__ placeholders with the new index for nested arrays
        $row.find('[name]').each(function () {
            var name = $(this).attr('name');
            if (name && name.indexOf('__IDX__') !== -1) {
                $(this).attr('name', name.replace(/__IDX__/g, String(currentIndex)));
            }
        });

        // Clear all inputs/selects
        $row.find('input[type="text"], input[type="date"], input[type="time"], input[type="number"]').val('');
        $row.find('select').each(function () { this.selectedIndex = 0; });

        // Append to rules table body
        $('.mp_peak_hour_rules_insert').append($row);
    }

    function handleSortableFunctionality() {
        // Make peak hour rules sortable (use the same handle class as main plugin)
        if ($.fn.sortable) {
            $('.mp_peak_hour_rules_insert').sortable({
                handle: '._mpBtn_themeButton_xs.mp_sortable_button, .mp_sortable_button',
                placeholder: 'ui-state-highlight',
                items: '> tr',
                axis: 'y',
                cancel: 'input,select,textarea,button,a,._whiteButton_xs,._mpBtn_themeButton_xs',
                update: function(event, ui) {
                    // Optional: Save order or perform other actions
                }
            });
        }
    }

    // Time slot add/remove handlers
    $(document).on('click', '.mp_add_time_slot', function(e) {
        e.preventDefault();
        var $container = $(this).closest('.time-slots');
        var $tbody = $container.find('.mp_time_slots_insert');

        // Find the rule index by looking at existing time slot inputs in this rule
        var $existingInput = $tbody.find('input[name*="mptbm_peak_hour_start_time"]').first();
        var ruleIndex = 0;

        if ($existingInput.length) {
            var name = $existingInput.attr('name');
            var match = name.match(/mptbm_peak_hour_start_time\[(\d+)\]/);
            if (match) {
                ruleIndex = parseInt(match[1]);
            }
        } else {
            // If no existing inputs, get the rule index from the parent rule row
            var $ruleRow = $(this).closest('tr');
            ruleIndex = $('.mp_peak_hour_rules_insert > tr').index($ruleRow);
        }

        var row = '<tr>' +
            '<td><label><input type="time" name="mptbm_peak_hour_start_time[' + ruleIndex + '][]" class="formControl" value="" /></label></td>' +
            '<td><label><input type="time" name="mptbm_peak_hour_end_time[' + ruleIndex + '][]" class="formControl" value="" /></label></td>' +
            '<td><label><input type="number" step="0.01" name="mptbm_peak_hour_slot_price[' + ruleIndex + '][]" class="formControl" placeholder="EX: 20" /></label></td>' +
            '<td><label><select name="mptbm_peak_hour_slot_type[' + ruleIndex + '][]" class="formControl"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed Amount</option></select></label></td>' +
            '<td><label><select name="mptbm_peak_hour_slot_direction[' + ruleIndex + '][]" class="formControl"><option value="increase">Increase</option><option value="decrease">Decrease</option></select></label></td>' +
            '<td><button type="button" class="_whiteButton_xs mp_time_slot_remove"><span class="fas fa-trash-alt"></span></button></td>' +
            '</tr>';

        // Debug: log the rule index being used
        console.log('Adding time slot for rule index:', ruleIndex);
        $tbody.append(row);
    });

    $(document).on('click', '.mp_time_slot_remove', function(e) {
        e.preventDefault();
        e.stopPropagation();

        console.log('Time slot remove button clicked'); // Debug log

        var $button = $(this);
        var $row = $button.closest('tr');
        var $tbody = $row.closest('tbody');

        console.log('Found row:', $row.length, 'Found tbody:', $tbody.length); // Debug log
        console.log('Current rows in tbody:', $tbody.find('tr').length); // Debug log

        // Simple and direct removal logic
        if ($tbody.find('tr').length > 1) {
            // Remove the row completely if there are multiple rows
            console.log('Removing row completely'); // Debug log
            $row.remove();
        } else {
            // If it's the last slot, just clear the fields but keep the row
            console.log('Clearing fields in last row'); // Debug log
            $row.find('input[type="time"]').val('');
            $row.find('input[type="number"]').val('');
            $row.find('select').prop('selectedIndex', 0);
        }

        return false;
    });

    function showValidationMessage(element, message) {
        hideValidationMessage(element);
        element.after('<div class="validation-message" style="color: #dc3545; font-size: 12px; margin-top: 5px;">' + message + '</div>');
    }

    function hideValidationMessage(element) {
        element.siblings('.validation-message').remove();
    }

    // Handle form submission validation
    $('form').on('submit', function(e) {
        var hasErrors = false;
        var errorMessages = [];

        // Only validate peak hour rules if they have some data filled
        $('input[name="mptbm_peak_hour_rule_name[]"]').each(function() {
            var ruleName = $(this).val().trim();
            var startDate = $(this).closest('tr').find('input[name="mptbm_peak_hour_start_date[]"]').val();
            var endDate = $(this).closest('tr').find('input[name="mptbm_peak_hour_end_date[]"]').val();
            var startTime = $(this).closest('tr').find('input[name="mptbm_peak_hour_start_time[]"]').val();
            var endTime = $(this).closest('tr').find('input[name="mptbm_peak_hour_end_time[]"]').val();
            var priceAdjustment = $(this).closest('tr').find('input[name="mptbm_peak_hour_price_adjustment[]"]').val();

            // Only validate if at least one field has data
            if (ruleName || startDate || endDate || startTime || endTime || priceAdjustment) {
                if (ruleName === '') {
                    hasErrors = true;
                    $(this).addClass('error');
                    errorMessages.push('Rule name is required for peak hour rules');
                }
                if (startDate === '') {
                    hasErrors = true;
                    $(this).closest('tr').find('input[name="mptbm_peak_hour_start_date[]"]').addClass('error');
                    errorMessages.push('Start date is required for peak hour rules');
                }
                if (endDate === '') {
                    hasErrors = true;
                    $(this).closest('tr').find('input[name="mptbm_peak_hour_end_date[]"]').addClass('error');
                    errorMessages.push('End date is required for peak hour rules');
                }
                if (startTime === '') {
                    hasErrors = true;
                    $(this).closest('tr').find('input[name="mptbm_peak_hour_start_time[]"]').addClass('error');
                    errorMessages.push('Start time is required for peak hour rules');
                }
                if (endTime === '') {
                    hasErrors = true;
                    $(this).closest('tr').find('input[name="mptbm_peak_hour_end_time[]"]').addClass('error');
                    errorMessages.push('End time is required for peak hour rules');
                }
                if (priceAdjustment === '') {
                    hasErrors = true;
                    $(this).closest('tr').find('input[name="mptbm_peak_hour_price_adjustment[]"]').addClass('error');
                    errorMessages.push('Price adjustment is required for peak hour rules');
                }
            }
        });

        if (hasErrors) {
            e.preventDefault();
            alert('Please fix the validation errors before saving:\n\n' + errorMessages.join('\n'));
            return false;
        }
    });

    // Auto-save functionality (optional)
    var autoSaveTimeout;
    $('input, select, textarea').on('change', function() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(function() {
            // Optional: Implement auto-save functionality
        }, 2000);
    });

    // Handle priority settings interface
    $('#pricing_priority_select').on('change', function() {
        var selectedValue = $(this).val();
        $('.help-item').hide();
        $('.help-item[data-priority="' + selectedValue + '"]').show();
    });

    // Initialize help text on page load
    var initialValue = $('#pricing_priority_select').val();
    if (initialValue) {
        $('.help-item[data-priority="' + initialValue + '"]').show();
    }

    // Handle advanced conditions toggle
    $('.show-advanced-btn').on('click', function() {
        var $advanced = $('.advanced-conditions');
        var $button = $(this);

        if ($advanced.is(':visible')) {
            $advanced.slideUp();
            $button.html('<span class="fas fa-cog"></span> Show Advanced Conditions');
        } else {
            $advanced.slideDown();
            $button.html('<span class="fas fa-cog"></span> Hide Advanced Conditions');
        }
    });

    // Day-wise pricing toggle functionality
    $('.day-status-toggle').on('change', function() {
        var $card = $(this).closest('.day-pricing-card');
        var $content = $card.find('.day-card-content');
        var $inputs = $content.find('.day-price-input, .day-type-select, .day-direction-select');
        var $preview = $card.find('.pricing-preview');

        if ($(this).is(':checked')) {
            $content.removeClass('disabled').addClass('enabled');
            $inputs.prop('disabled', false);
        } else {
            $content.removeClass('enabled').addClass('disabled');
            $inputs.prop('disabled', true);
            $preview.hide();
        }
    });

    // Update pricing preview when values change
    $('.day-price-input, .day-type-select, .day-direction-select').on('input change', function() {
        var $card = $(this).closest('.day-pricing-card');
        var $content = $card.find('.day-card-content');

        if ($content.hasClass('disabled')) {
            return;
        }

        var price = $card.find('.day-price-input').val();
        var type = $card.find('.day-type-select').val();
        var direction = $card.find('.day-direction-select').val();
        var $preview = $card.find('.pricing-preview');
        var $previewValue = $preview.find('.preview-value');

        if (price && price !== '') {
            var directionText = (direction === 'increase') ? 'increase' : 'decrease';
            var directionIcon = (direction === 'increase') ? '📈' : '📉';
            var previewText = '';

            if (type === 'percentage') {
                previewText = directionIcon + ' ' + price + '% ' + directionText;
            } else {
                previewText = directionIcon + ' $' + price + ' ' + directionText;
            }

            $previewValue.text(previewText);
            $preview.show();
        } else {
            $preview.hide();
        }
    });

    // Initialize preview for existing values
    $('.day-pricing-card').each(function() {
        var $card = $(this);
        var $content = $card.find('.day-card-content');

        if ($content.hasClass('enabled')) {
            var price = $card.find('.day-price-input').val();
            var type = $card.find('.day-type-select').val();
            var direction = $card.find('.day-direction-select').val();
            var $preview = $card.find('.pricing-preview');
            var $previewValue = $preview.find('.preview-value');

            if (price && price !== '') {
                var directionText = (direction === 'increase') ? 'increase' : 'decrease';
                var directionIcon = (direction === 'increase') ? '📈' : '📉';
                var previewText = '';

                if (type === 'percentage') {
                    previewText = directionIcon + ' ' + price + '% ' + directionText;
                } else {
                    previewText = directionIcon + ' $' + price + ' ' + directionText;
                }

                $previewValue.text(previewText);
                $preview.show();
            }
        }
    });

});

