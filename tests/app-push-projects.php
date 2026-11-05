<?php
/**
 * Push delivery when the site's devices belong to more than one app (customer app, driver app, Expo Go).
 * Expo's push service is stubbed, so no network is used and nothing is stored.
 *
 * Run with: wp --path=/var/www/html/magepeople eval-file wp-content/plugins/ecab-taxi-booking-manager/tests/app-push-projects.php
 */
if (!defined('ABSPATH')) {
	die;
}
$failures = array();
$checks   = 0;
$check    = static function ($condition, $message) use (&$failures, &$checks): void {
	++$checks;
	if (!$condition) {
		$failures[] = $message;
	}
};
if (!class_exists('MPTBM_App_Push')) {
	echo "SKIPPED: the app API is required.\n";
	return;
}

$requests = 0;
$mode     = 'mixed';
$project  = static function (string $token): string {
	return false !== strpos($token, 'DRV') ? '@owner/driver' : '@owner/customer';
};
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$requests, &$mode, $project) {
	if (false === strpos($url, 'exp.host/--/api/v2/push/send')) {
		return $pre;
	}
	++$requests;
	$tokens   = array_column(json_decode($args['body'], true), 'to');
	$response = static function (int $code, array $body) {
		return array('headers' => array(), 'body' => wp_json_encode($body), 'response' => array('code' => $code, 'message' => ''), 'cookies' => array(), 'filename' => null);
	};
	if (count(array_unique(array_map($project, $tokens))) > 1) {
		$details = array();
		foreach ($tokens as $token) {
			$details[$project($token)][] = $token;
		}
		return $response(400, array('errors' => array(array('code' => 'PUSH_TOO_MANY_EXPERIENCE_IDS', 'message' => 'one project at a time', 'details' => 'nogroups' === $mode ? array() : $details))));
	}
	return $response(200, array('data' => array_map(static function ($token) use ($mode, $project) {
		return 'creds' === $mode && '@owner/driver' === $project($token) ? array('status' => 'error', 'message' => 'no key', 'details' => array('error' => 'InvalidCredentials')) : array('status' => 'ok');
	}, $tokens)));
}, 10, 3);

$messages = static function (array $tokens): array {
	return array_map(static function ($t) {
		return array('to' => $t, 'title' => 'Hi', 'body' => 'x');
	}, $tokens);
};
$tokens = array('ExponentPushToken[C1]', 'ExponentPushToken[DRV1]', 'ExponentPushToken[C2]', 'ExponentPushToken[DRV2]');

$result = MPTBM_App_Push::deliver($messages($tokens));
$check(4 === count($result['tickets']) && 4 === count(array_filter($result['tickets'], static function ($t) { return 'ok' === $t['status']; })), 'devices from two apps are all reached');
$check(array() === $result['errors'], 'nothing is reported as failed after the split');
$check(3 === $requests, 'one refused request, then one request per app');

$mode   = 'nogroups';
$result = MPTBM_App_Push::deliver($messages($tokens));
$check(4 === count(array_filter($result['tickets'], static function ($t) { return 'ok' === $t['status']; })), 'without Expo\'s grouping each device is sent alone and still arrives');

$mode   = 'creds';
$result = MPTBM_App_Push::deliver($messages($tokens));
$check(2 === count(array_filter($result['tickets'], static function ($t) { return 'ok' === $t['status']; })), 'the app with a valid key is reached');
$check(1 === count($result['errors']) && 2 === reset($result['errors']) && false !== strpos((string) key($result['errors']), 'Firebase'), 'the other app is reported with a reason that names the Firebase key');

echo $failures ? "FAILED:\n - " . implode("\n - ", $failures) . "\n" : "OK ($checks checks)\n";
