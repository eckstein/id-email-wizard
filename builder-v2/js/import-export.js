// Function to fetch JSON data and display or export it
function get_wiztemplate_json(templateId, callback) {
    var sessionData = get_template_from_session();
    if (sessionData) {
        callback(sessionData);
    } else {
        var additionalData = { template_id: templateId };
        idemailwiz_do_ajax("get_wiztemplate_with_ajax", idAjax_template_editor.nonce, additionalData, 
            function(data) { // Success callback
                callback(data.data);
            }, 
            function(xhr, status, error) { // Error callback
                console.error('Error retrieving or generating JSON for template');
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'Error retrieving or generating JSON for template!',
                });
            }, 
            "json");
    }
}

// Function to display JSON data in a Swal box
function display_wiztemplate_json(templateData) {
    var jsonData = JSON.stringify(templateData, null, 2);
    Swal.fire({
        title: 'JSON Data',
        html: `<pre><code class="json">${wizEscapeHtml(jsonData)}</code></pre>`,
        customClass: {
            popup: 'template-json-modal',
            htmlContainer: 'template-json-pre-wrap'
        },
        width: '800px',
        didOpen: () => {
            document.querySelectorAll('pre code').forEach((block) => {
                hljs.highlightElement(block);
            });
        }
    });
}

// Utility function to safely escape HTML
function wizEscapeHtml(text) {
    return text.replace(/&/g, "&amp;")
               .replace(/</g, "&lt;")
               .replace(/>/g, "&gt;")
               .replace(/"/g, "&quot;")
               .replace(/'/g, "&#039;");
}

function download_template_json($clicked) {
    var templateId = $clicked.data("post-id");
    get_wiztemplate_json(templateId, function(jsonData) {
        var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(jsonData, null, 2));
        var downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute("href", dataStr);
        downloadAnchorNode.setAttribute("download", "template_data.json");
        document.body.appendChild(downloadAnchorNode); // required for firefox
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
    });
};

function import_wiztemplate_json() {
    Swal.fire({
        title: 'Import JSON Data',
        showCancelButton: true,
        confirmButtonText: 'Import',
        html: `
            <div class="swalTabs">
                <ul>
                    <li><a href="#pasteTab" class="active" data-tab="pasteTab">Paste JSON</a></li>
                    <li><a href="#uploadTab" data-tab="uploadTab">Upload File</a></li>
                </ul>
                <div id="pasteTab" style="display: block; height: 300px;">
                    <textarea id="jsonInput" rows="10" style="width: 100%; margin-top: 15px;"></textarea>
                </div>
                <div id="uploadTab" style="display: none; height: 300px;">
                    <div class="swal-file-upload">
                        <input type="file" id="jsonFileInput" name="jsonFile">
                        <label for="jsonFileInput" class="file-upload-label">Drag and drop a file here or click to select a file</label>
                    </div>
                </div>

            </div>
        `,
        focusConfirm: false,
        preConfirm: () => {
            const isPastedData = jQuery('.swalTabs ul li a.active').attr('data-tab') === 'pasteTab';
            return process_wiz_template_json_upload(isPastedData)
            .then(sessionKey => {
                // Optionally clear the session storage if it's no longer needed
                sessionStorage.removeItem(sessionKey);

                // Show a success message with Swal
                Swal.fire({
                    title: 'Success!',
                    text: 'Your JSON data has been processed successfully.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    if (result.value) {
                        // Refresh the page to reflect the changes
                        window.location.reload();
                    }
                });
            })
            .catch(error => {
                Swal.showValidationMessage(`Process failed: ${error.message}`);
                // Returning a rejected promise prevents Swal from closing
                return Promise.reject(error);
					
            });
        },
        didOpen: () => {
            // Initialize the tab interface
            document.querySelectorAll('.swalTabs ul li a').forEach((tab) => {
                tab.addEventListener('click', (e) => {
                    e.preventDefault();
                    const tabId = tab.getAttribute('href').substring(1); // Get the ID without the '#'
                    
                    // Deactivate all tabs and hide all tab content
                    document.querySelectorAll('.swalTabs ul li a').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.swalTabs > div').forEach(content => content.style.display = 'none');
                    
                    // Activate clicked tab and show its content
                    tab.classList.add('active');
                    document.getElementById(tabId).style.display = 'block';
                });
            });

            // Trigger click on the first tab to show it by default
            document.querySelector('.swalTabs ul li a').click();

            jQuery('#jsonFileInput').on('change', function() {
                // Check if any files were selected
                if (this.files && this.files.length > 0) {
                    var file = this.files[0];
                    var fileType = file.type;
                    var match = ['application/json', 'text/json'];

                    // Validate file type
                    if (match.indexOf(fileType) !== -1) {
                        // File is a JSON, update label text to show file name
                        jQuery('.file-upload-label').text(file.name + " is ready to upload.")
                            .css('color', '#28a745'); // Optional: change label color
            
                        jQuery('.swal-file-upload').css({
                            'border-color': '#28a745', // Example: Change border color
                            'background-color': '#e2e6ea' // Lighten background
                        });
                    } else {
                        // File is not a JSON, show error and reset input
                        jQuery('.file-upload-label').text("Invalid file type. Please select a .json file.")
                            .css('color', '#dc3545'); // Optional: change label color for error
            
                        jQuery('.swal-file-upload').css({
                            'border-color': '#dc3545', // Example: Change border color for error
                            'background-color': '#f8d7da' // Light background for error
                        });

                        // Reset the file input for another selection
                        jQuery(this).val('');
                    }
                } else {
                    // No file selected, reset to default state
                    resetUploadField();
                }
            });

            // Function to reset the upload field to its default state
            function resetUploadField() {
                jQuery('.file-upload-label').text("Drag and drop a file here or click to select a file")
                    .css('color', '#007bff');
    
                jQuery('.swal-file-upload').css({
                    'border-color': '#007bff',
                    'background-color': '#f8f9fa'
                });
            }

        }
    });
}


async function process_wiz_template_json_upload(isPastedData) {
    async function processData(data) {
        //try {
            const parsedData = JSON.parse(data);
            // Await the validation result; this will throw an error if validation fails
            //await validate_wiztemplate_schema(parsedData);

            // If validation succeeds, proceed with saving the data
            const timestamp = new Date().getTime();
            const sessionKey = `uploadedJsonData_${timestamp}`;
            sessionStorage.setItem(sessionKey, JSON.stringify(parsedData));

            // Update template from JSON
            save_template_data(parsedData);
        
            // Return the session key or any other result as needed
            return sessionKey;
        // } catch (error) {
        //     // Handle or rethrow the error as appropriate
        //     console.error(error);
        //     throw error;
        // }
    }

    if (isPastedData) {
        const pastedData = document.getElementById('jsonInput').value;
        return processData(pastedData); // Return the promise
    } else {
        const file = document.getElementById('jsonFileInput').files[0];
        if (file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = async (e) => {
                    try {
                        const fileData = e.target.result;
                        await processData(fileData);
                        resolve(); // Resolve the outer promise
                    } catch (error) {
                        reject(error); // Reject the outer promise
                    }
                };
                reader.onerror = (e) => {
                    reject(`Error reading file: ${e.target.error}`);
                };
                reader.readAsText(file);
            });
        } else {
            return Promise.reject(new Error('No file selected.'));
        }
    }
}


	
function validate_wiztemplate_schema(parsedData) {
    // Check if the main key 'template_options' exists
    if (parsedData.hasOwnProperty('template_options')) {
        // Check for 'message_settings' and 'rows' keys within 'template_options'
        if (parsedData.template_options.hasOwnProperty('message_settings') &&
            parsedData.template_options.hasOwnProperty('rows')) {
            console.log("JSON structure is valid.");
            return true;
        } else {
            console.error("JSON structure does not have the required 'message_settings' and 'rows' keys.");
            return false;
        }
    } else {
        console.error("JSON structure does not have the 'template_options' key.");
        return false;
    }
}