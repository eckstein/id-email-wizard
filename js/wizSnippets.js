jQuery(document).ready(function ($) {

    if ($('#wizSnippet-editor').length) {
        var snippetEditor = CodeMirror.fromTextArea(document.getElementById('wizSnippet-editor'), {
            mode: 'htmlmixed',
            lineNumbers: true,
            theme: 'mbo',
            viewportMargin: Infinity
        });

        var snippetCssEditor = CodeMirror.fromTextArea(document.getElementById('wizSnippet-css-editor'), {
            mode: 'css',
            lineNumbers: true,
            theme: 'mbo',
            viewportMargin: Infinity
        });
    }

    $('#save-wizSnippet').on('click', function() {
        var snippetContent = snippetEditor.getValue();
        var snippetCss = snippetCssEditor.getValue();
        var post_id = $(this).attr('data-post-id');

        // Prepare the data to be sent
        var additionalData = {
            post_id: post_id,
            content: snippetContent,
            css: snippetCss,
        };

        // Call your AJAX function
        idemailwiz_do_ajax('save_wizSnippet_content', idAjax_wizSnippets.nonce, additionalData, 
            function(response) {
                // Success callback
                console.log('Snippet saved successfully:', response);
                do_wiz_notif({ message: "Snippet saved!", duration: 3000 });
            },
            function(xhr, status, error) {
                // Error callback
                console.error('Error saving snippet:', error);
                do_wiz_notif({ message: "Error saving snippet", duration: 3000 });
            }
        );
    });

    
    // Delete an snippet from the single snippet page
    $(".delete-snippet").on("click", function () {
        const snippetId = $(this).data("snippetid"); 
        idwiz_deleteSnippets([snippetId], function () {
            // Redirect to /snippets
            window.location.href = "/snippets";
        });
    });


});




function idwiz_deleteSnippets(snippetIds, onSuccess) {
    // Modify the Swal2 text based on the number of selected snippets
    const swalText = snippetIds.length > 1 ? "Delete These Snippets?" : "Delete This Snippet?";
    const swalButton = snippetIds.length > 1 ? "Yes, delete them" : "Yes, delete it";

    // Show Swal2 confirmation dialog
    Swal.fire({
        title: 'Are you sure?',
        text: swalText,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: swalButton,
    }).then((result) => {
        if (result.isConfirmed) {
            // Proceed with the Ajax call to delete the snippets
            idemailwiz_do_ajax(
                "idemailwiz_delete_snippets",
                idAjax_wizSnippets.nonce,
                { selectedIds: snippetIds },
                function (data) {
                    if (data.success) {
                        // Call the onSuccess callback if provided
                        if (typeof onSuccess === "function") {
                            onSuccess();
                        }
                    }
                },
                function (error) {
                    console.log(error);
                }
            );
        }
    });
}
