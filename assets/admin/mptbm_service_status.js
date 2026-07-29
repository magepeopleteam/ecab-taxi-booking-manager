(function ($) {
    'use strict';

    const selectors = {
        modal: '[data-service-status-modal]',
        open: '[data-service-status-open]',
        close: '[data-service-status-close]',
        form: '[data-service-status-form]',
        submit: '[data-service-status-submit]',
        message: '[data-service-status-message]',
        grid: '[data-service-status-grid]',
        empty: '[data-service-status-empty]',
        count: '[data-service-status-count]'
    };

    const $modal = $(selectors.modal);
    const $form = $(selectors.form);
    const $name = $('#mptbm-service-status-name');
    const $message = $(selectors.message);
    let lastFocusedElement = null;

    function openModal() {
        lastFocusedElement = document.activeElement;
        $form[0].reset();
        $message.removeClass('is-error is-success').text('');
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
        $submit.find('span').text(isSubmitting ? mptbmServiceStatus.addingLabel : mptbmServiceStatus.addLabel);
        $submit.find('i').toggleClass('fa-plus', !isSubmitting).toggleClass('fa-spinner fa-spin', isSubmitting);
    }

    $(document)
        .on('click', selectors.open, openModal)
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
            $message.addClass('is-error').removeClass('is-success').text('Enter a service status name.');
            $name.trigger('focus');
            return;
        }

        setSubmitting(true);
        $message.removeClass('is-error is-success').text('');

        $.ajax({
            url: mptbmServiceStatus.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: mptbmServiceStatus.action,
                nonce: mptbmServiceStatus.nonce,
                name: name,
                description: $('#mptbm-service-status-description').val()
            }
        }).done(function (response) {
            if (!response || !response.success) {
                const errorMessage = response && response.data && response.data.message
                    ? response.data.message
                    : mptbmServiceStatus.genericError;
                $message.addClass('is-error').removeClass('is-success').text(errorMessage);
                return;
            }

            $(selectors.grid).prepend(response.data.card);
            $(selectors.empty).addClass('is-hidden');
            const $count = $(selectors.count);
            $count.text((parseInt($count.text(), 10) || 0) + 1);
            $message.addClass('is-success').removeClass('is-error').text(response.data.message);

            window.setTimeout(function () {
                setSubmitting(false);
                closeModal();
            }, 500);
        }).fail(function (xhr) {
            const response = xhr.responseJSON;
            const errorMessage = response && response.data && response.data.message
                ? response.data.message
                : mptbmServiceStatus.genericError;
            $message.addClass('is-error').removeClass('is-success').text(errorMessage);
        }).always(function () {
            if (!$message.hasClass('is-success')) {
                setSubmitting(false);
            }
        });
    });
})(jQuery);
