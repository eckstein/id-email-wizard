<?php
// Include CSS and JS for this report
echo '<link rel="stylesheet" type="text/css" href="' . plugin_dir_url(__FILE__) . '../../../styles/marketing-monthly-recaps.css">';
echo '<script src="' . plugin_dir_url(__FILE__) . '../../../js/marketing-monthly-recaps.js"></script>';

// Use existing date range from the standard reports filter
$reportStartDate = $startDate; // This comes from the main reports.php file
$reportEndDate = $endDate; // This comes from the main reports.php file

$selectedCampaigns = isset($_GET['campaigns']) ? explode(',', $_GET['campaigns']) : [];

// Get all triggered campaigns (regardless of activity in date range)
$triggeredCampaignsArgs = [
    'type' => 'Triggered',
    'campaignState' => 'Running',
    'fields' => ['id', 'name', 'type', 'campaignState']
];

$allTriggeredCampaigns = get_idwiz_campaigns($triggeredCampaignsArgs);
?>

<div class="marketing-monthly-recaps-report">
    <h2>Marketing Monthly Recaps (Triggered Campaigns)</h2>
    
    <!-- Campaign Selection Form -->
    <div class="recap-controls">
        <form id="recap-filter-form" method="GET">
            <input type="hidden" name="reportType" value="marketing-monthly-recaps">
            <input type="hidden" name="startDate" value="<?php echo esc_attr($reportStartDate); ?>">
            <input type="hidden" name="endDate" value="<?php echo esc_attr($reportEndDate); ?>">
            
            <div class="control-group">
                <p><strong>Date Range:</strong> <?php echo date('M j, Y', strtotime($reportStartDate)); ?> - <?php echo date('M j, Y', strtotime($reportEndDate)); ?></p>
                <p><em>Use the date filter above to change the reporting period.</em></p>
            </div>
            
            <div class="control-group campaign-selection-wrapper">
                <label>Select Triggered Campaigns:</label>
                
                <!-- Search and Add Interface -->
                <div class="campaign-search-section">
                    <div class="search-box">
                        <input type="text" id="campaign-search" placeholder="Search campaigns..." autocomplete="off">
                        <div class="search-results" id="search-results"></div>
                    </div>
                </div>
                
                <!-- Selected Campaigns Display -->
                <div class="selected-campaigns-section">
                    <h4>Selected Campaigns (<span id="selected-count">0</span>): <small>Drag to reorder</small></h4>
                    <div class="selected-campaigns-list" id="selected-campaigns-list">
                        <p class="no-campaigns-msg">No campaigns selected. Search and click campaigns above to add them.</p>
                    </div>
                    <div class="bulk-actions">
                        <button type="button" id="select-all-campaigns" class="wiz-button small">Add All</button>
                        <button type="button" id="clear-campaigns" class="wiz-button small">Clear All</button>
                    </div>
                </div>
                
                <!-- Hidden input to store selected campaign IDs -->
                <input type="hidden" name="campaigns" id="campaigns-input" value="<?php echo implode(',', $selectedCampaigns); ?>">
                
                <small>Search for campaigns by name and click to add/remove them from your selection.</small>
            </div>
            
            <!-- Pass campaign data to JavaScript -->
            <script type="text/javascript">
                window.allTriggeredCampaigns = <?php echo json_encode($allTriggeredCampaigns ?: []); ?>;
                window.selectedCampaignIds = <?php echo json_encode($selectedCampaigns); ?>;
            </script>
            
            <div class="control-group">
                <button type="submit" class="wiz-button green">Generate Recap</button>
            </div>
        </form>
    </div>

    <?php if (!empty($selectedCampaigns)): ?>
    <!-- Report Table -->
    <div class="recap-results">
        <h3>Marketing Recap for <?php echo date('M j, Y', strtotime($reportStartDate)); ?> - <?php echo date('M j, Y', strtotime($reportEndDate)); ?></h3>
        <p>Selected Campaigns: <?php echo count($selectedCampaigns); ?></p>
        
        <table class="idemailwiz_table recap-table">
            <thead>
                <tr>
                    <th>Campaign Name</th>
                    <th>Sent</th>
                    <th>Delivered</th>
                    <th>Opens</th>
                    <th>Open Rate</th>
                    <th>Clicks</th>
                    <th>Click Rate</th>
                    <th>CTO</th>
                    <th>Purchases</th>
                    <th>CVR</th>
                    <th>GA Rev</th>
                    <th>Rev</th>
                    <th>Unsub Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $totalSent = 0;
                $totalDelivered = 0;
                $totalOpens = 0;
                $totalClicks = 0;
                $totalPurchases = 0;
                $totalRevenue = 0;
                $totalGaRevenue = 0;
                $totalUnsubscribes = 0;
                
                foreach ($selectedCampaigns as $campaignId) {
                    // Get campaign details
                    $campaign = get_idwiz_campaign($campaignId);
                    if (!$campaign) continue;
                    
                    // Get metrics for the selected date range
                    $metrics = get_triggered_campaign_metrics([$campaignId], $reportStartDate, $reportEndDate);
                    
                    // If no metrics, create empty array with zeros (allows showing campaigns with no activity)
                    if (!$metrics || empty($metrics)) {
                        $metrics = [
                            'uniqueEmailSends' => 0,
                            'uniqueEmailsDelivered' => 0,
                            'uniqueEmailOpens' => 0,
                            'uniqueEmailClicks' => 0,
                            'uniquePurchases' => 0,
                            'revenue' => 0,
                            'gaRevenue' => 0,
                            'uniqueUnsubscribes' => 0
                        ];
                    }
                    
                    $sent = $metrics['uniqueEmailSends'] ?? 0;
                    $delivered = $metrics['uniqueEmailsDelivered'] ?? 0;
                    $opens = $metrics['uniqueEmailOpens'] ?? 0;
                    $clicks = $metrics['uniqueEmailClicks'] ?? 0;
                    $purchases = $metrics['uniquePurchases'] ?? 0;
                    $revenue = $metrics['revenue'] ?? 0;
                    $gaRevenue = $metrics['gaRevenue'] ?? 0;
                    $unsubscribes = $metrics['uniqueUnsubscribes'] ?? 0;
                    
                    // Calculate rates
                    $openRate = $sent > 0 ? ($opens / $sent) * 100 : 0;
                    $clickRate = $sent > 0 ? ($clicks / $sent) * 100 : 0;
                    $cto = $opens > 0 ? ($clicks / $opens) * 100 : 0;
                    $cvr = $sent > 0 ? ($purchases / $sent) * 100 : 0;
                    $unsubRate = $sent > 0 ? ($unsubscribes / $sent) * 100 : 0;
                    
                    // Add to totals
                    $totalSent += $sent;
                    $totalDelivered += $delivered;
                    $totalOpens += $opens;
                    $totalClicks += $clicks;
                    $totalPurchases += $purchases;
                    $totalRevenue += $revenue;
                    $totalGaRevenue += $gaRevenue;
                    $totalUnsubscribes += $unsubscribes;
                ?>
                <tr>
                    <td><a href="<?php echo get_bloginfo('url'); ?>/campaigns/campaign/?id=<?php echo $campaignId; ?>"><?php echo htmlspecialchars($campaign['name']); ?></a></td>
                    <td><?php echo number_format($sent); ?></td>
                    <td><?php echo number_format($delivered); ?></td>
                    <td><?php echo number_format($opens); ?></td>
                    <td><?php echo number_format($openRate, 2); ?>%</td>
                    <td><?php echo number_format($clicks); ?></td>
                    <td><?php echo number_format($clickRate, 2); ?>%</td>
                    <td><?php echo number_format($cto, 2); ?>%</td>
                    <td><?php echo number_format($purchases); ?></td>
                    <td><?php echo number_format($cvr, 2); ?>%</td>
                    <td>$<?php echo number_format($gaRevenue, 2); ?></td>
                    <td>$<?php echo number_format($revenue, 2); ?></td>
                    <td><?php echo number_format($unsubRate, 2); ?>%</td>
                </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr class="totals-row">
                    <td><strong>TOTALS</strong></td>
                    <td><strong><?php echo number_format($totalSent); ?></strong></td>
                    <td><strong><?php echo number_format($totalDelivered); ?></strong></td>
                    <td><strong><?php echo number_format($totalOpens); ?></strong></td>
                    <td><strong><?php echo $totalSent > 0 ? number_format(($totalOpens / $totalSent) * 100, 2) : '0.00'; ?>%</strong></td>
                    <td><strong><?php echo number_format($totalClicks); ?></strong></td>
                    <td><strong><?php echo $totalSent > 0 ? number_format(($totalClicks / $totalSent) * 100, 2) : '0.00'; ?>%</strong></td>
                    <td><strong><?php echo $totalOpens > 0 ? number_format(($totalClicks / $totalOpens) * 100, 2) : '0.00'; ?>%</strong></td>
                    <td><strong><?php echo number_format($totalPurchases); ?></strong></td>
                    <td><strong><?php echo $totalSent > 0 ? number_format(($totalPurchases / $totalSent) * 100, 2) : '0.00'; ?>%</strong></td>
                    <td><strong>$<?php echo number_format($totalGaRevenue, 2); ?></strong></td>
                    <td><strong>$<?php echo number_format($totalRevenue, 2); ?></strong></td>
                    <td><strong><?php echo $totalSent > 0 ? number_format(($totalUnsubscribes / $totalSent) * 100, 2) : '0.00'; ?>%</strong></td>
                </tr>
            </tfoot>
        </table>
        
        <!-- Export Options -->
        <div class="export-options">
            <button type="button" id="export-csv" class="wiz-button green">Export as CSV</button>
            <button type="button" id="copy-table" class="wiz-button">Copy to Clipboard</button>
        </div>
    </div>
    <?php else: ?>
    <div class="no-campaigns-selected">
        <p>Please select campaigns to generate the recap for the date range: <?php echo date('M j, Y', strtotime($reportStartDate)); ?> - <?php echo date('M j, Y', strtotime($reportEndDate)); ?></p>
        <?php if (empty($allTriggeredCampaigns)): ?>
        <p><em>No triggered campaigns found in the system.</em></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>


