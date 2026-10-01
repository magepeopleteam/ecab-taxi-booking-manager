/**
 * Run with: node tests/weekday-schedule.cjs
 * Exercise the booking template's actual weekday lookup in customer timezones.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { execFileSync } = require('node:child_process');

if (!process.argv.includes('--timezone-check')) {
	for (const timezone of ['UTC', 'America/New_York', 'America/Los_Angeles', 'Pacific/Honolulu', 'Asia/Dhaka', 'Pacific/Kiritimati']) {
		const output = execFileSync(process.execPath, [__filename, '--timezone-check'], {
			env: { ...process.env, TZ: timezone },
			encoding: 'utf8',
		});
		process.stdout.write(output);
	}
} else {
	const template = fs.readFileSync(path.join(__dirname, '../templates/registration/get_details.php'), 'utf8');
	const start = template.indexOf('function updateTimeRangeForDay(selectedDate)');
	const end = template.indexOf('function updateTimePickerOptions(', start);
	assert.ok(start >= 0 && end > start, 'Schedule lookup must exist in the booking template');
	const source = template.slice(start, end).replace(/<\?php[\s\S]*?\?>/g, '0');
	const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
	const starts = [10, 6, 7, 8, 9, 11, 12];
	let actual;
	const context = {
		dayTimeRanges: Object.fromEntries(days.map((day, index) => [day, { start: [starts[index]], end: [20] }])),
		updateTimePickerOptions: (...args) => { actual = args; },
	};
	vm.createContext(context);
	vm.runInContext(source, context);
	for (let index = 0; index < days.length; index++) {
		const date = '2026-10-' + String(4 + index).padStart(2, '0');
		context.updateTimeRangeForDay(date);
		assert.deepEqual(actual, [starts[index], 20, date], days[index] + ' must use its own hours');
	}
	// Fleet ranges still use the earliest opening and latest closing for that day.
	context.dayTimeRanges.monday = { start: [8, 6], end: [18, 22] };
	context.updateTimeRangeForDay('2026-10-05');
	assert.deepEqual(actual, [6, 22, '2026-10-05']);
	delete context.dayTimeRanges.monday;
	context.updateTimeRangeForDay('2026-10-05');
	assert.deepEqual(actual, [1, 0, '2026-10-05'], 'Missing weekday hours must generate no options');
	actual = undefined;
	context.updateTimeRangeForDay('');
	assert.equal(actual, undefined, 'An empty date must not rebuild options');
	console.log(process.env.TZ + ': all seven weekdays, fleet range and empty date passed');
}
