(function ($) {
    'use strict';

    const selectors = {
        modal: '[data-extra-services-modal]',
        open: '[data-extra-services-open]',
        close: '[data-extra-services-close]',
        form: '[data-extra-services-form]',
        submit: '[data-extra-services-submit]',
        submitLabel: '[data-extra-services-submit-label]',
        message: '[data-extra-services-message]',
        grid: '[data-extra-services-grid]',
        empty: '[data-extra-services-empty]',
        count: '[data-extra-services-count]',
        title: '[data-extra-services-title]',
        subtitle: '[data-extra-services-subtitle]',
        postId: '[data-extra-services-post-id]',
        rowsBody: '[data-extra-services-rows-body]',
        edit: '[data-extra-service-edit]',
        del: '[data-extra-service-delete]'
    };

    const $modal = $(selectors.modal);
    const $form = $(selectors.form);
    const $name = $('#mptbm-extra-services-name');
    const $message = $(selectors.message);

    let mode = 'add';
    let lastFocusedElement = null;

    function openModal(newMode, data) {
        lastFocusedElement = document.activeElement;
        mode = newMode;

        $form[0].reset();
        $(selectors.rowsBody).empty();
        $message.removeClass('is-error is-success').text('');
        $(selectors.postId).val('');

        if (mode === 'edit' && data) {
            $(selectors.title).text(mptbmExtraServices.editTitle);
            $(selectors.subtitle).text(mptbmExtraServices.editSubtitle);
            $(selectors.submitLabel).text(mptbmExtraServices.saveLabel);
            $(selectors.postId).val(data.postId);
            $name.val(data.title);
            $(selectors.rowsBody).html(data.rowsHtml);
        } else {
            $(selectors.title).text(mptbmExtraServices.addTitle);
            $(selectors.subtitle).text(mptbmExtraServices.addSubtitle);
            $(selectors.submitLabel).text(mptbmExtraServices.addLabel);
        }

        $modal.addClass('is-visible').attr('aria-hidden', 'false');
        $('body').addClass('mptbm-modal-open');
        window.setTimeout(function () {
            $name.trigger('focus');
        }, 80);
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
            $(selectors.submitLabel).text(mptbmExtraServices.savingLabel);
        } else {
            $(selectors.submitLabel).text(mode === 'edit' ? mptbmExtraServices.saveLabel : mptbmExtraServices.addLabel);
        }
        $submit.find('i').toggleClass('fa-plus', !isSubmitting).toggleClass('fa-spinner fa-spin', isSubmitting);
    }

    $(document)
        .on('click', selectors.open, function () {
            openModal('add', null);
        })
        .on('click', selectors.edit, function (event) {
            const $card = $(event.currentTarget).closest('.mptbm-extra-services-card');
            const postId = $card.data('post-id');

            $.ajax({
                url: mptbmExtraServices.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: mptbmExtraServices.getRowsAction,
                    nonce: mptbmExtraServices.nonce,
                    post_id: postId
                }
            }).done(function (response) {
                if (!response || !response.success) {
                    window.alert((response && response.data && response.data.message) || mptbmExtraServices.rowsLoadError);
                    return;
                }
                openModal('edit', {
                    postId: postId,
                    title: response.data.title,
                    rowsHtml: response.data.rowsHtml
                });
            }).fail(function () {
                window.alert(mptbmExtraServices.rowsLoadError);
            });
        })
        .on('click', selectors.del, function (event) {
            const $card = $(event.currentTarget).closest('.mptbm-extra-services-card');
            const postId = $card.data('post-id');

            if (!window.confirm(mptbmExtraServices.confirmDelete)) {
                return;
            }

            $.ajax({
                url: mptbmExtraServices.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: mptbmExtraServices.deleteAction,
                    nonce: mptbmExtraServices.nonce,
                    post_id: postId
                }
            }).done(function (response) {
                if (!response || !response.success) {
                    window.alert((response && response.data && response.data.message) || mptbmExtraServices.genericError);
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
                window.alert(mptbmExtraServices.genericError);
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
            $message.addClass('is-error').removeClass('is-success').text(mptbmExtraServices.requiredName);
            $name.trigger('focus');
            return;
        }

        setSubmitting(true);
        $message.removeClass('is-error is-success').text('');

        const isEdit = mode === 'edit';

        // Serialize the whole form: title + nonce + every service_name[]/service_icon[]/
        // service_price[]/etc row field the shared repeater already renders, then just
        // add our own action/nonce/post_id on top.
        const payload = $form.serializeArray();
        payload.push({ name: 'action', value: isEdit ? mptbmExtraServices.updateAction : mptbmExtraServices.addAction });
        payload.push({ name: 'nonce', value: mptbmExtraServices.nonce });
        if (isEdit) {
            payload.push({ name: 'post_id', value: $(selectors.postId).val() });
        }

        $.ajax({
            url: mptbmExtraServices.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: payload
        }).done(function (response) {
            if (!response || !response.success) {
                const errorMessage = response && response.data && response.data.message
                    ? response.data.message
                    : mptbmExtraServices.genericError;
                $message.addClass('is-error').removeClass('is-success').text(errorMessage);
                return;
            }

            if (isEdit) {
                $('.mptbm-extra-services-card[data-post-id="' + response.data.postId + '"]').replaceWith(response.data.card);
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
                : mptbmExtraServices.genericError;
            $message.addClass('is-error').removeClass('is-success').text(errorMessage);
        }).always(function () {
            if (!$message.hasClass('is-success')) {
                setSubmitting(false);
            }
        });
    });
})(jQuery);
