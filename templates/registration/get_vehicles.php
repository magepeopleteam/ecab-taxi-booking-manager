<?php
    $post_id = MP_Global_Function::data_sanitize($_POST['post_id']);
    if ($post_id > 0) {
        include(MPTBM_Function::template_path('registration/vehicle_item.php'));
    } else {
        $price_based = MP_Global_Function::data_sanitize($_POST['price_based']);
        $all_posts = MPTBM_Query::query_transport_list($price_based);
        if ($all_posts->found_posts > 0) {
            $posts = $all_posts->posts;
            foreach ($posts as $post) {
                $post_id = $post->ID;
                include(MPTBM_Function::template_path('registration/vehicle_item.php'));
            }
        }
    }