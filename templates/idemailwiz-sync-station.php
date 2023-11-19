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
                <form id="syncStationForm" method="post" class="wizSyncForm">
                    <fieldset id="syncTypes">
                        <legend>Sync Types:</legend>
                        <?php
                        $syncTypes = ['Blast Metrics', 'Sends', 'Opens', 'Clicks', 'Unsubscribes', 'Bounces', 'Complaints', 'SendSkips'];
                        foreach ($syncTypes as $type) {
                            echo "<label><input type='checkbox' name='syncTypes[]' value='$type'> $type</label>";
                        }
                        ?>
                    </fieldset>
                    <fieldset id="syncStation-syncDates">
                        <div class="wizSyncForm-startDate-group">
                            <label for="startDate">Start Date:</label>
                            <input type="date" id="startDate" name="startDate">
                        </div>
                        <div class="wizSyncForm-startDate-group">
                            <label for="endDate">End Date:</label>
                            <input type="date" id="endDate" name="endDate">
                        </div>
                    </fieldset>
                    <fieldset id="syncStation-syncCampaigns">
                        <label for="campaignIds">Campaign IDs (comma-separated):</label><br />
                        <textarea id="campaignIds" name="campaignIds"></textarea>
                    </fieldset>

                    <input type="submit" class="wiz-button green" value="Initiate Sync">
                </form>
            </div>
        </div>

        <div class="wizcampaign-sections-row" id="wiz_log_panels">
            <!-- Sync Log Section -->
            <div class="wizcampaign-section inset">
                <h2>Sync Log</h2>
                <pre id="syncLogContent"><code>
                    <?php echo file_get_contents(plugin_dir_path(__FILE__) . '../sync-log.txt'); ?>
                </code></pre>
            </div>

            <!-- Error Log Section -->
            <div class="wizcampaign-section inset">
                <h2>Error Log</h2>
                <pre id="errorLogContent"><code>
                    <?php echo file_get_contents(WP_CONTENT_DIR . '/debug.log'); ?>
                </code></pre>
            </div>
        </div>


    </div>
</article>

<?php get_footer(); ?>