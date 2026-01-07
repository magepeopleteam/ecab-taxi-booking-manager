<?php
/*
Template Name: Transport Result
*/

// Ensure WordPress is loaded
if (!defined('ABSPATH')) {
    exit;
}

// Start the session to access session variables
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



// Retrieve the content from the session variable
$content = isset($_SESSION['custom_content']) ? $_SESSION['custom_content'] : '';



// Check if $content is empty, redirect to homepage if it is
if (empty($content)) {
    wp_redirect(home_url());
    exit;
}

// Clear only pricing-related caches to ensure fresh pricing calculations
// This prevents object caching from showing only minimum-priced vehicles
// but preserves essential search data
global $wpdb;
$cache_patterns = array(
    'weather_pricing_%',
    'traffic_data_%',
    'mptbm_custom_price_message_%'
);

foreach ($cache_patterns as $pattern) {
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        '_transient_' . $pattern
    ));
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        '_transient_timeout_' . $pattern
    ));
}

// Store content in a variable before unsetting session
$display_content = $content;

// Unset the session variable after storing it
unset($_SESSION['custom_content']);

// Handle theme header - works with both classic and block themes
if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
    // For block themes, use the block template system
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php wp_title('|', true, 'right'); ?></title>
        <?php wp_head(); ?>
    </head>
    <body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    
    <!-- Block Theme Header -->
    <header class="wp-block-template-part site-header" style="background: #fff; padding: 15px 0; border-bottom: 1px solid #ddd;">
        <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 15px; display: flex; justify-content: space-between; align-items: center;">
            <div class="site-branding">
                <h1 style="margin: 0; font-size: 24px;">
                    <a href="<?php echo esc_url(home_url('/')); ?>" style="color: #333; text-decoration: none;">
                        <?php bloginfo('name'); ?>
                    </a>
                </h1>
            </div>
            <nav class="main-navigation">
                <?php
                if (has_nav_menu('primary')) {
                    wp_nav_menu(array(
                        'theme_location' => 'primary',
                        'menu_id'        => 'primary-menu',
                        'container'      => false,
                        'menu_class'     => 'nav-menu',
                        'items_wrap'     => '<ul id="%1$s" class="%2$s" style="list-style: none; margin: 0; padding: 0; display: flex; gap: 20px;">%3$s</ul>',
                    ));
                } else {
                    // Default menu
                    echo '<ul style="list-style: none; margin: 0; padding: 0; display: flex; gap: 20px;">';
                    echo '<li><a href="' . home_url() . '" style="color: #333; text-decoration: none;">Home</a></li>';
                    echo '</ul>';
                }
                ?>
            </nav>
        </div>
    </header>
    <?php
} else {
    // For classic themes, try to load header
    get_header();
}
?>
<!-- DEBUG: Header loaded successfully -->
<!-- Theme: <?php echo get_template(); ?> -->
<!-- Template: <?php echo get_page_template_slug(); ?> -->
<script type="text/javascript">
    var httpReferrer = "<?php echo isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''; ?>";
    document.cookie = "httpReferrer=" + httpReferrer + ";path=/";
</script>

<style>
/* Ensure proper centering and alignment for transport result page */
.mptbm-show-search-result {
    padding: 20px 0;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: flex-start;
}

.mptbm-show-search-result .container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 15px;
}

.mptbm-show-search-result .background-img-skin {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 30px;
    margin: 0 auto;
}

/* Ensure the transport search area is properly centered */
.mptbm-show-search-result .mptbm_transport_search_area {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
}

/* Ensure content is properly aligned */
.mptbm-show-search-result .tabsContentNext {
    width: 100%;
}

/* Specific styling for the search result content */
.mptbm-show-search-result .mptbm_map_search_result {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
}

/* Ensure the flexWrap is properly displayed */
.mptbm-show-search-result .flexWrap {
    display: flex !important;
    gap: 30px;
    width: 100%;
    align-items: flex-start;
    visibility: visible !important;
    opacity: 1 !important;
}

.mptbm-show-search-result .mainSection {
    flex: 1;
    min-width: 0; /* Allow flex item to shrink */
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.mptbm-show-search-result .mp_sticky_section {
    width: 100%;
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

/* Ensure the summary section is properly positioned */
.mptbm-show-search-result .leftSidebar {
    width: 300px;
    min-width: 300px;
    margin-right: 30px;
    flex-shrink: 0; /* Prevent sidebar from shrinking */
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

/* Ensure vehicle items are properly displayed */
.mptbm-show-search-result .mptbm_booking_item {
    display: flex !important;
    margin-bottom: 15px;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    padding: 15px;
    background: #fff;
    visibility: visible !important;
    opacity: 1 !important;
}

/* Force all content to be visible */
.mptbm-show-search-result * {
    visibility: visible !important;
    opacity: 1 !important;
}

/* Ensure the search result content is displayed */
.mptbm-show-search-result .mptbm_map_search_result {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

/* Override any hidden classes */
.mptbm-show-search-result .mptbm_booking_item_hidden {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}

/* Mobile responsiveness */
@media only screen and (max-width: 768px) {
    .mptbm-show-search-result .container {
        padding: 0 10px;
    }
    
    .mptbm-show-search-result .background-img-skin {
        padding: 15px;
        margin: 0 5px;
    }
    
    .mptbm-show-search-result .flexWrap {
        flex-direction: column !important;
        gap: 20px;
    }
    
    .mptbm-show-search-result .leftSidebar {
        width: 100%;
        min-width: auto;
        margin-right: 0;
        order: 2;
    }
    
    .mptbm-show-search-result .mainSection {
        order: 1;
    }
}
</style>

<main role="main" id="maincontent" class="middle-align mptbm-show-search-result">
    <div class="container">
        <div class="container background-img-skin">
            <div class="mpStyle mptbm_transport_search_area">
                <div class="mpTabsNext">
                    <div class="tabListsNext">
                        <div data-tabs-target-next="#mptbm_pick_up_details" class="tabItemNext active" data-open-text="1" data-close-text=" " data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
                            <h4 class="circleIcon" data-class>
                                <span class="mp_zero" data-icon></span>
                                <span class="mp_zero" data-text>1</span>
                            </h4>
                            <h6 class="circleTitle" data-class><?php esc_html_e('Enter Ride Details', 'ecab-taxi-booking-manager'); ?></h6>
                        </div>
                        <div data-tabs-target-next="#mptbm_search_result" class="tabItemNext active" data-open-text="2" data-close-text="" data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
                            <h4 class="circleIcon" data-class>
                                <span class="mp_zero" data-icon></span>
                                <span class="mp_zero" data-text>2</span>
                            </h4>
                            <h6 class="circleTitle" data-class><?php esc_html_e('Choose a vehicle', 'ecab-taxi-booking-manager'); ?></h6>
                        </div>
                        <div data-tabs-target-next="#mptbm_order_summary" class="tabItemNext step-place-order" data-open-text="3" data-close-text="" data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
                            <h4 class="circleIcon" data-class>
                                <span class="mp_zero" data-icon></span>
                                <span class="mp_zero" data-text>3</span>
                            </h4>
                            <h6 class="circleTitle" data-class><?php echo mptbm_get_translation('place_order_label', __('Place Order', 'ecab-taxi-booking-manager')); ?></h6>
                        </div>
                    </div>
                    <div class="tabsContentNext">
                        <div data-tabs-next="#mptbm_search_result" class="mptbm_map_search_result">
                            <?php 
                            // Use cached content but with fresh pricing calculations
                            // This preserves the search context while ensuring all vehicles show with correct prices
                            
                            if (!empty($display_content)) {
                                // Remove the outer div wrapper if it exists in the content
                                $clean_content = $display_content;
                                if (strpos($clean_content, '<div data-tabs-next="#mptbm_search_result" class="mptbm_map_search_result">') === 0) {
                                    // Remove the opening div
                                    $clean_content = preg_replace('/^<div[^>]*class="mptbm_map_search_result"[^>]*>/', '', $clean_content);
                                    // Remove the closing div
                                    $clean_content = preg_replace('/<\/div>\s*$/', '', $clean_content);
                                }
                                
                                // Output the cleaned content
                                echo $clean_content;
                            } else {
                                echo '<div style="text-align: center; padding: 50px; color: #666;">';
                                echo '<h3>No content available</h3>';
                                echo '<p>Please go back and search for transport again.</p>';
                                echo '<a href="' . home_url() . '" style="color: #007cba; text-decoration: none;">← Back to Home</a>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Force refresh of all pricing displays and vehicle visibility on transport result page
    // This ensures object caching doesn't affect the display of all available vehicles
    
    setTimeout(function() {
        // Show all vehicles that might be hidden by caching
        $('.mptbm_booking_item').each(function() {
            var $item = $(this);
            
            // Remove any hidden classes that might be applied by caching
            $item.removeClass('mptbm_booking_item_hidden');
            
            // Ensure the item is visible
            $item.show().css({
                'display': 'flex !important',
                'visibility': 'visible !important',
                'opacity': '1 !important'
            });
        });
        
        // Show any vehicles that might be hidden due to caching
        $('.mptbm_booking_item_hidden').removeClass('mptbm_booking_item_hidden').show();
        
        // If all vehicles were hidden and "No Transport Available" is showing, hide it
        if ($('.mptbm_booking_item:visible').length > 0) {
            $('.geo-fence-no-transport').hide();
        }
        
        // Force refresh of price calculations by triggering mptbm_price_calculation
        if (typeof mptbm_price_calculation === 'function') {
            $('.mptbm_booking_item').each(function() {
                mptbm_price_calculation($(this));
            });
        }
        
        
    }, 100); // Small delay to ensure DOM is ready
});
</script>

<?php
// Handle theme footer - works with both classic and block themes
if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
    // For block themes, provide footer
    ?>
    <!-- Block Theme Footer -->
    <footer class="wp-block-template-part site-footer" style="background: #333; color: #fff; padding: 30px 0; margin-top: 50px;">
        <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 15px; text-align: center;">
            <p style="margin: 0;">
                &copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. 
                <?php printf(esc_html__('Powered by %s', 'ecab-taxi-booking-manager'), '<a href="https://wordpress.org/" style="color: #fff;">WordPress</a>'); ?>
            </p>
        </div>
    </footer>
    
    <?php wp_footer(); ?>
    </body>
    </html>
    <?php
} else {
    // For classic themes, try to load footer
    get_footer();
}
?>
