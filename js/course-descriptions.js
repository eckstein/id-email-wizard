jQuery(document).ready(function($) {
    let currentCourseId = null;
    
    // Helper function to escape HTML for attributes
    function escapeHtmlAttr(text) {
        return text
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
    
    // Helper function to escape HTML for display
    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }
    
    // Open modal
    $('.edit-description-btn').on('click', function() {
        currentCourseId = $(this).data('course-id');
        const courseTitle = $(this).data('course-title');
        const currentDesc = $(this).attr('data-current-desc') || '';
        
        $('#modal-course-title').text(courseTitle);
        $('#course-description-input').val(currentDesc);
        $('#edit-description-modal').fadeIn(200);
    });
    
    // Close modal
    $('.modal-close, .modal-backdrop').on('click', function() {
        $('#edit-description-modal').fadeOut(200);
        currentCourseId = null;
    });
    
    // Save description
    $('#save-description-btn').on('click', function() {
        if (!currentCourseId) return;
        
        const newDescription = $('#course-description-input').val();
        const $btn = $(this);
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'idwiz_save_course_description',
                security: courseDescriptionsData.nonce,
                course_id: currentCourseId,
                description: newDescription
            },
            success: function(response) {
                if (response.success) {
                    // Update the description display in the table
                    const $row = $('tr[data-course-id="' + currentCourseId + '"]');
                    const $descDisplay = $row.find('.course-description-display');
                    
                    if (newDescription.trim() === '') {
                        $descDisplay.html('<em style="color: #999;">No description added</em>');
                    } else {
                        $descDisplay.html('<p>' + escapeHtml(newDescription) + '</p>');
                    }
                    
                    // Update the button's data attribute properly with escaped value
                    $row.find('.edit-description-btn').attr('data-current-desc', newDescription);
                    
                    // Close modal
                    $('#edit-description-modal').fadeOut(200);
                    
                    // Show success message
                    if (typeof window.wizNotif === 'function') {
                        window.wizNotif('Course description updated successfully', 'success');
                    }
                } else {
                    alert('Error: ' + (response.data || 'Failed to save description'));
                }
            },
            error: function() {
                alert('Error: Failed to communicate with server');
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Close modal on Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#edit-description-modal').is(':visible')) {
            $('#edit-description-modal').fadeOut(200);
            currentCourseId = null;
        }
    });
});

