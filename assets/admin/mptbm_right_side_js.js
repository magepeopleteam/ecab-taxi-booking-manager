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



        $('#mptbm_taxi_category_dropdown').on('change', function () {

            let nonce = $('#mptbm_taxi_nonce').val();
            let post_id = $('#mptbm_taxi_post_id').val();
            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "mptbm_taxi_save_post_category",
                    post_id: post_id,
                    nonce: nonce,
                    category_id: $("#mptbm_taxi_category_dropdown").val()
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
                    } else {
                        alert(res.data.message);
                    }

                }
            });

        });

        $('#mptbm_taxi_category_tag_input').on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                let tagValue = $.trim($(this).val());
                if (tagValue !== "") {
                    var tagHTML = `
                        <span class="mptbm_taxi_category_badge"
                              data-tag="${tagValue}">
                            ${tagValue}
                            <i class="mptbm_taxi_category_remove_tag">&times;</i>
                        </span>
                    `;
                    $('#mptbm_taxi_category_tags_list').append(tagHTML);
                    $(this).val('');
                    $.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: {
                            action: "mptbm_taxi_add_tag",
                            nonce: $('#mptbm_taxi_nonce').val(),
                            post_id: $('#mptbm_taxi_post_id').val(),
                            tag: tagValue
                        },
                        success: function (res) {
                            if (!res.success) {
                                alert(res.data.message || "Error saving tag");
                            }
                        }
                    });
                }
            }

        });

        $('#mptbm_taxi_category_tags_list').on('click', '.mptbm_taxi_category_remove_tag', function () {

            let tagElement = $(this).closest('.mptbm_taxi_category_badge');
            let tagValue = tagElement.data('tag');
            let post_id = $('#mptbm_taxi_post_id').val();
            let nonce = $('#mptbm_taxi_nonce').val();
            tagElement.remove();
            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "mptbm_taxi_remove_tag",
                    post_id: post_id,
                    nonce: nonce,
                    tag: tagValue
                },
                success: function (res) {
                    if (!res.success) {
                        alert(res.data.message || "Failed to remove tag");
                    }
                }
            });
        });

    });
}(jQuery));