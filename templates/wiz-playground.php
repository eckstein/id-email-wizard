<?php
get_header();

global $wpdb;

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <header class="wizHeader">
        <div class="wizHeaderInnerWrap">
            <div class="wizHeader-left">
                <h1 class="wizEntry-title" itemprop="name">
                    Playground
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
        function generatePixelArtGrid($numberOfPixels) {
            $htmlOutput = '<div class="pixel-art-grid">';
            for ($i = 1; $i <= $numberOfPixels; $i++) {
                $htmlOutput .= '<input type="checkbox" id="pixel' . $i . '" class="pixel-checkbox" /><label for="pixel' . $i . '" class="pixel-label"></label>';
            }
            $htmlOutput .= '</div>';
            return $htmlOutput;
        }

        // Example usage
        $gridSize = 400; // Specify the total number of pixels in the grid
        echo htmlspecialchars(generatePixelArtGrid($gridSize));

        ?>


    </div>
    </div>
</article>

<?php get_footer();