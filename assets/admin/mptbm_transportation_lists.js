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

    /*Search Fields*/
    function filterCards() {

        let value = $('#mptbm_search_input').val().toLowerCase();

        $('.mptbm_transportation_lists_card').each(function () {

            let title = $(this).data('transport-title').toLowerCase();

            if (title.indexOf(value) > -1) {

                // smooth show
                $(this).stop(true, true).slideDown(250);

            } else {

                // smooth hide
                $(this).stop(true, true).slideUp(250);
            }
        });
    }

    // live typing search
    $('#mptbm_search_input').on('keyup', function () {
        filterCards();
    });

    // button search
    $('#mptbm_search_btn').on('click', function () {
        filterCards();
    });

});