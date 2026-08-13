<?php
/*
 * Modern Operation Areas (mptbm_operate_areas CPT) list management.
 *
 * Only the two shape types the classic screen's own map JS can actually draw
 * are supported here (fixed / single area, and the geo-fence pair) — the
 * classic metabox also referenced a 3rd "geo-matched" type in its save
 * handler and in its own template, but that type's dropdown option was never
 * offered by the classic UI itself, has no map-drawing JS behind it, and
 * referenced an always-undefined PHP variable. Deliberately not ported
 * forward (confirmed with the user).
 *
 * @Author MagePeople Team
 * Copyright: mage-people.com
 */
if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('MPTBM_Operation_Areas_Manager')) {
    class MPTBM_Operation_Areas_Manager {
        const POST_TYPE = 'mptbm_operate_areas';

        public function __construct() {
            add_filter('admin_body_class', [ $this, 'add_body_class' ]);
            add_action('admin_enqueue_scripts', [ $this, 'enqueue_assets' ], 90);
            add_action('admin_notices', [ $this, 'render_screen' ], 20);
            add_action('wp_ajax_mptbm_add_operation_area', [ $this, 'ajax_add_operation_area' ]);
            add_action('wp_ajax_mptbm_update_operation_area', [ $this, 'ajax_update_operation_area' ]);
            add_action('wp_ajax_mptbm_delete_operation_area', [ $this, 'ajax_delete_operation_area' ]);
            add_action('wp_ajax_mptbm_get_operation_area_data', [ $this, 'ajax_get_operation_area_data' ]);
        }

        // Only the edit.php list screen — post.php/post-new.php (still fully
        // functional as a fallback) are intentionally left untouched, since
        // MPTBM_Admin_Shell::is_native_management_screen() matches both for a
        // CPT (unlike a taxonomy, which only ever has one screen).
        private function is_operation_areas_list_screen(): bool {
            if (!is_admin() || !function_exists('get_current_screen')) {
                return false;
            }

            $screen = get_current_screen();
            return $screen && $screen->base === 'edit' && $screen->post_type === self::POST_TYPE;
        }

        public function add_body_class(string $classes): string {
            if ($this->is_operation_areas_list_screen()) {
                $classes .= ' mptbm-operation-areas-screen';
            }

            return $classes;
        }

        public function enqueue_assets(): void {
            if (!$this->is_operation_areas_list_screen()) {
                return;
            }

            $css_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm_operation_areas.css';
            $js_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm_operation_areas.js';

            wp_enqueue_style(
                'mptbm-operation-areas',
                MPTBM_PLUGIN_URL . '/assets/admin/mptbm_operation_areas.css',
                [ 'mptbm-shell' ],
                file_exists($css_path) ? filemtime($css_path) : MPTBM_PLUGIN_VERSION
            );
            // No hard dependency on mptbm_admin_map/leaflet/google — those handles
            // are only conditionally registered (never when the map is disabled, or
            // Google is picked without an API key). Priority 90 (after
            // MPTBM_Dependencies' 80) guarantees load order without a fragile
            // dependency name; the JS below defensively checks typeof before using
            // any map global, same as the Locations manager.
            wp_enqueue_script(
                'mptbm-operation-areas',
                MPTBM_PLUGIN_URL . '/assets/admin/mptbm_operation_areas.js',
                [ 'jquery' ],
                file_exists($js_path) ? filemtime($js_path) : MPTBM_PLUGIN_VERSION,
                true
            );

            wp_localize_script(
                'mptbm-operation-areas',
                'mptbmOperationAreas',
                [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('mptbm_operation_areas_manager_nonce'),
                    'addAction' => 'mptbm_add_operation_area',
                    'updateAction' => 'mptbm_update_operation_area',
                    'deleteAction' => 'mptbm_delete_operation_area',
                    'getDataAction' => 'mptbm_get_operation_area_data',
                    'mapType' => MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'openstreetmap'),
                    'defaultLat' => (float) MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_latitude', '23.8103'),
                    'defaultLng' => (float) MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_longitude', '90.4125'),
                    'genericError' => esc_html__('Something went wrong. Please try again.', 'ecab-taxi-booking-manager'),
                    'requiredName' => esc_html__('Enter a name for this operation area.', 'ecab-taxi-booking-manager'),
                    'requiredFixedShape' => esc_html__('Draw the operation area on the map and confirm a starting location before saving.', 'ecab-taxi-booking-manager'),
                    'requiredGeoFenceShape' => esc_html__('Draw both operation areas on the map and confirm both starting locations before saving.', 'ecab-taxi-booking-manager'),
                    'requiredPriceAmount' => esc_html__('Enter a price adjustment amount.', 'ecab-taxi-booking-manager'),
                    'addTitle' => esc_html__('Add Operation Area', 'ecab-taxi-booking-manager'),
                    'addSubtitle' => esc_html__('Define where this pricing rule applies by drawing it on the map.', 'ecab-taxi-booking-manager'),
                    'addLabel' => esc_html__('Add area', 'ecab-taxi-booking-manager'),
                    'addingLabel' => esc_html__('Adding…', 'ecab-taxi-booking-manager'),
                    'editTitle' => esc_html__('Edit Operation Area', 'ecab-taxi-booking-manager'),
                    'editSubtitle' => esc_html__('Update the name, shape, or pricing for this operation area.', 'ecab-taxi-booking-manager'),
                    'saveLabel' => esc_html__('Save changes', 'ecab-taxi-booking-manager'),
                    'savingLabel' => esc_html__('Saving…', 'ecab-taxi-booking-manager'),
                    'confirmDelete' => esc_html__('Move this operation area to Trash?', 'ecab-taxi-booking-manager'),
                    'confirmClear' => esc_html__('Clear this boundary? You can draw it again before saving.', 'ecab-taxi-booking-manager'),
                    'confirmDiscard' => esc_html__('Discard the unsaved changes to this operation area?', 'ecab-taxi-booking-manager'),
                    'dataLoadError' => esc_html__('Could not load this operation area.', 'ecab-taxi-booking-manager'),
                    'mapUnavailable' => esc_html__('The map drawing tools are unavailable. Check the map provider settings and reload this page.', 'ecab-taxi-booking-manager'),
                    'locationNeeded' => esc_html__('Choose a location from the search suggestions.', 'ecab-taxi-booking-manager'),
                    'boundaryNeeded' => esc_html__('Draw a boundary with at least three points.', 'ecab-taxi-booking-manager'),
                    'notReady' => esc_html__('Setup incomplete', 'ecab-taxi-booking-manager'),
                    'readyToSave' => esc_html__('Ready to save', 'ecab-taxi-booking-manager'),
                    'boundaryReady' => esc_html__('Boundary ready', 'ecab-taxi-booking-manager'),
                    'boundaryEmpty' => esc_html__('Boundary not drawn', 'ecab-taxi-booking-manager'),
                    'finishAtStart' => esc_html__('START — click to finish', 'ecab-taxi-booking-manager'),
                    'drawBoundary' => esc_html__('Draw a new boundary', 'ecab-taxi-booking-manager'),
                    'undoPoint' => esc_html__('Undo the last point', 'ecab-taxi-booking-manager'),
                    'nothingToUndo' => esc_html__('There is no point to undo yet. Place a point first, or use Clear to start over.', 'ecab-taxi-booking-manager'),
                    'editBoundary' => esc_html__('Edit boundary points', 'ecab-taxi-booking-manager'),
                    'deleteBoundary' => esc_html__('Delete the boundary', 'ecab-taxi-booking-manager'),
                    'verticesLabel' => esc_html__('points', 'ecab-taxi-booking-manager'),
                    'areaLabel' => esc_html__('Approx. area', 'ecab-taxi-booking-manager'),
                    'completeDetails' => esc_html__('Add a clear area name to continue.', 'ecab-taxi-booking-manager'),
                    'completeBoundary' => esc_html__('Confirm the location and draw the service boundary.', 'ecab-taxi-booking-manager'),
                    'completeBoundaries' => esc_html__('Confirm both locations and draw both boundaries.', 'ecab-taxi-booking-manager'),
                    'completePricing' => esc_html__('Enter a valid route price adjustment.', 'ecab-taxi-booking-manager'),
                    'allComplete' => esc_html__('Everything looks good. This operation area is ready to save.', 'ecab-taxi-booking-manager'),
                ]
            );
        }

        public function render_screen(): void {
            if (!$this->is_operation_areas_list_screen()) {
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

            $map_type = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'openstreetmap');
            ?>
            <section class="mptbm-operation-areas-page" aria-labelledby="mptbm-operation-areas-title">
                <header class="mptbm-operation-areas-hero">
                    <div class="mptbm-operation-areas-heading">
                        <span class="mptbm-operation-areas-heading-icon" aria-hidden="true">
                            <i class="fas fa-draw-polygon"></i>
                        </span>
                        <div>
                            <p class="mptbm-operation-areas-eyebrow"><?php esc_html_e('Map-based pricing', 'ecab-taxi-booking-manager'); ?></p>
                            <h1 id="mptbm-operation-areas-title"><?php esc_html_e('Operation Areas', 'ecab-taxi-booking-manager'); ?></h1>
                            <p><?php esc_html_e('Draw single areas or intercity route pairs on the map to apply location-based price adjustments.', 'ecab-taxi-booking-manager'); ?></p>
                        </div>
                    </div>
                    <button type="button" class="button button-primary mptbm-operation-areas-add" data-operation-areas-open>
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <?php esc_html_e('Add New Area', 'ecab-taxi-booking-manager'); ?>
                    </button>
                </header>

                <div class="mptbm-operation-areas-summary">
                    <div>
                        <span><?php esc_html_e('Configured areas', 'ecab-taxi-booking-manager'); ?></span>
                        <strong data-operation-areas-count><?php echo esc_html(count($posts)); ?></strong>
                    </div>
                    <p><?php esc_html_e('Areas with a drawn shape are used immediately for map-based pricing.', 'ecab-taxi-booking-manager'); ?></p>
                </div>

                <div class="mptbm-operation-areas-grid" data-operation-areas-grid>
                    <?php foreach ($posts as $post) : ?>
                        <?php echo $this->get_operation_area_card($post); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>

                <div class="mptbm-operation-areas-empty<?php echo $posts ? ' is-hidden' : ''; ?>" data-operation-areas-empty>
                    <span aria-hidden="true"><i class="fas fa-draw-polygon"></i></span>
                    <h2><?php esc_html_e('No operation areas yet', 'ecab-taxi-booking-manager'); ?></h2>
                    <p><?php esc_html_e('Add your first area to enable map-based pricing adjustments.', 'ecab-taxi-booking-manager'); ?></p>
                    <button type="button" class="button button-primary" data-operation-areas-open>
                        <?php esc_html_e('Add First Area', 'ecab-taxi-booking-manager'); ?>
                    </button>
                </div>
            </section>

            <div class="mptbm-operation-areas-modal" data-operation-areas-modal data-map-type="<?php echo esc_attr($map_type); ?>" aria-hidden="true">
                <div class="mptbm-operation-areas-modal-backdrop" data-operation-areas-close></div>
                <div class="mptbm-operation-areas-dialog" role="dialog" aria-modal="true" aria-labelledby="mptbm-operation-areas-modal-title">
                    <header class="mptbm-operation-areas-dialog-header">
                        <div class="mptbm-operation-areas-dialog-heading">
                            <div class="mptbm-operation-areas-dialog-icon" aria-hidden="true">
                                <i class="fas fa-draw-polygon"></i>
                            </div>
                            <div>
                                <p class="mptbm-operation-areas-dialog-eyebrow"><?php esc_html_e('Area builder', 'ecab-taxi-booking-manager'); ?></p>
                                <h2 id="mptbm-operation-areas-modal-title" data-operation-areas-title><?php esc_html_e('Add Operation Area', 'ecab-taxi-booking-manager'); ?></h2>
                                <p data-operation-areas-subtitle><?php esc_html_e('Define where this pricing rule applies by drawing it on the map.', 'ecab-taxi-booking-manager'); ?></p>
                            </div>
                        </div>
                        <button type="button" class="mptbm-operation-areas-modal-close" data-operation-areas-close aria-label="<?php esc_attr_e('Close dialog', 'ecab-taxi-booking-manager'); ?>">
                            <i class="fas fa-times" aria-hidden="true"></i>
                        </button>
                    </header>

                    <form data-operation-areas-form novalidate>
                        <?php wp_nonce_field('mptbm_operate_areas', 'mptbm_operate_areas'); ?>
                        <input type="hidden" name="post_id" data-operation-areas-post-id value="" />

                        <ol class="mptbm-operation-areas-progress" aria-label="<?php esc_attr_e('Operation area setup progress', 'ecab-taxi-booking-manager'); ?>">
                            <li data-progress-step="details"><span>1</span><strong><?php esc_html_e('Details', 'ecab-taxi-booking-manager'); ?></strong></li>
                            <li data-progress-step="boundaries"><span>2</span><strong><?php esc_html_e('Boundaries', 'ecab-taxi-booking-manager'); ?></strong></li>
                            <li data-progress-step="settings"><span>3</span><strong><?php esc_html_e('Review & save', 'ecab-taxi-booking-manager'); ?></strong></li>
                        </ol>

                        <section class="mptbm-operation-areas-panel mptbm-operation-areas-basics" aria-labelledby="mptbm-area-details-heading">
                            <div class="mptbm-operation-areas-panel-heading">
                                <span aria-hidden="true"><i class="fas fa-info-circle"></i></span>
                                <div>
                                    <h3 id="mptbm-area-details-heading"><?php esc_html_e('Area details', 'ecab-taxi-booking-manager'); ?></h3>
                                    <p><?php esc_html_e('Give this rule a clear name and choose how it should match trips.', 'ecab-taxi-booking-manager'); ?></p>
                                </div>
                            </div>
                            <div class="mptbm-operation-areas-field">
                                <label for="mptbm-operation-areas-name"><?php esc_html_e('Area name', 'ecab-taxi-booking-manager'); ?> <span aria-hidden="true">*</span></label>
                                <input type="text" id="mptbm-operation-areas-name" name="title" maxlength="200" autocomplete="off" placeholder="<?php esc_attr_e('For example: Downtown Zone', 'ecab-taxi-booking-manager'); ?>" required>
                                <small><?php esc_html_e('Use a name your dispatch and pricing teams will recognize.', 'ecab-taxi-booking-manager'); ?></small>
                            </div>

                            <div class="mptbm-operation-areas-field">
                                <span class="mptbm-operation-areas-field-label" id="mptbm-operation-type-label"><?php esc_html_e('Operation type', 'ecab-taxi-booking-manager'); ?></span>
                                <select class="formControl mptbm-operation-areas-native-select" name="mptbm-operation-type" id="mptbm-operation-type" data-collapse-target tabindex="-1" aria-hidden="true">
                                    <option data-option-target="#fixed-operation-area-type" value="fixed-operation-area-type" selected><?php esc_html_e('Single Operation Area', 'ecab-taxi-booking-manager'); ?></option>
                                    <option data-option-target="#geo-fence-operation-area-type" value="geo-fence-operation-area-type"><?php esc_html_e('Intercity Operation Area', 'ecab-taxi-booking-manager'); ?></option>
                                </select>
                                <div class="mptbm-operation-type-choices" role="radiogroup" aria-labelledby="mptbm-operation-type-label">
                                    <button type="button" class="mptbm-operation-type-choice is-selected" data-operation-type-choice="fixed-operation-area-type" role="radio" aria-checked="true">
                                        <span class="mptbm-operation-type-choice-icon"><i class="fas fa-draw-polygon" aria-hidden="true"></i></span>
                                        <span><strong><?php esc_html_e('Single area', 'ecab-taxi-booking-manager'); ?></strong><small><?php esc_html_e('Match trips that start inside one boundary.', 'ecab-taxi-booking-manager'); ?></small></span>
                                        <i class="fas fa-check-circle mptbm-operation-type-choice-check" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="mptbm-operation-type-choice" data-operation-type-choice="geo-fence-operation-area-type" role="radio" aria-checked="false">
                                        <span class="mptbm-operation-type-choice-icon is-amber"><i class="fas fa-route" aria-hidden="true"></i></span>
                                        <span><strong><?php esc_html_e('Intercity pair', 'ecab-taxi-booking-manager'); ?></strong><small><?php esc_html_e('Connect an origin boundary with a destination boundary.', 'ecab-taxi-booking-manager'); ?></small></span>
                                        <i class="fas fa-check-circle mptbm-operation-type-choice-check" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </section>

                        <section class="mptbm-operation-areas-section mActive" data-collapse="#fixed-operation-area-type">
                            <?php $this->render_map_builder('three', __('Service boundary', 'ecab-taxi-booking-manager'), __('Search for the service location, then outline the area covered by this rule.', 'ecab-taxi-booking-manager'), __('Single area', 'ecab-taxi-booking-manager'), $map_type); ?>
                        </section>

                        <section class="mptbm-operation-areas-section" data-collapse="#geo-fence-operation-area-type">
                            <div class="mptbm-operation-areas-map-grid">
                                <?php $this->render_map_builder('one', __('Origin boundary', 'ecab-taxi-booking-manager'), __('Trips begin inside this area.', 'ecab-taxi-booking-manager'), __('Step 1', 'ecab-taxi-booking-manager'), $map_type); ?>
                                <?php $this->render_map_builder('two', __('Destination boundary', 'ecab-taxi-booking-manager'), __('Trips end inside this area.', 'ecab-taxi-booking-manager'), __('Step 2', 'ecab-taxi-booking-manager'), $map_type); ?>
                            </div>

                            <section class="mptbm-operation-areas-panel mptbm-operation-areas-pricing" aria-labelledby="mptbm-area-pricing-heading">
                                <div class="mptbm-operation-areas-panel-heading">
                                    <span class="is-amber" aria-hidden="true"><i class="fas fa-tags"></i></span>
                                    <div>
                                        <h3 id="mptbm-area-pricing-heading"><?php esc_html_e('Route pricing', 'ecab-taxi-booking-manager'); ?></h3>
                                        <p><?php esc_html_e('Choose the surcharge and whether this pair works in one or both directions.', 'ecab-taxi-booking-manager'); ?></p>
                                    </div>
                                </div>
                                <div class="mptbm-operation-areas-pricing-grid">
                                    <div class="mptbm-operation-areas-field">
                                        <label for="mptbm-geo-fence-increase-price-by"><?php esc_html_e('Increase price by', 'ecab-taxi-booking-manager'); ?></label>
                                        <select class="formControl" name="mptbm-geo-fence-increase-price-by" id="mptbm-geo-fence-increase-price-by" data-collapse-target>
                                            <option data-option-target="#geo-fence-fixed-price" value="geo-fence-fixed-price" selected><?php esc_html_e('Fixed Price', 'ecab-taxi-booking-manager'); ?></option>
                                            <option data-option-target="#geo-fence-percentage-price" value="geo-fence-percentage-price"><?php esc_html_e('Percentage', 'ecab-taxi-booking-manager'); ?></option>
                                        </select>
                                    </div>
                                    <div class="mptbm-operation-areas-field mActive" data-collapse="#geo-fence-fixed-price">
                                        <label for="mptbm-geo-fence-fixed-price-amount"><?php esc_html_e('Fixed price amount', 'ecab-taxi-booking-manager'); ?></label>
                                        <input class="formControl" type="number" min="0" step="0.01" name="mptbm-geo-fence-fixed-price-amount" id="mptbm-geo-fence-fixed-price-amount" placeholder="<?php esc_attr_e('For example: 10', 'ecab-taxi-booking-manager'); ?>" />
                                    </div>
                                    <div class="mptbm-operation-areas-field" data-collapse="#geo-fence-percentage-price" style="display:none;">
                                        <label for="mptbm-geo-fence-percentage-amount"><?php esc_html_e('Percentage amount', 'ecab-taxi-booking-manager'); ?></label>
                                        <input class="formControl" type="number" min="1" max="100" step="0.01" name="mptbm-geo-fence-percentage-amount" id="mptbm-geo-fence-percentage-amount" placeholder="<?php esc_attr_e('For example: 10', 'ecab-taxi-booking-manager'); ?>" />
                                    </div>
                                    <div class="mptbm-operation-areas-field">
                                        <label for="mptbm-geo-fence-direction"><?php esc_html_e('Direction', 'ecab-taxi-booking-manager'); ?></label>
                                        <select class="formControl" name="mptbm-geo-fence-direction" id="mptbm-geo-fence-direction">
                                            <option value="geo-fence-one-direction" selected><?php esc_html_e('One Direction (Origin → Destination)', 'ecab-taxi-booking-manager'); ?></option>
                                            <option value="geo-fence-both-direction"><?php esc_html_e('Both Directions (Origin ↔ Destination)', 'ecab-taxi-booking-manager'); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </section>
                        </section>

                        <section class="mptbm-operation-areas-review" data-operation-areas-review aria-live="polite">
                            <span class="mptbm-operation-areas-review-icon"><i class="fas fa-clipboard-check" aria-hidden="true"></i></span>
                            <div><strong data-operation-areas-readiness><?php esc_html_e('Setup incomplete', 'ecab-taxi-booking-manager'); ?></strong><p data-operation-areas-review-text><?php esc_html_e('Complete the details and boundary steps to save this area.', 'ecab-taxi-booking-manager'); ?></p></div>
                            <span class="mptbm-operation-areas-review-count" data-operation-areas-completion>0%</span>
                        </section>

                        <div class="mptbm-operation-areas-message" data-operation-areas-message role="alert" aria-live="assertive"></div>
                        <div class="mptbm-operation-areas-modal-actions">
                            <div class="mptbm-operation-areas-save-state" data-operation-areas-save-state><i class="fas fa-circle" aria-hidden="true"></i><span><?php esc_html_e('Setup incomplete', 'ecab-taxi-booking-manager'); ?></span></div>
                            <div class="mptbm-operation-areas-action-buttons">
                                <button type="button" class="button" data-operation-areas-close><?php esc_html_e('Cancel', 'ecab-taxi-booking-manager'); ?></button>
                                <button type="submit" class="button button-primary" data-operation-areas-submit disabled>
                                    <i class="fas fa-plus" aria-hidden="true"></i>
                                    <span data-operation-areas-submit-label><?php esc_html_e('Add area', 'ecab-taxi-booking-manager'); ?></span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php
        }

        private function render_map_builder(string $slot, string $title, string $description, string $badge, string $map_type): void {
            $input_id = 'mptbm-starting-location-' . $slot;
            $coords_id = 'mptbm-coordinates-' . $slot;
            ?>
            <article class="mptbm-operation-area-builder" data-area-slot="<?php echo esc_attr($slot); ?>">
                <header class="mptbm-operation-area-builder-header">
                    <div>
                        <span class="mptbm-operation-area-builder-badge"><?php echo esc_html($badge); ?></span>
                        <h3><?php echo esc_html($title); ?></h3>
                        <p><?php echo esc_html($description); ?></p>
                    </div>
                    <span class="mptbm-operation-area-builder-status" data-boundary-status><i class="fas fa-circle" aria-hidden="true"></i><span><?php esc_html_e('Boundary not drawn', 'ecab-taxi-booking-manager'); ?></span></span>
                </header>

                <div class="mptbm-operation-areas-field mptbm-operation-area-location-field">
                    <label for="<?php echo esc_attr($input_id); ?>"><?php esc_html_e('Search and confirm location', 'ecab-taxi-booking-manager'); ?></label>
                    <div class="mptbm-operation-area-search-wrap">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <input class="formControl" type="text" id="<?php echo esc_attr($input_id); ?>" autocomplete="off" placeholder="<?php esc_attr_e('Type at least 3 characters and select a result…', 'ecab-taxi-booking-manager'); ?>" />
                        <span class="mptbm-operation-area-location-check" data-location-check title="<?php esc_attr_e('Location confirmed', 'ecab-taxi-booking-manager'); ?>"><i class="fas fa-check" aria-hidden="true"></i></span>
                    </div>
                    <input type="hidden" name="<?php echo esc_attr($input_id); ?>" id="<?php echo esc_attr($input_id . '-hidden'); ?>" />
                    <input type="hidden" name="<?php echo esc_attr($coords_id); ?>" id="<?php echo esc_attr($coords_id); ?>" />
                </div>

                <div class="mptbm-operation-area-drawing-guide">
                    <span><b>1</b><?php esc_html_e('Search', 'ecab-taxi-booking-manager'); ?></span>
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    <span><b>2</b><?php esc_html_e('Draw boundary', 'ecab-taxi-booking-manager'); ?></span>
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    <span><b>3</b><?php esc_html_e('Click the orange START point to finish', 'ecab-taxi-booking-manager'); ?></span>
                </div>

                <div class="mptbm-operation-area-map-shell">
                    <div id="mptbm-map-canvas-<?php echo esc_attr($slot); ?>" class="mptbm-operation-areas-map"></div>
                    <?php if ($map_type === 'openstreetmap') : ?>
                        <div class="mptbm-operation-area-map-actions" aria-label="<?php esc_attr_e('Boundary drawing actions', 'ecab-taxi-booking-manager'); ?>">
                            <button type="button" data-map-action="draw" title="<?php esc_attr_e('Draw a new boundary', 'ecab-taxi-booking-manager'); ?>" aria-label="<?php esc_attr_e('Draw a new boundary', 'ecab-taxi-booking-manager'); ?>"><i class="fas fa-pencil-alt" aria-hidden="true"></i><span><?php esc_html_e('Draw', 'ecab-taxi-booking-manager'); ?></span></button>
                            <button type="button" data-map-action="undo" disabled title="<?php esc_attr_e('Undo the last point', 'ecab-taxi-booking-manager'); ?>" aria-label="<?php esc_attr_e('Undo the last point', 'ecab-taxi-booking-manager'); ?>"><i class="fas fa-undo" aria-hidden="true"></i><span><?php esc_html_e('Undo', 'ecab-taxi-booking-manager'); ?></span></button>
                            <button type="button" data-map-action="edit" title="<?php esc_attr_e('Edit boundary points', 'ecab-taxi-booking-manager'); ?>" aria-label="<?php esc_attr_e('Edit boundary points', 'ecab-taxi-booking-manager'); ?>"><i class="fas fa-edit" aria-hidden="true"></i><span><?php esc_html_e('Edit', 'ecab-taxi-booking-manager'); ?></span></button>
                            <button type="button" data-map-action="fit" title="<?php esc_attr_e('Fit the boundary on the map', 'ecab-taxi-booking-manager'); ?>" aria-label="<?php esc_attr_e('Fit the boundary on the map', 'ecab-taxi-booking-manager'); ?>"><i class="fas fa-expand" aria-hidden="true"></i><span><?php esc_html_e('Fit', 'ecab-taxi-booking-manager'); ?></span></button>
                            <button type="button" class="is-danger" data-map-action="clear" title="<?php esc_attr_e('Clear the boundary', 'ecab-taxi-booking-manager'); ?>" aria-label="<?php esc_attr_e('Clear the boundary', 'ecab-taxi-booking-manager'); ?>"><i class="fas fa-trash-alt" aria-hidden="true"></i><span><?php esc_html_e('Clear', 'ecab-taxi-booking-manager'); ?></span></button>
                        </div>
                    <?php endif; ?>
                </div>

                <footer class="mptbm-operation-area-builder-footer">
                    <span data-boundary-metric><i class="fas fa-vector-square" aria-hidden="true"></i><?php esc_html_e('No boundary data yet', 'ecab-taxi-booking-manager'); ?></span>
                    <span data-location-status><i class="fas fa-map-marker-alt" aria-hidden="true"></i><?php esc_html_e('Location not confirmed', 'ecab-taxi-booking-manager'); ?></span>
                </footer>
            </article>
            <?php
        }

        private function get_operation_area_card(WP_Post $post): string {
            $type = get_post_meta($post->ID, 'mptbm-operation-type', true);
            $is_fixed = $type !== 'geo-fence-operation-area-type';

            if ($is_fixed) {
                $location_one = get_post_meta($post->ID, 'mptbm-starting-location-three', true);
                $location_two = '';
                $coordinates_three = get_post_meta($post->ID, 'mptbm-coordinates-three', true);
                $is_mapped = is_array($coordinates_three) && count($coordinates_three) >= 6;
                $preview_polygons = $is_mapped
                    ? [ [ 'coordinates' => array_values($coordinates_three), 'color' => '#635bff' ] ]
                    : [];
            } else {
                $location_one = get_post_meta($post->ID, 'mptbm-starting-location-one', true);
                $location_two = get_post_meta($post->ID, 'mptbm-starting-location-two', true);
                $coordinates_one = get_post_meta($post->ID, 'mptbm-coordinates-one', true);
                $coordinates_two = get_post_meta($post->ID, 'mptbm-coordinates-two', true);
                $is_mapped = is_array($coordinates_one) && count($coordinates_one) >= 6 && is_array($coordinates_two) && count($coordinates_two) >= 6;
                $preview_polygons = $is_mapped
                    ? [
                        [ 'coordinates' => array_values($coordinates_one), 'color' => '#635bff' ],
                        [ 'coordinates' => array_values($coordinates_two), 'color' => '#f59e0b' ],
                    ]
                    : [];
            }

            $price_by = get_post_meta($post->ID, 'mptbm-geo-fence-increase-price-by', true);
            $fixed_amount = get_post_meta($post->ID, 'mptbm-geo-fence-fixed-price-amount', true);
            $percentage_amount = get_post_meta($post->ID, 'mptbm-geo-fence-percentage-amount', true);

            $initial = function_exists('mb_substr') ? mb_substr($post->post_title, 0, 1) : substr($post->post_title, 0, 1);
            $initial = function_exists('mb_strtoupper') ? mb_strtoupper($initial) : strtoupper($initial);
            $initial = $initial !== '' ? $initial : '?';

            ob_start();
            ?>
            <article class="mptbm-operation-areas-card" data-post-id="<?php echo esc_attr($post->ID); ?>">
                <div class="mptbm-operation-areas-card-top">
                    <span class="mptbm-operation-areas-avatar" aria-hidden="true"><?php echo esc_html($initial); ?></span>
                    <div class="mptbm-operation-areas-card-actions">
                        <button type="button" class="mptbm-operation-areas-icon-btn" data-operation-area-edit title="<?php esc_attr_e('Edit area', 'ecab-taxi-booking-manager'); ?>">
                            <i class="fas fa-pen" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="mptbm-operation-areas-icon-btn is-danger" data-operation-area-delete title="<?php esc_attr_e('Move to Trash', 'ecab-taxi-booking-manager'); ?>">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <h2><?php echo esc_html($post->post_title); ?></h2>
                <span class="mptbm-operation-areas-type-badge <?php echo $is_fixed ? 'is-fixed' : 'is-geofence'; ?>">
                    <i class="fas <?php echo $is_fixed ? 'fa-map-marker-alt' : 'fa-route'; ?>" aria-hidden="true"></i>
                    <?php echo $is_fixed ? esc_html__('Single Area', 'ecab-taxi-booking-manager') : esc_html__('Intercity Pair', 'ecab-taxi-booking-manager'); ?>
                </span>
                <div class="mptbm-operation-areas-location-line <?php echo $is_mapped ? 'is-mapped' : 'is-unmapped'; ?>">
                    <i class="fas fa-map-pin" aria-hidden="true"></i>
                    <span>
                    <?php
                    if ($is_fixed) {
                        echo esc_html($location_one !== '' ? $location_one : __('No location set', 'ecab-taxi-booking-manager'));
                    } else {
                        $parts = array_filter([ $location_one, $location_two ]);
                        echo $parts ? esc_html(implode(' ↔ ', $parts)) : esc_html__('No locations set', 'ecab-taxi-booking-manager');
                    }
                    ?>
                    </span>
                </div>
                <?php if ($is_mapped) : ?>
                    <div class="mptbm-operation-areas-card-map" data-operation-area-preview data-polygons="<?php echo esc_attr(wp_json_encode($preview_polygons)); ?>" role="img" aria-label="<?php esc_attr_e('Saved operation area boundary map', 'ecab-taxi-booking-manager'); ?>">
                        <span><i class="fas fa-map-marked-alt" aria-hidden="true"></i><?php esc_html_e('Loading boundary map…', 'ecab-taxi-booking-manager'); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!$is_fixed) : ?>
                    <p class="mptbm-operation-areas-price-line">
                        <i class="fas fa-tag" aria-hidden="true"></i>
                        <?php
                        if ($price_by === 'geo-fence-percentage-price' && $percentage_amount !== '') {
                            printf(esc_html__('+%s%% surcharge', 'ecab-taxi-booking-manager'), esc_html($percentage_amount));
                        } elseif ($fixed_amount !== '') {
                            printf(esc_html__('+%s fixed surcharge', 'ecab-taxi-booking-manager'), esc_html($fixed_amount));
                        } else {
                            esc_html_e('No surcharge set', 'ecab-taxi-booking-manager');
                        }
                        ?>
                    </p>
                <?php endif; ?>
                <footer>
                    <code><?php echo esc_html($post->post_name); ?></code>
                </footer>
            </article>
            <?php
            return (string) ob_get_clean();
        }

        private function guard_ajax_access(): void {
            if (!current_user_can('manage_mptbm_transportation')) {
                wp_send_json_error(
                    [ 'message' => esc_html__('You do not have permission to manage operation areas.', 'ecab-taxi-booking-manager') ],
                    403
                );
            }
        }

        // Validates the submitted shape/price fields ourselves BEFORE calling
        // wp_insert_post()/wp_update_post() — the classic save_operate_areas_settings()
        // (which still runs automatically via save_post) silently saves nothing for an
        // incomplete shape with no error at all; gating here instead gives the user a
        // visible error and blocks the save entirely, per explicit product decision.
        private function validate_submission(): string {
            $type = isset($_POST['mptbm-operation-type']) ? sanitize_text_field(wp_unslash($_POST['mptbm-operation-type'])) : '';

            if (!in_array($type, [ 'fixed-operation-area-type', 'geo-fence-operation-area-type' ], true)) {
                return esc_html__('Choose a valid operation area type.', 'ecab-taxi-booking-manager');
            }

            if ($type === 'fixed-operation-area-type') {
                $location = isset($_POST['mptbm-starting-location-three']) ? trim(sanitize_text_field(wp_unslash($_POST['mptbm-starting-location-three']))) : '';
                $coords = isset($_POST['mptbm-coordinates-three']) ? trim(sanitize_text_field(wp_unslash($_POST['mptbm-coordinates-three']))) : '';
                if ($location === '' || !$this->coordinates_are_valid($coords)) {
                    return esc_html__('Draw the operation area on the map and confirm a starting location before saving.', 'ecab-taxi-booking-manager');
                }
                return '';
            }

            $location_one = isset($_POST['mptbm-starting-location-one']) ? trim(sanitize_text_field(wp_unslash($_POST['mptbm-starting-location-one']))) : '';
            $location_two = isset($_POST['mptbm-starting-location-two']) ? trim(sanitize_text_field(wp_unslash($_POST['mptbm-starting-location-two']))) : '';
            $coords_one = isset($_POST['mptbm-coordinates-one']) ? trim(sanitize_text_field(wp_unslash($_POST['mptbm-coordinates-one']))) : '';
            $coords_two = isset($_POST['mptbm-coordinates-two']) ? trim(sanitize_text_field(wp_unslash($_POST['mptbm-coordinates-two']))) : '';
            if ($location_one === '' || $location_two === '' || !$this->coordinates_are_valid($coords_one) || !$this->coordinates_are_valid($coords_two)) {
                return esc_html__('Draw both operation areas on the map and confirm both starting locations before saving.', 'ecab-taxi-booking-manager');
            }

            $price_by = isset($_POST['mptbm-geo-fence-increase-price-by']) ? sanitize_text_field(wp_unslash($_POST['mptbm-geo-fence-increase-price-by'])) : '';
            if (!in_array($price_by, [ 'geo-fence-fixed-price', 'geo-fence-percentage-price' ], true)) {
                return esc_html__('Choose a valid price adjustment type.', 'ecab-taxi-booking-manager');
            }
            $amount = $price_by === 'geo-fence-percentage-price'
                ? (isset($_POST['mptbm-geo-fence-percentage-amount']) ? trim(sanitize_text_field(wp_unslash($_POST['mptbm-geo-fence-percentage-amount']))) : '')
                : (isset($_POST['mptbm-geo-fence-fixed-price-amount']) ? trim(sanitize_text_field(wp_unslash($_POST['mptbm-geo-fence-fixed-price-amount']))) : '');
            $numeric_amount = is_numeric($amount) ? (float) $amount : -1;
            if ($amount === '' || $numeric_amount < ($price_by === 'geo-fence-percentage-price' ? 1 : 0) || ($price_by === 'geo-fence-percentage-price' && $numeric_amount > 100)) {
                return esc_html__('Enter a price adjustment amount.', 'ecab-taxi-booking-manager');
            }

            return '';
        }

        private function coordinates_are_valid(string $coordinates): bool {
            $values = array_map('trim', explode(',', $coordinates));
            if (count($values) < 6 || count($values) % 2 !== 0) {
                return false;
            }

            for ($index = 0; $index < count($values); $index += 2) {
                if (!is_numeric($values[$index]) || !is_numeric($values[$index + 1])) {
                    return false;
                }
                $latitude = (float) $values[$index];
                $longitude = (float) $values[$index + 1];
                if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                    return false;
                }
            }

            return true;
        }

        public function ajax_add_operation_area(): void {
            check_ajax_referer('mptbm_operation_areas_manager_nonce', 'nonce');
            $this->guard_ajax_access();

            $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
            if ($title === '') {
                wp_send_json_error(
                    [ 'message' => esc_html__('Enter a name for this operation area.', 'ecab-taxi-booking-manager') ],
                    400
                );
            }

            $validation_error = $this->validate_submission();
            if ($validation_error !== '') {
                wp_send_json_error([ 'message' => $validation_error ], 400);
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

            // The shape/price meta is saved automatically here: wp_insert_post() just
            // fired save_post, which MPTBM_Operation_Areas::save_operate_areas_settings()
            // is already hooked to — it reads mptbm-operation-type/mptbm-coordinates-*/
            // etc and the mptbm_operate_areas nonce directly from this same $_POST
            // payload (already validated above, so its own no-op branches never trigger).
            $post = get_post((int) $post_id);
            if (!$post) {
                wp_send_json_error(
                    [ 'message' => esc_html__('The area was added but could not be displayed.', 'ecab-taxi-booking-manager') ],
                    500
                );
            }

            wp_send_json_success(
                [
                    'message' => esc_html__('Operation area added successfully.', 'ecab-taxi-booking-manager'),
                    'card' => $this->get_operation_area_card($post),
                    'postId' => (int) $post->ID,
                ]
            );
        }

        public function ajax_update_operation_area(): void {
            check_ajax_referer('mptbm_operation_areas_manager_nonce', 'nonce');
            $this->guard_ajax_access();

            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            $existing = $post_id ? get_post($post_id) : null;
            if (!$existing || $existing->post_type !== self::POST_TYPE) {
                wp_send_json_error(
                    [ 'message' => esc_html__('This operation area could not be found.', 'ecab-taxi-booking-manager') ],
                    404
                );
            }

            $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
            if ($title === '') {
                wp_send_json_error(
                    [ 'message' => esc_html__('Enter a name for this operation area.', 'ecab-taxi-booking-manager') ],
                    400
                );
            }

            $validation_error = $this->validate_submission();
            if ($validation_error !== '') {
                wp_send_json_error([ 'message' => $validation_error ], 400);
            }

            $result = wp_update_post(
                [
                    'ID' => $post_id,
                    'post_title' => $title,
                    'post_status' => 'publish',
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
                    'message' => esc_html__('Operation area updated successfully.', 'ecab-taxi-booking-manager'),
                    'card' => $this->get_operation_area_card($post),
                    'postId' => (int) $post->ID,
                ]
            );
        }

        public function ajax_delete_operation_area(): void {
            check_ajax_referer('mptbm_operation_areas_manager_nonce', 'nonce');
            $this->guard_ajax_access();

            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            $existing = $post_id ? get_post($post_id) : null;
            if (!$existing || $existing->post_type !== self::POST_TYPE) {
                wp_send_json_error(
                    [ 'message' => esc_html__('This operation area could not be found.', 'ecab-taxi-booking-manager') ],
                    404
                );
            }

            // Trash (not force-delete): this CPT has WordPress's normal trash/undo
            // safety net available, unlike Locations (a taxonomy term has none).
            $result = wp_trash_post($post_id);
            if (!$result) {
                wp_send_json_error(
                    [ 'message' => esc_html__('This operation area could not be moved to Trash.', 'ecab-taxi-booking-manager') ],
                    500
                );
            }

            wp_send_json_success(
                [
                    'message' => esc_html__('Operation area moved to Trash.', 'ecab-taxi-booking-manager'),
                    'postId' => $post_id,
                ]
            );
        }

        public function ajax_get_operation_area_data(): void {
            check_ajax_referer('mptbm_operation_areas_manager_nonce', 'nonce');
            $this->guard_ajax_access();

            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            $post = $post_id ? get_post($post_id) : null;
            if (!$post || $post->post_type !== self::POST_TYPE) {
                wp_send_json_error(
                    [ 'message' => esc_html__('This operation area could not be found.', 'ecab-taxi-booking-manager') ],
                    404
                );
            }

            $coords_one = get_post_meta($post_id, 'mptbm-coordinates-one', true);
            $coords_two = get_post_meta($post_id, 'mptbm-coordinates-two', true);
            $coords_three = get_post_meta($post_id, 'mptbm-coordinates-three', true);

            // Normalize to only the 2 supported types: any other stored value
            // (e.g. a legacy "geo-matched" record from before this redesign) is
            // treated as "fixed", matching get_operation_area_card()'s own logic.
            $stored_type = get_post_meta($post_id, 'mptbm-operation-type', true);
            $operation_type = $stored_type === 'geo-fence-operation-area-type' ? 'geo-fence-operation-area-type' : 'fixed-operation-area-type';

            wp_send_json_success(
                [
                    'postId' => (int) $post->ID,
                    'title' => $post->post_title,
                    'operationType' => $operation_type,
                    'location_one' => (string) get_post_meta($post_id, 'mptbm-starting-location-one', true),
                    'location_two' => (string) get_post_meta($post_id, 'mptbm-starting-location-two', true),
                    'location_three' => (string) get_post_meta($post_id, 'mptbm-starting-location-three', true),
                    'coordinates_one' => is_array($coords_one) ? array_values($coords_one) : [],
                    'coordinates_two' => is_array($coords_two) ? array_values($coords_two) : [],
                    'coordinates_three' => is_array($coords_three) ? array_values($coords_three) : [],
                    'priceBy' => (string) get_post_meta($post_id, 'mptbm-geo-fence-increase-price-by', true) ?: 'geo-fence-fixed-price',
                    'fixedAmount' => (string) get_post_meta($post_id, 'mptbm-geo-fence-fixed-price-amount', true),
                    'percentageAmount' => (string) get_post_meta($post_id, 'mptbm-geo-fence-percentage-amount', true),
                    'direction' => (string) get_post_meta($post_id, 'mptbm-geo-fence-direction', true) ?: 'geo-fence-one-direction',
                ]
            );
        }
    }

    new MPTBM_Operation_Areas_Manager();
}
