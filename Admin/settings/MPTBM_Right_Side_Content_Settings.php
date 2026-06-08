<?php
/*
   * @Author 		rubelcuet10@gmail.com
   * Copyright: 	mage-people.com
   */
if ( ! defined( 'ABSPATH' ) ) {
    die;
} // Cannot access pages directly.
if ( ! class_exists('MPTBM_Right_Side_Content_Settings') ) {
    class MPTBM_Right_Side_Content_Settings{
        public function __construct(){
            add_action('mptbm_right_side_section', [ $this, 'mptbm_right_side_section'], 10, 1 );

            add_action('wp_ajax_mptbm_taxi_save_category', [ $this, 'mptbm_taxi_save_category' ] );
            add_action('wp_ajax_mptbm_taxi_save_post_category', [ $this, 'mptbm_taxi_save_post_category' ]);

            add_action('wp_ajax_mptbm_taxi_add_tag',  [ $this, 'mptbm_taxi_add_tag' ] );
            add_action('wp_ajax_mptbm_taxi_remove_tag', [ $this, 'mptbm_taxi_remove_tag' ] );


        }


        public function mptbm_right_side_section( $post_id ) {

            self::mptbm_right_feature_image( $post_id );

            self::mptbm_right_quick_tipcs( $post_id );

            self::category_tag_add( $post_id );

        }

        public static function category_tag_add( $post_id ){
            $saved_category = get_post_meta($post_id, 'mptbm_taxi_category_id', true);

            $categories = get_option('mptbm_taxi_categories', []);
            $nonce = wp_create_nonce('mptbm_taxi_nonce');
            ?>
            <div class="mptbm_taxi_category_container">
                <input type="hidden"
                       id="mptbm_taxi_nonce"
                       value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden"
                       id="mptbm_taxi_post_id"
                       value="<?php echo esc_attr($post_id); ?>">

                <div class="mptbm_taxi_category_card">
                    <label class="mptbm_taxi_category_label">Category</label>
                    <span class="mptbm_taxi_category_subtext">
                        Select vehicle category
                    </span>

                    <div class="mptbm_taxi_category_flex_group" id="mptbm_taxi_category_flex_group">

                        <select id="mptbm_taxi_category_dropdown"
                                class="mptbm_taxi_category_select">

                            <option value="" disabled>Select Category</option>

                            <?php foreach ($categories as $cat) : ?>

                                <option value="<?php echo esc_attr($cat['id']); ?>"
                                    <?php selected($saved_category, $cat['id']); ?>>

                                    <?php echo esc_html($cat['name']); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <button type="button"
                                id="mptbm_taxi_category_open_popup"
                                class="mptbm_taxi_category_btn_add">
                            +
                        </button>

                    </div>
                    <p class="mptbm_taxi_category_helptext">
                        Choose the category that best fits this transport.
                    </p>
                </div>



                <?php
                $tags = get_post_meta($post_id, 'mptbm_taxi_tags', true);
                if (!is_array($tags)) {
                    $tags = [];
                }
                ?>

                <div class="mptbm_taxi_category_card">
                    <label class="mptbm_taxi_category_label">Tags</label>
                    <span class="mptbm_taxi_category_subtext">
                        Add keywords for searching
                    </span>
                    <div class="mptbm_taxi_category_tag_input_wrapper">
                        <input type="text"
                               id="mptbm_taxi_category_tag_input"
                               class="mptbm_taxi_category_input"
                               placeholder="Add a tag and press Enter">
                    </div>
                    <div id="mptbm_taxi_category_tags_list"
                         class="mptbm_taxi_category_tags_container">
                        <?php foreach ($tags as $tag) : ?>
                            <span class="mptbm_taxi_category_badge"
                                  data-tag="<?php echo esc_attr($tag); ?>">
                                <?php echo esc_html($tag); ?>
                                <i class="mptbm_taxi_category_remove_tag">&times;</i>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php self::category_add_popup();?>
            </div>
        <?php }

        public static function mptbm_right_quick_tipcs( $post_id ){ ?>
            <div class="mptbm_quick_tips_card">

                <div class="mptbm_quick_tips_badge">
                    <?php esc_html_e( 'Quick Help', 'ecab-taxi-booking-manager' ); ?>
                </div>

                <div class="mptbm_quick_tips_title">
                    <?php esc_html_e( 'Quick Tips & Documentation', 'ecab-taxi-booking-manager' ); ?>
                </div>

                <div class="mptbm_quick_tips_desc">
                    <?php esc_html_e( 'Configure your taxi booking settings easily using our step-by-step documentation and setup guide.', 'ecab-taxi-booking-manager' ); ?>
                </div>

                <ul class="mptbm_quick_tips_list">
                    <li><?php esc_html_e( 'How do extra service settings in transport be set up?', 'ecab-taxi-booking-manager' ); ?></li>
                    <li><?php esc_html_e( 'Transport Booking ShortCode', 'ecab-taxi-booking-manager' ); ?></li>
                    <li><?php esc_html_e( 'How does the price-based model work on transportation?', 'ecab-taxi-booking-manager' ); ?></li>
                    <li><?php esc_html_e( 'What is the initial price in transport?', 'ecab-taxi-booking-manager' ); ?></li>
                </ul>

                <a href="https://docs.mage-people.com/ecab-taxi-booking-manager/" class="mptbm_quick_tips_btn">
                    <?php esc_html_e( 'View Documentation', 'ecab-taxi-booking-manager' ); ?>
                </a>

            </div>
        <?php }
        public static function mptbm_right_feature_image( $post_id ) {
            $image_id = get_post_thumbnail_id( $post_id );
            $image_url = '';
            if ( $image_id ) {
                $image_url = wp_get_attachment_image_url( $image_id, 'medium' );
            }
            ?>
            <div class="mptbm_feature_image_card">
                <div class="mptbm_feature_image_head">
                    <h2><?php esc_html_e( 'Featured Image', 'ecab-taxi-booking-manager' ); ?></h2>
                    <p><?php esc_html_e( 'Main event thumbnail', 'ecab-taxi-booking-manager' ); ?></p>
                </div>
                <div class="mptbm_feature_image_body">
                    <input
                        type="hidden"
                        id="mptbm_feature_image_id"
                        name="mptbm_feature_image_id"
                        value="<?php echo esc_attr( $image_id ); ?>"
                    >
                    <div
                        class="mptbm_feature_image_wrapper"
                        data-has-image="<?php echo $image_id ? '1' : '0'; ?>"
                    >
                        <div class="mptbm_feature_image_preview">

                            <?php if ( $image_url ) : ?>
                                <img
                                    src="<?php echo esc_url( $image_url ); ?>"
                                    alt=""
                                >
                            <?php else : ?>
                                <div class="mptbm_feature_image_placeholder">
                                    <?php esc_html_e( 'Select Transportation Image', 'ecab-taxi-booking-manager' ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mptbm_feature_image_actions">
                            <button
                                type="button"
                                class="button button-primary mptbm_feature_image_select"
                            >
                                <?php esc_html_e( 'Select Image', 'ecab-taxi-booking-manager' ); ?>
                            </button>
                            <button
                                type="button"
                                class="button mptbm_feature_image_remove"
                            >
                                <?php esc_html_e( 'Remove', 'ecab-taxi-booking-manager' ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
        public function mptbm_taxi_save_post_category() {
            if (
                !isset($_POST['nonce']) ||
                !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mptbm_taxi_nonce')
            ) {
                wp_send_json_error([
                    'message' => 'Security check failed'
                ]);
            }

            $post_id = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : "" ;
            $category_id = isset( $_POST['category_id'] ) ? sanitize_text_field( wp_unslash( $_POST['category_id'] ) ) : '' ;

            if (!$post_id || !$category_id) {
                wp_send_json_error(['message' => 'Invalid data']);
            }

            update_post_meta(
                $post_id,
                'mptbm_taxi_category_id',
                $category_id
            );

            wp_send_json_success(['message' => 'Category saved']);
        }
        function mptbm_taxi_save_category() {
            if (
                !isset($_POST['nonce']) ||
                !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mptbm_taxi_nonce')
            ) {
                wp_send_json_error([
                    'message' => 'Security check failed'
                ]);
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error([
                    'message' => 'Permission denied'
                ]);
            }

            $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
            $type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
            $desc = isset( $_POST['desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['desc'] ) ) : '';

            if (empty($name)) {
                wp_send_json_error([
                    'message' => 'Category name is required'
                ]);
            }
            $categories = get_option('mptbm_taxi_categories', []);
            if (!is_array($categories)) {
                $categories = [];
            }
            $categories[] = [
                'id'   => time(),
                'name' => $name,
                'type' => $type,
                'desc' => $desc,
            ];
            update_option('mptbm_taxi_categories', $categories);

            ob_start();
            ?>
            <select id="mptbm_taxi_category_dropdown" class="mptbm_taxi_category_select">
                <option value=""><?php esc_attr_e( 'Select Category', 'ecab-taxi-booking-manager' );?></option>
                <?php foreach ($categories as $category) : ?>
                    <option value="<?php echo esc_attr($category['id']); ?>">
                        <?php echo esc_html($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php
            $html = ob_get_clean();

            wp_send_json_success([
                'message' => 'Category saved successfully',
                'data'    => $categories,
                'category_html_data'    => $html
            ]);
        }

        public static function category_add_popup(){ ?>
            <div id="mptbm_taxi_category_modal" class="mptbm_taxi_category_modal_overlay">

                <div class="mptbm_taxi_category_modal_content">

                    <div class="mptbm_taxi_category_modal_header">
                        <h3>Create New Category</h3>
                        <span id="mptbm_taxi_category_close_popup"
                              class="mptbm_taxi_category_modal_close">&times;</span>
                    </div>

                    <div class="mptbm_taxi_category_modal_body">
                        <div class="mptbm_taxi_category_form_group">
                            <label class="mptbm_taxi_category_modal_label">Category Name</label>
                            <input type="text"
                                   id="mptbm_taxi_category_new_name"
                                   class="mptbm_taxi_category_input"
                                   placeholder="e.g., Electric Van">
                        </div>
                        <div class="mptbm_taxi_category_form_group">
                            <label class="mptbm_taxi_category_modal_label">Category Type</label>
                            <select id="mptbm_taxi_category_type"
                                    class="mptbm_taxi_category_input">
                                <option value="standard">Standard</option>
                                <option value="premium">Premium</option>
                                <option value="luxury">Luxury</option>
                            </select>
                        </div>
                        <div class="mptbm_taxi_category_form_group">
                            <label class="mptbm_taxi_category_modal_label">Description</label>
                            <textarea id="mptbm_taxi_category_desc"
                                      class="mptbm_taxi_category_input"
                                      placeholder="Write description..."></textarea>
                        </div>
                    </div>
                    <div class="mptbm_taxi_category_modal_footer">
                        <button type="button"
                                id="mptbm_taxi_category_save_btn"
                                class="mptbm_taxi_category_btn_save">
                            Save Category
                        </button>
                    </div>

                </div>

            </div>
        <?php }

        public function mptbm_taxi_add_tag() {
            if (
                !isset($_POST['nonce']) ||
                !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mptbm_taxi_nonce')
            ) {
                wp_send_json_error(['message' => 'Security failed']);
            }
            $post_id = isset( $_POST['post_id'] ) ? intval($_POST['post_id']) : '';
            $tag = isset( $_POST['tag'] ) ?sanitize_text_field( wp_unslash( $_POST['tag'] ) ) : '';
            if (!$post_id || !$tag) {
                wp_send_json_error(['message' => 'Invalid data']);
            }
            $tags = get_post_meta($post_id, 'mptbm_taxi_tags', true);
            if (!is_array($tags)) {
                $tags = [];
            }
            if (!in_array($tag, $tags)) {
                $tags[] = $tag;
            }
            update_post_meta($post_id, 'mptbm_taxi_tags', $tags);
            wp_send_json_success([
                'message' => 'Tag saved successfully',
                'tags'    => $tags
            ]);
        }

        function mptbm_taxi_remove_tag() {
            if (
                !isset($_POST['nonce']) ||
                !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mptbm_taxi_nonce')
            ) {
                wp_send_json_error(['message' => 'Security failed']);
            }

            $post_id = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : '';
            $tag = isset( $_POST['tag'] ) ? sanitize_text_field( wp_unslash(  $_POST['tag'] ) ) : '';

            if (!$post_id || !$tag) {
                wp_send_json_error(['message' => 'Invalid data']);
            }
            $tags = get_post_meta($post_id, 'mptbm_taxi_tags', true);
            if (!is_array($tags)) {
                $tags = [];
            }
            $tags = array_values(array_filter($tags, function ($t) use ($tag) {
                return $t !== $tag;
            }));
            update_post_meta($post_id, 'mptbm_taxi_tags', $tags);
            wp_send_json_success([
                'message' => 'Tag removed',
                'tags' => $tags
            ]);
        }

    }

    new MPTBM_Right_Side_Content_Settings();
}

