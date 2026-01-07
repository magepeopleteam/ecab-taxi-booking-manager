<?php
/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.
if (!class_exists('MPTBM_Guideline')) {
	class MPTBM_Guideline
	{
		public function __construct()
		{
			add_action('admin_menu', array($this, 'guideline_menu'));
		}
		public function guideline_menu()
		{
			$cpt = MPTBM_Function::get_cpt();
			add_submenu_page('edit.php?post_type=' . $cpt, esc_html__('Guideline', 'ecab-taxi-booking-manager'), '<span>' . esc_html__('Guideline', 'ecab-taxi-booking-manager') . '</span>', 'manage_options', 'mptbm_guideline_page', array($this, 'guideline_page'));
		}
		public function guideline_page()
		{
			$label = MPTBM_Function::get_name();
			wp_enqueue_style('mptbm-guideline-style', MPTBM_PLUGIN_URL . '/assets/admin/css/guideline.css', array(), time());
?>
			<div class="wrap"></div>
			<div class="mpStyle mptbm-documentation">
				<div class="mptbm-doc-header">
					<div class="mptbm-doc-header-content">
						<h1><?php echo esc_html($label) . ' ' . esc_html__('Documentation', 'ecab-taxi-booking-manager'); ?></h1>
						<p><?php esc_html_e('Everything you need to know to get started with the Taxi Booking Manager', 'ecab-taxi-booking-manager'); ?></p>
					</div>
				</div>
				
				<div class="mptbm-content-wrapper">
					<div class="mptbm-content-container">
						<div class="mptbm-doc-section">
							<div class="mptbm-section-header">
								<div class="mptbm-section-icon"><span class="dashicons dashicons-shortcode"></span></div>
								<h2><?php esc_html_e('Main Booking Shortcode', 'ecab-taxi-booking-manager'); ?></h2>
							</div>

							<div class="mptbm-shortcode-box">
								<code>[mptbm_booking price_based='dynamic' form='horizontal' progressbar='yes' map='yes']</code>
								<button class="mptbm-copy-btn" onclick="copyToClipboard(this)" data-clipboard-text="[mptbm_booking price_based='dynamic' form='horizontal' progressbar='yes' map='yes']">
									<span class="dashicons dashicons-clipboard"></span>
								</button>
							</div>

							<div class="mptbm-param-table-wrapper">
								<table class="mptbm-param-table">
									<thead>
										<tr>
											<th><?php esc_html_e('Parameter', 'ecab-taxi-booking-manager'); ?></th>
											<th><?php esc_html_e('Values', 'ecab-taxi-booking-manager'); ?></th>
											<th><?php esc_html_e('Description', 'ecab-taxi-booking-manager'); ?></th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td><code>price_based</code></td>
											<td>
												<span class="mptbm-tag mptbm-tag-manual">manual</span>
												<span class="mptbm-tag mptbm-tag-dynamic">dynamic</span>
											</td>
											<td>
												<?php esc_html_e('Default is', 'ecab-taxi-booking-manager'); ?> <strong>dynamic</strong>. 
												<?php esc_html_e('Dynamic pricing works based on Google Maps distance, while manual means fixed pricing between 2 locations.', 'ecab-taxi-booking-manager'); ?>
											</td>
										</tr>
										<tr>
											<td><code>form</code></td>
											<td>
												<span class="mptbm-tag mptbm-tag-inline">inline</span>
												<span class="mptbm-tag mptbm-tag-horizontal">horizontal</span>
											</td>
											<td>
												<?php esc_html_e('Default is', 'ecab-taxi-booking-manager'); ?> <strong>horizontal</strong>.
												<?php esc_html_e('Inline displays a minimal single-line form.', 'ecab-taxi-booking-manager'); ?>
											</td>
										</tr>
										<tr>
											<td><code>progressbar</code></td>
											<td>
												<span class="mptbm-tag mptbm-tag-yes">yes</span>
												<span class="mptbm-tag mptbm-tag-no">no</span>
											</td>
											<td>
												<?php esc_html_e('Default is', 'ecab-taxi-booking-manager'); ?> <strong>yes</strong>.
												<?php esc_html_e('If set to no, the progress bar will be hidden.', 'ecab-taxi-booking-manager'); ?>
											</td>
										</tr>
										<tr>
											<td><code>map</code></td>
											<td>
												<span class="mptbm-tag mptbm-tag-yes">yes</span>
												<span class="mptbm-tag mptbm-tag-no">no</span>
											</td>
											<td>
												<?php esc_html_e('Default is', 'ecab-taxi-booking-manager'); ?> <strong>yes</strong>.
												<?php esc_html_e('If set to no, the map will be hidden.', 'ecab-taxi-booking-manager'); ?>
											</td>
										</tr>
										<tr>
											<td><code>tab</code></td>
											<td>
												<span class="mptbm-tag mptbm-tag-yes">yes</span>
												<span class="mptbm-tag mptbm-tag-no">no</span>
											</td>
											<td>
												<?php esc_html_e('Default is', 'ecab-taxi-booking-manager'); ?> <strong>no</strong>.
												<?php esc_html_e('If yes, it shows tabs: hourly, distance, manual.', 'ecab-taxi-booking-manager'); ?>
												<?php esc_html_e('Used when tab is set to yes.', 'ecab-taxi-booking-manager'); ?>
											</td>
										</tr>
										<tr>
											<td><code>tabs</code></td>
											<td>
												<span class="mptbm-tag mptbm-tag-hourly">hourly</span>
												<span class="mptbm-tag mptbm-tag-distance">distance</span>
												<span class="mptbm-tag mptbm-tag-manual">manual</span>
											</td>
											<td>
												<?php esc_html_e('List tabs to include. Only applies when tab is set to yes.', 'ecab-taxi-booking-manager'); ?>
												<div class="mptbm-examples">
													<p><strong><?php esc_html_e('Examples:', 'ecab-taxi-booking-manager'); ?></strong></p>
													<div class="mptbm-example">
														<code>[mptbm_booking tab='yes' tabs='hourly,distance,manual']</code>
														<p><?php esc_html_e('→ Shows all tabs', 'ecab-taxi-booking-manager'); ?></p>
													</div>
													<div class="mptbm-example">
														<code>[mptbm_booking tab='yes' tabs='hourly,distance']</code>
														<p><?php esc_html_e('→ Hides manual tab, shows hourly & distance tabs', 'ecab-taxi-booking-manager'); ?></p>
													</div>
													<div class="mptbm-example">
														<code>[mptbm_booking tab='yes' tabs='manual']</code>
														<p><?php esc_html_e('→ Hides hourly & distance tabs, shows only manual tab', 'ecab-taxi-booking-manager'); ?></p>
													</div>
												</div>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
						
						<div class="mptbm-doc-section">
							<div class="mptbm-pro-tip">
								<div class="mptbm-pro-tip-icon">
									<span class="dashicons dashicons-lightbulb"></span>
								</div>
								<div class="mptbm-pro-tip-content">
									<h3><?php esc_html_e('Pro Tip', 'ecab-taxi-booking-manager'); ?></h3>
									<p><?php esc_html_e('For the best user experience, use the dynamic pricing mode with Google Maps API configured correctly in your settings.', 'ecab-taxi-booking-manager'); ?></p>
								</div>
							</div>
						</div>
					</div>
				</div>
				
				<script>
					function copyToClipboard(element) {
						const text = element.getAttribute('data-clipboard-text');
						navigator.clipboard.writeText(text).then(() => {
							// Change button icon temporarily
							const icon = element.querySelector('.dashicons');
							icon.classList.remove('dashicons-clipboard');
							icon.classList.add('dashicons-yes');
							
							// Reset after 2 seconds
							setTimeout(() => {
								icon.classList.remove('dashicons-yes');
								icon.classList.add('dashicons-clipboard');
							}, 2000);
						});
					}
				</script>
			</div>
<?php
		}
	}
	new MPTBM_Guideline();
}
