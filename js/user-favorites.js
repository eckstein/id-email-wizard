jQuery(document).ready(function ($) {
	// Add or remove favorite click action
	$(document).on("click", ".addRemoveFavorite", function () {
		var object_id = $(this).attr("data-objectid");
		var object_type = $(this).attr("data-objecttype");
		addRemoveFavorite(object_id, object_type, "#content");
	});

	//Add or remove a favorite and refresh UI elements, if specified
	function addRemoveFavorite(object_id, object_type, refreshElement = false) {
		const userFavoriteData = {
			object_id: object_id,
			object_type: object_type,
		};

		const userFavoriteSuccess = function (response) {
			if (response.success) {
				$('.addRemoveFavorite[data-objectid="' + response.objectid + '"]').toggleClass("fa-regular fa-solid");
				//console.log("Favorite " + response.action + "for object ID " + response.objectid);
				if (refreshElement) {
					const allRefreshes = refreshElement.split(",");
					$.each(allRefreshes, function (index, value) {
						$.refreshUIelement(value);
					});
				}
			} else {
				console.log("Favorite editing failure!" + JSON.stringify(response));
			}
		};

		const userFavoriteError = function (xhr, status, error) {
			console.log("Error: " + error + "<br/>" + JSON.stringify(xhr));
		};

		idemailwiz_do_ajax("add_remove_user_favorite", idAjax_template_actions.nonce, userFavoriteData, userFavoriteSuccess, userFavoriteError);
	}
});
