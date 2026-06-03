/**
 * Admin Distance Tier Pricing JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize distance tier pricing functionality
    initDistanceTierPricing();

    function initDistanceTierPricing() {
        // Handle collapse/expand functionality
        handleCollapseExpand();

        // Handle form validation
        handleFormValidation();

        // Handle dynamic tier addition
        handleDynamicTierAddition();

        // Handle sortable functionality
        handleSortableFunctionality();
    }

    function handleCollapseExpand() {
        // Handle distance tier enabled/disabled toggle
        $('select[name="mptbm_distance_tier_enabled"]').on('change', function() {
            var isEnabled = $(this).val() === 'enable';

            if (isEnabled) {
                $('[data-collapse="#distance_tier_section"]').addClass('mActive');
            } else {
                $('[data-collapse="#distance_tier_section"]').removeClass('mActive');
            }
        });

        // Trigger change event on page load
        $('select[name="mptbm_distance_tier_enabled"]').trigger('change');
    }

    function handleFormValidation() {
        // Validate tier names
        $('input[name="mptbm_distance_tier_name[]"]').on('blur', function() {
            var value = $(this).val().trim();
            if (value === '') {
                $(this).addClass('error');
                showValidationMessage($(this), 'Tier name is required');
            } else {
                $(this).removeClass('error');
                hideValidationMessage($(this));
            }
        });

        // Validate distance ranges
        $('input[name="mptbm_distance_tier_min[]"]').on('change', function() {
            var minDistance = parseFloat($(this).val());
            var maxDistance = parseFloat($(this).closest('tr').find('input[name="mptbm_distance_tier_max[]"]').val());

            if (minDistance < 0) {
                showValidationMessage($(this), 'Minimum distance must be 0 or greater');
            } else if (maxDistance && minDistance >= maxDistance) {
                showValidationMessage($(this), 'Minimum distance must be less than maximum distance');
            } else {
                hideValidationMessage($(this));
            }
        });

        $('input[name="mptbm_distance_tier_max[]"]').on('change', function() {
            var maxDistance = parseFloat($(this).val());
            var minDistance = parseFloat($(this).closest('tr').find('input[name="mptbm_distance_tier_min[]"]').val());

            if (maxDistance <= 0) {
                showValidationMessage($(this), 'Maximum distance must be greater than 0');
            } else if (minDistance >= maxDistance) {
                showValidationMessage($(this), 'Maximum distance must be greater than minimum distance');
            } else {
                hideValidationMessage($(this));
            }
        });

        // Validate price adjustments
        $('input[name="mptbm_distance_tier_price[]"]').on('blur', function() {
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

    function handleDynamicTierAddition() {
        // Handle add new tier button within the distance tier settings container
        $(document)
            .off('click.distanceTierAdd')
            .on('click.distanceTierAdd', '.mptbm_distance_tier_pricing_settings .mp_add_new_button, .mptbm_distance_tier_pricing_settings .mp_add_item', function(e) {
                var $button = $(this);
                var $scope = $button.closest('.mptbm_distance_tier_pricing_settings');
                if ($scope.length === 0) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === 'function') {
                    e.stopImmediatePropagation();
                }

                addNewDistanceTier($scope);
            });

        // Handle add range button
        $(document)
            .off('click.addRange')
            .on('click.addRange', '.add-range-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $btn = $(this);
                var tierIndex = $btn.data('tier-index');
                addNewRange(tierIndex);
            });

        // Handle remove tier button
        $(document)
            .off('click.removeTier')
            .on('click.removeTier', '.remove-tier-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $btn = $(this);
                var $tierCard = $btn.closest('.tier-card');
                
                if (confirm('Are you sure you want to remove this tier and all its ranges?')) {
                    $tierCard.remove();
                    updateTierIndices();
                }
            });

        // Handle remove range button
        $(document)
            .off('click.removeRange')
            .on('click.removeRange', '.remove-range-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $btn = $(this);
                var $rangeItem = $btn.closest('.range-item');
                var $rangesContainer = $rangeItem.closest('.ranges-container');
                
                // Don't allow removing the last range
                if ($rangesContainer.find('.range-item').length <= 1) {
                    alert('At least one range is required per tier.');
                    return;
                }
                
                $rangeItem.remove();
                updateRangeIndices($rangesContainer);
            });

        // Legacy support for old table structure
        $(document)
            .off('click.distanceTierRemove keypress.distanceTierRemove')
            .on('click.distanceTierRemove keypress.distanceTierRemove', '.mptbm_distance_tier_pricing_settings .mp_distance_tiers_insert .mp_item_remove', function(e) {
                var $btn = $(this);
                var $scope = $btn.closest('.mptbm_distance_tier_pricing_settings');
                if ($scope.length === 0) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === 'function') {
                    e.stopImmediatePropagation();
                }

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
    }

    function addNewDistanceTier($scopeParam) {
        var $scope = $scopeParam && $scopeParam.length ? $scopeParam : $('.mptbm_distance_tier_pricing_settings');
        var $tierContainer = $scope.find('.tier-container');

        // Determine next tier index
        var currentIndex = $tierContainer.find('.tier-card').length;
        
        // Create new tier card HTML
        var tierCardHtml = createTierCardHtml(currentIndex);
        
        // Append to tier container
        $tierContainer.append(tierCardHtml);
        
        // Initialize the new tier
        initializeTierCard($tierContainer.find('.tier-card').last());
        
        handleFormValidation();
        updatePreviewBadges();
    }

    function addNewRange(tierIndex) {
        var $tierCard = $('.tier-card[data-tier-index="' + tierIndex + '"]');
        var $rangesContainer = $tierCard.find('.ranges-container');
        
        // Determine next range index
        var currentRangeIndex = $rangesContainer.find('.range-item').length;
        
        // Create new range HTML
        var rangeHtml = createRangeHtml(tierIndex, currentRangeIndex);
        
        // Append to ranges container
        $rangesContainer.append(rangeHtml);
        
        // Initialize the new range
        initializeRangeItem($rangesContainer.find('.range-item').last());
        
        handleFormValidation();
        updatePreviewBadges();
    }

    function createTierCardHtml(tierIndex) {
        return '<div class="tier-card" data-tier-index="' + tierIndex + '">' +
            '<div class="tier-header">' +
                '<div class="tier-name-section">' +
                    '<label>' +
                        '<h6>Tier Name<span class="textRequired">&nbsp;*</span></h6>' +
                        '<input type="text" name="mptbm_distance_tier_name[' + tierIndex + ']" class="formControl mp_name_validation" value="" placeholder="EX: City Distance" />' +
                    '</label>' +
                '</div>' +
                '<div class="tier-actions">' +
                    '<button type="button" class="add-range-btn" data-tier-index="' + tierIndex + '">' +
                        '<i class="fas fa-plus"></i> Add Range' +
                    '</button>' +
                    '<button type="button" class="remove-tier-btn">' +
                        '<i class="fas fa-trash"></i> Remove Tier' +
                    '</button>' +
                '</div>' +
            '</div>' +
            '<div class="ranges-container" data-tier-index="' + tierIndex + '">' +
                createRangeHtml(tierIndex, 0) +
            '</div>' +
        '</div>';
    }

    function createRangeHtml(tierIndex, rangeIndex) {
        return '<div class="range-item" data-range-index="' + rangeIndex + '">' +
            '<div class="range-fields">' +
                '<div class="field-group">' +
                    '<label>' +
                        '<span>Min Distance (km)<span class="textRequired">&nbsp;*</span></span>' +
                        '<input type="number" step="0.01" name="mptbm_distance_tier_ranges[' + tierIndex + '][' + rangeIndex + '][min_distance]" class="formControl mp_number_validation" value="" placeholder="EX: 0" />' +
                    '</label>' +
                '</div>' +
                '<div class="field-group">' +
                    '<label>' +
                        '<span>Max Distance (km)<span class="textRequired">&nbsp;*</span></span>' +
                        '<input type="number" step="0.01" name="mptbm_distance_tier_ranges[' + tierIndex + '][' + rangeIndex + '][max_distance]" class="formControl mp_number_validation" value="" placeholder="EX: 10" />' +
                    '</label>' +
                '</div>' +
                '<div class="field-group">' +
                    '<label>' +
                        '<span>Price Adjustment<span class="textRequired">&nbsp;*</span></span>' +
                        '<input type="number" step="0.01" name="mptbm_distance_tier_ranges[' + tierIndex + '][' + rangeIndex + '][price_adjustment]" class="formControl mp_price_validation" value="" placeholder="EX: 15" />' +
                    '</label>' +
                '</div>' +
                '<div class="field-group">' +
                    '<label>' +
                        '<span>Type<span class="textRequired">&nbsp;*</span></span>' +
                        '<select name="mptbm_distance_tier_ranges[' + tierIndex + '][' + rangeIndex + '][adjustment_type]" class="formControl">' +
                            '<option value="per_km">Per Kilometer</option>' +
                        '</select>' +
                    '</label>' +
                '</div>' +
                '<div class="field-group preview-group">' +
                    '<div class="range-preview">' +
                        '<span class="preview-empty">—</span>' +
                    '</div>' +
                '</div>' +
                '<div class="field-group action-group">' +
                    '<button type="button" class="remove-range-btn">' +
                        '<i class="fas fa-trash"></i>' +
                    '</button>' +
                '</div>' +
            '</div>' +
        '</div>';
    }

    function initializeTierCard($tierCard) {
        // Add event handlers for the new tier card
        $tierCard.find('.add-range-btn').on('click', function() {
            var tierIndex = $(this).data('tier-index');
            addNewRange(tierIndex);
        });
        
        $tierCard.find('.remove-tier-btn').on('click', function() {
            if (confirm('Are you sure you want to remove this tier and all its ranges?')) {
                $tierCard.remove();
                updateTierIndices();
            }
        });
        
        // Initialize all ranges in this tier
        $tierCard.find('.range-item').each(function() {
            initializeRangeItem($(this));
        });
    }

    function initializeRangeItem($rangeItem) {
        // Add event handlers for the new range item
        $rangeItem.find('.remove-range-btn').on('click', function() {
            var $rangesContainer = $rangeItem.closest('.ranges-container');
            
            // Don't allow removing the last range
            if ($rangesContainer.find('.range-item').length <= 1) {
                alert('At least one range is required per tier.');
                return;
            }
            
            $rangeItem.remove();
            updateRangeIndices($rangesContainer);
        });
    }

    function updateTierIndices() {
        $('.tier-card').each(function(index) {
            var $tierCard = $(this);
            $tierCard.attr('data-tier-index', index);
            
            // Update tier name input
            $tierCard.find('input[name*="mptbm_distance_tier_name"]').attr('name', 'mptbm_distance_tier_name[' + index + ']');
            
            // Update add range button
            $tierCard.find('.add-range-btn').attr('data-tier-index', index);
            
            // Update ranges container
            $tierCard.find('.ranges-container').attr('data-tier-index', index);
            
            // Update all range inputs
            $tierCard.find('.range-item').each(function(rangeIndex) {
                updateRangeInputs($(this), index, rangeIndex);
            });
        });
    }

    function updateRangeIndices($rangesContainer) {
        var tierIndex = $rangesContainer.attr('data-tier-index');
        
        $rangesContainer.find('.range-item').each(function(rangeIndex) {
            var $rangeItem = $(this);
            $rangeItem.attr('data-range-index', rangeIndex);
            updateRangeInputs($rangeItem, tierIndex, rangeIndex);
        });
    }

    function updateRangeInputs($rangeItem, tierIndex, rangeIndex) {
        $rangeItem.find('input[name*="min_distance"]').attr('name', 'mptbm_distance_tier_ranges[' + tierIndex + '][' + rangeIndex + '][min_distance]');
        $rangeItem.find('input[name*="max_distance"]').attr('name', 'mptbm_distance_tier_ranges[' + tierIndex + '][' + rangeIndex + '][max_distance]');
        $rangeItem.find('input[name*="price_adjustment"]').attr('name', 'mptbm_distance_tier_ranges[' + tierIndex + '][' + rangeIndex + '][price_adjustment]');
        $rangeItem.find('select[name*="adjustment_type"]').attr('name', 'mptbm_distance_tier_ranges[' + tierIndex + '][' + rangeIndex + '][adjustment_type]');
    }

    function handleSortableFunctionality() {
        // Make distance tiers sortable
        if ($.fn.sortable) {
            $('.mp_distance_tiers_insert').sortable({
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

        // Only validate distance tiers if they have some data filled
        $('input[name="mptbm_distance_tier_name[]"]').each(function() {
            var tierName = $(this).val().trim();
            var minDistance = $(this).closest('tr').find('input[name="mptbm_distance_tier_min[]"]').val();
            var maxDistance = $(this).closest('tr').find('input[name="mptbm_distance_tier_max[]"]').val();
            var priceAdjustment = $(this).closest('tr').find('input[name="mptbm_distance_tier_price[]"]').val();

            // Only validate if at least one field has data
            if (tierName || minDistance || maxDistance || priceAdjustment) {
                if (tierName === '') {
                    hasErrors = true;
                    $(this).addClass('error');
                    errorMessages.push('Tier name is required for distance tiers');
                }
                if (minDistance === '') {
                    hasErrors = true;
                    $(this).closest('tr').find('input[name="mptbm_distance_tier_min[]"]').addClass('error');
                    errorMessages.push('Minimum distance is required for distance tiers');
                }
                if (maxDistance === '') {
                    hasErrors = true;
                    $(this).closest('tr').find('input[name="mptbm_distance_tier_max[]"]').addClass('error');
                    errorMessages.push('Maximum distance is required for distance tiers');
                }
                if (priceAdjustment === '') {
                    hasErrors = true;
                    $(this).closest('tr').find('input[name="mptbm_distance_tier_price[]"]').addClass('error');
                    errorMessages.push('Price adjustment is required for distance tiers');
                }
            }
        });

        if (hasErrors) {
            e.preventDefault();
            alert('Please fix the validation errors before saving:\n\n' + errorMessages.join('\n'));
            return false;
        }
    });

    // Update preview badges when values change
    function updatePreviewBadges() {
        // Update new multi-range structure
        $('.range-item').each(function() {
            var $rangeItem = $(this);
            var minDistance = $rangeItem.find('input[name*="min_distance"]').val();
            var maxDistance = $rangeItem.find('input[name*="max_distance"]').val();
            var priceAdjustment = $rangeItem.find('input[name*="price_adjustment"]').val();
            var adjustmentType = $rangeItem.find('select[name*="adjustment_type"]').val();

            var $preview = $rangeItem.find('.range-preview');
            var $badge = $preview.find('.preview-badge');
            var $empty = $preview.find('.preview-empty');

            if (priceAdjustment && minDistance !== '' && maxDistance !== '') {
                var previewText = priceAdjustment + ' per km';

                if ($badge.length) {
                    $badge.text(previewText);
                } else {
                    $empty.remove();
                    $preview.append('<span class="preview-badge">' + previewText + '</span>');
                }
            } else {
                $badge.remove();
                if (!$empty.length) {
                    $preview.append('<span class="preview-empty">—</span>');
                }
            }
        });

        // Legacy support for old table structure
        $('.mp_distance_tiers_insert > tr').each(function() {
            var $row = $(this);
            var tierName = $row.find('input[name*="distance_tier_name"]').val();
            var minDistance = $row.find('input[name*="distance_tier_min"]').val();
            var maxDistance = $row.find('input[name*="distance_tier_max"]').val();
            var priceAdjustment = $row.find('input[name*="distance_tier_price"]').val();
            var adjustmentType = $row.find('select[name*="distance_tier_type"]').val();

            var $preview = $row.find('.tier-preview');
            var $badge = $preview.find('.preview-badge');
            var $empty = $preview.find('.preview-empty');

            if (priceAdjustment && minDistance !== '' && maxDistance !== '') {
                var previewText = priceAdjustment + ' per km';

                if ($badge.length) {
                    $badge.text(previewText);
                } else {
                    $empty.remove();
                    $preview.append('<span class="preview-badge">' + previewText + '</span>');
                }
            } else {
                $badge.remove();
                if (!$empty.length) {
                    $preview.append('<span class="preview-empty">—</span>');
                }
            }
        });
    }

    // Auto-save functionality (optional)
    var autoSaveTimeout;
    $('input, select, textarea').on('change input', function() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(function() {
            updatePreviewBadges();
            // Optional: Implement auto-save functionality
        }, 500);
    });

    // Initial preview update
    updatePreviewBadges();

});
