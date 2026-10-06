<?php
/*
 * New shortcode: [mptbm_taxi_list]
 * Modern grid/list browsing widget for every published vehicle (mptbm_rent) -
 * independent of the search/booking flow, for pages that just want to show
 * off the fleet (e.g. a "Our Fleet" landing page).
 *
 * Attributes:
 *   title   heading text shown above the grid (default: '' - hidden when empty)
 *   view    grid|list, the view shown on first load        (default: grid)
 *   column  grid columns on desktop, 1-6                   (default: 4)
 *   show    vehicles loaded initially / per "Load More" click (default: 20)
 *
 * Example: [mptbm_taxi_list title="Our Fleet" column="3" show="12"]
 *
 * First batch renders server-side in PHP (real content for guests/SEO/no-JS);
 * "Load More" fetches the next batch over AJAX using offset pagination - same
 * server-render-first-page-then-AJAX pattern already used by the per-vehicle
 * Stoppage picker (Admin/MPTBM_Rent_Custom_Editor.php).
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Taxi_List_Shortcode')) {
    class MPTBM_Taxi_List_Shortcode {
        const NONCE_ACTION = 'mptbm_taxi_list';

        public function __construct() {
            add_shortcode('mptbm_taxi_list', array($this, 'render'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
            add_action('wp_ajax_mptbm_taxi_list_load_more', array($this, 'ajax_load_more'));
            add_action('wp_ajax_nopriv_mptbm_taxi_list_load_more', array($this, 'ajax_load_more'));
        }

        public function enqueue_assets() {
            wp_enqueue_style(
                'mptbm_taxi_list',
                MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_taxi_list.css',
                array(),
                MPTBM_PLUGIN_VERSION
            );
            wp_enqueue_script(
                'mptbm_taxi_list',
                MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_taxi_list.js',
                array('jquery'),
                MPTBM_PLUGIN_VERSION,
                true
            );
            wp_localize_script('mptbm_taxi_list', 'mptbm_taxi_list_i18n', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce(self::NONCE_ACTION),
            ));
        }

        public function render($attributes) {
            $atts = shortcode_atts(array(
                'title'  => '',
                'view'   => 'grid',
                'column' => 3,
                'show'   => 20,
            ), $attributes, 'mptbm_taxi_list');

            $view   = ('list' === $atts['view']) ? 'list' : 'grid';
            $column = max(1, min(6, absint($atts['column']) ?: 3));
            $show   = max(1, absint($atts['show']) ?: 20);
            $title  = trim((string) $atts['title']);

            $query = $this->query_vehicles($show, 0);
            $total = (int) $query->found_posts;
            $shown = count($query->posts);

            static $instance = 0;
            $instance++;

            ob_start();
            ?>
            <div class="mptbm_taxilist" id="mptbm-taxi-list-<?php echo esc_attr($instance); ?>" data-view="<?php echo esc_attr($view); ?>" style="--mptbm-tl-columns: <?php echo esc_attr($column); ?>" data-offset="<?php echo esc_attr($shown); ?>" data-per-page="<?php echo esc_attr($show); ?>">
                <div class="mptbm_taxilist_header">
                    <div class="mptbm_taxilist_heading">
                        <?php if ('' !== $title) : ?><h3 class="mptbm_taxilist_title"><?php echo esc_html($title); ?></h3><?php endif; ?>
                        <span class="mptbm_taxilist_count" data-taxilist-count><?php echo esc_html(sprintf(_n('%d vehicle', '%d vehicles', $total, 'ecab-taxi-booking-manager'), $total)); ?></span>
                    </div>
                    <div class="mptbm_taxilist_view_toggle" role="group" aria-label="<?php esc_attr_e('Switch view', 'ecab-taxi-booking-manager'); ?>">
                        <button type="button" class="mptbm_taxilist_view_btn<?php echo ('grid' === $view) ? ' is-active' : ''; ?>" data-taxilist-view="grid" aria-label="<?php esc_attr_e('Grid view', 'ecab-taxi-booking-manager'); ?>">
                            <span class="fas fa-th-large" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="mptbm_taxilist_view_btn<?php echo ('list' === $view) ? ' is-active' : ''; ?>" data-taxilist-view="list" aria-label="<?php esc_attr_e('List view', 'ecab-taxi-booking-manager'); ?>">
                            <span class="fas fa-list" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>

                <?php if ($total > 0) : ?>
                    <div class="mptbm_taxilist_grid" data-taxilist-grid>
                        <?php echo $this->render_cards($query->posts); // phpcs:ignore ?>
                    </div>

                    <?php if ($total > $shown) : ?>
                        <div class="mptbm_taxilist_footer" data-taxilist-footer>
                            <button type="button" class="mptbm_taxilist_load_more" data-taxilist-load-more>
                                <span data-taxilist-load-more-text><?php esc_html_e('Load More', 'ecab-taxi-booking-manager'); ?></span>
                                <span class="mptbm_taxilist_spinner" data-taxilist-spinner hidden></span>
                            </button>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <p class="mptbm_taxilist_empty"><?php esc_html_e('No vehicles found.', 'ecab-taxi-booking-manager'); ?></p>
                <?php endif; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        private function query_vehicles($per_page, $offset) {
            return new WP_Query(array(
                'post_type'      => MPTBM_Function::get_cpt(),
                'post_status'    => 'publish',
                'posts_per_page' => $per_page,
                'offset'         => $offset,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'no_found_rows'  => false,
            ));
        }

        private function render_cards($posts) {
            ob_start();
            foreach ($posts as $post) {
                $this->render_card($post->ID);
            }
            return ob_get_clean();
        }

        private function render_card($post_id) {
            $title      = get_the_title($post_id);
            $permalink  = get_permalink($post_id);
            $thumbnail  = MP_Global_Function::get_image_url($post_id, '', 'medium_large');
            $passengers = (int) get_post_meta($post_id, 'mptbm_maximum_passenger', true);
            $bags       = (int) get_post_meta($post_id, 'mptbm_maximum_bag', true);
            $price      = class_exists('MPTBM_Function') ? MPTBM_Function::get_price_headline_info($post_id) : array('headline' => '', 'unit' => '');
            ?>
            <div class="mptbm_taxilist_card">
                <a class="mptbm_taxilist_card_media" href="<?php echo esc_url($permalink); ?>">
                    <span class="mptbm_taxilist_card_media_bg" <?php echo $thumbnail ? 'style="background-image:url(' . esc_url($thumbnail) . ')"' : ''; ?>></span>
                    <?php if ('' !== $price['headline']) : ?>
                        <span class="mptbm_taxilist_price_tag">
                            <strong><?php echo wp_kses_post($price['headline']); ?></strong>
                            <?php if ('' !== $price['unit']) : ?><small><?php echo esc_html($price['unit']); ?></small><?php endif; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <div class="mptbm_taxilist_card_body">
                    <h4 class="mptbm_taxilist_card_title"><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a></h4>
                    <?php if ($passengers > 0 || $bags > 0) : ?>
                        <div class="mptbm_taxilist_card_meta">
                            <?php if ($passengers > 0) : ?><span><span class="fas fa-user" aria-hidden="true"></span> <?php echo esc_html($passengers); ?></span><?php endif; ?>
                            <?php if ($bags > 0) : ?><span><span class="fas fa-suitcase" aria-hidden="true"></span> <?php echo esc_html($bags); ?></span><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }

        public function ajax_load_more() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            $offset   = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
            $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
            $per_page = $per_page > 0 ? $per_page : 20;

            $query = $this->query_vehicles($per_page, $offset);

            wp_send_json_success(array(
                'html'  => $this->render_cards($query->posts),
                'total' => (int) $query->found_posts,
                'shown' => $offset + count($query->posts),
            ));
        }
    }
    new MPTBM_Taxi_List_Shortcode();
}
