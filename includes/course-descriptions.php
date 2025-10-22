<?php
/**
 * Course Descriptions Management
 * 
 * Admin interface for managing course descriptions from the courses database
 */

// Register the admin page
add_action('admin_menu', 'idwiz_register_course_descriptions_page', 21);
function idwiz_register_course_descriptions_page()
{
    add_submenu_page(
        'idemailwiz_settings', // Parent slug from wiz-options.php add_menu_page
        'Course Descriptions',
        'Course Descriptions',
        'manage_options',
        'idemailwiz_course_descriptions',
        'idwiz_course_descriptions_page_content'
    );
}

// Main page content
function idwiz_course_descriptions_page_content()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_courses';
    
    // Get filter parameters
    $selected_division = isset($_GET['division_filter']) ? intval($_GET['division_filter']) : 0;
    $search_term = isset($_GET['search_term']) ? sanitize_text_field($_GET['search_term']) : '';
    $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'active';
    
    // Build query
    $query = "SELECT id, title, abbreviation, courseDesc, wizStatus, division_id FROM {$table_name} WHERE 1=1";
    $params = [];
    
    // Apply filters
    if ($selected_division > 0) {
        $query .= " AND division_id = %d";
        $params[] = $selected_division;
    }
    
    if (!empty($search_term)) {
        $query .= " AND (title LIKE %s OR abbreviation LIKE %s OR id LIKE %s)";
        $params[] = '%' . $wpdb->esc_like($search_term) . '%';
        $params[] = '%' . $wpdb->esc_like($search_term) . '%';
        $params[] = '%' . $wpdb->esc_like($search_term) . '%';
    }
    
    if ($status_filter === 'active') {
        $query .= " AND wizStatus = 'active'";
    } elseif ($status_filter === 'inactive') {
        $query .= " AND wizStatus = 'inactive'";
    }
    
    $query .= " ORDER BY title ASC";
    
    // Execute query
    if (!empty($params)) {
        $courses = $wpdb->get_results($wpdb->prepare($query, $params));
    } else {
        $courses = $wpdb->get_results($query);
    }
    
    ?>
    <div class="wrap">
        <h1>Course Descriptions Management</h1>
        
        <p class="description">
            Manage course descriptions for all courses in the database. Click on a course to edit its description.
        </p>
        
        <!-- Filters -->
        <form method="get" id="course-descriptions-filters" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
            <input type="hidden" name="page" value="idemailwiz_course_descriptions">
            
            <div style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <div>
                    <label for="division_filter" style="display: block; margin-bottom: 5px; font-weight: 600;">Division:</label>
                    <select name="division_filter" id="division_filter" style="min-width: 150px;">
                        <option value="0">All Divisions</option>
                        <option value="25" <?php selected($selected_division, 25); ?>>iDTC</option>
                        <option value="22" <?php selected($selected_division, 22); ?>>iDTA</option>
                        <option value="42" <?php selected($selected_division, 42); ?>>VTC</option>
                        <option value="47" <?php selected($selected_division, 47); ?>>OTA</option>
                        <option value="41" <?php selected($selected_division, 41); ?>>OPL</option>
                    </select>
                </div>
                
                <div>
                    <label for="status_filter" style="display: block; margin-bottom: 5px; font-weight: 600;">Status:</label>
                    <select name="status_filter" id="status_filter" style="min-width: 150px;">
                        <option value="all" <?php selected($status_filter, 'all'); ?>>All</option>
                        <option value="active" <?php selected($status_filter, 'active'); ?>>Active</option>
                        <option value="inactive" <?php selected($status_filter, 'inactive'); ?>>Inactive</option>
                    </select>
                </div>
                
                <div style="flex: 1;">
                    <label for="search_term" style="display: block; margin-bottom: 5px; font-weight: 600;">Search:</label>
                    <input type="text" name="search_term" id="search_term" value="<?php echo esc_attr($search_term); ?>" 
                           placeholder="Search by title, abbreviation, or ID..." style="min-width: 300px; width: 100%;">
                </div>
                
                <div>
                    <button type="submit" class="button button-primary">Apply Filters</button>
                    <a href="<?php echo admin_url('admin.php?page=idemailwiz_course_descriptions'); ?>" class="button">Reset</a>
                </div>
            </div>
        </form>
        
        <!-- Results count -->
        <p style="margin: 15px 0;">
            <strong>Showing <?php echo count($courses); ?> course<?php echo count($courses) !== 1 ? 's' : ''; ?></strong>
        </p>
        
        <!-- Courses table -->
        <table class="wp-list-table widefat fixed striped" id="course-descriptions-table">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th style="width: 100px;">Code</th>
                    <th style="width: 300px;">Course Name</th>
                    <th style="width: 100px;">Division</th>
                    <th style="width: 80px;">Status</th>
                    <th>Description</th>
                    <th style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($courses)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">
                            <em>No courses found matching your criteria.</em>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($courses as $course): ?>
                        <tr data-course-id="<?php echo esc_attr($course->id); ?>">
                            <td><?php echo esc_html($course->id); ?></td>
                            <td><?php echo esc_html($course->abbreviation); ?></td>
                            <td><strong><?php echo esc_html($course->title); ?></strong></td>
                            <td><?php echo esc_html(get_division_name($course->division_id)); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($course->wizStatus); ?>">
                                    <?php echo esc_html(ucfirst($course->wizStatus)); ?>
                                </span>
                            </td>
                            <td>
                                <div class="course-description-display">
                                    <?php if (!empty($course->courseDesc)): ?>
                                        <p><?php echo esc_html($course->courseDesc); ?></p>
                                    <?php else: ?>
                                        <em style="color: #999;">No description added</em>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <button type="button" class="button button-small edit-description-btn" 
                                        data-course-id="<?php echo esc_attr($course->id); ?>"
                                        data-course-title="<?php echo esc_attr($course->title); ?>"
                                        data-current-desc="<?php echo esc_attr($course->courseDesc); ?>">
                                    <span class="dashicons dashicons-edit"></span> Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Edit Modal -->
    <div id="edit-description-modal" style="display: none;">
        <div class="modal-backdrop"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Course Description</h2>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p><strong>Course:</strong> <span id="modal-course-title"></span></p>
                <label for="course-description-input" style="display: block; margin: 15px 0 5px; font-weight: 600;">
                    Description:
                </label>
                <textarea id="course-description-input" rows="6" style="width: 100%; max-width: 100%;"></textarea>
                <p class="description">Enter a description for this course. This will be stored in the courseDesc field.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-secondary modal-close">Cancel</button>
                <button type="button" class="button button-primary" id="save-description-btn">Save Description</button>
            </div>
        </div>
    </div>
    
    <style>
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .course-description-display p {
            margin: 0;
            line-height: 1.4;
        }
        
        /* Modal styles */
        #edit-description-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 100000;
        }
        
        .modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
        }
        
        .modal-content {
            position: relative;
            background: white;
            max-width: 600px;
            margin: 50px auto;
            border-radius: 4px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            z-index: 100001;
        }
        
        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 18px;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            line-height: 1;
            cursor: pointer;
            color: #999;
            padding: 0;
            width: 30px;
            height: 30px;
        }
        
        .modal-close:hover {
            color: #333;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #ddd;
            text-align: right;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        #course-descriptions-table .button-small {
            padding: 2px 8px;
            height: auto;
            line-height: 1.4;
        }
        
        #course-descriptions-table .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
            vertical-align: middle;
            margin-right: 2px;
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        let currentCourseId = null;
        
        // Open modal
        $('.edit-description-btn').on('click', function() {
            currentCourseId = $(this).data('course-id');
            const courseTitle = $(this).data('course-title');
            const currentDesc = $(this).data('current-desc');
            
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
                    security: '<?php echo wp_create_nonce('id-general'); ?>',
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
                            $descDisplay.html('<p>' + $('<div>').text(newDescription).html() + '</p>');
                        }
                        
                        // Update the button's data attribute
                        $row.find('.edit-description-btn').data('current-desc', newDescription);
                        
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
    </script>
    <?php
}

// AJAX handler for saving course descriptions
add_action('wp_ajax_idwiz_save_course_description', 'idwiz_save_course_description_handler');
function idwiz_save_course_description_handler()
{
    // Check nonce
    if (!check_ajax_referer('id-general', 'security', false)) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Get and validate data
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
    
    if ($course_id <= 0) {
        wp_send_json_error('Invalid course ID');
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_courses';
    
    // Update the course description
    $result = $wpdb->update(
        $table_name,
        ['courseDesc' => $description],
        ['id' => $course_id],
        ['%s'],
        ['%d']
    );
    
    if ($result === false) {
        error_log("Failed to update course description for course ID: {$course_id}. Error: " . $wpdb->last_error);
        wp_send_json_error('Database update failed: ' . $wpdb->last_error);
        return;
    }
    
    wp_send_json_success([
        'message' => 'Course description updated successfully',
        'course_id' => $course_id,
        'description' => $description
    ]);
}

