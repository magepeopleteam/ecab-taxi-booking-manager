<?php
/**
 * Ride History Template
 *
 * This template displays the user's ride history
 *
 * @package MPTBM
 */

defined('ABSPATH') || exit;

// Get current user
$current_user = wp_get_current_user();
?>

<div class="mptbm-ride-history-container">
    <div class="mptbm-ride-history-header">
        <h2><?php esc_html_e('Your Ride History', 'ecab-taxi-booking-manager'); ?></h2>
        <p class="mptbm-ride-history-subtitle"><?php esc_html_e('View and rebook your past rides', 'ecab-taxi-booking-manager'); ?></p>
    </div>

    <?php if (empty($taxi_orders)) : ?>
        <div class="mptbm-no-rides">
            <div class="mptbm-no-rides-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22 12.2V13.9C22 17.05 20.32 18.4 17.5 18.4H6.5C3.68 18.4 2 17.05 2 13.9V8.5C2 5.35 3.68 4 6.5 4H17.5C20.32 4 22 5.35 22 8.5V10.2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M8 13H16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M2 9.5H22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M2 13.5H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M18 13.5H22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <p><?php esc_html_e('You haven\'t taken any rides yet.', 'ecab-taxi-booking-manager'); ?></p>
            <a href="<?php echo esc_url(get_permalink(get_page_by_path('transport_booking'))); ?>" class="mptbm-button">
                <?php esc_html_e('Book Your First Ride', 'ecab-taxi-booking-manager'); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="mptbm-ride-history-list">
            <?php foreach ($taxi_orders as $order) : 
                $order_id = $order->get_id();
                $order_date = $order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format'));
                $order_status = wc_get_order_status_name($order->get_status());
                
                // Get taxi items from this order
                $taxi_items = array();
                foreach ($order->get_items() as $item_id => $item) {
                    $product_id = $item->get_product_id();
                    $linked_id = MP_Global_Function::get_post_info($product_id, 'link_mptbm_id', $product_id);
                    
                    if (get_post_type($linked_id) == MPTBM_Function::get_cpt()) {
                        $taxi_items[$item_id] = $item;
                    }
                }
                
                if (empty($taxi_items)) {
                    continue;
                }
            ?>
                <div class="mptbm-ride-card">
                    <div class="mptbm-ride-card-header">
                        <div class="mptbm-ride-order-info">
                            <span class="mptbm-order-number"><?php echo esc_html__('Order #', 'ecab-taxi-booking-manager') . $order->get_order_number(); ?></span>
                            <span class="mptbm-order-date"><?php echo esc_html($order_date); ?></span>
                        </div>
                        <div class="mptbm-ride-status <?php echo esc_attr(strtolower($order_status)); ?>">
                            <?php echo esc_html($order_status); ?>
                        </div>
                    </div>
                    
                    <?php foreach ($taxi_items as $item_id => $item) : 
                        $product_id = $item->get_product_id();
                        $linked_id = MP_Global_Function::get_post_info($product_id, 'link_mptbm_id', $product_id);
                        $product_name = $item->get_name();
                        
                        // Get ride details
                        $start_place = $item->get_meta('mptbm_start_place');
                        $end_place = $item->get_meta('mptbm_end_place');
                        $journey_date = $item->get_meta('mptbm_journey_date');
                        $journey_time = $item->get_meta('mptbm_journey_time');
                        
                        // Get vehicle image
                        $vehicle_image = get_the_post_thumbnail_url($linked_id, 'thumbnail');
                        if (!$vehicle_image) {
                            $vehicle_image = MPTBM_PLUGIN_URL . '/assets/images/default-car.png';
                        }
                    ?>
                        <div class="mptbm-ride-details">
                            <div class="mptbm-ride-vehicle">
                                <img src="<?php echo esc_url($vehicle_image); ?>" alt="<?php echo esc_attr($product_name); ?>" class="mptbm-vehicle-image">
                                <h4 class="mptbm-vehicle-name"><?php echo esc_html($product_name); ?></h4>
                            </div>
                            
                            <div class="mptbm-ride-route">
                                <div class="mptbm-route-points">
                                    <div class="mptbm-route-start">
                                        <span class="mptbm-point-marker mptbm-start-marker"></span>
                                        <div class="mptbm-point-details">
                                            <span class="mptbm-point-label"><?php esc_html_e('Pickup', 'ecab-taxi-booking-manager'); ?></span>
                                            <span class="mptbm-point-address"><?php echo esc_html($start_place); ?></span>
                                        </div>
                                    </div>
                                    <div class="mptbm-route-line"></div>
                                    <div class="mptbm-route-end">
                                        <span class="mptbm-point-marker mptbm-end-marker"></span>
                                        <div class="mptbm-point-details">
                                            <span class="mptbm-point-label"><?php esc_html_e('Dropoff', 'ecab-taxi-booking-manager'); ?></span>
                                            <span class="mptbm-point-address"><?php echo esc_html($end_place); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mptbm-ride-datetime">
                                    <div class="mptbm-ride-date">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M8 2V5" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M16 2V5" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M3.5 9.09H20.5" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M21 8.5V17C21 20 19.5 22 16 22H8C4.5 22 3 20 3 17V8.5C3 5.5 4.5 3.5 8 3.5H16C19.5 3.5 21 5.5 21 8.5Z" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M15.6947 13.7H15.7037" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M15.6947 16.7H15.7037" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M11.9955 13.7H12.0045" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M11.9955 16.7H12.0045" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M8.29431 13.7H8.30329" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M8.29431 16.7H8.30329" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php echo esc_html($journey_date); ?>
                                    </div>
                                    <div class="mptbm-ride-time">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M22 12C22 17.52 17.52 22 12 22C6.48 22 2 17.52 2 12C2 6.48 6.48 2 12 2C17.52 2 22 6.48 22 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M15.71 15.18L12.61 13.33C12.07 13.01 11.63 12.24 11.63 11.61V7.51" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php echo esc_html($journey_time); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mptbm-ride-actions">
                                <button class="mptbm-rebook-button" 
                                        data-order-id="<?php echo esc_attr($order_id); ?>" 
                                        data-item-id="<?php echo esc_attr($item_id); ?>">
                                    <?php esc_html_e('Book Again', 'ecab-taxi-booking-manager'); ?>
                                </button>
                                <a href="<?php echo esc_url($order->get_view_order_url()); ?>" class="mptbm-view-details">
                                    <?php esc_html_e('View Details', 'ecab-taxi-booking-manager'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($orders->max_num_pages > 1 && $atts['pagination'] === 'yes') : ?>
            <div class="mptbm-ride-history-pagination">
                <?php
                echo paginate_links(array(
                    'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                    'format' => '?paged=%#%',
                    'current' => max(1, get_query_var('paged')),
                    'total' => $orders->max_num_pages,
                    'prev_text' => '&larr;',
                    'next_text' => '&rarr;',
                    'type' => 'list',
                    'end_size' => 3,
                    'mid_size' => 3
                ));
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>