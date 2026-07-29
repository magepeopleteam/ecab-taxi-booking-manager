<?php
/*
 * Modern Locations taxonomy management.
 *
 * @Author MagePeople Team
 * Copyright: mage-people.com
 */
if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('MPTBM_Locations_Manager')) {
    class MPTBM_Locations_Manager {
        const TAXONOMY = 'locations';

        public function __construct() {
            add_filter('admin_body_class', [ $this, 'add_body_class' ]);
            add_action('admin_enqueue_scripts', [ $this, 'enqueue_assets' ], 90);
            add_action('admin_notices', [ $this, 'render_screen' ], 20);
            add_action('wp_ajax_mptbm_add_location', [ $this, 'ajax_add_location' ]);
            add_action('wp_ajax_mptbm_update_location', [ $this, 'ajax_update_location' ]);
            add_action('wp_ajax_mptbm_delete_location', [ $this, 'ajax_delete_location' ]);
        }

        private function is_locations_screen(): bool {
            if (!is_admin() || !function_exists('get_current_screen')) {
                return false;
            }

            $screen = get_current_screen();
            return $screen && $screen->taxonomy === self::TAXONOMY;
        }

        public function add_body_class(string $classes): string {
            if ($this->is_locations_screen()) {
                $classes .= ' mptbm-locations-screen';
            }

            return $classes;
        }

        public function enqueue_assets(): void {
            if (!$this->is_locations_screen()) {
                return;
            }

            $css_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm_locations.css';
            $js_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm_locations.js';

            wp_enqueue_style(
                'mptbm-locations',
                MPTBM_PLUGIN_URL . '/assets/admin/mptbm_locations.css',
                [ 'mptbm-shell' ],
                file_exists($css_path) ? filemtime($css_path) : MPTBM_PLUGIN_VERSION
            );
            // No hard dependency on mptbm_admin_map/leaflet/google here: those handles
            // are only conditionally registered by MPTBM_Dependencies (e.g. never when
            // the map is disabled, or when Google is picked without an API key). Naming
            // an unregistered handle as a dep would silently drop this whole script, so
            // load order is instead ensured by running at a later admin_enqueue_scripts
            // priority (90 vs MPTBM_Dependencies' 80), and the map init below guards with
            // typeof checks exactly like MPTBM_Taxonomy_Meta's existing inline map script.
            wp_enqueue_script(
                'mptbm-locations',
                MPTBM_PLUGIN_URL . '/assets/admin/mptbm_locations.js',
                [ 'jquery' ],
                file_exists($js_path) ? filemtime($js_path) : MPTBM_PLUGIN_VERSION,
                true
            );

            $map_type = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'openstreetmap');

            wp_localize_script(
                'mptbm-locations',
                'mptbmLocations',
                [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('mptbm_locations_nonce'),
                    'addAction' => 'mptbm_add_location',
                    'updateAction' => 'mptbm_update_location',
                    'deleteAction' => 'mptbm_delete_location',
                    'mapType' => $map_type,
                    'defaultLat' => (float) MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_latitude', '23.8103'),
                    'defaultLng' => (float) MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_longitude', '90.4125'),
                    'genericError' => esc_html__('Something went wrong. Please try again.', 'ecab-taxi-booking-manager'),
                    'requiredName' => esc_html__('Enter a location name.', 'ecab-taxi-booking-manager'),
                    'addTitle' => esc_html__('Add Location', 'ecab-taxi-booking-manager'),
                    'addSubtitle' => esc_html__('Name the location and, if needed, pin its exact position on the map.', 'ecab-taxi-booking-manager'),
                    'addLabel' => esc_html__('Add location', 'ecab-taxi-booking-manager'),
                    'addingLabel' => esc_html__('Adding…', 'ecab-taxi-booking-manager'),
                    'editTitle' => esc_html__('Edit Location', 'ecab-taxi-booking-manager'),
                    'editSubtitle' => esc_html__('Update the name, description, or map position for this location.', 'ecab-taxi-booking-manager'),
                    'saveLabel' => esc_html__('Save changes', 'ecab-taxi-booking-manager'),
                    'savingLabel' => esc_html__('Saving…', 'ecab-taxi-booking-manager'),
                    'confirmDelete' => esc_html__('Delete this location? This cannot be undone.', 'ecab-taxi-booking-manager'),
                ]
            );
        }

        public function render_screen(): void {
            if (!$this->is_locations_screen()) {
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

            $map_type = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'openstreetmap');
            ?>
            <section class="mptbm-locations-page" aria-labelledby="mptbm-locations-title">
                <header class="mptbm-locations-hero">
                    <div class="mptbm-locations-heading">
                        <span class="mptbm-locations-heading-icon" aria-hidden="true">
                            <i class="fas fa-map-marker-alt"></i>
                        </span>
                        <div>
                            <p class="mptbm-locations-eyebrow"><?php esc_html_e('Route network', 'ecab-taxi-booking-manager'); ?></p>
                            <h1 id="mptbm-locations-title"><?php esc_html_e('Locations', 'ecab-taxi-booking-manager'); ?></h1>
                            <p><?php esc_html_e('Manage the pickup and drop-off locations used across your transportation pricing and bookings.', 'ecab-taxi-booking-manager'); ?></p>
                        </div>
                    </div>
                    <button type="button" class="button button-primary mptbm-locations-add" data-locations-open>
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <?php esc_html_e('Add New Location', 'ecab-taxi-booking-manager'); ?>
                    </button>
                </header>

                <div class="mptbm-locations-summary">
                    <div>
                        <span><?php esc_html_e('Configured locations', 'ecab-taxi-booking-manager'); ?></span>
                        <strong data-locations-count><?php echo esc_html(count($terms)); ?></strong>
                    </div>
                    <p><?php esc_html_e('Locations with coordinates power map-based pricing and route selection at checkout.', 'ecab-taxi-booking-manager'); ?></p>
                </div>

                <div class="mptbm-locations-grid" data-locations-grid>
                    <?php foreach ($terms as $term) : ?>
                        <?php echo $this->get_location_card($term); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>

                <div class="mptbm-locations-empty<?php echo $terms ? ' is-hidden' : ''; ?>" data-locations-empty>
                    <span aria-hidden="true"><i class="fas fa-map-marked-alt"></i></span>
                    <h2><?php esc_html_e('No locations yet', 'ecab-taxi-booking-manager'); ?></h2>
                    <p><?php esc_html_e('Add your first location to enable map-based pricing and route selection.', 'ecab-taxi-booking-manager'); ?></p>
                    <button type="button" class="button button-primary" data-locations-open>
                        <?php esc_html_e('Add First Location', 'ecab-taxi-booking-manager'); ?>
                    </button>
                </div>
            </section>

            <div class="mptbm-locations-modal" data-locations-modal aria-hidden="true">
                <div class="mptbm-locations-modal-backdrop" data-locations-close></div>
                <div class="mptbm-locations-dialog" role="dialog" aria-modal="true" aria-labelledby="mptbm-locations-modal-title">
                    <button type="button" class="mptbm-locations-modal-close" data-locations-close aria-label="<?php esc_attr_e('Close dialog', 'ecab-taxi-booking-manager'); ?>">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                    <div class="mptbm-locations-dialog-icon" aria-hidden="true">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h2 id="mptbm-locations-modal-title" data-locations-title><?php esc_html_e('Add Location', 'ecab-taxi-booking-manager'); ?></h2>
                    <p data-locations-subtitle><?php esc_html_e('Name the location and, if needed, pin its exact position on the map.', 'ecab-taxi-booking-manager'); ?></p>

                    <form data-locations-form novalidate>
                        <input type="hidden" name="term_id" data-locations-term-id value="" />
                        <div class="mptbm-locations-field">
                            <label for="mptbm-locations-name"><?php esc_html_e('Location name', 'ecab-taxi-booking-manager'); ?> <span aria-hidden="true">*</span></label>
                            <input type="text" id="mptbm-locations-name" name="name" maxlength="200" autocomplete="off" placeholder="<?php esc_attr_e('For example: Downtown Airport', 'ecab-taxi-booking-manager'); ?>" required>
                        </div>
                        <div class="mptbm-locations-field">
                            <label for="mptbm-locations-description"><?php esc_html_e('Description', 'ecab-taxi-booking-manager'); ?> <span class="is-optional"><?php esc_html_e('Optional', 'ecab-taxi-booking-manager'); ?></span></label>
                            <textarea id="mptbm-locations-description" name="description" rows="3" maxlength="500" placeholder="<?php esc_attr_e('Add any helpful notes about this location.', 'ecab-taxi-booking-manager'); ?>"></textarea>
                        </div>

                        <?php if ($map_type !== 'disable') : ?>
                        <div class="mptbm-locations-field">
                            <label for="mptbm-locations-geo-search"><?php esc_html_e('Search location on map', 'ecab-taxi-booking-manager'); ?> <span class="is-optional"><?php esc_html_e('Optional', 'ecab-taxi-booking-manager'); ?></span></label>
                            <input type="text" id="mptbm-locations-geo-search" name="mptbm_geo_location_name" autocomplete="off" placeholder="<?php esc_attr_e('Search an address...', 'ecab-taxi-booking-manager'); ?>">
                            <input type="hidden" id="mptbm-locations-geo" name="mptbm_geo_location" value="">
                            <div id="mptbm-locations-map" class="mptbm-locations-map"></div>
                            <small><?php esc_html_e('Drag the pin or search above to set exact coordinates for map-based pricing.', 'ecab-taxi-booking-manager'); ?></small>
                        </div>
                        <?php endif; ?>

                        <div class="mptbm-locations-message" data-locations-message role="alert" aria-live="polite"></div>
                        <div class="mptbm-locations-modal-actions">
                            <button type="button" class="button" data-locations-close><?php esc_html_e('Cancel', 'ecab-taxi-booking-manager'); ?></button>
                            <button type="submit" class="button button-primary" data-locations-submit>
                                <i class="fas fa-plus" aria-hidden="true"></i>
                                <span data-locations-submit-label><?php esc_html_e('Add location', 'ecab-taxi-booking-manager'); ?></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php
        }

        private function get_location_card(WP_Term $term): string {
            $initial = function_exists('mb_substr') ? mb_substr($term->name, 0, 1) : substr($term->name, 0, 1);
            $initial = function_exists('mb_strtoupper') ? mb_strtoupper($initial) : strtoupper($initial);

            $geo = get_term_meta($term->term_id, 'mptbm_geo_location', true);
            $geo_name = get_term_meta($term->term_id, 'mptbm_geo_location_name', true);
            $is_mapped = $geo !== '';

            ob_start();
            ?>
            <article class="mptbm-locations-card" data-term-id="<?php echo esc_attr($term->term_id); ?>" data-name="<?php echo esc_attr($term->name); ?>" data-description="<?php echo esc_attr($term->description); ?>" data-geo="<?php echo esc_attr($geo); ?>" data-geo-name="<?php echo esc_attr($geo_name); ?>">
                <div class="mptbm-locations-card-top">
                    <span class="mptbm-locations-avatar" aria-hidden="true"><?php echo esc_html($initial); ?></span>
                    <div class="mptbm-locations-card-actions">
                        <button type="button" class="mptbm-locations-icon-btn" data-location-edit title="<?php esc_attr_e('Edit location', 'ecab-taxi-booking-manager'); ?>">
                            <i class="fas fa-pen" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="mptbm-locations-icon-btn is-danger" data-location-delete title="<?php esc_attr_e('Delete location', 'ecab-taxi-booking-manager'); ?>">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <h2><?php echo esc_html($term->name); ?></h2>
                <p><?php echo $term->description ? esc_html($term->description) : esc_html__('No description added yet.', 'ecab-taxi-booking-manager'); ?></p>
                <div class="mptbm-locations-geo-line <?php echo $is_mapped ? 'is-mapped' : 'is-unmapped'; ?>">
                    <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                    <span><?php echo $is_mapped ? esc_html($geo_name ?: $geo) : esc_html__('No coordinates set', 'ecab-taxi-booking-manager'); ?></span>
                </div>
                <footer>
                    <code><?php echo esc_html($term->slug); ?></code>
                </footer>
            </article>
            <?php
            return (string) ob_get_clean();
        }

        public function ajax_add_location(): void {
            check_ajax_referer('mptbm_locations_nonce', 'nonce');

            $taxonomy = get_taxonomy(self::TAXONOMY);
            if (!$taxonomy || !current_user_can($taxonomy->cap->manage_terms)) {
                wp_send_json_error(
                    [ 'message' => esc_html__('You do not have permission to add locations.', 'ecab-taxi-booking-manager') ],
                    403
                );
            }

            $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
            $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';

            if ($name === '') {
                wp_send_json_error(
                    [ 'message' => esc_html__('Enter a location name.', 'ecab-taxi-booking-manager') ],
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
                    ? esc_html__('A location with this name already exists.', 'ecab-taxi-booking-manager')
                    : $result->get_error_message();
                wp_send_json_error([ 'message' => $message ], 400);
            }

            // Geo term meta (mptbm_geo_location / mptbm_geo_location_name) is saved
            // automatically here: wp_insert_term() just fired created_locations,
            // which MPTBM_Taxonomy_Meta::save_location_geo_field() is already hooked
            // to, and it reads those same two $_POST keys directly.
            $term = get_term((int) $result['term_id'], self::TAXONOMY);
            if (!$term || is_wp_error($term)) {
                wp_send_json_error(
                    [ 'message' => esc_html__('The location was added but could not be displayed.', 'ecab-taxi-booking-manager') ],
                    500
                );
            }

            wp_send_json_success(
                [
                    'message' => esc_html__('Location added successfully.', 'ecab-taxi-booking-manager'),
                    'card' => $this->get_location_card($term),
                    'termId' => (int) $term->term_id,
                ]
            );
        }

        public function ajax_update_location(): void {
            check_ajax_referer('mptbm_locations_nonce', 'nonce');

            $taxonomy = get_taxonomy(self::TAXONOMY);
            if (!$taxonomy || !current_user_can($taxonomy->cap->edit_terms)) {
                wp_send_json_error(
                    [ 'message' => esc_html__('You do not have permission to edit locations.', 'ecab-taxi-booking-manager') ],
                    403
                );
            }

            $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
            if (!$term_id || !get_term($term_id, self::TAXONOMY)) {
                wp_send_json_error(
                    [ 'message' => esc_html__('This location could not be found.', 'ecab-taxi-booking-manager') ],
                    404
                );
            }

            $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
            $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';

            if ($name === '') {
                wp_send_json_error(
                    [ 'message' => esc_html__('Enter a location name.', 'ecab-taxi-booking-manager') ],
                    400
                );
            }

            $result = wp_update_term(
                $term_id,
                self::TAXONOMY,
                [ 'name' => $name, 'description' => $description ]
            );

            if (is_wp_error($result)) {
                $message = $result->get_error_code() === 'term_exists'
                    ? esc_html__('A location with this name already exists.', 'ecab-taxi-booking-manager')
                    : $result->get_error_message();
                wp_send_json_error([ 'message' => $message ], 400);
            }

            // Same automatic-save mechanism as add: wp_update_term() just fired
            // edited_locations -> MPTBM_Taxonomy_Meta::save_location_geo_field().
            $term = get_term($term_id, self::TAXONOMY);
            if (!$term || is_wp_error($term)) {
                wp_send_json_error(
                    [ 'message' => esc_html__('The location was updated but could not be displayed.', 'ecab-taxi-booking-manager') ],
                    500
                );
            }

            wp_send_json_success(
                [
                    'message' => esc_html__('Location updated successfully.', 'ecab-taxi-booking-manager'),
                    'card' => $this->get_location_card($term),
                    'termId' => (int) $term->term_id,
                ]
            );
        }

        public function ajax_delete_location(): void {
            check_ajax_referer('mptbm_locations_nonce', 'nonce');

            $taxonomy = get_taxonomy(self::TAXONOMY);
            if (!$taxonomy || !current_user_can($taxonomy->cap->delete_terms)) {
                wp_send_json_error(
                    [ 'message' => esc_html__('You do not have permission to delete locations.', 'ecab-taxi-booking-manager') ],
                    403
                );
            }

            $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
            if (!$term_id || !get_term($term_id, self::TAXONOMY)) {
                wp_send_json_error(
                    [ 'message' => esc_html__('This location could not be found.', 'ecab-taxi-booking-manager') ],
                    404
                );
            }

            $result = wp_delete_term($term_id, self::TAXONOMY);
            if (is_wp_error($result) || $result === false) {
                wp_send_json_error(
                    [ 'message' => esc_html__('The location could not be deleted.', 'ecab-taxi-booking-manager') ],
                    500
                );
            }

            wp_send_json_success(
                [
                    'message' => esc_html__('Location deleted.', 'ecab-taxi-booking-manager'),
                    'termId' => $term_id,
                ]
            );
        }
    }

    new MPTBM_Locations_Manager();
}
