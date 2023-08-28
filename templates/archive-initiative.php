<?php 
get_header();
?>
<h1>Active Initiatives</h1>
<div id="initiatives-grid">
    <div class="initiatives-grid-row">
    <?php if ( have_posts() ) { 
    while ( have_posts() ) { the_post(); ?>
    <div class="initiative-card">
        <div class="initiative-card-content">
            <?php the_post_thumbnail('medium'); ?>
        </div>
        <div class="initiative-card-title">
            <?php the_title(); ?>
        </div>
    </div>
    <?php
    }
    
    } else {
        // No initiatives found
    } ?>
    </div>
</div>

<?php get_footer(); ?>
