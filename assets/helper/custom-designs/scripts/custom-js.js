
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

        parent.find('[data-main-price]').html(mp_price_format(main_price));
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



