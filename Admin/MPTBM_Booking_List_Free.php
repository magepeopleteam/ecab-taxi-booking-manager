<?php
	/*
	 * Free-plugin "Bookings" list — a limited, upgrade-teaser version of the Pro
	 * unified booking list (MPTBM_Booking_List in ecab-taxi-booking-manager-pro).
	 *
	 * Purpose: when the Pro plugin is NOT active, admins can still see their bookings
	 * (the `mptbm_booking` records the free WooCommerce bridge creates on checkout) in a
	 * familiar table. Pro-only capabilities are shown but locked:
	 *
	 *   - Statistics bar   -> rendered blurred behind a "PRO" overlay (no real figures).
	 *   - Filter panel     -> rendered blurred/disabled behind a "PRO" overlay.
	 *   - Booking detail   -> locked action (PRO badge, no navigation).
	 *   - Change status    -> locked action (PRO badge, status shown read-only).
	 *   - Bulk select      -> removed entirely (no checkboxes).
	 *   - Delete           -> ALLOWED in free (works, cancels a linked WooCommerce order).
	 *
	 * This class is fully self-contained: it depends only on jQuery + dashicons (both
	 * always present in wp-admin) and the free plugin's own helpers. It deliberately does
	 * NOT reuse any Pro asset (e.g. mptbmToast), so it can never fatal when Pro is absent.
	 * When Pro IS active it stands down completely (no menu, no AJAX handling) so the Pro
	 * list is the single source of truth.
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! class_exists( 'MPTBM_Booking_List_Free' ) ) {
		class MPTBM_Booking_List_Free {

			const PER_PAGE = 20;

			public function __construct() {
				add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
				add_action( 'wp_ajax_mptbm_free_delete_booking', array( $this, 'ajax_delete_booking' ) );
			}

			/**
			 * Pro is detected at hook/request time (both plugins are loaded by then), so the
			 * free list reliably stands down when Pro is present. Mirrors the same check the
			 * rest of the free plugin uses (see MPTBM_CPT).
			 */
			private function is_pro_active() {
				return class_exists( 'MPTBM_Dependencies_Pro' ) || class_exists( 'MPTBM_Plugin_Pro' );
			}

			private function get_cpt() {
				return class_exists( 'MPTBM_Function' ) ? MPTBM_Function::get_cpt() : 'mptbm_rent';
			}

			private function base_url() {
				return admin_url( 'edit.php?post_type=' . $this->get_cpt() . '&page=mptbm_bookings' );
			}

			/** Filterable upgrade destination shown on every "PRO" lock. */
			private function upgrade_url() {
				return apply_filters( 'mptbm_pro_upgrade_url', 'https://www.magepeople.com/downloads/taxi-booking-manager-for-woocommerce/' );
			}

			public function add_menu() {
				if ( $this->is_pro_active() ) {
					return; // Pro provides the full Bookings page; don't duplicate the menu.
				}
				add_submenu_page(
					'edit.php?post_type=' . $this->get_cpt(),
					__( 'Bookings', 'ecab-taxi-booking-manager' ),
					__( 'Bookings', 'ecab-taxi-booking-manager' ),
					'manage_options',
					'mptbm_bookings',
					array( $this, 'render_page' )
				);
			}

			/* --------------------------------------------------------------
			 * Data
			 * ------------------------------------------------------------ */

			private function format_price( $amount ) {
				return class_exists( 'MP_Global_Function' ) ? MP_Global_Function::format_price( (float) $amount ) : number_format( (float) $amount, 2 );
			}

			/** Unified status label + CSS class (kept in sync with the Pro normalizer). */
			private function status_meta( $key ) {
				$key = strtolower( (string) $key );
				if ( 0 === strpos( $key, 'wc-' ) ) {
					$key = substr( $key, 3 );
				}
				$key = $key ?: 'pending';
				$map = array(
					'pending'        => array( __( 'Pending', 'ecab-taxi-booking-manager' ),        'pending' ),
					'processing'     => array( __( 'Processing', 'ecab-taxi-booking-manager' ),     'processing' ),
					'on-hold'        => array( __( 'On Hold', 'ecab-taxi-booking-manager' ),        'on-hold' ),
					'completed'      => array( __( 'Completed', 'ecab-taxi-booking-manager' ),      'completed' ),
					'cancelled'      => array( __( 'Cancelled', 'ecab-taxi-booking-manager' ),      'cancelled' ),
					'refunded'       => array( __( 'Refunded', 'ecab-taxi-booking-manager' ),       'refunded' ),
					'failed'         => array( __( 'Failed', 'ecab-taxi-booking-manager' ),         'failed' ),
					'partially-paid' => array( __( 'Partially Paid', 'ecab-taxi-booking-manager' ), 'partially-paid' ),
				);
				return isset( $map[ $key ] ) ? $map[ $key ] : array( ucfirst( str_replace( '-', ' ', $key ) ), 'pending' );
			}

			/**
			 * One page of bookings (newest first). Returns [ rows[], total ].
			 * Only the requested page is queried, so nothing loads the whole table into memory.
			 *
			 * @param int $paged 1-based page number.
			 * @return array{0: array<int,array>, 1: int}
			 */
			private function fetch_page( $paged ) {
				$q = new WP_Query( array(
					'post_type'      => 'mptbm_booking',
					'post_status'    => array( 'publish', 'pending', 'draft' ),
					'posts_per_page' => self::PER_PAGE,
					'paged'          => max( 1, $paged ),
					'orderby'        => 'date',
					'order'          => 'DESC',
					'no_found_rows'  => false,
				) );

				$rows     = array();
				$wc_ready = function_exists( 'wc_get_order' );
				while ( $q->have_posts() ) {
					$q->the_post();
					$id       = get_the_ID();
					$vehicle  = absint( get_post_meta( $id, 'mptbm_id', true ) );
					$order_id = absint( get_post_meta( $id, 'mptbm_order_id', true ) );

					// Source + total resolved per rendered row only (max PER_PAGE lookups).
					$wc_order = ( $wc_ready && $order_id && $order_id !== $id ) ? wc_get_order( $order_id ) : false;
					$is_woo   = ( $wc_order instanceof WC_Order );
					$total    = $is_woo ? (float) $wc_order->get_total() : (float) get_post_meta( $id, 'mptbm_tp', true );

					$rows[] = array(
						'ID'       => $id,
						'is_woo'   => $is_woo,
						'order_id' => $order_id,
						'name'     => get_post_meta( $id, 'mptbm_billing_name', true ),
						'email'    => get_post_meta( $id, 'mptbm_billing_email', true ),
						'vehicle'  => $vehicle ? get_the_title( $vehicle ) : '—',
						'pickup'   => get_post_meta( $id, 'mptbm_date', true ),
						'status'   => get_post_meta( $id, 'mptbm_order_status', true ) ?: 'pending',
						'total'    => $this->format_price( $total ),
					);
				}
				wp_reset_postdata();

				return array( $rows, (int) $q->found_posts );
			}

			/* --------------------------------------------------------------
			 * AJAX: delete (the one action available in free)
			 * ------------------------------------------------------------ */

			public function ajax_delete_booking() {
				check_ajax_referer( 'mptbm_free_bookings', 'nonce' );
				if ( $this->is_pro_active() ) {
					// Defense in depth: when Pro is active it owns deletion.
					wp_send_json_error( array( 'message' => __( 'Handled by Pro.', 'ecab-taxi-booking-manager' ) ), 400 );
				}
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( array( 'message' => __( 'Unauthorized', 'ecab-taxi-booking-manager' ) ), 403 );
				}
				$id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
				if ( ! $id || get_post_type( $id ) !== 'mptbm_booking' ) {
					wp_send_json_error( array( 'message' => __( 'Invalid booking.', 'ecab-taxi-booking-manager' ) ) );
				}

				$order_id = absint( get_post_meta( $id, 'mptbm_order_id', true ) );

				// Cancel the linked WooCommerce order (if any, and if WooCommerce is active).
				if ( $order_id && $order_id !== $id && function_exists( 'wc_get_order' ) ) {
					$order = wc_get_order( $order_id );
					if ( $order instanceof WC_Order && ! in_array( $order->get_status(), array( 'cancelled', 'refunded' ), true ) ) {
						$order->update_status( 'cancelled', __( 'Order cancelled due to booking deletion.', 'ecab-taxi-booking-manager' ) );
					}
				}

				// Remove the extra-service records tied to this booking.
				$service_q = new WP_Query( array(
					'post_type'      => 'mptbm_service_booking',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_query'     => array(
						'relation' => 'AND',
						array( 'key' => 'mptbm_id', 'value' => get_post_meta( $id, 'mptbm_id', true ) ),
						array( 'key' => 'mptbm_order_id', 'value' => $order_id ),
					),
				) );
				foreach ( $service_q->posts as $service_id ) {
					wp_delete_post( $service_id, true );
				}
				wp_reset_postdata();

				wp_trash_post( $id );

				wp_send_json_success( array( 'message' => __( 'Booking deleted.', 'ecab-taxi-booking-manager' ) ) );
			}

			/* --------------------------------------------------------------
			 * Render
			 * ------------------------------------------------------------ */

			private function pro_badge() {
				return '<span class="mptbm-lock-badge"><span class="dashicons dashicons-lock"></span>' . esc_html__( 'PRO', 'ecab-taxi-booking-manager' ) . '</span>';
			}

			public function render_page() {
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}

				$paged            = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
				list( $rows, $total ) = $this->fetch_page( $paged );
				$pages            = max( 1, (int) ceil( $total / self::PER_PAGE ) );
				if ( $paged > $pages ) {
					$paged = $pages; // Clamp out-of-range page requests (e.g. after deletes).
					list( $rows, $total ) = $this->fetch_page( $paged );
				}
				$nonce   = wp_create_nonce( 'mptbm_free_bookings' );
				$upgrade = $this->upgrade_url();
				?>
				<div class="mptbm-orders-wrap mptbm-free-wrap">
					<div class="mptbm-orders-header">
						<div class="mptbm-orders-title-group">
							<h1 class="mptbm-orders-title"><span class="dashicons dashicons-clipboard"></span><?php esc_html_e( 'Bookings', 'ecab-taxi-booking-manager' ); ?></h1>
							<p class="mptbm-orders-subtitle"><?php esc_html_e( 'A read-only overview of your bookings. Unlock filters, stats, details and status management with Pro.', 'ecab-taxi-booking-manager' ); ?></p>
						</div>
						<div class="mptbm-orders-header-actions">
							<a href="<?php echo esc_url( $upgrade ); ?>" target="_blank" rel="noopener" class="mptbm-btn mptbm-btn-primary"><span class="dashicons dashicons-star-filled"></span><?php esc_html_e( 'Upgrade to Pro', 'ecab-taxi-booking-manager' ); ?></a>
						</div>
					</div>

					<?php /* Statistics — locked. Blurred placeholders behind a PRO overlay. */ ?>
					<div class="mptbm-pro-lock mptbm-stats-lock">
						<div class="mptbm-pro-lock-inner" aria-hidden="true">
							<div class="mptbm-stats-bar">
								<div class="mptbm-stat-card"><span class="mptbm-stat-icon dashicons dashicons-cart"></span><div class="mptbm-stat-info"><span class="mptbm-stat-value">1,248</span><span class="mptbm-stat-label"><?php esc_html_e( 'Total Bookings', 'ecab-taxi-booking-manager' ); ?></span></div></div>
								<div class="mptbm-stat-card"><span class="mptbm-stat-icon dashicons dashicons-money-alt"></span><div class="mptbm-stat-info"><span class="mptbm-stat-value">$48,920</span><span class="mptbm-stat-label"><?php esc_html_e( 'Total Revenue', 'ecab-taxi-booking-manager' ); ?></span></div></div>
								<div class="mptbm-stat-card"><span class="mptbm-stat-icon dashicons dashicons-clock"></span><div class="mptbm-stat-info"><span class="mptbm-stat-value">36</span><span class="mptbm-stat-label"><?php esc_html_e( 'Pending', 'ecab-taxi-booking-manager' ); ?></span></div></div>
							</div>
						</div>
						<?php $this->pro_overlay( $upgrade, __( 'Live statistics are a Pro feature', 'ecab-taxi-booking-manager' ) ); ?>
					</div>

					<?php /* Filter — locked. Blurred, disabled inputs behind a PRO overlay. */ ?>
					<div class="mptbm-pro-lock mptbm-filter-lock">
						<div class="mptbm-pro-lock-inner" aria-hidden="true">
							<div class="mptbm-filter-panel">
								<div class="mptbm-filter-panel-header"><span class="dashicons dashicons-filter"></span><strong><?php esc_html_e( 'Filter Bookings', 'ecab-taxi-booking-manager' ); ?></strong></div>
								<div class="mptbm-filter-body">
									<div class="mptbm-filter-grid">
										<div class="mptbm-filter-field"><label><?php esc_html_e( 'Search', 'ecab-taxi-booking-manager' ); ?></label><input type="text" disabled placeholder="<?php esc_attr_e( 'Name, email or #ID…', 'ecab-taxi-booking-manager' ); ?>"></div>
										<div class="mptbm-filter-field"><label><?php esc_html_e( 'Status', 'ecab-taxi-booking-manager' ); ?></label><select disabled><option><?php esc_html_e( 'All Statuses', 'ecab-taxi-booking-manager' ); ?></option></select></div>
										<div class="mptbm-filter-field"><label><?php esc_html_e( 'Booking Source', 'ecab-taxi-booking-manager' ); ?></label><select disabled><option><?php esc_html_e( 'All Sources', 'ecab-taxi-booking-manager' ); ?></option></select></div>
										<div class="mptbm-filter-field"><label><?php esc_html_e( 'Date From', 'ecab-taxi-booking-manager' ); ?></label><input type="date" disabled></div>
									</div>
								</div>
							</div>
						</div>
						<?php $this->pro_overlay( $upgrade, __( 'Filtering & search are a Pro feature', 'ecab-taxi-booking-manager' ) ); ?>
					</div>

					<div class="mptbm-table-wrap">
						<div class="mptbm-table-toolbar">
							<span class="mptbm-result-count"><?php printf( esc_html( _n( '%d booking', '%d bookings', $total, 'ecab-taxi-booking-manager' ) ), (int) $total ); ?></span>
						</div>
						<div class="mptbm-table-scroll">
							<table class="mptbm-orders-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Booking', 'ecab-taxi-booking-manager' ); ?></th>
										<th><?php esc_html_e( 'Customer', 'ecab-taxi-booking-manager' ); ?></th>
										<th><?php echo esc_html( class_exists( 'MPTBM_Function' ) ? MPTBM_Function::get_name() : __( 'Vehicle', 'ecab-taxi-booking-manager' ) ); ?></th>
										<th><?php esc_html_e( 'Total', 'ecab-taxi-booking-manager' ); ?></th>
										<th><?php esc_html_e( 'Pickup', 'ecab-taxi-booking-manager' ); ?></th>
										<th><?php esc_html_e( 'Status', 'ecab-taxi-booking-manager' ); ?></th>
										<th class="mptbm-col-actions"><?php esc_html_e( 'Actions', 'ecab-taxi-booking-manager' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php if ( empty( $rows ) ) : ?>
										<tr><td colspan="7" class="mptbm-no-orders"><span class="dashicons dashicons-clipboard"></span><p><?php esc_html_e( 'No bookings yet.', 'ecab-taxi-booking-manager' ); ?></p></td></tr>
									<?php else : foreach ( $rows as $b ) :
										list( $label, $cls ) = $this->status_meta( $b['status'] );
										?>
										<tr data-row-id="<?php echo esc_attr( $b['ID'] ); ?>">
											<td class="mptbm-col-id">
												<span class="mptbm-order-link">#<?php echo esc_html( $b['ID'] ); ?></span>
												<?php if ( $b['is_woo'] ) : ?><span class="mptbm-source-badge mptbm-source-woo"><?php esc_html_e( 'WooCommerce', 'ecab-taxi-booking-manager' ); ?></span><?php else : ?><span class="mptbm-source-badge mptbm-source-ecab"><?php esc_html_e( 'ECAB', 'ecab-taxi-booking-manager' ); ?></span><?php endif; ?>
											</td>
											<td class="mptbm-col-customer">
												<strong><?php echo esc_html( $b['name'] ?: '—' ); ?></strong>
												<?php if ( $b['email'] ) : ?><br><span class="mptbm-email-muted"><?php echo esc_html( $b['email'] ); ?></span><?php endif; ?>
											</td>
											<td><?php echo esc_html( $b['vehicle'] ); ?></td>
											<td class="mptbm-col-total"><strong><?php echo wp_kses_post( $b['total'] ); ?></strong></td>
											<td class="mptbm-col-date"><?php echo esc_html( $b['pickup'] ?: '—' ); ?></td>
											<td><span class="mptbm-status-pill mptbm-status-<?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $label ); ?></span></td>
											<td class="mptbm-col-actions">
												<div class="mptbm-free-actions">
													<span class="mptbm-icon-btn mptbm-locked" title="<?php esc_attr_e( 'Booking details are available in Pro', 'ecab-taxi-booking-manager' ); ?>"><span class="dashicons dashicons-visibility"></span><?php echo $this->pro_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
													<span class="mptbm-icon-btn mptbm-locked" title="<?php esc_attr_e( 'Changing status is available in Pro', 'ecab-taxi-booking-manager' ); ?>"><span class="dashicons dashicons-update"></span><?php echo $this->pro_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
													<button type="button" class="mptbm-icon-btn mptbm-icon-danger mptbm-free-delete" data-id="<?php echo esc_attr( $b['ID'] ); ?>" title="<?php esc_attr_e( 'Delete booking', 'ecab-taxi-booking-manager' ); ?>"><span class="dashicons dashicons-trash"></span></button>
												</div>
											</td>
										</tr>
									<?php endforeach; endif; ?>
								</tbody>
							</table>
						</div>
						<?php if ( $pages > 1 ) : ?>
							<div class="mptbm-free-pagination">
								<?php
								$prev = $paged > 1 ? add_query_arg( 'paged', $paged - 1, $this->base_url() ) : '';
								$next = $paged < $pages ? add_query_arg( 'paged', $paged + 1, $this->base_url() ) : '';
								?>
								<a class="mptbm-btn mptbm-btn-outline mptbm-btn-sm <?php echo $prev ? '' : 'mptbm-btn-disabled'; ?>" href="<?php echo esc_url( $prev ?: '#' ); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span><?php esc_html_e( 'Prev', 'ecab-taxi-booking-manager' ); ?></a>
								<span class="mptbm-page-indicator"><?php printf( esc_html__( 'Page %1$d of %2$d', 'ecab-taxi-booking-manager' ), (int) $paged, (int) $pages ); ?></span>
								<a class="mptbm-btn mptbm-btn-outline mptbm-btn-sm <?php echo $next ? '' : 'mptbm-btn-disabled'; ?>" href="<?php echo esc_url( $next ?: '#' ); ?>"><?php esc_html_e( 'Next', 'ecab-taxi-booking-manager' ); ?><span class="dashicons dashicons-arrow-right-alt2"></span></a>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<?php $this->render_styles(); ?>
				<script>
				(function($){
					var cfg = { ajax:'<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', nonce:'<?php echo esc_js( $nonce ); ?>' };
					$(document).on('click', '.mptbm-free-delete', function(){
						var $btn = $(this), id = $btn.data('id');
						if (!window.confirm('<?php echo esc_js( __( 'Delete this booking? This moves it to trash and cancels any linked WooCommerce order.', 'ecab-taxi-booking-manager' ) ); ?>')) { return; }
						$btn.prop('disabled', true).addClass('is-busy');
						$.post(cfg.ajax, { action:'mptbm_free_delete_booking', nonce:cfg.nonce, booking_id:id }, function(res){
							if (res && res.success) {
								var $row = $('tr[data-row-id="' + id + '"]');
								$row.fadeOut(180, function(){
									$row.remove();
									// If the page is now empty, reload to pull the next page / empty state.
									if ($('.mptbm-orders-table tbody tr[data-row-id]').length === 0) { window.location.reload(); }
								});
							} else {
								window.alert((res && res.data && res.data.message) || '<?php echo esc_js( __( 'Error deleting booking.', 'ecab-taxi-booking-manager' ) ); ?>');
								$btn.prop('disabled', false).removeClass('is-busy');
							}
						}).fail(function(){
							window.alert('<?php echo esc_js( __( 'Error deleting booking.', 'ecab-taxi-booking-manager' ) ); ?>');
							$btn.prop('disabled', false).removeClass('is-busy');
						});
					});
				})(jQuery);
				</script>
				<?php
			}

			private function pro_overlay( $upgrade, $text ) {
				?>
				<a class="mptbm-pro-overlay" href="<?php echo esc_url( $upgrade ); ?>" target="_blank" rel="noopener">
					<span class="mptbm-pro-overlay-badge"><span class="dashicons dashicons-lock"></span><?php esc_html_e( 'PRO', 'ecab-taxi-booking-manager' ); ?></span>
					<span class="mptbm-pro-overlay-text"><?php echo esc_html( $text ); ?></span>
				</a>
				<?php
			}

			private function render_styles() {
				?>
				<style>
				.mptbm-orders-wrap{--mptbm-accent:#F12971;--mptbm-accent-soft:#fde7f1;width:100%;max-width:100%;box-sizing:border-box;padding:0 20px 40px;color:#1e293b;}
				.mptbm-orders-header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin:18px 0 20px;}
				.mptbm-orders-title{display:flex;align-items:center;gap:10px;font-size:22px!important;font-weight:700!important;color:#0f172a!important;margin:0!important;padding:0!important;}
				.mptbm-orders-title .dashicons{font-size:22px;width:22px;height:22px;color:var(--mptbm-accent);}
				.mptbm-orders-subtitle{color:#64748b;font-size:13px;margin:4px 0 0;max-width:640px;}
				.mptbm-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;border:none;transition:all .15s ease;line-height:1.4;}
				.mptbm-btn .dashicons{font-size:15px;width:15px;height:15px;margin-top:1px;}
				.mptbm-btn-sm{padding:5px 12px;font-size:12px;}
				.mptbm-btn-primary{background:var(--mptbm-accent);color:#fff;}
				.mptbm-btn-primary:hover{background:#d61f63;color:#fff;}
				.mptbm-btn-outline{background:#fff;border:1.5px solid #cbd5e1;color:#475569;}
				.mptbm-btn-outline:hover{border-color:var(--mptbm-accent);color:var(--mptbm-accent);}
				.mptbm-btn-disabled{opacity:.4;pointer-events:none;}
				.mptbm-stats-bar{display:flex;gap:16px;flex-wrap:wrap;}
				.mptbm-stat-card{flex:1;min-width:160px;background:#fff;border:1px solid #eef0f3;border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 2px rgba(15,23,42,.05);}
				.mptbm-stat-icon{font-size:22px;width:22px;height:22px;color:var(--mptbm-accent);background:var(--mptbm-accent-soft);border-radius:8px;padding:10px;display:flex;align-items:center;justify-content:center;}
				.mptbm-stat-info{display:flex;flex-direction:column;}
				.mptbm-stat-value{font-size:20px;font-weight:700;color:#0f172a;line-height:1.2;}
				.mptbm-stat-label{font-size:11.5px;color:#64748b;margin-top:2px;text-transform:uppercase;letter-spacing:.04em;}
				/* Pro lock */
				.mptbm-pro-lock{position:relative;margin-bottom:20px;border-radius:12px;overflow:hidden;}
				.mptbm-pro-lock-inner{filter:blur(4px);pointer-events:none;user-select:none;opacity:.9;}
				.mptbm-pro-overlay{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-decoration:none;background:rgba(248,250,252,.55);backdrop-filter:saturate(115%);}
				.mptbm-pro-overlay-badge{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#F12971,#c81e5b);color:#fff;font-size:12px;font-weight:800;letter-spacing:.08em;padding:6px 16px;border-radius:20px;box-shadow:0 6px 16px rgba(241,41,113,.35);}
				.mptbm-pro-overlay-badge .dashicons{font-size:14px;width:14px;height:14px;}
				.mptbm-pro-overlay-text{font-size:12.5px;font-weight:600;color:#334155;background:#fff;padding:4px 12px;border-radius:20px;box-shadow:0 1px 3px rgba(0,0,0,.08);}
				.mptbm-table-wrap{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04);}
				.mptbm-table-toolbar{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #f1f5f9;background:#f8fafc;}
				.mptbm-result-count{font-size:12.5px;color:#64748b;font-weight:500;}
				.mptbm-table-scroll{overflow-x:auto;}
				.mptbm-orders-table{width:100%;border-collapse:collapse;font-size:13px;}
				.mptbm-orders-table thead tr{background:#f8fafc;}
				.mptbm-orders-table thead th{padding:12px 16px;text-align:left;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap;}
				.mptbm-orders-table tbody tr{border-bottom:1px solid #f1f5f9;}
				.mptbm-orders-table tbody tr:last-child{border-bottom:none;}
				.mptbm-orders-table tbody tr:hover{background:#fffafc;}
				.mptbm-orders-table tbody td{padding:13px 16px;vertical-align:middle;color:#374151;line-height:1.5;}
				.mptbm-col-id{white-space:nowrap;}
				.mptbm-col-total,.mptbm-col-date{white-space:nowrap;}
				.mptbm-col-date{font-size:12px;color:#64748b;}
				.mptbm-order-link{font-weight:700;color:var(--mptbm-accent);font-size:13.5px;}
				.mptbm-email-muted{font-size:12px;color:#64748b;}
				.mptbm-source-badge{display:inline-block;font-size:9.5px;font-weight:700;padding:1px 7px;border-radius:20px;letter-spacing:.05em;text-transform:uppercase;vertical-align:middle;margin-left:4px;}
				.mptbm-source-ecab{background:#d1fae5;color:#065f46;}
				.mptbm-source-woo{background:#ede9fe;color:#5b21b6;}
				.mptbm-status-pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:600;letter-spacing:.03em;white-space:nowrap;}
				.mptbm-status-completed{background:#d1fae5;color:#065f46;}
				.mptbm-status-pending{background:#fef3c7;color:#92400e;}
				.mptbm-status-cancelled{background:#fee2e2;color:#991b1b;}
				.mptbm-status-processing{background:#dbeafe;color:#1e40af;}
				.mptbm-status-on-hold{background:#fef9c3;color:#854d0e;}
				.mptbm-status-refunded{background:#f3e8ff;color:#6b21a8;}
				.mptbm-status-failed{background:#fee2e2;color:#7f1d1d;}
				.mptbm-status-partially-paid{background:#e0f2fe;color:#075985;}
				.mptbm-col-actions{width:150px;}
				.mptbm-free-actions{display:flex;align-items:center;gap:6px;}
				.mptbm-icon-btn{display:inline-flex;align-items:center;gap:3px;height:30px;padding:0 8px;border-radius:7px;border:1.5px solid #e2e8f0;background:#fff;color:#475569;cursor:pointer;}
				.mptbm-icon-btn .dashicons{font-size:16px;width:16px;height:16px;}
				.mptbm-icon-danger{border-color:#fecaca;color:#dc2626;}
				.mptbm-icon-danger:hover{background:#fef2f2;border-color:#dc2626;}
				.mptbm-icon-btn.is-busy{opacity:.5;cursor:progress;}
				.mptbm-locked{cursor:not-allowed;background:#f8fafc;color:#94a3b8;}
				.mptbm-lock-badge{display:inline-flex;align-items:center;gap:2px;font-size:8.5px;font-weight:800;letter-spacing:.06em;color:#fff;background:linear-gradient(135deg,#F12971,#c81e5b);padding:1px 5px;border-radius:10px;}
				.mptbm-lock-badge .dashicons{font-size:9px;width:9px;height:9px;}
				.mptbm-no-orders{text-align:center;padding:56px 20px!important;color:#94a3b8;}
				.mptbm-no-orders .dashicons{font-size:46px;width:46px;height:46px;display:block;margin:0 auto 12px;color:#cbd5e1;}
				.mptbm-no-orders p{margin:0;font-size:14px;}
				.mptbm-free-pagination{display:flex;align-items:center;justify-content:center;gap:14px;padding:16px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;}
				.mptbm-page-indicator{font-size:12.5px;color:#64748b;}
				/* Blurred filter/stat placeholders (visual only) */
				.mptbm-filter-panel{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;}
				.mptbm-filter-panel-header{display:flex;align-items:center;gap:8px;padding:14px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:600;color:#374151;}
				.mptbm-filter-panel-header .dashicons{color:var(--mptbm-accent);font-size:16px;width:16px;height:16px;}
				.mptbm-filter-body{padding:20px;}
				.mptbm-filter-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;}
				.mptbm-filter-field{display:flex;flex-direction:column;gap:5px;}
				.mptbm-filter-field label{font-size:12px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.04em;}
				.mptbm-filter-field input,.mptbm-filter-field select{width:100%;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff;height:38px;box-sizing:border-box;}
				</style>
				<?php
			}
		}

		new MPTBM_Booking_List_Free();
	}
