<?php
/*
 * Global Routes (mptbm_routes CPT) list management - admin.
 * Each post is one named, fixed-stop route (e.g. "Paris City Tour") that a
 * vehicle can be assigned to, with its own per-vehicle price set on that
 * vehicle's Price Settings tab (see MPTBM_Price_Settings::route_price_item()).
 */
if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('MPTBM_Routes_Manager')) {
    class MPTBM_Routes_Manager {
        const POST_TYPE = 'mptbm_routes';

        public function __construct() {
            add_filter('admin_body_class', [ $this, 'add_body_class' ]);
            add_action('admin_enqueue_scripts', [ $this, 'enqueue_assets' ], 90);
            add_action('admin_notices', [ $this, 'render_screen' ], 20);
            add_action('wp_ajax_mptbm_add_route', [ $this, 'ajax_add_route' ]);
            add_action('wp_ajax_mptbm_update_route', [ $this, 'ajax_update_route' ]);
            add_action('wp_ajax_mptbm_delete_route', [ $this, 'ajax_delete_route' ]);
        }

        // CPT itself is registered centrally in MPTBM_CPT.php (same place as
        // mptbm_stoppages/mptbm_operate_areas) - this class only owns the
        // admin UI/AJAX for managing its posts.

        // Only the edit.php list screen — post.php/post-new.php are left as a
        // fully-functional fallback, same reasoning as MPTBM_Stoppages_Manager.
        private function is_routes_list_screen(): bool {
            if (!is_admin() || !function_exists('get_current_screen')) {
                return false;
            }

            $screen = get_current_screen();
            return $screen && $screen->base === 'edit' && $screen->post_type === self::POST_TYPE;
        }

        public function add_body_class(string $classes): string {
            if ($this->is_routes_list_screen()) {
                $classes .= ' mptbm-routes-screen';
            }

            return $classes;
        }

        public function enqueue_assets(): void {
            if (!$this->is_routes_list_screen()) {
                return;
            }

            $css_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm_routes.css';
            $js_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm_routes.js';

            wp_enqueue_style(
                'mptbm-routes',
                MPTBM_PLUGIN_URL . '/assets/admin/mptbm_routes.css',
                [ 'mptbm-shell' ],
                file_exists($css_path) ? filemtime($css_path) : MPTBM_PLUGIN_VERSION
            );

            // No hard dependency on mptbm_admin_map/leaflet/google here: those handles
            // are only conditionally registered by MPTBM_Dependencies (e.g. never when
            // the map is disabled, or when Google is picked without an API key). Naming
            // an unregistered handle as a dep would silently drop this whole script, so
            // load order is instead ensured by running at a later admin_enqueue_scripts
            // priority (90 vs MPTBM_Dependencies' 80), and the map init below guards with
            // typeof checks exactly like MPTBM_Locations_Manager's identical setup.
            wp_enqueue_script(
                'mptbm-routes',
                MPTBM_PLUGIN_URL . '/assets/admin/mptbm_routes.js',
                [ 'jquery' ],
                file_exists($js_path) ? filemtime($js_path) : MPTBM_PLUGIN_VERSION,
                true
            );

            $map_type = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'openstreetmap');

            wp_localize_script(
                'mptbm-routes',
                'mptbmRoutes',
                [
                    'ajaxUrl'         => admin_url('admin-ajax.php'),
                    'nonce'           => wp_create_nonce('mptbm_routes_manager_nonce'),
                    'addAction'       => 'mptbm_add_route',
                    'updateAction'    => 'mptbm_update_route',
                    'deleteAction'    => 'mptbm_delete_route',
                    'mapType'         => $map_type,
                    'defaultLat'      => (float) MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_latitude', '23.8103'),
                    'defaultLng'      => (float) MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_longitude', '90.4125'),
                    'genericError'    => esc_html__('Something went wrong. Please try again.', 'ecab-taxi-booking-manager'),
                    'requiredName'    => esc_html__('Enter a name for this route.', 'ecab-taxi-booking-manager'),
                    'requiredStops'   => esc_html__('Search and add at least one stop for this route.', 'ecab-taxi-booking-manager'),
                    'addTitle'        => esc_html__('Add Route', 'ecab-taxi-booking-manager'),
                    'addLabel'        => esc_html__('Add route', 'ecab-taxi-booking-manager'),
                    'addingLabel'     => esc_html__('Adding…', 'ecab-taxi-booking-manager'),
                    'editTitle'       => esc_html__('Edit Route', 'ecab-taxi-booking-manager'),
                    'saveLabel'       => esc_html__('Save changes', 'ecab-taxi-booking-manager'),
                    'savingLabel'     => esc_html__('Saving…', 'ecab-taxi-booking-manager'),
                    'confirmDelete'   => esc_html__('Move this route to Trash? Vehicles it is assigned to will stop offering it.', 'ecab-taxi-booking-manager'),
                    'geocodingLabel'  => esc_html__('Locating stops on the map…', 'ecab-taxi-booking-manager'),
                    'stopNotFound'    => esc_html__('Could not locate on the map: ', 'ecab-taxi-booking-manager'),
                    'removeStopLabel' => esc_html__('Remove stop', 'ecab-taxi-booking-manager'),
                ]
            );
        }

        public function render_screen(): void {
            if (!$this->is_routes_list_screen()) {
                return;
            }

            if (!current_user_can('manage_mptbm_transportation')) {
                return;
            }

            $map_type = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'openstreetmap');

            $posts = get_posts([
                'post_type'      => self::POST_TYPE,
                'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
                'numberposts'    => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);
            ?>
            <section class="mptbm-routes-page" aria-labelledby="mptbm-routes-title">
                <header class="mptbm-routes-hero">
                    <div class="mptbm-routes-heading">
                        <span class="mptbm-routes-heading-icon" aria-hidden="true">
                            <i class="fas fa-route"></i>
                        </span>
                        <div>
                            <p class="mptbm-routes-eyebrow"><?php esc_html_e('Fixed Routes', 'ecab-taxi-booking-manager'); ?></p>
                            <h1 id="mptbm-routes-title"><?php esc_html_e('Routes', 'ecab-taxi-booking-manager'); ?></h1>
                            <p><?php esc_html_e('Create named, multi-stop routes once here, then assign each one to any vehicle with its own price on that vehicle\'s Price Settings tab (Fixed Route mode).', 'ecab-taxi-booking-manager'); ?></p>
                        </div>
                    </div>
                    <button type="button" class="button button-primary mptbm-routes-add" data-route-open>
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <?php esc_html_e('Add New Route', 'ecab-taxi-booking-manager'); ?>
                    </button>
                </header>

                <div class="mptbm-routes-summary">
                    <div>
                        <span><?php esc_html_e('Configured routes', 'ecab-taxi-booking-manager'); ?></span>
                        <strong data-routes-count><?php echo esc_html(count($posts)); ?></strong>
                    </div>
                    <p><?php esc_html_e('A route\'s stops only control the map preview - price is set per vehicle when you assign the route to it.', 'ecab-taxi-booking-manager'); ?></p>
                    <?php if ($posts) : ?>
                        <label class="mptbm-routes-search">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="search" data-routes-search placeholder="<?php esc_attr_e('Search routes by name…', 'ecab-taxi-booking-manager'); ?>" aria-label="<?php esc_attr_e('Search routes by name', 'ecab-taxi-booking-manager'); ?>">
                        </label>
                    <?php endif; ?>
                </div>

                <div class="mptbm-routes-grid" data-routes-grid>
                    <?php foreach ($posts as $post) : ?>
                        <?php echo $this->get_route_card($post); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>

                <div class="mptbm-routes-empty<?php echo $posts ? ' is-hidden' : ''; ?>" data-routes-empty>
                    <span aria-hidden="true"><i class="fas fa-route"></i></span>
                    <h2><?php esc_html_e('No routes yet', 'ecab-taxi-booking-manager'); ?></h2>
                    <p><?php esc_html_e('Add your first route so vehicles can start offering it.', 'ecab-taxi-booking-manager'); ?></p>
                    <button type="button" class="button button-primary" data-route-open>
                        <?php esc_html_e('Add First Route', 'ecab-taxi-booking-manager'); ?>
                    </button>
                </div>

                <div class="mptbm-routes-empty is-hidden" data-routes-search-empty>
                    <span aria-hidden="true"><i class="fas fa-search"></i></span>
                    <h2><?php esc_html_e('No matching routes', 'ecab-taxi-booking-manager'); ?></h2>
                    <p><?php esc_html_e('Try a different search term.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
            </section>

            <div class="mptbm-routes-modal" data-route-modal aria-hidden="true">
                <div class="mptbm-routes-modal-backdrop" data-route-close></div>
                <div class="mptbm-routes-dialog" role="dialog" aria-modal="true" aria-labelledby="mptbm-routes-modal-title">
                    <button type="button" class="mptbm-routes-modal-close" data-route-close aria-label="<?php esc_attr_e('Close dialog', 'ecab-taxi-booking-manager'); ?>">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                    <div class="mptbm-routes-dialog-head">
                        <h2 id="mptbm-routes-modal-title" data-route-title><?php esc_html_e('Add Route', 'ecab-taxi-booking-manager'); ?></h2>
                        <p><?php esc_html_e('A named, ordered list of stops - shown as a map preview when a customer picks this route.', 'ecab-taxi-booking-manager'); ?></p>
                    </div>

                    <form data-route-form novalidate>
                        <?php wp_nonce_field('mptbm_routes_manager_nonce', 'mptbm_routes_manager_nonce'); ?>
                        <input type="hidden" name="post_id" data-route-post-id value="" />

                        <div class="mptbm-routes-field">
                            <label for="mptbm-route-name"><?php esc_html_e('Route Name', 'ecab-taxi-booking-manager'); ?> <span aria-hidden="true">*</span></label>
                            <input type="text" id="mptbm-route-name" name="title" maxlength="200" autocomplete="off" placeholder="<?php esc_attr_e('For example: Paris City Tour', 'ecab-taxi-booking-manager'); ?>" required>
                        </div>

                        <div class="mptbm-routes-field">
                            <label for="mptbm-route-stop-search"><?php esc_html_e('Add Stops', 'ecab-taxi-booking-manager'); ?> <span aria-hidden="true">*</span></label>
                            <input type="text" id="mptbm-route-stop-search" autocomplete="off" placeholder="<?php esc_attr_e('Search a place and pick it to add as the next stop…', 'ecab-taxi-booking-manager'); ?>">
                            <input type="hidden" id="mptbm-route-waypoints" name="waypoints" value="" required>
                            <ul class="mptbm-routes-stop-list" data-route-stop-list></ul>
                            <?php if ($map_type !== 'disable') : ?>
                                <div id="mptbm-route-map" class="mptbm-routes-map"></div>
                            <?php endif; ?>
                            <small><?php esc_html_e('Each place you pick is added as the next stop, in order - drag isn\'t needed, just search them in the order the route should follow.', 'ecab-taxi-booking-manager'); ?></small>
                        </div>

                        <div class="mptbm-routes-message" data-route-message role="alert" aria-live="polite"></div>
                        <div class="mptbm-routes-modal-actions">
                            <button type="button" class="button" data-route-close><?php esc_html_e('Cancel', 'ecab-taxi-booking-manager'); ?></button>
                            <button type="submit" class="button button-primary" data-route-submit>
                                <span data-route-submit-label><?php esc_html_e('Add route', 'ecab-taxi-booking-manager'); ?></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php
        }

        private function get_route_card(WP_Post $post): string {
            $waypoints = get_post_meta($post->ID, 'mptbm_route_waypoints', true);
            $stop_count = $waypoints !== '' ? count(array_filter(array_map('trim', explode(',', $waypoints)))) : 0;

            ob_start();
            ?>
            <article class="mptbm-routes-card" data-post-id="<?php echo esc_attr($post->ID); ?>"
                data-title="<?php echo esc_attr($post->post_title); ?>"
                data-waypoints="<?php echo esc_attr($waypoints); ?>">
                <div class="mptbm-routes-card-body">
                    <div class="mptbm-routes-card-icon" aria-hidden="true"><i class="fas fa-route"></i></div>
                    <div class="mptbm-routes-card-actions">
                        <button type="button" class="mptbm-routes-icon-btn" data-route-edit title="<?php esc_attr_e('Edit route', 'ecab-taxi-booking-manager'); ?>">
                            <i class="fas fa-pen" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="mptbm-routes-icon-btn is-danger" data-route-delete title="<?php esc_attr_e('Move to Trash', 'ecab-taxi-booking-manager'); ?>">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                    <h2><?php echo esc_html($post->post_title); ?></h2>
                    <p class="mptbm-routes-card-stops"><?php echo esc_html($waypoints); ?></p>
                    <p class="mptbm-routes-card-meta">
                        <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?php echo esc_html(sprintf(_n('%d stop', '%d stops', $stop_count, 'ecab-taxi-booking-manager'), $stop_count)); ?></span>
                    </p>
                </div>
            </article>
            <?php
            return (string) ob_get_clean();
        }

        private function guard_ajax_access(): void {
            if (!current_user_can('manage_mptbm_transportation')) {
                wp_send_json_error(
                    [ 'message' => esc_html__('You do not have permission to manage routes.', 'ecab-taxi-booking-manager') ],
                    403
                );
            }
        }

        private function save_route_meta(int $post_id): void {
            $waypoints = isset($_POST['waypoints']) ? sanitize_text_field(wp_unslash($_POST['waypoints'])) : '';
            update_post_meta($post_id, 'mptbm_route_waypoints', $waypoints);
        }

        public function ajax_add_route(): void {
            check_ajax_referer('mptbm_routes_manager_nonce', 'nonce');
            $this->guard_ajax_access();

            $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
            if ($title === '') {
                wp_send_json_error([ 'message' => esc_html__('Enter a name for this route.', 'ecab-taxi-booking-manager') ], 400);
            }

            $waypoints = isset($_POST['waypoints']) ? sanitize_text_field(wp_unslash($_POST['waypoints'])) : '';
            if ($waypoints === '') {
                wp_send_json_error([ 'message' => esc_html__('Enter at least one stop for this route.', 'ecab-taxi-booking-manager') ], 400);
            }

            $post_id = wp_insert_post([
                'post_type'   => self::POST_TYPE,
                'post_title'  => $title,
                'post_status' => 'publish',
            ], true);

            if (is_wp_error($post_id)) {
                wp_send_json_error([ 'message' => $post_id->get_error_message() ], 400);
            }

            $this->save_route_meta((int) $post_id);
            $post = get_post((int) $post_id);

            wp_send_json_success([
                'message' => esc_html__('Route added successfully.', 'ecab-taxi-booking-manager'),
                'card'    => $this->get_route_card($post),
                'postId'  => (int) $post_id,
            ]);
        }

        public function ajax_update_route(): void {
            check_ajax_referer('mptbm_routes_manager_nonce', 'nonce');
            $this->guard_ajax_access();

            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            $existing = $post_id ? get_post($post_id) : null;
            if (!$existing || $existing->post_type !== self::POST_TYPE) {
                wp_send_json_error([ 'message' => esc_html__('This route could not be found.', 'ecab-taxi-booking-manager') ], 404);
            }

            $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
            if ($title === '') {
                wp_send_json_error([ 'message' => esc_html__('Enter a name for this route.', 'ecab-taxi-booking-manager') ], 400);
            }

            $waypoints = isset($_POST['waypoints']) ? sanitize_text_field(wp_unslash($_POST['waypoints'])) : '';
            if ($waypoints === '') {
                wp_send_json_error([ 'message' => esc_html__('Enter at least one stop for this route.', 'ecab-taxi-booking-manager') ], 400);
            }

            $result = wp_update_post([ 'ID' => $post_id, 'post_title' => $title ], true);
            if (is_wp_error($result)) {
                wp_send_json_error([ 'message' => $result->get_error_message() ], 400);
            }

            $this->save_route_meta($post_id);
            $post = get_post($post_id);

            wp_send_json_success([
                'message' => esc_html__('Route updated successfully.', 'ecab-taxi-booking-manager'),
                'card'    => $this->get_route_card($post),
                'postId'  => (int) $post_id,
            ]);
        }

        public function ajax_delete_route(): void {
            check_ajax_referer('mptbm_routes_manager_nonce', 'nonce');
            $this->guard_ajax_access();

            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            $existing = $post_id ? get_post($post_id) : null;
            if (!$existing || $existing->post_type !== self::POST_TYPE) {
                wp_send_json_error([ 'message' => esc_html__('This route could not be found.', 'ecab-taxi-booking-manager') ], 404);
            }

            $result = wp_trash_post($post_id);
            if (!$result) {
                wp_send_json_error([ 'message' => esc_html__('This route could not be moved to Trash.', 'ecab-taxi-booking-manager') ], 500);
            }

            wp_send_json_success([
                'message' => esc_html__('Route moved to Trash.', 'ecab-taxi-booking-manager'),
                'postId'  => $post_id,
            ]);
        }
    }

    new MPTBM_Routes_Manager();
}
