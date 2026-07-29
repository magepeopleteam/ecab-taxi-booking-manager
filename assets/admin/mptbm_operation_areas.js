(function ($) {
    'use strict';

    const selectors = {
        modal: '[data-operation-areas-modal]',
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
        del: '[data-operation-area-delete]'
    };

    const $modal = $(selectors.modal);
    const $form = $(selectors.form);
    const $name = $('#mptbm-operation-areas-name');
    const $typeSelect = $('#mptbm-operation-type');
    const $message = $(selectors.message);

    let mode = 'add';
    let lastFocusedElement = null;
    // Currently loaded record's shape/location data (Edit mode), or null (Add mode).
    let currentData = null;

    function callGlobal(name) {
        const args = Array.prototype.slice.call(arguments, 1);
        if (typeof window[name] === 'function') {
            window[name].apply(window, args);
        }
    }

    function defaultCenter() {
        return {
            lat: parseFloat(mptbmOperationAreas.defaultLat),
            lng: parseFloat(mptbmOperationAreas.defaultLng)
        };
    }

    // Loads an existing polygon (iniOSMSavedMap/iniSavedtMap) if this slot's
    // coordinates are present in currentData, otherwise starts a fresh empty
    // drawable map (InitOSMMap*/InitMap*) — mirrors exactly what the classic
    // metabox's own PHP branching does, just re-evaluated in JS on demand
    // since this modal is reused across opens instead of rendered once.
    function loadOrInitSlot(slot, coordsFieldId, locVisibleId, locHiddenId, emptyOSMFn, emptyGoogleFn, emptyExtraArgs) {
        const canvasId = 'mptbm-map-canvas-' + slot;
        const coords = currentData && currentData['coordinates_' + slot];

        if (coords && coords.length) {
            $('#' + locVisibleId).val(currentData['location_' + slot] || '');
            $('#' + locHiddenId).val(currentData['location_' + slot] || '');
            $('#' + coordsFieldId).val(coords.join(','));

            if (mptbmOperationAreas.mapType === 'openstreetmap') {
                callGlobal('iniOSMSavedMap', coords, canvasId, coordsFieldId);
            } else {
                callGlobal('iniSavedtMap', coords, canvasId, coordsFieldId);
            }
        } else {
            const center = defaultCenter();
            if (mptbmOperationAreas.mapType === 'openstreetmap') {
                callGlobal.apply(null, [ emptyOSMFn, center ].concat(emptyExtraArgs || []));
            } else {
                callGlobal(emptyGoogleFn, center);
            }
        }
    }

    function initMapsForType(type) {
        if (type === 'geo-fence-operation-area-type') {
            loadOrInitSlot('one', 'mptbm-coordinates-one', 'mptbm-starting-location-one', 'mptbm-starting-location-one-hidden', 'InitOSMMapOne', 'InitMapOne');
            loadOrInitSlot('two', 'mptbm-coordinates-two', 'mptbm-starting-location-two', 'mptbm-starting-location-two-hidden', 'InitOSMMapTwo', 'InitMapTwo');
        } else {
            loadOrInitSlot('three', 'mptbm-coordinates-three', 'mptbm-starting-location-three', 'mptbm-starting-location-three-hidden', 'InitOSMMapFixed', 'InitMapFixed', [ '' ]);
        }
    }

    // The shared mp_script.js collapse system already toggles section visibility
    // on this same change event — only run our (heavier) map init once the modal
    // is actually visible, so programmatic .trigger('change') calls made while
    // opening the modal (used solely to reset the collapse UI) don't try to size
    // a Leaflet/Google map inside a still-hidden container.
    $(document).on('change', '#mptbm-operation-type', function () {
        if ($modal.hasClass('is-visible')) {
            initMapsForType($(this).val());
        }
    });

    function resetShapeFields() {
        $('#mptbm-starting-location-one, #mptbm-starting-location-two, #mptbm-starting-location-three').val('');
        $('#mptbm-starting-location-one-hidden, #mptbm-starting-location-two-hidden, #mptbm-starting-location-three-hidden').val('');
        $('#mptbm-coordinates-one, #mptbm-coordinates-two, #mptbm-coordinates-three').val('');
    }

    function openModal(newMode, data) {
        lastFocusedElement = document.activeElement;
        mode = newMode;
        currentData = data || null;

        $form[0].reset();
        resetShapeFields();
        $message.removeClass('is-error is-success').text('');
        $(selectors.postId).val('');

        if (mode === 'edit' && data) {
            $(selectors.title).text(mptbmOperationAreas.editTitle);
            $(selectors.subtitle).text(mptbmOperationAreas.editSubtitle);
            $(selectors.submitLabel).text(mptbmOperationAreas.saveLabel);
            $(selectors.postId).val(data.postId);
            $name.val(data.title);
            $typeSelect.val(data.operationType).trigger('change');
            $('#mptbm-geo-fence-increase-price-by').val(data.priceBy).trigger('change');
            $('#mptbm-geo-fence-fixed-price-amount').val(data.fixedAmount);
            $('#mptbm-geo-fence-percentage-amount').val(data.percentageAmount);
            $('#mptbm-geo-fence-direction').val(data.direction);
        } else {
            $(selectors.title).text(mptbmOperationAreas.addTitle);
            $(selectors.subtitle).text(mptbmOperationAreas.addSubtitle);
            $(selectors.submitLabel).text(mptbmOperationAreas.addLabel);
            $typeSelect.val('fixed-operation-area-type').trigger('change');
            $('#mptbm-geo-fence-increase-price-by').val('geo-fence-fixed-price').trigger('change');
        }

        $modal.addClass('is-visible').attr('aria-hidden', 'false');
        $('body').addClass('mptbm-modal-open');

        window.setTimeout(function () {
            $name.trigger('focus');
            initMapsForType($typeSelect.val());
        }, 150);
    }

    function closeModal() {
        if ($form.hasClass('is-submitting')) {
            return;
        }
        $modal.removeClass('is-visible').attr('aria-hidden', 'true');
        $('body').removeClass('mptbm-modal-open');
        if (lastFocusedElement) {
            $(lastFocusedElement).trigger('focus');
        }
    }

    function setSubmitting(isSubmitting) {
        const $submit = $(selectors.submit);
        $form.toggleClass('is-submitting', isSubmitting);
        $submit.prop('disabled', isSubmitting);
        if (isSubmitting) {
            $(selectors.submitLabel).text(mptbmOperationAreas.savingLabel);
        } else {
            $(selectors.submitLabel).text(mode === 'edit' ? mptbmOperationAreas.saveLabel : mptbmOperationAreas.addLabel);
        }
        $submit.find('i').toggleClass('fa-plus', !isSubmitting).toggleClass('fa-spinner fa-spin', isSubmitting);
    }

    $(document)
        .on('click', selectors.open, function () {
            openModal('add', null);
        })
        .on('click', selectors.edit, function (event) {
            const $card = $(event.currentTarget).closest('.mptbm-operation-areas-card');
            const postId = $card.data('post-id');

            $.ajax({
                url: mptbmOperationAreas.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: mptbmOperationAreas.getDataAction,
                    nonce: mptbmOperationAreas.nonce,
                    post_id: postId
                }
            }).done(function (response) {
                if (!response || !response.success) {
                    window.alert((response && response.data && response.data.message) || mptbmOperationAreas.dataLoadError);
                    return;
                }
                openModal('edit', response.data);
            }).fail(function () {
                window.alert(mptbmOperationAreas.dataLoadError);
            });
        })
        .on('click', selectors.del, function (event) {
            const $card = $(event.currentTarget).closest('.mptbm-operation-areas-card');
            const postId = $card.data('post-id');

            if (!window.confirm(mptbmOperationAreas.confirmDelete)) {
                return;
            }

            $.ajax({
                url: mptbmOperationAreas.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: mptbmOperationAreas.deleteAction,
                    nonce: mptbmOperationAreas.nonce,
                    post_id: postId
                }
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
            });
        })
        .on('click', selectors.close, closeModal)
        .on('keydown', function (event) {
            if (event.key === 'Escape' && $modal.hasClass('is-visible')) {
                closeModal();
            }
        });

    $form.on('submit', function (event) {
        event.preventDefault();

        const name = $.trim($name.val());
        if (!name) {
            $message.addClass('is-error').removeClass('is-success').text(mptbmOperationAreas.requiredName);
            $name.trigger('focus');
            return;
        }

        setSubmitting(true);
        $message.removeClass('is-error is-success').text('');

        const isEdit = mode === 'edit';
        const payload = $form.serializeArray();
        payload.push({ name: 'action', value: isEdit ? mptbmOperationAreas.updateAction : mptbmOperationAreas.addAction });
        payload.push({ name: 'nonce', value: mptbmOperationAreas.nonce });
        if (isEdit) {
            payload.push({ name: 'post_id', value: $(selectors.postId).val() });
        }

        $.ajax({
            url: mptbmOperationAreas.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: payload
        }).done(function (response) {
            if (!response || !response.success) {
                const errorMessage = response && response.data && response.data.message
                    ? response.data.message
                    : mptbmOperationAreas.genericError;
                $message.addClass('is-error').removeClass('is-success').text(errorMessage);
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

            $message.addClass('is-success').removeClass('is-error').text(response.data.message);

            window.setTimeout(function () {
                setSubmitting(false);
                closeModal();
            }, 500);
        }).fail(function (xhr) {
            const response = xhr.responseJSON;
            const errorMessage = response && response.data && response.data.message
                ? response.data.message
                : mptbmOperationAreas.genericError;
            $message.addClass('is-error').removeClass('is-success').text(errorMessage);
        }).always(function () {
            if (!$message.hasClass('is-success')) {
                setSubmitting(false);
            }
        });
    });
})(jQuery);
