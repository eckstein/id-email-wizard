jQuery(document).ready(function ($) {
	// Constants
	const config = {
		TRANSACTIONAL_MESSAGE_TYPE_ID: 52620,
		PROMOTIONAL_MESSAGE_TYPE_ID: 52634,
		TRANSACTIONAL_FROM_EMAIL: "info@idtechnotifications.com",
		PROMOTIONAL_FROM_EMAIL: "info@idtechonline.com",
		API_KEY: '282da5d7dd77450eae45bdc715ead2a4',
	};
	var existingTemplateId = $('#templateUI').data('iterableid');
	// Main click event handler
	$("#sendToIterable").on("click", function () {
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
				handleAjaxError(false, data.message);
			} else {
				handleTemplateDataSuccess(data);
			}
		})
		.fail(() => handleAjaxError(true));
	});

	// Error handling function
	function handleAjaxError(hideOverlay = true, message = "Whoops, something went wrong!") {
		if (hideOverlay) {
			toggleOverlay(false);
		}
		Swal.fire(message, { icon: "error" });
	}

	// Success handling function for getting template data
	function handleTemplateDataSuccess(data) {
		var existingTemplateId = $('#templateUI').data('iterableid');
		const fieldsToList = Object.entries(data.fields)
			.filter(([key]) => key !== "postId")
			.map(([key, value]) => {
				const val = value || "<em>No value set</em>";
				return `<li><strong>${key}</strong>: ${val}</li>`;
			})
			.join("");
		//console.log(data);
		const fieldList = `<ul style="text-align: left;">${fieldsToList}</ul>`;
		var existingTemplateMessage = 'Enter an existing template ID or leave blank to create a new base template.';
		if (existingTemplateId) {
			console.log('existing template');
			if (data.alreadySent == true) {
				console.log('already sent');
				var existingTemplateMessage = `The campaign attached to template <a target="_blank" href="https://app.iterable.com/templates/editor?templateId=${existingTemplateId}">${existingTemplateId}</a> has already been sent! Click OK below to create a new template in Iterable.`
				existingTemplateId = '';
			} else {
				var existingTemplateMessage = `Currently synced to template <a target="_blank" href="https://app.iterable.com/templates/editor?templateId=${existingTemplateId}">${existingTemplateId}</a>.`;
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
			inputPlaceholder: 'Leave blank to create new base template',
		}).then((result) => {
			if (result.isConfirmed) {
				create_or_update_iterable_template(data.fields, result.value, data.alreadySent)
					.then(handleTemplateUpdateSuccess)
					.catch(handleTemplateUpdateFailure);
			} else {
				toggleOverlay(false);
			}
		});
	}

	// Function to handle success of template sync
	function handleTemplateUpdateSuccess(templateId) {
		Swal.fire({
			title: "Sync complete",
			html: `Sync was successful!<br/><a style="text-decoration:underline;" href="https://app.iterable.com/templates/editor?templateId=${templateId}" target="_blank">Click here to go to Iterable template</a>.`,
			showConfirmButton: true,
		}).then(() => {
			var currentUrl = window.location.href;
			window.location.href = currentUrl;
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
			if (existingTemplateId && alreadySent) {
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
				utmTerm,
			} = templateData;

			const messageTypeId = messageType === "Transactional" ? config.TRANSACTIONAL_MESSAGE_TYPE_ID : config.PROMOTIONAL_MESSAGE_TYPE_ID;
			const fromSender = messageType === "Transactional" ? config.TRANSACTIONAL_FROM_EMAIL : config.PROMOTIONAL_FROM_EMAIL;

			const additionalData = {
				template_id: postId
			};

			idemailwiz_do_ajax('idemailwiz_generate_template_html', idAjax_template_editor.nonce, additionalData, getHTMLsuccessCallback, getHTMLerrorCallback, 'html');

			function getHTMLsuccessCallback(data) {
				// Decode HTML entities
				let templateHtml = $('<div/>').html(data).text();

				// Replace curly quotes with straight quotes
				templateHtml = templateHtml.replace(/[\u201C\u201D]/g, '"');

				const apiData = {
					name: templateName,
					fromName: fromName,
					fromEmail: fromSender,
					subject: emailSubject,
					preheaderText: preheader,
					clientTemplateId: `${postId}_${Date.now()}`,
					creatorUserId: createdBy,
					messageTypeId,
					html: templateHtml
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

					updateTemplateAfterSync(postId, existingTemplateId);

					resolve(existingTemplateId);
				})
				.fail(function() {
					reject("Failed to update or create Iterable template");
				});
			}

			function getHTMLerrorCallback() {
				reject("Failed to fetch template HTML");
			}
		});
	}


	// Function to update template after sync
	function updateTemplateAfterSync(postId, templateId) {
		return $.post(idAjax.ajaxurl, {
			action: "update_template_after_sync",
			post_id: postId,
			template_id: templateId,
			security: idAjax_iterable_actions.nonce
		});
	}

	// Function to extract ID from string
	function extractID(s) {
		const match = s.match(/\b(\d+)$/);
		return match ? parseInt(match[1], 10) : null;
	}


});
