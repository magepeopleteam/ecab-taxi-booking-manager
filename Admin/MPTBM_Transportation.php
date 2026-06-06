<?php
/**
 * Transportation list - modern responsive card/table design.
 * Mirrors the bus/rental fleet list design in the plugin's own indigo theme.
 */
class MPTBM_Transportation
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'mptbm_transportation_lists_menu'));
        add_action('admin_menu', array($this, 'reorder_mptbm_submenu'), 999);
        add_action('admin_post_mptbm_trash_transport', array($this, 'mptbm_trash_transport_callback'));
        add_action('admin_post_mptbm_restore_transport', array($this, 'mptbm_restore_transport_callback'));
        add_action('admin_post_mptbm_delete_transport', array($this, 'mptbm_delete_transport_callback'));
    }

    public function reorder_mptbm_submenu()
    {
        global $submenu;
        $parent = 'edit.php?post_type=mptbm_rent';
        if (isset($submenu[$parent])) {
            $new_order = array();
            foreach ($submenu[$parent] as $item) {
                if (isset($item[2]) && $item[2] === 'mptbm_transportation_lists') {
                    array_unshift($new_order, $item);
                } else {
                    $new_order[] = $item;
                }
            }
            $submenu[$parent] = $new_order;
        }
    }

    public function mptbm_transportation_lists_menu()
    {
        add_submenu_page(
            'edit.php?post_type=mptbm_rent',
            'Transportation Lists',
            'Transportation Lists',
            'manage_options',
            'mptbm_transportation_lists',
            array($this, 'mptbm_transportation_lists_page')
        );
    }

    private function base_url()
    {
        return admin_url('edit.php?post_type=mptbm_rent&page=mptbm_transportation_lists');
    }

    /* ---- Action handlers (nonce protected, redirect back to page) ----- */
    public function mptbm_trash_transport_callback()
    {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$id || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'mptbm_trash_' . $id)) {
            wp_die('Invalid request');
        }
        if (!current_user_can('delete_post', $id)) {
            wp_die('You are not allowed to trash this item.');
        }
        wp_trash_post($id);
        wp_safe_redirect($this->base_url());
        exit;
    }

    public function mptbm_restore_transport_callback()
    {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$id || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'mptbm_restore_' . $id)) {
            wp_die('Invalid request');
        }
        if (!current_user_can('edit_post', $id)) {
            wp_die('You are not allowed to restore this item.');
        }
        wp_untrash_post($id);
        wp_safe_redirect(add_query_arg('mptbm_status', 'trash', $this->base_url()));
        exit;
    }

    public function mptbm_delete_transport_callback()
    {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$id || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'mptbm_delete_' . $id)) {
            wp_die('Invalid request');
        }
        if (!current_user_can('delete_post', $id)) {
            wp_die('You are not allowed to delete this item.');
        }
        wp_delete_post($id, true);
        wp_safe_redirect(add_query_arg('mptbm_status', 'trash', $this->base_url()));
        exit;
    }

    /* ---- Data --------------------------------------------------------- */
    private function get_items($statuses = array('publish', 'draft', 'pending', 'private'))
    {
        $query = new WP_Query(array(
            'post_type'      => 'mptbm_rent',
            'post_status'    => $statuses,
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ));
        $items = array();
        foreach ($query->posts as $post) {
            $pid   = $post->ID;
            $model = '';
            $features = get_post_meta($pid, 'mptbm_features', true);
            if (!empty($features) && is_array($features)) {
                foreach ($features as $feature) {
                    if (isset($feature['label']) && strtolower($feature['label']) === 'model') {
                        $model = $feature['text'] ?? '';
                        break;
                    }
                }
            }
            $items[] = array(
                'id'          => $pid,
                'title'       => get_the_title($pid) ?: __('(no title)', 'ecab-taxi-booking-manager'),
                'location'    => get_post_meta($pid, 'mptbm_location', true),
                'type'        => get_post_meta($pid, 'mptbm_transport_type', true),
                'km'          => get_post_meta($pid, 'mptbm_km_price', true),
                'hourly'      => get_post_meta($pid, 'mptbm_hour_price', true),
                'model'       => $model,
                'status'      => $post->post_status,
                'image'       => get_post_meta($pid, 'feature_image', true) ?: get_the_post_thumbnail_url($pid, 'medium_large'),
                'price_based' => get_post_meta($pid, 'mptbm_price_based', true),
                'author'      => get_the_author_meta('display_name', $post->post_author),
                'edit_link'   => admin_url('admin.php?page=mptbm-rent-edit&post_id=' . $pid),
                'trash_link'  => wp_nonce_url(admin_url('admin-post.php?action=mptbm_trash_transport&id=' . $pid), 'mptbm_trash_' . $pid),
                'restore_link' => wp_nonce_url(admin_url('admin-post.php?action=mptbm_restore_transport&id=' . $pid), 'mptbm_restore_' . $pid),
                'delete_link'  => wp_nonce_url(admin_url('admin-post.php?action=mptbm_delete_transport&id=' . $pid), 'mptbm_delete_' . $pid),
            );
        }

        return $items;
    }

    private static function mptbm_get_current_month_sales_total()
    {
        $start_date = gmdate('Y-m-01 00:00:00');
        $end_date   = gmdate('Y-m-t 23:59:59');
        $query = new WP_Query(array(
            'post_type'      => 'mptbm_booking',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'mptbm_date',
                    'value'   => array($start_date, $end_date),
                    'compare' => 'BETWEEN',
                    'type'    => 'DATETIME',
                ),
            ),
        ));
        $total = 0;
        foreach ($query->posts as $bid) {
            $total += floatval(get_post_meta($bid, 'mptbm_tp', true));
        }

        return $total;
    }

    private function initials($name)
    {
        $name = trim(wp_strip_all_tags((string) $name));
        if ($name === '') {
            return '?';
        }
        $parts = preg_split('/\s+/', $name);
        $first = mb_substr($parts[0], 0, 1);
        $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first . $last);
    }

    private function status_label($status)
    {
        switch ($status) {
            case 'publish':
                return __('Published', 'ecab-taxi-booking-manager');
            case 'draft':
                return __('Draft', 'ecab-taxi-booking-manager');
            case 'pending':
                return __('Pending', 'ecab-taxi-booking-manager');
            case 'private':
                return __('Private', 'ecab-taxi-booking-manager');
            default:
                return ucfirst($status);
        }
    }

    private function money($amount)
    {
        if ($amount === '' || $amount === null) {
            return '';
        }
        if (function_exists('wc_price') && is_numeric($amount)) {
            return wp_strip_all_tags(wc_price($amount));
        }

        return esc_html($amount);
    }

    /* ---- Page --------------------------------------------------------- */
    public function mptbm_transportation_lists_page()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $is_trash = isset($_GET['mptbm_status']) && sanitize_text_field(wp_unslash($_GET['mptbm_status'])) === 'trash';

        $active    = $this->get_items();
        $total     = count($active);
        $published = 0;
        $draft     = 0;
        $types     = array();
        foreach ($active as $it) {
            if ($it['status'] === 'publish') {
                $published++;
            } elseif ($it['status'] === 'draft') {
                $draft++;
            }
            if ($it['type']) {
                $types[$it['type']] = true;
            }
        }
        $revenue = self::mptbm_get_current_month_sales_total();

        $status_counts = wp_count_posts('mptbm_rent');
        $trash         = isset($status_counts->trash) ? (int) $status_counts->trash : 0;

        $items     = $is_trash ? $this->get_items(array('trash')) : $active;
        $base_url  = $this->base_url();
        $trash_url = add_query_arg('mptbm_status', 'trash', $base_url);
        $add_url   = admin_url('admin.php?page=mptbm-rent-edit');
        ?>
        <div class="wrap mptbm-fleet-wrap">
            <div class="mptbm-fleet">

                <div class="mptbm-page-header">
                    <div class="mptbm-page-title"><?php esc_html_e('Transportation', 'ecab-taxi-booking-manager'); ?>
                        <span><?php echo esc_html(sprintf(_n('%d vehicle', '%d vehicles', $total, 'ecab-taxi-booking-manager'), $total)); ?></span>
                    </div>
                    <div class="mptbm-header-actions">
                        <a class="mptbm-add-btn" href="<?php echo esc_url($add_url); ?>">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            <?php esc_html_e('Add New Transportation', 'ecab-taxi-booking-manager'); ?>
                        </a>
                    </div>
                </div>

                <div class="mptbm-stats">
                    <div class="mptbm-stat-card">
                        <div class="mptbm-stat-icon indigo">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 17h14M6 17l1.5-5h9L18 17M7.5 12l1-4h7l1 4"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/></svg>
                        </div>
                        <div><div class="mptbm-stat-num"><?php echo esc_html($total); ?></div><div class="mptbm-stat-label"><?php esc_html_e('Active Fleet', 'ecab-taxi-booking-manager'); ?></div></div>
                    </div>
                    <div class="mptbm-stat-card">
                        <div class="mptbm-stat-icon green">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
                        </div>
                        <div><div class="mptbm-stat-num"><?php echo esc_html($published); ?></div><div class="mptbm-stat-label"><?php esc_html_e('Published', 'ecab-taxi-booking-manager'); ?></div></div>
                    </div>
                    <div class="mptbm-stat-card">
                        <div class="mptbm-stat-icon orange">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        </div>
                        <div><div class="mptbm-stat-num"><?php echo esc_html($draft); ?></div><div class="mptbm-stat-label"><?php esc_html_e('Draft', 'ecab-taxi-booking-manager'); ?></div></div>
                    </div>
                    <div class="mptbm-stat-card">
                        <div class="mptbm-stat-icon blue">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                        </div>
                        <div><div class="mptbm-stat-num"><?php echo esc_html($this->money($revenue) ?: '0'); ?></div><div class="mptbm-stat-label"><?php esc_html_e('Revenue / mo', 'ecab-taxi-booking-manager'); ?></div></div>
                    </div>
                </div>

                <div class="mptbm-filters">
                    <div class="mptbm-tab-pills">
                        <?php if ($is_trash) : ?>
                            <a class="mptbm-tab-pill" href="<?php echo esc_url($base_url); ?>"><?php printf(esc_html__('All (%d)', 'ecab-taxi-booking-manager'), $total); ?></a>
                            <a class="mptbm-tab-pill" href="<?php echo esc_url($base_url); ?>"><?php printf(esc_html__('Published (%d)', 'ecab-taxi-booking-manager'), $published); ?></a>
                            <a class="mptbm-tab-pill" href="<?php echo esc_url($base_url); ?>"><?php printf(esc_html__('Draft (%d)', 'ecab-taxi-booking-manager'), $draft); ?></a>
                            <span class="mptbm-tab-pill mptbm-tab-trash active">
                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                <?php printf(esc_html__('Trash (%d)', 'ecab-taxi-booking-manager'), $trash); ?>
                            </span>
                        <?php else : ?>
                            <button class="mptbm-tab-pill mptbm-filter-pill active" data-status=""><?php printf(esc_html__('All (%d)', 'ecab-taxi-booking-manager'), $total); ?></button>
                            <button class="mptbm-tab-pill mptbm-filter-pill" data-status="publish"><?php printf(esc_html__('Published (%d)', 'ecab-taxi-booking-manager'), $published); ?></button>
                            <button class="mptbm-tab-pill mptbm-filter-pill" data-status="draft"><?php printf(esc_html__('Draft (%d)', 'ecab-taxi-booking-manager'), $draft); ?></button>
                            <a class="mptbm-tab-pill mptbm-tab-trash" href="<?php echo esc_url($trash_url); ?>" title="<?php esc_attr_e('View trashed items', 'ecab-taxi-booking-manager'); ?>">
                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                <?php printf(esc_html__('Trash (%d)', 'ecab-taxi-booking-manager'), $trash); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="mptbm-search-box">
                        <svg class="mptbm-search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" placeholder="<?php esc_attr_e('Search transportation...', 'ecab-taxi-booking-manager'); ?>" id="mptbmSearchInput" autocomplete="off">
                        <button type="button" class="mptbm-search-clear" id="mptbmSearchClear" aria-label="<?php esc_attr_e('Clear search', 'ecab-taxi-booking-manager'); ?>">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <?php if (!empty($types)) : ?>
                        <select class="mptbm-filter-select" id="mptbmTypeFilter">
                            <option value=""><?php esc_html_e('All Types', 'ecab-taxi-booking-manager'); ?></option>
                            <?php foreach (array_keys($types) as $t) : ?>
                                <option value="<?php echo esc_attr($t); ?>"><?php echo esc_html(ucfirst($t)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <div class="mptbm-view-toggle">
                        <button class="mptbm-vtog active" id="mptbmGridBtn" title="<?php esc_attr_e('Grid view', 'ecab-taxi-booking-manager'); ?>">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        </button>
                        <button class="mptbm-vtog" id="mptbmListBtn" title="<?php esc_attr_e('List view', 'ecab-taxi-booking-manager'); ?>">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                        </button>
                    </div>
                </div>

                <?php if (empty($items)) : ?>
                    <div class="mptbm-no-data">
                        <?php if ($is_trash) : ?>
                            <p><?php esc_html_e('Trash is empty.', 'ecab-taxi-booking-manager'); ?></p>
                            <a class="mptbm-classic-link" href="<?php echo esc_url($base_url); ?>"><?php esc_html_e('Back to list', 'ecab-taxi-booking-manager'); ?></a>
                        <?php else : ?>
                            <p><?php esc_html_e('No transportation found yet.', 'ecab-taxi-booking-manager'); ?></p>
                            <a class="mptbm-add-btn" href="<?php echo esc_url($add_url); ?>"><?php esc_html_e('Add your first transportation', 'ecab-taxi-booking-manager'); ?></a>
                        <?php endif; ?>
                    </div>
                <?php else : ?>

                <div class="mptbm-grid" id="mptbmGrid">
                    <?php foreach ($items as $it) : ?>
                        <div class="mptbm-card" data-name="<?php echo esc_attr(strtolower($it['title'] . ' ' . $it['location'] . ' ' . $it['model'])); ?>" data-type="<?php echo esc_attr($it['type']); ?>" data-status="<?php echo esc_attr($it['status']); ?>">
                            <div class="mptbm-thumb">
                                <?php if ($it['image']) : ?>
                                    <img src="<?php echo esc_url($it['image']); ?>" alt="<?php echo esc_attr($it['title']); ?>">
                                <?php else : ?>
                                    <div class="mptbm-thumb-placeholder">
                                        <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M5 17h14M6 17l1.5-5h9L18 17M7.5 12l1-4h7l1 4"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/></svg>
                                    </div>
                                <?php endif; ?>
                                <div class="mptbm-thumb-overlay"></div>
                                <div class="mptbm-thumb-badges">
                                    <?php if ($it['price_based']) : ?><span class="mptbm-thumb-badge type"><?php echo esc_html($it['price_based']); ?></span><?php endif; ?>
                                    <?php if ($it['type']) : ?><span class="mptbm-thumb-badge alt"><?php echo esc_html(ucfirst($it['type'])); ?></span><?php endif; ?>
                                </div>
                                <div class="mptbm-actions-top">
                                    <?php if ($is_trash) : ?>
                                        <a class="mptbm-act-btn restore" href="<?php echo esc_url($it['restore_link']); ?>" title="<?php esc_attr_e('Restore', 'ecab-taxi-booking-manager'); ?>">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12a9 9 0 109-9 9 9 0 00-7 3.3"/><polyline points="3 4 3 8 7 8"/></svg>
                                        </a>
                                        <a class="mptbm-act-btn del" href="<?php echo esc_url($it['delete_link']); ?>" title="<?php esc_attr_e('Delete Permanently', 'ecab-taxi-booking-manager'); ?>" onclick="return confirm('<?php echo esc_js(__('Permanently delete this item? This cannot be undone.', 'ecab-taxi-booking-manager')); ?>');">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                        </a>
                                    <?php else : ?>
                                        <a class="mptbm-act-btn edit" href="<?php echo esc_url($it['edit_link']); ?>" title="<?php esc_attr_e('Edit', 'ecab-taxi-booking-manager'); ?>">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </a>
                                        <a class="mptbm-act-btn del" href="<?php echo esc_url($it['trash_link']); ?>" title="<?php esc_attr_e('Move to Trash', 'ecab-taxi-booking-manager'); ?>" onclick="return confirm('<?php echo esc_js(__('Move this item to Trash?', 'ecab-taxi-booking-manager')); ?>');">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mptbm-body">
                                <?php if ($is_trash) : ?>
                                    <span class="mptbm-name"><?php echo esc_html($it['title']); ?></span>
                                <?php else : ?>
                                    <a class="mptbm-name" href="<?php echo esc_url($it['edit_link']); ?>"><?php echo esc_html($it['title']); ?></a>
                                <?php endif; ?>
                                <?php if ($it['location']) : ?>
                                    <div class="mptbm-location"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg> <?php echo esc_html($it['location']); ?></div>
                                <?php endif; ?>
                                <div class="mptbm-meta-grid">
                                    <div class="mptbm-meta-cell"><span class="mptbm-meta-k"><?php esc_html_e('KM Price', 'ecab-taxi-booking-manager'); ?></span><span class="mptbm-meta-v"><?php echo esc_html($this->money($it['km']) ?: '-'); ?></span></div>
                                    <div class="mptbm-meta-cell"><span class="mptbm-meta-k"><?php esc_html_e('Hourly', 'ecab-taxi-booking-manager'); ?></span><span class="mptbm-meta-v"><?php echo esc_html($this->money($it['hourly']) ?: '-'); ?></span></div>
                                    <div class="mptbm-meta-cell"><span class="mptbm-meta-k"><?php esc_html_e('Model', 'ecab-taxi-booking-manager'); ?></span><span class="mptbm-meta-v"><?php echo esc_html($it['model'] ?: '-'); ?></span></div>
                                </div>
                                <div class="mptbm-footer">
                                    <div class="mptbm-author"><span class="mptbm-author-avatar"><?php echo esc_html($this->initials($it['author'])); ?></span> <?php echo esc_html($it['author']); ?></div>
                                    <span class="mptbm-status-dot status-<?php echo esc_attr($it['status']); ?>"><?php echo esc_html($this->status_label($it['status'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <table class="mptbm-table" id="mptbmTable">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Transportation', 'ecab-taxi-booking-manager'); ?></th>
                            <th><?php esc_html_e('Location', 'ecab-taxi-booking-manager'); ?></th>
                            <th><?php esc_html_e('KM Price', 'ecab-taxi-booking-manager'); ?></th>
                            <th><?php esc_html_e('Hourly', 'ecab-taxi-booking-manager'); ?></th>
                            <th><?php esc_html_e('Status', 'ecab-taxi-booking-manager'); ?></th>
                            <th><?php esc_html_e('Actions', 'ecab-taxi-booking-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it) : ?>
                            <tr class="mptbm-row" data-name="<?php echo esc_attr(strtolower($it['title'] . ' ' . $it['location'] . ' ' . $it['model'])); ?>" data-type="<?php echo esc_attr($it['type']); ?>" data-status="<?php echo esc_attr($it['status']); ?>">
                                <td data-label="<?php esc_attr_e('Name', 'ecab-taxi-booking-manager'); ?>"><?php if ($is_trash) : ?><?php echo esc_html($it['title']); ?><?php else : ?><a href="<?php echo esc_url($it['edit_link']); ?>"><?php echo esc_html($it['title']); ?></a><?php endif; ?></td>
                                <td data-label="<?php esc_attr_e('Location', 'ecab-taxi-booking-manager'); ?>"><?php echo esc_html($it['location'] ?: '-'); ?></td>
                                <td data-label="<?php esc_attr_e('KM Price', 'ecab-taxi-booking-manager'); ?>"><?php echo esc_html($this->money($it['km']) ?: '-'); ?></td>
                                <td data-label="<?php esc_attr_e('Hourly', 'ecab-taxi-booking-manager'); ?>"><?php echo esc_html($this->money($it['hourly']) ?: '-'); ?></td>
                                <td data-label="<?php esc_attr_e('Status', 'ecab-taxi-booking-manager'); ?>"><span class="mptbm-status-dot status-<?php echo esc_attr($it['status']); ?>"><?php echo esc_html($this->status_label($it['status'])); ?></span></td>
                                <td data-label="<?php esc_attr_e('Actions', 'ecab-taxi-booking-manager'); ?>">
                                    <?php if ($is_trash) : ?>
                                        <a class="mptbm-table-edit" href="<?php echo esc_url($it['restore_link']); ?>"><?php esc_html_e('Restore', 'ecab-taxi-booking-manager'); ?></a>
                                        <a class="mptbm-table-del" href="<?php echo esc_url($it['delete_link']); ?>" onclick="return confirm('<?php echo esc_js(__('Permanently delete this item? This cannot be undone.', 'ecab-taxi-booking-manager')); ?>');"><?php esc_html_e('Delete', 'ecab-taxi-booking-manager'); ?></a>
                                    <?php else : ?>
                                        <a class="mptbm-table-edit" href="<?php echo esc_url($it['edit_link']); ?>"><?php esc_html_e('Edit', 'ecab-taxi-booking-manager'); ?></a>
                                        <a class="mptbm-table-del" href="<?php echo esc_url($it['trash_link']); ?>" onclick="return confirm('<?php echo esc_js(__('Move this item to Trash?', 'ecab-taxi-booking-manager')); ?>');"><?php esc_html_e('Trash', 'ecab-taxi-booking-manager'); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="mptbm-empty" id="mptbmEmptyMsg"><?php esc_html_e('No transportation found matching your search.', 'ecab-taxi-booking-manager'); ?></div>

                <div class="mptbm-pagination" id="mptbmPagination">
                    <div class="mptbm-page-info" id="mptbmPageInfo"></div>
                    <div class="mptbm-page-btns" id="mptbmPageBtns"></div>
                </div>

                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

new MPTBM_Transportation();
