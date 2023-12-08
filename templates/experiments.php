<?php get_header(); ?>


<header class="wizHeader">
    <div class="wizHeaderInnerWrap">
        <div class="wizHeader-left">
            <h1 class="wizEntry-title" itemprop="name">
                Experiments
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
    $allExperiments = get_idwiz_experiments();
    foreach ($allExperiments as $experiment) {
        $groupedTemplates[$experiment['name']][] = $experiment;
    }

    foreach ($groupedTemplates as $experimentName => $experiments) {
        $countTemplates = 0;
        $campaignId = $experiments[0]['campaignId'];
        $wizCampaign = get_idwiz_campaign($campaignId);
        $campaignStartStamp = (int) ($wizCampaign['startAt'] / 1000);
        echo date('m/d/Y', $campaignStartStamp).'<br/>';
        echo "<strong>{$wizCampaign['name']}</strong><br/>";
        //echo "<strong>{$experimentName}</strong><br/>";
        foreach ($experiments as $experiment) {
            $countTemplates++;
            $wizTemplate = get_idwiz_template($experiment['templateId']);
            echo 'Variation:' . $countTemplates . ': ' . $wizTemplate['name']. '<br/>';
        }
    }
    ?>
</div>
<?php get_footer(); ?>