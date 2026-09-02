/* global jQuery, mptbmRoutes, mptbm_admin_ajax, L, google */
(function ($) {
	'use strict';

	$(function () {
		var cfg = window.mptbmRoutes || {};
		var $modal = $('[data-route-modal]');
		var $form = $('[data-route-form]');
		var $grid = $('[data-routes-grid]');
		var $empty = $('[data-routes-empty]');
		var $count = $('[data-routes-count]');
		var $stopSearch = $('#mptbm-route-stop-search');
		var $waypointsField = $('#mptbm-route-waypoints');
		var $stopList = $('[data-route-stop-list]');
		var mode = 'add';

		// Route-building state - one ordered list of {name, lat, lng} stops,
		// kept in sync with a Leaflet/Google marker + connecting line per
		// entry, and with the hidden `waypoints` field the form actually
		// submits (a plain comma-separated name list, same shape the old
		// free-text field used, so the PHP save handler needed no changes).
		var stops = [];
		var markers = [];
		var routeLine = null;
		var map = null;
		var searchBound = false;
		var geocoder = null;
		var searchDebounce = null;
		var $searchResults = null;

		function setCount(n) {
			$count.text(n);
			$empty.toggleClass('is-hidden', n > 0);
		}

		function syncWaypointsField() {
			$waypointsField.val(stops.map(function (s) { return s.name; }).join(', '));
		}

		function renderStopList() {
			$stopList.empty();
			stops.forEach(function (stop, index) {
				var $li = $('<li>', { class: 'mptbm-routes-stop-chip' });
				$li.append($('<span>', { class: 'mptbm-routes-stop-chip-index', text: index + 1 }));
				$li.append($('<span>', { class: 'mptbm-routes-stop-chip-name', text: stop.name, title: stop.name }));
				$li.append(
					$('<button>', { type: 'button', class: 'mptbm-routes-stop-chip-remove', 'aria-label': cfg.removeStopLabel || 'Remove stop' })
						.attr('data-remove-stop', index)
						.append($('<i>', { class: 'fas fa-times', 'aria-hidden': 'true' }))
				);
				$stopList.append($li);
			});
		}

		function clearMarkers() {
			markers.forEach(function (marker) {
				if (cfg.mapType === 'openstreetmap') {
					if (map) { map.removeLayer(marker); }
				} else if (marker.setMap) {
					marker.setMap(null);
				}
			});
			markers = [];
		}

		// Redrawn from scratch (not incrementally) every time stops change -
		// cheap for a handful of points, and avoids ordering bugs from trying
		// to patch a Leaflet/Google polyline's path in place.
		function refreshRouteLine() {
			if (!map) {
				return;
			}
			if (cfg.mapType === 'openstreetmap') {
				if (routeLine) {
					map.removeLayer(routeLine);
					routeLine = null;
				}
				if (stops.length >= 2) {
					routeLine = L.polyline(stops.map(function (s) { return [ s.lat, s.lng ]; }), {
						color: '#4f46e5',
						weight: 3,
						opacity: 0.85,
						dashArray: '8,6'
					}).addTo(map);
				}
			} else {
				if (routeLine) {
					routeLine.setMap(null);
					routeLine = null;
				}
				if (stops.length >= 2 && typeof google !== 'undefined' && google.maps) {
					routeLine = new google.maps.Polyline({
						path: stops.map(function (s) { return { lat: s.lat, lng: s.lng }; }),
						map: map,
						strokeColor: '#4f46e5',
						strokeWeight: 3,
						strokeOpacity: 0.85
					});
				}
			}
		}

		function fitMapToStops() {
			if (!map || stops.length === 0) {
				return;
			}
			if (cfg.mapType === 'openstreetmap') {
				if (stops.length === 1) {
					map.setView([ stops[0].lat, stops[0].lng ], 14);
				} else {
					var bounds = L.latLngBounds(stops.map(function (s) { return [ s.lat, s.lng ]; }));
					map.fitBounds(bounds, { padding: [ 30, 30 ] });
				}
			} else {
				if (stops.length === 1) {
					map.setCenter({ lat: stops[0].lat, lng: stops[0].lng });
					map.setZoom(14);
				} else {
					var gBounds = new google.maps.LatLngBounds();
					stops.forEach(function (s) { gBounds.extend({ lat: s.lat, lng: s.lng }); });
					map.fitBounds(gBounds, 30);
				}
			}
		}

		function addMarkerForStop(stop, index) {
			if (!map) {
				return;
			}
			if (cfg.mapType === 'openstreetmap') {
				var marker = L.marker([ stop.lat, stop.lng ]).addTo(map).bindPopup((index + 1) + '. ' + stop.name);
				markers.push(marker);
			} else if (typeof google !== 'undefined' && google.maps) {
				var gMarker = new google.maps.Marker({
					position: { lat: stop.lat, lng: stop.lng },
					map: map,
					label: String(index + 1)
				});
				markers.push(gMarker);
			}
		}

		function addStop(name, lat, lng) {
			var stop = { name: name, lat: lat, lng: lng };
			stops.push(stop);
			addMarkerForStop(stop, stops.length - 1);
			renderStopList();
			syncWaypointsField();
			refreshRouteLine();
			fitMapToStops();
		}

		function removeStopAt(index) {
			stops.splice(index, 1);
			clearMarkers();
			stops.forEach(function (stop, i) { addMarkerForStop(stop, i); });
			renderStopList();
			syncWaypointsField();
			refreshRouteLine();
			fitMapToStops();
		}

		$(document).on('click', '[data-remove-stop]', function () {
			removeStopAt(parseInt($(this).attr('data-remove-stop'), 10));
		});

		// This vehicle fleet operates in one city/country, so every search -
		// live autocomplete while adding stops, and re-geocoding saved names
		// when Edit opens - is biased toward the admin's configured business
		// location (Map API Settings' lat/lng, e.g. Dhaka). Without a bias, a
		// short/common name like "Savar" or "Mohammadpur" can resolve to an
		// unrelated match anywhere in the world, and fitBounds() then zooms
		// out to a near-useless world view to fit that stray point in. Both
		// paths go through the same server-side `mptbm_osm_search` proxy the
		// frontend already uses (cached, rate-limited, and already supports
		// this lat/lon bias plus this site's optional country restriction) -
		// not the admin map's own direct-to-Photon helper, which has neither.
		function adminAjaxConfig() {
			var g = window.mptbm_admin_ajax || {};
			return {
				url: g.admin_ajax_url || cfg.ajaxUrl,
				nonce: g.admin_nonce || ''
			};
		}

		function searchStopCandidates(query) {
			var ajax = adminAjaxConfig();
			var params = new URLSearchParams({
				action: 'mptbm_osm_search',
				nonce: ajax.nonce,
				q: query
			});
			if (cfg.defaultLat && cfg.defaultLng) {
				params.set('lat', cfg.defaultLat);
				params.set('lon', cfg.defaultLng);
			}
			return fetch(ajax.url + '?' + params.toString())
				.then(function (res) { return res.json(); })
				.then(function (resp) {
					return (resp && resp.success && Array.isArray(resp.data)) ? resp.data : [];
				})
				.catch(function () { return []; });
		}

		// The short, addable name for one OSM search result - prefer the raw
		// place name from `address.name` (e.g. "Eiffel Tower") over
		// `display_name` (e.g. "Eiffel Tower, Paris"), which is itself
		// comma-joined and would otherwise shatter into extra fake stops the
		// moment it's saved into the comma-separated waypoints field.
		function shortNameFor(result) {
			if (result.address && result.address.name) {
				return result.address.name;
			}
			return (result.display_name || '').split(',')[0].trim() || result.display_name;
		}

		function positionSearchDropdown() {
			if (!$searchResults) {
				return;
			}
			var rect = $stopSearch[0].getBoundingClientRect();
			$searchResults.css({ top: (rect.bottom + 2) + 'px', left: rect.left + 'px', width: rect.width + 'px' });
		}

		function setupStopSearchOSM() {
			if ($searchResults) {
				return; // Already wired for this page load.
			}
			$searchResults = $('<div>', { class: 'osm-location-autocomplete' }).css({
				position: 'fixed', background: '#fff', border: '1px solid #ddd', borderRadius: '4px',
				maxHeight: '200px', overflowY: 'auto', zIndex: 999999, display: 'none',
				boxShadow: '0 4px 6px rgba(0,0,0,0.1)'
			}).appendTo('body');

			$stopSearch.on('input', function () {
				clearTimeout(searchDebounce);
				var query = $stopSearch.val().trim();
				if (query.length < 3) {
					$searchResults.hide();
					return;
				}
				searchDebounce = setTimeout(function () {
					positionSearchDropdown();
					$searchResults.show().html('<div style="padding:10px;text-align:center;color:#666;">Searching…</div>');
					searchStopCandidates(query).then(function (results) {
						$searchResults.empty();
						if (results.length === 0) {
							$searchResults.html('<div style="padding:10px;color:#666;">No results found</div>');
							return;
						}
						results.forEach(function (result) {
							var $item = $('<div>', { text: result.display_name })
								.css({ padding: '10px', cursor: 'pointer', borderBottom: '1px solid #eee' })
								.on('mouseenter', function () { $(this).css('background', '#f5f5f5'); })
								.on('mouseleave', function () { $(this).css('background', '#fff'); })
								.on('click', function () {
									$searchResults.hide();
									addStop(shortNameFor(result), result.lat, result.lon);
									$stopSearch.val('');
								});
							$searchResults.append($item);
						});
					});
				}, 300);
			});

			$(window).on('scroll resize', function () {
				if ($searchResults.is(':visible')) {
					positionSearchDropdown();
				}
			});
			$(document).on('click', function (e) {
				if (e.target !== $stopSearch[0] && $searchResults && !$searchResults.is(e.target) && $searchResults.has(e.target).length === 0) {
					$searchResults.hide();
				}
			});
		}

		// One-name-at-a-time geocode, used only to re-plot a route's already
		// saved stops when the Edit modal opens - same bias, same proxy as
		// the live search above, just a single best match instead of a list.
		function geocodeName(name) {
			if (cfg.mapType === 'openstreetmap') {
				return searchStopCandidates(name).then(function (results) {
					return results.length > 0 ? { lat: results[0].lat, lng: results[0].lon } : null;
				});
			}

			if (typeof google === 'undefined' || !google.maps) {
				return Promise.resolve(null);
			}
			if (!geocoder) {
				geocoder = new google.maps.Geocoder();
			}
			var bias = (cfg.defaultLat && cfg.defaultLng)
				? new google.maps.Circle({ center: { lat: parseFloat(cfg.defaultLat), lng: parseFloat(cfg.defaultLng) }, radius: 100000 }).getBounds()
				: null;
			return new Promise(function (resolve) {
				geocoder.geocode({ address: name, bounds: bias }, function (results, status) {
					if (status === 'OK' && results && results[0]) {
						resolve({ lat: results[0].geometry.location.lat(), lng: results[0].geometry.location.lng() });
					} else {
						resolve(null);
					}
				});
			});
		}

		function loadStopsFromNames(names) {
			stops = [];
			clearMarkers();
			renderStopList();
			syncWaypointsField();
			refreshRouteLine();

			var index = 0;
			function next() {
				if (index >= names.length) {
					fitMapToStops();
					return;
				}
				var name = names[index++];
				geocodeName(name).then(function (result) {
					if (result) {
						// Keep the exact saved name (not the geocoder's possibly
						// reworded display text) so re-saving doesn't silently
						// rewrite data the admin already had.
						stops.push({ name: name, lat: result.lat, lng: result.lng });
						addMarkerForStop(stops[stops.length - 1], stops.length - 1);
						renderStopList();
						syncWaypointsField();
						refreshRouteLine();
					}
					next();
				});
			}
			next();
		}

		function initRouteMap() {
			var canvas = document.getElementById('mptbm-route-map');
			if (!canvas) {
				return; // Map disabled site-wide.
			}

			if (cfg.mapType === 'openstreetmap') {
				if (typeof L === 'undefined') {
					return;
				}
				if (canvas._leaflet_id) {
					canvas._leaflet_id = null;
					canvas.innerHTML = '';
				}
				map = L.map(canvas).setView([ parseFloat(cfg.defaultLat) || 23.8103, parseFloat(cfg.defaultLng) || 90.4125 ], 12);
				L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
					attribution: '© OpenStreetMap contributors'
				}).addTo(map);

				setupStopSearchOSM();

				window.setTimeout(function () {
					if (map) { map.invalidateSize(); }
				}, 300);
			} else {
				if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
					return;
				}
				var center = { lat: parseFloat(cfg.defaultLat) || 23.8103, lng: parseFloat(cfg.defaultLng) || 90.4125 };
				map = new google.maps.Map(canvas, { zoom: 12, center: center });

				if (!searchBound && google.maps.places) {
					var autocomplete = new google.maps.places.Autocomplete($stopSearch[0]);
					// Soft bias (not a hard filter, like the OSM path's lat/lon) so
					// results near the business's own city rank first.
					autocomplete.setBounds(new google.maps.Circle({ center: center, radius: 100000 }).getBounds());
					autocomplete.addListener('place_changed', function () {
						var place = autocomplete.getPlace();
						if (place && place.geometry) {
							var location = place.geometry.location;
							addStop(place.name || place.formatted_address, location.lat(), location.lng());
						}
						$stopSearch.val('');
					});
					searchBound = true;
				}
			}
		}

		function resetForm() {
			$form[0].reset();
			$form.find('[data-route-post-id]').val('');
			$form.find('[data-route-message]').text('').removeClass('is-error is-success');
			stops = [];
			clearMarkers();
			renderStopList();
			syncWaypointsField();
			refreshRouteLine();
			$stopSearch.val('');
		}

		function openModal(titleText, submitLabel) {
			$modal.find('[data-route-title]').text(titleText);
			$modal.find('[data-route-submit-label]').text(submitLabel);
			$modal.attr('aria-hidden', 'false').addClass('is-open');
			$('body').addClass('mptbm-routes-modal-open');
			window.setTimeout(initRouteMap, 120);
		}

		function closeModal() {
			$modal.attr('aria-hidden', 'true').removeClass('is-open');
			$('body').removeClass('mptbm-routes-modal-open');
			resetForm();
		}

		$(document).on('click', '[data-route-open]', function () {
			mode = 'add';
			resetForm();
			openModal(cfg.addTitle || 'Add Route', cfg.addLabel || 'Add route');
		});

		$(document).on('click', '[data-route-close]', function (e) {
			if (e.target === this) {
				closeModal();
			}
		});

		$(document).on('click', '.mptbm-routes-modal-backdrop, .mptbm-routes-modal-close', function () {
			closeModal();
		});

		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' && $modal.hasClass('is-open')) {
				closeModal();
			}
		});

		$(document).on('click', '[data-route-edit]', function () {
			var $card = $(this).closest('.mptbm-routes-card');
			mode = 'edit';
			resetForm();

			$form.find('[data-route-post-id]').val($card.data('post-id'));
			$form.find('#mptbm-route-name').val($card.data('title'));

			var waypoints = ($card.data('waypoints') || '').toString();
			var names = waypoints.split(',').map(function (s) { return s.trim(); }).filter(Boolean);

			openModal(cfg.editTitle || 'Edit Route', cfg.saveLabel || 'Save changes');
			window.setTimeout(function () { loadStopsFromNames(names); }, 250);
		});

		$(document).on('click', '[data-route-delete]', function () {
			var $card = $(this).closest('.mptbm-routes-card');
			var postId = $card.data('post-id');
			if (!window.confirm(cfg.confirmDelete || 'Move this route to Trash?')) {
				return;
			}
			$.post(cfg.ajaxUrl, {
				action: cfg.deleteAction,
				nonce: cfg.nonce,
				post_id: postId
			}).done(function (resp) {
				if (resp && resp.success) {
					$card.remove();
					setCount($grid.find('.mptbm-routes-card').length);
				} else {
					window.alert((resp && resp.data && resp.data.message) || cfg.genericError);
				}
			}).fail(function () {
				window.alert(cfg.genericError);
			});
		});

		$form.on('submit', function (e) {
			e.preventDefault();

			var name = $form.find('#mptbm-route-name').val().trim();
			var waypoints = $waypointsField.val().trim();
			var $message = $form.find('[data-route-message]');
			if (!name) {
				$message.text(cfg.requiredName || 'Enter a name for this route.').addClass('is-error');
				return;
			}
			if (!waypoints) {
				$message.text(cfg.requiredStops || 'Search and add at least one stop for this route.').addClass('is-error');
				return;
			}

			var isEdit = mode === 'edit';
			var payload = {
				action: isEdit ? cfg.updateAction : cfg.addAction,
				nonce: cfg.nonce,
				post_id: $form.find('[data-route-post-id]').val(),
				title: name,
				waypoints: waypoints
			};

			var $submit = $form.find('[data-route-submit]');
			var originalLabel = $submit.find('[data-route-submit-label]').text();
			$submit.prop('disabled', true).find('[data-route-submit-label]').text(isEdit ? (cfg.savingLabel || 'Saving…') : (cfg.addingLabel || 'Adding…'));
			$message.text('').removeClass('is-error is-success');

			$.post(cfg.ajaxUrl, payload).done(function (resp) {
				if (resp && resp.success) {
					var $existing = $grid.find('.mptbm-routes-card[data-post-id="' + resp.data.postId + '"]');
					if ($existing.length) {
						$existing.replaceWith(resp.data.card);
					} else {
						$grid.prepend(resp.data.card);
					}
					setCount($grid.find('.mptbm-routes-card').length);
					closeModal();
				} else {
					$message.text((resp && resp.data && resp.data.message) || cfg.genericError).addClass('is-error');
				}
			}).fail(function () {
				$message.text(cfg.genericError).addClass('is-error');
			}).always(function () {
				$submit.prop('disabled', false).find('[data-route-submit-label]').text(originalLabel);
			});
		});

		var $search = $('[data-routes-search]');
		var $searchEmpty = $('[data-routes-search-empty]');

		function applySearch() {
			var term = $.trim($search.val() || '').toLowerCase();
			var $cards = $grid.find('.mptbm-routes-card');
			var visibleCount = 0;

			$cards.each(function () {
				var $card = $(this);
				var matches = !term || (($card.data('title') || '').toString().toLowerCase().indexOf(term) !== -1);
				$card.toggle(matches);
				if (matches) {
					visibleCount++;
				}
			});

			$searchEmpty.toggleClass('is-hidden', !(term && visibleCount === 0 && $cards.length > 0));
		}

		$search.on('input', applySearch);
	});
})(jQuery);
