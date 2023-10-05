<?php
get_header();

global $wpdb;


// Fetch data and group by cohort
$raw_purchase_data = fetch_cohort_purchase_data();
$cohorts = group_by_cohort($raw_purchase_data);

// Calculate the lop_data based on $cohorts
$lop_data = calculate_lop_data($cohorts); // You will need to define or update this function


// Use utility functions to prepare data for tables
$average_time_data = calculate_average_time_to_next_purchase($lop_data);
$interval_distribution_data = calculate_time_interval_distribution($lop_data);

// Table Headers
$headers_for_average_time = [
    'LOP' => '50%',
    'Average Time to Next Purchase (Days)' => '50%'
];

$headers_for_interval_distribution = [
    'LOB' => '33%',
    'Time Interval (Days)' => '33%',
    'Frequency' => '34%'
];

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <header class="wizHeader">
        <div class="wizHeader-left">
            <h1 class="wizEntry-title" itemprop="name">
                Playground
            </h1>
        </div>
        <div class="wizHeader-right">
            <div class="wizHeader-actions">
                <form id="cohort-selection-form" method="GET">
                    <label for="cohort-day">Select Cohort Day-of-Year:</label>
                    <input type="date" id="day-of-year" name="day-of-year" value="<?php echo isset($_GET['day-of-year']) ? esc_attr($_GET['day-of-year']) : ''; ?>" />

                    <fieldset>
                        <legend>Select Divisions:</legend>
                        <?php
                        $query = "SELECT DISTINCT cohort_value FROM {$wpdb->prefix}idemailwiz_cohorts WHERE cohort_type = 'division'";
                        $result = $wpdb->get_results($query);

                        // Get divisions from GET parameters
                        $selectedDivisions = isset($_GET['divisions']) ? (array) $_GET['divisions'] : [];

                        // Generate checkboxes
                        foreach ($result as $row) {
                            $divisionName = $row->cohort_value;
                            $isChecked = in_array($divisionName, $selectedDivisions) ? 'checked' : '';
                            echo '<label><input type="checkbox" name="divisions[]" value="' . esc_attr($divisionName) . '" ' . $isChecked . '> ' . esc_html($divisionName) . '</label><br>';
                        }
                        ?>
                    </fieldset>

                    <input type="submit" value="Generate Chart">
                </form>

            </div>
        </div>
    </header>
    <div class="entry-content" itemprop="mainContentOfPage">
        <div class="wizcampaign-sections-row grid">
            <div class="wizcampaign-section inset" id="averageTimeByLOB">
                <div class="wizcampaign-section-title-area">
                    <h4>Title here</h4>
                    <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">
                        
                    </div>
                </div>
                <div class="wizcampaign-section-content">
                    <canvas id="cohortChart" data-day-of-year="" data-divisions=""></canvas>
                </div>
            </div>

        </div>


    </div>
    </div>
</article>

<?php get_footer();