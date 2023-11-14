<?php get_header(); ?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <header class="wizHeader">
        <div class="wizHeaderInnerWrap">
            <div class="wizHeader-left">
                <h1 class="wizEntry-title" itemprop="name">
                    Journeys & Automations
                </h1>

                

            </div>
            <div class="wizHeader-right">
                <div class="wizHeader-actions">

                </div>
            </div>
        </div>
    </header>

    <div class="entry-content" itemprop="mainContentOfPage">
        <?php
        $getJourneys = get_posts( ['post_type' => 'journey', 'posts_per_page' => -1]);
        foreach ($getJourneys as $journey) {
            $workflowId = get_field('workflow_id', $journey->ID);
            $journeyCampaigns = get_idwiz_campaigns(['workflowId' => $workflowId, 'sortBy' => 'startAt', 'sort'=>'ASC']);
            echo '<h3>'.$journey->post_title.'</h3>';
            foreach ($journeyCampaigns as $campaign) {
                $lastSendDate = date('m/d/Y', $campaign['startAt']/1000);
                echo $lastSendDate.' - '.$campaign['name'].'<br/>';
            }
        }
        
        ?>
    </div>




</article>
<?php get_footer(); ?>