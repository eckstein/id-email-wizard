<?php
// Get cohorts from URL
$selectedCohorts = isset($_GET['cohorts']) ? explode(',', $_GET['cohorts']) : ['all'];
$excludedCohorts = isset($_GET['exclude_cohorts']) ? explode(',', $_GET['exclude_cohorts']) : [];

// Get campaign type and message medium filters
$selectedCampaignType = isset($_GET['campaignType']) ? $_GET['campaignType'] : 'all';
$selectedMessageMedium = isset($_GET['messageMedium']) ? $_GET['messageMedium'] : 'all';

// Build campaign filter arguments
$campaignFilters = [
    'startAt_start' => $startDate, 
    'startAt_end' => $endDate, 
    'sortBy' => 'startAt', 
    'sort' => 'ASC'
];

// Add campaign type filter if not 'all'
if ($selectedCampaignType !== 'all') {
    $campaignFilters['type'] = $selectedCampaignType;
}

// Add message medium filter if not 'all'
if ($selectedMessageMedium !== 'all') {
    $campaignFilters['messageMedium'] = $selectedMessageMedium;
}
// If 'all' is selected, don't add a messageMedium filter to show all mediums

// Get campaigns for the current year range
$campaignsInRange = get_idwiz_campaigns($campaignFilters);

// Get campaigns for the previous year range
$lastYearStart = date('Y-m-d', strtotime('-1 year', strtotime($startDate)));
$lastYearEnd = date('Y-m-d', strtotime('-1 year', strtotime($endDate)));

$lastYearFilters = $campaignFilters;
$lastYearFilters['startAt_start'] = $lastYearStart;
$lastYearFilters['startAt_end'] = $lastYearEnd;

$lastYearCampaigns = get_idwiz_campaigns($lastYearFilters);

$engagementModules = [
    'opensReport' => [
        'title' => 'Open Rate per Campaign (YoY)',
        'description' => 'Year-over-year comparison of campaign open rates',
        'defaultMinFilter' => 0,
        'defaultMaxFilter' => 100,
        'defaultMinScale' => 0,
        'defaultMaxScale' => 100,
        'unit' => '%',
        'step' => 0.1
    ],
    'ctrReport' => [
        'title' => 'Click Rate per Campaign (YoY)',
        'description' => 'Year-over-year comparison of campaign click rates',
        'defaultMinFilter' => 0,
        'defaultMaxFilter' => 30,
        'defaultMinScale' => 0,
        'defaultMaxScale' => 30,
        'unit' => '%',
        'step' => 0.1
    ],
    'ctoReport' => [
        'title' => 'Click-to-Open Rate (YoY)',
        'description' => 'Year-over-year comparison of click-to-open rates',
        'defaultMinFilter' => 0,
        'defaultMaxFilter' => 100,
        'defaultMinScale' => 0,
        'defaultMaxScale' => 100,
        'unit' => '%',
        'step' => 0.1
    ],
    'unsubReport' => [
        'title' => 'Unsubscribe Rate (YoY)',
        'description' => 'Year-over-year comparison of unsubscribe rates',
        'defaultMinFilter' => 0,
        'defaultMaxFilter' => 5,
        'defaultMinScale' => 0,
        'defaultMaxScale' => 5,
        'unit' => '%',
        'step' => 0.01
    ],
    'revReport' => [
        'title' => 'Revenue per Campaign (YoY)',
        'description' => 'Year-over-year comparison of campaign revenue',
        'defaultMinFilter' => 0,
        'defaultMaxFilter' => 100000,
        'defaultMinScale' => 0,
        'defaultMaxScale' => 100000,
        'unit' => '$',
        'step' => 100
    ]
];

foreach ($engagementModules as $moduleId => $moduleConfig) {
    // Get current values from URL parameters
    $currentMinFilter = $_GET[$moduleId . '_minFilter'] ?? $moduleConfig['defaultMinFilter'];
    $currentMaxFilter = $_GET[$moduleId . '_maxFilter'] ?? $moduleConfig['defaultMaxFilter'];
    $currentMinScale = $_GET[$moduleId . '_minScale'] ?? $moduleConfig['defaultMinScale'];
    $currentMaxScale = $_GET[$moduleId . '_maxScale'] ?? $moduleConfig['defaultMaxScale'];
    $currentChartMode = $_GET[$moduleId . '_chartMode'] ?? 'standard';
    
    // For backward compatibility with old parameter names
    if ($moduleId == 'opensReport') {
        $currentMinFilter = $_GET['minOpenRate'] ?? $currentMinFilter;
        $currentMaxFilter = $_GET['maxOpenRate'] ?? $currentMaxFilter;
    } elseif ($moduleId == 'ctrReport') {
        $currentMinFilter = $_GET['minClickRate'] ?? $currentMinFilter;
        $currentMaxFilter = $_GET['maxClickRate'] ?? $currentMaxFilter;
    } elseif ($moduleId == 'ctoReport') {
        $currentMinFilter = $_GET['minCtoRate'] ?? $currentMinFilter;
        $currentMaxFilter = $_GET['maxCtoRate'] ?? $currentMaxFilter;
    }
?>
    <div class="engagement-module" id="<?php echo $moduleId; ?>-module">
        <div class="engagement-module-header">
            <div class="engagement-module-title-section">
                <h2><?php echo $moduleConfig['title']; ?></h2>
                <p class="engagement-module-description"><?php echo $moduleConfig['description']; ?></p>
            </div>
            
            <div class="engagement-module-actions">
                <button type="button" 
                        class="toggle-module-controls wiz-button" 
                        data-module="<?php echo $moduleId; ?>">
                    Show Controls
                </button>
            </div>
        </div>
        
        <!-- Module Controls -->
        <div class="engagement-module-controls" style="display: none;">
                    <div class="engagement-controls-layout">
                        <?php if ($moduleId === 'revReport') { ?>
                        <div class="engagement-control-group">
                            <h4>Chart Mode</h4>
                            <div class="engagement-control-row">
                                <div class="engagement-field-group">
                                    <label>
                                        <input type="radio" name="<?php echo $moduleId; ?>_chartMode" value="standard" 
                                               <?php echo (!isset($_GET[$moduleId . '_chartMode']) || $_GET[$moduleId . '_chartMode'] === 'standard') ? 'checked' : ''; ?>>
                                        Standard (Individual Campaign Revenue)
                                    </label>
                                </div>
                                <div class="engagement-field-group">
                                    <label>
                                        <input type="radio" name="<?php echo $moduleId; ?>_chartMode" value="cumulative"
                                               <?php echo (isset($_GET[$moduleId . '_chartMode']) && $_GET[$moduleId . '_chartMode'] === 'cumulative') ? 'checked' : ''; ?>>
                                        Cumulative Race (Running Total YoY)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                        
                        <div class="engagement-control-group">
                            <h4>Filter Settings (campaigns to include)</h4>
                            <div class="engagement-control-row">
                                <div class="engagement-field-group">
                                    <label>Min <?php echo $moduleConfig['unit']; ?></label>
                                    <input type="number" 
                                           class="module-filter-min" 
                                           data-module="<?php echo $moduleId; ?>"
                                           data-type="filter"
                                           value="<?php echo $currentMinFilter; ?>" 
                                           min="0" 
                                           step="<?php echo $moduleConfig['step']; ?>" />
                                </div>
                                <div class="engagement-field-group">
                                    <label>Max <?php echo $moduleConfig['unit']; ?></label>
                                    <input type="number" 
                                           class="module-filter-max" 
                                           data-module="<?php echo $moduleId; ?>"
                                           data-type="filter"
                                           value="<?php echo $currentMaxFilter; ?>" 
                                           min="0" 
                                           step="<?php echo $moduleConfig['step']; ?>" />
                                </div>
                            </div>
                        </div>
                        
                        <div class="engagement-control-group">
                            <h4>Scale Settings (chart display range)</h4>
                            <div class="engagement-control-row">
                                <div class="engagement-field-group">
                                    <label>Min <?php echo $moduleConfig['unit']; ?></label>
                                    <input type="number" 
                                           class="module-scale-min" 
                                           data-module="<?php echo $moduleId; ?>"
                                           data-type="scale"
                                           value="<?php echo $currentMinScale; ?>" 
                                           min="0" 
                                           step="<?php echo $moduleConfig['step']; ?>" />
                                </div>
                                <div class="engagement-field-group">
                                    <label>Max <?php echo $moduleConfig['unit']; ?></label>
                                    <input type="number" 
                                           class="module-scale-max" 
                                           data-module="<?php echo $moduleId; ?>"
                                           data-type="scale"
                                           value="<?php echo $currentMaxScale; ?>" 
                                           min="0" 
                                           step="<?php echo $moduleConfig['step']; ?>" />
                                </div>
                            </div>
                        </div>
                        
                        <div class="engagement-control-actions">
                            <button type="button" 
                                    class="update-module-btn wiz-button green" 
                                    data-module="<?php echo $moduleId; ?>">
                                Update Chart
                            </button>
                        </div>
                    </div>
                </div>
            
        <div class="wizChartWrapper">
            <canvas class="<?php echo $moduleId; ?> wiz-canvas engagement-chart-canvas" 
                    data-chartid="<?php echo $moduleId; ?>" 
                    data-campaignids='<?php echo json_encode(array_column($campaignsInRange, 'id')); ?>' 
                    data-lastYearCampaignIds='<?php echo json_encode(array_column($lastYearCampaigns, 'id')); ?>' 
                    data-charttype="line" 
                    data-campaigntype="<?php echo strtolower($selectedCampaignType); ?>" 
                    data-messagemedium="<?php echo $selectedMessageMedium; ?>"
                    data-startdate="<?php echo $startDate; ?>" 
                    data-enddate="<?php echo $endDate; ?>" 
                    data-cohorts='<?php echo json_encode($selectedCohorts); ?>' 
                    data-cohorts-exclude='<?php echo json_encode($excludedCohorts); ?>' 
                    data-minsends="<?php echo isset($_GET['minSendSize']) ? $_GET['minSendSize'] : 1; ?>" 
                    data-maxsends="<?php echo isset($_GET['maxSendSize']) ? $_GET['maxSendSize'] : 500000; ?>" 
                    data-minfilter="<?php echo $currentMinFilter; ?>"
                    data-maxfilter="<?php echo $currentMaxFilter; ?>"
                    data-minscale="<?php echo $currentMinScale; ?>"
                    data-maxscale="<?php echo $currentMaxScale; ?>"
                    data-minmetric="<?php echo $currentMinFilter; ?>" 
                    data-maxmetric="<?php echo $currentMaxFilter; ?>"
                    data-chartmode="<?php echo $currentChartMode; ?>">
            </canvas>
        </div>
    </div>
<?php
}
?>
