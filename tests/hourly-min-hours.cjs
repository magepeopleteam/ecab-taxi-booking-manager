/**
 * DOM checks for the hourly "Select Hours" minimum (drive time rounded up).
 * npm install --prefix /tmp/ecab-native-time-check jsdom jquery@3
 * NODE_PATH=/tmp/ecab-native-time-check/node_modules node tests/hourly-min-hours.cjs
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { JSDOM } = require('jsdom');
const items = [1, 2, 3, 4, 5, 6, 7, 8].map((i) => `<li data-value="${i}">${i} Hours</li>`).join('');
const dom = new JSDOM(`<input name="mptbm_price_based" value="fixed_hourly"><input id="mptbm_calculated_duration" value="0">
<div class="inputList mp_input_select"><input type="hidden" id="mptbm_fixed_hours" value="2"><input type="text" class="formControl" value="2 Hours"><ul class="mp_input_select_list">${items}</ul></div>
<div id="mptbm_fixed_hours_warning" style="display:none"><span></span></div>`);
const $ = require('jquery')(dom.window);
global.window = dom.window;
global.document = dom.window.document;
const source = fs.readFileSync(path.join(__dirname, '../assets/frontend/mptbm_registration.js'), 'utf8');
const start = source.indexOf('function mptbmRequiredHours(');
const end = source.indexOf("jQuery(document).on('mp_change change', '#mptbm_fixed_hours'", start);
assert.ok(start >= 0 && end > start);
const context = vm.createContext({ jQuery: $, document: dom.window.document, parseInt, parseFloat, Math });
vm.runInContext(source.slice(start, end), context);
const duration = (s) => { $('#mptbm_calculated_duration').val(s); context.mptbm_update_fixed_hours_warning(); };
const disabled = () => $('li[aria-disabled="true"]').map((_, e) => +e.dataset.value).get();
const hint = () => $('#mptbm_fixed_hours_warning');

assert.equal(context.mptbmRequiredHours(4 * 3600 + 20 * 60), 5);
assert.equal(context.mptbmRequiredHours(4 * 3600), 4);
assert.equal(context.mptbmRequiredHours(30), 1);
assert.equal(context.mptbmRequiredHours(0), 0);

duration(0); // no route: everything open, nothing changed
assert.deepEqual(disabled(), []);
assert.equal($('#mptbm_fixed_hours').val(), '2');

duration(4 * 3600 + 20 * 60); // 4h20m -> 5
assert.deepEqual(disabled(), [1, 2, 3, 4]);
assert.equal($('#mptbm_fixed_hours').val(), '5');
assert.equal($('input.formControl').val(), '5 Hours');
assert.equal(hint().css('display'), 'flex');
assert.match(hint().text(), /4h 20m.*at least 5 hours/);

$('#mptbm_fixed_hours').val('7'); // user raises it: kept
duration(4 * 3600 + 20 * 60);
assert.equal($('#mptbm_fixed_hours').val(), '7');

duration(20 * 60); // shorter route frees the lower hours again
assert.deepEqual(disabled(), []);
assert.equal($('#mptbm_fixed_hours').val(), '7');

duration(11 * 3600); // longer than every offer: flagged, longest held
assert.equal(disabled().length, 8);
assert.equal($('#mptbm_fixed_hours').val(), '8');
assert.match(hint().text(), /longer than the longest/);

duration(0); // route cleared
assert.deepEqual(disabled(), []);
assert.equal(hint().css('display'), 'none');
console.log('hourly-min-hours: OK');
