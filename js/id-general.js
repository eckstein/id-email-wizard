


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

	
	

});
	
//Global scope functions


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
function idemailwiz_do_ajax(actionFunctionName, nonceValue, additionalData, successCallback, errorCallback) {

    let defaultData = {
        action: actionFunctionName,
        security: nonceValue
    };

    let mergedData = Object.assign({}, defaultData, additionalData);

    jQuery.ajax({
        url: idAjax.ajaxurl,
        type: 'POST',
        data: mergedData,
        dataType: 'json' //Keep an eye on this value in case ajax doesn't work for some calls. If we remove this, we have to parse the JSON in our callbacks
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








