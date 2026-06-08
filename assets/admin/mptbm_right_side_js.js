(function ($) {
    $(document).ready(function() {

        $('#mptbm_taxi_category_open_popup').on('click', function() {
            $('#mptbm_taxi_category_modal').css('display', 'flex');
            $('#mptbm_taxi_category_new_name').val('').focus();
        });

        $('#mptbm_taxi_category_close_popup, #mptbm_taxi_category_modal').on('click', function(e) {
            if (e.target === this) {
                $('#mptbm_taxi_category_modal').css('display', 'none');
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

        $('#mptbm_taxi_category_save_btn').on('click', function () {

            let name = $('#mptbm_taxi_category_new_name').val();
            let type = $('#mptbm_taxi_category_type').val();
            let desc = $('#mptbm_taxi_category_desc').val();

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
                    desc: desc
                },
                beforeSend: function () {
                    $('#mptbm_taxi_category_save_btn').text('Saving...');
                },
                success: function (res) {
                    $('#mptbm_taxi_category_save_btn').text('Save Category');
                    if (res.success) {
                        alert(res.data.message);
                        $('#mptbm_taxi_category_modal').fadeOut();
                        $('#mptbm_taxi_category_flex_group').html( res.data.category_html_data );
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