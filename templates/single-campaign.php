<?php get_header(); ?>
<?php

global $wpdb; // Declare the WordPress Database variable

date_default_timezone_set('America/Los_Angeles');

$campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$campaign = get_idwiz_campaign($campaign_id);

// Check if the startDate and endDate parameters are present in the $_GET array
if (isset($_GET['startDate']) || isset($_GET['endDate'])) {
    if (isset($_GET['startDate']) && $_GET['startDate'] !== '') {
        $startDate = $_GET['startDate'] ?? '2021-11-01';
    }
    if (isset($_GET['endDate']) && $_GET['endDate'] !== '') {
        $endDate = $_GET['endDate'] ?? date('Y-m-d');
    }
} else {
    $campaignStartStamp = (int) ($campaign['startAt'] / 1000);
    $startDate = date('Y-m-d', $campaignStartStamp);
    $endDate = date('Y-m-t');
}




$metrics = get_idwiz_metric($campaign['id']);
$template = get_idwiz_template($campaign['templateId']);



$campaignStartStamp = (int) ($campaign['startAt'] / 1000);
$campaignStartAt = date('m/d/Y', $campaignStartStamp);

$campaignEndStamp = (int) ($campaign['endedAt'] / 1000);
$campaignEndedAt = date('g:ia', $campaignEndStamp);

$campaignEndDateTime = date('m/d/Y \a\t g:ia', $campaignEndStamp);

$sendsTimezone = date('T', $campaignStartStamp);



$purchases = get_idwiz_purchases(array('campaignIds' => [$campaign['id'], 'shoppingCartItems_utmMedium' => 'email']));

//$experimentIds = maybe_unserialize($campaign['experimentIds']) ?? array();
// This returns one row per experiment TEMPLATE
$experiments = get_idwiz_experiments(array('campaignIds' => [$campaign['id']]));
// Returns multiple templates with the same experiment ID, so we de-dupe
$experimentIds = array_unique(array_column($experiments, 'experimentId'));
$linkedExperimentIds = array_map(function ($id) {
    return '<a href="https://app.iterable.com/experiments/monitor?experimentId=' . urlencode($id) . '">' . htmlspecialchars($id) . '</a>';
}, $experimentIds);

?>

<article id="campaign-<?php echo $campaign['id']; ?>" class="wizcampaign-single has-wiz-chart"
    data-campaignid="<?php echo $campaign['id']; ?>">
    <?php
    //fetch_ga_data();
    ?>

    <header class="wizHeader">
        <div class="wizHeaderInnerWrap">

            <div class="wizHeader-left">
                <h1 class="wizEntry-title single-wizcampaign-title" itemprop="name">
                    <?php echo $campaign['name']; ?>
                </h1>
                <div class="wizEntry-meta">
                    <strong>
                        <?php echo $campaign['type']; ?> Campaign <a
                            href="https://app.iterable.com/campaigns/<?php echo $campaign['id']; ?>?view=summary">
                            <?php echo $campaign['id']; ?>
                        </a>
                        <?php if ($experimentIds) {
                            echo '&nbsp;with Experiment ' . implode(', ', $linkedExperimentIds) . '</a>';
                        } ?>
                    </strong>
                    &nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
                    <?php echo $campaign['messageMedium']; ?>

                    &nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
                    <?php if ($campaign['type'] == 'Triggered') {
                        echo 'Last ';
                    } ?>Sent on
                    <?php echo $campaignStartAt; ?>
                    <?php
                    if ($campaignStartAt != $campaignEndDateTime) {
                        echo "— $campaignEndedAt";
                    }
                    echo '&nbsp;' . $sendsTimezone;
                    ?>


                    <?php
                    $journeyPosts = get_posts(['post_type' => 'journey', 'posts_per_page' => -1]);
                    foreach ($journeyPosts as $journeyPost) {
                        if (get_field('workflow_id', $journeyPost->ID) == $campaign['workflowId']) { ?>

                            (<i class="fa-regular fa-calendar"></i> <a
                                href="<?php echo get_bloginfo('url'); ?>?p=<?php echo $journeyPost->ID; ?>&highlight=<?php echo $campaign['id']; ?>">view
                                timeline</a>)
                            <?php
                        }
                    }
                    ?>

                    <?php
                    if (isset($template['clientTemplateId'])) { ?>
                        &nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
                        <?php echo 'Wiz Template: <a href="' . get_bloginfo('url') . '?p=' . $template['clientTemplateId'] . '">' . $template['clientTemplateId'] . '</a>'; ?>
                    <?php } ?>
                </div>
                <?php echo generate_initiative_flags($campaign['id']); ?>
            </div>
            <div class="wizHeader-right">
                <div class="wizHeader-actions">
                    <button class="wiz-button green doWizSync"
                        data-campaignIds="<?php echo esc_attr(json_encode(array($campaign['id']))); ?>"
                        data-metricTypes="<?php echo esc_attr(json_encode(array('blast'))); ?>"><i
                            class="fa-solid fa-arrows-rotate"></i>&nbsp;&nbsp;Sync Metrics</button>

                    <button class="wiz-button green doWizSync"
                    data-campaignIds="<?php echo esc_attr(json_encode(array($campaign['id']))); ?>"
                    data-metricTypes="<?php echo esc_attr(json_encode(array('send','open','click','bounce','sendSkip','unSubscribe','complaint'))); ?>"><i
                        class="fa-solid fa-arrows-rotate"></i>&nbsp;&nbsp;Sync Triggered Data</button>

                    <?php include plugin_dir_path(__FILE__) . 'parts/module-user-settings-form.php'; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="entry-content" itemprop="mainContentOfPage">

        <?php if ($campaign['type'] == 'Triggered') {
            //include plugin_dir_path(__FILE__) . 'parts/dashboard-date-buttons.php'; 
            include plugin_dir_path(__FILE__) . 'parts/dashboard-date-pickers.php';

        }
        ?>

        <?php
        $metricRates = get_idwiz_metric_rates([$campaign['id']], $startDate, $endDate);

        echo get_idwiz_rollup_row($metricRates);
        ?>

        <?php
        if ($campaign['type'] == 'Triggered') {
            ?>
            <div>
                <?php
                $triggeredSends = get_triggered_data_by_campaign_id($campaign['id'], 'send');
                if (!empty($triggeredSends)) {
                    ?>


                    <div class="wizcampaign-sections-row">
                        <div class="wizcampaign-section inset" id="sendsByDateSection">
                            <div class="wizcampaign-section-title-area">
                                <h4>Sends by Date</h4>
                            </div>
                            <div class="wizChartWrapper">


                                <canvas class="sendsByDate wiz-canvas" data-chartid="sendsByDate"
                                    data-campaignids='<?php echo json_encode(array($campaign['id'])); ?>' data-charttype="bar"
                                    data-startdate="<?php echo $startDate; ?>" data-enddate="<?php echo $endDate; ?>"></canvas>
                            </div>
                        </div>

                        <div class="wizcampaign-section inset" id="openedByDateSection">
                            <div class="wizcampaign-section-title-area">
                                <h4>Opens by Date</h4>
                            </div>
                            <div class="wizChartWrapper">


                                <canvas class="opensByDate wiz-canvas" data-chartid="opensByDate"
                                    data-campaignids='<?php echo json_encode(array($campaign['id'])); ?>' data-charttype="bar"
                                    data-startdate="<?php echo $startDate; ?>" data-enddate="<?php echo $endDate; ?>"></canvas>
                            </div>
                        </div>

                        <div class="wizcampaign-section inset" id="clicksByDateSection">
                            <div class="wizcampaign-section-title-area">
                                <h4>Clicks by Date</h4>
                            </div>
                            <div class="wizChartWrapper">


                                <canvas class="clicksByDate wiz-canvas" data-chartid="clicksByDate"
                                    data-campaignids='<?php echo json_encode(array($campaign['id'])); ?>' data-charttype="bar"
                                    data-startdate="<?php echo $startDate; ?>" data-enddate="<?php echo $endDate; ?>"></canvas>
                            </div>
                        </div>
                    </div>

                    <?php
                }
                ?>
            </div>
            <?php
        }

        // Setup standard chart variables
        $standardChartCampaignIds = array($campaign['id']);
        $standardChartPurchases = $purchases;
        include plugin_dir_path(__FILE__) . 'parts/standard-charts.php';

        if ($experiments) {
            ?>
            <div class="wizcampaign-section inset wizcampaign-experiments">
                <div class="wizcampaign-experiments-header">
                    <h2>Experiment Results</h2>

                </div>
                <div class="wizcampaign-experiment-results">
                    <div class="wizcampaign-experiment-metrics">
                        <?php
                        $metrics = [
                            'uniqueEmailSends' => ['label' => 'Sent', 'type' => 'number'],
                            'wizOpenRate' => ['label' => 'Open Rate', 'type' => 'percent'],
                            'wizCtr' => ['label' => 'CTR', 'type' => 'percent'],
                            'wizCto' => ['label' => 'CTO', 'type' => 'percent'],
                            'totalPurchases' => ['label' => 'Purchases', 'type' => 'number'],
                            'revenue' => ['label' => 'Revenue', 'type' => 'currency'],
                            'wizCvr' => ['label' => 'CVR', 'type' => 'percent'],
                            'wizAov' => ['label' => 'AOV', 'type' => 'currency'],
                            'wizUnsubRate' => ['label' => 'Unsub. Rate', 'type' => 'percent'],
                            'confidence' => ['label' => 'Confidence', 'type' => 'percent'],
                            'improvement' => ['label' => 'Improvement', 'type' => 'percent']
                        ];

                        // Calculate max values for each metric
                        $maxValues = [];
                        $topTwoUniqueValues = [];
                        foreach ($metrics as $key => $metric) {
                            $values = array_column($experiments, $key);
                            arsort($values); // Sort in descending order
                            $uniqueValues = array_unique($values);
                            $topTwoUnique = array_slice($uniqueValues, 0, 2);
                            $topTwoUniqueValues[$key] = $topTwoUnique;

                            if (!in_array($key, array('uniqueEmailSends', 'wizUnsubRate'))) {
                                $maxValues[$key] = max(array_column($experiments, $key));
                            } else if ($key == 'wizUnsubRate') {
                                // For unsubs, we flip the max so we highlight the lowest
                                $maxValues[$key] = min(array_column($experiments, $key));
                            }
                        }

                        foreach ($experiments as $experiment) {
                            $winnerClass = $experiment['wizWinner'] ? 'winner' : '';
                            ?>
                            <div class="wizcampaign-experiment">

                                <div class="experiment_var_wrapper <?php echo $winnerClass; ?>">
                                    <h4>
                                        <?php echo $experiment['name']; ?>
                                    </h4>
                                    <div class="rollup_summary_wrapper">
                                        <?php
                                        foreach ($metrics as $key => $metric) {
                                            $value = $experiment[$key];
                                            $formattedValue = "";

                                            switch ($metric['type']) {
                                                case 'number':
                                                    $formattedValue = number_format($value);
                                                    break;
                                                case 'percent':
                                                    $formattedValue = number_format($value, 2) . "%";
                                                    break;
                                                case 'currency':
                                                    $formattedValue = "$" . number_format($value, 0);
                                                    break;
                                            }

                                            $epsilon = 0.01; // smallest number above what we'll display as zero
                                            $highlightClass = '';
                                            if (isset($maxValues[$key]) && count($topTwoUniqueValues[$key]) > 1) {
                                                // If both top two unique values are effectively zero, then don't highlight
                                                if ($topTwoUniqueValues[$key][0] < $epsilon && $topTwoUniqueValues[$key][1] < $epsilon) {
                                                    $highlightClass = '';
                                                } else {
                                                    $highlightClass = ($value == $maxValues[$key]) ? 'highlight' : '';
                                                }
                                            }
                                            ?>
                                            <div class="metric-item <?php echo $highlightClass; ?>">
                                                <span class="metric-label">
                                                    <?php echo $metric['label']; ?>
                                                </span>
                                                <span class="metric-value">
                                                    <?php echo $formattedValue; ?>
                                                </span>
                                            </div>
                                        <?php } ?>
                                    </div>

                                    <div class="mark_as_winner">
                                        <button class="wiz-button"
                                            data-actiontype="<?php echo $winnerClass ? 'remove-winner' : 'add-winner'; ?>"
                                            data-experimentid="<?php echo $experiment['experimentId']; ?>"
                                            data-templateid="<?php echo $experiment['templateId']; ?>">
                                            <?php echo $winnerClass ? 'Winner!' : 'Mark as winner'; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php
                        } // End of foreach loop
                        ?>


                    </div>
                    <div class="wizcampaign-experiment-notes"
                        data-experimentid="<?php echo $experiments[0]['experimentId']; ?>">
                        <h3>Experiment Notes</h3>
                        <?php $experimentNotes = stripslashes($experiments[0]['experimentNotes']) ?? ''; ?>
                        <textarea id="experimentNotes"
                            placeholder="Enter some notes about this experiment..."><?php echo $experimentNotes; ?></textarea>
                    </div>
                </div>
            </div>
            <?php
        }
        ?>

        <div class="wizcampaign-template-area">

            <?php
            $displayTemplates = [];
            $templateName = '';
            if ($experiments) {
                $experimentTemplateIds = array_column($experiments, 'templateId');
                foreach ($experimentTemplateIds as $templateId) {
                    $displayTemplates[] = get_idwiz_template($templateId);
                }
                $templateName = 'Variation';
            } else {
                $templateName = '';
                $displayTemplates = array($template);
            }

            if (!empty($displayTemplates)) {

                foreach ($displayTemplates as $currentTemplate) {
                    if (!$currentTemplate) {
                        continue;
                    }


                    foreach ($experiments as $experiment) {
                        if ($experiment['templateId'] == $currentTemplate['templateId']) {
                            $templateName = $experiment['name'];
                            break;
                        }
                    }

                    ?>

                    <div class="wizcampaign-template-html">
                        <div class="wizcampaign-template-top">
                            <?php if (isset($currentTemplate['heatmapFile'])) {

                                $fullFilePath = $currentTemplate['heatmapFile'];

                                // Extract just the file name from the full path
                                $fileName = basename($fullFilePath);

                                // Extract the timestamp from the file name
                                // Assuming the format is "heatmap_[unique_id]_[timestamp].csv"
                                $parts = explode('_', $fileName);
                                $timestamp = rtrim(end($parts), '.csv'); // Remove the file extension and get the timestamp
                    
                                // Convert the timestamp to a human-readable date format
                                $lastUpdated = date("F j, Y, g:ia", $timestamp);

                                // Display the file name as a link and the last updated date
                                echo "Heatmap file: <a href='$fullFilePath'>$fileName</a> | Last updated: $lastUpdated";
                                echo "&nbsp;&nbsp;<span id='removeHeatmap' title='Remove heatmap' data-templateid='{$currentTemplate['templateId']}'><i class='fa-solid fa-circle-xmark'></i></span>";

                            } else { ?>
                                <?php echo do_shortcode('[wordpress_file_upload uploadid="' . $currentTemplate['templateId'] . '" singlebutton="true" fitmode="responsive" resetmode="onsuccess" uploadrole="all" uploadpatterns="*.csv" createpath="false" showtargetfolder="true" duplicatespolicy="maintain both" uniquepattern="datetimestamp" placements="filename+selectbutton+uploadbutton/message" uploadtitle="Upload heatmap" selectbutton="Upload Heatmap" successmessage="Success! Heatmap uploaded to %filepath%" userdatalabel="website|t:honeypot" medialink="true"]'); ?>
                                (<a target="_blank"
                                    href="https://app.iterable.com/analytics/campaignPerformance/heatmap?campaignId=<?php echo $campaign_id; ?>">Get
                                    heatmap from Iterable</a>)
                            <?php } ?>
                            <?php if (isset($currentTemplate['clientTemplateId'])) { ?>
                                <button title="Duplicate Template" class="wiz-button duplicate-template"
                                    data-postid="<?php echo $template['clientTemplateId']; ?>"><i
                                        class="fa-solid fa-copy"></i></button>
                            <?php } ?>
                        </div>
                        <?php

                        $messageMedium = $currentTemplate['messageMedium'];
                        // Fallback in case template didn't get a message medium for some reason
                        if (!isset($currentTemplate['messageMedium'])) {
                            $messageMedium = $campaign['messageMedium'];
                        }
                        if ($messageMedium == 'Email') {
                            $csv_file = $currentTemplate['heatmapFile'];
                            $heatmap_div = generate_idwizcampaign_heatmap_overlay($csv_file);
                            ?>
                            <div class="wizcampaign-template-details">
                                <h3>
                                    <?php echo $templateName; ?>
                                </h3>
                                <ul>
                                    <li>
                                        <strong>
                                            <?php echo $template['subject']; ?>
                                        </strong>
                                    </li>
                                    <li>
                                        <?php echo $template['preheaderText']; ?>
                                    </li>
                                    <li>
                                        <?php echo $template['fromName'] . ' &lt' . $template['fromEmail'] . '&gt'; ?>
                                    </li>
                                </ul>
                            </div>
                            <div class="wizcampaign-template-preview-iframe-container">
                                <!-- Wrap iframe and heatmap in a container -->
                                <iframe srcdoc="<?php echo htmlspecialchars($currentTemplate['html'], ENT_QUOTES, 'UTF-8'); ?>"
                                    frameborder="0" class="templatePreviewIframe"></iframe>
                                <div class="heatmap-container"> <!-- Position heatmap over iframe -->
                                    <?php echo $heatmap_div; ?>
                                </div>
                            </div>
                        <?php } else {
                            ?>
                            <div class="wizcampaign-mobile-template">
                                <img src="<?php echo $template['imageUrl']; ?>" />
                                <?php echo $template['message']; ?>
                            </div>
                        <?php } ?>
                    </div>
                    <?php

                }
            }
            ?>
        </div>

    </div>

    </div>
</article>
<?php get_footer(); ?>