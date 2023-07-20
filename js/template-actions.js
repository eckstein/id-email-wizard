jQuery(document).ready(function ($) {
	


//Click event for deleting a template
$(".delete-template").click(async function (e) {
    const post_id = $(this).attr("data-postid");
    id_delete_templates([post_id]);
});

	


//Event for restoring a template from the trash
$(".templateTable").on('click', '.restore-template', async function (e) {
    const post_id = $(this).attr("data-postid");
    id_restore_templates([post_id]);
});




	//Ajax duplicate template
	$(".duplicate-template").click(function (e) {
		var post_id = $(this).attr("data-postid");
		$("#iDoverlay, #iDspinner").show();
		Swal.fire({
			title: "Duplicate Template?",
			text: "This will copy all template settings and fields, but it will not sync the new template to Iterable.",
			icon: "warning",
			confirmButtonText: 'Duplicate',
			showCancelButton: true,
			cancelButtonText: "Nevermind",
		}).then((confirmDuplicate) => {
			console.log(confirmDuplicate);
			if (confirmDuplicate.isConfirmed) {
				$.ajax({
					type: "POST",
					url: idAjax.ajaxurl,
					data: {
						action: "id_ajax_template_actions", //this function matches handler function in functions.php
						template_action: "duplicate",
						post_id: post_id,
						security: idAjax_template_actions.nonce
					},
					success: function (response) {
						console.log(response);
						Swal.fire({
							title: "Post duplicated!",
							input: "checkbox",
							inputValue: 1,
							inputPlaceholder: "Go to new post now (uncheck to stay here).",
							confirmButtonText: 'Continue <i class="fa fa-arrow-right"></i>',
						}).then((whereToGo) => {
							console.log(whereToGo);
							if (whereToGo.value == 1) {
								// Redirect to new duplicated post URL after confirming
								window.location.href = response.newURL;
							} else {
								var isTemplatesArchive = window.location.href.indexOf('/templates/') > -1;
								if (isTemplatesArchive) {
									window.location.href = window.location.href;
								} else {
									$("#iDoverlay, #iDspinner").hide();
								}
							}
						});

						//console.log(response.status + ' | ' + response.message);
					},
					error: function (response) {
						Swal.fire("Uh oh, something went wrong! Refresh and try again maybe?", {
							icon: "error",
						});
						//console.log(response.status + ' | ' + response.message);
					},
				});
			} else {
				$("#iDoverlay, #iDspinner").hide();
			}
		});
	});

	//Create a new template from a template layout
	$(".startTemplate").on('click', function (e) {
		e.preventDefault();
		var post_id = $(this).attr("data-postid");
		$("#iDoverlay, #iDspinner").show();
		Swal.fire({
			title: "New Template",
			icon: "info",
			confirmButtonText: 'Create Template',
			showCancelButton: true,
			cancelButtonText: "Cancel",
			input: 'text',
			inputLabel: 'Enter a template title',
			inputPlaceholder: 'ie: 0876 | Global | VTC Rocks!'
		}).then((inputValue) => {
			console.log(inputValue);
			if (inputValue.isConfirmed) {
				var template_title = inputValue.value.trim();
				if (template_title.length === 0) {
					Swal.showValidationMessage("Please enter a title for the new template.");
					return;
				}
				$.ajax({
					type: "POST",
					url: idAjax.ajaxurl,
					data: {
						action: "id_ajax_template_actions",
						template_action: "create_from_template",
						post_id: post_id,
						template_title: template_title,
						security: idAjax_template_actions.nonce
					},
					success: function (response) {

						window.location.href = response.actionResponse.newURL;
					},
					error: function (response) {
						Swal.fire("Uh oh, something went wrong! Refresh and try again maybe?", {
							icon: "error",
						});
					},
				});
			} else {
				$("#iDoverlay, #iDspinner").hide();
			}
		});
	});
	


	

	// Add or remove click action 
	$(document).on('click', '.addRemoveFavorite', function(){
	var object_id = $(this).attr('data-objectid');
	var object_type = $(this).attr('data-objecttype');
	addRemoveFavorite(object_id, object_type, '.folderList,.templateTable');
	 
	});

	//Add or remove a favorite and refresh UI elements, if specified
	function addRemoveFavorite(object_id, object_type, refreshElement=false) {
		$.ajax({
		  url: idAjax.ajaxurl,
		  type: 'POST',
		  data: {
			action: 'add_remove_user_favorite',
			object_id: object_id,
			object_type: object_type,
			security: idAjax_template_actions.nonce
		  },
		  success: function(response) {
			   if (response.success) {

				// Update the UI 
				$('.addRemoveFavorite[data-objectid="' + response.objectid + '"]').toggleClass('fa-regular fa-solid');
				
				console.log('Favorite ' + response.action + 'for object ID ' + response.objectid);
				 if (refreshElement) {
					 var allRefreshes = refreshElement.split(',');
					 $.each(allRefreshes, function(index, value) {
					  // Do something with each value
					  $.refreshUIelement(value);
					});
					
				  }

			  } else {
				  console.log('Failure!' + JSON.stringify(response));
				
			  }
		  },
		  error: function(xhr, status, error) {
			console.log('Error: ' + error + '<br/>' + JSON.stringify(xhr));

		  }
		});
	}

	//Move template to another folder
	$(document).on('click', '.moveTemplate', function() {
		const thisTemplate = $(this).attr('data-postid');
		id_move_template([thisTemplate]);
		
	});
		
	
});


//Global scope functions

//Move a template to another folder
function id_move_template(templateIDs) {
	
	if (templateIDs.length > 1) {
		var msgText = 'these templates';
		var confirmText = 'Templates';
	} else {
		var msgText = 'this template';
		var confirmText = 'Template';
	}
	
	Swal.fire({
		title: 'Move ' + confirmText,
		html: 'Move ' + msgText + ' to:<br/><select id="moveToFolder" style="margin-top:10px;"><option value="">Select new location</option></select>',
		showCancelButton: true,
		confirmButtonText: 'Move ' + confirmText,
		preConfirm: function() {
			return new Promise(function(resolve) {
				resolve({
					this_template: templateIDs,
					move_into: jQuery('#moveToFolder').val()
				});
			});
		},
		didOpen: function () {
	// Generate a hierarchical list of all categories
		jQuery.ajax({
			type: 'POST',
			url: idAjax.ajaxurl,
			data: {
				action: 'id_generate_folders_select_ajax',
				security: idAjax_template_actions.nonce
			},
			success: function (response) {
				console.log(JSON.stringify(response, null, 2));
				jQuery('#moveToFolder').append(response.data.options);
			}
		});
	}

	}).then(function(result) {
		console.log(result);
		if (result.isConfirmed) {
			// Make an AJAX request to update the folder
			jQuery.ajax({
				type: 'POST',
				url: idAjax.ajaxurl,
				data: {
					action: 'id_move_template',
					this_template: result.value.this_template,
					move_into: result.value.move_into,
					security: idAjax_template_actions.nonce
				},
				success: function(response) {
					// Show a success message and update the select field
					Swal.fire({
						title: 'Template Moved!',
						icon: 'success'
					}).then(function() {
					   //redirect to the new folder location
					   //console.log(response.data.newFolderLink);
					   window.location.href = response.data.newFolderLink;
					});
				}
			});
		}
	});
}


// Delete templates
async function id_delete_templates(post_ids) {
	if (post_ids.length > 1) {
		var msgText = 'these templates';
		var confirmText = 'Templates';
	} else {
		var msgText = 'this template';
		var confirmText = 'Template';
	}
    const {
        isConfirmed
    } = await Swal.fire({
        title: 'Delete ' + msgText + '?',
        text: 'Once deleted, templates can be restored from the trash, but they will be permanently removed from any user favorites and disconnected from any Iterable connections. If present, the templates within Iterable will not be deleted.',
        icon: 'warning',
        showCancelButton: true,
        iconColor: '#dc3545',
    });

    if (isConfirmed) {
        for (let i = 0; i < post_ids.length; i++) {
            const response = await jQuery.ajax({
                type: 'POST',
                url: idAjax.ajaxurl,
                data: {
                    action: 'id_ajax_template_actions',
                    template_action: 'delete',
                    post_id: post_ids[i],
					security: idAjax_template_actions.nonce
                },
            });

            if (response.error) {
                // Optionally handle errors, perhaps breaking out of the loop
                console.error(`Error deleting post ID ${post_ids[i]}: ${response.error}`);
                //return `Error deleting post ID ${post_ids[i]}: ${response.error}`; 
            }
        }

        Swal.fire({
            icon: 'success',
            html: 'All done! Templates can be restored from the <a href="http://localhost/templates/trash/">trash</a> for 30 days.',
        }).then(() => {
            const isTemplatesArchive = window.location.href.indexOf('/templates/') > -1;
            if (isTemplatesArchive) {
                post_ids.forEach(post_id => {
                    jQuery('#template-' + post_id)
                        .css('background-color', 'red')
                        .fadeOut(1500);
                });
                jQuery.refreshUIelement('.folderList');
            } else {
                const redirectUrl = `jQuery{window.location.origin}/templates/all-templates`;
                window.location.href = redirectUrl;
            }
        });

        return 'Delete actions completed';
    } else {
        return 'Action cancelled by user';
    }
}

//restore templates from the trash
async function id_restore_templates(templateIDs) {
	if (templateIDs.length > 1) {
		var msgText = 'these templates';
		var confirmText = 'templates';
	} else {
		var msgText = 'this template';
		var confirmText = 'template';
	}
	
	const {
        isConfirmed
    } = await Swal.fire({
        title: 'Restore ' + msgText + '?',
        text: 'If previously synced to Iterable, it will NOT re-sync upon restoration.',
        icon: 'question',
        confirmButtonText: 'Restore ' + confirmText,
        showCancelButton: true,
        iconColor: '#dc3545',
    });
    if (isConfirmed) {
		
		for (let i = 0; i < templateIDs.length; i++) {
            const response = await jQuery.ajax({
                type: 'POST',
                url: idAjax.ajaxurl,
                data: {
                    action: 'id_ajax_template_actions',
                    template_action: 'restore',
                    post_id: templateIDs[i],
					security: idAjax_template_actions.nonce
                },
            });

            if (response.error) {
                // Optionally handle errors, perhaps breaking out of the loop
                console.error(`Error restoring post ID jQuery{post_ids[i]}: jQuery{response.error}`);
                //return `Error restoring post ID jQuery{post_ids[i]}: jQuery{response.error}`; 
            }
        }
        Swal.fire({
            icon: "success",
            text: "Template restored!",
        }).then(() => {
            jQuery.refreshUIelement('.templateTable');
        });
    }
}