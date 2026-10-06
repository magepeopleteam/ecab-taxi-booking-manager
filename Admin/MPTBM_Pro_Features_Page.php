<?php
/*
 * "Pro Features" upgrade teaser page.
 * Only registers its submenu when the Pro plugin is NOT active - mirrors the
 * same detection MPTBM_Booking_List_Free.php uses (class_exists check), since
 * there is no reason to show an upgrade page to a site that already has Pro.
 * Content mirrors the "Pro Features" / "Available Addons" sections already
 * published in readme.txt, so it stays one source of truth for the claims.
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Pro_Features_Page')) {
    class MPTBM_Pro_Features_Page {
        const PRODUCT_URL = 'https://mage-people.com/product/wordpress-taxi-cab-booking-plugin-for-woocommerce/';

        public function __construct() {
            if ($this->is_pro_active()) {
                return;
            }
            add_action('admin_menu', array($this, 'add_menu'));
        }

        private function is_pro_active(): bool {
            return class_exists('MPTBM_Dependencies_Pro') || class_exists('MPTBM_Plugin_Pro');
        }

        public function add_menu(): void {
            $cpt = MPTBM_Function::get_cpt();
            add_submenu_page(
                'edit.php?post_type=' . $cpt,
                esc_html__('Pro Features', 'ecab-taxi-booking-manager'),
                '<span>' . esc_html__('Pro Features', 'ecab-taxi-booking-manager') . '</span>',
                'manage_options',
                'mptbm_pro_features_page',
                array($this, 'render_page')
            );
        }

        private function features(): array {
            return array(
                array(
                    'icon'  => '✈️',
                    'title' => __('Specialized Airport Transfer Shortcodes', 'ecab-taxi-booking-manager'),
                    'desc'  => __('A fixed-route shortcode for set pickup/drop-off points, and a zone-to-point shortcode for pickups from an entire operation area with drop-offs at specific places.', 'ecab-taxi-booking-manager'),
                    'modal' => 'airport-shortcodes',
                ),
                array(
                    'icon'  => '📅',
                    'title' => __('Google Calendar Integration', 'ecab-taxi-booking-manager'),
                    'desc'  => __('Automatically sync booking details to the admin\'s Google Calendar. Customers also receive a link to add the trip to their own personal calendars.', 'ecab-taxi-booking-manager'),
                ),
                array(
                    'icon'  => '📧',
                    'title' => __('Email & PDF Customization', 'ecab-taxi-booking-manager'),
                    'desc'  => __('Receive professional order confirmations and automatically deliver PDF receipts/invoices to customers after successful payments.', 'ecab-taxi-booking-manager'),
                ),
                array(
                    'icon'  => '⏳',
                    'title' => __('Paid Wait Time Option', 'ecab-taxi-booking-manager'),
                    'desc'  => __('Offer extra waiting time for users with automated pricing - perfect for airport pickups where flight delays or luggage collection take extra time.', 'ecab-taxi-booking-manager'),
                ),
                array(
                    'icon'  => '🛒',
                    'title' => __('Advanced Checkout Fields', 'ecab-taxi-booking-manager'),
                    'desc'  => __('Add, edit, or delete personal info fields on checkout, ensuring you collect specific data (like flight numbers) before the ride.', 'ecab-taxi-booking-manager'),
                ),
                array(
                    'icon'  => '🚩',
                    'title' => __('Operation Areas & Geo-Fencing', 'ecab-taxi-booking-manager'),
                    'desc'  => __('Designate specific transport operation areas on the map, and use geo-fencing to set different pricing for intercity and intracity zones.', 'ecab-taxi-booking-manager'),
                ),
                array(
                    'icon'  => '🚍',
                    'title' => __('Driver Management Panel', 'ecab-taxi-booking-manager'),
                    'desc'  => __('A dedicated panel for admins to assign vehicles to drivers. Drivers can track service status, with automated emails notifying all parties of any changes.', 'ecab-taxi-booking-manager'),
                ),
                array(
                    'icon'  => '🔢',
                    'title' => __('Quantity & Interval Booking', 'ecab-taxi-booking-manager'),
                    'desc'  => __('Set the quantity of available transport with specific booking time intervals to prevent overbooking and manage fleet availability.', 'ecab-taxi-booking-manager'),
                ),
                array(
                    'icon'  => '🏷️',
                    'title' => __('Hybrid Pricing Logic', 'ecab-taxi-booking-manager'),
                    'desc'  => __('One shortcode that charges a fixed price within an operation area, manual pricing for specific destinations, and distance/duration pricing everywhere else.', 'ecab-taxi-booking-manager'),
                ),
                array(
                    'icon'  => '📋',
                    'title' => __('Comprehensive Order Management', 'ecab-taxi-booking-manager'),
                    'desc'  => __('An advanced order list view to edit orders, manually change drivers, and manage the full lifecycle of every booking.', 'ecab-taxi-booking-manager'),
                ),
            );
        }

        private function addons(): array {
            return array(
                array(
                    'title' => __('Peak Hour Addon', 'ecab-taxi-booking-manager'),
                    'desc'  => __('Set peak hour pricing by date range and specific time range.', 'ecab-taxi-booking-manager'),
                    'url'   => 'https://mage-people.com/product/taxi-peak-hour-pricing-addon/',
                ),
                array(
                    'title' => __('Distance Based Tier Pricing Addon', 'ecab-taxi-booking-manager'),
                    'desc'  => __('Add distance-based tiered pricing to your rides - automatically adjust fares by trip length.', 'ecab-taxi-booking-manager'),
                    'url'   => 'https://mage-people.com/product/distance-based-tier-pricing-for-e-cab',
                ),
            );
        }

        // Feature "how to configure" modals - keyed by the 'modal' id a
        // features() entry points at. Each step gets a 'color' key (violet,
        // blue, teal, gold) so the numbered badges read as distinct stages.
        private function modals(): array {
            return array(
                'airport-shortcodes' => array(
                    'icon'    => '✈️',
                    'eyebrow' => __('Feature guide', 'ecab-taxi-booking-manager'),
                    'title'   => __('Specialized Airport Transfer Shortcodes', 'ecab-taxi-booking-manager'),
                    'intro'   => __('Charge a different fixed fare depending on direction - for example Airport to Downtown priced differently than Downtown to Airport.', 'ecab-taxi-booking-manager'),
                    'steps'   => array(
                        array(
                            'color' => 'violet',
                            'title' => __('Add the airport as a Location', 'ecab-taxi-booking-manager'),
                            'desc'  => __('Go to Taxi Booking → Locations and add a location named "Airport". This is the fixed point every fare below starts or ends at.', 'ecab-taxi-booking-manager'),
                        ),
                        array(
                            'color' => 'blue',
                            'title' => __('Add one Operation Area per zone you serve', 'ecab-taxi-booking-manager'),
                            'desc'  => __('Go to Taxi Booking → Operation Area and add an entry for every district a passenger can be picked up from or dropped at, e.g. Downtown, City Center.', 'ecab-taxi-booking-manager'),
                        ),
                        array(
                            'color' => 'teal',
                            'title' => __('Switch the vehicle to Fixed Zone pricing', 'ecab-taxi-booking-manager'),
                            'desc'  => __('On the vehicle\'s pricing tab, set "Pricing based on" to Fixed Zone, then add a price row for the airport leg in each direction.', 'ecab-taxi-booking-manager'),
                        ),
                        array(
                            'color' => 'gold',
                            'title' => __('Place one booking form per direction', 'ecab-taxi-booking-manager'),
                            'desc'  => __('Use the two shortcodes below - one where the airport is the pickup, one where it is the destination.', 'ecab-taxi-booking-manager'),
                        ),
                    ),
                    'codes'   => array(
                        array(
                            'label' => __('Airport → anywhere (pickup)', 'ecab-taxi-booking-manager'),
                            'code'  => "[mptbm_booking price_based='fixed_zone']",
                        ),
                        array(
                            'label' => __('Anywhere → Airport (drop-off)', 'ecab-taxi-booking-manager'),
                            'code'  => "[mptbm_booking price_based='fixed_zone_dropoff']",
                        ),
                    ),
                ),
            );
        }

        public function render_page(): void {
            $css_path = MPTBM_PLUGIN_DIR . '/assets/admin/css/pro-features.css';
            $js_path = MPTBM_PLUGIN_DIR . '/assets/admin/pro-features.js';
            wp_enqueue_style('mptbm-guideline-style', MPTBM_PLUGIN_URL . '/assets/admin/css/guideline.css', array(), MPTBM_PLUGIN_VERSION);
            wp_enqueue_style(
                'mptbm-pro-features',
                MPTBM_PLUGIN_URL . '/assets/admin/css/pro-features.css',
                array('mptbm-guideline-style'),
                file_exists($css_path) ? filemtime($css_path) : MPTBM_PLUGIN_VERSION
            );
            wp_enqueue_script(
                'mptbm-pro-features',
                MPTBM_PLUGIN_URL . '/assets/admin/pro-features.js',
                array(),
                file_exists($js_path) ? filemtime($js_path) : MPTBM_PLUGIN_VERSION,
                true
            );

            MPTBM_Admin_Shell::render_shell_open();
            ?>
            <div class="mpStyle mptbm-documentation">
                <div class="mptbm-doc-header mptbm-pro-header">
                    <span class="mptbm-pro-header-decor" aria-hidden="true"></span>
                    <span class="mptbm-doc-header-icon mptbm-pro-header-icon" aria-hidden="true"><i class="fas fa-crown"></i></span>
                    <div class="mptbm-doc-header-content">
                        <p class="mptbm-doc-eyebrow mptbm-pro-eyebrow"><?php esc_html_e('Upgrade', 'ecab-taxi-booking-manager'); ?></p>
                        <h1><?php echo esc_html(MPTBM_Function::get_name()); ?> <?php esc_html_e('Pro', 'ecab-taxi-booking-manager'); ?></h1>
                        <p><?php esc_html_e('Unlock driver management, calendar sync, geo-fencing, custom checkout fields, and more.', 'ecab-taxi-booking-manager'); ?></p>
                    </div>
                    <a class="mptbm-pro-cta" href="<?php echo esc_url(self::PRODUCT_URL); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('View Pro Version', 'ecab-taxi-booking-manager'); ?>
                        <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    </a>
                </div>

                <div class="mptbm-content-wrapper">
                    <div class="mptbm-content-container">
                        <div class="mptbm-doc-section">
                            <div class="mptbm-section-header">
                                <div class="mptbm-section-icon mptbm-pro-section-icon"><span class="dashicons dashicons-star-filled"></span></div>
                                <div>
                                    <h2><?php esc_html_e('Pro Features', 'ecab-taxi-booking-manager'); ?></h2>
                                    <p class="mptbm-pro-section-sub"><?php esc_html_e('Everything below ships in the Pro version - the free plugin stays exactly as it is.', 'ecab-taxi-booking-manager'); ?></p>
                                </div>
                            </div>
                            <div class="mptbm-pro-feature-grid">
                                <?php foreach ($this->features() as $feature) :
                                    $has_modal = !empty($feature['modal']);
                                    $card_class = 'mptbm-pro-feature' . ($has_modal ? ' mptbm-pro-feature-clickable' : '');
                                    ?>
                                    <div
                                        class="<?php echo esc_attr($card_class); ?>"
                                        <?php if ($has_modal) : ?>
                                            role="button" tabindex="0" data-open-modal="<?php echo esc_attr($feature['modal']); ?>"
                                        <?php endif; ?>
                                    >
                                        <span class="mptbm-pro-feature-icon" aria-hidden="true"><?php echo esc_html($feature['icon']); ?></span>
                                        <div class="mptbm-pro-feature-body">
                                            <div class="mptbm-pro-feature-top">
                                                <h3><?php echo esc_html($feature['title']); ?></h3>
                                                <span class="mptbm-pro-badge"><?php esc_html_e('Pro', 'ecab-taxi-booking-manager'); ?></span>
                                            </div>
                                            <p><?php echo esc_html($feature['desc']); ?></p>
                                            <?php if ($has_modal) : ?>
                                                <span class="mptbm-pro-feature-hint"><?php esc_html_e('See how to configure', 'ecab-taxi-booking-manager'); ?> <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mptbm-doc-section">
                            <div class="mptbm-section-header">
                                <div class="mptbm-section-icon mptbm-pro-section-icon"><span class="dashicons dashicons-admin-plugins"></span></div>
                                <div>
                                    <h2><?php esc_html_e('Available Add-ons', 'ecab-taxi-booking-manager'); ?></h2>
                                    <p class="mptbm-pro-section-sub"><?php esc_html_e('Optional paid extensions you can add on top of Pro.', 'ecab-taxi-booking-manager'); ?></p>
                                </div>
                            </div>
                            <div class="mptbm-pro-addon-list">
                                <?php foreach ($this->addons() as $addon) : ?>
                                    <a class="mptbm-pro-addon" href="<?php echo esc_url($addon['url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <div>
                                            <h3><?php echo esc_html($addon['title']); ?></h3>
                                            <p><?php echo esc_html($addon['desc']); ?></p>
                                        </div>
                                        <span class="mptbm-pro-addon-arrow" aria-hidden="true"><i class="fas fa-arrow-up-right-from-square"></i></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mptbm-doc-section">
                            <div class="mptbm-pro-tip">
                                <div class="mptbm-pro-tip-icon">
                                    <span class="dashicons dashicons-info"></span>
                                </div>
                                <div class="mptbm-pro-tip-content">
                                    <h3><?php esc_html_e('Already purchased Pro?', 'ecab-taxi-booking-manager'); ?></h3>
                                    <p><?php esc_html_e('Install and activate the Pro plugin from your purchase - once it is active, this menu disappears automatically.', 'ecab-taxi-booking-manager'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php foreach ($this->modals() as $modal_id => $modal) : ?>
                <div class="mptbm-pro-modal" id="mptbm-pro-modal-<?php echo esc_attr($modal_id); ?>" hidden>
                    <div class="mptbm-pro-modal-backdrop" data-modal-close></div>
                    <div class="mptbm-pro-modal-panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr($modal['title']); ?>">
                        <button type="button" class="mptbm-pro-modal-close" data-modal-close aria-label="<?php esc_attr_e('Close', 'ecab-taxi-booking-manager'); ?>">&times;</button>
                        <div class="mptbm-pro-modal-header">
                            <span class="mptbm-pro-modal-icon" aria-hidden="true"><?php echo esc_html($modal['icon']); ?></span>
                            <div>
                                <p class="mptbm-pro-modal-eyebrow"><?php echo esc_html($modal['eyebrow']); ?></p>
                                <h2><?php echo esc_html($modal['title']); ?></h2>
                            </div>
                        </div>
                        <div class="mptbm-pro-modal-body">
                            <p class="mptbm-pro-modal-intro"><?php echo esc_html($modal['intro']); ?></p>
                            <ol class="mptbm-pro-modal-steps">
                                <?php foreach ($modal['steps'] as $index => $step) : ?>
                                    <li class="mptbm-step-color-<?php echo esc_attr($step['color']); ?>">
                                        <span class="mptbm-pro-modal-step-num"><?php echo esc_html((string) ($index + 1)); ?></span>
                                        <div>
                                            <h4><?php echo esc_html($step['title']); ?></h4>
                                            <p><?php echo esc_html($step['desc']); ?></p>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <?php if (!empty($modal['codes'])) : ?>
                                <div class="mptbm-pro-modal-codes">
                                    <?php foreach ($modal['codes'] as $code_row) : ?>
                                        <div class="mptbm-pro-modal-code-row">
                                            <span class="mptbm-pro-modal-code-label"><?php echo esc_html($code_row['label']); ?></span>
                                            <code class="mptbm-pro-modal-code" tabindex="0"><?php echo esc_html($code_row['code']); ?></code>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php
            MPTBM_Admin_Shell::render_shell_close();
        }
    }
    new MPTBM_Pro_Features_Page();
}
