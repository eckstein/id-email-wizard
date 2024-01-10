<?php
add_action( 'wp_ajax_create_new_comparison_post', 'create_new_comparison_post_handler' );

function create_new_comparison_post_handler() {
	check_ajax_referer( 'comparisons', 'security' ); // Check nonce for security

	$postTitle = isset( $_POST['postTitle'] ) ? sanitize_text_field( $_POST['postTitle'] ) : '';

	// Create post array
	$postArr = array(
		'post_title' => $postTitle,
		'post_status' => 'publish',
		'post_type' => 'idwiz_comparison',
	);

	// Insert the post into the database
	$postId = wp_insert_post( $postArr );

	if ( $postId !== 0 ) {
		$permalink = get_permalink( $postId );
		wp_send_json_success( [ 'url' => $permalink ] );
	} else {
		wp_send_json_error( [ 'message' => 'Failed to create a new post.' ] );
	}
}

function idemailwiz_update_set_title() {
	check_ajax_referer( 'comparisons', 'security' );

	$setTitle = isset( $_POST['setTitle'] ) ? sanitize_text_field( $_POST['setTitle'] ) : '';
	$setId = isset( $_POST['setId'] ) ? intval( $_POST['setId'] ) : 0;
	$postId = isset( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0;

	// Fetch existing campaign sets
	$campaignSets = get_post_meta( $postId, 'compare_campaign_sets', true );

	// Update the setName for the specific setId
	foreach ( $campaignSets['sets'] as &$set ) {
		if ( $set['setId'] === $setId ) {
			$set['setName'] = $setTitle;
			break;
		}
	}

	// Save the updated campaign sets
	$updateResult = update_post_meta( $postId, 'compare_campaign_sets', $campaignSets );

	if ( $updateResult !== false ) {
		wp_send_json_success( 'Set title updated successfully' );
	} else {
		wp_send_json_error( 'Error updating set title' );
	}
}
add_action( 'wp_ajax_idemailwiz_update_set_title', 'idemailwiz_update_set_title' );

function idemailwiz_refresh_comparison_subtitle() {
	$postId = isset( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0;
	if ( ! $postId ) {
		wp_send_json_error( 'Error generating subtitle' );
	}

	$campaignSets = get_post_meta( $postId, 'compare_campaign_sets', true );
	$newTitle = generateComparisonSubtitle( $campaignSets );

	wp_send_json_success( $newTitle );

}
add_action( 'wp_ajax_idemailwiz_refresh_comparison_subtitle', 'idemailwiz_refresh_comparison_subtitle' );

function idemailwiz_re_sort_compare_campaigns() {
	check_ajax_referer( 'comparisons', 'security' );

	$setId = isset( $_POST['setId'] ) ? intval( $_POST['setId'] ) : 0;
	$postId = isset( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0;
	$sort = isset( $_POST['sort'] ) ? $_POST['sort'] : 'DESC';

	$campaignSets = get_post_meta( $postId, 'compare_campaign_sets', true );

	foreach ( $campaignSets['sets'] as &$set ) {
		if ( $set['setId'] === $setId ) {
			// Filter out spacer campaigns
			$nonSpacerCampaignIds = array_filter( $set['campaigns'], function ($id) {
				return ! str_starts_with( $id, 'spacer_' );
			} );

			// Fetch and sort campaign details only for non-spacer campaigns
			if ( ! empty( $nonSpacerCampaignIds ) ) {
				$campaignDetails = get_idwiz_campaigns( [ 
					'campaignIds' => $nonSpacerCampaignIds,
					'fields' => 'startAt, id',
					'sort' => $sort,
					'sortBy' => 'startAt'
				] );

				// Extract only the IDs for sorted campaigns
				$sortedCampaignIds = array_column( $campaignDetails, 'id' );

				// Update the campaigns array with sorted campaign IDs
				$set['campaigns'] = $sortedCampaignIds;
			} else {
				// If no non-spacer campaigns are found, clear the set
				$set['campaigns'] = [];
			}
		}
	}

	// Save the updated campaign sets
	$updateSuccess = update_post_meta( $postId, 'compare_campaign_sets', $campaignSets );

	if ( $updateSuccess !== false ) {
		wp_send_json_success( 'Campaigns re-sorted successfully' );
	} else {
		wp_send_json_error( 'Error re-sorting campaigns' );
	}
}
add_action( 'wp_ajax_idemailwiz_re_sort_compare_campaigns', 'idemailwiz_re_sort_compare_campaigns' );


function generateComparisonSubtitle( $campaignSets = [] ) {
	$setDescriptions = [];
	if ( empty( $campaignSets['sets'] ) ) {
		return false;
	}
	foreach ( $campaignSets['sets'] as $set ) {
		$setName = ! empty( $set['setName'] ) ? $set['setName'] : "campaign set {$set['setId']}";

		// Filter out campaign IDs starting with "spacer_"
		$campaignIds = array_filter( $set['campaigns'], function ($id) {
			return strpos( $id, "spacer_" ) !== 0;
		} );

		$campaignCount = count( $campaignIds );
		$setDescriptions[] = "$setName ($campaignCount campaigns)";
	}

	return 'Comparing ' . implode( ' against ', $setDescriptions );
}




function idemailwiz_add_compare_set_spacer() {
	// Check the nonce for security
	if ( ! check_ajax_referer( 'comparisons', 'security', false ) ) {
		error_log( 'Nonce check failed' );
		wp_send_json_error( 'Nonce check failed' );
		return;
	}

	// Retrieve the AJAX sent data
	$setId = isset( $_POST['setId'] ) ? intval( $_POST['setId'] ) : 0;
	$postId = isset( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0;
	$addBefore = isset( $_POST['addBefore'] ) ? $_POST['addBefore'] : false;

	//Create a campaignId for this spacer
	$campaignId = uniqid( 'spacer_' . $setId . '_' . $postId . '_' );

	// Check if a valid post ID is provided
	if ( ! $postId ) {
		wp_send_json_error( 'Invalid post ID' );
		return;
	}

	// Fetch existing campaign sets or initialize if not set
	$campaignSets = get_post_meta( $postId, 'compare_campaign_sets', true );
	if ( ! is_array( $campaignSets ) || ! isset( $campaignSets['sets'] ) ) {
		$campaignSets = [ 'sets' => [] ]; // Initialize if not set or not the expected structure
	}

	// Initialize a flag to track if the set was found and updated
	$setFound = false;

	// Find the specific set and append or insert new campaign IDs
	foreach ( $campaignSets['sets'] as &$set ) {
		if ( isset( $set['setId'] ) && $set['setId'] === $setId ) {
			$setFound = true;

			if ( $addBefore !== false ) {
				// Find position to insert before
				$insertPosition = array_search( $addBefore, $set['campaigns'] );
				if ( $insertPosition !== false ) {
					array_splice( $set['campaigns'], $insertPosition, 0, [ $campaignId ] );
				} else {
					// If $addBefore ID not found, append to end
					$set['campaigns'] = array_merge( $set['campaigns'], [ $campaignId ] );
				}
			} else {
				// Append new campaign IDs to existing ones
				$set['campaigns'] = array_merge( $set['campaigns'], [ $campaignId ] );
			}

			$set['campaigns'] = array_unique( $set['campaigns'] );
			break;
		}
	}

	// If the set was not found, create a new one
	if ( ! $setFound ) {
		$campaignSets['sets'][] = [ 
			'setId' => $setId,
			'campaigns' => [ $campaignId ]
		];
	}

	// Save the updated campaign sets
	$updateResult = update_post_meta( $postId, 'compare_campaign_sets', $campaignSets );

	if ( $updateResult === false ) {
		wp_send_json_error( 'Failed to update campaign sets' );
	} else {
		$spacerHtml = generate_compare_campaign_card_html( $setId, $campaignId, $postId, true );

		wp_send_json_success( [ 'message' => 'Campaigns updated successfully', 'spacerHtml' => $spacerHtml ] );
	}

}
add_action( 'wp_ajax_idemailwiz_add_compare_set_spacer', 'idemailwiz_add_compare_set_spacer' );

function idemailwiz_handle_ajax_add_compare_campaign() {
	// Check the nonce for security
	if ( ! check_ajax_referer( 'comparisons', 'security', false ) ) {
		error_log( 'Nonce check failed' );
		wp_send_json_error( 'Nonce check failed' );
		return;
	}

	// Retrieve the AJAX sent data
	$mode = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : '';
	$setId = isset( $_POST['setId'] ) ? intval( $_POST['setId'] ) : 0;
	$postId = isset( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0;
	$addBefore = isset( $_POST['addBefore'] ) ? $_POST['addBefore'] : false;
	$replaceWith = isset( $_POST['replaceWith'] ) ? $_POST['replaceWith'] : false;
	$refreshOnly = isset( $_POST['refreshOnly'] ) ? $_POST['refreshOnly'] : false;
	$siblingCard = isset( $_POST['siblingCard'] ) ? $_POST['siblingCard'] : false; //ID of the sibling card

	// Check if a valid post ID is provided
	if ( ! $postId ) {
		wp_send_json_error( 'Invalid post ID' );
		return;
	}

	// Initialize an array to store new campaign IDs
	$newCampaignIds = [];

	if ( $refreshOnly ) {
		$newCampaignIds[] = $replaceWith;
	} else {

		// Handle 'byCampaign' mode - Specific Campaigns
		if ( $mode === 'byCampaign' && isset( $_POST['campaigns'] ) ) {
			$newCampaignIds = array_map( 'intval', $_POST['campaigns'] );
		} else if ( $mode === 'byDate' && isset( $_POST['startDate'], $_POST['endDate'] ) ) {
			// Handle 'byDate' mode - Date Range
			$startDate = sanitize_text_field( $_POST['startDate'] );
			$endDate = sanitize_text_field( $_POST['endDate'] );
			$dateRangeCampaigns = get_idwiz_campaigns( [ 'type' => 'Blast', 'startAt_start' => $startDate, 'startAt_end' => $endDate, 'sortBy' => 'startAt', 'sort' => 'DESC' ] );

			if ( $dateRangeCampaigns ) {
				foreach ( $dateRangeCampaigns as $campaign ) {
					$newCampaignIds[] = $campaign['id'];
				}
			}
		}
	}

	if ( ! empty( $newCampaignIds ) ) {
		// Fetch existing campaign sets or initialize if not set
		$campaignSets = get_post_meta( $postId, 'compare_campaign_sets', true );
		if ( ! is_array( $campaignSets ) || ! isset( $campaignSets['sets'] ) ) {

			$campaignSets = [ 'sets' => [] ];
		}

		$setFound = false;
        $setValid = false;
		$html = '';

		// Find the specific set and append or insert new campaign IDs
		foreach ( $campaignSets['sets'] as &$set ) {
			if ( isset( $set['setId'] ) && $set['setId'] === $setId) {
				$setFound = true;

                if (count($set['campaigns']) > 1) {
                    $setValid = true;
                }

				if ( $replaceWith !== false ) {
					$replaceIndex = array_search( $replaceWith, $set['campaigns'] );
					if ( $replaceIndex !== false ) {
						$set['campaigns'][ $replaceIndex ] = $newCampaignIds[0];
					}
				} else if ( $addBefore !== false ) {
					// Find position to insert before
					$insertPosition = array_search( $addBefore, $set['campaigns'] );
					if ( $insertPosition !== false ) {
						array_splice( $set['campaigns'], $insertPosition, 0, $newCampaignIds );
					} else {
						// If $addBefore ID not found, append to end
						$set['campaigns'] = array_merge( $set['campaigns'], $newCampaignIds );
					}
				} else {
					// Append new campaign IDs to existing ones
					$set['campaigns'] = array_merge( $set['campaigns'], $newCampaignIds );
				}



				if ( ! $refreshOnly ) {
					// Remove duplicates and track duped IDs
					$originalCampaigns = $set['campaigns'];
					$set['campaigns'] = array_unique( $set['campaigns'] );
					$dupedCampaignIds = array_diff( $originalCampaigns, $set['campaigns'] );
					$addedCampaignIds = array_diff( $newCampaignIds, $dupedCampaignIds );
				}

				if ( ! empty( $addedCampaignIds ) ) {
					foreach ( $addedCampaignIds as $addedCampaignId ) {
						$html .= generate_compare_campaign_card_html( $set['setId'], $addedCampaignId, $postId, true, null, true );
					}
				}

				break;
			}
		}


		if ( $refreshOnly ) {
			$newCampaignIds[] = $replaceWith;
			$newCampaignIds = array_unique( $newCampaignIds );

			// Generate HTML for the refreshed campaign
			foreach ( $newCampaignIds as $campaignId ) {
				$html .= generate_compare_campaign_card_html( $setId, $campaignId, $postId, true, null, true );
			}

			wp_send_json_success( [ 
				'message' => 'Campaign refreshed successfully',
				'replaceWith' => $replaceWith,
				'html' => $html
			] );
			return; // Make sure to return after sending the response
		} else {
			// If the set was not found, create a new one
			if ( ! $setFound ) {
				// If the set was not found, create and add a new one
				$campaignSets['sets'][] = [ 
					'setId' => $setId,
					'campaigns' => $newCampaignIds
				];
				// Generate HTML for the new set
				foreach ( $newCampaignIds as $addedCampaignId ) {
					$html .= generate_compare_campaign_card_html( $setId, $addedCampaignId, $postId, true, null, true );
				}
			}

			// Save the updated campaign sets
			$updateSuccess = update_post_meta( $postId, 'compare_campaign_sets', $campaignSets );

			if ( $updateSuccess ) {
				$successMessage = count( $addedCampaignIds ) . ' campaigns were added successfully';
				if ( count( $dupedCampaignIds ) > 0 ) {
					$successMessage .= '. ' . count( $dupedCampaignIds ) . ' already existed and were not added';
				}
				wp_send_json_success( [ 
					'message' => $successMessage,
					'addBefore' => $addBefore,
					'replaceWith' => $replaceWith,
					'html' => $html,
					'firstAddition' => $setValid ? false : true
				] );
			} else {
				wp_send_json_error( 'Campaign list was not updated! Perhaps that campaign is already added?' );
			}
		}


	} else {
		wp_send_json_error( 'No campaigns found to add' );
	}

}

add_action( 'wp_ajax_idemailwiz_handle_ajax_add_compare_campaign', 'idemailwiz_handle_ajax_add_compare_campaign' );

function idemailwiz_clear_comparision_campaign() {
	check_ajax_referer( 'comparisons', 'security' );

	$postId = isset( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0;
	$setId = isset( $_POST['setId'] ) ? intval( $_POST['setId'] ) : 0;

	if ( ! $postId || ! $setId ) {
		wp_send_json_error( 'Invalid data provided' );
		return;
	}

	$campaignSets = get_post_meta( $postId, 'compare_campaign_sets', true );

	foreach ( $campaignSets['sets'] as &$set ) {
		if ( $set['setId'] === $setId ) {
			$set['campaigns'] = []; // Clear the campaigns for this set
			update_post_meta( $postId, 'compare_campaign_sets', $campaignSets );
			wp_send_json_success( 'Campaigns cleared from set' );
			return;
		}
	}

	wp_send_json_error( 'Set not found' );
}
add_action( 'wp_ajax_idemailwiz_clear_comparision_campaign', 'idemailwiz_clear_comparision_campaign' );


function idemailwiz_update_comparison_campaigns_order() {
	// Check nonce and validate POST data
	check_ajax_referer( 'comparisons', 'security' );

	$postId = isset( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0;
	if ( ! $postId ) {
		wp_send_json_error( 'Invalid post ID' );
		return;
	}

	$setId = intval( $_POST['setId'] );
	$droppedCampaignId = $_POST['droppedCampaignId'];
	$nextCampaignId = isset( $_POST['nextCampaignId'] ) ? $_POST['nextCampaignId'] : null;

	// Fetch the campaign sets
	$campaignSets = get_post_meta( $postId, 'compare_campaign_sets', true );

	if ( ! isset( $campaignSets['sets'] ) ) {
		wp_send_json_error( 'No campaign sets found' );
		return;
	}

	$setFound = false;
	$updateSuccess = false;

	// Find and rearrange campaigns in the specified set
	foreach ( $campaignSets['sets'] as &$set ) {
		if ( $set['setId'] === $setId ) {
			$setFound = true;

			// Remove the dropped campaign ID from its current position
			$currentKey = array_search( $droppedCampaignId, $set['campaigns'] );
			if ( $currentKey !== false ) {
				unset( $set['campaigns'][ $currentKey ] );
			}

			// Determine the new position for the dropped campaign ID
			if ( $nextCampaignId !== null ) {
				$nextKey = array_search( $nextCampaignId, $set['campaigns'] );
				array_splice( $set['campaigns'], $nextKey, 0, $droppedCampaignId );
			} else {
				// If no next campaign ID, append to the end
				$set['campaigns'][] = $droppedCampaignId;
			}

			// Re-index the array to ensure it's properly formatted
			if ( is_array( $set['campaigns'] ) ) {
				$set['campaigns'] = array_values( $set['campaigns'] );
			}

			// Update post meta with the rearranged set
			$updateSuccess = update_post_meta( $postId, 'compare_campaign_sets', $campaignSets );
			break;
		}
	}

	if ( ! $setFound ) {
		wp_send_json_error( 'Set not found' );
	} elseif ( ! $updateSuccess ) {
		wp_send_json_error( 'Failed to update campaign order' );
	} else {
		wp_send_json_success( 'Campaign order updated' );
	}
}
add_action( 'wp_ajax_idemailwiz_update_comparison_campaigns_order', 'idemailwiz_update_comparison_campaigns_order' );


function idemailwiz_remove_comparision_campaign() {
	check_ajax_referer( 'comparisons', 'security' );

	$postId = isset( $_POST['postId'] ) ? intval( $_POST['postId'] ) : false;
	$setId = isset( $_POST['setId'] ) ? intval( $_POST['setId'] ) : false;
	$campaignId = isset( $_POST['campaignId'] ) ? $_POST['campaignId'] : false;

	//error_log('Removing campaign ' . $campaignId . 'from set ' . $setId);
	//error_log('Post ID: ' . $postId);

	if ( $postId === false || $setId === false || $campaignId === false ) {
		wp_send_json_error( 'Invalid data provided' );
		return;
	}

	$campaignSets = get_post_meta( $postId, 'compare_campaign_sets', true );

	foreach ( $campaignSets['sets'] as &$set ) {
		if ( $set['setId'] === $setId ) {
			// Remove the campaign ID and re-index the array
			$set['campaigns'] = array_values( array_filter( $set['campaigns'], function ($id) use ($campaignId) {
				return (string) $id !== (string) $campaignId;
			} ) );

			// Update the post meta
			$updateSuccess = update_post_meta( $postId, 'compare_campaign_sets', $campaignSets );

			$campaignIdCount = count( $set['campaigns'] );
			$refreshForEmpty = false;
            if ( $campaignIdCount === 0 ) {
                $refreshForEmpty = true;
            }

			// Check if the update was successful
			if ( $updateSuccess ) {
				wp_send_json_success( array( 'message' => 'Campaign successfully removed', 'refreshForEmpty' => $refreshForEmpty ) );
			} else {
				wp_send_json_error( array( 'message' => 'Failed to update campaign sets metadata' ) );
			}

			return;
		}
	}

	wp_send_json_error( 'Set not found' );



	wp_send_json_error( 'Set not found' );
}
add_action( 'wp_ajax_idemailwiz_remove_comparision_campaign', 'idemailwiz_remove_comparision_campaign' );



function idemailwiz_generate_campaign_card_ajax() {
	// Check the nonce for security
	check_ajax_referer( 'comparisons', 'security' );

	// Validate and sanitize input parameters
	$setId = isset( $_POST['setId'] ) ? sanitize_text_field( $_POST['setId'] ) : null;
	$campaignId = isset( $_POST['campaignId'] ) ? sanitize_text_field( $_POST['campaignId'] ) : null;
	$postId = isset( $_POST['postId'] ) ? sanitize_text_field( $_POST['postId'] ) : null;
	$asNew = isset( $_POST['asNew'] ) ? filter_var( $_POST['asNew'], FILTER_VALIDATE_BOOLEAN ) : false;
	$templateId = isset( $_POST['templateId'] ) ? sanitize_text_field( $_POST['templateId'] ) : null;
	$isBaseMetric = isset( $_POST['isBaseMetric'] );

	// Check if all required parameters are present
	if ( ! $setId || ! $campaignId || ! $postId ) {
		wp_send_json_error( [ 'message' => 'Missing required parameters' ] );
		return;
	}

	// Generate the campaign card HTML
	$html = generate_compare_campaign_card_html( $setId, $campaignId, $postId, $asNew, $templateId, $isBaseMetric );

	if ( $html ) {
		wp_send_json_success( [ 'html' => $html ] );
	} else {
		wp_send_json_error( [ 'message' => 'Failed to generate campaign card HTML' ] );
	}
}

add_action( 'wp_ajax_idemailwiz_generate_campaign_card_ajax', 'idemailwiz_generate_campaign_card_ajax' );


function generate_compare_campaign_card_html( $setId, $campaignId, $postId, $asNew = false, $templateId = false, $isBaseMetric = false ) {
	date_default_timezone_set( 'America/Los_Angeles' );
	// Validate input parameters
	if ( empty( $setId ) || empty( $campaignId ) || empty( $postId ) ) {
		return ''; // Return an empty string or handle error as needed
	}

	// Handle spacer campaigns
	if ( str_contains( $campaignId, 'spacer' ) ) {
		return generate_spacer_html( $setId, $campaignId, $postId );
	}

	// Retrieve campaign details
	$campaign = get_idwiz_campaign( $campaignId );
	if ( ! $campaign ) {
		return "Campaign not found."; // Or handle this case as needed
	}

	// Determine whether to use the default or specific templateId
	$useDefaultTemplateId = ! $templateId;
	$effectiveTemplateId = $useDefaultTemplateId ? $campaign['templateId'] : $templateId;
	$selectedTemplateId = false;
	if ( isset( $campaign['experimentIds'] ) ) {
		// Generate experiment tabs
		list( $experimentTabs, $selectedTemplateId ) = generate_experiment_tabs( $campaign, $effectiveTemplateId, $isBaseMetric );
	} else {
		$experimentTabs = '';
	}

	// Fetch metrics based on the templateId
	if ( $useDefaultTemplateId ) {
		// Fetch combined metrics for the base campaign
		$campaignMetrics = get_idwiz_metric( [ $campaignId ] );
	} else {
		// Fetch metrics for the specific experiment template
		$campaignMetrics = get_idwiz_experiment( [ $selectedTemplateId ] );
	}

	// Construct the HTML for the campaign card
	return build_campaign_card_html( $setId, $campaignId, $postId, $asNew, $campaign, $campaignMetrics, $experimentTabs, $selectedTemplateId, $isBaseMetric );
}

function generate_experiment_tabs( $campaign, $templateId, $isBaseMetric = false ) {
	$experimentTabs = '<div class="wizcampaign-sections-tabs compare-experiment-tabs"><ul>';

	// 'All Versions' tab is active if looking at base metrics ($isBaseMetric is true)
	$experimentTabs .= "<li class='" . ( $isBaseMetric ? 'active' : '' ) . "' data-is-base-metric='true'>All&nbsp;Versions</li>";

	// Fetch experiments
	$experiments = get_idwiz_experiments( array( 'campaignIds' => [ $campaign['id'] ] ) );

	foreach ( $experiments as $experiment ) {
		$winnerClass = '';
		if ( $experiment['wizWinner'] == true || $experiment['type'] == 'Winner' ) {
			$winnerClass = 'winner';
		}
		// Experiment tabs are active based on specific template ID selection
		// and not being in base metrics view
		$isActive = ! $isBaseMetric && $templateId == $experiment['templateId'];
		$experimentTabs .= "<li class='" . ( $isActive ? 'active' : '' ) . " " . $winnerClass . "' data-templateid='" . $experiment['templateId'] . "'><i class='fa-solid fa-flask-vial'></i>&nbsp;" . $experiment['name'] . "</li>";
	}

	$experimentTabs .= '</ul></div>';
	return array( $experimentTabs, $templateId );
}

function generate_spacer_html( $setId, $campaignId, $postId ) {
	// Building the HTML for the spacer campaign
	$html = '<div class="wizcampaign-sections-row compare-campaign-wrapper compare-campaign-spacer" data-setid="' . htmlspecialchars( $setId ) . '" data-campaignid="' . htmlspecialchars( $campaignId ) . '" data-postid="' . htmlspecialchars( $postId ) . '">';
	$html .= '<div class="wizcampaign-section dotted">';
	$html .= '<div class="wizcampaign-section-title-area">';
	$html .= '<h4>&nbsp;</h4>'; // Empty header for spacer
	$html .= '<div class="wizcampaign-section-title-area-right wizcampaign-section-icons compare-campaign-actions" data-set-id="' . htmlspecialchars( $setId ) . '" data-campaign-id="' . htmlspecialchars( $campaignId ) . '">';
	$html .= '<i class="fa-solid fa-up-down sortable-handle" title="Drag campaign up or down to change order"></i>';
	$html .= "<i class='fa-solid fa-chevron-up collapse-compare-row'></i>";
	$html .= '<i class="fa-solid fa-xmark remove-comparison-campaign"></i>';
	$html .= '</div>'; // Close title area right
	$html .= '</div>'; // Close title area
	$html .= '</div>'; // Close campaign section
	$html .= '</div>'; // Close wrapper

	return $html;
}




function build_campaign_card_html( $setId, $campaignId, $postId, $asNew, $campaign, $campaignMetrics, $experimentTabs, $templateId = false, $isBaseMetric = true ) {

	if ( ! $templateId ) {
		$templateId = $campaign['templateId'];
	}
	// Convert startAt timestamp to a readable date
	$campaignStartStamp = (int) ( $campaign['startAt'] / 1000 );
	$readableStartAt = date( 'm/d/y', $campaignStartStamp );
	$readableStartTime = date( 'g:ia', $campaignStartStamp );

	// Retrieve the template data
	$campaignTemplate = get_idwiz_template( $templateId );
	if ( ! $campaignTemplate ) {
		$campaignTemplate = get_idwiz_experiment( $templateId );
	}

	if ( ! $campaignTemplate ) {
		$campaignTemplate['subject'] = 'Template not found!';
		$campaignTemplate['preheaderText'] = 'Template not found!';
		$campaignTemplate['fromName'] = 'Template not found!';
		$campaignTemplate['fromEmail'] = 'Template not found!';

	}

	// Retrieve template image
	$templateImage = false;
	if (isset($campaignTemplate['templateImage']) && $campaignTemplate['templateImage']!= '') {
		// Checks the first byte of an image for a valid type, or false if image invalid
		$templateImage = $campaignTemplate['templateImage'] ? $campaignTemplate['templateImage'].'.jpg' : false;
	}


	$campaignMetricsSection = "";
	if ( $campaignTemplate['messageMedium'] == 'Email' ) {
		$campaignMetricsSection .= "<div class='compare-campaign-details-section compare-campaign-sl-pt'>";
		$campaignMetricsSection .= "<strong>SL: </strong> " . $campaignTemplate['subject'];
		$campaignMetricsSection .= "<br/><strong>PT: </strong> " . $campaignTemplate['preheaderText'] ?? "<em>none</em>";
		$campaignMetricsSection .= "<br/><strong>From: </strong> " . $campaignTemplate['fromName'] . " &lt;" . $campaignTemplate['fromEmail'] . "&gt;";
		$campaignMetricsSection .= "</div>";
	}
	$campaignMetricsSection .= "<div class='compare-campaign-details-section compare-campaign-revenue'>";

	$campaignMetricsSection .= "</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics'>";

	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item'>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-title'>Sent</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-value'>" . number_format( $campaignMetrics['uniqueEmailSends'] ) . "</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metric-item-difference'>";
	//$campaignMetricsSection .= "+ 1,234,567";
	$campaignMetricsSection .= "</div>";
	$campaignMetricsSection .= "</div>";

	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item'>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-title'>Opened</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-value'>" . number_format( $campaignMetrics['wizOpenRate'], 2 ) . "%</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metric-item-difference'>";
	//$campaignMetricsSection .= "- 50%";
	$campaignMetricsSection .= "</div>";
	$campaignMetricsSection .= "</div>";

	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item'>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-title'>CTR</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-value'>" . number_format( $campaignMetrics['wizCtr'], 2 ) . "%</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metric-item-difference'>";
	$campaignMetricsSection .= "</div>";
	$campaignMetricsSection .= "</div>";

	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item'>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-title'>CTO</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-value'>" . number_format( $campaignMetrics['wizCto'], 2 ) . "%</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metric-item-difference'>";
	$campaignMetricsSection .= "</div>";
	$campaignMetricsSection .= "</div>";

	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item'>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-title'>Unsub.</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-value'>" . number_format( $campaignMetrics['wizUnsubRate'], 2 ) . "%</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metric-item-difference'>";
	$campaignMetricsSection .= "</div>";
	$campaignMetricsSection .= "</div>";

	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item'>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-title'>Comp.</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-value'>" . number_format( $campaignMetrics['wizCompRate'], 2 ) . "%</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metric-item-difference'>";
	$campaignMetricsSection .= "</div>";
	$campaignMetricsSection .= "</div>";

	$campaignMetricsSection .= "</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics'>";

	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item rev'>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-title'>Rev.</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-value'>$". number_format( $campaignMetrics['revenue'])."</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metric-item-difference'>";
	$campaignMetricsSection .= "</div>";
	$campaignMetricsSection .= "</div>";

	
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item rev'>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-title'>GA Rev.</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-value'>";
	if ( $isBaseMetric ) {
	$campaignMetricsSection .= "$" . number_format( get_idwiz_revenue( '2021-11-01', date( 'Y-m-d' ), null, [ $campaignId ], true ) );
	} else {
	$campaignMetricsSection .= "<span title='Not available for experiment variations'>N/A</span>";
	}
	$campaignMetricsSection .= "<div class='compare-campaign-metric-item-difference'>";
	$campaignMetricsSection .= "</div>";
	$campaignMetricsSection .= "</div>";
	$campaignMetricsSection .= "</div>";

	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item rev'>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-title'>Prchs.</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-value'>". $campaignMetrics['totalPurchases']."</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metric-item-difference'>";
	$campaignMetricsSection .= "</div>";
	$campaignMetricsSection .= "</div>";

	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item rev'>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-title'>CVR</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-value'>" . number_format( $campaignMetrics['wizCvr'], 3 )."%</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metric-item-difference'>";
	$campaignMetricsSection .= "</div>";
	$campaignMetricsSection .= "</div>";

	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item rev'>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-title'>AOV</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metrics-item-value'>$" . number_format( $campaignMetrics['wizAov'])."</div>";
	$campaignMetricsSection .= "<div class='compare-campaign-metric-item-difference'>";
	$campaignMetricsSection .= "</div>";
	$campaignMetricsSection .= "</div>";


	$campaignMetricsSection .= "</div>";




	// Additional CSS class for new campaigns
	$showAsNewClass = $asNew ? 'showAsNew' : '';

	// Building the HTML structure
	$html = "<div class='wizcampaign-sections-row compare-campaign-wrapper $showAsNewClass' data-setid='" . htmlspecialchars( $setId ) . "' data-campaignid='" . htmlspecialchars( $campaignId ) . "' data-postid='" . htmlspecialchars( $postId ) . "' data-templateid='" . htmlspecialchars( $campaign['templateId'] ) . "'>";
	$html .= "<div class='wizcampaign-section shadow'>";
	$html .= "<div class='wizcampaign-section-title-area'>";
	$html .= "<div class='compare-campaign-datetime sortable-handle'><span class='compare-campaign-date'>" . $readableStartAt . "</span><span class='compare-campaign-time'>" . $readableStartTime . "</span></div><h4 class='sortable-handle' title='" . htmlspecialchars( $campaign['name'] ) . "'><a href='".get_bloginfo('url')."/metrics/campaign/?id=" . $campaignId . "'>" . htmlspecialchars( $campaign['name'] ) . "</a></h4>";
	$html .= "<div class='wizcampaign-section-title-area-right wizcampaign-section-icons compare-campaign-actions' data-set-id='" . htmlspecialchars( $setId ) . "' data-campaign-id='" . htmlspecialchars( $campaignId ) . "'>";
	$html .= "<i class='fa-solid fa-up-down sortable-handle' title='Drag campaign up or down to change order'></i>";

	// Retrieve comments from post meta
	$comments = get_post_meta( $postId, 'compare_campaign_comments', true );
	$commentCount = 0;
	if ( ! empty( $comments[ $setId ][ $campaignId ] ) ) {
		$commentCount = count( $comments[ $setId ][ $campaignId ] );
	}
	$showCommentCount = $commentCount > 0 ? "(" . $commentCount . ")" : "";

	$html .= "<span class='action-span-wrap'><i class='fa-regular fa-comment show-hide-compare-comments'></i><sup class='comment-count'>" . $showCommentCount . "</sup></span>";
	$html .= "<i class='fa-solid fa-rotate refresh-compare-campaign' data-replacewith='$campaignId' data-set-id='$setId' data-post-id='$postId'></i>";
	$html .= "<i class='fa-solid fa-chevron-up collapse-compare-row' title='Collapse/expand row'></i>";
	$html .= "<i class='fa-solid fa-xmark remove-comparison-campaign'></i>";
	$html .= "</div></div>"; // Close title area

	$html .= "<div class='compare-campaign-details'>";

	$html .= "<div title='Click to enlarge' class='compare-template-preview wiztemplate-preview template-image-wrapper' data-templateid='" . $templateId . "'>";
	$html .= "<div class='wiztemplate-image-spinner'><i class='fa-solid fa-spin fa-spinner fa-3x'></i></div>";
	$html .= "<img data-templateid='" . $templateId . "' data-src='" . $templateImage . "' />";
	$html .= "</div>"; 


	$html .= "<div class='compare-campaign-info'>";


	$html .= "<div class='compare-campaign-comments'>";
	$html .= "<div class='compare-campaigns-comments-title-area'><h3>Comments " . $showCommentCount . "</h3><span class='compare-campaigns-comments-actions'>";
	$html .= "<i class='fa-solid fa-circle-plus add-new-compare-comment' title='Add comment' data-post-id='" . htmlspecialchars( $postId ) . "' data-campaign-id='" . htmlspecialchars( $campaignId ) . "' data-set-id='" . htmlspecialchars( $setId ) . "'></i><i class='fa-solid fa-xmark show-hide-compare-comments' title='Close comments'></i>";
	$html .= "</span></div>";
	$html .= "<div class='compare-campaigns-comments-scrollwrap'>";

	$currentUser = wp_get_current_user()->display_name;

	// Check if there are comments for the specific campaign in the set    
	if ( ! empty( $comments[ $setId ][ $campaignId ] ) ) {
		foreach ( $comments[ $setId ][ $campaignId ] as $comment ) {
			// Add edit icon if the current user is the author
			$compareCommentActions = '';
			if ( $currentUser == $comment['author'] ) {
				$compareCommentActions .= "<span class='compare-campaign-comment-actions' data-post-id='" . htmlspecialchars( $postId ) . "' data-campaign-id='" . htmlspecialchars( $campaignId ) . "' data-set-id='" . htmlspecialchars( $setId ) . "' data-timestamp='" . $comment['timestamp'] . "'><i class='fa-solid fa-pencil edit-compare-comment'></i><i class='fa-solid fa-xmark delete-compare-comment'></i></span>";
			}
			$html .= "<div class='compare-campaign-comment'>";
			$html .= "<div class='compare-campaign-comment-header'>";
			$html .= "<span class='compare-campaign-comment-meta'>" . esc_html( $comment['author'] ) . " - " . date( 'm/d/y g:ia', strtotime( $comment['timestamp'] ) );
			if ( isset( $comment['lastUpdated'] ) && $comment['lastUpdated'] != '' ) {
				$html .= "<span class='compare-campaign-last-updated'>Edited " . date( 'm/d/y g:ia', strtotime( $comment['lastUpdated'] ) ) . "</span>";
			}
			$html .= "</span>";
			$html .= $compareCommentActions;
			$html .= "</div>";
			$html .= "<div class='compare-campaign-comment-content'>";
			$html .= $comment['content'];
			$html .= "</div>";
			$html .= "</div>";
		}
	} else {
		// No comments message
		$html .= "<div class='no-comments-message'>No comments yet.</div>";
	}

	$html .= "<div class='add-new-compare-comment-wrap add-new-compare-comment' data-post-id='" . htmlspecialchars( $postId ) . "' data-campaign-id='" . htmlspecialchars( $campaignId ) . "' data-set-id='" . htmlspecialchars( $setId ) . "'>";
	$html .= "<i class='fa-solid fa-circle-plus'></i> Add Comment";
	$html .= "</div>";
	$html .= "</div>"; // Close scrollwrap
	$html .= "</div>";

	$html .= $experimentTabs;
	$html .= "<div class='compare-campaign-scroll-wrap'>";
	$html .= $campaignMetricsSection;
	//$html .= print_r($campaignMetrics, true); // Print for debugging
	$html .= "</div>"; // Close campaign info
	$html .= "</div>"; // Close campaign metrics
	$html .= "</div>"; // Close campaign details

	$html .= "</div>"; // Close campaign section
	// if ($setId == 1) {
	// $html .= "<div class='center-gutter'><div class='gutter-link'><i class='fa-solid fa-link link-column-campaigns'></i></div></div>";
	// $html .= "<div class='wizcampaign-section shadow compare-flyout'>compare stuff here</div>";
	// }
	$html .= "</div>"; // Close wrapper

	return $html;
}





add_action( 'wp_ajax_add_new_compare_comment', 'add_new_compare_comment_handler' );
function add_new_compare_comment_handler() {
	// Check the nonce for security
	check_ajax_referer( 'comparisons', 'security' );

	$postId = $_POST['postId'];
	$campaignId = $_POST['campaignId'];
	$setId = $_POST['setId'];
	$commentContent = nl2br( esc_html( $_POST['comment'] ) );

	// Retrieve existing comments
	$comments = get_post_meta( $postId, 'compare_campaign_comments', true );
	if ( ! is_array( $comments ) ) {
		$comments = [];
	}

	// Append new comment
	$comments[ $setId ][ $campaignId ][] = [ 
		'timestamp' => current_time( 'mysql' ),
		'author' => wp_get_current_user()->display_name,
		'content' => $commentContent
	];

	// Update post meta
	update_post_meta( $postId, 'compare_campaign_comments', $comments );

	// Generate HTML for the new comment and return it
	$authorName = wp_get_current_user()->display_name; // Get the author's display name
	$timestamp = current_time( 'mysql' ); // Get the current time in MySQL format

	$newCommentHtml = "<div class='compare-campaign-comment'>";
	$newCommentHtml .= "<div class='compare-campaign-comment-header'>";
	$newCommentHtml .= "<span class='compare-campaign-comment-meta'>" . esc_html( $timestamp ) . " by " . esc_html( $authorName ) . "</span>";
	$newCommentHtml .= "<span class='compare-campaign-comment-actions'>";
	$newCommentHtml .= "<i class='fa-solid fa-pencil'></i>"; // Edit icon (if needed)
	$newCommentHtml .= "<i class='fa-solid fa-xmark'></i>"; // Delete icon (if needed)
	$newCommentHtml .= "</span>";
	$newCommentHtml .= "</div>";
	$newCommentHtml .= "<div class='compare-campaign-comment-content'>";
	$newCommentHtml .= $commentContent;
	$newCommentHtml .= "</div>";
	$newCommentHtml .= "</div>";

	wp_send_json_success( [ 'html' => $newCommentHtml ] );
}


add_action( 'wp_ajax_save_edited_compare_comment', 'save_edited_compare_comment_handler' );
function save_edited_compare_comment_handler() {
	check_ajax_referer( 'comparisons', 'security' ); // Verify nonce for security

	$postId = isset( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0;
	$campaignId = isset( $_POST['campaignId'] ) ? sanitize_text_field( $_POST['campaignId'] ) : '';
	$setId = isset( $_POST['setId'] ) ? sanitize_text_field( $_POST['setId'] ) : '';
	$editedComment = isset( $_POST['comment'] ) ? nl2br( esc_html( $_POST['comment'] ) ) : '';
	$commentTimestamp = isset( $_POST['commentTimestamp'] ) ? sanitize_text_field( $_POST['commentTimestamp'] ) : '';

	// Validate received data
	if ( ! $postId || ! $campaignId || ! $setId || ! $commentTimestamp ) {
		wp_send_json_error( [ 'message' => 'Missing required data.' ] );
		return;
	}

	// Retrieve existing comments
	$comments = get_post_meta( $postId, 'compare_campaign_comments', true );
	if ( ! isset( $comments[ $setId ][ $campaignId ] ) ) {
		wp_send_json_error( [ 'message' => 'No comments found for this campaign.' ] );
		return;
	}

	$commentFound = false;
	foreach ( $comments[ $setId ][ $campaignId ] as $key => &$comment ) {
		if ( isset( $comment['timestamp'] ) && $comment['timestamp'] === $commentTimestamp ) {
			$comment['content'] = $editedComment;
			$comment['lastUpdated'] = current_time( 'mysql' ); // Update with current timestamp
			$commentFound = true;
			break;
		}
	}

	if ( $commentFound ) {
		// Save updated comments back to post meta
		$updateResult = update_post_meta( $postId, 'compare_campaign_comments', $comments );

		if ( $updateResult ) {
			$updatedCommentContent = $comment['content'];
			$lastUpdateDisplay = date( 'm/d/y g:ia', strtotime( $comment['lastUpdated'] ) );
			wp_send_json_success( [ 
				'html' => $updatedCommentContent,
				'message' => 'Comment updated successfully.',
				'lastUpdated' => $lastUpdateDisplay // Send back the last updated time
			] );
		} else {
			wp_send_json_error( [ 'message' => 'Failed to update comment.' ] );
		}
	} else {
		wp_send_json_error( [ 'message' => 'Comment not found.' ] );
	}
}



add_action( 'wp_ajax_delete_compare_comment', 'delete_compare_comment_handler' );
function delete_compare_comment_handler() {
	check_ajax_referer( 'comparisons', 'security' ); // Verify nonce for security

	$postId = intval( $_POST['postId'] );
	$campaignId = sanitize_text_field( $_POST['campaignId'] );
	$setId = sanitize_text_field( $_POST['setId'] );
	$commentTimestamp = isset( $_POST['commentTimestamp'] ) ? sanitize_text_field( $_POST['commentTimestamp'] ) : '';

	// Retrieve existing comments
	$comments = get_post_meta( $postId, 'compare_campaign_comments', true );

	// Delete the specific comment
	if ( isset( $comments[ $setId ][ $campaignId ] ) ) {
		foreach ( $comments[ $setId ][ $campaignId ] as $key => $comment ) {
			if ( $comment['timestamp'] === $commentTimestamp ) { // Assuming timestamp is sent as identifier
				unset( $comments[ $setId ][ $campaignId ][ $key ] );
				// Re-index array after deletion
				$comments[ $setId ][ $campaignId ] = array_values( $comments[ $setId ][ $campaignId ] );
				break;
			}
		}

		// Save updated comments back to post meta
		update_post_meta( $postId, 'compare_campaign_comments', $comments );

		wp_send_json_success( [ 'message' => 'Comment deleted successfully.' ] );
	} else {
		wp_send_json_error( [ 'message' => 'Comment not found.' ] );
	}
}


function idwiz_get_comparison_column_buttons( $postId, $setId ) {
	if ( ! $postId || ! $setId ) {
		return false;
	}
	ob_start();
	?>
	<button title="Sort by date DESC" class="wiz-button green centered re-sort-compare-campaigns"
		data-set-id="<?php echo $setId; ?>" data-post-id="<?php echo $postId; ?>"><i class="fa-solid fa-sort"></i> Date
		Sort</button>
	<button title="Expand all campaign cards" class="wiz-button green centered toggle-all-compare-campaigns"
		data-collapse-state="open"><i class="fa-solid fa-square-plus"></i> Expand</button>
	<button title="Collapse all campaign cards" class="wiz-button green centered toggle-all-compare-campaigns"
		data-collapse-state="close"><i class="fa-solid fa-square-minus"></i>
		Collapse</button>
	<button title="Remove all campaigns" class="wiz-button red centered clear-compare-campaigns"
		data-set-id="<?php echo $setId; ?>" data-post-id="<?php echo $postId; ?>"><i class="fa-solid fa-trash"></i>
		Clear</button>
	<?php
	return ob_get_clean();
}

