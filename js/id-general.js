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
	
	

	//custom sticky element
	// Get the initial position of the element
	if ($('.pre-sticky').length) {
	  var stickyTop = $('.pre-sticky');
	  var stickyScrolled = $('.pre-sticky').offset().top;
	  var preStickyWidth = $('.pre-sticky').width();
	  
	  // Create a placeholder element with the same size and position as the sticky element
	  var placeholder = $('<div>').css({
		'position': 'relative',
		'width': stickyTop.width() + 'px',
		'height': stickyTop.height() + 'px',
		'margin-top': stickyTop.css('margin-top'),
		'margin-right': stickyTop.css('margin-right'),
		'margin-bottom': stickyTop.css('margin-bottom'),
		'margin-left': stickyTop.css('margin-left')
	  });

	  $(window).scroll(function () {
		// Check if the user has scrolled past the element
		if ($(window).scrollTop() >= stickyScrolled) {
		  // Add a class to the element to make it sticky
		  stickyTop.addClass('idSticky');
		  
		  //set the width to its un-sticky width
		  stickyTop.width(preStickyWidth);
		  
		  
		  // Replace the sticky element with the placeholder
		  if (!stickyTop.prev().is(placeholder)) {
			stickyTop.before(placeholder);
		  }
		} else {
		  // Remove the class and margin to revert the element to its original position
		  stickyTop.removeClass('idSticky');
		  // Remove the placeholder and restore the sticky element
		  if (stickyTop.prev().is(placeholder)) {
			placeholder.remove();
		  }
		}
	  });
	}

	


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