jQuery(document).ready(function ($) {
	// Constants
	const config = {
		TRANSACTIONAL_MESSAGE_TYPE_ID: 52620,
		PROMOTIONAL_MESSAGE_TYPE_ID: 52634,
		TRANSACTIONAL_FROM_EMAIL: "info@idtechnotifications.com",
		PROMOTIONAL_FROM_EMAIL: "info@idtechonline.com",
		API_KEY: idAjax_iterable_actions.iterable_api_key,
	};
	//var existingTemplateId = $('#templateUI').data('iterableid');
	var existingTemplateId = $('#iterable_template_id').val();

	// Main click event handler
	$("#sendToIterable").on("click", function () {
		// Directly check sessionStorage here
		if (sessionStorage.getItem('unsavedChanges') === 'true') {
			return Swal.fire({
				html: 'Save your changes before syncing!',
				icon: "error"
			}).then(() => {
				toggleOverlay(false);
			});
		}
		const post_id = $(this).data("postid");
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
	});

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
		//console.log(data);
		var existingTemplateId = $('#iterable_template_id').val();
		
		const fieldsToList = Object.entries(data.fields)
			.filter(([key]) => key !== "postId")
			.map(([key, value]) => {
				const val = value || "<em>No value set</em>";
				return `<li><strong>${key}</strong>: ${val}</li>`;
			})
			.join("");

		const fieldList = `<ul style="text-align: left;">${fieldsToList}</ul>`;
		var existingTemplateMessage = 'Enter an existing template ID or leave blank to create a new base template.';
		
		if (existingTemplateId) {
			if (data.alreadySent === true) {
				
				existingTemplateMessage = `The campaign attached to template <a target="_blank" href="https://app.iterable.com/templates/editor?templateId=${existingTemplateId}">${existingTemplateId}</a> has already been sent! Click OK below to create a new template in Iterable.`;
				existingTemplateId = '';
			} else {
				existingTemplateMessage = `Currently synced to template <a target="_blank" href="https://app.iterable.com/templates/editor?templateId=${existingTemplateId}">${existingTemplateId}</a>.`;
			}
		}

		Swal.fire({
			title: "Confirm Sync Details",
			html: `${fieldList}<br/><em>${existingTemplateMessage}</em>`,
			icon: "warning",
			showCancelButton: true,
			confirmButtonText: "Confirm & Sync!",
			input: 'text',
			inputValue: existingTemplateId,
			inputPlaceholder: 'Leave blank to create new base template'
		}).then((result) => {
			existingTemplateId = result.value;
			if (!existingTemplateId) {
				existingTemplateId = 'new';
			}
			if (result.isConfirmed) {
				$.post(idAjax.ajaxurl, {
					action: "check_duplicate_itTemplateId",
					template_id: existingTemplateId,
					post_id: data.fields.postId
				})
				.done(dupCheckData => {
					if (dupCheckData.status == "error") {
						handleAjaxError(false, dupCheckData.message).then(() => {
							toggleOverlay(false);
						});
					} else {
						console.log(data.fields);
						create_or_update_iterable_template(data.fields, result.value, data.alreadySent)
							.then(handleTemplateUpdateSuccess)
							.catch(handleTemplateUpdateFailure);
					}
				});
			} else {
				toggleOverlay(false);
			}
		});
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
	function create_or_update_iterable_template(templateData, existingTemplateId = null, alreadySent = false) {
		
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
			} = templateData;

			const messageTypeId = messageType == "transactional" ? config.TRANSACTIONAL_MESSAGE_TYPE_ID : config.PROMOTIONAL_MESSAGE_TYPE_ID;
			const fromSender = messageType == "transactional" ? config.TRANSACTIONAL_FROM_EMAIL : config.PROMOTIONAL_FROM_EMAIL;

			var additionalData = {
				action: "generate_template_html_from_ajax",
				template_id: idAjax_template_editor.currentPost.ID,
				session_data: get_template_from_session(),
				//security: idAjax_template_editor.nonce
			};

			

			//idemailwiz_do_ajax('generate_template_html_from_ajax', idAjax_template_editor.nonce, additionalData, getHTMLsuccessCallback, getHTMLerrorCallback);

			
			jQuery.ajax({
				type: "POST",
				url: idAjax.ajaxurl,
				data: additionalData,
				//dataType: "json",
				success: getHTMLsuccessCallback,
				error: getHTMLerrorCallback
			});
			
			function getHTMLsuccessCallback(response) {

				let templateHtml = $('<div/>').html(response).text();

				 // Replace curly quotes with straight quotes
				  templateHtml = templateHtml.replace(/[\u201C\u201D]/g, '"');
				  
				// Replace curly quotes with straight quotes
				templateHtml = decodeHtml(templateHtml);

				const apiData = {
					name: templateName,
					fromName: fromName,
					fromEmail: fromSender,
					subject: emailSubject,
					preheaderText: preheader,
					clientTemplateId: postId,
					creatorUserId: createdBy,
					messageTypeId,
					html: templateHtml,
					googleAnalyticsCampaignName: '{{campaignId}}',
					linkParams: [
						{
							key: 'utm_content',
							value: '{{templateId}}'
						},
						
					]
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
			}

			function getHTMLerrorCallback() {
				reject("Failed to fetch template HTML");
			}
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
