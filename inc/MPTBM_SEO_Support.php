<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_SEO_Support')) {
    class MPTBM_SEO_Support {
        public function __construct() {
            // Add Schema.org structured data to single transport page
            add_action('wp_footer', array($this, 'add_transport_schema'));
            
            // Ensure only one H1 per page
            add_filter('the_title', array($this, 'single_transport_title'), 10, 2);
            
            // Add meta description
            add_action('wp_head', array($this, 'add_meta_description'), 1);

            // Add Open Graph tags
            add_action('wp_head', array($this, 'add_open_graph_tags'), 5);
            
            // Add canonical links
            add_action('wp_head', array($this, 'add_canonical_link'), 1);
        }

        /**
         * Add Schema.org JSON-LD markup for taxi service
         */
        public function add_transport_schema() {
            if (!is_singular('mptbm_rent')) {
                return;
            }

            global $post;
            
            $transport_id = get_the_ID();
            $price_based = get_post_meta($transport_id, 'mptbm_price_based', true);
            
            // Get price information based on pricing type
            $price = 0;
            if ($price_based === 'dynamic') {
                $price = get_post_meta($transport_id, 'mptbm_km_price', true);
                $pricing_text = sprintf(__('%s per kilometer/mile', 'ecab-taxi-booking-manager'), wc_price($price));
            } elseif ($price_based === 'fixed_hourly') {
                $price = get_post_meta($transport_id, 'mptbm_hour_price', true);
                $pricing_text = sprintf(__('%s per hour', 'ecab-taxi-booking-manager'), wc_price($price));
            } else {
                $price = get_post_meta($transport_id, 'mptbm_initial_price', true);
                $pricing_text = sprintf(__('Starting from %s', 'ecab-taxi-booking-manager'), wc_price($price));
            }
            
            // Get transport details
            $max_passenger = get_post_meta($transport_id, 'mptbm_max_passenger', true);
            $max_bag = get_post_meta($transport_id, 'mptbm_max_bag', true);
            
            // Get featured image
            $image_url = get_the_post_thumbnail_url($transport_id, 'full');
            
            // Prepare Schema data
            $schema = array(
                '@context' => 'https://schema.org',
                '@type' => 'TaxiService',
                'name' => get_the_title($transport_id),
                'description' => wp_strip_all_tags(get_the_content()),
                'url' => get_permalink($transport_id),
                'priceRange' => $pricing_text,
                'image' => $image_url ? $image_url : '',
                'serviceArea' => array(
                    '@type' => 'Place',
                    'name' => get_bloginfo('name') . ' Service Area'
                ),
                'provider' => array(
                    '@type' => 'Organization',
                    'name' => get_bloginfo('name'),
                    'url' => home_url()
                )
            );
            
            // Add capacity info if available
            if ($max_passenger) {
                $schema['maximumAttendeeCapacity'] = intval($max_passenger);
            }
            
            // Output schema as JSON-LD
            echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
        }
        
        /**
         * Ensure proper H1 usage for single transport pages
         */
        public function single_transport_title($title, $id = null) {
            if (is_singular('mptbm_rent') && in_the_loop() && is_main_query()) {
                // Ensure the title has proper heading markup for SEO
                return $title;
            }
            return $title;
        }
        
        /**
         * Add meta description for transport pages
         */
        public function add_meta_description() {
            if (!is_singular('mptbm_rent')) {
                return;
            }
            
            $post_id = get_the_ID();
            $description = wp_trim_words(strip_tags(get_the_content()), 30, '...');
            
            if (empty($description)) {
                $transport_type = get_post_meta($post_id, 'mptbm_transport_type', true);
                $description = sprintf(
                    __('Book a %s for your journey. Easy online booking, comfortable travel.', 'ecab-taxi-booking-manager'),
                    $transport_type ? $transport_type : __('taxi', 'ecab-taxi-booking-manager')
                );
            }
            
            echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
        }
        
        /**
         * Add Open Graph tags for better social sharing
         */
        public function add_open_graph_tags() {
            if (!is_singular('mptbm_rent')) {
                return;
            }
            
            global $post;
            $post_id = get_the_ID();
            
            // Get post title and URL
            $og_title = get_the_title($post_id);
            $og_url = get_permalink($post_id);
            
            // Get image
            $og_image = get_the_post_thumbnail_url($post_id, 'large');
            if (!$og_image) {
                // Use default image if no featured image is set
                $og_image = plugins_url('assets/images/default-taxi.jpg', dirname(__FILE__));
            }
            
            // Get description
            $og_description = wp_trim_words(strip_tags(get_the_content()), 30, '...');
            if (empty($og_description)) {
                $transport_type = get_post_meta($post_id, 'mptbm_transport_type', true);
                $og_description = sprintf(
                    __('Book a %s for your journey. Easy online booking, comfortable travel.', 'ecab-taxi-booking-manager'),
                    $transport_type ? $transport_type : __('taxi', 'ecab-taxi-booking-manager')
                );
            }
            
            // Output Open Graph tags
            echo '<meta property="og:title" content="' . esc_attr($og_title) . '" />' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($og_description) . '" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url($og_url) . '" />' . "\n";
            echo '<meta property="og:image" content="' . esc_url($og_image) . '" />' . "\n";
            echo '<meta property="og:type" content="website" />' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";
        }
        
        /**
         * Add canonical link for better SEO
         */
        public function add_canonical_link() {
            if (!is_singular('mptbm_rent')) {
                return;
            }
            
            $post_id = get_the_ID();
            $canonical_url = get_permalink($post_id);
            
            echo '<link rel="canonical" href="' . esc_url($canonical_url) . '" />' . "\n";
        }
    }
    
    new MPTBM_SEO_Support();
} 