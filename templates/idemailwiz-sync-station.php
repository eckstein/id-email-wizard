<?php
get_header();

global $wpdb;

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <header class="wizHeader">
        <div class="wizHeaderInnerWrap">
            <div class="wizHeader-left">
                <h1 class="wizEntry-title" itemprop="name">Sync Station</h1>
            </div>
            <div class="wizHeader-right">
                <!-- Additional header actions if needed -->
            </div>
        </div>
    </header>

    <div class="entry-content" itemprop="mainContentOfPage">
        <div class="wizcampaign-sections-row">
            <!-- Sync Form -->
            <div class="wizcampaign-section inset">
                <h2>Manual Sync Controls</h2>
                <form id="syncStationForm" method="post">
                    <fieldset class="syncTypes blast">
                        <legend>Sync Blast Metrics:</legend>
                        <?php echo "<label><input type='checkbox' name='syncTypes[]' value='blast'>Blast Metrics</label>"; ?>
                    </fieldset>
                    <fieldset class="syncTypes triggered">
                        <legend>Sync Triggered Metrics:</legend>
                        <?php
                        $syncTypes = ['Sends' => 'send', 'Opens' => 'open', 'Clicks' => 'click', 'Unsubscribes' => 'unSubscribe', 'Bounces' => 'bounce', 'Complaints' => 'complaint', 'SendSkips' => 'sendSkip'];
                        foreach ($syncTypes as $label => $type) {
                            echo "<label><input type='checkbox' name='syncTypes[]' value='$type'> $label</label>";
                        }
                        ?>

                    </fieldset>
                    <fieldset id="syncStation-syncDates">
                        <legend>Limit by start and/or end date(s) <br /><em>leave blank for all time</em></legend>
                        <div class="wizSyncForm-startDate-group">
                            <label for="startDate">Start Date: </label>
                            <input type="date" id="startDate" name="startDate">
                        </div>
                        <div class="wizSyncForm-startDate-group">
                            <label for="endDate">End Date: </label>
                            <input type="date" id="endDate" name="endDate">
                        </div>

                    </fieldset>

                    <fieldset id="syncStation-syncCampaigns">
                        <legend>Sync specific campaigns <br /><em>optional and only applicable to blast campaigns</em></legend>
                        <label for="campaignIds">Campaign IDs (comma-separated):</label><br />
                        <textarea id="campaignIds" name="campaignIds"></textarea>
                    </fieldset>

                    <input type="submit" class="wiz-button green" value="Initiate Sync">
                    <?php
                    // Check if a sync is already in progress
                    $overlayClass = '';
                    if (get_transient('idemailwiz_sync_in_progress')) {
                        $overlayClass = 'active';
                    }
                    ?>
                    <div class="syncForm-overlay <?php echo $overlayClass; ?>">
                        <div class="syncForm-overlayContent">Sync in progress...</div>
                    </div>
                </form>
            </div>
            <!-- Sync Log Section -->
            <div class="wizcampaign-section inset" id="sync-log-panel">
                <h2>Sync Log</h2>
                <pre id="syncLogContent"><code><?php echo file_get_contents(plugin_dir_path(__FILE__) . '../wiz-log.log'); ?></code></pre>
            </div>
        </div>




    </div>
</article>

<?php get_footer(); ?>