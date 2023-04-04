
function mptbm_price_format(price) {
    let price_text = '';
    price = parseFloat(price).toFixed(2);
    if (mp_currency_position === 'right') {
        price_text = price + mp_currency_symbol;
    } else if (mp_currency_position === 'right_space') {
        price_text = price + '&nbsp;' + mp_currency_symbol;
    } else if (mp_currency_position === 'left') {
        price_text = mp_currency_symbol + price;
    } else {
        price_text = mp_currency_symbol + '&nbsp;' + price;
    }
    return price_text;
}

(function ($) {
    $(document).on('change', '.mptbm_booking_item [data-extra-service-price]', function () {
        $(this).closest('label').toggleClass('active_select');
        let parent = $(this).closest('.mptbm_booking_item');
        let price=parseFloat(parent.find('[data-main-price]').attr('data-main-price'));
        parent.find('[data-extra-service-price]').each(function () {
            if ($(this).is(':checked')) {
                let value=$(this).val();
                $(this).siblings('[name="mptbm_extra_service[]"]').val(value);
                let ex_price = parseFloat($(this).data('extra-service-price'));
                price=price+ex_price;
            }else{
                $(this).siblings('[name="mptbm_extra_service[]"]').val('');
            }
        });
        parent.find('[data-main-price]').html(mptbm_price_format(price));

    });

    $(document).on("click", ".mptbm_book_now[type='button']", function () {
        let parent = $(this).closest('form');
        let start_place = parent.find('[name="mptbm_start_place"]');
        let end_place = parent.find('[name="mptbm_end_place"]');
        if (start_place.val() !=='' && end_place.val() !=='') {
            $.when(mptbm_set_cookie_distance_duration(start_place.value, end_place.value)).done(function () {
                parent.find('.mptbm_add_to_cart').trigger('click');
            });
        }S
    });

}(jQuery));

function calculate_price(element)
{
    jQuery(function($) {

        let parent = element.closest('.mptbm_booking_item');
        let main_price = parseFloat(parent.find('[data-main-price]').attr('data-main-price'));

        parent.find('[data-extra-service-price]').each(function () {

            if ($(this).is(':checked')) {
                let value=$(this).val();
                $(this).siblings('[name="mptbm_extra_service[]"]').val(value);
                let ex_price = parseFloat($(this).data('extra-service-price'));
                let quantity_box = $(this).closest('td').siblings().find('[name="mptbm_extra_service_quantity[]"]');
                main_price = main_price + ex_price * quantity_box.val();
            }else{
                $(this).siblings('[name="mptbm_extra_service[]"]').val('');
            }
        });

        parent.find('[data-main-price]').html(mptbm_price_format(main_price));
    });

}

(function ($) {
    $(document).ready(function() {

        $(document).on('click hover focus focus-visible', '.car-select', function (event) {
            event.preventDefault();
            $(this).toggleClass('car-selected-button');
        });

        $(document).on('click', '.car-select', function (event) {
            event.preventDefault();
            $(this).closest('#product-details').toggleClass('product-details-active');
        });

        $(document).on('click', '.selectCheckbox', function (event) {
            event.preventDefault();
            let target_quantity_box = $(this).data('extra-service-id');
            let target_element = $('#quantity_'+target_quantity_box);
            target_element.toggleClass("hide-quantity-box");
            $(this).toggleClass("selected-background");
            $(this).toggleClass("selected-extra-service");

            let price_checkbox = $(this).find("input[type='checkbox']").first();
            price_checkbox.prop('checked', !price_checkbox.is(':checked'));

            if(price_checkbox.is(':checked'))
            {
                $(this).find("input[name='mptbm_extra_service[]']").first().val(price_checkbox.val());
            }
            else
            {
                $(this).find("input[name='mptbm_extra_service[]']").first().val('');
            }

            let selected_element = $(this);

            calculate_price(selected_element);

        });

        $(document).on('focusout','.car-select',function (event) {

            event.preventDefault();

            if($(this).closest('#product-details').hasClass('product-details-active'))
            {
                $(this).addClass('uncollapsed-button');
            }
            else
            {
                $(this).removeClass('uncollapsed-button');
            }

        });

        $(document).on('change', 'input[name="mptbm_extra_service_quantity[]"]', function (event) {
            event.preventDefault();
            let selectBox = $(this).closest('td').siblings().find(".selectCheckbox");
            let price_checkbox = selectBox.find("input[type='checkbox']").first();

            if(price_checkbox.is(':checked'))
            {
                calculate_price(selectBox);
            }

        });

        $(document).on('click', '.field-wrapper .field-placeholder', function (event) {
            event.preventDefault();
            $(this).closest(".field-wrapper").find("input").focus();
        });

        $(document).on('keyup', '.field-wrapper input', function (event) {
            event.preventDefault();
            var value = $.trim($(this).val());
            if (value) {
                $(this).closest(".field-wrapper").addClass("hasValue");
            } else {
                $(this).closest(".field-wrapper").removeClass("hasValue");
            }
        });

    });
}(jQuery));

(function ($) {
    /************* Quantity Box *************/
    $(document).ready(function() {

        $(document).on('click', '.quantity-box > .minus', function () {
            var $input = $(this).parent().find('input');
            var count = parseInt($input.val()) - 1;
            count = count < 1 ? 1 : count;
            $input.val(count);
            $input.change();
            return false;
        });

        $(document).on('click', '.quantity-box > .plus', function () {
            var $input = $(this).parent().find('input');
            $input.val(parseInt($input.val()) + 1);
            $input.change();
            return false;
        });

    });
}(jQuery));