jQuery(document).ready(function ($) {
	//Create or update Iterable Template on click
	$("#sendToIterable").on("click", function () {
		const post_id = $(this).data("postid");
		$("#iDoverlay").show();
		$("#iDspinner").show();

		$.post(idAjax.ajaxurl, {
				action: "get_template_data_for_iterable",
				post_id
			})
			.done((response) => {
				console.log(`get_template_date_for_iterable: ${JSON.stringify(response)}`);

				if (response.status === "error") {
					Swal.fire({
						title: "Template can't be synced!",
						html: response.message,
						icon: "error",
					}).then(() => {
						$("#iDoverlay").hide();
						$("#iDspinner").hide();
					});
				} else {
					const fieldsToList = Object.entries(response.fields)
						.filter(([key]) => key !== "postId")
						.map(([key, value]) => {
							const val = value || "<em>Not value set</em>";
							return `<li><strong>${key}</strong>: ${val}</li>`;
						})
						.join("");

					const fieldList = `<ul style="text-align: left;">${fieldsToList}</ul>`;

					Swal.fire({
						title: "Confirm Sync Details",
						html: `<strong>The following will be synced to Iterable:</strong><br/><br/>${fieldList}`,
						icon: "warning",
						showCancelButton: true,
						cancelButtonText: "Go Back",
						confirmButtonText: "Confirm & Sync!",
						allowOutsideClick: false,
						preConfirm: () => new Promise((resolve) => resolve()),
					}).then((result) => {
						if (result.isConfirmed) {
							$("#iDspinner").hide();
							Swal.fire({
								title: "Syncing with Iterable...",
								html: "Please wait a few moments.",
								showCancelButton: false,
								showConfirmButton: false,
								allowOutsideClick: false,
								didOpen: () => {
									Swal.showLoading();
									const loader = Swal.getHtmlContainer().querySelector(".loader");

									create_iterable_template(response.fields)
										.then((makeTemplate) => {
											console.log(`make template: ${makeTemplate}`);
											if (makeTemplate) {
												Swal.hideLoading();
												Swal.update({
													title: "Sync complete",
													html: `Sync was successful!<br/><a style="text-decoration:underline;" href="https://app.iterable.com/templates/editor?templateId=${makeTemplate}" target="_blank">Click here to go to Iterable template</a>.`,
													showConfirmButton: true,
													allowOutsideClick: true,
												});
											} else {
												Swal.hideLoading();
												Swal.update({
													title: "Sync failed!",
													html: "Sync was unsuccessful.",
													icon: "error",
													showConfirmButton: true,
													allowOutsideClick: true,
												});
											}
										});
								},
							}).then(() => {
								$("#iDspinner").show();
								const currentUrl = window.location.href;
								window.location.href = currentUrl;
							});
						} else {
							$("#iDoverlay").hide();
							$("#iDspinner").hide();
						}
					});
				}
			})
			.fail((response) => {
				Swal.fire("Whoops, something's not right. Try re-saving your template first.", {
					icon: "error",
				}).then(() => {
					$("#iDoverlay").hide();
					$("#iDspinner").hide();
				});
			});
	});


	//Create/Update Template
	function create_iterable_template(templateData) {
		const apiKey = "282da5d7dd77450eae45bdc715ead2a4";
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

		let messageTypeId;
		if (messageType === "Transactional") {
			messageTypeId = 52620;
			fromSender = "info@idtechnotifications.com";
		} else {
			messageTypeId = 52634; //promotional
			fromSender = "info@idtechonline.com";
		}

		let templateHtml = $("#generatedCode").text();
		templateHtml = templateHtml.replace(/[\u201C\u201D]/g, '"');
		templateHtml = templateHtml.replace(">", ">");
		templateHtml = templateHtml.replace("<", "<");

		console.log("Mapping to API...");
		const data = {
			name: templateName,
			fromName: fromName,
			fromEmail: fromSender,
			replyToEmail: "info@idtechonline.com",
			subject: emailSubject,
			preheaderText: preheader,
			clientTemplateId: postId,
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
		};

		console.log("Making AJAX API call...");
		return new Promise(function (resolve, reject) {
			$.ajax({
				type: "POST",
				url: "https://api.iterable.com/api/templates/email/upsert",
				data: JSON.stringify(data),
				contentType: "text/plain",
				dataType: "json",
				beforeSend: function (xhr) {
					xhr.setRequestHeader("Api-Key", apiKey);
				},
				success: function (result) {
					console.log("Template created successfully!");
					const response = JSON.stringify(result);
					const iterableResponse = response.split(" ");
					const getTemplateId = iterableResponse.pop();
					const itTemplateId = getTemplateId.match(/\d+/g);

					$.ajax({
						type: "POST",
						url: idAjax.ajaxurl,
						data: {
							action: "update_template_after_sync",
							post_id: postId,
							template_id: itTemplateId[0],
						},
						success: function (afterSync) {
							if (afterSync.status === 'success') {
								console.log('after meta sync' + afterSync);
								resolve(itTemplateId);
							}
						},
						error: function (xhr, status, error) {
							console.log("Error: " + status + " - " + error + " Server response: " + JSON.stringify(xhr));
							reject(false);
						},
					});
				},
				error: function (xhr, status, error) {
					console.error("Template creation failed. HTTP Code: " + xhr.status);
					console.error("HTTP Response: " + xhr.responseText);
					reject(false);
				},
			});
		});
	}


});