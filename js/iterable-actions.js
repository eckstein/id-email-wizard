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
		console.log(data);
		var existingTemplateId = $('#templateUI').data('iterableid');
		
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
			if (result.isConfirmed) {
				$.post(idAjax.ajaxurl, {
					action: "check_duplicate_itTemplateId",
					template_id: result.value,
					post_id: data.fields.postId
				})
				.done(dupCheckData => {
					if (dupCheckData.status == "error") {
						handleAjaxError(false, dupCheckData.message).then(() => {
							toggleOverlay(false);
						});
					} else {
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
				utmTerm,
			} = templateData;

			const messageTypeId = messageType == "Transactional" ? config.TRANSACTIONAL_MESSAGE_TYPE_ID : config.PROMOTIONAL_MESSAGE_TYPE_ID;
			const fromSender = messageType == "Transactional" ? config.TRANSACTIONAL_FROM_EMAIL : config.PROMOTIONAL_FROM_EMAIL;

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
					clientTemplateId: postId,
					creatorUserId: createdBy,
					messageTypeId,
					html: templateHtml,
					linkParams: [
						{
							key: 'utm_term',
							value: utmTerm
						},
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


	// Function to send a campaign using Iterable API
	function send_iterable_campaign(campaignId, email) {
		return new Promise((resolve, reject) => {
			const dataFields = generateRandomDataFields();

			const apiData = {
				campaignId: campaignId,
				recipientEmail: email,
				dataFields: dataFields,
				sendAt: formatUTCDate(new Date()), // Ensure this function is defined and formats the date correctly
				allowRepeatMarketingSends: true,
				metadata: {} // Add any required metadata here
			};

			$.ajax({
				type: "POST",
				url: "https://api.iterable.com/api/email/target",
				data: JSON.stringify(apiData),
				contentType: "application/json",
				beforeSend: function (xhr) {
					xhr.setRequestHeader("Api-Key", config.API_KEY);
				},
			})
			.done(function(response) {
				// Check response status and resolve accordingly
				if (response && response.success) {
					resolve(response); // Successful response
				} else {
					reject("Failed to send campaign: " + (response.message || "Unknown error"));
				}
			})
			.fail(function(jqXHR, textStatus, errorThrown) {
				// Provide more detailed error information
				let errorMessage = "Failed to send campaign through Iterable. ";
				errorMessage += "Status: " + textStatus + ". ";
				errorMessage += "Error: " + errorThrown;
				if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.msg) {
					errorMessage += " Details: " + jqXHR.responseJSON.msg;
				}

				reject(errorMessage);
			});
		});
	}


	function formatUTCDate(date) {
		const pad = (num) => num.toString().padStart(2, '0');

		let year = date.getUTCFullYear();
		let month = pad(date.getUTCMonth() + 1); // getUTCMonth() returns 0-11
		let day = pad(date.getUTCDate());
		let hours = pad(date.getUTCHours());
		let minutes = pad(date.getUTCMinutes());
		let seconds = pad(date.getUTCSeconds());

		return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
	}

	// Function to generate random data fields
	// Function to generate random data fields with dummy data
	function generateRandomDataFields() {
		const currentYear = new Date().getFullYear();
		const dataPoints = [];

		// Names and gender codes for randomization
		const namesAndGenders = [
			{ name: "James", gender: 99000 },
			{ name: "Olivia", gender: 99001 },
			{ name: "Alex", gender: 99002 },
			{ name: "Ethan", gender: 99000 },
			{ name: "Sophia", gender: 99001 },
			{ name: "Taylor", gender: 99002 },
			{ name: "Mia", gender: 99001 },
			{ name: "Noah", gender: 99000 },
			{ name: "Jordan", gender: 99002 },
			{ name: "Emma", gender: 99001 }
		];

		for (let i = 0; i < 10; i++) {
			// Randomly select a name and gender
			const randomIndex = Math.floor(Math.random() * namesAndGenders.length);
			const { name, gender } = namesAndGenders[randomIndex];

			// Generate other random data
			const birthYear = Math.floor(Math.random() * (18 - 7 + 1)) + currentYear - 18;
			const birthMonth = Math.floor(Math.random() * 12) + 1;
			const birthDay = Math.floor(Math.random() * 28) + 1; // Simplified to 28 days to handle all months
			const studentDOB = `${birthYear}-${String(birthMonth).padStart(2, '0')}-${String(birthDay).padStart(2, '0')}`;

			dataPoints.push({
				"L10Level": Math.floor(Math.random() * 10),
				"StudentAccountNumber": "4ZV" + Math.random().toString(36).substr(2, 7).toUpperCase(),
				"StudentBirthDay": birthDay,
				"StudentBirthMonth": birthMonth,
				"StudentBirthYear": birthYear,
				"StudentDOB": studentDOB,
				"StudentFirstName": name,
				"StudentGender": gender,
				"StudentLastName": "Smith", // Just use Smith since last name is barely ever used
				"UnscheduledLessons": Math.floor(Math.random() * 10)
			});
		}

		return { "StudentArray": dataPoints };
	}

	$(document).on('click', '.sendCampaignProof', function() {
		console.log('clicked send email');
		var $button = $(this); // Cache the button
		var campaignId = $button.data('campaignid'); // Get the campaignId from the button
		var currentUser = idAjax_iterable_actions.current_user; // Get the current user

		// Check if currentUser and email are available
		if (currentUser && currentUser.data && currentUser.data.user_email) {
			var email = currentUser.data.user_email;

			// SweetAlert2 confirmation
			swal.fire({
				title: "Are you sure?",
				text: "This will send a proof to " + email,
				icon: "warning",
				showCancelButton: true,
				confirmButtonColor: '#3085d6',
				cancelButtonColor: '#d33',
				confirmButtonText: 'Yes, send it!'
			}).then((result) => {
				if (result.value) {
					// Change button state
					$button.find('i').removeClass('fa-share-from-square').addClass('fa-rotate fa-spin');
					$button.prop('disabled', true).addClass('disabled');

					// Call the function to send the campaign
					send_iterable_campaign(campaignId, email)
						.then(response => {
							// Success notification
							do_wiz_notif({ message: "Proof sent to " + email, duration: 10000 });

							// Reset button state
							resetButtonState($button);
						})
						.catch(error => {
							// Error notification
							do_wiz_notif({ message: "There was an error sending the proof: " + error, duration: 10000 });

							// Reset button state
							resetButtonState($button);
						});
				}
			});
		} else {
			swal.fire("Error", "Current user email not found!", "error");
		}
	});


});
