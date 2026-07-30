<?php

	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.

	// Block themes (Twenty Twenty-Five and other FSE themes) ship with no
	// header.php/footer.php at all, so a plain get_header()/get_footer() call
	// falls through to WordPress's own long-deprecated wp-includes/theme-compat/
	// header.php - a bare, unstyled 2005-era shell with no navigation and none of
	// the theme's real global-styles padding, nothing like the theme's actual
	// (block-based) header/footer shown on every other page. Render the real
	// header/footer template parts via block_header_area()/block_footer_area()
	// when the active theme is block-based; classic themes keep using
	// get_header()/get_footer() exactly as before.
	$mptbm_is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	if ( $mptbm_is_block_theme ) {
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
<?php
		block_header_area();
	} else {
		get_header();
	}
	the_post();
	do_action( 'mptbm_single_page_before_wrapper' );
	if ( post_password_required() ) {
		echo wp_kses_post(get_the_password_form()); // WPCS: XSS ok.
	} else {
		do_action( 'woocommerce_before_single_product' );
		$post_id                   = get_the_id();
		$template_name = MP_Global_Function::get_post_info( $post_id, 'mptbm_theme_file', 'default.php' );
		$price_based    = MP_Global_Function::get_post_info( $post_id, 'mptbm_price_based' );
		include_once( MPTBM_Function::details_template_path() );
	}
	do_action( 'mptbm_single_page_after_wrapper' );
	if ( $mptbm_is_block_theme ) {
		block_footer_area();
		?>
</div><!-- .wp-site-blocks -->
<?php wp_footer(); ?>
</body>
</html>
<?php
	} else {
		get_footer();
	}
