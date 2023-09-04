jQuery(document).ready(function ($) {


// Sync a single campaign
$('.sync-campaign').on('click', function() {
  var campaignId = $(this).attr('data-campaignid');
  $('#wiztable_status_updates').show();
  $('#wiztable_status_updates').addClass('active');
   $('#wiztable_status_updates .wiztable_update').text('Syncing campaign...');
  idemailwiz_do_ajax(
      'idemailwiz_ajax_sync',
      idAjax_wiz_metrics.nonce, 
      {
      campaignIds: JSON.stringify(campaignId),
      },
      function(data) {
      if (data.success) {
        console.log('campaign synced!' + JSON.stringify(data));
        $('#wiztable_status_updates .wiztable_update').text('Campaign synced! Refresh for new data.');
      } else {
        console.log('campaign sync failed!' + JSON.stringify(data));
        $('#wiztable_status_updates .wiztable_update').text('Sync failed! Try refreshing the page.');
      }
      },
      function(error) {
      console.error("Failed to sync campaign", error);
      failure();
      }
  );
});


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


  $('.mark_as_winner button').on('click', function() {
    console.log('toggling winner....');
      // Get the experiment and template IDs from the button's data attributes
      const experimentId = $(this).data('experimentid');
      const templateId = $(this).data('templateid');
      const actionType = $(this).data('actiontype');

      // Create the data object to pass to the Ajax function
      let additionalData = {
          experimentId: experimentId,
          templateId: templateId,
          actionType: actionType
      };

      // Make the Ajax call
      idemailwiz_do_ajax('handle_experiment_winner_toggle', idAjax_wiz_metrics.nonce, additionalData, toggleWinnerSuccess.bind(this), toggleWinnerError.bind(this));

      // Define success and error callbacks
      function toggleWinnerSuccess(data) {
          if (data.success) {
              // Handle successful response
              console.log("Winner updated successfully");
              $('.experiment_var_wrapper.winner .mark_as_winner button').text('Mark as winner');
              $('.experiment_var_wrapper.winner').removeClass('winner');
              $(this).closest('.experiment_var_wrapper').toggleClass('winner');
              $(this).closest('.experiment_var_wrapper .mark_as_winner button').text('Winner!');
          }
      }

      function toggleWinnerError(jqXHR, textStatus, errorThrown) {
          // Handle error
          console.log("Error updating winner: " + textStatus);
      }

      
  });



   $('#experimentNotes').on('blur', function() {
      // Get textarea value
      let notes = $(this).val();

      // Data validation and cleanup
      notes = notes.trim();
      console.log(notes);
      // Get experiment ID from parent element
      let experimentId = $(this).closest('.wizcampaign-experiment-notes').data('experimentid');

      // Prepare data to send
      let additionalData = {
          experimentNotes: notes,
          experimentId: experimentId
      };
      console.log(additionalData);
      // Make AJAX call
      idemailwiz_do_ajax(
          'save_experiment_notes', 
          idAjax_wiz_metrics.nonce, 
          additionalData, 
          function(response) {
              console.log('Data saved successfully:', response);
          }, 
          function(error) {
              console.error('Error saving data:', error);
          }
      );
  });



});

