<?php
/*
 * Modern Service Status taxonomy management.
 *
 * @Author MagePeople Team
 * Copyright: mage-people.com
 */
if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('MPTBM_Service_Status_Manager')) {
    class MPTBM_Service_Status_Manager {
        private const TAXONOMY = 'mptbm_service_status';

        public function __construct() {
            add_filter('admin_body_class', [ $this, 'add_body_class' ]);
            add_action('admin_enqueue_scripts', [ $this, 'enqueue_assets' ]);
            add_action('admin_notices', [ $this, 'render_screen' ], 20);
            add_action('wp_ajax_mptbm_add_service_status', [ $this, 'ajax_add_service_status' ]);
        }

        private function is_service_status_screen(): bool {
            if (!is_admin() || !function_exists('get_current_screen')) {
                return false;
            }

            $screen = get_current_screen();
            return $screen && $screen->taxonomy === self::TAXONOMY;
        }

        public function add_body_class(string $classes): string {
            if ($this->is_service_status_screen()) {
                $classes .= ' mptbm-service-status-screen';
            }

            return $classes;
        }

        public function enqueue_assets(): void {
            if (!$this->is_service_status_screen()) {
                return;
            }

            $css_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm_service_status.css';
            $js_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm_service_status.js';

            wp_enqueue_style(
                'mptbm-service-status',
                MPTBM_PLUGIN_URL . '/assets/admin/mptbm_service_status.css',
                [ 'mptbm-shell' ],
                file_exists($css_path) ? filemtime($css_path) : MPTBM_PLUGIN_VERSION
            );
            wp_enqueue_script(
                'mptbm-service-status',
                MPTBM_PLUGIN_URL . '/assets/admin/mptbm_service_status.js',
                [ 'jquery' ],
                file_exists($js_path) ? filemtime($js_path) : MPTBM_PLUGIN_VERSION,
                true
            );
            wp_localize_script(
                'mptbm-service-status',
                'mptbmServiceStatus',
                [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('mptbm_service_status_nonce'),
                    'action' => 'mptbm_add_service_status',
                    'genericError' => esc_html__('The status could not be added. Please try again.', 'ecab-taxi-booking-manager'),
                    'addingLabel' => esc_html__('Adding status…', 'ecab-taxi-booking-manager'),
                    'addLabel' => esc_html__('Add service status', 'ecab-taxi-booking-manager'),
                ]
            );
        }

        public function render_screen(): void {
            if (!$this->is_service_status_screen()) {
                return;
            }

            $taxonomy = get_taxonomy(self::TAXONOMY);
            if (!$taxonomy || !current_user_can($taxonomy->cap->manage_terms)) {
                return;
            }

            $terms = get_terms(
                [
                    'taxonomy' => self::TAXONOMY,
                    'hide_empty' => false,
                    'orderby' => 'name',
                    'order' => 'ASC',
                ]
            );
            if (is_wp_error($terms)) {
                $terms = [];
            }
            ?>
            <section class="mptbm-service-status-page" aria-labelledby="mptbm-service-status-title">
                <header class="mptbm-service-status-hero">
                    <div class="mptbm-service-status-heading">
                        <span class="mptbm-service-status-heading-icon" aria-hidden="true">
                            <i class="fas fa-tasks"></i>
                        </span>
                        <div>
                            <p class="mptbm-service-status-eyebrow"><?php esc_html_e('Transportation workflow', 'ecab-taxi-booking-manager'); ?></p>
                            <h1 id="mptbm-service-status-title"><?php esc_html_e('Service Status', 'ecab-taxi-booking-manager'); ?></h1>
                            <p><?php esc_html_e('Create the statuses used to organize and track transportation services.', 'ecab-taxi-booking-manager'); ?></p>
                        </div>
                    </div>
                    <button type="button" class="button button-primary mptbm-service-status-add" data-service-status-open>
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <?php esc_html_e('Add New Service Status', 'ecab-taxi-booking-manager'); ?>
                    </button>
                </header>

                <div class="mptbm-service-status-summary">
                    <div>
                        <span><?php esc_html_e('Configured statuses', 'ecab-taxi-booking-manager'); ?></span>
                        <strong data-service-status-count><?php echo esc_html(count($terms)); ?></strong>
                    </div>
                    <p><?php esc_html_e('Statuses are immediately available throughout the transportation workflow.', 'ecab-taxi-booking-manager'); ?></p>
                </div>

                <div class="mptbm-service-status-grid" data-service-status-grid>
                    <?php foreach ($terms as $term) : ?>
                        <?php echo $this->get_status_card($term); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>

                <div class="mptbm-service-status-empty<?php echo $terms ? ' is-hidden' : ''; ?>" data-service-status-empty>
                    <span aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                    <h2><?php esc_html_e('No service statuses yet', 'ecab-taxi-booking-manager'); ?></h2>
                    <p><?php esc_html_e('Add your first status to start organizing the service workflow.', 'ecab-taxi-booking-manager'); ?></p>
                    <button type="button" class="button button-primary" data-service-status-open>
                        <?php esc_html_e('Add First Status', 'ecab-taxi-booking-manager'); ?>
                    </button>
                </div>
            </section>

            <div class="mptbm-service-status-modal" data-service-status-modal aria-hidden="true">
                <div class="mptbm-service-status-modal-backdrop" data-service-status-close></div>
                <div class="mptbm-service-status-dialog" role="dialog" aria-modal="true" aria-labelledby="mptbm-service-status-modal-title">
                    <button type="button" class="mptbm-service-status-modal-close" data-service-status-close aria-label="<?php esc_attr_e('Close dialog', 'ecab-taxi-booking-manager'); ?>">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                    <div class="mptbm-service-status-dialog-icon" aria-hidden="true">
                        <i class="fas fa-plus"></i>
                    </div>
                    <h2 id="mptbm-service-status-modal-title"><?php esc_html_e('Add Service Status', 'ecab-taxi-booking-manager'); ?></h2>
                    <p><?php esc_html_e('Create a clear label your team can use when managing transportation services.', 'ecab-taxi-booking-manager'); ?></p>

                    <form data-service-status-form novalidate>
                        <div class="mptbm-service-status-field">
                            <label for="mptbm-service-status-name"><?php esc_html_e('Status name', 'ecab-taxi-booking-manager'); ?> <span aria-hidden="true">*</span></label>
                            <input type="text" id="mptbm-service-status-name" name="name" maxlength="100" autocomplete="off" placeholder="<?php esc_attr_e('For example: Awaiting Driver', 'ecab-taxi-booking-manager'); ?>" required>
                            <small><?php esc_html_e('Use a short, recognizable workflow label.', 'ecab-taxi-booking-manager'); ?></small>
                        </div>
                        <div class="mptbm-service-status-field">
                            <label for="mptbm-service-status-description"><?php esc_html_e('Description', 'ecab-taxi-booking-manager'); ?> <span class="is-optional"><?php esc_html_e('Optional', 'ecab-taxi-booking-manager'); ?></span></label>
                            <textarea id="mptbm-service-status-description" name="description" rows="4" maxlength="500" placeholder="<?php esc_attr_e('Explain when this status should be used.', 'ecab-taxi-booking-manager'); ?>"></textarea>
                        </div>
                        <div class="mptbm-service-status-message" data-service-status-message role="alert" aria-live="polite"></div>
                        <div class="mptbm-service-status-modal-actions">
                            <button type="button" class="button" data-service-status-close><?php esc_html_e('Cancel', 'ecab-taxi-booking-manager'); ?></button>
                            <button type="submit" class="button button-primary" data-service-status-submit>
                                <i class="fas fa-plus" aria-hidden="true"></i>
                                <span><?php esc_html_e('Add service status', 'ecab-taxi-booking-manager'); ?></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php
        }

        private function get_status_card(WP_Term $term): string {
            $initial = function_exists('mb_substr') ? mb_substr($term->name, 0, 1) : substr($term->name, 0, 1);
            $tone = ((int) $term->term_id % 6) + 1;

            ob_start();
            ?>
            <article class="mptbm-service-status-card is-tone-<?php echo esc_attr($tone); ?>" data-term-id="<?php echo esc_attr($term->term_id); ?>">
                <div class="mptbm-service-status-card-top">
                    <span class="mptbm-service-status-avatar" aria-hidden="true"><?php echo esc_html(strtoupper($initial)); ?></span>
                    <span class="mptbm-service-status-live">
                        <i aria-hidden="true"></i>
                        <?php esc_html_e('Active', 'ecab-taxi-booking-manager'); ?>
                    </span>
                </div>
                <h2><?php echo esc_html($term->name); ?></h2>
                <p><?php echo $term->description ? esc_html($term->description) : esc_html__('Ready to use across your transportation workflow.', 'ecab-taxi-booking-manager'); ?></p>
                <footer>
                    <span>
                        <i class="fas fa-car-side" aria-hidden="true"></i>
                        <?php
                        printf(
                            esc_html(_n('%s transportation', '%s transportations', (int) $term->count, 'ecab-taxi-booking-manager')),
                            esc_html(number_format_i18n($term->count))
                        );
                        ?>
                    </span>
                    <code><?php echo esc_html($term->slug); ?></code>
                </footer>
            </article>
            <?php
            return (string) ob_get_clean();
        }

        public function ajax_add_service_status(): void {
            check_ajax_referer('mptbm_service_status_nonce', 'nonce');

            $taxonomy = get_taxonomy(self::TAXONOMY);
            if (!$taxonomy || !current_user_can($taxonomy->cap->manage_terms)) {
                wp_send_json_error(
                    [ 'message' => esc_html__('You do not have permission to add service statuses.', 'ecab-taxi-booking-manager') ],
                    403
                );
            }

            $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
            $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';

            if ($name === '') {
                wp_send_json_error(
                    [ 'message' => esc_html__('Enter a service status name.', 'ecab-taxi-booking-manager') ],
                    400
                );
            }

            $result = wp_insert_term(
                $name,
                self::TAXONOMY,
                [ 'description' => $description ]
            );

            if (is_wp_error($result)) {
                $message = $result->get_error_code() === 'term_exists'
                    ? esc_html__('A service status with this name already exists.', 'ecab-taxi-booking-manager')
                    : $result->get_error_message();
                wp_send_json_error([ 'message' => $message ], 400);
            }

            $term = get_term((int) $result['term_id'], self::TAXONOMY);
            if (!$term || is_wp_error($term)) {
                wp_send_json_error(
                    [ 'message' => esc_html__('The status was added but could not be displayed.', 'ecab-taxi-booking-manager') ],
                    500
                );
            }

            wp_send_json_success(
                [
                    'message' => esc_html__('Service status added successfully.', 'ecab-taxi-booking-manager'),
                    'card' => $this->get_status_card($term),
                    'termId' => (int) $term->term_id,
                ]
            );
        }
    }

    new MPTBM_Service_Status_Manager();
}
