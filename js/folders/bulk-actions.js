/**
 * Bulk-action toolbar on the template-folder archive.
 *
 * Selection UI is a real <input type="checkbox" class="row-select">. Rows
 * expose data-type="folder|template" so we can route actions to the right
 * backend handler and support mixed selections (move / delete folders and
 * templates in one shot).
 */

jQuery(document).ready(function ($) {
	function refreshBulkState() {
		var $checked = $(".row-select:checked");
		$("#bulkActionsSelect").prop("disabled", $checked.length === 0);

		// Keep the header select-all checkbox in sync (indeterminate when partial).
		var $all = $(".row-select");
		var $selectAll = $("#selectAllRows");
		if ($selectAll.length) {
			if ($checked.length === 0) {
				$selectAll.prop({ checked: false, indeterminate: false });
			} else if ($checked.length === $all.length) {
				$selectAll.prop({ checked: true, indeterminate: false });
			} else {
				$selectAll.prop({ checked: false, indeterminate: true });
			}
		}
	}

	$(document).on("change", ".row-select", function () {
		var $row = $(this).closest("[data-objectid]");
		$row.toggleClass("selected", this.checked);
		refreshBulkState();
	});

	$(document).on("change", "#selectAllRows", function () {
		var isChecked = this.checked;
		$(".row-select").each(function () {
			this.checked = isChecked;
			$(this).closest("[data-objectid]").toggleClass("selected", isChecked);
		});
		refreshBulkState();
	});

	// Re-sync after an AJAX refresh swaps the table in.
	$(document).on("idwiz:uiRefreshed", refreshBulkState);

	$(document).on("change", "#bulkActionsSelect", function () {
		var $select = $(this);
		var action = $select.val();
		if (!action) {
			return;
		}

		var folderIds = [];
		var templateIds = [];
		$(".row-select:checked").each(function () {
			var $cb = $(this);
			var id = $cb.attr("data-objectid");
			if ($cb.attr("data-type") === "folder") {
				folderIds.push(id);
			} else {
				templateIds.push(id);
			}
		});

		id_do_bulk_action(action, folderIds, templateIds);

		$select.val("").prop("disabled", true);
	});

	function id_do_bulk_action(action, folderIds, templateIds) {
		if (action === "move") {
			if (folderIds.length && !templateIds.length) {
				id_move_folder(folderIds);
			} else if (!folderIds.length && templateIds.length) {
				id_move_template(templateIds);
			} else if (folderIds.length && templateIds.length) {
				id_move_mixed(folderIds, templateIds);
			}
		} else if (action === "delete") {
			// Folders first (their modal prompts for a destination for contents).
			// Templates follow in its own confirm modal.
			if (folderIds.length && templateIds.length) {
				// Fire folder flow; user can trigger the template delete after.
				id_delete_folders(folderIds);
				// Chain template deletion without blocking on the folder modal.
				setTimeout(function () {
					id_delete_templates(templateIds);
				}, 300);
			} else if (folderIds.length) {
				id_delete_folders(folderIds);
			} else if (templateIds.length) {
				id_delete_templates(templateIds);
			}
		} else if (action === "restore") {
			if (templateIds.length) {
				id_restore_templates(templateIds);
			}
		}
	}
});

/**
 * Move a mixed selection (folders + templates) to the same destination in
 * a single confirmation modal.
 */
function id_move_mixed(folderIDs, templateIDs) {
	var total = folderIDs.length + templateIDs.length;
	Swal.fire({
		title: "Move " + total + " items",
		html:
			"Move selected folders and templates to:" +
			'<br/><select id="moveToFolder" style="margin-top:10px;"><option value="">Select new location</option></select>',
		showCancelButton: true,
		confirmButtonText: "Move Items",
		preConfirm: function () {
			return new Promise(function (resolve) {
				resolve({
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
					console.error("Error loading folders", error);
				}
			);
		},
	}).then(function (result) {
		if (!result.isConfirmed) {
			return;
		}
		var destination = result.value.move_into;
		var folderPromise = folderIDs.length
			? idemailwiz_do_ajax("id_move_folder", idAjax_folder_actions.nonce, {
					this_folder: folderIDs,
					move_into: destination,
			  })
			: Promise.resolve();
		var templatePromise = templateIDs.length
			? idemailwiz_do_ajax("id_move_template", idAjax_template_actions.nonce, {
					this_template: templateIDs,
					move_into: destination,
			  })
			: Promise.resolve();

		Promise.all([folderPromise, templatePromise])
			.then(function () {
				Swal.fire({
					title: "Items Moved!",
					icon: "success",
				}).then(function () {
					window.location.reload();
				});
			})
			.catch(function (err) {
				Swal.fire({
					title: "Error moving items",
					text: err && err.message ? err.message : "",
					icon: "error",
				});
			});
	});
}
