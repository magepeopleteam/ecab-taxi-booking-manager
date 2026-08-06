<?php
/*
 * Modern Extra Services (mptbm_extra_services CPT) list management.
 *
 * @Author MagePeople Team
 * Copyright: mage-people.com
 */
if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('MPTBM_Extra_Services_Manager')) {
    class MPTBM_Extra_Services_Manager {
        const POST_TYPE = 'mptbm_extra_services';

        public function __construct() {
            add_filter('admin_body_class', [ $this, 'add_body_class' ]);
            add_action('admin_enqueue_scripts', [ $this, 'enqueue_assets' ], 90);
            add_action('admin_notices', [ $this, 'render_screen' ], 20);
            add_action('wp_ajax_mptbm_add_extra_service_set', [ $this, 'ajax_add_extra_service_set' ]);
            add_action('wp_ajax_mptbm_update_extra_service_set', [ $this, 'ajax_update_extra_service_set' ]);
            add_action('wp_ajax_mptbm_delete_extra_service_set', [ $this, 'ajax_delete_extra_service_set' ]);
            add_action('wp_ajax_mptbm_get_extra_service_rows', [ $this, 'ajax_get_extra_service_rows' ]);
        }

        // Only the edit.php list screen — post.php/post-new.php (still fully
        // functional as a fallback) are intentionally left untouched, since
        // MPTBM_Admin_Shell::is_native_management_screen() matches both for a
        // CPT (unlike a taxonomy, which only ever has one screen).
        private function is_extra_services_list_screen(): bool {
            if (!is_admin() || !function_exists('get_current_screen')) {
                return false;
            }

            $screen = get_current_screen();
            return $screen && $screen->base === 'edit' && $screen->post_type === self::POST_TYPE;
        }

        public function add_body_class(string $classes): string {
            if ($this->is_extra_services_list_screen()) {
                $classes .= ' mptbm-extra-services-screen';
            }

            return $classes;
        }

        public function enqueue_assets(): void {
            if (!$this->is_extra_services_list_screen()) {
                return;
            }

            $css_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm_extra_services.css';
            $js_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm_extra_services.js';

            wp_enqueue_style(
                'mptbm-extra-services',
                MPTBM_PLUGIN_URL . '/assets/admin/mptbm_extra_services.css',
                [ 'mptbm-shell' ],
                file_exists($css_path) ? filemtime($css_path) : MPTBM_PLUGIN_VERSION
            );
            // jquery-ui-sortable / mp_admin_settings (repeatable-row add/remove,
            // icon popup) and wp_enqueue_media() are already loaded unconditionally
            // on every wp-admin screen by mp_global/MP_Global_File_Load.php, so no
            // dependency needs naming here — same reasoning as the Locations manager
            // (don't hard-depend on a handle whose registration isn't guaranteed).
            wp_enqueue_script(
                'mptbm-extra-services',
                MPTBM_PLUGIN_URL . '/assets/admin/mptbm_extra_services.js',
                [ 'jquery' ],
                file_exists($js_path) ? filemtime($js_path) : MPTBM_PLUGIN_VERSION,
                true
            );

            wp_localize_script(
                'mptbm-extra-services',
                'mptbmExtraServices',
                [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('mptbm_extra_services_manager_nonce'),
                    'addAction' => 'mptbm_add_extra_service_set',
                    'updateAction' => 'mptbm_update_extra_service_set',
                    'deleteAction' => 'mptbm_delete_extra_service_set',
                    'getRowsAction' => 'mptbm_get_extra_service_rows',
                    'genericError' => esc_html__('Something went wrong. Please try again.', 'ecab-taxi-booking-manager'),
                    'requiredName' => esc_html__('Enter a name for this extra services set.', 'ecab-taxi-booking-manager'),
                    'addTitle' => esc_html__('Add Extra Services Set', 'ecab-taxi-booking-manager'),
                    'addSubtitle' => esc_html__('Group service add-ons under one name your customers will see at checkout.', 'ecab-taxi-booking-manager'),
                    'addLabel' => esc_html__('Add set', 'ecab-taxi-booking-manager'),
                    'addingLabel' => esc_html__('Adding…', 'ecab-taxi-booking-manager'),
                    'editTitle' => esc_html__('Edit Extra Services Set', 'ecab-taxi-booking-manager'),
                    'editSubtitle' => esc_html__('Update the name or the individual service items in this set.', 'ecab-taxi-booking-manager'),
                    'saveLabel' => esc_html__('Save changes', 'ecab-taxi-booking-manager'),
                    'savingLabel' => esc_html__('Saving…', 'ecab-taxi-booking-manager'),
                    'confirmDelete' => esc_html__('Move this extra services set to Trash?', 'ecab-taxi-booking-manager'),
                    'rowsLoadError' => esc_html__('Could not load the service items for this set.', 'ecab-taxi-booking-manager'),
                ]
            );
        }

        public function render_screen(): void {
            if (!$this->is_extra_services_list_screen()) {
                return;
            }

            if (!current_user_can('manage_mptbm_transportation')) {
                return;
            }

            $posts = get_posts(
                [
                    'post_type' => self::POST_TYPE,
                    'post_status' => [ 'publish', 'draft', 'pending', 'private' ],
                    'numberposts' => -1,
                    'orderby' => 'title',
                    'order' => 'ASC',
                ]
            );
            ?>
            <section class="mptbm-extra-services-page" aria-labelledby="mptbm-extra-services-title">
                <header class="mptbm-extra-services-hero">
                    <div class="mptbm-extra-services-heading">
                        <span class="mptbm-extra-services-heading-icon" aria-hidden="true">
                            <i class="fas fa-concierge-bell"></i>
                        </span>
                        <div>
                            <p class="mptbm-extra-services-eyebrow"><?php esc_html_e('Add-ons', 'ecab-taxi-booking-manager'); ?></p>
                            <h1 id="mptbm-extra-services-title"><?php esc_html_e('Extra Services', 'ecab-taxi-booking-manager'); ?></h1>
                            <p><?php esc_html_e('Group priced add-ons (child seat, extra luggage, driver...) into named sets vehicles can offer at checkout.', 'ecab-taxi-booking-manager'); ?></p>
                        </div>
                    </div>
                    <button type="button" class="button button-primary mptbm-extra-services-add" data-extra-services-open>
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <?php esc_html_e('Add New Set', 'ecab-taxi-booking-manager'); ?>
                    </button>
                </header>

                <div class="mptbm-extra-services-summary">
                    <div>
                        <span><?php esc_html_e('Configured sets', 'ecab-taxi-booking-manager'); ?></span>
                        <strong data-extra-services-count><?php echo esc_html(count($posts)); ?></strong>
                    </div>
                    <p><?php esc_html_e('A vehicle can use its own custom set or reuse any set configured here.', 'ecab-taxi-booking-manager'); ?></p>
                </div>

                <div class="mptbm-extra-services-grid" data-extra-services-grid>
                    <?php foreach ($posts as $post) : ?>
                        <?php echo $this->get_extra_service_card($post); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>

                <div class="mptbm-extra-services-empty<?php echo $posts ? ' is-hidden' : ''; ?>" data-extra-services-empty>
                    <span aria-hidden="true"><i class="fas fa-concierge-bell"></i></span>
                    <h2><?php esc_html_e('No extra services yet', 'ecab-taxi-booking-manager'); ?></h2>
                    <p><?php esc_html_e('Add your first set to start offering priced add-ons at checkout.', 'ecab-taxi-booking-manager'); ?></p>
                    <button type="button" class="button button-primary" data-extra-services-open>
                        <?php esc_html_e('Add First Set', 'ecab-taxi-booking-manager'); ?>
                    </button>
                </div>
            </section>

            <div class="mptbm-extra-services-modal" data-extra-services-modal aria-hidden="true">
                <div class="mptbm-extra-services-modal-backdrop" data-extra-services-close></div>
                <div class="mptbm-extra-services-dialog" role="dialog" aria-modal="true" aria-labelledby="mptbm-extra-services-modal-title">
                    <button type="button" class="mptbm-extra-services-modal-close" data-extra-services-close aria-label="<?php esc_attr_e('Close dialog', 'ecab-taxi-booking-manager'); ?>">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                    <div class="mptbm-extra-services-dialog-icon" aria-hidden="true">
                        <i class="fas fa-concierge-bell"></i>
                    </div>
                    <h2 id="mptbm-extra-services-modal-title" data-extra-services-title><?php esc_html_e('Add Extra Services Set', 'ecab-taxi-booking-manager'); ?></h2>
                    <p data-extra-services-subtitle><?php esc_html_e('Group service add-ons under one name your customers will see at checkout.', 'ecab-taxi-booking-manager'); ?></p>

                    <form data-extra-services-form novalidate>
                        <?php wp_nonce_field('mptbm_extra_service_nonce', 'mptbm_extra_service_nonce'); ?>
                        <input type="hidden" name="post_id" data-extra-services-post-id value="" />

                        <div class="mptbm-extra-services-field">
                            <label for="mptbm-extra-services-name"><?php esc_html_e('Set name', 'ecab-taxi-booking-manager'); ?> <span aria-hidden="true">*</span></label>
                            <input type="text" id="mptbm-extra-services-name" name="title" maxlength="200" autocomplete="off" placeholder="<?php esc_attr_e('For example: Airport Package Extras', 'ecab-taxi-booking-manager'); ?>" required>
                        </div>

                        <div class="mptbm-extra-services-field">
                            <label><?php esc_html_e('Service items', 'ecab-taxi-booking-manager'); ?></label>
                            <div class="mp_settings_area mptbm-extra-services-rows-area mpStyle">
                                <div class="_ovAuto_mT_xs">
                                    <table class="mptbm-extra-services-rows-table">
                                        <thead>
                                            <tr>
                                                <th><span><?php esc_html_e('Icon', 'ecab-taxi-booking-manager'); ?></span></th>
                                                <th><span><?php esc_html_e('Name', 'ecab-taxi-booking-manager'); ?></span></th>
                                                <th><span><?php esc_html_e('Description', 'ecab-taxi-booking-manager'); ?></span></th>
                                                <th><span><?php esc_html_e('Price', 'ecab-taxi-booking-manager'); ?></span></th>
                                                <th><span><?php esc_html_e('Qty Type', 'ecab-taxi-booking-manager'); ?></span></th>
                                                <th><span><?php esc_html_e('Action', 'ecab-taxi-booking-manager'); ?></span></th>
                                            </tr>
                                        </thead>
                                        <tbody class="mp_sortable_area mp_item_insert" data-extra-services-rows-body></tbody>
                                    </table>
                                </div>
                                <?php MP_Custom_Layout::add_new_button(esc_html__('Add Service Item', 'ecab-taxi-booking-manager')); ?>
                                <?php do_action('add_mp_hidden_table', 'mptbm_extra_service_item'); ?>
                            </div>
                        </div>

                        <div class="mptbm-extra-services-message" data-extra-services-message role="alert" aria-live="polite"></div>
                        <div class="mptbm-extra-services-modal-actions">
                            <button type="button" class="button" data-extra-services-close><?php esc_html_e('Cancel', 'ecab-taxi-booking-manager'); ?></button>
                            <button type="submit" class="button button-primary" data-extra-services-submit>
                                <i class="fas fa-plus" aria-hidden="true"></i>
                                <span data-extra-services-submit-label><?php esc_html_e('Add set', 'ecab-taxi-booking-manager'); ?></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php
        }

        private function get_extra_service_card(WP_Post $post): string {
            $rows = get_post_meta($post->ID, 'mptbm_extra_service_infos', true);
            $rows = is_array($rows) ? $rows : [];
            $count = count($rows);

            $preview_names = [];
            foreach ($rows as $row) {
                if (!empty($row['service_name'])) {
                    $preview_names[] = $row['service_name'];
                }
                if (count($preview_names) >= 3) {
                    break;
                }
            }

            $initial = function_exists('mb_substr') ? mb_substr($post->post_title, 0, 1) : substr($post->post_title, 0, 1);
            $initial = function_exists('mb_strtoupper') ? mb_strtoupper($initial) : strtoupper($initial);
            $initial = $initial !== '' ? $initial : '?';

            ob_start();
            ?>
            <article class="mptbm-extra-services-card" data-post-id="<?php echo esc_attr($post->ID); ?>">
                <div class="mptbm-extra-services-card-top">
                    <span class="mptbm-extra-services-avatar" aria-hidden="true"><?php echo esc_html($initial); ?></span>
                    <div class="mptbm-extra-services-card-actions">
                        <button type="button" class="mptbm-extra-services-icon-btn" data-extra-service-edit title="<?php esc_attr_e('Edit set', 'ecab-taxi-booking-manager'); ?>">
                            <i class="fas fa-pen" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="mptbm-extra-services-icon-btn is-danger" data-extra-service-delete title="<?php esc_attr_e('Move to Trash', 'ecab-taxi-booking-manager'); ?>">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <h2><?php echo esc_html($post->post_title); ?></h2>
                <p class="mptbm-extra-services-count">
                    <?php
                    printf(
                        esc_html(_n('%s service item', '%s service items', $count, 'ecab-taxi-booking-manager')),
                        esc_html(number_format_i18n($count))
                    );
                    ?>
                </p>
                <?php if ($preview_names) : ?>
                    <div class="mptbm-extra-services-chips">
                        <?php foreach ($preview_names as $name) : ?>
                            <span class="mptbm-extra-services-chip"><?php echo esc_html($name); ?></span>
                        <?php endforeach; ?>
                        <?php if ($count > count($preview_names)) : ?>
                            <span class="mptbm-extra-services-chip is-muted">+<?php echo esc_html($count - count($preview_names)); ?></span>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <p class="mptbm-extra-services-empty-hint"><?php esc_html_e('No service items added yet.', 'ecab-taxi-booking-manager'); ?></p>
                <?php endif; ?>
            </article>
            <?php
            return (string) ob_get_clean();
        }

        private function guard_ajax_access(): void {
            if (!current_user_can('manage_mptbm_transportation')) {
                wp_send_json_error(
                    [ 'message' => esc_html__('You do not have permission to manage extra services.', 'ecab-taxi-booking-manager') ],
                    403
                );
            }
        }

        public function ajax_add_extra_service_set(): void {
            check_ajax_referer('mptbm_extra_services_manager_nonce', 'nonce');
            $this->guard_ajax_access();

            $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
            if ($title === '') {
                wp_send_json_error(
                    [ 'message' => esc_html__('Enter a name for this extra services set.', 'ecab-taxi-booking-manager') ],
                    400
                );
            }

            $post_id = wp_insert_post(
                [
                    'post_type' => self::POST_TYPE,
                    'post_title' => $title,
                    'post_status' => 'publish',
                ],
                true
            );

            if (is_wp_error($post_id)) {
                wp_send_json_error([ 'message' => $post_id->get_error_message() ], 400);
            }

            // mptbm_extra_service_infos is saved automatically here: wp_insert_post()
            // just fired save_post, which MPTBM_Extra_Service::save_ex_service_settings()
            // is already hooked to — it reads service_name[]/service_price[]/etc and the
            // mptbm_extra_service_nonce field directly from this same $_POST payload.
            $post = get_post((int) $post_id);
            if (!$post) {
                wp_send_json_error(
                    [ 'message' => esc_html__('The set was added but could not be displayed.', 'ecab-taxi-booking-manager') ],
                    500
                );
            }

            wp_send_json_success(
                [
                    'message' => esc_html__('Extra services set added successfully.', 'ecab-taxi-booking-manager'),
                    'card' => $this->get_extra_service_card($post),
                    'postId' => (int) $post->ID,
                ]
            );
        }

        public function ajax_update_extra_service_set(): void {
            check_ajax_referer('mptbm_extra_services_manager_nonce', 'nonce');
            $this->guard_ajax_access();

            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            $existing = $post_id ? get_post($post_id) : null;
            if (!$existing || $existing->post_type !== self::POST_TYPE) {
                wp_send_json_error(
                    [ 'message' => esc_html__('This extra services set could not be found.', 'ecab-taxi-booking-manager') ],
                    404
                );
            }

            $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
            if ($title === '') {
                wp_send_json_error(
                    [ 'message' => esc_html__('Enter a name for this extra services set.', 'ecab-taxi-booking-manager') ],
                    400
                );
            }

            $result = wp_update_post(
                [
                    'ID' => $post_id,
                    'post_title' => $title,
                ],
                true
            );

            if (is_wp_error($result)) {
                wp_send_json_error([ 'message' => $result->get_error_message() ], 400);
            }

            // Same automatic-save mechanism as add: wp_update_post() re-fires save_post.
            $post = get_post($post_id);
            wp_send_json_success(
                [
                    'message' => esc_html__('Extra services set updated successfully.', 'ecab-taxi-booking-manager'),
                    'card' => $this->get_extra_service_card($post),
                    'postId' => (int) $post->ID,
                ]
            );
        }

        public function ajax_delete_extra_service_set(): void {
            check_ajax_referer('mptbm_extra_services_manager_nonce', 'nonce');
            $this->guard_ajax_access();

            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            $existing = $post_id ? get_post($post_id) : null;
            if (!$existing || $existing->post_type !== self::POST_TYPE) {
                wp_send_json_error(
                    [ 'message' => esc_html__('This extra services set could not be found.', 'ecab-taxi-booking-manager') ],
                    404
                );
            }

            // Trash (not force-delete): this CPT has WordPress's normal trash/undo
            // safety net available, unlike Locations (a taxonomy term has none).
            $result = wp_trash_post($post_id);
            if (!$result) {
                wp_send_json_error(
                    [ 'message' => esc_html__('This extra services set could not be moved to Trash.', 'ecab-taxi-booking-manager') ],
                    500
                );
            }

            wp_send_json_success(
                [
                    'message' => esc_html__('Extra services set moved to Trash.', 'ecab-taxi-booking-manager'),
                    'postId' => $post_id,
                ]
            );
        }

        public function ajax_get_extra_service_rows(): void {
            check_ajax_referer('mptbm_extra_services_manager_nonce', 'nonce');
            $this->guard_ajax_access();

            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            $post = $post_id ? get_post($post_id) : null;
            if (!$post || $post->post_type !== self::POST_TYPE) {
                wp_send_json_error(
                    [ 'message' => esc_html__('This extra services set could not be found.', 'ecab-taxi-booking-manager') ],
                    404
                );
            }

            $rows = get_post_meta($post_id, 'mptbm_extra_service_infos', true);
            $rows = is_array($rows) ? $rows : [];

            ob_start();
            foreach ($rows as $row) {
                do_action('mptbm_extra_service_item', $row);
            }
            $rows_html = (string) ob_get_clean();

            wp_send_json_success(
                [
                    'title' => $post->post_title,
                    'rowsHtml' => $rows_html,
                    'postId' => (int) $post->ID,
                ]
            );
        }
    }

    new MPTBM_Extra_Services_Manager();
}
