// Add a new interactive using a swal 2 input for the title and redirecting to single snippet page
  jQuery('.new-interactive').on('click', function () {
      let createdPostId;

      Swal.fire({
          title: 'Add a new interactive',
          input: 'text',
          inputPlaceholder: 'Enter interactive title',
          showCancelButton: true,
          confirmButtonText: 'Create',
          cancelButtonText: 'Cancel',
          showLoaderOnConfirm: true,
          preConfirm: (title) => {
              return idemailwiz_do_ajax('idemailwiz_create_new_interactive', idAjax_interactives.nonce, { title: title },
                  function (response) {
                      console.log('Interactive created successfully:', response);
                      do_wiz_notif({ message: "Interactive created!", duration: 3000 });
                      createdPostId = response.data.post_id;
                  },
                  function (xhr, status, error) {
                      console.error('Error creating interactive:', error);
                      do_wiz_notif({ message: "Error creating interactive", duration: 3000 });
                  }
              );
          },
          allowOutsideClick: false
      }).then((result) => {
          if (result.isConfirmed && createdPostId) {
              window.location.href = idAjax.site_url + "?p=" + createdPostId;
          }
      });
  });


jQuery(document).ready(function($) {

    updatePreview();
    
    // Setup main builder tabs
    if ($('.interactive-builder-tabs li').length > 0) {
        
        $('.interactive-builder-tab-content').hide();
        $('.interactive-builder-tabs li:first-child').addClass('active');
        $('.interactive-builder-tab-content:first-child').show();
        
    }
    $('.interactive-builder-tabs li').on('click', function () {
        const tabId = $(this).attr('data-tab');
        $('.interactive-builder-tabs li').removeClass('active');
        $(this).addClass('active');
        $('.interactive-builder-tab-content').hide();
        $('.interactive-builder-tab-content[data-tab='+tabId+']').show();
    });    
   
    
    // Initialize CodeMirror for the CSS tab
    if ($('#module-css-tab').length > 0) {
        let cssCodeMirror = CodeMirror.fromTextArea($('#module-css-tab').find('textarea')[0], {
            lineNumbers: true,
            mode: "css",
            theme: "mbo", 
            lineWrapping: true,
            viewportMargin: Infinity
        });

        if (cssCodeMirror) {
            cssCodeMirror.on('change', function() {
                cssCodeMirror.save();
                updatePreview();
            });
        }
    }


    // Refresh CSS tab CodeMirror instance when the "Show CSS" button is clicked
    $('#show-css-tab').on('click', function () {
        const $moduleCssTab = $('#module-css-tab');
        if (!$moduleCssTab.hasClass('codemirror-refreshed')) {
            const codeMirror = $moduleCssTab.find('.CodeMirror')[0].CodeMirror;
            setTimeout(function () {
                codeMirror.refresh();
            }, 100);
            $moduleCssTab.addClass('codemirror-refreshed');
        }
       
    });
   

    // Add event listeners for form changes
    $('#interactive-builder-form').on('change', 'input, select, textarea', updatePreview);
    

   

    // Save functionality
     $('.save-interactive-btn').on('click', function() {
       // submit the form
       $('#interactive-builder-form').submit(); 
    });

    $('#interactive-builder-form').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'wiz_save_recommendation_engine');
        formData.append('security', idAjax_template_editor.nonce);
        formData.append('post_id', idAjax.currentPostId);

        // Update all CodeMirror instances and collect their values
        $('#interactive-builder-form').find('.CodeMirror').each(function(index) {
            const cmInstance = this.CodeMirror;
            cmInstance.save();
            const content = cmInstance.getValue();
            const name = $(this).closest('[data-name]').data('name');
            formData.set(name, content);
        });

        $.ajax({
            url: idAjax.wizAjaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    do_wiz_notif({ message: 'Interactive saved successfully!', duration: 3000 });
                } else {
                    console.error('Error saving Interactive:', response.data);
                    do_wiz_notif({ message: 'Error saving Interactive. See console for more info.', duration: 3000 });
                    
                }
            },
            error: function() {
                alert('An error occurred while saving the Interactive.');
            }
        });
    });

    // View HTML in swal modal
    $(document).on('click', '.view-module-html', function() {
        show_html_preview();
    });

    

});

function show_html_preview() {
    getPreviewHTML().then(html => {
        Swal.fire({
            title: 'HTML/CSS Code',
            html: `
                <button id="copy-code-btn" class="wiz-button green" style="margin-bottom: 15px;">Copy Code</button>
                <div id="code-preview-wrapper">
                    <textarea id="code-preview"></textarea>
                </div>
                
            `,
            showConfirmButton: true,
            confirmButtonText: 'Close',
            buttonsStyling: false,
            customClass: {
                container: 'html-preview-popup',
                content: 'html-preview-content',
                confirmButton: 'wiz-button green'
            },
            width: "960px",
            height: "90%",
            heightAuto: false,
            didOpen: () => {
                // Initialize CodeMirror after the Swal popup is opened
                const editor = CodeMirror.fromTextArea(document.getElementById("code-preview"), {
                    lineNumbers: true,
                    mode: "htmlmixed",
                    theme: "mbo", 
                    readOnly: true,
                    lineWrapping: true,
                });
                editor.setValue(html);

                // Apply styles to ensure left alignment
                jQuery('.CodeMirror').css({
                    'text-align': 'left',
                    'margin': '0 auto',
                    'min-height': '500px'
                });

                // Copy button functionality
                jQuery('#copy-code-btn').on('click', function() {
                    const codeText = editor.getValue();
                    navigator.clipboard.writeText(codeText).then(() => {
                        do_wiz_notif({ message: 'Code copied to clipboard', duration: 1500 });
                    }).catch(err => {
                        console.error('Failed to copy text: ', err);
                        Swal.showToast({
                            icon: 'error',
                            title: 'Failed to copy code',
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 1500
                        });
                    });
                });
            },
        });
    }).catch(error => {
        console.error('Error generating preview:', error);
    });
}

// Automatic preview update
function updatePreview() {
    getPreviewHTML().then(html => {
        jQuery('#preview-content').html(html);
    }).catch(error => {
        console.error('Error generating preview:', error);
    });
}

function getPreviewHTML() {
    return new Promise((resolve, reject) => {
        const formData = new FormData(jQuery('#interactive-builder-form')[0]);
        formData.append('action', 'wiz_preview_recommendation_engine');
        formData.append('security', idAjax_template_editor.nonce);

        jQuery.ajax({
            url: idAjax.wizAjaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // removed escaped quotes from CSS
                    response.data.css = response.data.css.replace(/\\"/g, '"');
                    resolve(response.data.css + response.data.html);
                } else {
                    reject('Error generating preview: ' + response.data);
                }
            },
            error: function() {
                reject('An error occurred while generating the preview.');
            }
        });
    });
}