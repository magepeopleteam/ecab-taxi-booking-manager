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
        }

        public function mptbm_right_side_section( $post_id ) {

            self::mptbm_right_feature_image( $post_id );

            self::mptbm_right_quick_tipcs( $post_id );

        }

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

    }

    new MPTBM_Right_Side_Content_Settings();
}

