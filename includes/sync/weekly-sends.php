<?php
/**
 * Weekly sends cohort sync.
 *
 * Runs once a week to pull the prior week's email sends from Iterable and
 * bucket users by send count into the idemailwiz_sends_by_week table.
 */

if (!defined('ABSPATH')) {
	exit;
}

function idemailwiz_schedule_weekly_cron()
{
	if (!wp_next_scheduled('idemailwiz_weekly_send_sync')) {
		wp_schedule_event(strtotime('next Monday 1am'), 'weekly_monday_morning', 'idemailwiz_weekly_send_sync');
	}
}
add_action('init', 'idemailwiz_schedule_weekly_cron');

add_action('idemailwiz_weekly_send_sync', 'idemailwiz_sync_sends_weekly_cron');

function idemailwiz_sync_sends_weekly_cron()
{
	wiz_log('Starting weekly send sync...');
	$currentDate = new DateTime();
	$currentDate->modify('previous Monday');
	$year = $currentDate->format('Y');
	$week = $currentDate->format('W');
	$syncSends = idemailwiz_sync_sends_by_week($year, $week);
	if ($syncSends > 0) {
		wiz_log('Weekly send sync complete.');
	} else {
		wiz_log('Weekly send sync failed.');
	}
}


function idemailwiz_sync_sends_by_week($year, $week)
{
	// if week is 1 digit, convert it to 2 with a zero in front
	if (strlen($week) == 1) {
		$week = '0' . $week;
	}
	global $wpdb;
	$return = 0;
	$sends_by_week_table = $wpdb->prefix . 'idemailwiz_sends_by_week';

	// Calculate the start and end dates for the given year and week
	$startDate = date(
		'Y-m-d',
		strtotime("$year-W$week-1")
	);
	$endDate = date(
		'Y-m-d',
		strtotime("$year-W$week-7")
	);

	// Calculate the month based on the start date
	$month = date('n', strtotime($startDate));

	// Make the API call to Iterable to fetch send data for the specified week
	$sendDataGenerator = idemailwiz_fetch_sends(null, $startDate, $endDate);

	// Process the send data and prepare it for insertion into the sends_by_week table
	// Process the send data and prepare it for insertion into the sends_by_week table
	$userSendCounts = [];
	$userIdsBySendCount = [];

	foreach ($sendDataGenerator as $send) {
		$userId = $send['userId'];
		if (
			$userId === null || $userId === '' || $userId == '0'
		) {
			continue;
		}

		// Increment count, or start new count if it doesn't exist
		if (isset($userSendCounts[$userId])) {
			$userSendCounts[$userId]++;
		} else {
			$userSendCounts[$userId] = 1;
		}
	}

	// Aggregate the user send counts into cohorts and store user IDs for each send count
	foreach ($userSendCounts as $userId => $sendCount) {
		if (!isset($userIdsBySendCount[$sendCount])) {
			$userIdsBySendCount[$sendCount] = [];
		}
		$userIdsBySendCount[$sendCount][] = $userId;
	}

	// Insert or update the records in the sends_by_week table
	foreach ($userIdsBySendCount as $sends => $userIds) {
		// Skip cohorts with more than 25 sends (should exclude seed list people)
		if ($sends > 30) {
			continue;
		}

		$totalUsers = count($userIds);
		$serializedUserIds = serialize($userIds);

		// Check if a record already exists for the given year, month, week, and sends
		$existingRecord = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $sends_by_week_table WHERE year = %d AND month = %d AND week = %d AND sends = %d",
			$year,
			$month,
			$week,
			$sends
		));

		if ($existingRecord) {
			// Update the existing record
			$return = $wpdb->update(
				$sends_by_week_table,
				['total_users' => $totalUsers, 'userIds' => $serializedUserIds],
				['year' => $year, 'month' => $month, 'week' => $week, 'sends' => $sends]
			);
		} else {
			// Insert a new record
			$return = $wpdb->insert(
				$sends_by_week_table,
				[
					'year' => $year,
					'month' => $month,
					'week' => $week,
					'sends' => $sends,
					'total_users' => $totalUsers,
					'userIds' => $serializedUserIds
				]
			);
		}
	}
	return $return;
}

function idemailwiz_fetch_sends($campaignId = null, $startDateTime = null, $endDateTime = null)
{
	$baseUrl = 'https://api.iterable.com/api/export/data.csv';

	$queryParams = [
		'dataTypeName' => 'emailSend',
	];

	if ($startDateTime || $endDateTime) {
		if ($startDateTime) {
			$queryParams['startDateTime'] = $startDateTime;
		}
		if ($endDateTime) {
			$queryParams['endDateTime'] = $endDateTime;
		}
	} else {
		// If no date range is provided, default to the previously completed week
		$currentDate = new DateTime();
		$currentDate->setTimezone(new DateTimeZone('UTC'));
		$currentDate->modify('last Saturday');
		$endDateTime = $currentDate->format('Y-m-d\T23:59:59\Z');
		$currentDate->modify('previous Sunday');
		$startDateTime = $currentDate->format('Y-m-d\T00:00:00\Z');

		$queryParams['startDateTime'] = $startDateTime;
		$queryParams['endDateTime'] = $endDateTime;
	}

	if ($campaignId) {
		$queryParams['campaignId'] = $campaignId;
	}

	$queryString = http_build_query($queryParams);
	$url = $baseUrl . '?' . $queryString . '&onlyFields=createdAt&onlyFields=userId&onlyFields=campaignId';

	try {
		$response = idemailwiz_iterable_curl_call($url);
	} catch (Throwable $e) {
		wiz_log("Error encountered for fetch sends curl call to : " . $url . " - " . $e->getMessage());
		return [];
	}

	$tempFile = tmpfile();
	fwrite($tempFile, $response['response']);
	fseek($tempFile, 0);

	$file = new SplFileObject(stream_get_meta_data($tempFile)['uri']);
	$file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
	$file->rewind();

	$headers = $file->current();
	$file->next();

	while (!$file->eof()) {
		$values = $file->current();
		if (!empty($values)) {
			$values = array_pad($values, count($headers), '');
			yield array_combine($headers, $values);
		}
		$file->next();
	}

	fclose($tempFile);
}
