<?php get_header(); ?>
<?php if (have_posts()):
    while (have_posts()):
        the_post();

        $workflowId = get_field('workflow_id');
        $journeyCampaigns = get_idwiz_campaigns(['workflowId' => $workflowId, 'sortBy' => 'startAt', 'sort' => 'ASC']);

        // Check if the startDate and endDate parameters are present in the $_GET array
        $startDate = $_GET['startDate'] ?? '2021-11-01';
        $endDate = $_GET['endDate'] ?? date('Y-m-d');

        $startDateDT = new DateTime($startDate);
        $endDateDT = new DateTime($endDate);

        $journeySends = get_idemailwiz_triggered_data('idemailwiz_triggered_sends', ['campaignIds' => array_column($journeyCampaigns, 'id')], 50000);
        $journeySendAts = array_column($journeySends, 'startAt');

        $defFirstJourneySend = min($journeySendAts);
        $defLastJourneySend = max($journeySendAts);

        // Get the definitive first and last send from the whole dataset

        // Initialize the campaignSends array
        $campaignSends = [];

        // Filter sends that are within the specified date range
        $filteredSends = array_filter($journeySends, function ($send) use ($startDateDT, $endDateDT) {
            $sendDate = new DateTime(date('Y-m-d', $send['startAt'] / 1000));
            return $sendDate >= $startDateDT && $sendDate <= $endDateDT;
        });

        // Extract startAt values from filtered sends
        $sendAts = array_column($filteredSends, 'startAt');

        // Calculate min and max only if there are sends in the filtered result
        if (!empty($sendAts)) {
            $firstJourneySend = min($sendAts);
            $lastJourneySend = max($sendAts);
        } else {
            // Fallback if no sends are in the specified range
            $firstJourneySend = $startDateDT->getTimestamp() * 1000;
            $lastJourneySend = $endDateDT->getTimestamp() * 1000;
        }

        // Loop through each send in the journeySends array
        foreach ($journeySends as $send) {
            // Extract the campaignId, startAt, and messageId from the current send
            $campaignId = $send['campaignId'];
            $startAt = $send['startAt'];
            $messageId = $send['messageId']; // Assuming each send has a messageId field

            // Convert timestamp to date
            $sendDate = date('Y-m-d', $startAt / 1000);

            // Check if this campaignId has already been encountered
            if (!isset($campaignSends[$campaignId])) {
                // If not, initialize the structure for this campaignId
                $campaignSends[$campaignId] = [
                    'title' => '',
                    // Placeholder, set the actual title if available
                    'firstSend' => $startAt,
                    'lastSend' => $startAt,
                    'sends' => [],
                    // To store send dates
                    'sendDetails' => [],
                    // To store the complete send records
                    'messageIdsByDate' => [] // To map dates to message IDs
                ];
            } else {
                // Update the firstSend and lastSend if necessary
                $campaignSends[$campaignId]['firstSend'] = min($campaignSends[$campaignId]['firstSend'], $startAt);
                $campaignSends[$campaignId]['lastSend'] = max($campaignSends[$campaignId]['lastSend'], $startAt);
            }

            // Add the send date to the sends array for this campaign
            if (!in_array($sendDate, $campaignSends[$campaignId]['sends'])) {
                $campaignSends[$campaignId]['sends'][] = $sendDate;
            }

            // Add the complete send record to the sendDetails array
            $campaignSends[$campaignId]['sendDetails'][] = $send;

            // // Map the send date to the messageId
            if (!isset($campaignSends[$campaignId]['messageIdsByDate'][$sendDate])) {
                $campaignSends[$campaignId]['messageIdsByDate'][$sendDate] = [];
            }
            if (!in_array($messageId, $campaignSends[$campaignId]['messageIdsByDate'][$sendDate])) {
                $campaignSends[$campaignId]['messageIdsByDate'][$sendDate][] = $messageId;
            }
        }

        // Sort the campaignSends array by the earliest startAt send record
        uasort($campaignSends, function ($a, $b) {
            return $a['firstSend'] - $b['firstSend'];
        });



        ?>
        <article id="post-<?php the_ID(); ?>" data-initiativeid="<?php echo get_the_ID(); ?>" <?php post_class('has-wiz-chart'); ?>>
            <header class="wizHeader">
                <div class="wizHeaderInnerWrap">
                    <div class="wizHeader-left">
                        <h1 class="wizEntry-title single-wizcampaign-title" title="<?php echo get_the_title(); ?>"
                            itemprop="name">
                            <?php echo get_the_title(); ?>
                        </h1>
                        <div class="wizEntry-meta"><strong>Journey</strong>&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;Send dates:
                            <?php echo date('m/d/Y', $defFirstJourneySend / 1000); ?> -
                            <?php echo date('m/d/Y', $defLastJourneySend / 1000); ?> &nbsp;&nbsp;&#x2022;&nbsp;&nbsp;Includes
                            <?php echo count($journeyCampaigns); ?> campaigns
                        </div>

                    </div>
                    <div class="wizHeader-right">
                        <div class="wizHeader-actions">
                            <button class="wiz-button green sync-journey"
                                data-journeyids="<?php echo htmlspecialchars(json_encode(array_column($journeyCampaigns, 'id'))); ?>">Sync
                                Journey</button>
                            <?php include plugin_dir_path(__FILE__) . 'parts/module-user-settings-form.php'; ?>

                        </div>
                    </div>
                </div>
            </header>

            <div class="entry-content" itemprop="mainContentOfPage">
                <?php include plugin_dir_path(__FILE__) . 'parts/dashboard-date-pickers.php'; ?>
                <?php
                if (isset($_GET['highlight'])) {
                    $highlightId = $_GET['highlight'];
                    $highlightedCampaign = get_idwiz_campaign($highlightId);
                    echo '<div class="highlight-indicator"><h2>Highlighting "'.$highlightedCampaign['name'].'"</h2>';
                    echo '<a href="'. remove_query_arg( 'highlight').'"><i class="fa-solid fa-xmark"></i></a></div>';
                }
                ?>
                <div class="dragScroll-indicator">Drag timeline to scroll <i class="fa-solid fa-right-long"></i></div>
                <div class="journey-timeline idwiz-dragScroll">
                    <?php
                    // Start and end dates
                    $firstSend = new DateTime(date('Y-m-d', $firstJourneySend / 1000));
                    $lastSend = new DateTime(date('Y-m-d', $lastJourneySend / 1000));



                    // Determine if the current startDate is after the firstSend date
                    if ($startDateDT > $firstSend) {
                        $showStartDate = $startDateDT;
                    } else {
                        $showStartDate = $firstSend;
                    }
                    if ($endDateDT < $lastSend) {
                        $showEndDate = $endDateDT;
                    } else {
                        $showEndDate = $lastSend;
                    }

                    // Calculate the interval and the number of days
                    $sendInterval = $showStartDate->diff($showEndDate);
                    $totalSendDays = $sendInterval->days + 1; // Including both start and end date
            
                    // Displaying the top row with dates
                    ?>
                    <div class="timeline-campaign-row date-row">
                        <div class="timeline-campaign-title">
                            Send Dates
                        </div>
                        <div class="timeline-campaign-cell-wrap">
                            <?php
                            for ($day = 0; $day < $totalSendDays; $day++) {
                                $cellDate = clone $showStartDate;
                                $cellDate->modify("+$day day");
                                echo "<div class='timeline-cell send-date'>" . $cellDate->format('D\<\/\b\r\>n/j') . "</div>";
                            }
                            ?>
                        </div>
                    </div>

                    <?php
                    // Fetch message open and click data for all campaigns in the journey
                    $allMessageOpens = get_idemailwiz_triggered_data('idemailwiz_triggered_opens', ['campaignIds' => array_column($journeyCampaigns, 'id')]);
                    $allMessageClicks = get_idemailwiz_triggered_data('idemailwiz_triggered_clicks', ['campaignIds' => array_column($journeyCampaigns, 'id')]);


                    

                    // Preprocess opens and clicks into a lookup table by messageId
                    $opensByMessageId = [];
                    $clicksByMessageId = [];

                    foreach ($allMessageOpens as $open) {
                        $messageId = $open['messageId'];
                        if (!isset($opensByMessageId[$messageId])) {
                            $opensByMessageId[$messageId] = 0;
                        }
                        $opensByMessageId[$messageId]++;
                    }

                    foreach ($allMessageClicks as $click) {
                        $messageId = $click['messageId'];
                        if (!isset($clicksByMessageId[$messageId])) {
                            $clicksByMessageId[$messageId] = 0;
                        }
                        $clicksByMessageId[$messageId]++;
                    }

                    // Loop through each campaign
                    $highlight = '';
                    foreach ($campaignSends as $campaignId => $send) {
                        $wizCampaign = get_idwiz_campaign($campaignId);
                        if (isset($_GET['highlight'])) {
                            $highlightId = $_GET['highlight'];
                            if ($highlightId == $campaignId) {
                                $highlight = 'highlight';
                            } else {
                                $highlight = '';
                            }
                        }

                        ?>
                        <div class="timeline-campaign-row <?php echo $highlight; ?>">
                            <div class="timeline-campaign-title">
                                <a href="<?php echo get_bloginfo('url').'/metrics/campaign?id='.$wizCampaign['id']; ?>"><?php echo $wizCampaign['name']; ?></a>
                            </div>
                            <div class="timeline-campaign-cell-wrap">
                                <?php
                                // Create a cell for each day
                                for ($day = 0; $day < $totalSendDays; $day++) {
                                    $cellDate = clone $firstSend;
                                    $cellDate->modify("+$day day");
                                    $dateString = $cellDate->format('Y-m-d');

                                    $today = date('Y-m-d');

                                    // Check if the campaign has a send on this day
                                    $activeClass = in_array($dateString, $send['sends']) ? 'active' : '';

                                    // Get message IDs for the current date
                                    $messageIds = $send['messageIdsByDate'][$dateString] ?? [];
                                    $totalDateSends = count($messageIds);

                                    // Calculate opens and clicks counts for the current date
                                    $dateMessageOpens = 0;
                                    $dateMessageClicks = 0;
                                    foreach ($messageIds as $messageId) {
                                        if (isset($opensByMessageId[$messageId])) {
                                            $dateMessageOpens += $opensByMessageId[$messageId];
                                        }
                                        if (isset($clicksByMessageId[$messageId])) {
                                            $dateMessageClicks += $clicksByMessageId[$messageId];
                                        }
                                    }

                                    // Output the cell with the correct class
                                    ?>
                                    <div class='timeline-cell <?php echo $activeClass; ?>'>
                                        <?php
                                        if ($activeClass == 'active') {
                                            echo '<a href="' . get_bloginfo('url') . '/metrics/campaign?id=' . $wizCampaign['id'] . '&startDate=' . $dateString . '&endDate=' . $today . '" class="timeline-cell-link"></a><i class="fa-regular fa-envelope"></i>';
                                            ?>
                                            <div class="timeline-cell-popup">
                                                <div class="timeline-cell-popup-title"><?php echo $cellDate->format('m/d/Y'); ?></div>
                                                <div class="timeline-cell-popup-content">
                                                    Sends: <?php echo $totalDateSends; ?><br/>
                                                    Opens: <?php echo $dateMessageOpens; ?> (<?php echo (number_format($dateMessageOpens / $totalDateSends * 100)).'%'; ?>)<br/>
                                                    Clicks: <?php echo $dateMessageClicks; ?> (<?php echo (number_format($dateMessageClicks / $totalDateSends * 100)).'%'; ?>)
                                                    </div>
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <?php
                                }
                                ?>
                            </div>
                        </div>
                        <?php
                    }

                    ?>
                </div>
            </div>
        </article>
    <?php endwhile; endif; ?>
<?php get_footer(); ?>