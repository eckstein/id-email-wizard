<?php
/**
 * Template Name: Metrics Campaign Template
 */
?>
<?php get_header(); ?>
<?php
global $wpdb; // Declare the WordPress Database variable

date_default_timezone_set('America/Los_Angeles');
//print_r(idemailwiz_sync_templates(array(7652831)));

$campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$campaign = get_idwiz_campaign($campaign_id);
$metrics = get_idwiz_metric($campaign['id']);
$template = get_idwiz_template($campaign['templateId']);

$startAt = date('m/d/Y \a\t g:ia', $campaign['startAt'] / 1000);

$purchases = get_idwiz_purchases(array('campaignId' => $campaign['id']));
//$experimentIds = maybe_unserialize($campaign['experimentIds']) ?? array();
// This returns one row per experiment TEMPLATE
$experiments = get_idwiz_experiments(array('campaignId' => $campaign['id']));
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
        <div class="wizHeader-left">
            <h1 class="wizEntry-title single-wizcampaign-title" itemprop="name">
                <?php echo $campaign['name']; ?>
            </h1>
            <div class="wizEntry-meta">
                <strong>Campaign <a
                        href="https://app.iterable.com/campaigns/<?php echo $campaign['id']; ?>?view=summary">
                        <?php echo $campaign['id']; ?>
                    </a>
                    <?php if ($experimentIds) {
                        echo '&nbsp;&nbsp;&#x2022;&nbsp;&nbsp; with Experiment ' . implode(', ', $linkedExperimentIds) . '</a>';
                    } ?>
                </strong>
                &nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
                <?php echo $campaign['messageMedium']; ?>
                &nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
                <?php echo '<a href="' . get_bloginfo('url') . '/campaigns/?view=' . $campaign['type'] . '">' . $campaign['type'] . '</a>'; ?>
                &nbsp;&nbsp;&#x2022;&nbsp;&nbsp;Sent on
                <?php echo $startAt; ?>
                &nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
                <?php echo 'Wiz Template: <a href="' . get_bloginfo('url') . '?p=' . $template['clientTemplateId'] . '">' . $template['clientTemplateId'] . '</a>'; ?>
            </div>
            <?php echo generate_initiative_flags($campaign['id']); ?>
        </div>
        <div class="wizHeader-right">
            <div class="wizHeader-actions">
                <button class="wiz-button green sync-campaign" data-campaignid="<?php echo $campaign['id']; ?>">Sync
                    Campaign</button>
                <button class="wiz-button green">View Template</button>
            </div>
        </div>
    </header>
    <div id="wiztable_status_updates"><span class="wiztable_update"></span><span class="wiztable_view_sync_details">View
            sync log&nbsp;<i class="fa-solid fa-chevron-down"></i></span></div>
    <div id="wiztable_status_sync_details">Sync log will show here...</div>

    <div class="entry-content" itemprop="mainContentOfPage">
        <?php echo get_single_metrics_campaign_rollup($campaign); ?>
        <?php
        if ($campaign['type'] == 'Triggered') {
            ?>
            <div class="wizmodules">
                <?php
                $triggeredSends = get_triggered_sends_by_campaign_id($campaign['id']);
                if (!empty($triggeredSends)) {
                    ?>
                    <div class="wizcampaign-section short inset span3" id="sendsByDateSection">
                        <h4>Sends by Date</h4>
                        <div class="wizChartWrapper">
                        <canvas class="sendsByDate wiz-canvas" data-chartid="sendsByDate"
                            data-campaignids='<?php echo json_encode(array($campaign['id'])); ?>' data-charttype="bar"></canvas>
                            </div>
                    </div>

                    <?php
                }
                ?>
            </div>
            <?php
        }

        ?>
        <div class="wizcampaign-sections-row" id="single-campaign-sections">

            <div class="wizcampaign-section short inset" id="email-info">
                <h4>Purchases by Date</h4>
                <div class="wizChartWrapper">
                    <canvas class="purchByDate wiz-canvas" data-chartid="purchasesByDate"
                        data-campaignids='<?php echo json_encode(array($campaign['id'])); ?>'
                        data-charttype="bar"></canvas>
                </div>
            </div>

            <div class="wizcampaign-section short inset">
                <div class="wizcampaign-section-title-area">
                    <h4>Purchases by Division</h4>
                    <div class="wizcampaign-section-icons">
                        <i class="fa-solid fa-chart-simple active chart-type-switcher" data-chart-type="bar"></i><i
                            class="fa-solid fa-chart-pie chart-type-switcher" data-chart-type="pie"></i>
                    </div>
                </div>
                <div class="wizChartWrapper">
                    <canvas class="purchByDivision wiz-canvas" data-chartid="purchasesByDivision"
                        data-campaignids='<?php echo json_encode(array($campaign['id'])); ?>'
                        data-charttype="bar"></canvas>
                </div>
            </div>

            <div class="wizcampaign-section short inset">
                <div class="wizcampaign-section-title-area">
                    <h4>Purchases by Product</h4>
                    <div class="wizcampaign-section-icons">
                        
                    </div>
                </div>
                
                <?php
                $headers = [
                    'Product' => '50%',
                    'Purchases' => '25%',
                    'Revenue' => '25%'
                ];

                $data = [];
                $products = array();
                $productRevenue = array();
                foreach ($purchases as $purchase) {
                    $product = $purchase['shoppingCartItems_name'];
                    if (!isset($products[$product])) {
                        $products[$product] = 0;
                        $productRevenue[$product] = 0;
                    }
                    $products[$product]++;
                    $productRevenue[$product] += $purchase['shoppingCartItems_price'];
                }

                // Sort products by the number of purchases in descending order
                arsort($products);

                // Prepare the data for the table
                foreach ($products as $productName => $purchaseCount) {
                    $data[] = [
                        'Product' => $productName,
                        'Purchases' => $purchaseCount,
                        'Revenue' => '$' . number_format($productRevenue[$productName], 2)
                    ];
                }

                generate_mini_table($headers, $data);
                ?>
            </div>

            <?php generate_promo_code_section($purchases); ?>

            <div class="wizcampaign-section short inset">
                <div class="wizcampaign-section-title-area">
                    <h4>Purchases by Topic</h4>
                    <div class="wizcampaign-section-icons">
                        <i class="fa-solid fa-chart-simple active chart-type-switcher" data-chart-type="bar"></i><i
                            class="fa-solid fa-chart-pie chart-type-switcher" data-chart-type="pie"></i>
                    </div>
                </div>
                <div class="wizChartWrapper">
                    <canvas class="purchByTopic wiz-canvas" data-chartid="purchasesByTopic"
                        data-campaignids='<?php echo json_encode(array($campaign['id'])); ?>'
                        data-charttype="pie"></canvas>
                </div>
            </div>

            <div class="wizcampaign-section short inset">
                <div class="wizcampaign-section-title-area">
                    <h4>Purchases by Campus</h4>
                    <div class="wizcampaign-section-icons">
                        <i class="fa-solid fa-chart-simple active chart-type-switcher" data-chart-type="bar"></i><i
                            class="fa-solid fa-chart-pie chart-type-switcher" data-chart-type="pie"></i>
                    </div>
                </div>
                <div class="wizChartWrapper">
                    <canvas class="purchByLocation wiz-canvas" data-chartid="purchasesByLocation"
                        data-campaignids='<?php echo json_encode(array($campaign['id'])); ?>'
                        data-charttype="pie"></canvas>
                </div>
            </div>


        </div>
        <?php
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
                                    <div class="wiztable_view_metrics_div">
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
                        <?php $experimentNotes = $experiments[0]['experimentNotes'] ?? ''; ?>
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
                        <?php

                        $messageMedium = $currentTemplate['messageMedium'];
                        // Fallback in case template didn't get a message medium for some reason
                        if (!isset($currentTemplate['messageMedium'])) {
                            $messageMedium = $campaign['messageMedium'];
                        }
                        if ($messageMedium == 'Email') {
                            $csv_file = 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/heatmap.csv';
                            //$heatmap_div = generate_idwizcampaign_heatmap_overlay($csv_file);
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
                                    <?php //echo $heatmap_div; ?>
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


<?php
// Helper Functions
function get_single_metrics_campaign_rollup($campaign)
{
    return generate_idwiz_rollup_row(
        array($campaign['id']),
        array(
            'uniqueEmailSends' => array(
                'label' => 'Sends',
                'format' => 'num',
            ),
            'uniqueEmailOpens' => array(
                'label' => 'Opens',
                'format' => 'num',
            ),
            'wizOpenRate' => array(
                'label' => 'Open Rate',
                'format' => 'perc',
            ),
            'uniqueEmailClicks' => array(
                'label' => 'Clicks',
                'format' => 'num',
            ),
            'wizCtr' => array(
                'label' => 'CTR',
                'format' => 'perc',
            ),
            'wizCto' => array(
                'label' => 'CTO',
                'format' => 'perc',
            ),
            'uniquePurchases' => array(
                'label' => 'Purchases',
                'format' => 'num',
            ),
            'revenue' => array(
                'label' => 'Dir. Rev.',
                'format' => 'money',
            ),
            'gaRevenue' => array(
                'label' => 'GA Rev.',
                'format' => 'money',
            ),

        ),

    );
}

function get_display_templates($experiments, $template)
{
    $displayTemplates = [];
    if ($experiments) {
        foreach ($experiments as $experiment) {
            $displayTemplates[] = get_idwiz_template($experiment['templateId']);
        }
    } else {
        $displayTemplates = array($template);
    }
    return $displayTemplates;
}


function generate_initiative_flags($campaignId)
{
    $initIds = idemailwiz_get_initiative_ids_for_campaign($campaignId);
    ob_start(); // Start output buffering
    ?>
    <div class="campaign-init-flags">
        <?php if ($initIds) { ?>
            <?php foreach ($initIds as $initId): ?>
                <span class="campaign-init-flag">
                    <a href="<?php echo get_the_permalink($initId); ?>" title="Go to Initiative">
                        <?php echo get_the_title($initId); ?>
                    </a>
                    <span class="remove-initiative-icon fa fa-times" data-action="remove" data-initid="<?php echo $initId; ?>"
                        data-campaignid="<?php echo $campaignId; ?>" title="Remove campaign from Initiative">
                    </span>
                </span>
            <?php endforeach; ?>
        <?php } else {
            echo '<em>No connected initiatives</em>';
        } ?>
        <span class="add-initiative-icon fa fa-plus" data-action="add" data-initids='<?php echo json_encode($initIds); ?>'
            data-campaignid="<?php echo $campaignId; ?>" class="add-edit-campaign-initiative">
        </span>
    </div>
    <?php
    return ob_get_clean(); // End output buffering and return the captured HTML
}