<?php
/*
 * @Author 		mage-people.com
 * Copyright: 	mage-people.com
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Taxonomy_Meta')) {
	class MPTBM_Taxonomy_Meta {
		public function __construct() {
			// Add fields to taxonomy
			add_action('locations_add_form_fields', array($this, 'add_location_geo_field'), 10, 2);
			add_action('locations_edit_form_fields', array($this, 'edit_location_geo_field'), 10, 2);

			// Save taxonomy fields
			add_action('created_locations', array($this, 'save_location_geo_field'), 10, 2);
			add_action('edited_locations', array($this, 'save_location_geo_field'), 10, 2);
		}

		public function add_location_geo_field($taxonomy) {
			$this->render_geo_field('', '');
		}

		public function edit_location_geo_field($term, $taxonomy) {
			$geo_location = get_term_meta($term->term_id, 'mptbm_geo_location', true);
			$geo_location_name = get_term_meta($term->term_id, 'mptbm_geo_location_name', true);
			$this->render_geo_field($geo_location, $geo_location_name, true);
		}

		private function render_geo_field($value, $name = '', $edit = false) {
			$map_type = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'openstreetmap');
			if ($map_type == 'disable') {
				return;
			}
			?>
			<?php if ($edit): ?>
				<tr class="form-field">
					<th scope="row" valign="top"><label for="mptbm_geo_location"><?php esc_html_e('Geo Location', 'ecab-taxi-booking-manager'); ?></label></th>
					<td>
			<?php else: ?>
				<div class="form-field">
					<label for="mptbm_geo_location"><?php esc_html_e('Geo Location', 'ecab-taxi-booking-manager'); ?></label>
			<?php endif; ?>
					
					<div class="mptbm_taxonomy_map_area">
						<input type="text" id="mptbm_geo_location_search" name="mptbm_geo_location_name" class="formControl" placeholder="<?php esc_attr_e('Search location on map...', 'ecab-taxi-booking-manager'); ?>" value="<?php echo esc_attr($name); ?>" autocomplete="off" />
						<input type="hidden" name="mptbm_geo_location" id="mptbm_geo_location" value="<?php echo esc_attr($value); ?>" />
						<div id="mptbm_taxonomy_map" style="width: 100%; height: 300px; margin-top: 10px; border: 1px solid #ddd;"></div>
					</div>
					<p class="description"><?php esc_html_e('Search and pick a location on the map. This will be used for map-based pricing.', 'ecab-taxi-booking-manager'); ?></p>

					<script>
						jQuery(document).ready(function($) {
							var mapType = '<?php echo esc_js($map_type); ?>';
							var savedGeo = '<?php echo esc_js($value); ?>';
							var lat = 23.8103, lng = 90.4125;
							
							if (savedGeo) {
								var parts = savedGeo.split(',');
								if (parts.length === 2) {
									lat = parseFloat(parts[0]);
									lng = parseFloat(parts[1]);
								}
							}

							function initTaxonomyMap() {
								if (mapType === 'openstreetmap') {
									if (typeof L === 'undefined') return;
									
									var osmMap = L.map('mptbm_taxonomy_map').setView([lat, lng], 13);
									L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
										attribution: '© OpenStreetMap contributors'
									}).addTo(osmMap);
									
									var marker = L.marker([lat, lng], {draggable: true}).addTo(osmMap);
									
									marker.on('dragend', function(e) {
										var position = marker.getLatLng();
										$('#mptbm_geo_location').val(position.lat.toFixed(6) + ',' + position.lng.toFixed(6));
									});

									// Search function for OSM
									if (typeof setupOSMLocationSearch === 'function') {
										setupOSMLocationSearch('mptbm_geo_location_search', osmMap, function(newLat, newLng, displayName) {
											osmMap.setView([newLat, newLng], 15);
											marker.setLatLng([newLat, newLng]);
											$('#mptbm_geo_location').val(newLat.toFixed(6) + ',' + newLng.toFixed(6));
											// Name is already set by search function but ensure it matches
											$('#mptbm_geo_location_search').val(displayName);
										});
									}
									
									setTimeout(function() { osmMap.invalidateSize(); }, 500);
								} else {
									// Google Maps
									if (typeof google === 'undefined' || typeof google.maps === 'undefined') return;
									
									var myLatLng = {lat: lat, lng: lng};
									var map = new google.maps.Map(document.getElementById('mptbm_taxonomy_map'), {
										zoom: 13,
										center: myLatLng
									});
									
									var marker = new google.maps.Marker({
										position: myLatLng,
										map: map,
										draggable: true
									});
									
									marker.addListener('dragend', function() {
										var position = marker.getPosition();
										$('#mptbm_geo_location').val(position.lat().toFixed(6) + ',' + position.lng().toFixed(6));
									});

									// Autocomplete for Google
									var input = document.getElementById('mptbm_geo_location_search');
									var autocomplete = new google.maps.places.Autocomplete(input);
									autocomplete.addListener('place_changed', function() {
										var place = autocomplete.getPlace();
										if (place.geometry) {
											var location = place.geometry.location;
											map.setCenter(location);
											map.setZoom(15);
											marker.setPosition(location);
											$('#mptbm_geo_location').val(location.lat().toFixed(6) + ',' + location.lng().toFixed(6));
										}
									});
								}
							}

							// Small delay to ensure scripts are ready and container is visible
							setTimeout(initTaxonomyMap, 500);
						});
					</script>

			<?php if ($edit): ?>
					</td>
				</tr>
			<?php else: ?>
				</div>
			<?php endif; ?>
			<?php
		}

		public function save_location_geo_field($term_id, $tt_id) {
			if (isset($_POST['mptbm_geo_location'])) {
				update_term_meta($term_id, 'mptbm_geo_location', sanitize_text_field($_POST['mptbm_geo_location']));
			}
			if (isset($_POST['mptbm_geo_location_name'])) {
				update_term_meta($term_id, 'mptbm_geo_location_name', sanitize_text_field($_POST['mptbm_geo_location_name']));
			}
		}
	}
	new MPTBM_Taxonomy_Meta();
}
