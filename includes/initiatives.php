<?php
// Hook into main initiatives archive query to make our archive tab work
add_action( 'pre_get_posts', 'idemailwiz_filter_initiatives_by_archive_status' );

function idemailwiz_filter_initiatives_by_archive_status( $query ) {
	// Check if this is the main query, on the frontend, and on the archive page for 'idwiz_initiative'
	if ( $query->is_main_query() && ! is_admin() && $query->is_post_type_archive( 'idwiz_initiative' ) ) {

		// Check if the 'view' GET parameter is set to either "Active" or "Archive"
		if ( isset( $_GET['view'] ) ) {
			$view = $_GET['view'];

			// Meta query args
			$meta_query_args = array();

			if ( $view === 'Active' || $view === null ) {
				$meta_query_args = array(
					'relation' => 'OR',
					array(
						'key' => 'is_archived',
						'compare' => 'NOT EXISTS',  // works when key is not set
					),
					array(
						'key' => 'is_archived',
						'value' => 'true',
						'compare' => '!=',  // Not equal to 'true'
					),
				);
			} elseif ( $view === 'Archive' ) {
				$meta_query_args = array(
					array(
						'key' => 'is_archived',
						'value' => 'true',
						'compare' => '=',  // Equal to 'true'
					),
				);
			}


			// Update the meta_query of the main query
			$query->set( 'meta_query', $meta_query_args );
		}
	}
}

// Archive initiatives
function idemailwiz_archive_initiative() {
	// Check for nonce and security
	if ( ! check_ajax_referer( 'initiatives', 'security', false ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		return;
	}

	// Get the initiative IDs from POST
	$initiativeIds = $_POST['initiativeIds'];

	// Check if initiativeIds are provided
	if ( empty( $initiativeIds ) || ! is_array( $initiativeIds ) ) {
		wp_send_json_error( array( 'message' => 'Invalid initiative IDs' ) );
		return;
	}

	// Loop through each initiative ID to toggle the "is_archived" meta
	$errors = [];
	foreach ( $initiativeIds as $id ) {
		$is_archived = get_post_meta( $id, 'is_archived', true );

		if ( $is_archived === '' ) {
			// Meta does not exist, add it as true
			if ( ! add_post_meta( $id, 'is_archived', 'true', true ) ) {
				$errors[] = "Failed to archive post with ID: $id";
			}
		} elseif ( $is_archived === 'true' ) {
			// Meta exists and is true, update to false
			if ( ! update_post_meta( $id, 'is_archived', 'false' ) ) {
				$errors[] = "Failed to unarchive post with ID: $id";
			}
		} else {
			// Meta exists and is false, update to true
			if ( ! update_post_meta( $id, 'is_archived', 'true' ) ) {
				$errors[] = "Failed to archive post with ID: $id";
			}
		}
	}

	if (! empty($errors)) {
		wp_send_json_error(array('message' => 'Some initiatives could not be archived/unarchived', 'errors' => $errors));
	} else {
		wp_send_json_success(array('message' => 'Successfully archived/unarchived initiatives'));
	}
}
add_action( 'wp_ajax_idemailwiz_archive_initiative', 'idemailwiz_archive_initiative' );

function idemailwiz_create_new_initiative() {
	// Check for nonce and security
	if ( ! check_ajax_referer( 'initiatives', 'security', false ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		return;
	}

	// Fetch title from POST
	$title = $_POST['newInitTitle'];

	// Validate that the title is not empty
	if ( empty( $title ) ) {
		wp_send_json_error( array( 'message' => 'The title cannot be empty' ) );
		return;
	}

	// Create new initiative post
	$post_id = wp_insert_post(
		array(
			'post_title' => $title,
			'post_type' => 'idwiz_initiative',
			'post_status' => 'publish',
		)
	);

	if ( $post_id > 0 ) {
		wp_send_json_success( array( 'message' => 'Initiative created successfully', 'post_id' => $post_id ) );
	} else {
		wp_send_json_error( array( 'message' => 'Failed to create the initiative' ) );
	}
}
add_action( 'wp_ajax_idemailwiz_create_new_initiative', 'idemailwiz_create_new_initiative' );

function idemailwiz_delete_initiative() {
	// Check for nonce and security
	if ( ! check_ajax_referer( 'initiatives', 'security', false ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		return;
	}

	// Fetch selected IDs from POST
	$selectedIds = $_POST['selectedIds'];

	foreach ( $selectedIds as $post_id ) {
		wp_delete_post( $post_id, true ); // Set second parameter to false if you don't want to force delete
		// Remove initiative from any campaigns that have it
		$campaign_ids = idemailwiz_get_campaign_ids_for_initiative($post_id );
		foreach ($campaign_ids as $campaign_id) {
		idemailwiz_update_campaign_initiative_relationship($campaign_id, $post_id, 'remove');
		}
	}

	

	wp_send_json_success( array( 'message' => 'Initiatives deleted successfully' ) );
}
add_action( 'wp_ajax_idemailwiz_delete_initiative', 'idemailwiz_delete_initiative' );







function get_idwiz_initiative_daterange( $initiative_post_id ) {
	$campaign_ids = idemailwiz_get_campaign_ids_for_initiative( $initiative_post_id );

	if ( empty( $campaign_ids ) ) {
		$return['error'] = 'No campaigns found!';
		return $return;
	}

	$campaigns = get_idwiz_campaigns( array( 'campaignIds' => $campaign_ids ) );
	$start_dates = array_column( $campaigns, 'startAt' );
	$min_start_date = min( $start_dates );
	$max_start_date = max( $start_dates );

	$return['startDate'] = date( 'm/d/Y', intval($min_start_date / 1000) );
	$return['endDate'] = date( 'm/d/Y', intval($max_start_date / 1000) );
	return $return;
}

// Add or remove the campaign initiative relationship
function idemailwiz_update_campaign_initiative_relationship( $campaignID, $initiativeID, $action ) {
	global $wpdb;
	$table_name = $wpdb->prefix . "idemailwiz_init_campaigns";

	// Start transaction
	$wpdb->query( 'START TRANSACTION' );

	// Perform the specified action
	if ( $action === 'add' ) {
		$result = $wpdb->insert( $table_name, [ 'campaignId' => $campaignID, 'initiativeId' => $initiativeID ], [ '%d', '%d' ] );
	} elseif ( $action === 'remove' ) {
		$result = $wpdb->delete( $table_name, [ 'campaignId' => $campaignID, 'initiativeId' => $initiativeID ], [ '%d', '%d' ] );
	} else {
		return [ 'success' => false, 'message' => 'Invalid action specified' ];
	}

	// Check for failure and rollback if necessary
	if ( $result === false ) {
		$wpdb->query( 'ROLLBACK' );
		return [ 'success' => false, 'message' => "Failed to {$action} relationship" ];
	}
	

	// Commit transaction
	$wpdb->query( 'COMMIT' );

	return [ 'success' => true, 'message' => "Successfully {$action}ed relationship" ];
}


// Utility function to check if a campaign-initiative relationship exists
function idemailwiz_check_campaign_initiative_relationship( $campaignID, $initiativeID ) {
	global $wpdb;
	$table_name = $wpdb->prefix . "idemailwiz_init_campaigns";
	return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE campaignId = %d AND initiativeId = %d", $campaignID, $initiativeID ) ) > 0;
}

// Utility function to get all campaign IDs for a given initiative ID
function idemailwiz_get_campaign_ids_for_initiative( $initiativeID ) {
	global $wpdb;
	$table_name = $wpdb->prefix . "idemailwiz_init_campaigns";
	return $wpdb->get_col( $wpdb->prepare( "SELECT campaignId FROM $table_name WHERE initiativeId = %d", $initiativeID ) );
}

// Utility function to get the first, and only first, initiative ID for a given campaign ID
function idemailwiz_get_initiative_id_for_campaign( $campaignID ) {
	global $wpdb;
	$table_name = $wpdb->prefix . "idemailwiz_init_campaigns";
	return $wpdb->get_var( $wpdb->prepare( "SELECT initiativeId FROM $table_name WHERE campaignId = %d", $campaignID ) );
}
// Utility function to get ALL initiative IDs for a given campaign ID
function idemailwiz_get_initiative_ids_for_campaign( $campaignID ) {
	global $wpdb;
	$table_name = $wpdb->prefix . "idemailwiz_init_campaigns";
	$results = $wpdb->get_results( $wpdb->prepare( "SELECT initiativeId FROM $table_name WHERE campaignId = %d", $campaignID ), ARRAY_A );

	return array_map( function ($item) {
		return $item['initiativeId'];
	}, $results );
}


// Manages the relationship between campaigns and initiatives
function idemailwiz_manage_campaign_initiative_relationship( $action, $campaignID, $initiativeID ) {
	$response = [ 
		'success' => false,
		'message' => ''
	];

	$relationship_exists = idemailwiz_check_campaign_initiative_relationship( $campaignID, $initiativeID );

	if ( $action == 'add' ) {
		if ( ! $relationship_exists ) {
			$addRelationship = idemailwiz_update_campaign_initiative_relationship( $campaignID, $initiativeID, 'add' );
			if ( $addRelationship['success'] === true ) {
				$response['success'] = true;
				$response['message'] = $addRelationship['message'];
				// Update the image meta field
				idwiz_add_or_remove_campaign_assets_from_initiative( $initiativeID );
			} else {
				$response['message'] = $addRelationship['message'];
			}
		} else {
			$response['message'] = 'This campaign has already been added to the initiative!';
		}
	} elseif ( $action == 'remove' ) {
		if ( $relationship_exists ) {
			$removeRelationship = idemailwiz_update_campaign_initiative_relationship( $campaignID, $initiativeID, 'remove' );
			if ( $removeRelationship['success'] === true ) {
				$response['success'] = true;
				$response['message'] = $removeRelationship['message'];
				// Update the image meta field
				idwiz_add_or_remove_campaign_assets_from_initiative( $initiativeID );
			} else {
				$response['message'] = $removeRelationship['message'];
			}
		} else {
			$response['message'] = 'This campaign is not part of the initiative.';
		}
	}

	return $response;
}



add_action( 'wp_ajax_idemailwiz_add_remove_campaign_from_initiative', 'idemailwiz_add_remove_campaign_from_initiative' );
function idemailwiz_add_remove_campaign_from_initiative() {
	global $wpdb;
	$response = [ 
		'success' => true,
		'message' => '',
		'data' => []
	];

	$isValidNonce = check_ajax_referer( 'data-tables', 'security', false )
		|| check_ajax_referer( 'initiatives', 'security', false )
		|| check_ajax_referer( 'metrics', 'security', false );
	if ( ! $isValidNonce ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce.' ] );
		return;
	}

	$campaignIDs = $_POST['campaign_ids'];
	if ( ! is_array( $campaignIDs ) ) {
		$campaignIDs = [ $campaignIDs ];
	}
	$initiativeID = intval( $_POST['initiative_id'] );
	$action = $_POST['campaignAction'];

	if ( $action != 'add' && $action != 'remove' ) {
		wp_send_json_error( [ 'message' => 'Invalid action.' ] );
		return;
	}

	$messages = [];
	$successCount = 0;

	foreach ( $campaignIDs as $campaignID ) {
		$result = idemailwiz_manage_campaign_initiative_relationship( $action, $campaignID, $initiativeID );
		if ( $result['success'] ) {
			$successCount++;
		}
		$messages[] = $result['message'];
	}

	if ( $successCount == count( $campaignIDs ) ) {
		$response['message'] = "All campaigns successfully processed for action: {$action}.";
	} else {
		$response['success'] = false;
		$response['message'] = "Some campaigns could not be processed for action: {$action}.";
	}

	$response['data'] = [ 
		'successCount' => $successCount,
		'totalCount' => count( $campaignIDs ),
		'messages' => $messages,
		'action' => $action
	];

	wp_send_json( $response );
}


// Runs when a campaign is added or removed from an initiative.
// Adds or removes images URLs based on images found in each campaign template
function idwiz_add_or_remove_campaign_assets_from_initiative( $initiativeId ) {
	// Get the campaign IDs for the given initiative ID
	$campaignIds = idemailwiz_get_campaign_ids_for_initiative( $initiativeId );

	// Extract image data for these campaign IDs
	$allCampaignImageData = idwiz_extract_campaigns_images( $campaignIds );

	// De-duplicate the image data and flatten it into a single array
	$uniqueImageData = [];
	foreach ( $allCampaignImageData as $campaignId => $imageData ) {
		foreach ( $imageData as $data ) {
			$uniqueImageData[ md5( $data['src'] . $data['alt'] ) ] = $data;
		}
	}

	// Get the existing meta data, if any
	$existingMetaData = get_post_meta( $initiativeId, 'wizinitiative_assets', true );
	if ( ! is_array( $existingMetaData ) ) {
		$existingMetaData = [];
	}

	// Compare existing meta data and new data, and update accordingly
	$newKeys = array_keys( $uniqueImageData );
	$existingKeys = array_keys( $existingMetaData );
	$keysToAdd = array_diff( $newKeys, $existingKeys );
	$keysToRemove = array_diff( $existingKeys, $newKeys );

	// Remove entries
	foreach ( $keysToRemove as $key ) {
		unset( $existingMetaData[ $key ] );
	}

	// Add new entries
	foreach ( $keysToAdd as $key ) {
		$existingMetaData[ $key ] = $uniqueImageData[ $key ];
	}

	// Update the custom meta field
	update_post_meta( $initiativeId, 'wizinitiative_assets', $existingMetaData );

	return true;
}

add_action( 'wp_ajax_idwiz_get_initiative_titles', 'idwiz_get_initiative_titles' );

function idwiz_get_initiative_titles() {

	// TODO: Add nonce verification and other security measures here

	$initiative_ids = $_POST['initiative_ids'] ?? array();

	$links = [];

	foreach ( $initiative_ids as $id ) {
		$title = get_the_title( intval( $id ) );
		$url = get_permalink( intval( $id ) );
		$links[ $id ] = '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
	}

	echo json_encode( [ 'links' => $links ] );

	wp_die(); // Ensure to terminate immediately and return a proper response
}

function display_init_date_range( $associated_campaign_ids ) {
	if ( empty( $associated_campaign_ids ) ) {
		return '';
	}
	$initDateRange = get_idwiz_initiative_daterange( get_the_ID() );
	if ( ! isset( $initDateRange['error'] ) ) {
		return $initDateRange['startDate'] . ' - ' . $initDateRange['endDate'];
	}
}

function generate_initiative_flags( $campaignId ) {
	$initIds = idemailwiz_get_initiative_ids_for_campaign( $campaignId );
	ob_start(); // Start output buffering
	?>
	<div class="campaign-meta-flags">
		<?php if ( $initIds ) { ?>
			<?php foreach ( $initIds as $initId ) : ?>
				<span class="campaign-meta-flag">
					<a href="<?php echo get_the_permalink( $initId ); ?>" title="Go to Initiative">
						<?php echo get_the_title( $initId ); ?>
					</a>
					<span class="remove-initiative-from-campaign remove-meta-icon fa fa-times" data-action="remove" data-initid="<?php echo $initId; ?>"
						data-campaignid="<?php echo $campaignId; ?>" title="Remove campaign from Initiative">
					</span>
				</span>
			<?php endforeach; ?>
		<?php } else {
			echo '<span class="no-meta-message">No connected initiatives</span>';
		} ?>
		<span class="add-initiative-to-campaign add-meta-icon fa fa-plus" data-action="add" data-initids='<?php echo json_encode( $initIds ); ?>'
			data-campaignid="<?php echo $campaignId; ?>" class="add-edit-campaign-initiative" title="Add campaign to initiative">
		</span>
	</div>
	<?php
	return ob_get_clean(); // End output buffering and return the captured HTML
}