jQuery(document).ready(function ($) {





// Template preview heatmap for single campaigns page
if ($('.templatePreviewIframe').length) {
    heatmapOverlay();
}

function heatmapOverlay() {
// Function to set the overlay height to match the iframe content height
	function setOverlayHeight() {
		var iframeContentHeight = $(".templatePreviewIframe").contents().find("body").height();
		$(".heatmap-overlay").height(iframeContentHeight);
	}

	$(".heatmap-point").hover(
    function() {
      // Show tooltip
      var unique_clicks = $(this).data("unique-clicks");
      var unique_click_rate = $(this).data("unique-click-rate");
      var url = $(this).data("url");
      var tooltip_content = "Unique Clicks: " + unique_clicks +
                            "<br>Unique Click Rate: " + unique_click_rate +
                            "<br>URL: <a href=\"" + url + "\">" + url + "</a>";
      var tooltip = $('<div class="heatmap-tooltip"></div>').html(tooltip_content);

      var position = $(this).position(); // Get the position of the heatmap point
      tooltip.css({
        left: position.left,
        top: position.top - 30 // Position above the point, adjust as needed
      });

      $(".heatmap-tooltips").append(tooltip);
      tooltip.show();
    },
    function() {
      // Hide tooltip
      $(".heatmap-tooltips").find(".heatmap-tooltip").remove();
    }
  );

	// Function to synchronize scrolling
	function syncScrolling() {
  var iframeWindow = $(".templatePreviewIframe")[0].contentWindow;

  $(iframeWindow).on("scroll", function() {
		console.log('iframe scrolled');
		var scrollTop = $(iframeWindow.document).scrollTop();
		$(".heatmap-container").css("top", -scrollTop + "px");
	});
	}
	// Set the overlay height and synchronize scrolling when the iframe is fully loaded
	$(".templatePreviewIframe").on("load", function() {
		setOverlayHeight();
		syncScrolling();
	});
}

});

