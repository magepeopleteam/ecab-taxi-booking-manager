<?php
	// Template Name: Default Theme
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}
	$post_id = $post_id ?? get_the_id();

	$thumbnail   = MP_Global_Function::get_image_url( $post_id );
	$label       = MPTBM_Function::get_name();
	$extra_info  = MP_Global_Function::get_post_info( $post_id, 'mptbm_extra_info', '' );
	$max_passenger = MP_Global_Function::get_post_info( $post_id, 'mptbm_maximum_passenger', '' );
	$max_bag       = MP_Global_Function::get_post_info( $post_id, 'mptbm_maximum_bag', '' );

	// Same real, admin-filled vehicle-identity fields shown in the search results
	// "View Details" panel (Admin > Vehicle Specification) - never fabricated.
	$vehicle_spec_rows = array(
		'make_model' => array( esc_html__( 'Make & Model', 'ecab-taxi-booking-manager' ), MP_Global_Function::get_post_info( $post_id, 'mptbm_spec_make_model', '' ) ),
		'year'       => array( esc_html__( 'Year', 'ecab-taxi-booking-manager' ), MP_Global_Function::get_post_info( $post_id, 'mptbm_spec_year', '' ) ),
		'color'      => array( esc_html__( 'Color', 'ecab-taxi-booking-manager' ), MP_Global_Function::get_post_info( $post_id, 'mptbm_spec_color', '' ) ),
		'engine'     => array( esc_html__( 'Engine', 'ecab-taxi-booking-manager' ), MP_Global_Function::get_post_info( $post_id, 'mptbm_spec_engine', '' ) ),
		'plate'      => array( esc_html__( 'Plate Class', 'ecab-taxi-booking-manager' ), MP_Global_Function::get_post_info( $post_id, 'mptbm_spec_plate', '' ) ),
		'mileage'    => array( esc_html__( 'Mileage', 'ecab-taxi-booking-manager' ), MP_Global_Function::get_post_info( $post_id, 'mptbm_spec_mileage', '' ) ),
	);
	$vehicle_spec_rows = array_filter( $vehicle_spec_rows, function ( $row ) {
		return trim( (string) $row[1] ) !== '';
	} );

	// Feature chips (Admin > Vehicle Features) - full list, this page has room
	// to show every one rather than the 6-item cap used on the search card.
	$display_features = MP_Global_Function::get_post_info( $post_id, 'display_mptbm_features', 'on' );
	$all_features      = MP_Global_Function::get_post_info( $post_id, 'mptbm_features' );
	$clean_features    = array();
	if ( $display_features === 'on' && is_array( $all_features ) ) {
		$seen = array();
		foreach ( $all_features as $feature ) {
			$f_text = isset( $feature['text'] ) ? trim( $feature['text'] ) : '';
			if ( $f_text === '' || mb_strlen( $f_text ) < 2 || in_array( strtolower( $f_text ), $seen, true ) ) {
				continue;
			}
			$seen[]           = strtolower( $f_text );
			$clean_features[] = $feature;
		}
	}

	// Optional add-ons attached to this vehicle (Admin > Extra Services) - shown
	// here read-only; selecting/adding them to a booking happens in the actual
	// booking flow, not on this informational page.
	$display_extra_services = MP_Global_Function::get_post_info( $post_id, 'display_mptbm_extra_services', 'on' );
	$extra_service_ref_id    = MP_Global_Function::get_post_info( $post_id, 'mptbm_extra_services_id', $post_id );
	$extra_services          = MP_Global_Function::get_post_info( $extra_service_ref_id, 'mptbm_extra_service_infos', array() );
	$extra_services          = ( $display_extra_services === 'on' && is_array( $extra_services ) ) ? $extra_services : array();

	$rating_enabled = class_exists( 'MPTBM_Reviews' ) && MPTBM_Reviews::reviews_enabled( $post_id );

	// "Check Availability" points at the site's search/results page (Settings >
	// General > View Search Result Page), the same page the disabled inline
	// search form on this template would otherwise have submitted to.
	$results_slug = MP_Global_Function::get_settings( 'mptbm_general_settings', 'enable_view_search_result_page' );
	$results_slug = $results_slug ?: 'transport-result';
	$results_page = get_page_by_path( $results_slug );
	$results_url  = $results_page ? get_permalink( $results_page ) : home_url( '/' );
?>
	<div class="mpStyle mptbm_default_theme">
		<div class="mpContainer">

			<div class="mptbm-vpage-hero">
				<?php if ( $thumbnail ) : ?>
					<div class="mptbm-vpage-hero-image">
						<img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>">
					</div>
				<?php endif; ?>
				<div class="mptbm-vpage-hero-body">
					<span class="mptbm-vpage-eyebrow"><?php echo esc_html( $label ); ?></span>
					<h1 class="mptbm-vpage-title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
					<?php if ( $rating_enabled ) : ?>
						<div class="mptbm_vehicle_rating"><?php echo MPTBM_Reviews::get_rating_html( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<?php endif; ?>
					<?php if ( $max_passenger !== '' || $max_bag !== '' ) : ?>
						<div class="mptbm-vpage-quickfacts">
							<?php if ( $max_passenger !== '' ) : ?>
								<span><i class="fas fa-user" aria-hidden="true"></i> <?php echo esc_html( $max_passenger ); ?> <?php esc_html_e( 'Passengers', 'ecab-taxi-booking-manager' ); ?></span>
							<?php endif; ?>
							<?php if ( $max_bag !== '' ) : ?>
								<span><i class="fas fa-suitcase" aria-hidden="true"></i> <?php echo esc_html( $max_bag ); ?> <?php esc_html_e( 'Bags', 'ecab-taxi-booking-manager' ); ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<a class="mptbm-vpage-cta" href="<?php echo esc_url( $results_url ); ?>">
						<?php esc_html_e( 'Check Availability', 'ecab-taxi-booking-manager' ); ?>
					</a>
				</div>
			</div>

			<div class="mptbm-vpage-layout">
				<div class="mptbm-vpage-col-main">

					<?php if ( get_the_content( null, false, $post_id ) ) : ?>
						<div class="mptbm_details_block">
							<h4><?php esc_html_e( 'About This Vehicle', 'ecab-taxi-booking-manager' ); ?></h4>
							<div class="mptbm-vpage-content"><?php echo apply_filters( 'the_content', get_the_content( null, false, $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( trim( (string) $extra_info ) ) ) : ?>
						<div class="mptbm_details_block">
							<h4><?php esc_html_e( 'Additional Notes', 'ecab-taxi-booking-manager' ); ?></h4>
							<div class="mptbm_details_extra_info"><?php echo wp_kses_post( $extra_info ); ?></div>
						</div>
					<?php endif; ?>

					<?php if ( count( $vehicle_spec_rows ) > 0 ) : ?>
						<div class="mptbm_details_block">
							<h4><?php esc_html_e( 'Vehicle Specifications', 'ecab-taxi-booking-manager' ); ?></h4>
							<div class="mptbm_spec_table">
								<?php foreach ( $vehicle_spec_rows as $row ) : ?>
									<div class="mptbm_spec_row">
										<span><?php echo esc_html( $row[0] ); ?></span>
										<span><?php echo esc_html( $row[1] ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( count( $clean_features ) > 0 ) : ?>
						<div class="mptbm_details_block">
							<h4><?php esc_html_e( 'Features', 'ecab-taxi-booking-manager' ); ?></h4>
							<ul class="mptbm_details_spec_list">
								<?php foreach ( $clean_features as $feature ) :
									$f_label = isset( $feature['label'] ) ? trim( $feature['label'] ) : '';
									$f_text  = isset( $feature['text'] ) ? trim( $feature['text'] ) : '';
									$f_icon  = isset( $feature['icon'] ) ? $feature['icon'] : '';
									$show_label = ( $f_label !== '' && strcasecmp( $f_label, $f_text ) !== 0 );
								?>
									<li>
										<?php if ( $f_icon ) : ?><i class="<?php echo esc_attr( $f_icon ); ?>" aria-hidden="true"></i><?php endif; ?>
										<?php if ( $show_label ) : ?><span class="mptbm_details_spec_label"><?php echo esc_html( $f_label ); ?>:</span><?php endif; ?>
										<span class="mptbm_details_spec_value"><?php echo esc_html( $f_text ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

				</div>
				<div class="mptbm-vpage-col-side">

					<?php if ( count( $extra_services ) > 0 ) : ?>
						<div class="mptbm_details_block">
							<h4><?php esc_html_e( 'Available Add-ons', 'ecab-taxi-booking-manager' ); ?></h4>
							<ul class="mptbm-vpage-addon-list">
								<?php foreach ( $extra_services as $service ) :
									$service_name  = isset( $service['service_name'] ) ? $service['service_name'] : '';
									$service_price = isset( $service['service_price'] ) ? $service['service_price'] : 0;
									if ( $service_name === '' ) {
										continue;
									}
									$wc_price = MP_Global_Function::wc_price( $post_id, $service_price );
								?>
									<li>
										<span><?php echo esc_html( $service_name ); ?></span>
										<strong><?php echo wp_kses_post( MP_Global_Function::price_convert_raw( $wc_price ) ); ?></strong>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<?php do_action( 'mptbm_transport_search_form', $post_id ); ?>
				</div>
			</div>

		</div>
	</div>
<?php do_action( 'mptbm_after_details_page', $post_id ); ?>
