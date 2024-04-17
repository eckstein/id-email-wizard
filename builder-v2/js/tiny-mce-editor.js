
function init_element_title_tinymce($editable = null) {
    var selector = '';
    if ($editable) {
        selector = $editable.selector;
    } else {
        selector = '.edit-row-title, .edit-columnset-title, .edit-column-title';
    }
    
    tinymce.init({
        selector: selector,
        license_key: 'gpl',
        inline: true,
        menubar: false,
        toolbar: false,
        convert_newlines_to_brs: false,
        setup: function (editor) {
            editor.on('click', function (e) {
                if (editor.contentDocument.activeElement.nodeName === 'BODY') {
                    editor.getBody().focus();
                }
            });
            
            editor.on('keydown', function (e) {
                if (e.keyCode === 13) { // Enter key
                    e.preventDefault();
                    editor.save();
                    editor.hide();
                }
            });
            
            editor.on('init', function () {
                editor.setContent(editor.getContent({ format: 'raw' }));
            });
            
            // Show the editor when the element is clicked
            editor.on('click', function (e) {
                editor.show();
            });
            
            // Hide the editor when it loses focus
            editor.on('blur', function (e) {
                editor.hide();
            });
        }
    });
}


function init_template_title_tinymce() {
    tinymce.init({
        selector: '#single-template-title',
        license_key: 'gpl',
        inline: true,
        menubar: false,
        toolbar: false,
        setup: function(editor) {
            var titleH1 = jQuery(editor.getElement());
            var templateId = titleH1.attr("data-template-id");
            var debounceTimeout;

            editor.on('input', function() {
                clearTimeout(debounceTimeout);
                debounceTimeout = setTimeout(function() {
                    var editorContent = editor.getContent();
                    save_wiz_template_title(templateId, editorContent);
                }, 500); // Adjust the debounce delay as needed (in milliseconds)
            });
        }
    });
}

// Destroy and re-initialize TinyMCE on each .wiz-wysiwyg element with option element selection
function reinitTinyMCE($optionalElement = null) {

    // Determine the correct selector for the operation
    var selector = $optionalElement ? $optionalElement.find('.wiz-wysiwyg') : '.wiz-wysiwyg';
    
    jQuery(selector).each(function() {
        var editorId = jQuery(this).attr('id');
        var editor = tinymce.get(editorId);
        if (editor) {
            // Save the content from the TinyMCE editor back to the textarea
            editor.save();

            // Properly remove the TinyMCE instance to avoid any residual states
            editor.remove();
        }

        // Clear any TinyMCE-related data attributes that might interfere with reinitialization
        jQuery(this).removeAttr('data-mce-id').removeAttr('data-id');
    });

    // After ensuring all editors within the context are properly reset, reinitialize TinyMCE
    builder_init_tinymce($optionalElement);

}

var isInitialTinyMCELoad = true;
function builder_init_tinymce($optionalElement) {
    var selector = $optionalElement ? '#' + $optionalElement.attr('id') + ' .wiz-wysiwyg' : '.wiz-wysiwyg';
    

    function updateEditorContent(editor) {
        var $chunk = jQuery('#' + editor.id).closest('.builder-chunk');
        
        if (!isInitialTinyMCELoad) {
            handleEditorUpdate(editor);
            //updateChunkPreviews($chunk.attr('id'));
            update_chunk_data_attr_data($chunk);
            save_template_to_session();
            sessionStorage.setItem('unsavedChanges', 'true');
            update_template_preview();
        }
    }

    // Function to update the builder chunk title when tinyMce content is updated
    function update_builder_chunk_title(editor) {
        // Get the content from the editor, stripping HTML tags
        let textContent = editor.getContent({ format: 'text' }).trim();

        // Trim the text content to the first 32 characters
        textContent = textContent.substring(0, 32);

        // Find the closest .builder-chunk-title element and update its text
        const editorElement = editor.getElement();
        const builderChunkTitle = jQuery(editorElement).closest('.builder-chunk').find('.builder-chunk-title');
        if (builderChunkTitle.length) {
            builderChunkTitle.text(textContent + '...');
        }
    }

    // Debouncing logic integrated into the event listener or similar
    let debounceTimeout;
    function handleEditorUpdate(editor) {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => update_builder_chunk_title(editor), 1000);
    }
    

    

    tinymce.PluginManager.add('merge_tags_button', function(editor, url) {
        const menuItems = 	idwizMergeMenuItemList();
  
        function generateMenuItems(items) {
          return items.map(function(item) {
            return {
              type: 'menuitem',
              text: item.text,
              onAction: function() {
                editor.insertContent(item.value);
              }
            };
          });
        }
  
        editor.ui.registry.addMenuButton('merge_tags_button', {
          text: 'Merge Tags',
          tooltip: 'Insert personalization',
          fetch: function(callback) {
            const items = menuItems.map(item => ({
              type: 'nestedmenuitem',
              text: item.text,
              getSubmenuItems: () => generateMenuItems(item.items)
            }));
            callback(items);
          }
        });
    });

    tinymce.PluginManager.add('theme_switcher', function(editor) {
        editor.ui.registry.addToggleButton('theme_switcher', {
            icon: 'contrast', 
            onpostrender: function() {
                // Add class to button
                editor.ui.registry.get('theme_switcher').element.classList.add('theme-switcher');
            },
            onAction: function(api) {
                const isActive = api.isActive();
                api.setActive(!isActive); // Toggle the button's active state

                // Target the body of the editor's iframe document to change its background
                const bodyStyle = editor.getBody().style; // This targets the content body directly

                // Get the attached textarea element
                const textarea = editor.getElement();

                if (!isActive) { // If the button was not active, activate dark mode
                    bodyStyle.backgroundColor = '#222222';
                    // Set the data attribute on the textarea element
                    textarea.setAttribute('data-editor-mode', 'dark');
                } else { // If the button was active, revert to light mode
                    bodyStyle.backgroundColor = '#FFFFFF';
                    // Set the data attribute on the textarea element
                    textarea.setAttribute('data-editor-mode', 'light');
                }
                save_template_to_session();
            },
            onSetup: function(api) {
                // Retrieve the original textarea element that TinyMCE is based on
                const originalTextarea = editor.getElement();
                
                // Read the data-editor-mode attribute to determine the preferred mode
                const editorMode = originalTextarea.getAttribute('data-editor-mode');
    
                // Determine if dark mode should be active based on the attribute's value
                const isDarkMode = editorMode === 'dark';

                // Set the toggle button's active state based on isDarkMode
                api.setActive(isDarkMode);

                // Apply the corresponding styles to the editor's body based on the mode
                const bodyStyle = editor.getBody().style;
                if (isDarkMode) {
                    // Apply Dark Mode styles
                    bodyStyle.backgroundColor = '#222222';
                } else {
                    // Apply Light Mode styles (or simply don't change anything if these are the defaults)
                    bodyStyle.backgroundColor = '#FFFFFF';
                }

					
            }

        });
    });

    tinymce.PluginManager.add('custom_link_handler', function(editor) {
        editor.on('PreProcess', function(e) {
            jQuery(e.node).find('a').each(function() {
                var $link = jQuery(this);
                if (!$link.find('img').length) {
                    $link.addClass('id-textlink');
                    $link.removeAttr('data-mce-style'); // Remove the data-mce-style attribute
                }
            });
           
        });
    });

    tinymce.init({
        selector: selector,
        license_key: 'gpl',
        height: 250,
        toolbar: [
            { name: 'code', items: [ 'code'] },
            { name: 'merge_tags_button', items: [ 'merge_tags_button'] },
            { name: 'theme_switcher', items: [ 'theme_switcher'] },
            { name: 'styles', items: [ 'styles' ] }, 
            { name: 'formatting', items: [ 'fontsize', 'lineheight', 'forecolor', 'bold', 'italic', 'uppercase', 'removeformat'] },
            { name: 'alignment', items: [ 'alignleft', 'aligncenter', 'alignright' ] },
            { name: 'lists', items: [ 'bullist', 'numlist' ] },
            { name: 'link', items: [ 'link'] },
				
        ],
        toolbar_mode: 'scrolling',
        block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6;',
        font_size_formats: '.8em .9em 1em 1.1em 1.2em 1.3em 1.4em 1.5em 1.6em 1.7em 1.8em 1.9em 2em 2.5em',
        line_height_formats: '.8em .9em 1em 1.1em 1.2em 1.3em 1.4em 1.5em 1.6em 1.7em 1.8em 1.9em 2em 2.5em',
        elementpath: false,
        menubar: false,
        force_hex_color: 'always',
        extended_valid_elements: 'span[*]',
        valid_children: '+body[style],+span[span]',
        plugins: 'link code lists merge_tags_button theme_switcher custom_link_handler',
        setup: function(editor) {
            
            editor.ui.registry.addButton('uppercase', {
                text: 'aA',
                tooltip: 'Uppercase Style',
                onAction: function() {
                    var content = editor.selection.getContent({ 'format': 'html' });
                    editor.selection.setContent('<span style="text-transform: uppercase;">' + content + '</span>');
                }
            });

            editor.on('init', function() {
                var editorContainer = jQuery(editor.getContainer());
                var $baseColorInput = editorContainer.closest('.builder-chunk').find('input[name="text_base_color"]');
                var baseColor = $baseColorInput.attr('data-color-value');
                editor.getBody().style.color = baseColor;

                editorContainer.closest('.builder-chunk').on('change', 'input[name="text_base_color"]', function(e, tinycolor) {
                    var baseColor = tinycolor.toHexString();
                    var editorContainer = jQuery(editor.getContainer());
                    var $parent = jQuery(this).closest('.builder-chunk');
                    if ($parent.has(editorContainer).length) {
                        editor.getBody().style.color = baseColor;
                    }
                });

                // Set isInitialTinyMCELoad to false after the initial content update
                setTimeout(function() {
                    isInitialTinyMCELoad = false;
                }, 0);

               
            });

            editor.on('input', function() {
                updateEditorContent(editor);
            });

            editor.on('AddUndo', function(e) {
                updateEditorContent(editor);
            });

            editor.on('Undo', function(e) {
                updateEditorContent(editor);
            });

            editor.on('Redo', function(e) {
                updateEditorContent(editor);
            });

            editor.on('SetContent', function(e) {
                jQuery(editor.getBody()).find('a').each(function() {
                    var $link = jQuery(this);
                    if (!$link.find('img').length) {
                        $link.addClass('id-textlink');
                    }
                });
                
            });

            

            
        },
        formats: {
            bold: [
              { inline: 'span', styles: { fontWeight: 'bold' } },
              { inline: 'strong', remove: 'all' },
              { inline: 'b', remove: 'all' }
            ],
        },
        style_formats: [ // Define custom styles
            { title: 'P', format: 'p' },
            { title: 'H1', format: 'h1' },
            { title: 'H2', format: 'h2' },
            { title: 'H3', format: 'h3' },
            { title: 'H4', format: 'h4' },
            { title: 'H5', format: 'h5' },
            { title: 'H6', format: 'h6' },
				
        ],
        fontsize_formats: "8pt 10pt 12pt 14pt 18pt 24pt 36pt",
    });
}

function save_all_tiny_mces($optionalElement = null) {
    var selector = $optionalElement ? $optionalElement.find('.wiz-wysiwyg') : '.wiz-wysiwyg';
    jQuery(selector).each(function() {
        var editorId = jQuery(this).attr('id');
        var editor = tinymce.get(editorId);
        if (editor) {
            // Save the content from the TinyMCE editor back to the textarea
            editor.save();
        }
    });
}