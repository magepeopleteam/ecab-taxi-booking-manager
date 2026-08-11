(function ($) {
    'use strict';

    const selectors = {
        modal: '[data-operation-areas-modal]',
        dialog: '.mptbm-operation-areas-dialog',
        open: '[data-operation-areas-open]',
        close: '[data-operation-areas-close]',
        form: '[data-operation-areas-form]',
        submit: '[data-operation-areas-submit]',
        submitLabel: '[data-operation-areas-submit-label]',
        message: '[data-operation-areas-message]',
        grid: '[data-operation-areas-grid]',
        empty: '[data-operation-areas-empty]',
        count: '[data-operation-areas-count]',
        title: '[data-operation-areas-title]',
        subtitle: '[data-operation-areas-subtitle]',
        postId: '[data-operation-areas-post-id]',
        edit: '[data-operation-area-edit]',
        del: '[data-operation-area-delete]',
        typeChoice: '[data-operation-type-choice]',
        builder: '[data-area-slot]',
        mapAction: '[data-map-action]',
        readiness: '[data-operation-areas-readiness]',
        reviewText: '[data-operation-areas-review-text]',
        completion: '[data-operation-areas-completion]',
        saveState: '[data-operation-areas-save-state]'
    };

    const slotConfig = {
        one: {
            coordinates: '#mptbm-coordinates-one',
            visibleLocation: '#mptbm-starting-location-one',
            hiddenLocation: '#mptbm-starting-location-one-hidden',
            emptyOSM: 'InitOSMMapOne',
            emptyGoogle: 'InitMapOne'
        },
        two: {
            coordinates: '#mptbm-coordinates-two',
            visibleLocation: '#mptbm-starting-location-two',
            hiddenLocation: '#mptbm-starting-location-two-hidden',
            emptyOSM: 'InitOSMMapTwo',
            emptyGoogle: 'InitMapTwo'
        },
        three: {
            coordinates: '#mptbm-coordinates-three',
            visibleLocation: '#mptbm-starting-location-three',
            hiddenLocation: '#mptbm-starting-location-three-hidden',
            emptyOSM: 'InitOSMMapFixed',
            emptyGoogle: 'InitMapFixed',
            emptyExtraArgs: [ '' ]
        }
    };

    const $modal = $(selectors.modal);
    const $dialog = $modal.find(selectors.dialog);
    const $form = $(selectors.form);
    const $name = $('#mptbm-operation-areas-name');
    const $typeSelect = $('#mptbm-operation-type');
    const $priceType = $('#mptbm-geo-fence-increase-price-by');
    const $message = $(selectors.message);

    let mode = 'add';
    let lastFocusedElement = null;
    let currentData = null;
    let initialSignature = '';
    let isDirty = false;
    let statusTimer = null;
    let mapInitTimer = null;

    function callGlobal(name) {
        const args = Array.prototype.slice.call(arguments, 1);
        if (typeof window[name] === 'function') {
            return window[name].apply(window, args);
        }
        return null;
    }

    function defaultCenter() {
        return {
            lat: parseFloat(mptbmOperationAreas.defaultLat),
            lng: parseFloat(mptbmOperationAreas.defaultLng)
        };
    }

    function parseCoordinates(value) {
        const values = Array.isArray(value) ? value : String(value || '').split(',');
        return values
            .map(function (coordinate) { return parseFloat(coordinate); })
            .filter(function (coordinate) { return Number.isFinite(coordinate); });
    }

    function coordinatesForSlot(slot) {
        return parseCoordinates($(slotConfig[slot].coordinates).val());
    }

    function coordinatesAreValid(coordinates) {
        if (coordinates.length < 6 || coordinates.length % 2 !== 0) {
            return false;
        }
        for (let index = 0; index < coordinates.length; index += 2) {
            if (coordinates[index] < -90 || coordinates[index] > 90 || coordinates[index + 1] < -180 || coordinates[index + 1] > 180) {
                return false;
            }
        }
        return true;
    }

    function slotSuffix(slot) {
        return slot.charAt(0).toUpperCase() + slot.slice(1);
    }

    function mapObjectsForSlot(slot) {
        return {
            map: window['osmMap' + slotSuffix(slot)],
            layer: window['osmDrawLayer' + slotSuffix(slot)]
        };
    }

    function updateCurrentCoordinates(slot, coordinates) {
        if (currentData) {
            currentData['coordinates_' + slot] = coordinates.slice();
        }
    }

    function loadOrInitSlot(slot) {
        const config = slotConfig[slot];
        const canvasId = 'mptbm-map-canvas-' + slot;
        let coordinates = coordinatesForSlot(slot);

        if (coordinates.length < 6 && currentData) {
            coordinates = parseCoordinates(currentData['coordinates_' + slot]);
        }

        if (coordinates.length >= 6) {
            $(config.coordinates).val(coordinates.join(','));
            if (!$(config.visibleLocation).val() && currentData) {
                $(config.visibleLocation).val(currentData['location_' + slot] || '');
            }
            if (!$(config.hiddenLocation).val() && currentData) {
                $(config.hiddenLocation).val(currentData['location_' + slot] || '');
            }

            if (mptbmOperationAreas.mapType === 'openstreetmap') {
                callGlobal('iniOSMSavedMap', coordinates, canvasId, config.coordinates.substring(1));
            } else {
                callGlobal('iniSavedtMap', coordinates, canvasId, config.coordinates.substring(1));
            }
            return;
        }

        const center = defaultCenter();
        if (mptbmOperationAreas.mapType === 'openstreetmap') {
            callGlobal.apply(null, [ config.emptyOSM, center ].concat(config.emptyExtraArgs || []));
        } else {
            callGlobal(config.emptyGoogle, center);
        }
    }

    function activeSlots() {
        return $typeSelect.val() === 'geo-fence-operation-area-type' ? [ 'one', 'two' ] : [ 'three' ];
    }

    function initMapsForType(type) {
        if (mptbmOperationAreas.mapType === 'disable') {
            showMessage(mptbmOperationAreas.mapUnavailable, 'error');
            return;
        }

        const slots = type === 'geo-fence-operation-area-type' ? [ 'one', 'two' ] : [ 'three' ];
        slots.forEach(loadOrInitSlot);
        window.setTimeout(updateWorkflow, 350);
    }

    function setType(type, initializeMaps) {
        $typeSelect.val(type);
        $(selectors.typeChoice).each(function () {
            const selected = $(this).data('operation-type-choice') === type;
            $(this)
                .toggleClass('is-selected', selected)
                .attr('aria-checked', selected ? 'true' : 'false')
                .attr('tabindex', selected ? '0' : '-1');
        });
        $('[data-collapse="#fixed-operation-area-type"]').toggleClass('mActive', type === 'fixed-operation-area-type').toggle(type === 'fixed-operation-area-type');
        $('[data-collapse="#geo-fence-operation-area-type"]').toggleClass('mActive', type === 'geo-fence-operation-area-type').toggle(type === 'geo-fence-operation-area-type');

        if (initializeMaps && $modal.hasClass('is-visible')) {
            window.clearTimeout(mapInitTimer);
            mapInitTimer = window.setTimeout(function () { initMapsForType(type); }, 80);
        }
        updateWorkflow();
    }

    function setPriceType(type) {
        $priceType.val(type);
        const fixed = type !== 'geo-fence-percentage-price';
        $('[data-collapse="#geo-fence-fixed-price"]').toggleClass('mActive', fixed).toggle(fixed);
        $('[data-collapse="#geo-fence-percentage-price"]').toggleClass('mActive', !fixed).toggle(!fixed);
        updateWorkflow();
    }

    function resetShapeFields() {
        Object.keys(slotConfig).forEach(function (slot) {
            const config = slotConfig[slot];
            $(config.visibleLocation + ', ' + config.hiddenLocation + ', ' + config.coordinates).val('');
        });
    }

    function hydrateStoredFields(data) {
        Object.keys(slotConfig).forEach(function (slot) {
            const config = slotConfig[slot];
            $(config.visibleLocation).val(data['location_' + slot] || '');
            $(config.hiddenLocation).val(data['location_' + slot] || '');
            $(config.coordinates).val(parseCoordinates(data['coordinates_' + slot]).join(','));
        });
    }

    function signature() {
        const parts = [
            $.trim($name.val()),
            $typeSelect.val(),
            $priceType.val(),
            $('#mptbm-geo-fence-fixed-price-amount').val(),
            $('#mptbm-geo-fence-percentage-amount').val(),
            $('#mptbm-geo-fence-direction').val()
        ];
        Object.keys(slotConfig).forEach(function (slot) {
            const config = slotConfig[slot];
            parts.push($(config.visibleLocation).val(), $(config.hiddenLocation).val(), $(config.coordinates).val());
        });
        return parts.join('|');
    }

    function markDirty() {
        isDirty = initialSignature !== '' && signature() !== initialSignature;
    }

    function openModal(newMode, data) {
        lastFocusedElement = document.activeElement;
        mode = newMode;
        currentData = data || null;
        isDirty = false;
        initialSignature = '';

        $form[0].reset();
        resetShapeFields();
        clearMessage();
        $(selectors.postId).val('');

        if (mode === 'edit' && data) {
            $(selectors.title).text(mptbmOperationAreas.editTitle);
            $(selectors.subtitle).text(mptbmOperationAreas.editSubtitle);
            $(selectors.submitLabel).text(mptbmOperationAreas.saveLabel);
            $(selectors.postId).val(data.postId);
            $name.val(data.title);
            hydrateStoredFields(data);
            setType(data.operationType, false);
            setPriceType(data.priceBy);
            $('#mptbm-geo-fence-fixed-price-amount').val(data.fixedAmount);
            $('#mptbm-geo-fence-percentage-amount').val(data.percentageAmount);
            $('#mptbm-geo-fence-direction').val(data.direction);
        } else {
            $(selectors.title).text(mptbmOperationAreas.addTitle);
            $(selectors.subtitle).text(mptbmOperationAreas.addSubtitle);
            $(selectors.submitLabel).text(mptbmOperationAreas.addLabel);
            setType('fixed-operation-area-type', false);
            setPriceType('geo-fence-fixed-price');
        }

        $modal.addClass('is-visible').attr('aria-hidden', 'false');
        $('body').addClass('mptbm-modal-open');
        $dialog.scrollTop(0);
        initialSignature = signature();

        window.setTimeout(function () {
            $name.trigger('focus');
            initMapsForType($typeSelect.val());
            updateWorkflow();
            startStatusTimer();
        }, 150);
    }

    function closeModal(forceClose) {
        if ($form.hasClass('is-submitting')) {
            return;
        }
        markDirty();
        if (!forceClose && isDirty && !window.confirm(mptbmOperationAreas.confirmDiscard)) {
            return;
        }
        stopStatusTimer();
        window.clearTimeout(mapInitTimer);
        $('.osm-location-autocomplete').hide();
        $modal.removeClass('is-visible').attr('aria-hidden', 'true');
        $('body').removeClass('mptbm-modal-open');
        clearMessage();
        if (lastFocusedElement) {
            $(lastFocusedElement).trigger('focus');
        }
    }

    function showMessage(message, type) {
        $message.removeClass('is-error is-success').addClass(type === 'success' ? 'is-success' : 'is-error').text(message);
    }

    function clearMessage() {
        $message.removeClass('is-error is-success').text('');
        $form.find('.has-error').removeClass('has-error');
        $form.find('[aria-invalid="true"]').attr('aria-invalid', 'false');
    }

    function setSubmitting(isSubmitting) {
        const $submit = $(selectors.submit);
        $form.toggleClass('is-submitting', isSubmitting);
        if (isSubmitting) {
            $submit.prop('disabled', true);
            $(selectors.submitLabel).text(mptbmOperationAreas.savingLabel);
        } else {
            $(selectors.submitLabel).text(mode === 'edit' ? mptbmOperationAreas.saveLabel : mptbmOperationAreas.addLabel);
            updateWorkflow();
        }
        $submit.find('i').toggleClass('fa-plus', !isSubmitting).toggleClass('fa-spinner fa-spin', isSubmitting);
    }

    function polygonAreaSquareKm(coordinates) {
        if (coordinates.length < 6) {
            return 0;
        }
        const radius = 6378137;
        let total = 0;
        const points = [];
        for (let index = 0; index < coordinates.length; index += 2) {
            points.push({ lat: coordinates[index] * Math.PI / 180, lng: coordinates[index + 1] * Math.PI / 180 });
        }
        points.forEach(function (point, index) {
            const next = points[(index + 1) % points.length];
            total += (next.lng - point.lng) * (2 + Math.sin(point.lat) + Math.sin(next.lat));
        });
        return Math.abs(total * radius * radius / 2) / 1000000;
    }

    function slotState(slot) {
        const config = slotConfig[slot];
        const coordinates = coordinatesForSlot(slot);
        return {
            coordinates: coordinates,
            locationReady: $.trim($(config.hiddenLocation).val()) !== '',
            boundaryReady: coordinatesAreValid(coordinates),
            complete: $.trim($(config.hiddenLocation).val()) !== '' && coordinatesAreValid(coordinates)
        };
    }

    function updateBuilder(slot) {
        const state = slotState(slot);
        updateCurrentCoordinates(slot, state.coordinates);
        const $builder = $('[data-area-slot="' + slot + '"]');
        const points = Math.floor(state.coordinates.length / 2);
        const area = polygonAreaSquareKm(state.coordinates);
        const metric = state.boundaryReady
            ? points + ' ' + mptbmOperationAreas.verticesLabel + ' · ' + mptbmOperationAreas.areaLabel + ' ' + (area < 1 ? area.toFixed(2) : area.toFixed(1)) + ' km²'
            : mptbmOperationAreas.boundaryEmpty;

        $builder.toggleClass('is-location-ready', state.locationReady)
            .toggleClass('is-boundary-ready', state.boundaryReady)
            .toggleClass('is-complete', state.complete);
        $builder.find('[data-boundary-status] span').text(state.boundaryReady ? mptbmOperationAreas.boundaryReady : mptbmOperationAreas.boundaryEmpty);
        $builder.find('[data-boundary-metric]').html('<i class="fas fa-vector-square" aria-hidden="true"></i>' + $('<span>').text(metric).html());
        $builder.find('[data-location-status]').html('<i class="fas fa-map-marker-alt" aria-hidden="true"></i>' + $('<span>').text(state.locationReady ? $(slotConfig[slot].hiddenLocation).val() : mptbmOperationAreas.locationNeeded).html());
        $builder.find('[data-map-action="edit"], [data-map-action="fit"], [data-map-action="clear"]').prop('disabled', !state.boundaryReady);
        return state;
    }

    function pricingReady() {
        if ($typeSelect.val() !== 'geo-fence-operation-area-type') {
            return true;
        }
        const percentage = $priceType.val() === 'geo-fence-percentage-price';
        const rawValue = percentage ? $('#mptbm-geo-fence-percentage-amount').val() : $('#mptbm-geo-fence-fixed-price-amount').val();
        const value = parseFloat(rawValue);
        return rawValue !== '' && Number.isFinite(value) && value >= (percentage ? 1 : 0) && (!percentage || value <= 100);
    }

    function updateWorkflow() {
        if (!$modal.hasClass('is-visible')) {
            return;
        }

        const detailsReady = $.trim($name.val()) !== '';
        const states = {};
        Object.keys(slotConfig).forEach(function (slot) { states[slot] = updateBuilder(slot); });
        const boundariesReady = activeSlots().every(function (slot) { return states[slot].complete; });
        const settingsReady = pricingReady();
        const reviewReady = boundariesReady && settingsReady;
        const ready = detailsReady && boundariesReady && settingsReady && mptbmOperationAreas.mapType !== 'disable';
        const completedSteps = [ detailsReady, boundariesReady, reviewReady ].filter(Boolean).length;
        const completion = Math.round((completedSteps / 3) * 100);

        $('[data-progress-step="details"]').toggleClass('is-complete', detailsReady).toggleClass('is-active', !detailsReady);
        $('[data-progress-step="boundaries"]').toggleClass('is-complete', boundariesReady).toggleClass('is-active', detailsReady && !boundariesReady);
        $('[data-progress-step="settings"]').toggleClass('is-complete', reviewReady).toggleClass('is-active', detailsReady && boundariesReady && !reviewReady);

        let reviewText = mptbmOperationAreas.allComplete;
        if (!detailsReady) {
            reviewText = mptbmOperationAreas.completeDetails;
        } else if (!boundariesReady) {
            reviewText = $typeSelect.val() === 'geo-fence-operation-area-type' ? mptbmOperationAreas.completeBoundaries : mptbmOperationAreas.completeBoundary;
        } else if (!settingsReady) {
            reviewText = mptbmOperationAreas.completePricing;
        }

        $(selectors.readiness).text(ready ? mptbmOperationAreas.readyToSave : mptbmOperationAreas.notReady);
        $(selectors.reviewText).text(reviewText);
        $(selectors.completion).text(completion + '%');
        $(selectors.saveState).toggleClass('is-ready', ready).find('span').text(ready ? mptbmOperationAreas.readyToSave : mptbmOperationAreas.notReady);
        $('[data-operation-areas-review]').toggleClass('is-ready', ready);
        $(selectors.submit).prop('disabled', !ready || $form.hasClass('is-submitting'));
        markDirty();
    }

    function startStatusTimer() {
        stopStatusTimer();
        statusTimer = window.setInterval(updateWorkflow, 350);
    }

    function stopStatusTimer() {
        if (statusTimer) {
            window.clearInterval(statusTimer);
            statusTimer = null;
        }
    }

    function activateLeafletAction(slot, action) {
        if (typeof window.L === 'undefined') {
            showMessage(mptbmOperationAreas.mapUnavailable, 'error');
            return;
        }
        const objects = mapObjectsForSlot(slot);
        if (!objects.map || !objects.layer) {
            showMessage(mptbmOperationAreas.mapUnavailable, 'error');
            return;
        }

        if (action === 'draw') {
            if (!slotState(slot).locationReady) {
                showMessage(mptbmOperationAreas.locationNeeded, 'error');
                $(slotConfig[slot].visibleLocation).trigger('focus');
                return;
            }
            clearMessage();
            new L.Draw.Polygon(objects.map, {
                allowIntersection: false,
                showArea: true,
                shapeOptions: { color: '#635bff', fillColor: '#635bff', fillOpacity: 0.24, weight: 3 }
            }).enable();
        } else if (action === 'edit' && objects.layer.getLayers().length) {
            const nativeEditButton = document.querySelector('#mptbm-map-canvas-' + slot + ' .leaflet-draw-edit-edit');
            if (nativeEditButton) {
                nativeEditButton.click();
            }
        } else if (action === 'fit' && objects.layer.getLayers().length) {
            objects.map.fitBounds(objects.layer.getBounds(), { padding: [ 28, 28 ] });
        } else if (action === 'clear' && objects.layer.getLayers().length && window.confirm(mptbmOperationAreas.confirmClear)) {
            objects.layer.clearLayers();
            $(slotConfig[slot].coordinates).val('').trigger('change');
            updateCurrentCoordinates(slot, []);
        }
        window.setTimeout(updateWorkflow, 80);
    }

    function validateBeforeSubmit() {
        clearMessage();
        if (!$.trim($name.val())) {
            $name.attr('aria-invalid', 'true').closest('.mptbm-operation-areas-field').addClass('has-error');
            showMessage(mptbmOperationAreas.requiredName, 'error');
            $name.trigger('focus');
            return false;
        }

        const incompleteSlot = activeSlots().find(function (slot) { return !slotState(slot).complete; });
        if (incompleteSlot) {
            const state = slotState(incompleteSlot);
            const $target = state.locationReady ? $('[data-area-slot="' + incompleteSlot + '"] [data-map-action="draw"]') : $(slotConfig[incompleteSlot].visibleLocation);
            $('[data-area-slot="' + incompleteSlot + '"]').addClass('has-error');
            showMessage(state.locationReady ? mptbmOperationAreas.boundaryNeeded : mptbmOperationAreas.locationNeeded, 'error');
            $target.trigger('focus');
            return false;
        }

        if (!pricingReady()) {
            const $amount = $priceType.val() === 'geo-fence-percentage-price' ? $('#mptbm-geo-fence-percentage-amount') : $('#mptbm-geo-fence-fixed-price-amount');
            $amount.attr('aria-invalid', 'true').closest('.mptbm-operation-areas-field').addClass('has-error');
            showMessage(mptbmOperationAreas.completePricing, 'error');
            $amount.trigger('focus');
            return false;
        }
        return true;
    }

    function trapFocus(event) {
        if (event.key !== 'Tab' || !$modal.hasClass('is-visible')) {
            return;
        }
        const $focusable = $dialog.find('button:not(:disabled), input:not(:disabled):not([type="hidden"]), select:not(:disabled), [tabindex]:not([tabindex="-1"])').filter(':visible');
        if (!$focusable.length) {
            return;
        }
        const first = $focusable[0];
        const last = $focusable[$focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    $(document)
        .on('click', selectors.open, function () { openModal('add', null); })
        .on('click', selectors.typeChoice, function () {
            const type = $(this).data('operation-type-choice');
            if (type !== $typeSelect.val()) {
                $typeSelect.val(type).trigger('change');
            }
        })
        .on('keydown', selectors.typeChoice, function (event) {
            if (![ 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown' ].includes(event.key)) {
                return;
            }
            event.preventDefault();
            const $choices = $(selectors.typeChoice);
            const currentIndex = $choices.index(this);
            const direction = event.key === 'ArrowLeft' || event.key === 'ArrowUp' ? -1 : 1;
            const nextIndex = (currentIndex + direction + $choices.length) % $choices.length;
            $choices.eq(nextIndex).trigger('focus').trigger('click');
        })
        .on('change', '#mptbm-operation-type', function () { setType($(this).val(), true); })
        .on('change', '#mptbm-geo-fence-increase-price-by', function () { setPriceType($(this).val()); })
        .on('input change', '[data-operation-areas-form] input, [data-operation-areas-form] select', updateWorkflow)
        .on('input', '#mptbm-starting-location-one, #mptbm-starting-location-two, #mptbm-starting-location-three', function () {
            const hiddenId = '#' + this.id + '-hidden';
            if ($.trim($(this).val()) !== $.trim($(hiddenId).val())) {
                $(hiddenId).val('');
            }
            updateWorkflow();
        })
        .on('click', selectors.mapAction, function () {
            const $builder = $(this).closest(selectors.builder);
            activateLeafletAction($builder.data('area-slot'), $(this).data('map-action'));
        })
        .on('click', selectors.edit, function (event) {
            const $button = $(event.currentTarget);
            const $card = $button.closest('.mptbm-operation-areas-card');
            $button.prop('disabled', true).find('i').removeClass('fa-pen').addClass('fa-spinner fa-spin');

            $.ajax({
                url: mptbmOperationAreas.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: mptbmOperationAreas.getDataAction,
                    nonce: mptbmOperationAreas.nonce,
                    post_id: $card.data('post-id')
                }
            }).done(function (response) {
                if (!response || !response.success) {
                    window.alert((response && response.data && response.data.message) || mptbmOperationAreas.dataLoadError);
                    return;
                }
                openModal('edit', response.data);
            }).fail(function () {
                window.alert(mptbmOperationAreas.dataLoadError);
            }).always(function () {
                $button.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-pen');
            });
        })
        .on('click', selectors.del, function (event) {
            const $button = $(event.currentTarget);
            const $card = $button.closest('.mptbm-operation-areas-card');
            const postId = $card.data('post-id');
            if (!window.confirm(mptbmOperationAreas.confirmDelete)) {
                return;
            }

            $button.prop('disabled', true).find('i').removeClass('fa-trash').addClass('fa-spinner fa-spin');

            $.ajax({
                url: mptbmOperationAreas.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: { action: mptbmOperationAreas.deleteAction, nonce: mptbmOperationAreas.nonce, post_id: postId }
            }).done(function (response) {
                if (!response || !response.success) {
                    window.alert((response && response.data && response.data.message) || mptbmOperationAreas.genericError);
                    return;
                }
                $card.remove();
                const $count = $(selectors.count);
                const next = Math.max(0, (parseInt($count.text(), 10) || 0) - 1);
                $count.text(next);
                if (next === 0) {
                    $(selectors.empty).removeClass('is-hidden');
                }
            }).fail(function () {
                window.alert(mptbmOperationAreas.genericError);
            }).always(function () {
                if ($.contains(document, $button[0])) {
                    $button.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-trash');
                }
            });
        })
        .on('click', selectors.close, function () { closeModal(false); })
        .on('keydown', function (event) {
            if (event.key === 'Escape' && $modal.hasClass('is-visible')) {
                closeModal(false);
            } else {
                trapFocus(event);
            }
        });

    $form.on('submit', function (event) {
        event.preventDefault();
        if (!validateBeforeSubmit()) {
            return;
        }

        setSubmitting(true);
        clearMessage();
        const isEdit = mode === 'edit';
        const payload = $form.serializeArray();
        payload.push({ name: 'action', value: isEdit ? mptbmOperationAreas.updateAction : mptbmOperationAreas.addAction });
        payload.push({ name: 'nonce', value: mptbmOperationAreas.nonce });

        $.ajax({
            url: mptbmOperationAreas.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: payload
        }).done(function (response) {
            if (!response || !response.success) {
                showMessage(response && response.data && response.data.message ? response.data.message : mptbmOperationAreas.genericError, 'error');
                return;
            }

            if (isEdit) {
                $('.mptbm-operation-areas-card[data-post-id="' + response.data.postId + '"]').replaceWith(response.data.card);
            } else {
                $(selectors.grid).prepend(response.data.card);
                $(selectors.empty).addClass('is-hidden');
                const $count = $(selectors.count);
                $count.text((parseInt($count.text(), 10) || 0) + 1);
            }

            isDirty = false;
            initialSignature = signature();
            showMessage(response.data.message, 'success');
            window.setTimeout(function () {
                setSubmitting(false);
                closeModal(true);
            }, 500);
        }).fail(function (xhr) {
            const response = xhr.responseJSON;
            showMessage(response && response.data && response.data.message ? response.data.message : mptbmOperationAreas.genericError, 'error');
        }).always(function () {
            if (!$message.hasClass('is-success')) {
                setSubmitting(false);
            }
        });
    });
})(jQuery);
