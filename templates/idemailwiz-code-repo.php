<?php
echo '<div id="codeRepository">';
if (have_rows('repo_block', 'options')) {
    while (have_rows('repo_block', 'options')) {
        the_row();
        ?>
        <div class="two-col-wrap">
            <div class="left">
                <h2 class="repo-block-title"><?php echo get_sub_field('block_title'); ?></h2>
                <?php echo get_sub_field('block_info'); ?>
            </div>
            <div class="right">
                <?php
                ob_start();
                //get_template_part('template-parts/chunks/' . get_sub_field('block_slug'));
                include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/chunks/' . get_sub_field('block_slug') . '.php';
                $plainTextHTML = ob_get_clean();
                ?>
                <pre style="white-space: pre; " tabsize="1" wrap="soft">
                    <code class="language-html">
                        <?php echo htmlspecialchars($plainTextHTML); ?>
                    </code>
                </pre>
            </div>
        </div>
        <?php
    }
} else {
    // Handle the case when there are no rows.
    echo 'No code repository blocks found.';
}
echo '</div>';