


jQuery(document).ready(function ($) {

	

	// Call toggleOverlay(false) once everything (including images, iframes, scripts, etc.) has finished loading
    $(window).on('load', function() {
        toggleOverlay(false);
    });
	
	

	//Function to reload an element in the template folder interface
	(function($) {
	  $.refreshUIelement = function(selector) {
		// Load the content into a container div
		var container = $('<div>').load(location.href + ' ' + selector + ' > *', function () {
		  // Replace the contents of the specified element with the new content
		  $(selector).html(container.contents());
		  //If folder list is visible, reset it to the proper view
		  setupCategoriesView();
		  //Reinitialize select2 for template search
		  initialize_select2_for_template_search();
		});
	  };
	})(jQuery);


	//apply highlight to all <code> elements
	hljs.highlightAll();

	// Call setupCategoriesView on page load to show folder list correctly
	setupCategoriesView();

	// New Template popup interface
	$('.show-new-template-ui').on('click', function() {
		$('#new-template-popup').show();
		$('.idwiz-modal-overlay').show();
	});
	$('.idwiz-modal-close').on('click', function(){
		$('#new-template-popup').hide();
		$('.idwiz-modal-overlay').hide();
	});

	
	


	 // Stop click events within the popover from propagating
	$('#dt-popover-container').on('click', function(e) {
		e.stopPropagation();
	});

	// Stop click events within the Select2 dropdown from propagating
	$(document).on('click', '.select2-dropdown', function(e) {
		e.stopPropagation();
	});

});
	
//Global scope functions


function initialize_select2_for_template_search() {
	jQuery('.templateFolder #live-template-search').select2({
		minimumInputLength: 3,
		placeholder: "Search templates...",
		allowClear: true,
		ajax: {
			delay: 250,
			transport: function(params, success, failure) {
				idemailwiz_do_ajax(
					'idemailwiz_get_templates_for_select',
					idAjax_id_general.nonce, 
					{
						q: params.data.term,
					},
					function(data) {
						success({results: data});
					},
					function(error) {
						console.error("Failed to fetch templates", error);
						failure();
					}
				);
			}
		}
	}).on('select2:select', function (e) {
		var data = e.params.data;
		if (data.id) {
			var postUrl = idAjax_id_general.site_url + '/?p=' + data.id;
			window.location.href = postUrl;
		}
	});
}

function setupCategoriesView() {
  if (jQuery('.folderList').is(':visible')) {
	// Close all sub-categories initially
	jQuery('.sub-categories').hide();

	// Set the arrow icons for all categories to point down
	jQuery('.showHideSubs').removeClass('fa-angle-up').addClass('fa-angle-down');

	// Open the first top-level root folder by default
	jQuery('.cat-item').first().addClass('open').children('.sub-categories').show();
	jQuery('.cat-item.open').find('> .showHideSubs').removeClass('fa-angle-down').addClass('fa-angle-up');

	// Set current-cat and its direct parent categories to be expanded
	jQuery('.current-cat').parents('.cat-item').addClass('open').children('.sub-categories').show();
	jQuery('.current-cat, .current-cat').parents('.cat-item').find('> .showHideSubs').removeClass('fa-angle-down').addClass('fa-angle-up');

	// Show sub-categories of the current-cat if they exist
	jQuery('.current-cat').children('.sub-categories').show();
	jQuery('.current-cat').find('> .showHideSubs').removeClass('fa-angle-down').addClass('fa-angle-up');
  }
}


// A generalized Ajax call.
// Params: action_name, nonce_value, array of passed data, success callback, error callback
// The callback functions can either be directly built in the function or can also take names of 
// callback functions which will get the data and error objects passed into them for use
function idemailwiz_do_ajax(actionFunctionName, nonceValue, additionalData, successCallback, errorCallback, dataType='json') {

    let defaultData = {
        action: actionFunctionName,
        security: nonceValue
    };

    let mergedData = Object.assign({}, defaultData, additionalData);

    jQuery.ajax({
        url: idAjax.ajaxurl,
		context: this,
        type: 'POST',
        data: mergedData,
        dataType: dataType 
    })
    .done(successCallback)
    .fail(errorCallback)
    .always(function() {
        // Always executed regardless of response
    });
}





// Even more global function to reload and element, when passed one
function wizReloadThing(selector) {
	var element = jQuery(selector);
	
	if (element.length) {
		// Fetch the current page's content
		jQuery.ajax({
			url: window.location.href,
			type: 'GET',
			success: function(data) {
				// Replace the element's content with the content from the fetched page
				var updatedContent = jQuery(selector, data).html();
				element.html(updatedContent);
			},
			error: function() {
				console.error("Failed to reload element:", selector);
			}
		});
	} else {
		console.warn("Element not found:", selector);
	}
}


function handle_idwiz_sync_buttons(action, nonce, data = {}) {
  // Show status updates
  jQuery('#wiztable_status_updates').addClass('active').slideDown();
  jQuery('#wiztable_status_updates .wiztable_update').text('Syncing databases...');

 // Write initialization to log
idemailwiz_do_ajax(
	"ajax_to_wiz_log",
	idAjax_id_general.nonce,
	{
		log_data: "Initializing database sync. Please wait a few moments...",
		timestamp: true
	},
	function(result) {
		jQuery('#wiztable_status_sync_details').load(idAjax.plugin_url + '/sync-log.txt');
	},
	function(error) {
		console.log(error);
	}
);

  // Start refreshing the log
  let refreshInterval = setInterval(() => {
	jQuery('#wiztable_status_sync_details').load(idAjax.plugin_url + '/sync-log.txt');
  }, 3000);

  // Perform the AJAX call
  idemailwiz_do_ajax(
	action,
	nonce,
	data,
	function(result) { // success callback
	  clearInterval(refreshInterval);
	  jQuery('#wiztable_status_updates .wiztable_update').text('Sync completed! Refresh the table for new data');
	  jQuery('#wiztable_status_sync_details').load(idAjax.plugin_url + '/sync-log.txt');
	},
	function(error) { // error callback
	  clearInterval(refreshInterval);
	  jQuery('#wiztable_status_updates .wiztable_update').text('ERROR: Sync process failed with message: ' + JSON.stringify(error));
	  jQuery('#wiztable_status_sync_details').load(idAjax.plugin_url + '/sync-log.txt');
	}
  );
}

// Sync log toggle
jQuery(document).on('click', '.wiztable_view_sync_details', function() {
	jQuery('#wiztable_status_sync_details').slideToggle();
	jQuery(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
});


