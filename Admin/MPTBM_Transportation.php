<?php

class MPTBM_Transportation
{
    public function __construct(){

        add_action( 'admin_menu', array( $this, 'mptbm_transportation_lists_menu' ) );

        add_action( 'admin_menu', array( $this, 'reorder_mptbm_submenu' ), 999 );

        add_action('admin_post_mptbm_trash_transport',  array( $this, 'mptbm_trash_transport_callback' ) );
    }

    public function reorder_mptbm_submenu() {

        global $submenu;

        $parent = 'edit.php?post_type=mptbm_rent';

        if ( isset( $submenu[$parent] ) ) {
            $new_order = array();
            foreach ( $submenu[$parent] as $item ) {

                if ( isset($item[2]) && $item[2] === 'mptbm_transportation_lists' ) {
                    array_unshift( $new_order, $item );
                } else {
                    $new_order[] = $item;
                }
            }

            $submenu[$parent] = $new_order;
        }
    }


//esc_html_e('ON', 'ecab-taxi-booking-manager');
    public function mptbm_transportation_lists_menu() {

        add_submenu_page(
            'edit.php?post_type=mptbm_rent',
            'Transportation Lists',
            'Transportation Lists',
            'manage_options',
            'mptbm_transportation_lists',
            array( $this, 'mptbm_transportation_lists_page' )
        );
        }

    function mptbm_trash_transport_callback() {

        if (!isset($_GET['id'])) {
            wp_die('Invalid request');
        }

        $id = intval($_GET['id']);

        // Optional: security check (recommended)
        if (!current_user_can('delete_post', $id)) {
            wp_die('You are not allowed to trash this item.');
        }

        // WordPress built-in trash
        wp_trash_post($id);

        wp_redirect(admin_url('edit.php?post_type=mptbm_rent&page=mptbm_transportation_lists'));
        exit;
    }

    public function mptbm_transportation_lists_page() {

        $page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

        $lists = $this->mptbm_get_rent_lists( $page, 6 );
        $transportations = $lists['posts'];

        $total_pages = $lists['pagination']['total_pages'];
        $current     = $lists['pagination']['current_page'];
        $total_posts = $lists['pagination']['total_posts'];
        $per_page    = $lists['pagination']['per_page'];

//        error_log( print_r( [ '$transportations' => $transportations ], true ) );

        ?>

        <div class="wrap mptbm_transportation_lists_wrapper">

            <?php $this->mptbm_transportation_lists_header( $total_posts ); ?>

            <?php $this->mptbm_transportation_lists_stats( $total_posts ); ?>

            <?php $this->mptbm_transportation_lists_filter(); ?>

            <div class="mptbm_transportation_lists_cards_wrapper">

                <?php
                if ( ! empty( $transportations ) ) {

                    foreach ( $transportations as $transportation ) {

                        $this->mptbm_transportation_lists_single_card( $transportation );
                    }
                }
                ?>

            </div>

            <?php $this->mptbm_transportation_lists_footer( $lists, $total_pages, $current, $total_posts, $per_page ); ?>

        </div>

        <?php
    }


    /**
     * Get Transport Rent List With Pagination
     *
     * @param int $paged
     * @param int $per_page
     *
     * @return array
     */
    public function mptbm_get_rent_lists( $paged = 1, $per_page = 10 ) {

        $args = array(
            'post_type'      => 'mptbm_rent',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'ID',
            'order'          => 'DESC',
        );

        $query = new WP_Query( $args );

        $data = array();

        if ( $query->have_posts() ) {

            while ( $query->have_posts() ) {

                $query->the_post();

                $post_id = get_the_ID();

                $item = get_post_meta( $post_id, 'mptbm_features', true );
                $model = '';
                if ( ! empty( $item ) && is_array( $item ) ){
                    foreach ( $item as $feature ) {
                        if ( isset( $feature['label'] ) && strtolower( $feature['label'] ) === 'model' ) {
                            $model = $feature['text'];
                            break;
                        }
                    }
                }

                $data[] = array(

                    'id'       => $post_id,

                    'title'    => get_the_title(),

                    'location' => get_post_meta( $post_id, 'mptbm_location', true ),

                    'type'     => get_post_meta( $post_id, 'mptbm_transport_type', true ),

                    'km'       => get_post_meta( $post_id, 'mptbm_km_price', true ),

                    'hourly'   => get_post_meta( $post_id, 'mptbm_hour_price', true ),

                    'model'    => $model,

                    'status'   => get_post_status( $post_id ),

                    'image'    => get_post_meta( $post_id, 'feature_image', true ),

                    'link_wc_product' => get_post_meta( $post_id, 'link_wc_product', true ),

                    'price_based' => get_post_meta( $post_id, 'mptbm_price_based', true ),

                );
            }

            wp_reset_postdata();
        }

        return array(

            'posts' => $data,

            'pagination' => array(

                'total_posts' => $query->found_posts,
                'total_pages' => $query->max_num_pages,
                'current_page' => $paged,
                'per_page' => $per_page,

            ),

        );
    }

    /**
     * Transportation Data
     */

    /**
     * Header
     */
    private function mptbm_transportation_lists_header( $total_posts ) {
        ?>

        <div class="mptbm_transportation_lists_topbar">

            <div>

                <h1 class="mptbm_transportation_lists_page_title">
                    <?php esc_html_e('Transportation', 'ecab-taxi-booking-manager');?>
                </h1>

                <div class="mptbm_transportation_lists_tabs">

                    <a href="#" class="mptbm_transportation_lists_tab_active">
                        <?php esc_html_e('All', 'ecab-taxi-booking-manager');?> (<?php echo esc_attr( $total_posts );?>)
                    </a>

                    <a href="#">
                        <?php esc_html_e('Published', 'ecab-taxi-booking-manager');?> (<?php echo esc_attr( $total_posts );?>)
                    </a>

                    <a href="#">
                        <?php esc_html_e('Trashed', 'ecab-taxi-booking-manager');?> (<?php echo esc_attr( $total_posts );?>)
                    </a>

                </div>

            </div>

            <a href="#" class="mptbm_transportation_lists_add_btn">

                <span class="dashicons dashicons-plus-alt2"></span>

                <?php esc_html_e('Add New Transportation', 'ecab-taxi-booking-manager');?>

            </a>

        </div>

        <?php
    }

    private static function mptbm_get_current_month_sales_total() {

        $start_date = date('Y-m-01 00:00:00');
        $end_date   = date('Y-m-t 23:59:59');

        $args = array(
            'post_type'      => 'mptbm_booking',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => 'mptbm_date',
                    'value'   => array($start_date, $end_date),
                    'compare' => 'BETWEEN',
                    'type'    => 'DATETIME'
                )
            )
        );

        $query = new WP_Query($args);

        $total = 0;

        while ( $query->have_posts() ) {
            $query->the_post();

            $price = get_post_meta(get_the_ID(), 'mptbm_tp', true);
            $total += floatval($price);
        }

        wp_reset_postdata();

        return number_format($total, 2, '.', '');
    }

    /**
     * Stats
     */
    private function mptbm_transportation_lists_stats( $total_posts ) {

        $total_revenue = self::mptbm_get_current_month_sales_total();
        ?>

        <div class="mptbm_transportation_lists_stats_wrapper">

            <div class="mptbm_transportation_lists_stats_card">

                <div class="mptbm_transportation_lists_stats_icon">
                    <span class="dashicons dashicons-car"></span>
                </div>

                <div>
                    <div class="mptbm_transportation_lists_stats_label">
                        <?php esc_html_e('ACTIVE FLEET', 'ecab-taxi-booking-manager');?>
                    </div>

                    <div class="mptbm_transportation_lists_stats_value">
                        <?php echo esc_attr( $total_posts );?>  <?php esc_html_e('Vehicles', 'ecab-taxi-booking-manager');?>
                    </div>
                </div>

            </div>

            <div class="mptbm_transportation_lists_stats_card">

                <div class="mptbm_transportation_lists_stats_icon">
                    <span class="dashicons dashicons-location"></span>
                </div>

                <div>
                    <div class="mptbm_transportation_lists_stats_label">
                        <?php esc_html_e('TOTAL ROUTES', 'ecab-taxi-booking-manager');?>
                    </div>

                    <div class="mptbm_transportation_lists_stats_value">
                        <?php esc_html_e('24 Daily', 'ecab-taxi-booking-manager');?>
                    </div>
                </div>

            </div>

            <div class="mptbm_transportation_lists_stats_card">

                <div class="mptbm_transportation_lists_stats_icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>

                <div>
                    <div class="mptbm_transportation_lists_stats_label">
                        <?php esc_html_e('AVG. REVENUE', 'ecab-taxi-booking-manager');?>
                    </div>

                    <div class="mptbm_transportation_lists_stats_value">
                        <?php echo esc_attr( $total_revenue ); ' '.esc_html_e('/mo', 'ecab-taxi-booking-manager');?>
                    </div>
                </div>

            </div>

        </div>

        <?php
    }

    /**
     * Filter Bar
     */
    private function mptbm_transportation_lists_filter() {
        ?>

        <div class="mptbm_transportation_lists_filter_bar">

            <div class="mptbm_transportation_lists_filter_left">

                <select class="mptbm_transportation_lists_bulk_select">
                    <option> <?php esc_html_e('Bulk actions', 'ecab-taxi-booking-manager');?></option>
                </select>

                <button class="mptbm_transportation_lists_apply_btn">
                    <?php esc_html_e('Apply', 'ecab-taxi-booking-manager');?>
                </button>

            </div>

            <div class="mptbm_transportation_lists_search_box">

                <span class="dashicons dashicons-search"></span>

                <input name="mptbm_search_by_title" id="mptbm_search_by_title" type="text" placeholder="Search Transportation...">

                <button><?php esc_html_e('Search', 'ecab-taxi-booking-manager');?></button>

            </div>

        </div>

        <?php
    }

    /**
     * Single Card
     */
    private function mptbm_transportation_lists_single_card( $item ) {

        ?>

        <div class="mptbm_transportation_lists_card" data-transport-title="<?php echo esc_attr( $item['title'] );?>">

            <div class="mptbm_transportation_lists_image_area">

                <div class="mptbm_transportation_lists_badge">
                    <span class="dashicons dashicons-category"></span>
                    <?php echo esc_html( $item['price_based'] ); ?>
                </div>

                <img src="<?php echo esc_url( $item['image'] ); ?>" alt="">

            </div>

            <div class="mptbm_transportation_lists_content">

                <div class="mptbm_transportation_lists_card_top">

                    <div>

                        <h2 class="mptbm_transportation_lists_vehicle_title">
                            <span class="dashicons dashicons-car"></span>
                            <?php echo esc_html( $item['title'] ); ?>
                        </h2>

                        <div class="mptbm_transportation_lists_location">

                            <span class="dashicons dashicons-location"></span>

                            <?php echo esc_html( $item['location'] ); ?>

                        </div>

                    </div>

                    <div class="mptbm_transportation_lists_action_buttons">

                        <a href="<?php echo esc_url( admin_url('admin.php?page=mptbm-rent-edit&post_id=' . $item['id']) ); ?>"
                           class="mptbm_transportation_lists_edit_btn">
                            <span class="dashicons dashicons-edit"></span>
                        </a>

                        <a href="<?php echo esc_url( admin_url('admin-post.php?action=mptbm_trash_transport&id=' . $item['id']) ); ?>"
                           class="mptbm_transportation_lists_delete_btn mptbm-trash-confirm">
                            <span class="dashicons dashicons-trash"></span>
                        </a>

                    </div>

                </div>

                <div class="mptbm_transportation_lists_meta_box">

                    <div class="mptbm_transportation_lists_meta_item">

                        <div class="mptbm_transportation_lists_meta_label">
                            <span class="dashicons dashicons-money-alt"></span>
                            <?php esc_html_e('KM PRICE', 'ecab-taxi-booking-manager');?>
                        </div>

                        <div class="mptbm_transportation_lists_meta_value">
                            <?php echo esc_html( $item['km'] ); ?>
                        </div>

                    </div>

                    <div class="mptbm_transportation_lists_meta_item">

                        <div class="mptbm_transportation_lists_meta_label">
                            <span class="dashicons dashicons-clock"></span>
                            <?php esc_html_e('HOURLY PRICE', 'ecab-taxi-booking-manager');?>
                        </div>

                        <div class="mptbm_transportation_lists_meta_value">
                            <?php echo esc_html( $item['hourly'] ); ?>
                        </div>

                    </div>

                    <div class="mptbm_transportation_lists_meta_item">

                        <div class="mptbm_transportation_lists_meta_label">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php esc_html_e('MODEL', 'ecab-taxi-booking-manager');?>
                        </div>

                        <div class="mptbm_transportation_lists_meta_value_black">
                            <?php echo esc_html( $item['model'] ); ?>
                        </div>

                    </div>

                    <div class="mptbm_transportation_lists_meta_item">

                        <div class="mptbm_transportation_lists_meta_label">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('STATUS', 'ecab-taxi-booking-manager');?>
                        </div>

                        <div class="mptbm_transportation_lists_meta_status">

                            <span></span>

                            <?php echo esc_html( $item['status'] ); ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <?php
    }

    /**
     * Footer
     */
    private function mptbm_transportation_lists_footer( $lists, $total_pages, $current, $total_posts, $per_page ) {

        // Current showing items
        $start_item = ( ( $current - 1 ) * $per_page ) + 1;
        $end_item   = min( $current * $per_page, $total_posts );

        ?>

        <div class="mptbm_transportation_lists_footer">

            <div class="mptbm_transportation_lists_footer_text">

                <?php esc_html_e('Showing', 'ecab-taxi-booking-manager');?>
                <?php echo esc_html( $start_item ); ?>
                -
                <?php echo esc_html( $end_item ); ?>

                <?php esc_html_e('of', 'ecab-taxi-booking-manager');?>

                <?php echo esc_html( $total_posts ); ?>

                <?php esc_html_e('Transportation Items', 'ecab-taxi-booking-manager');?>

            </div>

            <?php if ( $total_pages > 1 ) : ?>

                <div class="mptbm_transportation_lists_pagination">

                    <!-- Previous Button -->
                    <?php if ( $current > 1 ) : ?>

                        <?php
                        $prev_url = admin_url(
                            'edit.php?post_type=mptbm_rent&page=mptbm_transportation_lists&paged=' . ( $current - 1 )
                        );
                        ?>

                        <a href="<?php echo esc_url( $prev_url ); ?>" class="mptbm_pagination_btn">

                            <span class="dashicons dashicons-arrow-left-alt2"></span>

                        </a>

                    <?php endif; ?>



                    <div class="mptbm_transportation_lists_pagination_text">

                        <?php

                        for ( $i = 1; $i <= $total_pages; $i++ ) {

                            $active = ( $current == $i ) ? 'active' : '';

                            $url = admin_url(
                                'edit.php?post_type=mptbm_rent&page=mptbm_transportation_lists&paged=' . $i
                            );

                            ?>

                            <a
                                class="mptbm_pagination_number <?php echo esc_attr( $active ); ?>"
                                href="<?php echo esc_url( $url ); ?>"
                            >
                                <?php echo esc_html( $i ); ?>
                            </a>

                            <?php
                        }
                        ?>

                    </div>

                    <!-- Next Button -->
                    <?php if ( $current < $total_pages ) : ?>

                        <?php
                        $next_url = admin_url(
                            'edit.php?post_type=mptbm_rent&page=mptbm_transportation_lists&paged=' . ( $current + 1 )
                        );
                        ?>

                        <a href="<?php echo esc_url( $next_url ); ?>" class="mptbm_pagination_btn">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </a>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

        <?php
    }


}
new MPTBM_Transportation();