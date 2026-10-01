/**
 * DOM regression checks for the native pickup input.
 * Install test-only dependencies outside the plugin:
 * npm install --prefix /tmp/ecab-native-time-check jsdom jquery@3 flatpickr@4.6.13
 * NODE_PATH=/tmp/ecab-native-time-check/node_modules node tests/native-pickup-time.cjs
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { JSDOM } = require('jsdom');
const dom = new JSDOM('<div id="form"><input id="mptbm_map_start_date"><input id="mptbm_start_time" type="text" data-mptbm-schedule-time="true" data-invalid-time="Unavailable time"><input id="mptbm_map_start_time"><ul class="start_time_list"></ul></div>');
const $ = require('jquery')(dom.window);
const source = fs.readFileSync(path.join(__dirname, '../assets/frontend/mptbm_registration.js'), 'utf8');
const start = source.indexOf('function mptbmNormalizeTimeToken(');
const end = source.indexOf("$(document).on('change', '#mptbm_start_time", start);
assert.ok(start >= 0 && end > start);
global.window = dom.window;
global.document = dom.window.document;
global.HTMLElement = dom.window.HTMLElement;
global.Node = dom.window.Node;
const flatpickr = require('flatpickr');
const context = vm.createContext({ $, flatpickr });
vm.runInContext(source.slice(start, end), context);
const form = $('#form');
const input = form.find('[data-mptbm-schedule-time]')[0];
const hidden = form.find('#mptbm_map_start_time');
const list = form.find('ul');
function slots(times) {
	list.empty();
	for (const time of times) {
		$('<li>').attr({ 'data-time': time, 'data-value': Number(time).toFixed(2) }).appendTo(list);
	}
}
function sync(time) {
	input.value = time;
	context.mptbmSyncNativePickupTime(form);
}
slots(['06.00', '06.30', '07.00']);
sync('06:00');
assert.equal(input.disabled, true, 'Choose a date before entering time');
assert.equal(hidden.val(), '');
form.find('#mptbm_map_start_date').val('2026-10-05');
sync('06:00');
assert.equal(input.disabled, false);
assert.equal(input.min, '06:00');
assert.equal(input._flatpickr.config.minTime.getHours(), 6);
assert.equal(input._flatpickr.config.maxTime.getHours(), 7);
assert.equal(input._flatpickr.config.disableMobile, true);
assert.equal(input.max, '07:00');
assert.equal(input.step, '1800');
assert.equal(input.checkValidity(), true);
assert.equal(hidden.val(), '6.00', 'Preserve the legacy search value');
sync('06:15');
assert.equal(input.checkValidity(), false, 'Reject times between configured slots');
assert.equal(hidden.val(), '');
sync('05:30');
assert.equal(input.checkValidity(), false, 'Reject times before opening');
sync('07:30');
assert.equal(input.checkValidity(), false, 'Reject times after closing');
list.find('[data-time="06.30"]').attr('aria-disabled', 'true');
sync('06:30');
assert.equal(input.checkValidity(), false, 'Reject booked slots');
assert.equal(hidden.val(), '');
sync('07:00');
assert.equal(input.checkValidity(), true, 'Accept closing boundary');
slots(['10.00', '10.30']);
context.mptbmSyncNativePickupTime(form);
assert.equal(hidden.val(), '', 'Invalidate the old selection when hours change');
slots(['00.50', '01.20', '01.50', '24.00']);
sync('01:20');
assert.equal(input.checkValidity(), true, 'Intervals can start off the whole hour');
assert.equal(hidden.val(), '1.20');
assert.equal(input.max, '01:50', '24:00 is not a native time value');
slots(['06.35', '07.05', '07.35']);
sync('06:00');
assert.equal(input.checkValidity(), false, 'Reject times removed by the booking buffer');
sync('07:05');
assert.equal(input.checkValidity(), true, 'Use the buffer-adjusted interval origin');
slots(['00.00', '00.30']);
sync('00:00');
assert.equal(input.checkValidity(), true, 'Accept midnight when configured');
assert.equal(hidden.val(), '0.00');
slots([]);
sync('');
assert.equal(input.disabled, true, 'Disable days with no generated options');
assert.equal(hidden.val(), '');
console.log('Native pickup: date requirement, schedule bounds, intervals, booked slots, search values, date changes and midnight passed');

// Exercise the actual time spinner: attempts outside the bounds are clamped.
slots(['06.00', '06.30', '07.00']);
sync('06:30');
input._flatpickr.setDate('06:30', false, 'H:i');
input._flatpickr.hourElement.value = '09';
input._flatpickr.hourElement.dispatchEvent(new dom.window.Event('blur'));
assert.equal(input.value, '07:00', 'Spinner cannot go past closing');
input._flatpickr.hourElement.value = '04';
input._flatpickr.hourElement.dispatchEvent(new dom.window.Event('blur'));
assert.equal(input.value, '06:00', 'Spinner cannot go before opening');
input._flatpickr.destroy();
console.log('Actual Flatpickr spinner: opening and closing constraints passed');
