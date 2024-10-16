jQuery(document).ready(function ($) {
//Add a new folder
$('body').on('click', '#addNewFolder', function () {
    Swal.fire({
        title: 'Create New Folder',
        html: '<input type="text" id="new-folder-name" placeholder="Enter folder name"><br/>' +
            '<select id="parent-folder" style="margin-top:10px;"><option value="">Select parent folder</option></select>',
        showCancelButton: true,
        confirmButtonText: 'Create Folder',
        preConfirm: function () {
            return new Promise(function (resolve, reject) {
                // Get the selected folder ID
                var newFolderName = $('#new-folder-name').val();
                var parentFolderId = $('#parent-folder').val();

                // Check if a parent folder has been selected
                if (!parentFolderId) {
                    reject('Please select a parent folder');
                } else {
                    resolve({
                        folder_name: newFolderName,
                        parent_folder: parentFolderId
                    });
                }
            }).catch(function(error) {
                Swal.showValidationMessage(error);
            });
        },
        didOpen: function () {
            // Generate a hierarchical list of all folders
            idemailwiz_do_ajax(
                'id_generate_folders_select_ajax',
                idAjax_folder_actions.nonce,
                {},
                function(response) {
                    console.log(response);
                    $('#parent-folder').append(response.data.options);
                },
                function(error) {
                    console.log(error);
                }
            );
        }

    }).then(function (result) {
        if (result.isConfirmed) {
            // Make an AJAX request to create the new folder
            idemailwiz_do_ajax(
                'id_add_new_folder',
                idAjax_folder_actions.nonce,
                {
                    folder_name: result.value.folder_name,
                    parent_folder: result.value.parent_folder
                },
                function(response) {
                    // Show a success message and update the select field
                    Swal.fire({
                        title: 'Folder Created!',
                        icon: 'success'
                    }).then(function () {
                        // Refresh the UI element containing the list of folders
                        $.refreshUIelement('.folderList');
                        $.refreshUIelement('.templateTable');
                    });
                },
                function(error) {
                    // Show an error message
                    Swal.fire({
                        title: 'Error!',
                        text: error,
                        icon: 'error'
                    });
                }
            );
        }
    });

});



//Show and hide the sub-categories in the folder panel
  $('.showHideSubs').click(function(e) {
    e.stopPropagation(); // Prevent event bubbling

    var subCategories = $(this).siblings('.sub-categories');
    var isSubGroupOpen = subCategories.is(':visible');

    if (isSubGroupOpen) {
      // Close the sub-group
      subCategories.hide();
      $(this).removeClass('fa-angle-up').addClass('fa-angle-down');
    } else {
      // Open the clicked sub-group
      subCategories.show();
      $(this).removeClass('fa-angle-down').addClass('fa-angle-up');
    }
  });


//Move folder to another folder
$(document).on('click', '.moveFolder', function(e) {
	e.preventDefault();
	id_move_folder([$(this).attr('data-folderid')]);
});

//Delete a folder
$('.deleteFolder').on('click', function () {
    var folderId = $(this).data('folderid');
    id_delete_folders([folderId]);
});



});

//Global scope functions

function id_move_folder(folderIDs) {
	if (folderIDs.length > 1) {
		var msgText = 'these folders';
		var confirmText = 'Folders';
	} else {
		var msgText = 'this folder';
		var confirmText = 'Folder';
	}
    Swal.fire({
        title: 'Move ' + confirmText,
        html: 'Move ' + msgText + ' to:<br/><select id="moveToFolder" style="margin-top:10px;"><option value="">Select new location</option></select>',
        showCancelButton: true,
        confirmButtonText: 'Move ' + confirmText ,
        preConfirm: function() {
            return new Promise(function(resolve) {
                resolve({
                    this_folder: folderIDs,
                    move_into: jQuery('#moveToFolder').val()
                });
            });
        },
        didOpen: function () {
            // Generate a hierarchical list of all categories
            idemailwiz_do_ajax(
                'id_generate_folders_select_ajax',
                idAjax_folder_actions.nonce,
                {},
                function(response) {
                    console.log(response);
                    jQuery('#moveToFolder').append(response.data.options);
                },
                function(error) {
                    console.log("Error: ", error);
                }
            );
        }


    }).then(function(result) {
        console.log(result);
        if (result.isConfirmed) {
            idemailwiz_do_ajax(
                'id_move_folder',
                idAjax_folder_actions.nonce,
                {
                    this_folder: result.value.this_folder,
                    move_into: result.value.move_into
                },
                function(response) {
                    Swal.fire({
                        title: 'Folder(s) Moved!',
                        icon: 'success'
                    }).then(function() {
                        // Redirect to the new folder location
                        console.log(response.data.newFolderLink);
                        window.location.href = response.data.newFolderLink;
                    });
                },
                function(error) {
                    console.log('Error: ', error);
                }
            );
        }
    });

}

//Delete a folder
async function id_delete_folders(folderIds) {
    if (folderIds.length > 1) {
        var msgText = 'these folders';
        var confirmText = 'Folders';
    } else {
        var msgText = 'this folder';
        var confirmText = 'Folder';
    }

    Swal.fire({
        title: 'Delete Folder',
        html: 'Move templates and sub-folders in ' + msgText + ' to:<br/><select id="newCategoryId" style="margin-top:10px;"></select>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete ' + confirmText + '!',
        cancelButtonText: 'Cancel',
        allowEscapeKey: true,
        allowOutsideClick: true,
        didOpen: function () {
            idemailwiz_do_ajax(
                'id_generate_folders_select_ajax',
                idAjax_folder_actions.nonce,
                {},
                function(response) {
                    console.log(response);
                    jQuery('#newCategoryId').append(response.data.options);
                },
                function(error) {
                    console.log('Error: ', error);
                }
            );
        },
        preConfirm: function () {
            return new Promise(function (resolve, reject) {
                // Get the selected category ID
                var newCategoryId = jQuery('#newCategoryId').val();

                idemailwiz_do_ajax(
                    'id_delete_folder',
                    idAjax_folder_actions.nonce,
                    {
                        this_folder: folderIds,
                        move_into: newCategoryId
                    },
                    function(response) {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(response.data.error);
                        }
                    },
                    function(error) {
                        console.error("Error status: " + error.status);
                        console.error("Error thrown: " + error.error);
                        console.error("Server response: " + error.xhr.responseText);
                        reject('An error occurred during folder deletion.');
                    }
                );
            });
        },
    }).then(function (result) {
        if (result.dismiss !== Swal.DismissReason.cancel && result.value) {
            Swal.fire({
                title: confirmText + ' Deleted Successfully',
                html: 'and templates have been moved.',
                icon: 'success',
            }).then(function () {
				//console.log('Redirect to:' + result.value.newFolderLink);
                window.location.href = result.value.newFolderLink;
            });
        }
    });
}
