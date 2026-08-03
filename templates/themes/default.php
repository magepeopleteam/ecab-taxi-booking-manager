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

	// "Browse All Vehicles" points at the site's search/results page (Settings >
	// General > View Search Result Page).
	$results_slug = MP_Global_Function::get_settings( 'mptbm_general_settings', 'enable_view_search_result_page' );
	$results_slug = $results_slug ?: 'transport-result';
	$results_page = get_page_by_path( $results_slug );
	$results_url  = $results_page ? get_permalink( $results_page ) : home_url( '/' );

	// ---- Pricing summary, derived from the same admin-filled meta the Price
	// Settings tab (Admin > Price) saves for this vehicle - see
	// Admin/settings/MPTBM_Price_Settings.php. Only fields that are actually set
	// are shown, so a vehicle with an unconfigured price model simply shows no
	// price card rather than a fabricated number. The real fare shown to the
	// customer is always recalculated server-side by the booking form below.
	$price_based          = MP_Global_Function::get_post_info( $post_id, 'mptbm_price_based', 'distance' );
	$price_display_type   = MP_Global_Function::get_post_info( $post_id, 'mptbm_price_display_type', 'normal' );
	$custom_price_message = trim( (string) MP_Global_Function::get_post_info( $post_id, 'mptbm_custom_price_message', '' ) );

	$km_price        = MP_Global_Function::get_post_info( $post_id, 'mptbm_km_price', '' );
	$hour_price       = MP_Global_Function::get_post_info( $post_id, 'mptbm_hour_price', '' );
	$initial_price    = MP_Global_Function::get_post_info( $post_id, 'mptbm_initial_price', '' );
	$min_price        = MP_Global_Function::get_post_info( $post_id, 'mptbm_min_price', '' );
	$waiting_price    = MP_Global_Function::get_post_info( $post_id, 'mptbm_waiting_price', '' );
	$fixed_map_price  = MP_Global_Function::get_post_info( $post_id, 'mptbm_fixed_map_price', '' );

	$manual_prices          = MP_Global_Function::get_post_info( $post_id, 'mptbm_manual_price_info', array() );
	$terms_prices           = MP_Global_Function::get_post_info( $post_id, 'mptbm_terms_price_info', array() );
	$fixed_zone_prices      = MP_Global_Function::get_post_info( $post_id, 'mptbm_fixed_zone_price_info', array() );
	$fixed_map_route_prices = MP_Global_Function::get_post_info( $post_id, 'mptbm_fixed_map_route_price_info', array() );
	$manual_prices          = is_array( $manual_prices ) ? $manual_prices : array();
	$terms_prices           = is_array( $terms_prices ) ? $terms_prices : array();
	$fixed_zone_prices      = is_array( $fixed_zone_prices ) ? $fixed_zone_prices : array();
	$fixed_map_route_prices = is_array( $fixed_map_route_prices ) ? $fixed_map_route_prices : array();

	// "Fixed Zone" rows store start/end as term_{id} (Location taxonomy) or
	// post_{id} (Operation Area) - resolve to a human-readable name for display.
	$zone_name = function ( $key ) {
		if ( strpos( $key, 'term_' ) === 0 ) {
			$term = get_term( (int) substr( $key, 5 ), 'locations' );
			return ( $term && ! is_wp_error( $term ) ) ? $term->name : $key;
		}
		if ( strpos( $key, 'post_' ) === 0 ) {
			$title = get_the_title( (int) substr( $key, 5 ) );
			return $title !== '' ? $title : $key;
		}
		return $key;
	};

	// Only read the route table(s) that belong to this vehicle's *currently
	// active* pricing model - a vehicle that was previously switched away from
	// manual/fixed zone/fixed map can still have old rows sitting in that meta,
	// and showing those alongside a different active model would be misleading.
	$route_rows = array();
	if ( 'manual' === $price_based ) {
		foreach ( $manual_prices as $row ) {
			if ( empty( $row['start_location'] ) || empty( $row['end_location'] ) || '' === $row['price'] ) {
				continue;
			}
			$route_rows[] = array( $row['start_location'], $row['end_location'], (float) $row['price'] );
		}
		foreach ( $terms_prices as $row ) {
			if ( empty( $row['start_location'] ) || empty( $row['end_location'] ) || '' === $row['price'] ) {
				continue;
			}
			$start_term   = get_term_by( 'slug', $row['start_location'], 'locations' );
			$end_term     = get_term_by( 'slug', $row['end_location'], 'locations' );
			$route_rows[] = array( $start_term ? $start_term->name : $row['start_location'], $end_term ? $end_term->name : $row['end_location'], (float) $row['price'] );
		}
	} elseif ( 'fixed_zone' === $price_based ) {
		foreach ( $fixed_zone_prices as $row ) {
			if ( empty( $row['start_location'] ) || empty( $row['end_location'] ) || '' === $row['price'] ) {
				continue;
			}
			$route_rows[] = array( $zone_name( $row['start_location'] ), $zone_name( $row['end_location'] ), (float) $row['price'] );
		}
	} elseif ( 'fixed_distance' === $price_based ) {
		// "Fixed with Map" area/location route overrides (Admin > Price >
		// Fixed Map Route Overrides) - same post_{id}/term_{id} shape as fixed zone.
		foreach ( $fixed_map_route_prices as $row ) {
			if ( empty( $row['start_location'] ) || empty( $row['end_location'] ) || '' === $row['price'] ) {
				continue;
			}
			$route_rows[] = array( $zone_name( $row['start_location'] ), $zone_name( $row['end_location'] ), (float) $row['price'] );
		}
	}
	$route_visible_count = 8;
	$route_total          = count( $route_rows );

	$price_headline = '';
	$price_unit     = '';
	$price_note     = '';
	$price_rows     = array();
	$price_icon     = 'fas fa-tags';

	switch ( $price_based ) {
		case 'distance':
			$price_icon = 'fas fa-route';
			if ( '' !== $km_price ) {
				$price_headline = MP_Global_Function::format_price( $km_price );
				$price_unit     = esc_html__( 'per km', 'ecab-taxi-booking-manager' );
			}
			break;
		case 'duration':
			$price_icon = 'fas fa-clock';
			if ( '' !== $hour_price ) {
				$price_headline = MP_Global_Function::format_price( $hour_price );
				$price_unit     = esc_html__( 'per hour', 'ecab-taxi-booking-manager' );
			}
			break;
		case 'distance_duration':
			$price_icon = 'fas fa-route';
			if ( '' !== $km_price ) {
				$price_headline = MP_Global_Function::format_price( $km_price );
				$price_unit     = esc_html__( 'per km', 'ecab-taxi-booking-manager' );
			}
			if ( '' !== $hour_price ) {
				$price_rows[] = array( esc_html__( 'Per Hour', 'ecab-taxi-booking-manager' ), MP_Global_Function::format_price( $hour_price ) );
			}
			break;
		case 'inclusive':
			$price_icon = 'fas fa-tags';
			if ( '' !== $km_price ) {
				$price_headline = MP_Global_Function::format_price( $km_price );
				$price_unit     = esc_html__( 'per km', 'ecab-taxi-booking-manager' );
			} elseif ( '' !== $hour_price ) {
				$price_headline = MP_Global_Function::format_price( $hour_price );
				$price_unit     = esc_html__( 'per hour', 'ecab-taxi-booking-manager' );
			}
			break;
		case 'fixed_hourly':
			$price_icon = 'fas fa-clock';
			if ( '' !== $hour_price ) {
				$price_headline = MP_Global_Function::format_price( $hour_price );
				$price_unit     = esc_html__( 'per hour package', 'ecab-taxi-booking-manager' );
			}
			break;
		case 'fixed_distance':
			$price_icon = 'fas fa-map-marked-alt';
			if ( '' !== $fixed_map_price ) {
				$price_headline = MP_Global_Function::format_price( $fixed_map_price );
				$price_unit     = esc_html__( 'fixed fare', 'ecab-taxi-booking-manager' );
			}
			if ( $route_total > 0 ) {
				$price_note = esc_html__( 'See the fixed area/location fares below.', 'ecab-taxi-booking-manager' );
			}
			break;
		case 'manual':
		case 'fixed_zone':
			$price_icon = 'fas fa-map-marker-alt';
			if ( $route_total > 0 ) {
				$lowest         = min( wp_list_pluck( $route_rows, 2 ) );
				$price_headline = MP_Global_Function::format_price( $lowest );
				$price_unit     = esc_html__( 'starting fare', 'ecab-taxi-booking-manager' );
				$price_note     = esc_html__( 'Final fare depends on your pickup & drop-off - see routes below.', 'ecab-taxi-booking-manager' );
			}
			break;
	}

	if ( '' !== $initial_price && (float) $initial_price > 0 && in_array( $price_based, array( 'distance', 'duration', 'distance_duration', 'inclusive' ), true ) ) {
		$price_rows[] = array( esc_html__( 'Initial / Base Price', 'ecab-taxi-booking-manager' ), MP_Global_Function::format_price( $initial_price ) );
	}
	if ( '' !== $min_price && (float) $min_price > 0 ) {
		$price_rows[] = array( esc_html__( 'Minimum Fare', 'ecab-taxi-booking-manager' ), MP_Global_Function::format_price( $min_price ) );
	}
	if ( '' !== $waiting_price && (float) $waiting_price > 0 ) {
		$price_rows[] = array( esc_html__( 'Waiting Price / Hour', 'ecab-taxi-booking-manager' ), MP_Global_Function::format_price( $waiting_price ) );
	}

	if ( 'zero' === $price_display_type ) {
		$price_headline = MP_Global_Function::format_price( 0 );
		$price_unit     = '';
		$price_rows     = array();
		$price_note     = '';
	} elseif ( 'custom_message' === $price_display_type && '' !== $custom_price_message ) {
		$price_headline = '';
		$price_unit     = '';
	}

	$show_price_card = ( '' !== $price_headline ) || ( 'custom_message' === $price_display_type && '' !== $custom_price_message ) || $route_total > 0;

	// ---- Service areas (Admin > Operation Area tab) - real per-vehicle zone
	// assignment. Shown so customers know where this vehicle actually operates
	// before they search, since a pickup outside these areas will silently
	// return no results for it (see choose_vehicles.php's geo-fence matching).
	$operation_area_type = MP_Global_Function::get_post_info( $post_id, 'mptbm_operation_area_type', '' );
	$selected_area_ids    = MP_Global_Function::get_post_info( $post_id, 'mptbm_selected_operation_areas', array() );
	$selected_area_ids    = is_array( $selected_area_ids ) ? array_map( 'absint', $selected_area_ids ) : array();
	$service_area_names   = array();
	foreach ( $selected_area_ids as $area_id ) {
		if ( $area_id && get_post_status( $area_id ) === 'publish' ) {
			$area_title = get_the_title( $area_id );
			if ( '' !== $area_title ) {
				$service_area_names[] = $area_title;
			}
		}
	}
	$service_area_note = '';
	switch ( $operation_area_type ) {
		case 'fixed-operation-area-type':
			$service_area_note = esc_html__( 'Both pickup and drop-off must be within these areas.', 'ecab-taxi-booking-manager' );
			break;
		case 'fixed-map-operation-area-type':
			$service_area_note = esc_html__( 'Pickup must be within one of these areas.', 'ecab-taxi-booking-manager' );
			break;
		case 'geo-fence-operation-area-type':
			$service_area_note = esc_html__( 'Available for trips between these regions.', 'ecab-taxi-booking-manager' );
			break;
		case 'geo-matched-operation-area-type':
			$service_area_note = esc_html__( 'Operates within this area.', 'ecab-taxi-booking-manager' );
			break;
	}

	// Booking widget must match this vehicle's own pricing model, otherwise the
	// shortcode defaults to 'dynamic' (distance/duration) and shows the wrong
	// fields for a manual/fixed-hourly/fixed-distance vehicle.
	// 'manual' uses the single-tab "Flat rate" widget (tab=yes, tabs=manual) so
	// pickup/drop-off render as the Location dropdowns, matching the same
	// Route Planning card already used elsewhere on the site - not the generic
	// map/address autocomplete the plain price_based='manual' attribute gives.
	$booking_shortcode_map = array(
		'fixed_distance' => 'fixed_map',
		'fixed_hourly'   => 'fixed_hourly',
	);
	if ( 'manual' === $price_based ) {
		$booking_shortcode = "[mptbm_booking tab='yes' tabs='manual']";
	} elseif ( isset( $booking_shortcode_map[ $price_based ] ) ) {
		$booking_shortcode = "[mptbm_booking price_based='" . $booking_shortcode_map[ $price_based ] . "']";
	} else {
		$booking_shortcode = '[mptbm_booking]';
	}
?>
	<div class="mpStyle mptbm_default_theme">
		<div class="mpContainer">

			<div class="mptbm-vpage-hero <?php echo $thumbnail ? 'mptbm-vpage-hero--photo' : 'mptbm-vpage-hero--flat'; ?>">
				<?php if ( $thumbnail ) : ?>
					<img class="mptbm-vpage-hero-bg" src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>">
					<div class="mptbm-vpage-hero-scrim"></div>
					<?php if ( $show_price_card && '' !== $price_headline ) : ?>
						<span class="mptbm-vpage-price-tag">
							<strong><?php echo wp_kses_post( $price_headline ); ?></strong>
							<?php if ( '' !== $price_unit ) : ?><small><?php echo esc_html( $price_unit ); ?></small><?php endif; ?>
						</span>
					<?php endif; ?>
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
					<div class="mptbm-vpage-cta-row">
						<a class="mptbm-vpage-cta" href="#mptbm-vpage-book">
							<?php esc_html_e( 'Book Now', 'ecab-taxi-booking-manager' ); ?>
							<i class="fas fa-arrow-right" aria-hidden="true"></i>
						</a>
						<a class="mptbm-vpage-cta-secondary" href="<?php echo esc_url( $results_url ); ?>">
							<?php esc_html_e( 'Browse All Vehicles', 'ecab-taxi-booking-manager' ); ?>
						</a>
					</div>
				</div>
			</div>

			<div class="mptbm-vpage-layout">
				<div class="mptbm-vpage-col-main">

					<?php if ( $show_price_card ) : ?>
						<div class="mptbm_details_block mptbm-vpage-price-card">
							<h4><?php esc_html_e( 'Pricing', 'ecab-taxi-booking-manager' ); ?></h4>
							<?php if ( 'custom_message' === $price_display_type && '' !== $custom_price_message ) : ?>
								<p class="mptbm-vpage-price-message"><?php echo esc_html( $custom_price_message ); ?></p>
							<?php else : ?>
								<?php if ( '' !== $price_headline ) : ?>
									<div class="mptbm-vpage-price-headline">
										<i class="<?php echo esc_attr( $price_icon ); ?>" aria-hidden="true"></i>
										<span class="mptbm-vpage-price-amount"><?php echo wp_kses_post( $price_headline ); ?></span>
										<?php if ( '' !== $price_unit ) : ?><span class="mptbm-vpage-price-unit"><?php echo esc_html( $price_unit ); ?></span><?php endif; ?>
									</div>
								<?php endif; ?>
								<?php if ( '' !== $price_note ) : ?>
									<p class="mptbm-vpage-price-note"><?php echo esc_html( $price_note ); ?></p>
								<?php endif; ?>
								<?php if ( count( $price_rows ) > 0 ) : ?>
									<div class="mptbm-vpage-price-grid">
										<?php foreach ( $price_rows as $p_row ) : ?>
											<div class="mptbm-vpage-price-row">
												<span><?php echo esc_html( $p_row[0] ); ?></span>
												<strong><?php echo wp_kses_post( $p_row[1] ); ?></strong>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
								<?php if ( $route_total > 0 ) : ?>
									<details class="mptbm-vpage-route-table">
										<summary><?php echo esc_html( sprintf( _n( '%d Fixed Route', '%d Fixed Routes', $route_total, 'ecab-taxi-booking-manager' ), $route_total ) ); ?></summary>
										<div class="mptbm-vpage-route-list">
											<?php foreach ( $route_rows as $r_index => $r_row ) : ?>
												<div class="mptbm-vpage-route-row"<?php echo $r_index >= $route_visible_count ? ' hidden' : ''; ?>>
													<span><?php echo esc_html( $r_row[0] ); ?> <i class="fas fa-arrow-right" aria-hidden="true"></i> <?php echo esc_html( $r_row[1] ); ?></span>
													<strong><?php echo wp_kses_post( MP_Global_Function::format_price( $r_row[2] ) ); ?></strong>
												</div>
											<?php endforeach; ?>
										</div>
										<?php if ( $route_total > $route_visible_count ) : ?>
											<button type="button" class="mptbm-vpage-route-loadmore" data-step="<?php echo esc_attr( $route_visible_count ); ?>">
												<?php echo esc_html( sprintf( esc_html__( 'Load More (%d)', 'ecab-taxi-booking-manager' ), $route_total - $route_visible_count ) ); ?>
											</button>
										<?php endif; ?>
									</details>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( count( $service_area_names ) > 0 ) : ?>
						<div class="mptbm_details_block">
							<h4><?php esc_html_e( 'Service Areas', 'ecab-taxi-booking-manager' ); ?></h4>
							<ul class="mptbm-vpage-feature-list">
								<?php foreach ( $service_area_names as $area_name ) : ?>
									<li>
										<span class="mptbm-vpage-feature-icon"><i class="fas fa-map-marker-alt" aria-hidden="true"></i></span>
										<span class="mptbm-vpage-feature-text"><?php echo esc_html( $area_name ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
							<?php if ( '' !== $service_area_note ) : ?>
								<p class="mptbm-vpage-price-note"><?php echo esc_html( $service_area_note ); ?></p>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( get_the_content( null, false, $post_id ) ) : ?>
						<div class="mptbm_details_block">
							<h4><?php esc_html_e( 'About This Vehicle', 'ecab-taxi-booking-manager' ); ?></h4>
							<div class="mptbm-vpage-content mptbm-vpage-clamp" id="mptbm-vpage-about-content"><?php echo apply_filters( 'the_content', get_the_content( null, false, $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							<button type="button" class="mptbm-vpage-seemore" data-target="mptbm-vpage-about-content" hidden>
								<span class="mptbm-vpage-seemore-more"><?php esc_html_e( 'See More', 'ecab-taxi-booking-manager' ); ?></span>
								<span class="mptbm-vpage-seemore-less"><?php esc_html_e( 'See Less', 'ecab-taxi-booking-manager' ); ?></span>
								<i class="fas fa-chevron-down" aria-hidden="true"></i>
							</button>
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
							<ul class="mptbm_details_spec_list mptbm-vpage-feature-list">
								<?php foreach ( $clean_features as $feature ) :
									$f_label = isset( $feature['label'] ) ? trim( $feature['label'] ) : '';
									$f_text  = isset( $feature['text'] ) ? trim( $feature['text'] ) : '';
									$f_icon  = isset( $feature['icon'] ) ? $feature['icon'] : '';
									$show_label = ( $f_label !== '' && strcasecmp( $f_label, $f_text ) !== 0 );
								?>
									<li>
										<?php if ( $f_icon ) : ?><span class="mptbm-vpage-feature-icon"><i class="<?php echo esc_attr( $f_icon ); ?>" aria-hidden="true"></i></span><?php endif; ?>
										<span class="mptbm-vpage-feature-text">
											<?php if ( $show_label ) : ?><span class="mptbm_details_spec_label"><?php echo esc_html( $f_label ); ?>:</span><?php endif; ?>
											<span class="mptbm_details_spec_value"><?php echo esc_html( $f_text ); ?></span>
										</span>
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
										<span class="mptbm-vpage-addon-icon"><i class="fas fa-plus" aria-hidden="true"></i></span>
										<span class="mptbm-vpage-addon-name"><?php echo esc_html( $service_name ); ?></span>
										<strong class="mptbm-vpage-addon-price"><?php echo wp_kses_post( MP_Global_Function::price_convert_raw( $wc_price ) ); ?></strong>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<div class="mptbm_details_block mptbm-vpage-trust">
						<ul class="mptbm-vpage-trust-list">
							<li><i class="fas fa-bolt" aria-hidden="true"></i> <?php esc_html_e( 'Instant Fare & Confirmation', 'ecab-taxi-booking-manager' ); ?></li>
							<li><i class="fas fa-lock" aria-hidden="true"></i> <?php esc_html_e( 'Secure Online Checkout', 'ecab-taxi-booking-manager' ); ?></li>
							<li><i class="fas fa-headset" aria-hidden="true"></i> <?php esc_html_e( 'Support Before & During Your Ride', 'ecab-taxi-booking-manager' ); ?></li>
						</ul>
					</div>

				</div>
			</div>

			<div class="mptbm-vpage-book" id="mptbm-vpage-book">
				<div class="mptbm-vpage-book-head">
					<h2><?php esc_html_e( 'Book This Vehicle', 'ecab-taxi-booking-manager' ); ?></h2>
					<p><?php esc_html_e( 'Enter your trip details to get an instant fare and reserve this vehicle.', 'ecab-taxi-booking-manager' ); ?></p>
				</div>
				<?php
				echo do_shortcode( $booking_shortcode );
				// Left in place for pro add-ons / child-theme extensions that may
				// want to append supplementary fields after the booking form.
				do_action( 'mptbm_transport_search_form', $post_id );
				?>
			</div>

		</div>
	</div>
	<script>
	document.addEventListener( 'click', function ( e ) {
		var loadMoreBtn = e.target.closest( '.mptbm-vpage-route-loadmore' );
		if ( loadMoreBtn ) {
			var list = loadMoreBtn.closest( '.mptbm-vpage-route-table' ).querySelector( '.mptbm-vpage-route-list' );
			var hiddenRows = list.querySelectorAll( '.mptbm-vpage-route-row[hidden]' );
			var step = parseInt( loadMoreBtn.getAttribute( 'data-step' ), 10 ) || 8;
			for ( var i = 0; i < hiddenRows.length && i < step; i++ ) {
				hiddenRows[ i ].removeAttribute( 'hidden' );
			}
			var remaining = list.querySelectorAll( '.mptbm-vpage-route-row[hidden]' ).length;
			if ( remaining > 0 ) {
				loadMoreBtn.textContent = loadMoreBtn.textContent.replace( /\(\d+\)/, '(' + remaining + ')' );
			} else {
				loadMoreBtn.remove();
			}
			return;
		}

		var seeMoreBtn = e.target.closest( '.mptbm-vpage-seemore' );
		if ( seeMoreBtn ) {
			var target = document.getElementById( seeMoreBtn.getAttribute( 'data-target' ) );
			if ( target ) {
				var expanded = target.classList.toggle( 'mptbm-vpage-clamp--expanded' );
				seeMoreBtn.classList.toggle( 'is-expanded', expanded );
			}
		}
	} );

	// Only reveal "See More" when the description is actually cut off by the
	// 4-line clamp - a short description that already fits needs no button.
	document.querySelectorAll( '.mptbm-vpage-clamp' ).forEach( function ( el ) {
		if ( el.scrollHeight > el.clientHeight + 2 ) {
			var seeMoreBtn = document.querySelector( '.mptbm-vpage-seemore[data-target="' + el.id + '"]' );
			if ( seeMoreBtn ) {
				seeMoreBtn.hidden = false;
			}
		}
	} );
	</script>
<?php do_action( 'mptbm_after_details_page', $post_id ); ?>
