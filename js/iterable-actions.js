jQuery(document).ready(function ($) {
	// Constants
	const config = {
	  TRANSACTIONAL_MESSAGE_TYPE_ID: 52620,
	  PROMOTIONAL_MESSAGE_TYPE_ID: 52634,
	  TRANSACTIONAL_FROM_EMAIL: "info@idtechnotifications.com",
	  PROMOTIONAL_FROM_EMAIL: "info@idtechonline.com",
	  API_KEY : '282da5d7dd77450eae45bdc715ead2a4',
	};
  
	// Function to handle click event
	$("#sendToIterable").on("click", function () {
	  const post_id = $(this).data("postid");
	  toggleOverlay();
  
	  $.post(idAjax.ajaxurl, {
		action: "get_template_data_for_iterable",
		security: idAjax_iterable_actions.nonce,
		post_id: post_id
	  })
	  .done(handleTemplateDataSuccess)
	  .fail(() => handleAjaxError());
	});
  
	// Function to show and hide overlays and spinners
	const toggleOverlay = (show = true) => {
	  $("#iDoverlay")[show ? "show" : "hide"]();
	  $("#iDspinner")[show ? "show" : "hide"]();
	};
  
	// Error handling function
	const handleAjaxError = (hideOverlay = true) => {
	  if (hideOverlay) {
		toggleOverlay(false);
	  }
	  Swal.fire("Whoops, something went wrong!", {
		icon: "error",
	  });
	};
  
	// Create/Update Template
	const create_or_update_iterable_template = (templateData, templateId) => {
		const {
			postId,
			createdBy,
			templateName,
			messageType,
			emailSubject = '', 
			preheader = '',
			fromName = '',
			utmTerm = ''
		} = templateData;
	
		const messageTypeId = messageType === "Transactional" ? config.TRANSACTIONAL_MESSAGE_TYPE_ID : config.PROMOTIONAL_MESSAGE_TYPE_ID;
		const fromSender = messageType === "Transactional" ? config.TRANSACTIONAL_FROM_EMAIL : config.PROMOTIONAL_FROM_EMAIL;
	
		let templateHtml = $("#generatedCode").text();
		templateHtml = templateHtml.replace(/[\u201C\u201D]/g, '"');
		templateHtml = templateHtml.replace(">", ">");
		templateHtml = templateHtml.replace("<", "<");
	
		const data = {
			name: templateName,
			fromName: fromName,
			fromEmail: fromSender,
			replyToEmail: "info@idtechonline.com",
			subject: emailSubject,
			preheaderText: preheader,
			//The clientTemplateId is appended with a unique timestamp of the action for now.
			//Later, if controlling more than 1 template in Iterable is needed, this should no longer be unique
			//on a per template basis and should just be the post_id
			clientTemplateId: postId + '_' + (new Date).getTime(),
			googleAnalyticsCampaignName: "{{campaignId}}",
			creatorUserId: createdBy,
			linkParams: [{
				key: "utm_term",
				value: utmTerm,
			},
			{
				key: "utm_content",
				value: "{{templateId}}",
			},
			],
			messageTypeId,
			html: templateHtml,
			security: idAjax_iterable_actions.nonce
		};
		//Add the template ID to the data if it was passed
		if (templateId) {
			data.templateId = parseInt(templateId);
		}

		const apiUrl = templateId
		? `https://api.iterable.com/api/templates/email/update`
		: `https://api.iterable.com/api/templates/email/upsert`;

		if (templateId) {
			return new Promise(function (resolve, reject) {
				$.ajax({
					type: "GET",
					url: `https://api.iterable.com/api/templates/email/get?templateId=${templateId}`,
					beforeSend: function (xhr) {
						xhr.setRequestHeader("Api-Key", config.API_KEY);
					},
					success: function (response) {
						//console.log("GET template response:", response);  // log the response from the GET request
						if (response.metadata) { // Check if metadata property exists, indicating a successful response.
							if (response.metadata.campaignId) { // Check if the metadata object contains a campaignId property.
								console.log('Campaign exists');
								Swal.fire({
									title: "Warning",
									text: "This template is already attached to a campaign. Please confirm you'd like to update it.",
									icon: "warning",
									showCancelButton: true,
									confirmButtonText: "Yes, continue",
									cancelButtonText: "No, go back",
								}).then((result) => {
									if (result.isConfirmed) {
										toggleOverlay(false);
										Swal.fire({
											title: "Syncing with Iterable...",
											html: "Please wait a few moments...",
											showCancelButton: false,
											showConfirmButton: false,
											allowOutsideClick: false,
											didOpen: () => {
												Swal.showLoading();
											},
										});
										performUpdate(resolve, reject, templateId);
									} else {
										reject("User cancelled the update.");
									}
								});
							} else {
								performUpdate(resolve, reject, templateId);
							}
						} else if (response.msg) { // Check if msg property exists, indicating an error response.
							reject(`Template with ID ${templateId} does not exist.`);
						}
					},
					error: function (xhr, textStatus, error) {
						console.log("GET template error:", xhr, textStatus, error);  // log the error from the GET request
						reject(`Error checking for template with ID ${templateId}.`);
					},
				});
			});
		} else {
			return new Promise((resolve, reject) => performUpdate(resolve, reject, templateId));
		}
			
		
		function performUpdate(resolve, reject, templateId=false) {
			console.log(JSON.stringify(data));
			$.ajax({
				type: "POST",
				url: apiUrl,
				data: JSON.stringify(data),
				contentType: "text/plain",
				dataType: "json",
				beforeSend: function (xhr) {
					xhr.setRequestHeader("Api-Key", config.API_KEY);
				},
				success: function (result) {
					console.log("POST template success:", result);  // log the result of the POST request
					if (!templateId) {
					function extractID(s) {
						var match = s.match(/\b(\d+)$/);
						if (match) {
							return parseInt(match[1], 10);
						}
						return null;
						}
						templateId = extractID(result.msg);
					}
					$.ajax({
						type: "POST",
						url: idAjax.ajaxurl,
						data: {
							action: "update_template_after_sync",
							post_id: postId,
							template_id: templateId,
							security: idAjax_iterable_actions.nonce
						},
						success: function (afterSync) {
							if (afterSync.status === 'success') {
								resolve(templateId);
							}
						},
						error: function (xhr, status, error) {
							reject(false);
						},
					});
				},
				error: function (xhr, textStatus, error) {
					console.log("POST template error:", xhr, textStatus, error);  // log the error from the POST request
					reject(false);
				},
			});
		}
	};
	
  
  
	// Success handling function for the get_template_data_for_iterable AJAX request
	const handleTemplateDataSuccess = (response) => {
		if (response.status === "error") {
			Swal.fire({
				title: "Template can't be synced!",
				html: response.message,
				icon: "error",
			}).then(() => {
				toggleOverlay(false);
			});
		} else {
			const fieldsToList = Object.entries(response.fields)
				.filter(([key]) => key !== "postId")
				.map(([key, value]) => {
					const val = value || "<em>No value set</em>";
					return `<li><strong>${key}</strong>: ${val}</li>`;
				})
				.join("");
	
			const fieldList = `<ul style="text-align: left;">${fieldsToList}</ul>`;
	
			let existingTemplateId = $('#templateUI').data('iterableid');
			console.log(existingTemplateId);
			let existingTemplateMessage = existingTemplateId ? `<span style="font-size:.8em;"><strong>Currently synced to template <a target="_blank" href="https://app.iterable.com/templates/editor?templateId=${existingTemplateId}">${existingTemplateId}</a>.</strong><br/>Change the ID to sync this to a different template or remove the ID to create a new base template.</span>` : 'Enter an existing template ID to sync this data to or leave blank to create a new base template. <strong>Important:</strong> <span style="color:red">Existing template data in Iterable will be irreversibly overwritten.</span>';
	
			Swal.fire({
				title: "Confirm Sync Details",
				html: `${fieldList}<br/><em>${existingTemplateMessage}</em>`,
				icon: "warning",
				showCancelButton: true,
				cancelButtonText: "Go Back",
				confirmButtonText: "Confirm & Sync!",
				allowOutsideClick: true,
				input: 'text',
				inputValue: existingTemplateId,
				inputPlaceholder: 'Leave blank to create new base template',
				preConfirm: () => new Promise((resolve) => resolve()),
			}).then((result) => {
				if (result.isConfirmed) {
					create_or_update_iterable_template(response.fields, result.value)
						.then((makeTemplate) => {
							Swal.fire({
								title: "Sync complete",
								html: `Sync was successful!<br/><a style="text-decoration:underline;" href="https://app.iterable.com/templates/editor?templateId=${makeTemplate}" target="_blank">Click here to go to Iterable template</a>.`,
								showConfirmButton: true,
								allowOutsideClick: true,
							}).then(() => {
								toggleOverlay(true);
								const currentUrl = window.location.href;
								window.location.href = currentUrl;
							});
						})
						.catch((error) => {
							Swal.fire({
								title: "Sync failed!",
								html: error,
								icon: "error",
								showConfirmButton: true,
								allowOutsideClick: true,
							});
						});
						
					toggleOverlay(false);
					Swal.fire({
						title: "Syncing with Iterable...",
						html: "Please wait a few moments...",
						showCancelButton: false,
						showConfirmButton: false,
						allowOutsideClick: false,
						didOpen: () => {
							Swal.showLoading();
						},
					});
				} else {
					toggleOverlay(false);
				}
			});
			
		}
	};
	
  
})