<?php
add_action('wp_ajax_idemailwiz_create_new_interactive', 'idemailwiz_create_new_interactive');
function idemailwiz_create_new_interactive()
{
    // Check for nonce and security
    if (! check_ajax_referer('interactives', 'security', false)) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
        return;
    }

    // Fetch title from POST
    $title = $_POST['title'];

    // Validate that the title is not empty
    if (empty($title)) {
        wp_send_json_error(array('message' => 'The title cannot be empty'));
        return;
    }

    // Create new snippet post
    $post_id = wp_insert_post(
        array(
            'post_title' => $title,
            'post_type' => 'wysiwyg_interactive',
            'post_status' => 'publish',
        )
    );

    if ($post_id > 0) {
        wp_send_json_success(array('message' => 'Interactive created successfully', 'post_id' => $post_id));
    } else {
        wp_send_json_error(array('message' => 'Failed to create the interactive'));
    }
}


add_action('wp_ajax_wiz_save_recommendation_engine', 'wiz_save_recommendation_engine');

function wiz_save_recommendation_engine()
{
    // Verify the nonce for security
    $nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
    if (!wp_verify_nonce($nonce, 'template-editor')) {
        wp_send_json_error('Invalid security token.');
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    if (!$post_id) {
        wp_send_json_error('Invalid post ID.');
    }

    $data = $_POST;
    unset($data['action'], $data['security'], $data['post_id']);

    // Save the raw data
    update_post_meta($post_id, '_recommendation_engine_data', $data);

    // Generate and save the HTML and CSS
    $template = generate_rec_engine_template($data);
    update_post_meta($post_id, '_recommendation_engine_html', $template['html']);
    update_post_meta($post_id, '_recommendation_engine_css', $template['css']);

    wp_send_json_success('Recommendation engine saved successfully.');
}

add_action('wp_ajax_wiz_preview_recommendation_engine', 'wiz_preview_recommendation_engine');


function wiz_preview_recommendation_engine()
{
    // Verify the nonce for security
    $nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
    if (!wp_verify_nonce($nonce, 'template-editor')) {
        wp_send_json_error('Invalid security token.');
    }

    $data = $_POST;
    unset($data['action'], $data['security']);

    if (empty($data)) {
        wp_send_json_error('Invalid data.');
    }

    // Generate the HTML and CSS
    $template = generate_rec_engine_template($data);

    wp_send_json_success(array(
        'html' => $template['html'],
        'css' => $template['css']
    ));
}

add_action('wp_ajax_generate_rec_builder_selection_group', 'generate_rec_builder_selection_group_ajax');
function generate_rec_builder_selection_group_ajax()
{
    // Verify the nonce for security
    $nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
    if (!wp_verify_nonce($nonce, 'template-editor')) {
        wp_send_json_error('Invalid security token.');
    }
    $index = isset($_POST['index']) ? intval($_POST['index']) : 0;
    $selection = isset($_POST['selection']) ? $_POST['selection'] : array();
    $html = generate_rec_builder_selection_group($index, $selection);
    wp_send_json_success(array('html' => $html));
}


function generate_rec_builder_selection_group($index, $selection = [])
{
    ob_start();
?>
    <div class="selection-group builder-field-group wizcampaign-section noFlex">
        <div class="selection-group-header">
            <div class="selection-group-number"><?php echo $index + 1; ?></div>
            <h4>
                <div class="builder-field-wrapper">
                    <input type="text" class="selection-name-input" id="selections[<?php echo $index; ?>][key]" name=" selections[<?php echo $index; ?>][key]" placeholder="Selection Group Title" required value="<?php echo esc_attr($selection['key'] ?? ''); ?>" data-value="<?php echo esc_attr($selection['key'] ?? ''); ?>" />
                </div>
            </h4>
            <div class=" toggle-hotspot"><span class="toggle-message">Click to toggle</span>
            </div>
            <div class="selection-group-actions">
                <button type="button" class="wiz-button small green add-option-btn"><i class="fa-solid fa-plus"></i>&nbsp;&nbsp;Add Option</button>
                <button type="button" class="wiz-button small red remove-selection-btn" title="Remove Selection Group"><i class="fa-solid fa-trash"></i></button>
            </div>
        </div>
        <div class="selection-group-content">
            <div class="options-container builder-field-group flex noWrap">
                <?php
                if (isset($selection['options']) && is_array($selection['options'])) {
                    foreach ($selection['options'] as $optionIndex => $option) {
                        echo generate_rec_builder_selection_option($index, $optionIndex, $option);
                    }
                }
                ?>
            </div>

        </div>
    </div>
<?php
    return ob_get_clean();
}

add_action('wp_ajax_generate_rec_builder_selection_option', 'generate_rec_builder_selection_option_ajax');
function generate_rec_builder_selection_option_ajax()
{
    // Verify the nonce for security
    $nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
    if (!wp_verify_nonce($nonce, 'template-editor')) {
        wp_send_json_error('Invalid security token.');
    }
    $selectionIndex = isset($_POST['selectionIndex']) ? intval($_POST['selectionIndex']) : 0;
    $optionIndex = isset($_POST['optionIndex']) ? intval($_POST['optionIndex']) : 0;
    $option = isset($_POST['option']) ? $_POST['option'] : array();
    $html = generate_rec_builder_selection_option($selectionIndex, $optionIndex, $option);
    wp_send_json_success(array('html' => $html));
}
function generate_rec_builder_selection_option($selectionIndex, $optionIndex, $option = [])
{
    ob_start();
?>
    <div class="option-group builder-field-wrapper flex column">
        <div class="builder-field-wrapper">
            <label for="selections[<?php echo $selectionIndex; ?>][options][<?php echo $optionIndex; ?>][value]">Option <span class="option-index-display"><?php echo $optionIndex + 1; ?></span> Class</label>
            <input type="text" name="selections[<?php echo $selectionIndex; ?>][options][<?php echo $optionIndex; ?>][value]" placeholder="Option Value" required value="<?php echo esc_attr($option['value'] ?? ''); ?>">
        </div>
        <div class="builder-field-wrapper">
            <label for="selections[<?php echo $selectionIndex; ?>][options][<?php echo $optionIndex; ?>][label]">Option <span class="option-index-display"><?php echo $optionIndex + 1; ?></span> Label</label>
            <input type="text" name="selections[<?php echo $selectionIndex; ?>][options][<?php echo $optionIndex; ?>][label]" placeholder="Option Label" required value="<?php echo esc_attr($option['label'] ?? ''); ?>">
            <button type="button" class="wiz-button small red remove-option-btn">Remove Option</button>
        </div>
    </div>
<?php
    return ob_get_clean();
}

add_action('wp_ajax_generate_rec_builder_result_group', 'generate_rec_builder_result_group_ajax');
function generate_rec_builder_result_group_ajax()
{
    // Verify the nonce for security
    $nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
    if (!wp_verify_nonce($nonce, 'template-editor')) {
        wp_send_json_error('Invalid security token.');
    }
    $index = isset($_POST['index']) ? intval($_POST['index']) : 0;
    error_log(print_r($_POST, true));
    $result = isset($_POST['result']) ? $_POST['result'] : array();
    $html = generate_rec_builder_result_group($index, $result);
    wp_send_json_success(array('html' => $html));
}
function generate_rec_builder_result_group($index, $result = [])
{
    ob_start();
?>
    <div class="result-container wizcampaign-section noFlex">
        <div class="result-container-header">
            <div class="result-group-number"><?php echo $index + 1; ?></div>
            <h4>
                <div class="builder-field-wrapper"><input type="text" name="results[<?php echo $index; ?>][title]" placeholder="Result Title" required value="<?php echo esc_attr($result['title'] ?? ''); ?>"></div>
            </h4>
            <div class="toggle-hotspot"><span class="toggle-message">Click to toggle</span></div>
            <div class="result-actions">
                <button type="button" class="wiz-button small red remove-result-btn"><i class="fa-solid fa-trash"></i></button>
            </div>
        </div>
        <div class="result-container-content builder-field-group flex distribute">
            <div class="builder-field-wrapper">
                <label for="results[<?php echo $index; ?>][classes][]">Classes</label>
                <select class="result-classes" name="results[<?php echo $index; ?>][classes][]" multiple="multiple">
                    <?php
                    if (isset($result['classes']) && is_array($result['classes'])) {
                        foreach ($result['classes'] as $class) {
                            echo '<option value="' . esc_attr($class) . '" selected>' . esc_html($class) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="builder-field-wrapper">
                <label for="results[<?php echo $index; ?>][content]">HTML Content</label>
                <textarea class="results-html" name="results[<?php echo $index; ?>][content]" placeholder="Result Content" required><?php echo esc_textarea($result['content'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>
<?php
    return ob_get_clean();
}





function generate_rec_engine_template($args)
{
    $html = generateRecEngineHtml($args);
    $css = generateRecEngineCss($args);

    return [
        'html' => $html,
        'css' => $css
    ];
}

function generateRecEngineHtml($args)
{
    $wrapperId = $args['settings']['wrapper_id'] ?? 'rec_engine_wrapper';
    $wrapperClasses = $args['settings']['wrapper_classes'] ?? 'rec_engine_wrapper';
    $formAction = esc_url($args['settings']['form_action'] ?? 'https://example.com/page');

    $html = "";
    $html .= "<div class='$wrapperClasses' id='$wrapperId' style='display:none;'>\n";
    $html .= "  <form action='$formAction' method='get' target='_blank'>\n";

    // Generate inputs and labels
    foreach ($args['selections'] as $selection) {
        $key = esc_attr($selection['key'] ?? '');
        if ($key) {
            $html .= "    <div class='selection-title'>$key</div>\n";
            foreach ($selection['options'] as $option) {
                $value = esc_attr($option['value']);
                $id = "option-{$key}-{$value}";
                $label = esc_html($option['label']);
                $html .= "<label for='x_$id' class='selection-option $key-option mso-label'>$label</label>\n";
                $html .= "<input type='radio' id='$id' name='$key' class='selection-input {$key}-input' value='$value' aria-label='$label' required>\n";
                $html .= "<label for='$id' class='selection-option $key-option'>$label</label>\n";
                
            }
        }
    }

    $html .= "    <div class='feedback-results'>\n";
    $progressMessage = esc_html($args['settings']['progress_message'] ?? "Make your selections above to see your personalized recommendations!");
    $html .= "      <div class='progress-message'>$progressMessage</div>\n";

    // Results section
    if (isset($args['results'])) {
        foreach ($args['results'] as $result) {
            if (isset($result['classes']) && is_array($result['classes'])) {
                $classesByKey = [];
                foreach ($result['classes'] as $class) {
                    list($key, $value) = explode('-', $class, 2);
                    $classesByKey[$key][] = $class;
                }

                $classCombinations = [[]];
                foreach ($classesByKey as $classes) {
                    $newCombinations = [];
                    foreach ($classCombinations as $combination) {
                        foreach ($classes as $class) {
                            $newCombinations[] = array_merge($combination, [$class]);
                        }
                    }
                    $classCombinations = $newCombinations;
                }

                $concatenatedClasses = array_map(function ($combo) {
                    return implode('-', array_map('esc_attr', $combo));
                }, $classCombinations);

                $html .= "      <div class='result " . implode(' ', $concatenatedClasses) . "'>\n";
                $html .= stripslashes($result['content']) . "\n";
                $html .= "      </div>\n";
            }
        }
    }

    $html .= "    </div>\n";

    // Submit button
    $html .= "    <div class='submit-row'>\n";
    $submitButtonText = esc_html($args['settings']['submit_button_text'] ?? 'Submit');
    $html .= "      <button type='submit' class='submit-button'>$submitButtonText</button>\n";
    $html .= "    </div>\n";

    $html .= "  </form>\n";
    $html .= "</div>\n";

    return $html;
}

function generateRecEngineCss($args)
{
    $wrapperId = $args['settings']['wrapper_id'] ?? 'rec_engine_wrapper';

    $css = "\n<style type='text/css'>\n";

    // Hide results
    $css .= ".results, .result { display: none; }\n";

    $css .= "\n</style>\n";
    
    $css .= "\n<style type='text/css'>\n";
    // Generate CSS to show specific results based on selections
    if (isset($args['results'])) {
        foreach ($args['results'] as $result) {
            if (isset($result['classes']) && is_array($result['classes'])) {
                $classesByKey = [];
                foreach ($result['classes'] as $class) {
                    list($key, $value) = explode('-', $class, 2);
                    $classesByKey[$key][] = $class;
                }

                $classCombinations = [[]];
                foreach ($classesByKey as $classes) {
                    $newCombinations = [];
                    foreach ($classCombinations as $combination) {
                        foreach ($classes as $class) {
                            $newCombinations[] = array_merge($combination, [$class]);
                        }
                    }
                    $classCombinations = $newCombinations;
                }

                foreach ($classCombinations as $combination) {
                    $selectors = array_map(function ($class) {
                        return "input#option-{$class}:checked";
                    }, $combination);

                    $selectorString = implode(' ~ ', $selectors);
                    $concatenatedClass = implode('-', array_map('esc_attr', $combination));

                    $css .= "#$wrapperId > form > $selectorString ~ .feedback-results .$concatenatedClass {
                        display: block!important;
                        animation: fadeIn 0.5s ease;
                        opacity: 1;
                    }\n";
                }
            }
        }
    }

    $css .= "</style>\n";

    // Add the module CSS
    $css .= $args['module_css'] ?? '';

    return $css;
}

function get_interactives_for_select()
{
    $intArgs = [
        'post_type' => 'wysiwyg_interactive',
        'posts_per_page' => -1,
        'orderby' => 'post_title',
        'order' => 'ASC'
    ];
    $interactives = get_posts($intArgs);

    $intsData = [];
    foreach ($interactives as $interactive) {
        $intsData[$interactive->ID] = $interactive->post_title;
    }

    if ($interactives) {
        return $intsData;
    } else {
        return 'No snippets found';
    }
}


