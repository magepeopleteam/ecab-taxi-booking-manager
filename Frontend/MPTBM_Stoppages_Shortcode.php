<?php
/*
 * [mptbm_stoppages_list] - public grid/list of the mptbm_stoppages CPT.
 * The CPT itself has no public permalink (public/publicly_queryable false in
 * MPTBM_CPT.php), so each card links back to the same shortcode with a
 * ?mptbm_stoppage=<id> query arg; render() swaps to the single-stop detail
 * view whenever that arg is present, instead of relying on a rewrite rule.
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Stoppages_Shortcode')) {
    class MPTBM_Stoppages_Shortcode {
        const POST_TYPE = 'mptbm_stoppages';
        const QUERY_ARG = 'mptbm_stoppage';
        const LOAD_MORE_ACTION = 'mptbm_stoppages_public_load_more';

        public function __construct() {
            add_shortcode('mptbm_stoppages_list', array($this, 'render'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
            add_action('wp_ajax_' . self::LOAD_MORE_ACTION, array($this, 'ajax_load_more'));
            add_action('wp_ajax_nopriv_' . self::LOAD_MORE_ACTION, array($this, 'ajax_load_more'));
        }

        public function enqueue_assets(): void {
            $css_relative = 'assets/frontend/mptbm_stoppages_list.css';
            $js_relative = 'assets/frontend/mptbm_stoppages_list.js';
            $css_path = MPTBM_PLUGIN_DIR . '/' . $css_relative;
            $js_path = MPTBM_PLUGIN_DIR . '/' . $js_relative;

            wp_enqueue_style(
                'mptbm-stoppages-list',
                MPTBM_PLUGIN_URL . '/' . $css_relative,
                array(),
                file_exists($css_path) ? filemtime($css_path) : MPTBM_PLUGIN_VERSION
            );
            wp_enqueue_script(
                'mptbm-stoppages-list',
                MPTBM_PLUGIN_URL . '/' . $js_relative,
                array('jquery'),
                file_exists($js_path) ? filemtime($js_path) : MPTBM_PLUGIN_VERSION,
                true
            );
            wp_localize_script('mptbm-stoppages-list', 'mptbmStoppagesList', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'action'  => self::LOAD_MORE_ACTION,
            ));
        }

        public function render($attributes): string {
            $requested_id = isset($_GET[self::QUERY_ARG]) ? absint($_GET[self::QUERY_ARG]) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ($requested_id) {
                return $this->render_single($requested_id);
            }
            return $this->render_list($attributes);
        }

        private function render_list($attributes): string {
            $atts = shortcode_atts(array(
                'title'     => '',
                'columns'   => '3',
                'view'      => 'grid',
                'per_page'  => '8',
                'load_more' => 'yes',
            ), $attributes, 'mptbm_stoppages_list');

            $columns = in_array((int) $atts['columns'], array(2, 3, 4), true) ? (int) $atts['columns'] : 3;
            $view = ($atts['view'] === 'list') ? 'list' : 'grid';
            $per_page = max(1, min(24, absint($atts['per_page']) ?: 8));
            $allow_load_more = ($atts['load_more'] !== 'no');

            $query = new WP_Query(array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => $per_page,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ));

            $total = (int) $query->found_posts;
            $loaded = count($query->posts);
            $has_more = $allow_load_more && ($loaded < $total);

            ob_start();
            ?>
            <div class="mptbm-stops" data-view="<?php echo esc_attr($view); ?>" data-columns="<?php echo esc_attr((string) $columns); ?>" data-per-page="<?php echo esc_attr((string) $per_page); ?>" data-loaded="<?php echo esc_attr((string) $loaded); ?>" data-total="<?php echo esc_attr((string) $total); ?>">
                <div class="mptbm-stops-toolbar">
                    <?php if ($atts['title'] !== '') : ?>
                        <h2 class="mptbm-stops-title"><?php echo esc_html($atts['title']); ?></h2>
                    <?php endif; ?>
                    <div class="mptbm-stops-view-toggle" role="group" aria-label="<?php esc_attr_e('Switch view', 'ecab-taxi-booking-manager'); ?>">
                        <button type="button" class="mptbm-stops-view-btn<?php echo $view === 'grid' ? ' is-active' : ''; ?>" data-view-btn="grid" aria-pressed="<?php echo $view === 'grid' ? 'true' : 'false'; ?>">
                            <i class="fas fa-th-large" aria-hidden="true"></i><span><?php esc_html_e('Grid', 'ecab-taxi-booking-manager'); ?></span>
                        </button>
                        <button type="button" class="mptbm-stops-view-btn<?php echo $view === 'list' ? ' is-active' : ''; ?>" data-view-btn="list" aria-pressed="<?php echo $view === 'list' ? 'true' : 'false'; ?>">
                            <i class="fas fa-list" aria-hidden="true"></i><span><?php esc_html_e('List', 'ecab-taxi-booking-manager'); ?></span>
                        </button>
                    </div>
                </div>

                <?php if (!$total) : ?>
                    <p class="mptbm-stops-empty"><?php esc_html_e('No stoppages have been added yet.', 'ecab-taxi-booking-manager'); ?></p>
                <?php else : ?>
                    <div class="mptbm-stops-grid" data-stops-grid>
                        <?php foreach ($query->posts as $post) {
                            echo $this->render_card($post); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_card() escapes internally.
                        } ?>
                    </div>
                    <?php if ($has_more) : ?>
                        <div class="mptbm-stops-load-more-wrap">
                            <button type="button" class="mptbm-stops-load-more" data-load-more data-nonce="<?php echo esc_attr(wp_create_nonce(self::LOAD_MORE_ACTION)); ?>">
                                <span class="mptbm-stops-load-more-label"><?php esc_html_e('Load more', 'ecab-taxi-booking-manager'); ?></span>
                                <span class="mptbm-stops-spinner" aria-hidden="true"></span>
                            </button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php
            return (string) ob_get_clean();
        }

        public function ajax_load_more(): void {
            check_ajax_referer(self::LOAD_MORE_ACTION, 'nonce');

            $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
            $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 8;
            $per_page = max(1, min(24, $per_page));

            $query = new WP_Query(array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => $per_page,
                'offset'         => $offset,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ));

            $html = '';
            foreach ($query->posts as $post) {
                $html .= $this->render_card($post);
            }

            $loaded = $offset + count($query->posts);
            $total = (int) $query->found_posts;

            wp_send_json_success(array(
                'html'     => $html,
                'loaded'   => $loaded,
                'total'    => $total,
                'has_more' => $loaded < $total,
            ));
        }

        private function badge_labels(): array {
            return array(
                'most_popular' => esc_html__('Most popular', 'ecab-taxi-booking-manager'),
                'recommended'  => esc_html__('Recommended', 'ecab-taxi-booking-manager'),
            );
        }

        private function format_price_or_free($price): string {
            return ($price !== '' && $price !== null)
                ? wp_kses_post(MP_Global_Function::format_price((float) $price))
                : esc_html__('Free', 'ecab-taxi-booking-manager');
        }

        private function render_card(WP_Post $post): string {
            $image_id = (int) get_post_meta($post->ID, 'mptbm_stoppage_image', true);
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium_large') : '';
            $duration = get_post_meta($post->ID, 'mptbm_stoppage_duration', true);
            $duration_display = MPTBM_Function::format_duration_minutes($duration);
            $price = get_post_meta($post->ID, 'mptbm_stoppage_price', true);
            $badge = get_post_meta($post->ID, 'mptbm_stoppage_badge', true);
            $badge_labels = $this->badge_labels();
            $detail_url = esc_url(add_query_arg(self::QUERY_ARG, $post->ID));

            ob_start();
            ?>
            <article class="mptbm-stop-card">
                <a class="mptbm-stop-card-link" href="<?php echo $detail_url; ?>">
                    <div class="mptbm-stop-card-media" <?php echo $image_url ? 'style="background-image:url(' . esc_url($image_url) . ')"' : ''; ?>>
                        <?php if (!$image_url) : ?><i class="fas fa-image" aria-hidden="true"></i><?php endif; ?>
                        <?php if ($badge && isset($badge_labels[$badge])) : ?>
                            <span class="mptbm-stop-card-badge is-<?php echo esc_attr($badge); ?>"><?php echo esc_html($badge_labels[$badge]); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="mptbm-stop-card-body">
                        <h3 class="mptbm-stop-card-title"><?php echo esc_html($post->post_title); ?></h3>
                        <p class="mptbm-stop-card-meta">
                            <?php if ($duration_display) : ?><span class="mptbm-stop-card-duration"><i class="fas fa-clock" aria-hidden="true"></i> <?php echo esc_html($duration_display); ?></span><?php endif; ?>
                            <span class="mptbm-stop-card-price"><?php echo $this->format_price_or_free($price); ?></span>
                        </p>
                    </div>
                </a>
            </article>
            <?php
            return (string) ob_get_clean();
        }

        private function render_single(int $id): string {
            $back_url = esc_url(remove_query_arg(self::QUERY_ARG));
            $post = get_post($id);

            if (!$post || $post->post_type !== self::POST_TYPE || $post->post_status !== 'publish') {
                ob_start();
                ?>
                <div class="mptbm-stop-single mptbm-stop-single-missing">
                    <p><?php esc_html_e('This stoppage could not be found.', 'ecab-taxi-booking-manager'); ?></p>
                    <a class="mptbm-stop-back" href="<?php echo $back_url; ?>">&larr; <?php esc_html_e('Back to all stops', 'ecab-taxi-booking-manager'); ?></a>
                </div>
                <?php
                return (string) ob_get_clean();
            }

            $image_id = (int) get_post_meta($post->ID, 'mptbm_stoppage_image', true);
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
            $duration = get_post_meta($post->ID, 'mptbm_stoppage_duration', true);
            $duration_display = MPTBM_Function::format_duration_minutes($duration);
            $price = get_post_meta($post->ID, 'mptbm_stoppage_price', true);
            $badge = get_post_meta($post->ID, 'mptbm_stoppage_badge', true);
            $badge_labels = $this->badge_labels();
            $description = get_post_meta($post->ID, 'mptbm_stoppage_description', true);

            $gallery_ids = get_post_meta($post->ID, 'mptbm_stoppage_gallery', true);
            $gallery_ids = is_array($gallery_ids) ? array_map('absint', $gallery_ids) : array();
            $gallery_images = array_values(array_filter(array_map(function ($gallery_id) {
                $thumb = wp_get_attachment_image_url($gallery_id, 'medium');
                $full = wp_get_attachment_image_url($gallery_id, 'large');
                return ($thumb && $full) ? array('thumb' => $thumb, 'full' => $full) : null;
            }, $gallery_ids)));

            ob_start();
            ?>
            <div class="mptbm-stop-single">
                <nav class="mptbm-stop-breadcrumb">
                    <a class="mptbm-stop-back" href="<?php echo $back_url; ?>">&larr; <?php esc_html_e('Back to all stops', 'ecab-taxi-booking-manager'); ?></a>
                </nav>

                <div class="mptbm-stop-hero" <?php echo $image_url ? 'style="background-image:url(' . esc_url($image_url) . ')"' : ''; ?>>
                    <div class="mptbm-stop-hero-overlay">
                        <?php if ($badge && isset($badge_labels[$badge])) : ?>
                            <span class="mptbm-stop-card-badge is-<?php echo esc_attr($badge); ?>"><?php echo esc_html($badge_labels[$badge]); ?></span>
                        <?php endif; ?>
                        <h1 class="mptbm-stop-hero-title"><?php echo esc_html($post->post_title); ?></h1>
                        <div class="mptbm-stop-hero-meta">
                            <?php if ($duration_display) : ?><span><i class="fas fa-clock" aria-hidden="true"></i> <?php echo esc_html($duration_display); ?></span><?php endif; ?>
                            <span class="mptbm-stop-hero-price"><?php echo $this->format_price_or_free($price); ?></span>
                        </div>
                    </div>
                </div>

                <div class="mptbm-stop-single-layout">
                    <div class="mptbm-stop-single-main">
                        <?php if ($description !== '') : ?>
                            <section class="mptbm-stop-section">
                                <h2><?php esc_html_e('About this stop', 'ecab-taxi-booking-manager'); ?></h2>
                                <div class="mptbm-stop-single-description"><?php echo wp_kses_post($description); ?></div>
                            </section>
                        <?php endif; ?>

                        <?php if (!empty($gallery_images)) : ?>
                            <section class="mptbm-stop-section">
                                <h2><?php esc_html_e('Gallery', 'ecab-taxi-booking-manager'); ?></h2>
                                <div class="mptbm-stop-single-gallery" data-lightbox-gallery>
                                    <?php foreach ($gallery_images as $gallery_image) : ?>
                                        <button type="button" class="mptbm-stop-gallery-item" data-lightbox-src="<?php echo esc_url($gallery_image['full']); ?>">
                                            <img src="<?php echo esc_url($gallery_image['thumb']); ?>" alt="<?php echo esc_attr($post->post_title); ?>" loading="lazy" />
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>
                    </div>

                    <aside class="mptbm-stop-single-sidebar">
                        <div class="mptbm-stop-info-card">
                            <h3><?php esc_html_e('Stop details', 'ecab-taxi-booking-manager'); ?></h3>
                            <ul class="mptbm-stop-info-list">
                                <?php if ($duration_display) : ?>
                                    <li><span><i class="fas fa-clock" aria-hidden="true"></i> <?php esc_html_e('Duration', 'ecab-taxi-booking-manager'); ?></span><strong><?php echo esc_html($duration_display); ?></strong></li>
                                <?php endif; ?>
                                <li><span><i class="fas fa-tag" aria-hidden="true"></i> <?php esc_html_e('Price', 'ecab-taxi-booking-manager'); ?></span><strong><?php echo $this->format_price_or_free($price); ?></strong></li>
                            </ul>
                            <p class="mptbm-stop-info-note"><?php esc_html_e('This stop can be added as an optional extra to an eligible trip during booking.', 'ecab-taxi-booking-manager'); ?></p>
                        </div>
                    </aside>
                </div>
            </div>

            <div class="mptbm-stop-lightbox" data-lightbox hidden>
                <button type="button" class="mptbm-stop-lightbox-close" data-lightbox-close aria-label="<?php esc_attr_e('Close', 'ecab-taxi-booking-manager'); ?>">&times;</button>
                <button type="button" class="mptbm-stop-lightbox-prev" data-lightbox-prev aria-label="<?php esc_attr_e('Previous image', 'ecab-taxi-booking-manager'); ?>">&lsaquo;</button>
                <img src="" alt="" class="mptbm-stop-lightbox-image" data-lightbox-image />
                <button type="button" class="mptbm-stop-lightbox-next" data-lightbox-next aria-label="<?php esc_attr_e('Next image', 'ecab-taxi-booking-manager'); ?>">&rsaquo;</button>
            </div>
            <?php
            return (string) ob_get_clean();
        }
    }
    new MPTBM_Stoppages_Shortcode();
}
