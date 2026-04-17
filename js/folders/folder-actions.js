/**
 * Folder CRUD: create / move / delete, plus the global-scope helpers
 * id_move_folder() and id_delete_folders() that the bulk-actions dropdown
 * also dispatches to.
 *
 * All click handlers are delegated so they survive $.refreshUIelement()
 * swaps of the sidebar and table.
 */

jQuery(document).ready(function ($) {
	$(document).on("click", "#addNewFolder", function () {
		Swal.fire({
			title: "Create New Folder",
			html:
				'<input type="text" id="new-folder-name" placeholder="Enter folder name"><br/>' +
				'<select id="parent-folder" style="margin-top:10px;"><option value="">Select parent folder</option></select>',
			showCancelButton: true,
			confirmButtonText: "Create Folder",
			preConfirm: function () {
				return new Promise(function (resolve, reject) {
					var newFolderName = $("#new-folder-name").val();
					var parentFolderId = $("#parent-folder").val();

					if (!parentFolderId) {
						reject("Please select a parent folder");
					} else {
						resolve({
							folder_name: newFolderName,
							parent_folder: parentFolderId,
						});
					}
				}).catch(function (error) {
					Swal.showValidationMessage(error);
				});
			},
			didOpen: function () {
				idemailwiz_do_ajax(
					"id_generate_folders_select_ajax",
					idAjax_folder_actions.nonce,
					{},
					function (response) {
						$("#parent-folder").append(response.data.options);
					},
					function (error) {
						console.log(error);
					}
				);
			},
		}).then(function (result) {
			if (result.isConfirmed) {
				idemailwiz_do_ajax(
					"id_add_new_folder",
					idAjax_folder_actions.nonce,
					{
						folder_name: result.value.folder_name,
						parent_folder: result.value.parent_folder,
					},
					function (response) {
						Swal.fire({
							title: "Folder Created!",
							icon: "success",
						}).then(function () {
							$.refreshUIelement(".folderList");
							$.refreshUIelement(".templateTable");
						});
					},
					function (error) {
						Swal.fire({
							title: "Error!",
							text: error,
							icon: "error",
						});
					}
				);
			}
		});
	});

	$(document).on("click", ".moveFolder", function (e) {
		e.preventDefault();
		id_move_folder([$(this).attr("data-folderid")]);
	});

	$(document).on("click", ".deleteFolder", function () {
		var folderId = $(this).data("folderid");
		id_delete_folders([folderId]);
	});
});

// Global-scope functions — also used by bulk-actions.js.

function id_move_folder(folderIDs) {
	if (folderIDs.length > 1) {
		var msgText = "these folders";
		var confirmText = "Folders";
	} else {
		var msgText = "this folder";
		var confirmText = "Folder";
	}
	Swal.fire({
		title: "Move " + confirmText,
		html:
			"Move " +
			msgText +
			' to:<br/><select id="moveToFolder" style="margin-top:10px;"><option value="">Select new location</option></select>',
		showCancelButton: true,
		confirmButtonText: "Move " + confirmText,
		preConfirm: function () {
			return new Promise(function (resolve) {
				resolve({
					this_folder: folderIDs,
					move_into: jQuery("#moveToFolder").val(),
				});
			});
		},
		didOpen: function () {
			idemailwiz_do_ajax(
				"id_generate_folders_select_ajax",
				idAjax_folder_actions.nonce,
				{},
				function (response) {
					jQuery("#moveToFolder").append(response.data.options);
				},
				function (error) {
					console.log("Error: ", error);
				}
			);
		},
	}).then(function (result) {
		if (result.isConfirmed) {
			idemailwiz_do_ajax(
				"id_move_folder",
				idAjax_folder_actions.nonce,
				{
					this_folder: result.value.this_folder,
					move_into: result.value.move_into,
				},
				function (response) {
					Swal.fire({
						title: "Folder(s) Moved!",
						icon: "success",
					}).then(function () {
						window.location.href = response.data.newFolderLink;
					});
				},
				function (error) {
					console.log("Error: ", error);
				}
			);
		}
	});
}

async function id_delete_folders(folderIds) {
	if (folderIds.length > 1) {
		var msgText = "these folders";
		var confirmText = "Folders";
	} else {
		var msgText = "this folder";
		var confirmText = "Folder";
	}

	Swal.fire({
		title: "Delete Folder",
		html:
			"Move templates and sub-folders in " +
			msgText +
			' to:<br/><select id="newCategoryId" style="margin-top:10px;"></select>',
		icon: "warning",
		showCancelButton: true,
		confirmButtonText: "Delete " + confirmText + "!",
		cancelButtonText: "Cancel",
		allowEscapeKey: true,
		allowOutsideClick: true,
		didOpen: function () {
			idemailwiz_do_ajax(
				"id_generate_folders_select_ajax",
				idAjax_folder_actions.nonce,
				{},
				function (response) {
					jQuery("#newCategoryId").append(response.data.options);
				},
				function (error) {
					console.log("Error: ", error);
				}
			);
		},
		preConfirm: function () {
			return new Promise(function (resolve, reject) {
				var newCategoryId = jQuery("#newCategoryId").val();

				idemailwiz_do_ajax(
					"id_delete_folder",
					idAjax_folder_actions.nonce,
					{
						this_folder: folderIds,
						move_into: newCategoryId,
					},
					function (response) {
						if (response.success) {
							resolve(response.data);
						} else {
							reject(response.data.error);
						}
					},
					function (error) {
						console.error("Error status: " + error.status);
						console.error("Error thrown: " + error.error);
						console.error("Server response: " + error.xhr.responseText);
						reject("An error occurred during folder deletion.");
					}
				);
			});
		},
	}).then(function (result) {
		if (result.dismiss !== Swal.DismissReason.cancel && result.value) {
			Swal.fire({
				title: confirmText + " Deleted Successfully",
				html: "and templates have been moved.",
				icon: "success",
			}).then(function () {
				window.location.href = result.value.newFolderLink;
			});
		}
	});
}
