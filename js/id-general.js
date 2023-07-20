jQuery(document).ready(function ($) {
	
	//Global function to reload an element in the dom
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

	// Call setupCategoriesView on page load
	setupCategoriesView();
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