<?php
/*
 * @Author 		MagePeople Team
 * Copyright: 	mage-people.com
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Admin_Shell')) {
    class MPTBM_Admin_Shell {

        // WordPress-generated hook suffixes for ecab's 8 non-CPT admin pages
        // (all submenus of edit.php?post_type=mptbm_rent, so WP names each
        // hook "{cpt}_page_{slug}"). "Bookings" is only reachable when the
        // free-tier page is actually registered (see get_menu_items()).
        const SCREEN_IDS = [
            'mptbm_rent_page_mptbm_transportation_lists',
            'mptbm_rent_page_mptbm_analytics_dashboard',
            'mptbm_rent_page_mptbm_api_docs',
            'mptbm_rent_page_mptbm_bookings',
            'mptbm_rent_page_mptbm_wc_checkout_fields',
            'mptbm_rent_page_mptbm_settings_page',
            'mptbm_rent_page_mptbm_status_page',
            'mptbm_rent_page_mptbm_guideline_page',
        ];

        public function __construct() {
            add_action('admin_enqueue_scripts', [ $this, 'enqueue_shell_assets' ]);
            add_action('in_admin_header', [ $this, 'render_edit_screen_chrome' ]);
            add_action('in_admin_header', [ $this, 'render_native_screen_chrome' ]);
            add_filter('wp_editor_settings', [ $this, 'simplify_content_editor_toolbar' ], 10, 2);
            add_action('admin_head', [ $this, 'print_metabox_reveal_style' ]);
            add_filter('admin_body_class', [ $this, 'add_body_class' ]);
            add_action('wp_ajax_mptbm_set_menu_layout_style', [ $this, 'ajax_set_menu_layout_style' ]);
            add_action('wp_ajax_mptbm_ajax_save_transport', [ $this, 'ajax_save_transport' ]);
        }

        // SCREEN_IDS plus whatever add-on plugins register via this filter —
        // same "apply_filters, add-on merges its own entries in" idiom used
        // by get_menu_items() below.
        public static function get_screen_ids(): array {
            return apply_filters('mptbm_shell_screen_ids', self::SCREEN_IDS);
        }

        // Detects whether the current wp-admin screen is one of this
        // plugin's own dashboard-style pages (not the CPT edit screen,
        // which is handled separately by is_metabox_screen()).
        public static function is_plugin_screen(): bool {
            if (!is_admin() || !function_exists('get_current_screen')) {
                return false;
            }
            $screen = get_current_screen();

            return $screen && in_array($screen->id, self::get_screen_ids(), true);
        }

        public function add_body_class($classes) {
            if (self::is_plugin_screen()) {
                // mptbm-admin: generic "hide WP's own chrome" marker, safe on
                // any of our screens. mptbm-admin-shell: only the 8
                // dashboard-style pages that get the full flex shell (the
                // edit screen's sidebar/topbar are a fixed overlay instead).
                $classes .= ' mptbm-admin mptbm-admin-shell';
            } elseif (self::is_metabox_screen()) {
                $classes .= ' mptbm-admin';
            } elseif (self::is_native_management_screen()) {
                $classes .= ' mptbm-admin mptbm-admin-native';
            }

            return $classes;
        }

        public static function get_native_screen_config(): array {
            if (!is_admin() || !function_exists('get_current_screen')) {
                return [];
            }

            $screen = get_current_screen();
            if (!$screen) {
                return [];
            }

            $cpt = MPTBM_Function::get_cpt();
            $base_url = admin_url('edit.php?post_type=' . $cpt);
            $configs = [
                'mptbm_service_status' => [
                    'label' => esc_html__('Service Status', 'ecab-taxi-booking-manager'),
                    'icon' => 'fas fa-tasks',
                    'link' => admin_url('edit-tags.php?taxonomy=mptbm_service_status&post_type=' . $cpt),
                ],
                'locations' => [
                    'label' => esc_html__('Locations', 'ecab-taxi-booking-manager'),
                    'icon' => 'fas fa-map-marker-alt',
                    'link' => admin_url('edit-tags.php?taxonomy=locations&post_type=' . $cpt),
                ],
                'mptbm_extra_services' => [
                    'label' => esc_html__('Extra Services', 'ecab-taxi-booking-manager'),
                    'icon' => 'fas fa-concierge-bell',
                    'link' => admin_url('edit.php?post_type=mptbm_extra_services'),
                ],
                'mptbm_operate_areas' => [
                    'label' => esc_html__('Operation Areas', 'ecab-taxi-booking-manager'),
                    'icon' => 'fas fa-draw-polygon',
                    'link' => admin_url('edit.php?post_type=mptbm_operate_areas'),
                ],
            ];

            $key = '';
            if (!empty($screen->taxonomy) && isset($configs[$screen->taxonomy])) {
                $key = $screen->taxonomy;
            } elseif (!empty($screen->post_type) && isset($configs[$screen->post_type])) {
                $key = $screen->post_type;
            }

            if ($key === '') {
                return [];
            }

            return array_merge(
                [
                    'slug' => $key,
                    'back_link' => $base_url . '&page=mptbm_transportation_lists',
                ],
                $configs[$key]
            );
        }

        public static function is_native_management_screen(): bool {
            return !empty(self::get_native_screen_config());
        }

        // The Transportation sidebar item's own sub-views — mirrors the
        // reference's "Car Rental" taxonomy sub-tabs, using ecab's actual
        // equivalent (the status-filter pills the Transportation Lists page
        // already has).
        public static function get_transportation_submenu_tabs(): array {
            $list_url = admin_url('edit.php?post_type=mptbm_rent&page=mptbm_transportation_lists');

            return [
                [ 'label' => esc_html__('All Transport', 'ecab-taxi-booking-manager'), 'icon' => 'fas fa-list', 'link' => $list_url ],
            ];
        }

        // Sidebar nav items, one place so every wrapped page (and the edit
        // screen) shows identical navigation. "Bookings" only appears when
        // MPTBM_Booking_List_Free actually registered its page (it self-guards
        // off when Pro is active — same class_exists() check it uses).
        public static function get_menu_items(): array {
            $cpt = MPTBM_Function::get_cpt();
            $base_url = admin_url('edit.php?post_type=' . $cpt);
            $items = [];

            $items[] = [
                'slug' => 'mptbm_transportation_lists',
                'label' => esc_html__('Transportation', 'ecab-taxi-booking-manager'),
                'icon' => 'fas fa-taxi',
                'link' => $base_url . '&page=mptbm_transportation_lists',
                'has_submenu' => true,
            ];
            $items[] = [
                'slug' => 'mptbm_service_status',
                'label' => esc_html__('Service Status', 'ecab-taxi-booking-manager'),
                'icon' => 'fas fa-tasks',
                'link' => admin_url('edit-tags.php?taxonomy=mptbm_service_status&post_type=' . $cpt),
            ];
            $items[] = [
                'slug' => 'locations',
                'label' => esc_html__('Locations', 'ecab-taxi-booking-manager'),
                'icon' => 'fas fa-map-marker-alt',
                'link' => admin_url('edit-tags.php?taxonomy=locations&post_type=' . $cpt),
            ];
            $items[] = [
                'slug' => 'mptbm_extra_services',
                'label' => esc_html__('Extra Services', 'ecab-taxi-booking-manager'),
                'icon' => 'fas fa-concierge-bell',
                'link' => admin_url('edit.php?post_type=mptbm_extra_services'),
            ];
            $items[] = [
                'slug' => 'mptbm_operate_areas',
                'label' => esc_html__('Operation Areas', 'ecab-taxi-booking-manager'),
                'icon' => 'fas fa-draw-polygon',
                'link' => admin_url('edit.php?post_type=mptbm_operate_areas'),
            ];
            $items[] = [
                'slug' => 'mptbm_analytics_dashboard',
                'label' => esc_html__('Analytics', 'ecab-taxi-booking-manager'),
                'icon' => 'fas fa-chart-line',
                'link' => $base_url . '&page=mptbm_analytics_dashboard',
            ];
            if (!class_exists('MPTBM_Dependencies_Pro') && !class_exists('MPTBM_Plugin_Pro')) {
                $items[] = [
                    'slug' => 'mptbm_bookings',
                    'label' => esc_html__('Bookings', 'ecab-taxi-booking-manager'),
                    'icon' => 'fas fa-calendar-check',
                    'link' => $base_url . '&page=mptbm_bookings',
                ];
            }
            // Only registered (see MPTBM_Admin.php) when WooCommerce is active —
            // link to it here only when it actually exists, same self-guard
            // pattern as the "Bookings" item above.
            if (MP_Global_Function::check_woocommerce() == 1) {
                $items[] = [
                    'slug' => 'mptbm_wc_checkout_fields',
                    'label' => esc_html__('Checkout Fields', 'ecab-taxi-booking-manager'),
                    'icon' => 'fas fa-credit-card',
                    'link' => $base_url . '&page=mptbm_wc_checkout_fields',
                ];
            }
            $items[] = [
                'slug' => 'mptbm_api_docs',
                'label' => esc_html__('API Documentation', 'ecab-taxi-booking-manager'),
                'icon' => 'fas fa-code',
                'link' => $base_url . '&page=mptbm_api_docs',
            ];

            // Add-on plugins inject their own ['slug','label','icon','link']
            // entries here — same idiom as get_screen_ids() above. Applied
            // before Settings/Status/Guideline so add-on items land right
            // after this plugin's own feature pages.
            $items = apply_filters('mptbm_shell_menu_items', $items);

            $items[] = [
                'slug' => 'mptbm_settings_page',
                'label' => esc_html__('Settings', 'ecab-taxi-booking-manager'),
                'icon' => 'fas fa-sliders-h',
                'link' => $base_url . '&page=mptbm_settings_page',
            ];
            $items[] = [
                'slug' => 'mptbm_status_page',
                'label' => esc_html__('Status', 'ecab-taxi-booking-manager'),
                'icon' => 'fas fa-heartbeat',
                'link' => $base_url . '&page=mptbm_status_page',
            ];
            $items[] = [
                'slug' => 'mptbm_guideline_page',
                'label' => esc_html__('Guideline', 'ecab-taxi-booking-manager'),
                'icon' => 'fas fa-book',
                'link' => $base_url . '&page=mptbm_guideline_page',
            ];

            return $items;
        }

        // Shared by render_shell_open() (flex layout, dashboard pages) and
        // render_edit_screen_chrome() (fixed overlay, CPT edit screen) so
        // both emit identical nav/active-state markup from one place.
        // $active_slug lets the edit screen force "Transportation" active
        // (there's no page query arg to detect it from there).
        public static function render_sidebar_markup(bool $fixed = false, string $active_slug = '') {
            $menu_items = self::get_menu_items();
            $current_page = $active_slug !== '' ? $active_slug : (isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '');
            $fixed_class = $fixed ? ' mptbm-shell-fixed' : '';
            ?>
            <div class="mptbm-shell-sidebar<?php echo esc_attr($fixed_class); ?>">
                <div class="mptbm-shell-sidebar-top">
                    <div class="mptbm-shell-logo">
                        <span class="mptbm-shell-logo-icon"><i class="fas fa-taxi"></i></span>
                        <span class="mptbm-shell-logo-text"><?php echo esc_html(MPTBM_Function::get_name()); ?></span>
                    </div>
                    <?php if (!$fixed) : ?>
                        <a href="#" class="mptbm-shell-fold-trigger" title="<?php esc_attr_e('Collapse menu', 'ecab-taxi-booking-manager'); ?>">
                            <i class="fas fa-bars"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <ul class="mptbm-shell-menu">
                    <?php foreach ($menu_items as $item) :
                        $is_active = ($current_page === $item['slug']);
                        $has_submenu = !empty($item['has_submenu']) && $is_active;
                        $li_class = trim(($is_active ? 'is-active' : '') . ($has_submenu ? ' has-children' : ''));
                        ?>
                        <li class="<?php echo esc_attr($li_class); ?>">
                            <a href="<?php echo esc_url($item['link']); ?>">
                                <i class="<?php echo esc_attr($item['icon']); ?>"></i>
                                <span><?php echo esc_html($item['label']); ?></span>
                                <?php if (!empty($item['has_submenu'])) : ?>
                                    <i class="fas fa-chevron-down mptbm-shell-menu-arrow"></i>
                                <?php endif; ?>
                            </a>
                            <?php if ($has_submenu) : ?>
                                <ul class="mptbm-shell-submenu">
                                    <?php foreach (self::get_transportation_submenu_tabs() as $tab) : ?>
                                        <li>
                                            <a href="<?php echo esc_url($tab['link']); ?>">
                                                <i class="<?php echo esc_attr($tab['icon']); ?>"></i>
                                                <span><?php echo esc_html($tab['label']); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?php echo esc_url(admin_url()); ?>" class="mptbm-shell-back-to-wp">
                    <i class="fab fa-wordpress"></i>
                    <span><?php esc_html_e('Back to WordPress', 'ecab-taxi-booking-manager'); ?></span>
                </a>
            </div>
            <?php
        }

        // Opens the shell for one of the 8 dashboard-style pages: sidebar +
        // topbar + content wrapper. $page_title is accepted but not printed
        // (each page keeps its own existing heading markup untouched).
        public static function render_shell_open(string $page_title = '') {
            $menu_style = self::get_menu_layout_style();
            ?>
            <div class="mptbm-shell side-menu-<?php echo esc_attr($menu_style); ?>">
                <div class="mptbm-shell-content-and-menu">
                    <?php self::render_sidebar_markup(false); ?>
                    <div class="mptbm-shell-content">
                        <div class="mptbm-shell-topbar">
                            <a href="#" class="mptbm-shell-mobile-trigger" title="<?php esc_attr_e('Menu', 'ecab-taxi-booking-manager'); ?>"><i class="fas fa-bars"></i></a>
                            <div class="mptbm-shell-topbar-spacer"></div>
                            <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener" class="mptbm-shell-topbar-link" title="<?php esc_attr_e('View Site', 'ecab-taxi-booking-manager'); ?>">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <div class="mptbm-shell-user-avatar" style="background-image:url('<?php echo esc_url(get_avatar_url(get_current_user_id())); ?>')"></div>
                        </div>
                        <div class="mptbm-shell-body mptbm">
            <?php
        }

        public static function render_shell_close() {
            ?>
                        </div><!-- .mptbm-shell-body -->
                    </div><!-- .mptbm-shell-content -->
                </div><!-- .mptbm-shell-content-and-menu -->
            </div><!-- .mptbm-shell -->
            <?php
        }

        public function ajax_set_menu_layout_style() {
            check_ajax_referer('mptbm_shell_nonce', 'nonce');
            $style = (isset($_POST['style']) && in_array($_POST['style'], [ 'full', 'compact' ], true)) ? sanitize_key($_POST['style']) : 'full';
            update_user_meta(get_current_user_id(), 'mptbm_admin_menu_style', $style);
            wp_send_json_success([ 'style' => $style ]);
        }

        public static function get_menu_layout_style(): string {
            $style = get_user_meta(get_current_user_id(), 'mptbm_admin_menu_style', true);

            return in_array($style, [ 'full', 'compact' ], true) ? $style : 'full';
        }

        /**
         * Save the complete native transportation edit form without reloading.
         *
         * WordPress's edit_post() remains the single save pipeline, so revisions,
         * taxonomies and every existing save_post callback continue to behave the
         * same as a normal Update/Publish request.
         */
        public function ajax_save_transport(): void {
            if (!check_ajax_referer('mptbm_shell_nonce', 'nonce', false)) {
                wp_send_json_error([ 'message' => esc_html__('The save session expired. Refresh the page and try again.', 'ecab-taxi-booking-manager') ], 403);
            }

            $post_id = isset($_POST['post_ID']) ? absint($_POST['post_ID']) : 0;
            $post = $post_id ? get_post($post_id) : null;
            if (!$post || $post->post_type !== MPTBM_Function::get_cpt()) {
                wp_send_json_error([ 'message' => esc_html__('Invalid transportation.', 'ecab-taxi-booking-manager') ], 400);
            }
            if (!current_user_can('edit_post', $post_id)) {
                wp_send_json_error([ 'message' => esc_html__('You are not allowed to edit this transportation.', 'ecab-taxi-booking-manager') ], 403);
            }

            $post_nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
            $settings_nonce = isset($_POST['mptbm_transportation_type_nonce']) ? sanitize_text_field(wp_unslash($_POST['mptbm_transportation_type_nonce'])) : '';
            if (!wp_verify_nonce($post_nonce, 'update-post_' . $post_id)
                || !wp_verify_nonce($settings_nonce, 'mptbm_transportation_type_nonce')) {
                wp_send_json_error([ 'message' => esc_html__('The save session expired. Refresh the page and try again.', 'ecab-taxi-booking-manager') ], 403);
            }

            $desired_status = isset($_POST['desired_status']) ? sanitize_key(wp_unslash($_POST['desired_status'])) : $post->post_status;
            if (!in_array($desired_status, [ 'publish', 'draft', 'pending', 'private' ], true)) {
                wp_send_json_error([ 'message' => esc_html__('Invalid transportation status.', 'ecab-taxi-booking-manager') ], 400);
            }

            // Remove the AJAX routing fields before handing the otherwise-native
            // form payload to WordPress's standard post-edit pipeline.
            unset($_POST['nonce'], $_POST['desired_status']);
            $_POST['action'] = 'editpost';
            $_POST['originalaction'] = 'editpost';
            $_POST['post_status'] = $desired_status;
            unset($_POST['publish'], $_POST['saveasdraft'], $_POST['saveasprivate'], $_POST['pending']);

            // The native Publish box is hidden but still in the form, so its
            // visibility radio ships with every save. Core's edit_post() applies
            // that field BEFORE the button fields and, for 'private', forces
            // post_status to private *and* makes _wp_translate_postdata() skip
            // the 'publish' button entirely - so a Private vehicle could never
            // be taken back to Public until visibility is realigned first.
            if ($desired_status === 'private') {
                $_POST['visibility'] = 'private';
            } elseif ($desired_status === 'publish') {
                $_POST['visibility'] = 'public';
            }

            if ($desired_status === 'publish') {
                $_POST['publish'] = '1';
            } elseif ($desired_status === 'draft') {
                $_POST['saveasdraft'] = '1';
            } elseif ($desired_status === 'private') {
                $_POST['saveasprivate'] = '1';
            } elseif ($desired_status === 'pending') {
                $_POST['pending'] = '1';
            }

            require_once ABSPATH . 'wp-admin/includes/post.php';
            $saved_id = edit_post($_POST);
            if (!$saved_id) {
                wp_send_json_error([ 'message' => esc_html__('The transportation could not be saved.', 'ecab-taxi-booking-manager') ], 500);
            }

            clean_post_cache($saved_id);
            $saved_post = get_post($saved_id);
            $is_published = $saved_post->post_status === 'publish';
            $status_labels = [
                'publish' => esc_html__('Published', 'ecab-taxi-booking-manager'),
                'pending' => esc_html__('Pending', 'ecab-taxi-booking-manager'),
                'private' => esc_html__('Private', 'ecab-taxi-booking-manager'),
                'draft' => esc_html__('Draft', 'ecab-taxi-booking-manager'),
            ];

            wp_send_json_success([
                'postId' => $saved_id,
                'title' => get_the_title($saved_id),
                'status' => $saved_post->post_status,
                'statusLabel' => $status_labels[$saved_post->post_status] ?? ucfirst($saved_post->post_status),
                'buttonLabel' => $is_published || $saved_post->post_status === 'private'
                    ? esc_html__('Update', 'ecab-taxi-booking-manager')
                    : esc_html__('Publish', 'ecab-taxi-booking-manager'),
                'message' => esc_html__('Transportation saved successfully.', 'ecab-taxi-booking-manager'),
                'editUrl' => get_edit_post_link($saved_id, 'raw'),
                'previewUrl' => $is_published ? get_permalink($saved_id) : get_preview_post_link($saved_id),
                'postNonce' => wp_create_nonce('update-post_' . $saved_id),
                'savedAt' => current_time(get_option('time_format')),
            ]);
        }

        // The native Add/Edit Transportation screen (post.php/post-new.php for
        // mptbm_rent). WordPress renders this screen itself, so the topbar
        // chrome is injected via in_admin_header as a fixed overlay rather
        // than wrapping a page we control.
        public static function is_metabox_screen(): bool {
            if (!is_admin() || !function_exists('get_current_screen')) {
                return false;
            }
            $screen = get_current_screen();

            return $screen && $screen->base === 'post' && $screen->post_type === MPTBM_Function::get_cpt();
        }

        // Hides the settings metabox from first paint (inline <style> in
        // <head>) until mptbm-shell.js finishes relocating the native
        // title/content/featured-image into their final positions, so the
        // browser never paints the pre-relocation layout and then snaps to
        // the relocated one a moment later.
        public function print_metabox_reveal_style() {
            if (!self::is_metabox_screen()) {
                return;
            }
            echo '<style id="mptbm-metabox-reveal">#mptbm_rent_settings_panel{visibility:hidden;}</style>';
        }

        // Trims the native content editor (the new "Vehicle Description"
        // field inside the Basic Information card) down to a minimal
        // toolbar — this field is admin-notes-only, not shown on the
        // frontend, so a full kitchen-sink editor is unnecessary.
        public function simplify_content_editor_toolbar($settings, $editor_id) {
            // 'content' would be the native editor's id, but the Basic
            // Information card actually renders this field as its own
            // wp_editor() instance ('mptbm_extra_info_editor'); the
            // never-shown duplicate ('mptbm_rent_description') is included
            // too so both stay consistent if it's ever unhidden.
            $targets = [ 'content', 'mptbm_rent_description', 'mptbm_extra_info_editor' ];
            if (!in_array($editor_id, $targets, true) || !self::is_metabox_screen()) {
                return $settings;
            }

            $settings['media_buttons'] = false;
            $settings['tinymce'] = is_array($settings['tinymce'] ?? null) ? $settings['tinymce'] : [];
            $settings['tinymce']['toolbar1'] = 'bold,italic,bullist,link,alignleft,aligncenter,alignright,alignjustify';
            $settings['tinymce']['toolbar2'] = '';
            $settings['tinymce']['height'] = 200;
            $settings['tinymce']['wp_autoresize_on'] = false;

            return $settings;
        }

        /**
         * Status/visibility choices for the edit screen's split-button menu.
         *
         * WordPress's native Publish box - the only place its Public/Private
         * visibility control lives - is hidden on this screen (see
         * mptbm-shell.css), so without these items an admin had no way to take
         * a vehicle from Private back to Public: the topbar's Update button
         * deliberately preserves the current status. ajax_save_transport()
         * already accepts every status listed here.
         *
         * Publish/Private are omitted for users who cannot publish (core's
         * edit_post() would silently downgrade their request to Pending).
         */
        public static function get_status_menu_items(): array {
            $items = array();
            $post_type = get_post_type_object(MPTBM_Function::get_cpt());
            $can_publish = $post_type && current_user_can($post_type->cap->publish_posts);

            if ($can_publish) {
                $items['publish'] = [
                    'label' => __('Public', 'ecab-taxi-booking-manager'),
                    'desc' => __('Visible & bookable by everyone', 'ecab-taxi-booking-manager'),
                    'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"></circle></svg>',
                ];
                $items['private'] = [
                    'label' => __('Private', 'ecab-taxi-booking-manager'),
                    'desc' => __('Only visible to site admins', 'ecab-taxi-booking-manager'),
                    'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="2"></rect><path d="M7 11V7a5 5 0 0110 0v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>',
                ];
            }

            $items['draft'] = [
                'label' => __('Save Draft', 'ecab-taxi-booking-manager'),
                'desc' => __('Hidden until published', 'ecab-taxi-booking-manager'),
                'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path><path d="M14 2v6h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>',
            ];

            $items = apply_filters('mptbm_shell_status_menu_items', $items);

            // Normalize, and keep the menu in lockstep with the statuses
            // ajax_save_transport() actually accepts — an add-on can't add an
            // item here that the save endpoint would then reject.
            $allowed = [ 'publish', 'private', 'pending', 'draft' ];
            $menu = array();
            foreach ((array) $items as $status => $item) {
                if (!in_array($status, $allowed, true) || !is_array($item) || empty($item['label'])) {
                    continue;
                }
                $menu[$status] = [
                    'label' => (string) $item['label'],
                    'desc' => isset($item['desc']) ? (string) $item['desc'] : '',
                    'icon' => isset($item['icon']) ? (string) $item['icon'] : '',
                ];
            }

            return $menu;
        }

        public function render_edit_screen_chrome(): void {
            if (!self::is_metabox_screen()) {
                return;
            }

            $post = get_post();
            $post_title = $post ? get_the_title($post) : '';
            $list_url = admin_url('admin.php?page=mptbm_transportation_lists');

            // Same status_slug/status_label mapping the old wizard header
            // used, so the pill reuses the existing .mptbm_status_pill CSS.
            switch ($post ? get_post_status($post) : '') {
                case 'publish':
                    $status_slug = 'publish';
                    $status_label = __('Published', 'ecab-taxi-booking-manager');
                    break;
                case 'pending':
                    $status_slug = 'pending';
                    $status_label = __('Pending', 'ecab-taxi-booking-manager');
                    break;
                case 'private':
                    $status_slug = 'private';
                    $status_label = __('Private', 'ecab-taxi-booking-manager');
                    break;
                default:
                    $status_slug = 'draft';
                    $status_label = __('Draft', 'ecab-taxi-booking-manager');
                    break;
            }
            ?>
            <?php self::render_sidebar_markup(true, 'mptbm_transportation_lists'); ?>
            <div class="mptbm-edit-topbar mptbm-shell-fixed" id="mptbm-edit-topbar">
                <a href="<?php echo esc_url($list_url); ?>" class="mptbm-edit-topbar-back">
                    <i class="fas fa-arrow-left"></i>
                    <span><?php esc_html_e('Back to Transportation', 'ecab-taxi-booking-manager'); ?></span>
                </a>
                <div class="mptbm-edit-topbar-title" id="mptbm-edit-topbar-title"><?php echo esc_html($post_title); ?></div>
                <div class="mptbm-edit-topbar-actions">
                    <span class="mptbm_status_pill is-<?php echo esc_attr($status_slug); ?>" id="mptbm-edit-topbar-status"><?php echo esc_html($status_label); ?></span>
                    <button type="button" class="mptbm-btn mptbm-btn-outline mptbm-btn-sm" id="mptbm-edit-topbar-preview">
                        <i class="far fa-eye"></i> <?php esc_html_e('Preview', 'ecab-taxi-booking-manager'); ?>
                    </button>
                    <div class="mptbm-split-publish" id="mptbm-edit-topbar-publish">
                        <button type="button" class="mptbm-split-publish__main" id="mptbm-edit-topbar-update">
                            <?php esc_html_e('Update', 'ecab-taxi-booking-manager'); ?>
                        </button>
                        <button type="button" class="mptbm-split-publish__toggle" id="mptbm-edit-topbar-publish-toggle" aria-expanded="false" aria-haspopup="true" aria-label="<?php esc_attr_e('Toggle publish options', 'ecab-taxi-booking-manager'); ?>">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                        </button>
                        <div class="mptbm-split-publish__menu" role="menu" id="mptbm-edit-topbar-publish-menu" aria-label="<?php esc_attr_e('Status & visibility', 'ecab-taxi-booking-manager'); ?>">
                            <div class="mptbm-split-publish__label" aria-hidden="true"><?php esc_html_e('Status & Visibility', 'ecab-taxi-booking-manager'); ?></div>
                            <?php foreach (self::get_status_menu_items() as $status => $item) : ?>
                                <button type="button"
                                        class="mptbm-split-publish__item mptbm-split-publish__<?php echo esc_attr($status); ?><?php echo $status === $status_slug ? ' is-current' : ''; ?>"
                                        <?php echo $status === 'draft' ? 'id="mptbm-edit-topbar-save-draft"' : ''; ?>
                                        data-status="<?php echo esc_attr($status); ?>"
                                        role="menuitemradio"
                                        aria-checked="<?php echo $status === $status_slug ? 'true' : 'false'; ?>">
                                    <?php echo $item['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG ?>
                                    <span class="mptbm-split-publish__item-text">
                                        <span class="mptbm-split-publish__item-title"><?php echo esc_html($item['label']); ?></span>
                                        <?php if ($item['desc'] !== '') : ?>
                                            <span class="mptbm-split-publish__item-desc"><?php echo esc_html($item['desc']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        public function render_native_screen_chrome(): void {
            $config = self::get_native_screen_config();
            if (empty($config)) {
                return;
            }
            ?>
            <?php self::render_sidebar_markup(true, $config['slug']); ?>
            <div class="mptbm-edit-topbar mptbm-native-topbar">
                <a href="#" class="mptbm-shell-mobile-trigger" title="<?php esc_attr_e('Menu', 'ecab-taxi-booking-manager'); ?>">
                    <i class="fas fa-bars"></i>
                </a>
                <a href="<?php echo esc_url($config['back_link']); ?>" class="mptbm-edit-topbar-back">
                    <i class="fas fa-arrow-left"></i>
                    <span><?php esc_html_e('Back to Transportation', 'ecab-taxi-booking-manager'); ?></span>
                </a>
                <div class="mptbm-edit-topbar-title"><?php echo esc_html($config['label']); ?></div>
                <div class="mptbm-edit-topbar-actions">
                    <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener" class="mptbm-shell-topbar-link" title="<?php esc_attr_e('View Site', 'ecab-taxi-booking-manager'); ?>">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                    <div class="mptbm-shell-user-avatar" style="background-image:url('<?php echo esc_url(get_avatar_url(get_current_user_id())); ?>')"></div>
                </div>
            </div>
            <?php
        }

        public function enqueue_shell_assets() {
            if (!self::is_plugin_screen() && !self::is_metabox_screen() && !self::is_native_management_screen()) {
                return;
            }

            $css_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm-shell.css';
            $js_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm-shell.js';

            wp_enqueue_style('mptbm-shell', MPTBM_PLUGIN_URL . '/assets/admin/mptbm-shell.css', [ 'mptbm_taxi_add_edit' ], file_exists($css_path) ? filemtime($css_path) : MPTBM_PLUGIN_VERSION);
            wp_enqueue_script('mptbm-shell', MPTBM_PLUGIN_URL . '/assets/admin/mptbm-shell.js', [ 'jquery' ], file_exists($js_path) ? filemtime($js_path) : MPTBM_PLUGIN_VERSION, true);

            wp_localize_script('mptbm-shell', 'mptbmShell', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mptbm_shell_nonce'),
                'saving' => esc_html__('Saving…', 'ecab-taxi-booking-manager'),
                'saved' => esc_html__('Saved', 'ecab-taxi-booking-manager'),
                'saveError' => esc_html__('The transportation could not be saved. Please try again.', 'ecab-taxi-booking-manager'),
                'unsavedWarning' => esc_html__('You have unsaved transportation changes.', 'ecab-taxi-booking-manager'),
            ]);
        }
    }
    new MPTBM_Admin_Shell();
}
