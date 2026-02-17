jQuery(document).ready(function ($) {
	// Constants
	const config = {
		API_KEY: idAjax_iterable_actions.iterable_api_key,
		TRANSACTIONAL_FROM_EMAIL: "info@idtechnotifications.com",
		PROMOTIONAL_FROM_EMAIL: "info@idtechonline.com",
	};
	//var existingTemplateId = $('#templateUI').data('iterableid');
	var existingTemplateId = $('#iterable_template_id').val();

	// Handle channel type change
	$(document).on('change', 'input[name=\"email_type\"]', function() {
		// Ignore changes during initialization
		if (window.wizBuilderInitializing) return;
		
		const channelType = $(this).val();
		$('.message-types').removeClass('active');
		$('.message-type-select').prop('disabled', true);
		

		if (channelType === 'promotional') {
			$('.message-types.promotional-types').addClass('active');
			$('.message-types.promotional-types .message-type-select').prop('disabled', false);
			// Set default message type if none selected
			if (!$('.message-types.promotional-types .message-type-select').val()) {
				$('.message-types.promotional-types .message-type-select').val('52634').trigger('change');
			}
		} else {
			$('.message-types.transactional-types').addClass('active');
			$('.message-types.transactional-types .message-type-select').prop('disabled', false);
			// Set default message type if none selected
			if (!$('.message-types.transactional-types .message-type-select').val()) {
				$('.message-types.transactional-types .message-type-select').val('52620').trigger('change');
			}
		}
	});

	// Handle message type selection
	$(document).on('change', '.message-type-select', function() {
		// Ignore changes during initialization
		if (window.wizBuilderInitializing) return;
		
		if (!$(this).prop('disabled')) {
			const messageTypeId = $(this).val();
			// Store the selected message type ID in a hidden input
			if (!$('#template_settings_message_type_id').length) {
				$('<input>').attr({
					type: 'hidden',
					id: 'template_settings_message_type_id',
					name: 'message_type_id',
					value: messageTypeId
				}).appendTo('#template-settings-form');
			} else {
				$('#template_settings_message_type_id').val(messageTypeId);
			}
			sessionStorage.setItem('unsavedChanges', 'true');
		}
	});

	// Main click event handler
	$("#sendToIterable").on("click", function () {
		const post_id = $(this).data("postid");
		
		// Check for unsaved changes and offer to save first
		if (sessionStorage.getItem('unsavedChanges') === 'true') {
			return Swal.fire({
				title: 'Unsaved Changes',
				html: 'You have unsaved changes. Would you like to save before syncing?',
				icon: "warning",
				showCancelButton: true,
				confirmButtonText: 'Save & Sync',
				cancelButtonText: 'Cancel',
				confirmButtonColor: '#94d401'
			}).then((result) => {
				if (result.isConfirmed) {
					// Save first, then trigger sync
					toggleOverlay(true, 'Saving...');
					save_template_data().then(() => {
						proceedWithSync(post_id);
					}).catch((error) => {
						toggleOverlay(false);
						Swal.fire({
							title: 'Save Failed',
							html: 'Could not save template: ' + error,
							icon: 'error'
						});
					});
				}
			});
		}
		
		proceedWithSync(post_id);
	});
	
	// Proceed with the sync process
	function proceedWithSync(post_id) {
		toggleOverlay(true);

		// Fetch template data from the server
		$.post(idAjax.ajaxurl, {
			action: "idemailwiz_get_template_data_for_iterable",
			security: idAjax_iterable_actions.nonce,
			post_id: post_id,
			template_id: existingTemplateId
		})
		.done(data => {
			if (data.status === "error") {
				handleAjaxError(true, data.message).then(() => {
					toggleOverlay(false);
				});
			} else {
				handleTemplateDataSuccess(data);
			}
		})
		.fail(() => handleAjaxError(true).then(() => {
			toggleOverlay(false);
		}));
	}

	// Error handling function
	function handleAjaxError(hideOverlay = true, message = "Whoops, something went wrong!") {
		return Swal.fire({
			html: message,
			icon: "error"
		}).then(() => {
			if (hideOverlay) {
				toggleOverlay(false);
			}
		});
	}

	// Success handling function for getting template data
	function handleTemplateDataSuccess(data) {
		var primaryTemplateId = data.primaryTemplateId || '';
		var syncHistory = data.syncHistory || [];
    
		// Function to format the display of a field
		function formatField(label, val, isOptional = false) {
			val = val || (isOptional ? "<em>Not set</em>" : "<em>No value set (REQUIRED)</em>");
			return `<li><strong>${label}:</strong> ${val}</li>`;
		}

		// Function to format UTM parameters
		function formatUTMs(utmParams) {
			if (!utmParams || !Array.isArray(utmParams) || utmParams.length === 0) {
				return "<li><strong>UTM Parameters:</strong> <em>None set</em></li>";
			}
			const utmList = utmParams.map(param => 
				`<li style="margin-left: 20px;">${param.key}: ${param.value || "<em>No value</em>"}</li>`
			).join('');
			return `<li><strong>UTM Parameters:</strong><ul>${utmList}</ul></li>`;
		}

		// Function to build sync target checkboxes
		function buildSyncTargets() {
			let html = '<div class="sync-targets-section">';
			html += '<label class="sync-targets-label">Sync to:</label>';
			html += '<div class="sync-targets-list">';
			
			// Check if primary ID exists but isn't in history (backwards compatibility)
			const historyIds = syncHistory.map(entry => String(entry.template_id));
			const hasPrimaryInHistory = primaryTemplateId && historyIds.includes(String(primaryTemplateId));
			
			// Add primary template ID first if it exists but isn't in history
			if (primaryTemplateId && !hasPrimaryInHistory) {
				html += `
					<label class="sync-target-item">
						<input type="checkbox" name="sync_target" value="${primaryTemplateId}" checked>
						<span class="sync-target-id">${primaryTemplateId} <span class="primary-badge">Primary</span></span>
						<a href="https://app.iterable.com/templates/editor?templateId=${primaryTemplateId}" 
							target="_blank" class="sync-target-link" title="View in Iterable">
							<i class="fa-solid fa-arrow-up-right-from-square"></i>
						</a>
					</label>
				`;
			}
			
			// Add existing history items as checkboxes
			if (syncHistory.length > 0) {
				syncHistory.forEach(entry => {
					const isPrimary = String(entry.template_id) === String(primaryTemplateId);
					const primaryLabel = isPrimary ? ' <span class="primary-badge">Primary</span>' : '';
					html += `
						<label class="sync-target-item">
							<input type="checkbox" name="sync_target" value="${entry.template_id}" checked>
							<span class="sync-target-id">${entry.template_id}${primaryLabel}</span>
							<a href="https://app.iterable.com/templates/editor?templateId=${entry.template_id}" 
								target="_blank" class="sync-target-link" title="View in Iterable">
								<i class="fa-solid fa-arrow-up-right-from-square"></i>
							</a>
						</label>
					`;
				});
			}
			
			// Add "Create new template" option (unchecked if there's existing sync targets)
			const hasExistingTargets = syncHistory.length > 0 || primaryTemplateId;
			html += `
				<label class="sync-target-item sync-target-new">
					<input type="checkbox" name="sync_target" value="new" ${!hasExistingTargets ? 'checked' : ''}>
					<span class="sync-target-id">Create new template</span>
				</label>
			`;
			
			// Add manual input for existing template ID
			html += `
				<div class="sync-target-manual">
					<label>Or enter an existing template ID:</label>
					<div class="sync-target-manual-input-wrap">
						<input type="text" id="manual_template_id" placeholder="Enter Iterable template ID">
						<button type="button" id="add_manual_template" class="wiz-button small green">Add</button>
					</div>
				</div>
			`;
			
			html += '</div></div>';
			return html;
		}

		const fieldList = `
			<ul style="text-align: left;">
				${formatField("Subject Line", data.fields.emailSubject)}
				${formatField("Preheader", data.fields.preheader, true)}
				${formatField("Type", data.fields.messageType.charAt(0).toUpperCase() + data.fields.messageType.slice(1))}
				${formatField("From", `${data.fields.fromName} <${data.fields.fromEmail}>`)}
				${formatField("Reply To", data.fields.replyToEmail)}
				${formatField("GA Campaign", data.fields.googleAnalyticsCampaignName, true)}
				${formatUTMs(data.fields.linkParams)}
			</ul>
			${buildSyncTargets()}
		`;

		Swal.fire({
			title: "Confirm Sync Details",
			html: `${fieldList}`,
			icon: false,
			showCancelButton: true,
			confirmButtonText: "Confirm & Sync!",
			customClass: {
				htmlContainer: 'sync-to-iterable-container'
			},
			didOpen: () => {
				// Handle adding manual template ID
				$(document).on('click', '#add_manual_template', function() {
					const manualId = $('#manual_template_id').val().trim();
					if (manualId) {
						// Check if already exists
						const exists = $(`.sync-targets-list input[value="${manualId}"]`).length > 0;
						if (!exists) {
							const newItem = `
								<label class="sync-target-item">
									<input type="checkbox" name="sync_target" value="${manualId}" checked>
									<span class="sync-target-id">${manualId}</span>
									<a href="https://app.iterable.com/templates/editor?templateId=${manualId}" 
										target="_blank" class="sync-target-link" title="View in Iterable">
										<i class="fa-solid fa-arrow-up-right-from-square"></i>
									</a>
								</label>
							`;
							$('.sync-target-new').before(newItem);
							$('#manual_template_id').val('');
						} else {
							// Check the existing checkbox
							$(`.sync-targets-list input[value="${manualId}"]`).prop('checked', true);
							$('#manual_template_id').val('');
						}
					}
				});
			},
			preConfirm: () => {
				// Collect checked values while modal is still open
				const selectedIds = [];
				$('.sync-targets-list input[name="sync_target"]:checked').each(function() {
					selectedIds.push($(this).val());
				});
				
				if (selectedIds.length === 0) {
					Swal.showValidationMessage('Please select at least one template to sync to.');
					return false;
				}
				
				return selectedIds;
			}
		}).then((result) => {
			if (result.isConfirmed && result.value) {
				// Process syncs sequentially with the collected IDs
				processSyncs(data.fields, result.value, data.alreadySent);
			} else {
				toggleOverlay(false);
			}
		});
	}
	
	// Process multiple syncs sequentially
	async function processSyncs(templateData, templateIds, alreadySent) {
		const successfulSyncs = [];
		const failedSyncs = [];
		const totalSyncs = templateIds.length;
		let currentSync = 0;
		
		// Show syncing overlay with initial message
		toggleOverlay(true, 'Syncing...');
		
		for (const templateId of templateIds) {
			currentSync++;
			const displayId = templateId === 'new' ? 'new template' : templateId;
			
			try {
				// Check for duplicates first (skip for 'new')
				if (templateId !== 'new') {
					updateOverlayMessage(`Syncing (${currentSync}/${totalSyncs}): Checking template ${displayId}...`);
					const dupCheck = await $.post(idAjax.ajaxurl, {
						action: "check_duplicate_itTemplateId",
						template_id: templateId,
						post_id: templateData.postId
					});
					
					if (dupCheck.status === "error") {
						failedSyncs.push({ id: templateId, error: dupCheck.message });
						continue;
					}
				}
				
				// Update message for syncing
				updateOverlayMessage(`Syncing (${currentSync}/${totalSyncs}): Pushing to ${displayId}...`);
				
				// For 'new', always use unique clientTemplateId to force Iterable to create a new template
				// (Iterable's upsert uses clientTemplateId to match existing templates)
				const isNewTemplate = templateId === 'new';
				const passedId = isNewTemplate ? null : templateId;
				
				const syncedId = await create_or_update_iterable_template(
					templateData, 
					passedId, 
					alreadySent,
					isNewTemplate // Always use unique clientTemplateId when creating new
				);
				successfulSyncs.push(syncedId);
			} catch (error) {
				failedSyncs.push({ id: templateId, error: error });
			}
		}
		
		// Update sync history with all successful syncs
		if (successfulSyncs.length > 0) {
			updateOverlayMessage('Syncing: Updating sync history...');
			const syncHistoryResponse = await updateSyncHistory(templateData.postId, successfulSyncs);
			handleMultiSyncSuccess(successfulSyncs, failedSyncs, syncHistoryResponse);
		} else {
			handleTemplateUpdateFailure(failedSyncs.map(f => f.error).join('<br>'));
		}
	}
	
	// Update sync history via AJAX
	function updateSyncHistory(postId, syncedTemplateIds) {
		return $.post(idAjax.ajaxurl, {
			action: "update_iterable_sync_history",
			security: idAjax_iterable_actions.nonce,
			post_id: postId,
			synced_template_ids: syncedTemplateIds
		});
	}
	
	// Handle success for multiple syncs
	function handleMultiSyncSuccess(successfulSyncs, failedSyncs, syncHistoryData) {
		let message = '<div class="sync-results">';
		
		if (successfulSyncs.length > 0) {
			message += '<div class="sync-results-section"><strong>Successfully synced to:</strong><ul class="sync-results-list">';
			successfulSyncs.forEach(id => {
				message += `<li><a href="https://app.iterable.com/templates/editor?templateId=${id}" target="_blank">${id} <i class="fa-solid fa-arrow-up-right-from-square"></i></a></li>`;
			});
			message += '</ul></div>';
		}
		
		if (failedSyncs.length > 0) {
			message += '<div class="sync-results-section sync-results-failed"><strong>Failed to sync:</strong><ul class="sync-results-list">';
			failedSyncs.forEach(f => {
				message += `<li>${f.id}: ${f.error}</li>`;
			});
			message += '</ul></div>';
		}
		
		message += '</div>';
		
		// Update the sync history UI without page reload
		if (syncHistoryData) {
			updateSyncHistoryUI(syncHistoryData.syncHistory, syncHistoryData.primaryTemplateId);
		}
		
		Swal.fire({
			title: "Sync Complete",
			html: message,
			icon: successfulSyncs.length > 0 ? 'success' : 'error',
			showConfirmButton: true,
			customClass: {
				htmlContainer: 'sync-results-container'
			}
		}).then(() => {
			toggleOverlay(false);
		});
	}
	
	// Update the sync history section in the UI without page reload
	function updateSyncHistoryUI(syncHistory, primaryTemplateId) {
		const $historyList = $('#sync-history-list');
		
		if (!$historyList.length) return;
		
		// Update primary template ID field
		$('#iterable_template_id').val(primaryTemplateId || '');
		
		// Update the Iterable link and Clear button next to primary ID
		const $inputParent = $('#iterable_template_id').parent();
		const $iterableLink = $inputParent.find('.iterable-link');
		const $clearBtn = $inputParent.find('.clear-primary-template');
		if (primaryTemplateId) {
			if ($iterableLink.length) {
				$iterableLink.attr('href', `https://app.iterable.com/templates/editor?templateId=${primaryTemplateId}`).show();
			} else {
				$inputParent.append(`
					<a href="https://app.iterable.com/templates/editor?templateId=${primaryTemplateId}" 
						target="_blank" class="iterable-link" title="View in Iterable">
						<i class="fa-solid fa-arrow-up-right-from-square"></i>
					</a>
				`);
			}
			if (!$clearBtn.length) {
				$inputParent.append(`
					<button type="button" class="clear-primary-template" title="Clear primary template ID">
						<i class="fa-solid fa-xmark"></i> Clear
					</button>
				`);
			} else {
				$clearBtn.show();
			}
		} else {
			$iterableLink.hide();
			$clearBtn.hide();
		}
		
		// Clear existing history
		$historyList.empty();
		
		if (!syncHistory || syncHistory.length === 0) {
			$historyList.html('<div class="sync-history-empty">No sync history yet</div>');
			return;
		}
		
		// Sort by synced_at descending (most recent first)
		syncHistory.sort((a, b) => new Date(b.synced_at) - new Date(a.synced_at));
		
		// Build new history items
		syncHistory.forEach(entry => {
			const templateId = entry.template_id;
			const isPrimary = (String(templateId) === String(primaryTemplateId));
			const syncedAt = entry.synced_at ? formatSyncDate(entry.synced_at) : 'Unknown';
			
			const itemHtml = `
				<div class="sync-history-item${isPrimary ? ' is-primary' : ''}" data-template-id="${templateId}">
					<span class="sync-history-id">
						<a href="https://app.iterable.com/templates/editor?templateId=${templateId}" 
							target="_blank" title="View in Iterable">
							${templateId}
							<i class="fa-solid fa-arrow-up-right-from-square"></i>
						</a>
						${isPrimary ? '<span class="primary-badge">Primary</span>' : ''}
					</span>
					<span class="sync-history-date">${syncedAt}</span>
					${!isPrimary ? `<button type="button" class="make-primary-template" data-template-id="${templateId}" title="Make primary">
						<i class="fa-solid fa-star"></i>
					</button>` : ''}
					<button type="button" class="remove-from-history" data-template-id="${templateId}" title="Remove from history">
						<i class="fa-solid fa-times"></i>
					</button>
				</div>
			`;
			$historyList.append(itemHtml);
		});
	}
	
	// Format sync date for display
	function formatSyncDate(isoDate) {
		try {
			const date = new Date(isoDate);
			return date.toLocaleString('en-US', {
				month: 'short',
				day: 'numeric',
				year: 'numeric',
				hour: 'numeric',
				minute: '2-digit',
				hour12: true
			});
		} catch (e) {
			return 'Unknown';
		}
	}


	// Function to handle success of template sync
	function handleTemplateUpdateSuccess(templateId) {
		$('#iterable_template_id').val(templateId).change();
		//$('#iterable_template_id').attr('data-auto-publish', 'true');
		Swal.fire({
			title: "Sync complete",
			html: `Sync was successful!<br/><a style="text-decoration:underline;" href="https://app.iterable.com/templates/editor?templateId=${templateId}" target="_blank">Click here to go to Iterable template</a>.`,
			showConfirmButton: true,
		}).then(() => {
			
			toggleOverlay(false);

			save_template_data();

			// Reload the page to reflect changes (if necessary)
			//var currentUrl = window.location.href;
			//window.location.href = currentUrl;
		});
	}

	// Function to handle failure of template update
	function handleTemplateUpdateFailure(error) {
		Swal.fire({
			title: "Sync failed!",
			html: error,
			icon: "error",
		}).then(() => {
			var currentUrl = window.location.href;
			window.location.href = currentUrl;
		});
	}

	// Function to create or update Iterable template
	// useUniqueClientId: when true, appends timestamp to clientTemplateId to force Iterable to create a new template
	function create_or_update_iterable_template(templateData, existingTemplateId = null, alreadySent = false, useUniqueClientId = false) {
		
		return new Promise((resolve, reject) => {
			// Check early if we're trying to update an already sent template and bail if so
			if (existingTemplateId && alreadySent === true) {
				reject("You cannot update an Iterable template attached to an already sent campaign!");
				return;
			}

		const {
			postId,
			createdBy,
			templateName,
			messageType,
			emailSubject,
			preheader,
			fromName,
			replyToEmail,
			plainText,
			googleAnalyticsCampaignName,
			linkParams,
			messageTypeId
		} = templateData;

			// Ensure we have a valid message type ID
			if (!messageTypeId) {
				reject("No message type ID selected. Please select a message type before syncing.");
				return;
			}

			const fromSender = messageType == "transactional" ? config.TRANSACTIONAL_FROM_EMAIL : config.PROMOTIONAL_FROM_EMAIL;

			// Set up parameters for get_template_part_do_callback
			var params = {
				partType: 'fullTemplate',
				isEditor: false,
				templateId: idAjax_builder_functions.currentPostId,
				security: idAjax_template_editor.nonce,
			};

			// Call get_template_part_do_callback to get the HTML
			get_template_part_do_callback(params, function(error, data) {
				if (error) {
					reject("Failed to fetch template HTML: " + error.message);
					return;
				}

				let templateHtml = data.html;

				// Replace curly quotes with straight quotes
				templateHtml = templateHtml.replace(/[\u201C\u201D]/g, '"');
            
				// Decode HTML entities
				templateHtml = decodeHtml(templateHtml);
            
			// Use unique clientTemplateId when creating additional templates
			// This prevents Iterable's upsert from finding/updating the existing template
			const clientId = useUniqueClientId ? `${postId}_${Date.now()}` : postId;
			
			const apiData = {
				name: templateName,
				fromName: fromName,
				fromEmail: fromSender,
				replyToEmail: replyToEmail,
				subject: emailSubject,
				preheaderText: preheader,
				clientTemplateId: clientId,
				creatorUserId: createdBy,
				messageTypeId,
				html: templateHtml,
				plainText: plainText,
				googleAnalyticsCampaignName: googleAnalyticsCampaignName,
				linkParams: linkParams,
			};

				if (existingTemplateId) {
					apiData.templateId = parseInt(existingTemplateId);
				}

				const apiUrl = existingTemplateId ? 
					"https://api.iterable.com/api/templates/email/update" : 
					"https://api.iterable.com/api/templates/email/upsert";

				$.ajax({
					type: "POST",
					url: apiUrl,
					data: JSON.stringify(apiData),
					contentType: "text/plain",
					beforeSend: function (xhr) {
						xhr.setRequestHeader("Api-Key", config.API_KEY);
					},
				})
				.done(function(response) {
					if (!existingTemplateId) {
						existingTemplateId = response.templateId || extractID(response.msg);
					}

					updateTemplateAfterSync(existingTemplateId, postId);

					resolve(existingTemplateId);
				})
				.fail(function(jqXHR) {
					var errorResponse = JSON.parse(jqXHR.responseText);

					// Construct a detailed error message
					var errorMessage = "Failed to update or create Iterable template. ";
					if (errorResponse && errorResponse.msg) {
						errorMessage += "Error: " + errorResponse.msg;
					} else {
						errorMessage += "An unknown error occurred.";
					}

					// Pass the detailed error message to the reject function
					reject(errorMessage);
				});
			});
		});
	}

	// Function to decode HTML entities
	function decodeHtml(html) {
	  var txt = document.createElement("textarea");
	  txt.innerHTML = html;
	  return txt.value;
	}


	// Function to update template after sync
	function updateTemplateAfterSync(templateId, postId = null, templateName = null) {
		return $.post(idAjax.ajaxurl, {
			action: "update_template_after_sync",
			post_id: postId,
			template_id: templateId,
			template_name: templateName,
			security: idAjax_iterable_actions.nonce
		});
	}

	// Function to extract ID from string
	function extractID(s) {
		const match = s.match(/\b(\d+)$/);
		return match ? parseInt(match[1], 10) : null;
	}

	// Function to update the template name
	function updateIterableTemplateName(templateId, newTemplateName) {
	  return new Promise((resolve, reject) => {
		const apiData = {
		  templateId: parseInt(templateId),
		  name: newTemplateName
		};

		$.ajax({
		  type: "POST",
		  url: "https://api.iterable.com/api/templates/email/update",
		  data: JSON.stringify(apiData),
		  contentType: "text/plain",
		  beforeSend: function (xhr) {
			xhr.setRequestHeader("Api-Key", config.API_KEY);
		  },
		})
		.done(function(response) {
		
		  resolve(response);
		})
		.fail(function(jqXHR) {
		  var errorResponse = JSON.parse(jqXHR.responseText);
		  var errorMessage = "Failed to update template name. ";
      
		  if (errorResponse && errorResponse.msg) {
			errorMessage += "Error: " + errorResponse.msg;
		  } else {
			errorMessage += "An unknown error occurred.";
		  }

		  reject(errorMessage);
		});
	  });
	}

	// Click event handler for removing template from sync history
	$(document).on('click', '.remove-from-history', function(e) {
		e.preventDefault();
		const $item = $(this).closest('.sync-history-item');
		const templateId = $(this).data('template-id');
		const postId = $('#sync-history-list').data('post-id');
		
		Swal.fire({
			title: 'Remove from history?',
			text: `Remove template ${templateId} from sync history?`,
			icon: 'warning',
			showCancelButton: true,
			confirmButtonText: 'Remove',
			confirmButtonColor: '#d33'
		}).then((result) => {
			if (result.isConfirmed) {
				$.post(idAjax.ajaxurl, {
					action: 'remove_from_iterable_sync_history',
					security: idAjax_iterable_actions.nonce,
					post_id: postId,
					template_id: templateId
				}).done(function(response) {
					if (response.status === 'success') {
						$item.fadeOut(300, function() {
							$(this).remove();
							// Update primary field if needed
							$('#iterable_template_id').val(response.primaryTemplateId || '');
							// Check if list is empty
							if ($('#sync-history-list .sync-history-item').length === 0) {
								$('#sync-history-list').html('<div class="sync-history-empty">No sync history yet</div>');
							}
						});
					} else {
						Swal.fire('Error', response.message, 'error');
					}
				}).fail(function() {
					Swal.fire('Error', 'Failed to remove template from history', 'error');
				});
			}
		});
	});

	// Click event handler for "Make Primary" button in sync history
	$(document).on('click', '.make-primary-template', function(e) {
		e.preventDefault();
		const templateId = $(this).data('template-id');
		const postId = $('#sync-history-list').data('post-id');

		Swal.fire({
			title: 'Make primary?',
			text: `Set template ${templateId} as the primary template ID?`,
			icon: 'question',
			showCancelButton: true,
			confirmButtonText: 'Make Primary',
			confirmButtonColor: '#28a745'
		}).then((result) => {
			if (result.isConfirmed) {
				$.post(idAjax.ajaxurl, {
					action: 'set_primary_iterable_template',
					security: idAjax_iterable_actions.nonce,
					post_id: postId,
					template_id: templateId
				}).done(function(response) {
					if (response.status === 'success') {
						updateSyncHistoryUI(response.syncHistory, response.primaryTemplateId);
					} else {
						Swal.fire('Error', response.message, 'error');
					}
				}).fail(function() {
					Swal.fire('Error', 'Failed to update primary template', 'error');
				});
			}
		});
	});

	// Click event handler for "Clear Primary" button next to primary template ID
	$(document).on('click', '.clear-primary-template', function(e) {
		e.preventDefault();
		const postId = $('#sync-history-list').data('post-id');

		Swal.fire({
			title: 'Clear primary?',
			text: 'Clear the primary template ID? It will be re-assigned on the next sync.',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonText: 'Clear',
			confirmButtonColor: '#d33'
		}).then((result) => {
			if (result.isConfirmed) {
				$.post(idAjax.ajaxurl, {
					action: 'clear_primary_iterable_template',
					security: idAjax_iterable_actions.nonce,
					post_id: postId
				}).done(function(response) {
					if (response.status === 'success') {
						updateSyncHistoryUI(response.syncHistory, response.primaryTemplateId);
					} else {
						Swal.fire('Error', response.message, 'error');
					}
				}).fail(function() {
					Swal.fire('Error', 'Failed to clear primary template', 'error');
				});
			}
		});
	});

	// Click event handler for the "edit name" link
	$(document).on('click', '.editTemplateName', function(e) {
	  e.preventDefault();
	  const templateId = $(this).data('templateid');
	  const currentName = $(this).data('currentname');

	  Swal.fire({
		title: 'Update Template Name',
		icon: 'info',
		input: 'text',
		inputValue: currentName,
		inputPlaceholder: 'Enter new template name',
		text: 'This will rename the template in Iterable and The Wizard',
		showCancelButton: true,
		confirmButtonText: 'Update',
		showLoaderOnConfirm: true,
		preConfirm: (newTemplateName) => {
		  return updateIterableTemplateName(templateId, newTemplateName)
			.then(response => {
			  updateTemplateAfterSync(templateId, null, newTemplateName);
			  Swal.fire({
				title: 'Template Name Updated',
				text: 'The template name has been successfully updated.',
				icon: 'success'
			  }).then(() => {
				location.reload();
			  });
			})
			.catch(error => {
			  Swal.showValidationMessage(error);
			});
		},
		allowOutsideClick: () => !Swal.isLoading()
	  });
	});

});
