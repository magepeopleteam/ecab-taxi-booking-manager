jQuery(document).ready(function($){

    $('.mptbm_transportation_lists_card').on('mouseenter', function(){

        $(this).addClass('mptbm_transportation_lists_card_active');

    }).on('mouseleave', function(){

        $(this).removeClass('mptbm_transportation_lists_card_active');

    });

    $('.mptbm_transportation_lists_search_box input').on('keyup', function(){

        var value = $(this).val().toLowerCase();

        $('.mptbm_transportation_lists_card').filter(function(){

            $(this).toggle(
                $(this).text().toLowerCase().indexOf(value) > -1
            );

        });

    });


    $(document).on('click', '.mptbm-trash-confirm', function (e) {
        if (!confirm('Move this item to trash?')) {
            e.preventDefault();
        }
    });

    $(document).on('click', '.mptbm-delete-confirm', function (e) {
        e.preventDefault();

        let url = $(this).attr('href');

        if (confirm('Delete this item permanently?')) {
            window.location.href = url;
        }
    });

});