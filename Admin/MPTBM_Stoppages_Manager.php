<?php
/*
 * Global Stoppages (mptbm_stoppages CPT) list management - admin.
 * Each post is one bookable sightseeing/rest stop that a taxi can offer
 * during a trip (name, image, description, duration, optional price).
 */
if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('MPTBM_Stoppages_Manager')) {
    class MPTBM_Stoppages_Manager {
        const POST_TYPE = 'mptbm_stoppages';

        public function __construct() {
            add_filter('admin_body_class', [ $this, 'add_body_class' ]);
            add_action('admin_enqueue_scripts', [ $this, 'enqueue_assets' ], 90);
            add_action('admin_notices', [ $this, 'render_screen' ], 20);
            add_action('wp_ajax_mptbm_add_stoppage', [ $this, 'ajax_add_stoppage' ]);
            add_action('wp_ajax_mptbm_update_stoppage', [ $this, 'ajax_update_stoppage' ]);
            add_action('wp_ajax_mptbm_delete_stoppage', [ $this, 'ajax_delete_stoppage' ]);
        }

        // CPT itself is registered centrally in MPTBM_CPT.php (same place as
        // mptbm_extra_services/mptbm_operate_areas) - this class only owns the
        // admin UI/AJAX for managing its posts.

        // Only the edit.php list screen — post.php/post-new.php are left as a
        // fully-functional fallback, same reasoning as MPTBM_Extra_Services_Manager.
        private function is_stoppages_list_screen(): bool {
            if (!is_admin() || !function_exists('get_current_screen')) {
                return false;
            }

            $screen = get_current_screen();
            return $screen && $screen->base === 'edit' && $screen->post_type === self::POST_TYPE;
        }

        public function add_body_class(string $classes): string {
            if ($this->is_stoppages_list_screen()) {
                $classes .= ' mptbm-stoppages-screen';
            }

            return $classes;
        }

        public function enqueue_assets(): void {
            if (!$this->is_stoppages_list_screen()) {
                return;
            }

            $css_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm_stoppages.css';
            $js_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm_stoppages.js';

            wp_enqueue_media();
            wp_enqueue_editor();

            wp_enqueue_style(
                'mptbm-stoppages',
                MPTBM_PLUGIN_URL . '/assets/admin/mptbm_stoppages.css',
                [ 'mptbm-shell' ],
                file_exists($css_path) ? filemtime($css_path) : MPTBM_PLUGIN_VERSION
            );

            wp_enqueue_script(
                'mptbm-stoppages',
                MPTBM_PLUGIN_URL . '/assets/admin/mptbm_stoppages.js',
                [ 'jquery' ],
                file_exists($js_path) ? filemtime($js_path) : MPTBM_PLUGIN_VERSION,
                true
            );

            wp_localize_script(
                'mptbm-stoppages',
                'mptbmStoppages',
                [
                    'ajaxUrl'        => admin_url('admin-ajax.php'),
                    'nonce'          => wp_create_nonce('mptbm_stoppages_manager_nonce'),
                    'addAction'      => 'mptbm_add_stoppage',
                    'updateAction'   => 'mptbm_update_stoppage',
                    'deleteAction'   => 'mptbm_delete_stoppage',
                    'descriptionEditorId' => 'mptbm_stoppage_description',
                    'genericError'   => esc_html__('Something went wrong. Please try again.', 'ecab-taxi-booking-manager'),
                    'requiredName'   => esc_html__('Enter a name for this stoppage.', 'ecab-taxi-booking-manager'),
                    'addTitle'       => esc_html__('Add Stoppage', 'ecab-taxi-booking-manager'),
                    'addLabel'       => esc_html__('Add stoppage', 'ecab-taxi-booking-manager'),
                    'addingLabel'    => esc_html__('Adding…', 'ecab-taxi-booking-manager'),
                    'editTitle'      => esc_html__('Edit Stoppage', 'ecab-taxi-booking-manager'),
                    'saveLabel'      => esc_html__('Save changes', 'ecab-taxi-booking-manager'),
                    'savingLabel'    => esc_html__('Saving…', 'ecab-taxi-booking-manager'),
                    'confirmDelete'  => esc_html__('Move this stoppage to Trash?', 'ecab-taxi-booking-manager'),
                    'chooseImage'    => esc_html__('Choose Image', 'ecab-taxi-booking-manager'),
                    'changeImage'    => esc_html__('Change Image', 'ecab-taxi-booking-manager'),
                    'mediaTitle'     => esc_html__('Select a stoppage image', 'ecab-taxi-booking-manager'),
                    'mediaButton'    => esc_html__('Use this image', 'ecab-taxi-booking-manager'),
                    'galleryTitle'   => esc_html__('Select gallery images', 'ecab-taxi-booking-manager'),
                    'galleryButton'  => esc_html__('Add to gallery', 'ecab-taxi-booking-manager'),
                ]
            );
        }

        public function render_screen(): void {
            if (!$this->is_stoppages_list_screen()) {
                return;
            }

            if (!current_user_can('manage_mptbm_transportation')) {
                return;
            }

            $posts = get_posts([
                'post_type'      => self::POST_TYPE,
                'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
                'numberposts'    => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);
            ?>
            <section class="mptbm-stoppages-page" aria-labelledby="mptbm-stoppages-title">
                <header class="mptbm-stoppages-hero">
                    <div class="mptbm-stoppages-heading">
                        <span class="mptbm-stoppages-heading-icon" aria-hidden="true">
                            <i class="fas fa-map-signs"></i>
                        </span>
                        <div>
                            <p class="mptbm-stoppages-eyebrow"><?php esc_html_e('Tourist Spots', 'ecab-taxi-booking-manager'); ?></p>
                            <h1 id="mptbm-stoppages-title"><?php esc_html_e('Stoppages', 'ecab-taxi-booking-manager'); ?></h1>
                            <p><?php esc_html_e('Create the sightseeing / rest stops taxis can offer along a route, with an optional price for each.', 'ecab-taxi-booking-manager'); ?></p>
                        </div>
                    </div>
                    <button type="button" class="button button-primary mptbm-stoppages-add" data-stoppage-open>
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <?php esc_html_e('Add New Stoppage', 'ecab-taxi-booking-manager'); ?>
                    </button>
                </header>

                <div class="mptbm-stoppages-summary">
                    <div>
                        <span><?php esc_html_e('Configured stoppages', 'ecab-taxi-booking-manager'); ?></span>
                        <strong data-stoppages-count><?php echo esc_html(count($posts)); ?></strong>
                    </div>
                    <p><?php esc_html_e('A vehicle can offer any number of these stoppages at checkout, one by one.', 'ecab-taxi-booking-manager'); ?></p>
                    <?php if ($posts) : ?>
                        <label class="mptbm-stoppages-search">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="search" data-stoppages-search placeholder="<?php esc_attr_e('Search stoppages by name…', 'ecab-taxi-booking-manager'); ?>" aria-label="<?php esc_attr_e('Search stoppages by name', 'ecab-taxi-booking-manager'); ?>">
                        </label>
                    <?php endif; ?>
                </div>

                <div class="mptbm-stoppages-grid" data-stoppages-grid>
                    <?php foreach ($posts as $post) : ?>
                        <?php echo $this->get_stoppage_card($post); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>

                <div class="mptbm-stoppages-empty<?php echo $posts ? ' is-hidden' : ''; ?>" data-stoppages-empty>
                    <span aria-hidden="true"><i class="fas fa-map-signs"></i></span>
                    <h2><?php esc_html_e('No stoppages yet', 'ecab-taxi-booking-manager'); ?></h2>
                    <p><?php esc_html_e('Add your first stoppage so vehicles can start offering it.', 'ecab-taxi-booking-manager'); ?></p>
                    <button type="button" class="button button-primary" data-stoppage-open>
                        <?php esc_html_e('Add First Stoppage', 'ecab-taxi-booking-manager'); ?>
                    </button>
                </div>

                <div class="mptbm-stoppages-empty is-hidden" data-stoppages-search-empty>
                    <span aria-hidden="true"><i class="fas fa-search"></i></span>
                    <h2><?php esc_html_e('No matching stoppages', 'ecab-taxi-booking-manager'); ?></h2>
                    <p><?php esc_html_e('Try a different search term.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
            </section>

            <div class="mptbm-stoppages-modal" data-stoppage-modal aria-hidden="true">
                <div class="mptbm-stoppages-modal-backdrop" data-stoppage-close></div>
                <div class="mptbm-stoppages-dialog" role="dialog" aria-modal="true" aria-labelledby="mptbm-stoppages-modal-title">
                    <button type="button" class="mptbm-stoppages-modal-close" data-stoppage-close aria-label="<?php esc_attr_e('Close dialog', 'ecab-taxi-booking-manager'); ?>">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                    <div class="mptbm-stoppages-dialog-head">
                        <h2 id="mptbm-stoppages-modal-title" data-stoppage-title><?php esc_html_e('Add Stoppage', 'ecab-taxi-booking-manager'); ?></h2>
                        <p><?php esc_html_e('One sightseeing/rest spot a customer can add to their trip.', 'ecab-taxi-booking-manager'); ?></p>
                    </div>

                    <form data-stoppage-form novalidate>
                        <?php wp_nonce_field('mptbm_stoppages_manager_nonce', 'mptbm_stoppages_manager_nonce'); ?>
                        <input type="hidden" name="post_id" data-stoppage-post-id value="" />

                        <div class="mptbm-stoppages-media-row">
                            <div class="mptbm-stoppages-field is-cover">
                                <label><?php esc_html_e('Cover Image', 'ecab-taxi-booking-manager'); ?></label>
                                <div class="mptbm-stoppages-media-picker" data-stoppage-media-preview>
                                    <div class="mptbm-stoppages-media-placeholder" data-stoppage-media-placeholder>
                                        <i class="fas fa-image" aria-hidden="true"></i>
                                    </div>
                                    <button type="button" class="mptbm-stoppages-media-pick" data-stoppage-media-pick>
                                        <i class="fas fa-camera" aria-hidden="true"></i>
                                        <span data-stoppage-media-pick-label><?php esc_html_e('Choose Image', 'ecab-taxi-booking-manager'); ?></span>
                                    </button>
                                </div>
                                <input type="hidden" name="image_id" data-stoppage-image-id value="" />
                            </div>

                            <div class="mptbm-stoppages-field is-gallery">
                                <label><?php esc_html_e('Gallery', 'ecab-taxi-booking-manager'); ?> <span class="is-optional"><?php esc_html_e('(optional)', 'ecab-taxi-booking-manager'); ?></span></label>
                                <div class="mptbm-stoppages-gallery-grid" data-stoppage-gallery-grid></div>
                                <button type="button" class="mptbm-stoppages-gallery-add" data-stoppage-gallery-pick>
                                    <i class="fas fa-images" aria-hidden="true"></i>
                                    <?php esc_html_e('Add Images', 'ecab-taxi-booking-manager'); ?>
                                </button>
                                <input type="hidden" name="gallery_ids" data-stoppage-gallery-ids value="[]" />
                            </div>
                        </div>

                        <div class="mptbm-stoppages-field">
                            <label for="mptbm-stoppage-name"><?php esc_html_e('Name', 'ecab-taxi-booking-manager'); ?> <span aria-hidden="true">*</span></label>
                            <input type="text" id="mptbm-stoppage-name" name="title" maxlength="200" autocomplete="off" placeholder="<?php esc_attr_e('For example: Mohakhali DOHS Viewpoint', 'ecab-taxi-booking-manager'); ?>" required>
                        </div>

                        <div class="mptbm-stoppages-field-row">
                            <div class="mptbm-stoppages-field">
                                <label for="mptbm-stoppage-duration"><?php esc_html_e('Duration (minutes)', 'ecab-taxi-booking-manager'); ?></label>
                                <input type="number" id="mptbm-stoppage-duration" name="duration" min="0" step="1" placeholder="<?php esc_attr_e('For example: 30', 'ecab-taxi-booking-manager'); ?>">
                            </div>
                            <div class="mptbm-stoppages-field">
                                <label for="mptbm-stoppage-price"><?php esc_html_e('Price', 'ecab-taxi-booking-manager'); ?> <span class="is-optional"><?php esc_html_e('(optional)', 'ecab-taxi-booking-manager'); ?></span></label>
                                <input type="number" id="mptbm-stoppage-price" name="price" step="0.01" min="0" placeholder="<?php esc_attr_e('Leave empty for free', 'ecab-taxi-booking-manager'); ?>">
                            </div>
                            <div class="mptbm-stoppages-field">
                                <label for="mptbm-stoppage-badge"><?php esc_html_e('Badge', 'ecab-taxi-booking-manager'); ?> <span class="is-optional"><?php esc_html_e('(optional)', 'ecab-taxi-booking-manager'); ?></span></label>
                                <select id="mptbm-stoppage-badge" name="badge">
                                    <option value=""><?php esc_html_e('None', 'ecab-taxi-booking-manager'); ?></option>
                                    <option value="most_popular"><?php esc_html_e('Most popular', 'ecab-taxi-booking-manager'); ?></option>
                                    <option value="recommended"><?php esc_html_e('Recommended', 'ecab-taxi-booking-manager'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="mptbm-stoppages-field mptbm-stoppages-editor-field">
                            <label for="mptbm_stoppage_description"><?php esc_html_e('Description', 'ecab-taxi-booking-manager'); ?></label>
                            <?php
                            wp_editor('', 'mptbm_stoppage_description', [
                                'textarea_name' => 'description',
                                'textarea_rows' => 7,
                                'teeny'         => true,
                                'media_buttons' => false,
                                'quicktags'     => true,
                            ]);
                            ?>
                        </div>

                        <div class="mptbm-stoppages-message" data-stoppage-message role="alert" aria-live="polite"></div>
                        <div class="mptbm-stoppages-modal-actions">
                            <button type="button" class="button" data-stoppage-close><?php esc_html_e('Cancel', 'ecab-taxi-booking-manager'); ?></button>
                            <button type="submit" class="button button-primary" data-stoppage-submit>
                                <span data-stoppage-submit-label><?php esc_html_e('Add stoppage', 'ecab-taxi-booking-manager'); ?></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php
        }

        private function get_stoppage_card(WP_Post $post): string {
            $image_id = (int) get_post_meta($post->ID, 'mptbm_stoppage_image', true);
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
            // Raw minutes for data-duration (repopulates the numeric edit field
            // as-is); $duration_display is the "1 h 30 min" text shown on the card.
            $duration = get_post_meta($post->ID, 'mptbm_stoppage_duration', true);
            $duration_display = MPTBM_Function::format_duration_minutes($duration);
            $price = get_post_meta($post->ID, 'mptbm_stoppage_price', true);
            $badge = get_post_meta($post->ID, 'mptbm_stoppage_badge', true);
            $badge_labels = [
                'most_popular' => esc_html__('Most popular', 'ecab-taxi-booking-manager'),
                'recommended'  => esc_html__('Recommended', 'ecab-taxi-booking-manager'),
            ];

            $gallery_ids = get_post_meta($post->ID, 'mptbm_stoppage_gallery', true);
            $gallery_ids = is_array($gallery_ids) ? array_map('absint', $gallery_ids) : [];
            $gallery_data = array_values(array_filter(array_map(function ($id) {
                $url = wp_get_attachment_image_url($id, 'thumbnail');
                return $url ? [ 'id' => $id, 'url' => $url ] : null;
            }, $gallery_ids)));

            ob_start();
            ?>
            <article class="mptbm-stoppages-card" data-post-id="<?php echo esc_attr($post->ID); ?>"
                data-title="<?php echo esc_attr($post->post_title); ?>"
                data-description="<?php echo esc_attr(get_post_meta($post->ID, 'mptbm_stoppage_description', true)); ?>"
                data-duration="<?php echo esc_attr($duration); ?>"
                data-price="<?php echo esc_attr($price); ?>"
                data-badge="<?php echo esc_attr($badge); ?>"
                data-gallery="<?php echo esc_attr(wp_json_encode($gallery_data)); ?>"
                data-image-id="<?php echo esc_attr($image_id); ?>"
                data-image-url="<?php echo esc_url($image_url); ?>">
                <div class="mptbm-stoppages-card-media" <?php echo $image_url ? 'style="background-image:url(' . esc_url($image_url) . ')"' : ''; ?>>
                    <?php if (!$image_url) : ?>
                        <i class="fas fa-image" aria-hidden="true"></i>
                    <?php endif; ?>
                    <?php if ($badge && isset($badge_labels[$badge])) : ?>
                        <span class="mptbm-stoppages-card-badge is-<?php echo esc_attr($badge); ?>"><?php echo esc_html($badge_labels[$badge]); ?></span>
                    <?php endif; ?>
                    <div class="mptbm-stoppages-card-actions">
                        <button type="button" class="mptbm-stoppages-icon-btn" data-stoppage-edit title="<?php esc_attr_e('Edit stoppage', 'ecab-taxi-booking-manager'); ?>">
                            <i class="fas fa-pen" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="mptbm-stoppages-icon-btn is-danger" data-stoppage-delete title="<?php esc_attr_e('Move to Trash', 'ecab-taxi-booking-manager'); ?>">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="mptbm-stoppages-card-body">
                    <h2><?php echo esc_html($post->post_title); ?></h2>
                    <p class="mptbm-stoppages-card-meta">
                        <?php if ($duration_display) : ?><span><i class="fas fa-clock" aria-hidden="true"></i> <?php echo esc_html($duration_display); ?></span><?php endif; ?>
                        <span class="mptbm-stoppages-card-price"><?php echo $price !== '' ? wp_kses_post(MP_Global_Function::format_price((float) $price)) : esc_html__('Free', 'ecab-taxi-booking-manager'); ?></span>
                    </p>
                </div>
            </article>
            <?php
            return (string) ob_get_clean();
        }

        private function guard_ajax_access(): void {
            if (!current_user_can('manage_mptbm_transportation')) {
                wp_send_json_error(
                    [ 'message' => esc_html__('You do not have permission to manage stoppages.', 'ecab-taxi-booking-manager') ],
                    403
                );
            }
        }

        private function save_stoppage_meta(int $post_id): void {
            // Rich content from the wp_editor field - wp_kses_post (not
            // sanitize_textarea_field) so the allowed post-content tags
            // (bold, links, lists, ...) survive instead of being stripped.
            $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';
            // Stored as a plain number of minutes - MPTBM_Function::format_duration_minutes()
            // turns it into "1 h 30 min" etc. everywhere it's displayed.
            $duration = isset($_POST['duration']) ? absint($_POST['duration']) : 0;
            $price = isset($_POST['price']) ? trim(sanitize_text_field(wp_unslash($_POST['price']))) : '';
            $badge = isset($_POST['badge']) ? sanitize_key(wp_unslash($_POST['badge'])) : '';
            $badge = in_array($badge, [ 'most_popular', 'recommended' ], true) ? $badge : '';
            $image_id = isset($_POST['image_id']) ? absint($_POST['image_id']) : 0;

            update_post_meta($post_id, 'mptbm_stoppage_description', $description);
            update_post_meta($post_id, 'mptbm_stoppage_duration', $duration);
            update_post_meta($post_id, 'mptbm_stoppage_price', ($price !== '' && is_numeric($price)) ? (float) $price : '');
            update_post_meta($post_id, 'mptbm_stoppage_badge', $badge);
            if ($image_id && wp_attachment_is_image($image_id)) {
                update_post_meta($post_id, 'mptbm_stoppage_image', $image_id);
            } else {
                delete_post_meta($post_id, 'mptbm_stoppage_image');
            }

            // Gallery - client posts a JSON array of attachment ids; only ids
            // that are real, existing image attachments survive.
            $gallery_raw = isset($_POST['gallery_ids']) ? json_decode(wp_unslash($_POST['gallery_ids']), true) : [];
            $gallery_ids = is_array($gallery_raw)
                ? array_values(array_unique(array_filter(array_map('absint', $gallery_raw))))
                : [];
            $gallery_ids = array_values(array_filter($gallery_ids, 'wp_attachment_is_image'));
            if ($gallery_ids) {
                update_post_meta($post_id, 'mptbm_stoppage_gallery', $gallery_ids);
            } else {
                delete_post_meta($post_id, 'mptbm_stoppage_gallery');
            }
        }

        public function ajax_add_stoppage(): void {
            check_ajax_referer('mptbm_stoppages_manager_nonce', 'nonce');
            $this->guard_ajax_access();

            $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
            if ($title === '') {
                wp_send_json_error([ 'message' => esc_html__('Enter a name for this stoppage.', 'ecab-taxi-booking-manager') ], 400);
            }

            $post_id = wp_insert_post([
                'post_type'   => self::POST_TYPE,
                'post_title'  => $title,
                'post_status' => 'publish',
            ], true);

            if (is_wp_error($post_id)) {
                wp_send_json_error([ 'message' => $post_id->get_error_message() ], 400);
            }

            $this->save_stoppage_meta((int) $post_id);
            $post = get_post((int) $post_id);

            wp_send_json_success([
                'message' => esc_html__('Stoppage added successfully.', 'ecab-taxi-booking-manager'),
                'card'    => $this->get_stoppage_card($post),
                'postId'  => (int) $post_id,
            ]);
        }

        public function ajax_update_stoppage(): void {
            check_ajax_referer('mptbm_stoppages_manager_nonce', 'nonce');
            $this->guard_ajax_access();

            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            $existing = $post_id ? get_post($post_id) : null;
            if (!$existing || $existing->post_type !== self::POST_TYPE) {
                wp_send_json_error([ 'message' => esc_html__('This stoppage could not be found.', 'ecab-taxi-booking-manager') ], 404);
            }

            $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
            if ($title === '') {
                wp_send_json_error([ 'message' => esc_html__('Enter a name for this stoppage.', 'ecab-taxi-booking-manager') ], 400);
            }

            $result = wp_update_post([ 'ID' => $post_id, 'post_title' => $title ], true);
            if (is_wp_error($result)) {
                wp_send_json_error([ 'message' => $result->get_error_message() ], 400);
            }

            $this->save_stoppage_meta($post_id);
            $post = get_post($post_id);

            wp_send_json_success([
                'message' => esc_html__('Stoppage updated successfully.', 'ecab-taxi-booking-manager'),
                'card'    => $this->get_stoppage_card($post),
                'postId'  => (int) $post_id,
            ]);
        }

        public function ajax_delete_stoppage(): void {
            check_ajax_referer('mptbm_stoppages_manager_nonce', 'nonce');
            $this->guard_ajax_access();

            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            $existing = $post_id ? get_post($post_id) : null;
            if (!$existing || $existing->post_type !== self::POST_TYPE) {
                wp_send_json_error([ 'message' => esc_html__('This stoppage could not be found.', 'ecab-taxi-booking-manager') ], 404);
            }

            $result = wp_trash_post($post_id);
            if (!$result) {
                wp_send_json_error([ 'message' => esc_html__('This stoppage could not be moved to Trash.', 'ecab-taxi-booking-manager') ], 500);
            }

            wp_send_json_success([
                'message' => esc_html__('Stoppage moved to Trash.', 'ecab-taxi-booking-manager'),
                'postId'  => $post_id,
            ]);
        }
    }

    new MPTBM_Stoppages_Manager();
}
