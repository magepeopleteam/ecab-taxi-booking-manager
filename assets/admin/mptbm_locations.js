(function ($) {
    'use strict';

    const selectors = {
        modal: '[data-locations-modal]',
        open: '[data-locations-open]',
        close: '[data-locations-close]',
        form: '[data-locations-form]',
        submit: '[data-locations-submit]',
        submitLabel: '[data-locations-submit-label]',
        message: '[data-locations-message]',
        grid: '[data-locations-grid]',
        empty: '[data-locations-empty]',
        count: '[data-locations-count]',
        title: '[data-locations-title]',
        subtitle: '[data-locations-subtitle]',
        termId: '[data-locations-term-id]',
        edit: '[data-location-edit]',
        del: '[data-location-delete]'
    };

    const $modal = $(selectors.modal);
    const $form = $(selectors.form);
    const $name = $('#mptbm-locations-name');
    const $description = $('#mptbm-locations-description');
    const $geoSearch = $('#mptbm-locations-geo-search');
    const $geo = $('#mptbm-locations-geo');
    const $message = $(selectors.message);

    let lastFocusedElement = null;
    let mode = 'add';
    let map = null;
    let marker = null;
    let searchBound = false;

    function parseGeo(value) {
        if (!value) {
            return null;
        }
        const parts = String(value).split(',');
        if (parts.length !== 2) {
            return null;
        }
        const lat = parseFloat(parts[0]);
        const lng = parseFloat(parts[1]);
        if (isNaN(lat) || isNaN(lng)) {
            return null;
        }
        return { lat: lat, lng: lng };
    }

    function setGeoValue(lat, lng) {
        $geo.val(lat.toFixed(6) + ',' + lng.toFixed(6));
    }

    function initLocationMap(lat, lng) {
        const canvas = document.getElementById('mptbm-locations-map');
        if (!canvas) {
            return; // Map is disabled site-wide (mptbm_map_api_settings.display_map === 'disable').
        }

        if (mptbmLocations.mapType === 'openstreetmap') {
            if (typeof L === 'undefined') {
                return;
            }

            if (canvas._leaflet_id) {
                canvas._leaflet_id = null;
                canvas.innerHTML = '';
            }

            map = L.map(canvas).setView([ lat, lng ], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            marker = L.marker([ lat, lng ], { draggable: true }).addTo(map);
            marker.on('dragend', function () {
                const position = marker.getLatLng();
                setGeoValue(position.lat, position.lng);
            });

            if (!searchBound && typeof setupOSMLocationSearch === 'function') {
                setupOSMLocationSearch('mptbm-locations-geo-search', map, function (newLat, newLng, displayName) {
                    map.setView([ newLat, newLng ], 15);
                    marker.setLatLng([ newLat, newLng ]);
                    setGeoValue(newLat, newLng);
                    $geoSearch.val(displayName);
                });
                searchBound = true;
            }

            window.setTimeout(function () {
                if (map) {
                    map.invalidateSize();
                }
            }, 300);
        } else {
            if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
                return;
            }

            const center = { lat: lat, lng: lng };
            map = new google.maps.Map(canvas, { zoom: 13, center: center });
            marker = new google.maps.Marker({ position: center, map: map, draggable: true });
            marker.addListener('dragend', function () {
                const position = marker.getPosition();
                setGeoValue(position.lat(), position.lng());
            });

            if (!searchBound && google.maps.places) {
                const autocomplete = new google.maps.places.Autocomplete($geoSearch[0]);
                autocomplete.addListener('place_changed', function () {
                    const place = autocomplete.getPlace();
                    if (place && place.geometry) {
                        const location = place.geometry.location;
                        map.setCenter(location);
                        map.setZoom(15);
                        marker.setPosition(location);
                        setGeoValue(location.lat(), location.lng());
                    }
                });
                searchBound = true;
            }
        }
    }

    function openModal(newMode, data) {
        lastFocusedElement = document.activeElement;
        mode = newMode;

        $form[0].reset();
        $message.removeClass('is-error is-success').text('');
        $(selectors.termId).val('');

        let coords;
        if (mode === 'edit' && data) {
            $(selectors.title).text(mptbmLocations.editTitle);
            $(selectors.subtitle).text(mptbmLocations.editSubtitle);
            $(selectors.submitLabel).text(mptbmLocations.saveLabel);
            $(selectors.termId).val(data.termId);
            $name.val(data.name);
            $description.val(data.description);
            $geoSearch.val(data.geoName);
            $geo.val(data.geo);
            coords = parseGeo(data.geo);
        } else {
            $(selectors.title).text(mptbmLocations.addTitle);
            $(selectors.subtitle).text(mptbmLocations.addSubtitle);
            $(selectors.submitLabel).text(mptbmLocations.addLabel);
        }

        if (!coords) {
            coords = { lat: parseFloat(mptbmLocations.defaultLat), lng: parseFloat(mptbmLocations.defaultLng) };
        }

        $modal.addClass('is-visible').attr('aria-hidden', 'false');
        $('body').addClass('mptbm-modal-open');

        window.setTimeout(function () {
            $name.trigger('focus');
            initLocationMap(coords.lat, coords.lng);
        }, 120);
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
            $(selectors.submitLabel).text(mode === 'edit' ? mptbmLocations.savingLabel : mptbmLocations.addingLabel);
        } else {
            $(selectors.submitLabel).text(mode === 'edit' ? mptbmLocations.saveLabel : mptbmLocations.addLabel);
        }
        $submit.find('i').toggleClass('fa-plus', !isSubmitting).toggleClass('fa-spinner fa-spin', isSubmitting);
    }

    $(document)
        .on('click', selectors.open, function () {
            openModal('add', null);
        })
        .on('click', selectors.edit, function (event) {
            const $card = $(event.currentTarget).closest('.mptbm-locations-card');
            openModal('edit', {
                termId: $card.data('term-id'),
                name: $card.data('name'),
                description: $card.data('description'),
                geo: $card.data('geo'),
                geoName: $card.data('geo-name')
            });
        })
        .on('click', selectors.del, function (event) {
            const $card = $(event.currentTarget).closest('.mptbm-locations-card');
            const termId = $card.data('term-id');

            if (!window.confirm(mptbmLocations.confirmDelete)) {
                return;
            }

            $.ajax({
                url: mptbmLocations.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: mptbmLocations.deleteAction,
                    nonce: mptbmLocations.nonce,
                    term_id: termId
                }
            }).done(function (response) {
                if (!response || !response.success) {
                    window.alert((response && response.data && response.data.message) || mptbmLocations.genericError);
                    return;
                }

                $card.remove();
                const $count = $(selectors.count);
                const next = Math.max(0, (parseInt($count.text(), 10) || 0) - 1);
                $count.text(next);
                if (next === 0) {
                    $(selectors.empty).removeClass('is-hidden');
                }
            }).fail(function (xhr) {
                const response = xhr.responseJSON;
                window.alert((response && response.data && response.data.message) || mptbmLocations.genericError);
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
            $message.addClass('is-error').removeClass('is-success').text(mptbmLocations.requiredName);
            $name.trigger('focus');
            return;
        }

        setSubmitting(true);
        $message.removeClass('is-error is-success').text('');

        const isEdit = mode === 'edit';
        const payload = {
            action: isEdit ? mptbmLocations.updateAction : mptbmLocations.addAction,
            nonce: mptbmLocations.nonce,
            name: name,
            description: $description.val(),
            mptbm_geo_location: $geo.val(),
            mptbm_geo_location_name: $geoSearch.val()
        };
        if (isEdit) {
            payload.term_id = $(selectors.termId).val();
        }

        $.ajax({
            url: mptbmLocations.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: payload
        }).done(function (response) {
            if (!response || !response.success) {
                const errorMessage = response && response.data && response.data.message
                    ? response.data.message
                    : mptbmLocations.genericError;
                $message.addClass('is-error').removeClass('is-success').text(errorMessage);
                return;
            }

            if (isEdit) {
                $('.mptbm-locations-card[data-term-id="' + response.data.termId + '"]').replaceWith(response.data.card);
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
                : mptbmLocations.genericError;
            $message.addClass('is-error').removeClass('is-success').text(errorMessage);
        }).always(function () {
            if (!$message.hasClass('is-success')) {
                setSubmitting(false);
            }
        });
    });
})(jQuery);
