(function ($) {
    $(document).ready(function() {

        $(document).on('click','#mptbm_taxi_category_open_popup', function() {
            $('#mptbm_taxi_category_modal').css('display', 'flex');
            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "mptbm_add_edit_taxi_category",
                    nonce: $('#mptbm_taxi_nonce').val(),
                    category_id: '',
                },
                success: function(res) {
                    if(res.success){
                        $("#mptbm_taxi_category_modal").html( res.data.add_edit_html );

                        $('#mptbm_taxi_category_new_name').focus();
                    }
                }
            });


        });

        $(document).on('click','#mptbm_taxi_category_close_popup, #mptbm_taxi_category_modal', function(e) {
            if (e.target === this) {
                $('#mptbm_taxi_category_modal').css('display', 'none');
                $('#mptbm_taxi_category_modal').empty();
            }
        });

        $(document).on('change', '#mptbm_taxi_category_dropdown', function () {
            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "mptbm_taxi_save_post_category",
                    post_id: $('#mptbm_taxi_post_id').val(),
                    nonce: $('#mptbm_taxi_nonce').val(),
                    category_id: $(this).val()
                },
                success: function(res) {
                    if(res.success){
                        alert("Saved!");
                    }
                }
            });
        });


        $(document).on('click', '.mptbm_taxi_all_category_label', function () {
            $('#mptbm_taxi_category_modal').css('display', 'flex');
            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "mptbm_get_all_categories",
                    nonce: $('#mptbm_taxi_nonce').val(),
                },
                success: function(res) {
                    if(res.success){
                        $("#mptbm_taxi_category_modal").empty();
                        $("#mptbm_taxi_category_modal").html( res.data.all_cat_html );
                    }
                }
            });
        });

        function mptbm_remove_category_popup(){
            $('#mptbm_taxi_category_modal').css('display', 'none');
            $('#mptbm_taxi_category_modal').empty();
        }

        $(document).on('click','.mptbm_all_categories_delete_btn', function( e ) {
            e.preventDefault();
            let category_id = $(this).attr('data-id');

            let $this = $(this);
            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "mptbm_remove_category_from_cat_list",
                    nonce: $('#mptbm_taxi_nonce').val(),
                    category_id: category_id,
                },
                success: function(res) {
                    if(res.success){
                        $('#mptbm_taxi_category_dropdown').html( res.data.category_html_data );
                        $this.closest('.mptbm_all_categories_card').fadeOut(300);
                    }
                }
            });
        });

        $(document).on('click','.mptbm_all_categories_edit_btn', function() {
            mptbm_remove_category_popup();
            $('#mptbm_taxi_category_modal').css('display', 'flex');
            let category_id = $(this).attr('data-id');
            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "mptbm_add_edit_taxi_category",
                    nonce: $('#mptbm_taxi_nonce').val(),
                    category_id: category_id,
                },
                success: function(res) {
                    if(res.success){
                        $("#mptbm_taxi_category_modal").html( res.data.add_edit_html );

                        $('#mptbm_taxi_category_new_name').focus();
                    }
                }
            });
        });

        $(document).on('click', '#mptbm_all_categories_close',function () {
            mptbm_remove_category_popup();
        });

        $(document).on('click', '#mptbm_taxi_category_save_btn',function () {

            let name = $('#mptbm_taxi_category_new_name').val();
            let type = $('#mptbm_taxi_category_type').val();
            let desc = $('#mptbm_taxi_category_desc').val();

            let id = $('#mptbm_taxi_category_id').val();

            let nonce = $('#mptbm_taxi_nonce').val();

            if (!name) {
                alert('Category name required');
                return;
            }
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mptbm_taxi_save_category',
                    name: name,
                    type: type,
                    nonce: nonce,
                    desc: desc,
                    cat_id: id,
                },
                beforeSend: function () {
                    $('#mptbm_taxi_category_save_btn').text('Saving...');
                },
                success: function (res) {
                    $('#mptbm_taxi_category_save_btn').text('Save Category');
                    if (res.success) {
                        alert(res.data.message);
                        $('#mptbm_taxi_category_modal').empty();
                        $('#mptbm_taxi_category_modal').fadeOut();
                        $('#mptbm_taxi_category_dropdown').html( res.data.category_html_data );
                    } else {
                        alert(res.data.message);
                    }

                }
            });

        });

        // Re-render the tag badges from the authoritative server list.
        function mptbm_render_tags(tags) {
            var $list = $('#mptbm_taxi_category_tags_list');
            if (!$list.length) { return; }
            $list.empty();
            (tags || []).forEach(function (t) {
                var $badge = $('<span>', { 'class': 'mptbm_taxi_category_badge' }).attr('data-tag', t).text(t);
                $badge.append($('<i class="mptbm_taxi_category_remove_tag">').html('&times;'));
                $list.append($badge);
            });
        }

        // Delegated + keydown so it fires even when the sidebar is re-rendered and
        // before any implicit form submit happens on Enter.
        $(document).on('keydown', '#mptbm_taxi_category_tag_input', function (e) {
            if (e.key === 'Enter' || e.which === 13 || e.keyCode === 13) {
                e.preventDefault();
                e.stopPropagation();
                var $input = $(this);
                var tagValue = $.trim($input.val());
                if (tagValue === '') { return; }
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mptbm_taxi_add_tag',
                        nonce: $('#mptbm_taxi_nonce').val(),
                        post_id: $('#mptbm_taxi_post_id').val(),
                        tag: tagValue
                    },
                    success: function (res) {
                        if (res && res.success) {
                            $input.val('');
                            mptbm_render_tags(res.data.tags);
                        } else {
                            alert((res && res.data && res.data.message) || 'Error saving tag');
                        }
                    }
                });
            }
        });

        $(document).on('click', '#mptbm_taxi_category_tags_list .mptbm_taxi_category_remove_tag', function () {
            var tagValue = $(this).closest('.mptbm_taxi_category_badge').attr('data-tag');
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mptbm_taxi_remove_tag',
                    post_id: $('#mptbm_taxi_post_id').val(),
                    nonce: $('#mptbm_taxi_nonce').val(),
                    tag: tagValue
                },
                success: function (res) {
                    if (res && res.success) {
                        mptbm_render_tags(res.data.tags);
                    } else {
                        alert((res && res.data && res.data.message) || 'Failed to remove tag');
                    }
                }
            });
        });

    });
}(jQuery));