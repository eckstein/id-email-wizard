<?php get_header(); ?>


<header class="wizHeader">
    <h1 class="wizEntry-title" itemprop="name">
        Promo Codes
    </h1>
    <div class="wizHeaderInnerWrap">
        <div class="wizHeader-left">


        </div>
        <div class="wizHeader-right">
            <div class="wizHeader-actions">

                <button class="wiz-button green new-promo-code"><i class="fa-regular fa-plus"></i>&nbsp;New
                    Promo Code</button>
            </div>
        </div>
    </div>
</header>
<div class="entry-content" itemprop="mainContentOfPage">
    <?php if (have_posts()) { ?>
        <table class="idemailwiz_table display" id="idemailwiz_promo_codes_table" style="width: 100%; vertical-align: middle" valign="middle" width="100%">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>iDTC Discount</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Last Used</th>
                    <th>Cohort</th>
                    <th>Campaigns</th>
                    <th>Camp. Purchases</th>
                    <th>All Purchases</th>
                    <th>Camp. Revenue</th>
                    <th>All Revenue</th>
                    <th>Delete</th>
                </tr>
            </thead>
            <!-- Rest of table generated in data tables js code -->
        </table>
    <?php } else {
        echo 'No promo codes found!';
    } ?>
</div>
<?php get_footer(); ?>