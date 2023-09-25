jQuery(document).ready(function ($) {
	// Select2 template search intitialize
	$(document).ready(function () {
		initialize_select2_for_template_search();
	});

	//Click event for deleting a single template
	$(".delete-template").click(async function (e) {
		const post_id = $(this).attr("data-postid");
		id_delete_templates([post_id]);
	});

	//Event for restoring a single template from the trash
	$(".templateTable").on("click", ".restore-template", async function (e) {
		const post_id = $(this).attr("data-postid");
		id_restore_templates([post_id]);
	});

	//Ajax duplicate a template
	$(".duplicate-template").on('click', function (e) {
		var post_id = $(this).attr("data-postid");
		var fromBase = $(this).attr("data-frombase");
		if (fromBase) {
			var swalTitle = 'New Template from Base';
			var message = 'Create a new template from this base template?';
			var confirmText = 'Create Template';
		} else {
			var swalTitle = 'Duplicate Template?';
			var message = 'This will copy all template settings and fields, but it will not sync the new template to Iterable.';
			var confirmText = 'Duplicate';
		}
		$("#iDoverlay, #iDspinner").show();
		Swal.fire({
			title: swalTitle,
			text: message,
			icon: "info",
			confirmButtonText: confirmText,
			showCancelButton: true,
			cancelButtonText: "Nevermind",
		}).then((confirmDuplicate) => {
			console.log(confirmDuplicate);
			if (confirmDuplicate.isConfirmed) {
				const templateDuplicateData = {
					template_action: "duplicate",
					post_id: post_id,
				};

				const templateDuplicateSuccess = function (response) {
					console.log(response);
					Swal.fire({
						title: "Template created!",
						input: "checkbox",
						inputValue: 1,
						inputPlaceholder: "Go to new template now (uncheck to stay here).",
						confirmButtonText: 'Continue <i class="fa fa-arrow-right"></i>',
					}).then((whereToGo) => {
						if (whereToGo.value == 1) {
							window.location.href = response.newURL;
						} else {
							const isTemplateArchive = window.location.href.indexOf("/templates/") > -1;
							if (isTemplateArchive) {
								window.location.href = window.location.href;
							} else {
								$("#iDoverlay, #iDspinner").hide();
							}
						}
					});
				};

				const templateDuplicateError = function (response) {
					Swal.fire("Uh oh, something went wrong! Refresh and try again maybe?", {
						icon: "error",
					});
				};

				idemailwiz_do_ajax("id_ajax_template_actions", idAjax_template_actions.nonce, templateDuplicateData, templateDuplicateSuccess, templateDuplicateError);
			} else {
				$("#iDoverlay, #iDspinner").hide();
			}
		});
	});

	

	//Move template to another folder
	$(document).on("click", ".moveTemplate", function () {
		const thisTemplate = $(this).attr("data-postid");
		id_move_template([thisTemplate]);
	});
});

//Global scope functions

//Move a template to another folder
function id_move_template(templateIDs) {
	if (templateIDs.length > 1) {
		var msgText = "these templates";
		var confirmText = "Templates";
	} else {
		var msgText = "this template";
		var confirmText = "Template";
	}

	Swal.fire({
		title: "Move " + confirmText,
		html: "Move " + msgText + ' to:<br/><select id="moveToFolder" style="margin-top:10px;"><option value="">Select new location</option></select>',
		showCancelButton: true,
		confirmButtonText: "Move " + confirmText,
		preConfirm: function () {
			return new Promise(function (resolve) {
				resolve({
					this_template: templateIDs,
					move_into: jQuery("#moveToFolder").val(),
				});
			});
		},
		didOpen: function () {
			// Generate a hierarchical list of all categories
			const generateFoldersData = {
				action: "id_generate_folders_select_ajax",
			};

			const generateFoldersSuccess = function (response) {
				console.log(JSON.stringify(response, null, 2));
				jQuery("#moveToFolder").append(response.data.options);
			};

			idemailwiz_do_ajax(
				"id_generate_folders_select_ajax",
				idAjax_template_actions.nonce,
				generateFoldersData,
				generateFoldersSuccess,
				null // No error callback specified
			);
		},
	}).then(function (result) {
		console.log(result);
		if (result.isConfirmed) {
			// Make an AJAX request to update the folder
			const moveTemplateData = {
				this_template: result.value.this_template,
				move_into: result.value.move_into,
			};

			const moveTemplateSuccess = function (response) {
				Swal.fire({
					title: "Template Moved!",
					icon: "success",
				}).then(function () {
					window.location.href = response.data.newFolderLink;
				});
			};

			idemailwiz_do_ajax(
				"id_move_template",
				idAjax_template_actions.nonce,
				moveTemplateData,
				moveTemplateSuccess,
				null // No error callback specified
			);
		}
	});
}

// Delete templates
async function id_delete_templates(post_ids) {
	if (post_ids.length > 1) {
		var msgText = "these templates";
		var confirmText = "Templates";
	} else {
		var msgText = "this template";
		var confirmText = "Template";
	}
	const { isConfirmed } = await Swal.fire({
		title: "Delete " + msgText + "?",
		text: "Once deleted, templates can be restored from the trash, but they will be permanently removed from any user favorites and disconnected from any Iterable connections. If present, the templates within Iterable will not be deleted.",
		icon: "warning",
		showCancelButton: true,
		iconColor: "#dc3545",
	});

	if (isConfirmed) {
		for (let i = 0; i < post_ids.length; i++) {
			const response = await idemailwiz_do_ajax(
				'id_ajax_template_actions',
				idAjax_template_actions.nonce,
				{
					template_action: 'delete',
					post_id: post_ids[i]
				}
			).catch((error) => {
				console.error(`Error deleting post ID ${post_ids[i]}: ${error}`);
			});

			if (response && response.error) {
				console.error(`Error deleting post ID ${post_ids[i]}: ${response.error}`);
			}
		}


		Swal.fire({
			icon: "success",
			html: 'All done! Templates can be restored from the <a href="http://localhost/templates/trash/">trash</a> for 30 days.',
		}).then(() => {
			const isTemplatesArchive = window.location.href.indexOf("/templates/") > -1;
			if (isTemplatesArchive) {
				post_ids.forEach((post_id) => {
					jQuery("#template-" + post_id)
						.css("background-color", "red")
						.fadeOut(1500);
				});
				jQuery.refreshUIelement(".folderList");
				jQuery.refreshUIelement("#bulkActionsSelect");
			} else {
				const redirectUrl = `jQuery{window.location.origin}/templates/all-templates`;
				window.location.href = redirectUrl;
			}
		});

		return "Delete actions completed";
	} else {
		return "Action cancelled by user";
	}
}

//restore templates from the trash
async function id_restore_templates(templateIDs) {
	if (templateIDs.length > 1) {
		var msgText = "these templates";
		var confirmText = "templates";
	} else {
		var msgText = "this template";
		var confirmText = "template";
	}

	const { isConfirmed } = await Swal.fire({
		title: "Restore " + msgText + "?",
		text: "If previously synced to Iterable, it will NOT re-sync upon restoration.",
		icon: "question",
		confirmButtonText: "Restore " + confirmText,
		showCancelButton: true,
		iconColor: "#dc3545",
	});
	if (isConfirmed) {
		for (let i = 0; i < templateIDs.length; i++) {
			const restoreTemplateData = {
				template_action: "restore",
				post_id: templateIDs[i],
			};

			const restoreTemplateResponse = await idemailwiz_do_ajax(
				"id_ajax_template_actions",
				idAjax_template_actions.nonce,
				restoreTemplateData,
				null, // No success callback specified
				null // No error callback specified
			);

			if (restoreTemplateResponse.error) {
				console.error(`Error restoring post ID ${templateIDs[i]}: ${restoreTemplateResponse.error}`);
			}
		}

		Swal.fire({
			icon: "success",
			text: "Template restored!",
		}).then(() => {
			jQuery.refreshUIelement(".templateTable");
			jQuery.refreshUIelement("#bulkActionsSelect");
		});
	}
}
