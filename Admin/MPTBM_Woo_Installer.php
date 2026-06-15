<?php
/**
 * MPTBM WooCommerce Installer
 *
 * Shows a beautiful blocking popup on the plugin's admin screens when
 * WooCommerce is missing, and installs + activates it over a sequence of
 * small AJAX requests instead of one big one.
 *
 * Why chunked? On low-memory / low-timeout shared hosts, downloading,
 * unzipping and activating WooCommerce inside a single PHP request often
 * exhausts the memory limit or hits max_execution_time and dies half way.
 * Splitting the work into discrete steps (download -> extract -> activate),
 * and raising the memory / time limit at the start of each step, keeps every
 * request short and light enough to finish on tiny servers.
 *
 * @package ECAB_Taxi_Booking_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'MPTBM_Woo_Installer' ) ) {

	class MPTBM_Woo_Installer {

		/**
		 * Nonce action shared by every installer step.
		 */
		const NONCE = 'mptbm_installer';

		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'admin_footer', array( $this, 'render_popup' ) );

			// Chunked install steps.
			add_action( 'wp_ajax_mptbm_inst_download', array( $this, 'ajax_download' ) );
			add_action( 'wp_ajax_mptbm_inst_extract', array( $this, 'ajax_extract' ) );
			add_action( 'wp_ajax_mptbm_inst_activate', array( $this, 'ajax_activate' ) );
		}

		/**
		 * Registry of plugins this installer can manage. Keyed by slug.
		 * Only WooCommerce for the free plugin.
		 *
		 * @return array<string,array>
		 */
		public static function registry() {
			return array(
				'woocommerce' => array(
					'zip'  => 'https://downloads.wordpress.org/plugin/woocommerce.zip',
					'file' => 'woocommerce/woocommerce.php',
					'name' => 'WooCommerce',
				),
			);
		}

		private function is_woo_active() {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			return is_plugin_active( 'woocommerce/woocommerce.php' );
		}

		private function is_installed( $plugin_file ) {
			return file_exists( WP_PLUGIN_DIR . '/' . $plugin_file );
		}

		/**
		 * Render the popup on screens where it makes sense: our own admin
		 * screens, the dashboard and the plugins list. Less intrusive than
		 * blasting it onto every single wp-admin page.
		 */
		private function should_show_popup() {
			if ( $this->is_woo_active() ) {
				return false;
			}
			$screen = get_current_screen();
			if ( ! $screen ) {
				return false;
			}
			return (
				strpos( $screen->id, 'mptbm' ) !== false
				|| $screen->post_type === 'mptbm_rent'
				|| $screen->id === 'dashboard'
				|| $screen->id === 'plugins'
			);
		}


		public function enqueue_assets() {
			if ( ! $this->should_show_popup() ) {
				return;
			}

			$css_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm_installer.css';
			$js_path  = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm_installer.js';

			wp_enqueue_style(
				'mptbm-installer',
				MPTBM_PLUGIN_URL . '/assets/admin/mptbm_installer.css',
				array(),
				file_exists( $css_path ) ? filemtime( $css_path ) : MPTBM_PLUGIN_VERSION
			);
			wp_enqueue_script(
				'mptbm-installer',
				MPTBM_PLUGIN_URL . '/assets/admin/mptbm_installer.js',
				array( 'jquery' ),
				file_exists( $js_path ) ? filemtime( $js_path ) : MPTBM_PLUGIN_VERSION,
				true
			);

			$reg = self::registry();
			$wc  = $reg['woocommerce'];

			wp_localize_script( 'mptbm-installer', 'mptbm_installer', array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( self::NONCE ),
				'redirect_url' => admin_url( 'edit.php?post_type=mptbm_rent&page=mptbm_transportation_lists' ),
				'plugins'      => array(
					array(
						'slug'      => 'woocommerce',
						'name'      => $wc['name'],
						'installed' => $this->is_installed( $wc['file'] ) ? 1 : 0,
					),
				),
				'i18n'         => array(
					'downloading' => __( 'Downloading %s...', 'ecab-taxi-booking-manager' ),
					'extracting'  => __( 'Extracting %s...', 'ecab-taxi-booking-manager' ),
					'activating'  => __( 'Activating %s...', 'ecab-taxi-booking-manager' ),
					'success'     => __( 'WooCommerce is ready!', 'ecab-taxi-booking-manager' ),
					'redirecting' => __( 'Redirecting...', 'ecab-taxi-booking-manager' ),
					'error'       => __( 'Something went wrong. Please try again.', 'ecab-taxi-booking-manager' ),
				),
			) );
		}

		public function render_popup() {
			if ( ! $this->should_show_popup() ) {
				return;
			}
			$is_installed = $this->is_installed( 'woocommerce/woocommerce.php' );
			$btn_text     = $is_installed
				? __( 'Activate WooCommerce', 'ecab-taxi-booking-manager' )
				: __( 'Install & Activate WooCommerce', 'ecab-taxi-booking-manager' );
			?>
			<div id="mptbm-inst-overlay" class="mptbm-inst-overlay">
				<div class="mptbm-inst-popup">

					<div class="mptbm-inst-header">
						<div class="mptbm-inst-header-icon">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none">
								<path d="M3 13l2-5h11l2 5M5 13h14v5H5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<circle cx="8" cy="18" r="1.6" stroke="currentColor" stroke-width="2"/>
								<circle cx="16" cy="18" r="1.6" stroke="currentColor" stroke-width="2"/>
							</svg>
						</div>
						<span class="mptbm-inst-header-text"><?php esc_html_e( 'Taxi Booking Manager', 'ecab-taxi-booking-manager' ); ?></span>
					</div>

					<div class="mptbm-inst-icon-wrapper">
						<div class="mptbm-inst-icon">
							<svg width="40" height="40" viewBox="0 0 24 24" fill="none">
								<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>
								<path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
							</svg>
						</div>
					</div>

					<div class="mptbm-inst-content">
						<h2 class="mptbm-inst-title"><?php esc_html_e( 'WooCommerce Required', 'ecab-taxi-booking-manager' ); ?></h2>
						<p class="mptbm-inst-desc">
							<?php esc_html_e( 'Taxi Booking Manager uses WooCommerce to handle bookings, carts, and payments. Install and activate WooCommerce to continue. We will do it for you in small steps so it works even on low-memory servers.', 'ecab-taxi-booking-manager' ); ?>
						</p>
					</div>

					<div class="mptbm-inst-features">
						<div class="mptbm-inst-feature">
							<span class="mptbm-inst-feature-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13.3 4.3L6 11.6 2.7 8.3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
							<span><?php esc_html_e( 'Booking checkout', 'ecab-taxi-booking-manager' ); ?></span>
						</div>
						<div class="mptbm-inst-feature">
							<span class="mptbm-inst-feature-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13.3 4.3L6 11.6 2.7 8.3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
							<span><?php esc_html_e( 'Payments &amp; orders', 'ecab-taxi-booking-manager' ); ?></span>
						</div>
						<div class="mptbm-inst-feature">
							<span class="mptbm-inst-feature-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13.3 4.3L6 11.6 2.7 8.3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
							<span><?php esc_html_e( 'Customer accounts', 'ecab-taxi-booking-manager' ); ?></span>
						</div>
					</div>

					<div id="mptbm-inst-progress" class="mptbm-inst-progress" style="display:none;">
						<div class="mptbm-inst-progress-bar">
							<div id="mptbm-inst-progress-fill" class="mptbm-inst-progress-fill"></div>
						</div>
						<p id="mptbm-inst-status-text" class="mptbm-inst-status-text"></p>
					</div>

					<div class="mptbm-inst-actions">
						<button type="button" id="mptbm-inst-btn" class="mptbm-inst-btn mptbm-inst-btn-primary">
							<span class="mptbm-inst-btn-icon">
								<svg width="18" height="18" viewBox="0 0 20 20" fill="none">
									<path d="M10 3v10m0 0l-4-4m4 4l4-4M3 17h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</span>
							<span class="mptbm-inst-btn-text"><?php echo esc_html( $btn_text ); ?></span>
						</button>
						<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ); ?>" class="mptbm-inst-btn mptbm-inst-btn-secondary">
							<?php esc_html_e( 'Install Manually', 'ecab-taxi-booking-manager' ); ?>
						</a>
					</div>

					<p class="mptbm-inst-footer-note">
						<svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="vertical-align: -2px; flex-shrink: 0;">
							<path d="M7 1a6 6 0 100 12A6 6 0 007 1zm0 8.5a.75.75 0 110-1.5.75.75 0 010 1.5zM7.75 6.25a.75.75 0 01-1.5 0V4a.75.75 0 011.5 0v2.25z" fill="currentColor"/>
						</svg>
						<?php esc_html_e( 'WooCommerce is free, open-source, and trusted by millions of stores worldwide.', 'ecab-taxi-booking-manager' ); ?>
					</p>
				</div>
			</div>
			<?php
		}

		/* ---------------------------------------------------------------------
		 * AJAX steps
		 * ------------------------------------------------------------------- */

		/**
		 * Shared guard for every step: verify nonce, capability and slug.
		 * Also raises the memory / time limit so the step can finish on
		 * constrained hosts. Returns the validated registry entry.
		 *
		 * @param string $cap Required capability.
		 * @return array{slug:string,entry:array}
		 */
		private function preflight( $cap ) {
			check_ajax_referer( self::NONCE, 'nonce' );

			if ( ! current_user_can( $cap ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'ecab-taxi-booking-manager' ) ) );
			}

			$slug     = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
			$registry = self::registry();
			if ( ! isset( $registry[ $slug ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Unknown plugin requested.', 'ecab-taxi-booking-manager' ) ) );
			}

			// Give this single step as much headroom as the host allows.
			if ( function_exists( 'wp_raise_memory_limit' ) ) {
				wp_raise_memory_limit( 'admin' );
			}
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 300 );
			}

			return array(
				'slug'  => $slug,
				'entry' => $registry[ $slug ],
			);
		}

		/**
		 * Step 1 — download the package zip to a temp file (streamed, low memory).
		 */
		public function ajax_download() {
			$ctx   = $this->preflight( 'install_plugins' );
			$slug  = $ctx['slug'];
			$entry = $ctx['entry'];

			// Already on disk? Skip straight past download.
			if ( $this->is_installed( $entry['file'] ) ) {
				wp_send_json_success( array( 'skipped' => true ) );
			}

			include_once ABSPATH . 'wp-admin/includes/file.php';

			$tmp = download_url( $entry['zip'], 300 );
			if ( is_wp_error( $tmp ) ) {
				wp_send_json_error( array( 'message' => $tmp->get_error_message() ) );
			}

			// Hand the temp path to the extract step via a short-lived transient.
			set_transient( 'mptbm_inst_pkg_' . $slug, $tmp, 15 * MINUTE_IN_SECONDS );

			wp_send_json_success( array( 'message' => 'downloaded' ) );
		}

		/**
		 * Step 2 — extract the downloaded zip into the plugins directory.
		 * unzip_file() writes each entry straight to disk, so memory use stays
		 * per-file rather than loading the whole archive at once.
		 */
		public function ajax_extract() {
			$ctx   = $this->preflight( 'install_plugins' );
			$slug  = $ctx['slug'];
			$entry = $ctx['entry'];

			if ( $this->is_installed( $entry['file'] ) ) {
				delete_transient( 'mptbm_inst_pkg_' . $slug );
				wp_send_json_success( array( 'skipped' => true ) );
			}

			$tmp = get_transient( 'mptbm_inst_pkg_' . $slug );
			if ( ! $tmp || ! file_exists( $tmp ) ) {
				wp_send_json_error( array( 'message' => __( 'Downloaded package not found. Please retry.', 'ecab-taxi-booking-manager' ) ) );
			}

			include_once ABSPATH . 'wp-admin/includes/file.php';

			// Prepare WP_Filesystem (direct method works on most hosts).
			WP_Filesystem();

			$result = unzip_file( $tmp, WP_PLUGIN_DIR );

			// Clean up the temp zip whether or not extraction succeeded.
			@unlink( $tmp );
			delete_transient( 'mptbm_inst_pkg_' . $slug );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			if ( ! $this->is_installed( $entry['file'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Extraction finished but the plugin file is missing.', 'ecab-taxi-booking-manager' ) ) );
			}

			wp_send_json_success( array( 'message' => 'extracted' ) );
		}

		/**
		 * Step 3 — activate the plugin.
		 */
		public function ajax_activate() {
			$ctx   = $this->preflight( 'activate_plugins' );
			$entry = $ctx['entry'];

			if ( ! $this->is_installed( $entry['file'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Plugin is not installed yet.', 'ecab-taxi-booking-manager' ) ) );
			}

			$result = activate_plugin( $entry['file'] );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			wp_send_json_success( array( 'message' => 'activated' ) );
		}
	}

	new MPTBM_Woo_Installer();
}
