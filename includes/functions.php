<?php
//Insert overlay and spinner into header
function insert_overlay_loader() {
	//$options = get_option( 'idemailwiz_settings' );
	if ( ( is_single() && get_post_type() == 'idemailwiz_template' ) ) {
		?>
		<div id="iDoverlay"></div>
		<div id="iDspinner" class="loader"></div>
		<?php
	}
	?>
	<script type="text/javascript">
		// Function to show and hide overlays and spinners
		const toggleOverlay = (show = true) => {
			if (show) {
				jQuery("#iDoverlay").fadeIn(100);
				jQuery("#iDspinner").fadeIn(250);
			} else {
				jQuery("#iDoverlay").fadeOut(100);
				jQuery("#iDspinner").hide();
			}
		};

		// Call toggleOverlay() as soon as the script is executed
		toggleOverlay();
	</script>
	<?php
}
add_action( 'wp_head', 'idemailwiz_head' );
function idemailwiz_head() {
	// Preload overlay stuff so it happens fast
	insert_overlay_loader();

	//Add meta to prevent scaling on mobile (for DataTables)
	echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">';
}


// Determine if a template or folder is in the current user's favorites
function is_user_favorite( $object_id, $object_type ) {
	// Determine the meta key based on the object_type
	$meta_key = 'idwiz_favorite_' . strtolower( $object_type ) . 's'; // either 'favorite_templates' or 'favorite_folders'

	$favorites = get_user_meta( get_current_user_id(), $meta_key, true );

	if ( ! is_array( $favorites ) ) {
		$favorites = array();
	}

	// Cast IDs in favorites to integers for consistent comparison
	$favorites = array_map( 'intval', $favorites );

	$object_id = intval( $object_id ); // Ensure object_id is an integer

	// Check if $object_id is in favorites
	if ( in_array( $object_id, $favorites ) ) {
		return true;
	}

	return false;
}

//Category page breadcrumb
function display_folder_hierarchy() {
	$queried_object = get_queried_object();

	if ( $queried_object instanceof WP_Term ) {
		// Handle term archives
		$term_links = array();

		while ( $queried_object ) {
			if ( ! is_wp_error( $queried_object ) ) {
				if ( $queried_object->term_id == get_queried_object_id() ) {
					$term_links[] = '<span>' . $queried_object->name . '</span>';
				} else {
					$term_links[] = '<a href="' . get_term_link( $queried_object->term_id ) . '">' . $queried_object->name . '</a>';
				}
				$queried_object = get_term( $queried_object->parent, 'idemailwiz_folder' ); // Replace 'idemailwiz_folder' with your taxonomy slug
			} else {
				break;
			}
		}

		$term_links = array_reverse( $term_links );
		echo implode( ' > ', $term_links );
	} elseif ( $queried_object instanceof WP_Post_Type ) {
		// Handle post type archives
		echo '<span>' . $queried_object->labels->name . '</span>';
	}
}

//Single Template breadcrumb
function display_template_folder_hierarchy( $post_id ) {
	$terms = get_the_terms( $post_id, 'idemailwiz_folder' ); // Replace 'idemailwiz_folder' with your taxonomy slug
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return;
	}
	$assigned_term = $terms[0];
	$term_links = array();

	while ( $assigned_term ) {
		if ( ! is_wp_error( $assigned_term ) ) {
			$term_links[] = '<a href="' . get_term_link( $assigned_term->term_id ) . '">' . $assigned_term->name . '</a>';
			$assigned_term = get_term( $assigned_term->parent, 'idemailwiz_folder' ); // Replace 'idemailwiz_folder' with your taxonomy slug
		} else {
			break;
		}
	}

	$term_links = array_reverse( $term_links );
	echo implode( ' > ', $term_links );
}

// Generate a drop-down list of folders
function id_generate_folders_select( $parent_id = 0, $prefix = '' ) {
	$options = '';

	$folders = get_terms( array( 'taxonomy' => 'idemailwiz_folder', 'parent' => $parent_id, 'hide_empty' => false ) );

	foreach ( $folders as $folder ) {
		//skips the trash folder if it exists
		$siteOptions = get_option( 'idemailwiz_settings' );
		$trashTerm = (int) $siteOptions['folder_trash'];
		if ( $folder->term_id == $trashTerm ) {
			continue;
		}
		$name = $folder->name;
		$options .= '<option value="' . $folder->term_id . '">' . $prefix . $name . '</option>';
		$options .= id_generate_folders_select( $folder->term_id, '&nbsp;&nbsp;' . $prefix . '-&nbsp;&nbsp;' );
	}

	return $options;
}

function id_generate_folders_select_ajax() {
	//check nonce (could be one of two files)
	$nonceCheck = check_ajax_referer( 'folder-actions', 'security', false );
	if ( ! $nonceCheck ) {
		check_ajax_referer( 'template-actions', 'security' );
	}

	$options = id_generate_folders_select();
	wp_send_json_success( array( 'options' => $options ) );
	wp_die();
}

add_action( 'wp_ajax_id_generate_folders_select_ajax', 'id_generate_folders_select_ajax' );


// Template select2 ajax handler
function idemailwiz_get_templates_for_select() {
	check_ajax_referer( 'id-general', 'security' );

	$searchTerm = $_POST['q'];

	$allTemplates = get_posts( array( 'post_type' => 'idemailwiz_template', 'posts_per_page' => -1, 's' => $searchTerm ) );
	$data = [];
	$cnt = 0;
	foreach ( $allTemplates as $template ) {
		$data[ $cnt ]['id'] = $template->ID;
		$data[ $cnt ]['text'] = $template->post_title;
		$cnt++;
	}
	//error_log(print_r($data, true));
	echo json_encode( array_values( $data ) );
	wp_die();
}
add_action( 'wp_ajax_idemailwiz_get_templates_for_select', 'idemailwiz_get_templates_for_select' );


// Initiaves select2 ajax handler


function idemailwiz_get_initiatives_for_select() {
	// Check for nonce and security
	if ( ! check_ajax_referer( 'initiatives', 'security', false )
		&& ! check_ajax_referer( 'id-general', 'security', false )
		&& ! check_ajax_referer( 'data-tables', 'security', false ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		return;
	}

	$searchTerm = isset( $_POST['q'] ) ? $_POST['q'] : '';

	// Fetch initiatives
	$allInitiatives = get_posts( array(
		'post_type' => 'idwiz_initiative', // Ensure this matches your actual custom post type name
		'posts_per_page' => -1,
		's' => $searchTerm
	) );

	// Prepare data
	$data = array_map( function ($initiative) {
		return array(
			'id' => $initiative->ID,
			'text' => $initiative->post_title
		);
	}, $allInitiatives );

	// Return JSON-encoded data
	echo json_encode( array_values( $data ) );
	wp_die();
}
add_action( 'wp_ajax_idemailwiz_get_initiatives_for_select', 'idemailwiz_get_initiatives_for_select' );



function idemailwiz_mergemap() {

	$mergeMapping = array(
		'{{{snippet "FirstName" "your child"}}}' => 'Garfield',
		'{{{snippet "FirstName" "Your child"}}}' => 'Garfield',
		'{{{snippet "FirstName" "Your Child"}}}' => 'Garfield',
		'{{{snippet "pronoun" "S"}}}' => 'he',
		'{{{snippet "pronoun" "O"}}}' => 'him',
		'{{{snippet "pronoun" "SP"}}}' => 'his',
		'{{{snippet "pronoun" "OP"}}}' => 'his',
		'{{{snippet "Pronoun" "S"}}}' => 'He',
		'{{{snippet "Pronoun" "O"}}}' => 'Him',
		'{{{snippet "Pronoun" "SP"}}}' => 'His',
		'{{{snippet "Pronoun" "OP"}}}' => 'His',
	);

	return $mergeMapping;
}




//Add custom meta metabox back to edit screens 	
add_filter( 'acf/settings/remove_wp_meta_box', '__return_false' );


// Extract Image URLs and alt values from a set of campaigns
function idwiz_extract_campaigns_images( $campaignIds = [] ) {
	if ( ! $campaignIds || empty( $campaignIds ) ) {
		return array();
	}
	// Initialize an array to store image data for all campaigns
	$allCampaignImageData = [];

	// Fetch templates for the given campaign IDs
	$templates = get_idwiz_templates( [ 'campaignIds' => $campaignIds ] );

	// Loop through each template to extract image information
	foreach ( $templates as $template ) {
		$templateHTML = $template['html'];

		// Load HTML content into a DOMDocument object
		$dom = new DOMDocument;
		@$dom->loadHTML( $templateHTML );

		// Initialize an array to store image data for this specific template
		$templateImageData = [];

		// Loop through all the <img> tags in this template
		$images = $dom->getElementsByTagName( 'img' );
		foreach ( $images as $image ) {
			$src = $image->getAttribute( 'src' );
			$alt = $image->getAttribute( 'alt' ) ?? '';
			$templateImageData[] = [ 'src' => $src, 'alt' => $alt ];
		}

		// Save the image data for this campaign
		$allCampaignImageData[ $template['templateId'] ] = $templateImageData;
	}

	return $allCampaignImageData;
}




add_action( 'wp_ajax_idwiz_fetch_base_templates', 'idwiz_fetch_base_templates' );

function idwiz_fetch_base_templates() {
	// Verify nonce
	if ( ! check_ajax_referer( 'id-general', 'security', false ) ) {
		wp_send_json_error( array( 'message' => 'Nonce verification failed.' ) );
		return;
	}

	// Initialize HTML strings for different types of templates
	$initiative_html = '';
	$layout_html = '';

	// Get the term by slug
	$base_template_term = get_term_by( 'slug', 'base-templates', 'idemailwiz_folder' );

	// Check if the term exists and is not an error
	if ( $base_template_term && ! is_wp_error( $base_template_term ) ) {

		// Define WP_Query arguments
		$args = array(
			'post_type' => 'idemailwiz_template',
			'tax_query' => array(
				array(
					'taxonomy' => 'idemailwiz_folder',
					'field' => 'term_id',
					'terms' => $base_template_term->term_id,
				),
			),
		);

		// Execute the query
		$query = new WP_Query( $args );

		// Loop through the posts and construct the HTML
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id = get_the_ID();
				$title = get_the_title();
				$mockups = get_field( 'template_mock-ups', $post_id );
				$initiative = get_field( 'base_template_for_initiative', $post_id );

				$dtMockup = $mockups['mock-up-image-desktop'] ?? '';
				$previewMockup = $dtMockup ? '<div class="create-from-template-mockup"><img src="' . $dtMockup . '"/></div>' : '';

				$template_html = "<div class='startTemplate' data-postid='{$post_id}'>
                                    <h4>{$title}</h4>
                                    {$previewMockup}
                                  </div>";

				if ( $initiative ) {
					$initiative_html .= $template_html;
				} else {
					$layout_html .= $template_html;
				}
			}
			wp_reset_postdata();
		}
	}

	$final_html = '<div class="swalTabs">
                     <ul>
                       <li><a href="#initiativeTemplates">Initiative Templates</a></li>
                       <li><a href="#layoutTemplates">Layout Templates</a></li>
                     </ul>
                     <div id="initiativeTemplates" class="templateSelectWrap">' . $initiative_html . '</div>
                     <div id="layoutTemplates" class="templateSelectWrap">' . $layout_html . '</div>
                   </div>';

	// Send the HTML as a successful AJAX response
	wp_send_json_success( array( 'html' => $final_html ) );
}



// Add or remove a favorite template or folder from a user's profile
function add_remove_user_favorite() {
	//check nonce
	if (
		check_ajax_referer( 'template-actions', 'security', false )
		|| check_ajax_referer( 'user-favorites', 'security', false )
		|| check_ajax_referer( 'initiatives', 'security', false )
	) {
	} else {
		wp_die( 'Invalid nonce' );
	}
	;

	// Ensure object_id and object_type are set
	$object_id = isset( $_POST['object_id'] ) ? intval( $_POST['object_id'] ) : 0;
	$object_type = isset( $_POST['object_type'] ) ? sanitize_text_field( $_POST['object_type'] ) : '';

	if ( $object_id <= 0 || empty( $object_type ) ) {
		wp_send_json(
			array(
				'success' => false,
				'message' => 'Invalid object id or object type was sent!',
				'action' => null,
				'objectid' => $object_id,
			)
		);
	}

	// Determine the meta key based on the object_type
	$meta_key = 'idwiz_favorite_' . strtolower( $object_type ) . 's'; // either 'idwiz_favorite_templates' or 'idwiz_favorite_folders'

	$favorites = get_user_meta( get_current_user_id(), $meta_key, true );

	if ( ! is_array( $favorites ) ) {
		$favorites = array();
	}

	$success = false;
	$message = '';
	$action = '';

	$key = array_search( $object_id, $favorites );
	if ( false !== $key ) {
		unset( $favorites[ $key ] );
		$message = 'Favorite ' . $object_type . ' removed.';
		$action = 'removed';
	} else {
		$favorites[] = intval( $object_id ); // Ensure object_id is an integer
		$message = 'Favorite ' . $object_type . ' added.';
		$action = 'added';
	}
	$success = true;

	if ( $success ) {
		$update_status = update_user_meta( get_current_user_id(), $meta_key, $favorites );
		if ( $update_status === false ) {
			$success = false;
			$message = 'Failed to update user meta.';
		} else {
			$updated_favorites = get_user_meta( get_current_user_id(), $meta_key, true );
			if ( ! is_array( $updated_favorites ) ) {
				$success = false;
				$message = 'User meta was updated but the structure is incorrect.';
			} else {
				// Check if the object_id was correctly added or removed
				if ( $action === 'added' && ! in_array( $object_id, $updated_favorites ) ) {
					$success = false;
					$message = 'Object id was not added correctly to ' . $object_type . '.';
				} elseif ( $action === 'removed' && in_array( $object_id, $updated_favorites ) ) {
					$success = false;
					$message = 'Object id was not removed correctly from ' . $object_type . '.';
				}
			}
		}
	}

	wp_send_json(
		array(
			'success' => $success,
			'message' => $message,
			'action' => $action,
			'objectid' => $object_id,
		)
	);
}

add_action( 'wp_ajax_add_remove_user_favorite', 'add_remove_user_favorite' );

function generate_mini_table(
	array $headers,
	array $data,
	string $tableClass = '',
	string $scrollWrapClass = ''
) {
	if ( empty( $data ) ) {
		echo 'No data available';
	} else {
		// Table with sticky header
		echo '<table class="wizcampaign-tiny-table ' . $tableClass . '">';
		echo '<thead><tr>';
		foreach ( $headers as $col => $width ) {
			echo '<th width="' . $width . '">' . $col . '</th>';
		}
		echo '</tr></thead>';

		echo '<tbody>';


		// Table rows
		foreach ( $data as $row ) {
			echo '<tr>';
			foreach ( $headers as $col => $width ) {
				$value = $row[ $col ] instanceof RawHtml ? (string) $row[ $col ] : htmlspecialchars( $row[ $col ] );
				echo '<td width="' . $width . '">' . $value . '</td>';

			}
			echo '</tr>';
		}
	}

	echo '</tbody>';
	echo '</table>';
}


function prepare_promo_code_summary_data( $purchases ) {
	// Initialize variables and prepare data based on your existing logic for promo codes
	$promoCounts = [];
	$totalOrders = [];
	$ordersWithPromo = [];

	foreach ( $purchases as $purchase ) {
		$promo = $purchase['shoppingCartItems_discountCode'];
		$orderID = $purchase['id'];

		// Keep track of all unique order IDs
		$totalOrders[ $orderID ] = true;

		// Skip blank or null promo codes
		if ( empty( $promo ) ) {
			continue;
		}

		// Keep track of unique order IDs with promo codes
		$ordersWithPromo[ $orderID ] = true;

		if ( ! isset( $promoCounts[ $promo ] ) ) {
			$promoCounts[ $promo ] = [];
		}

		if ( ! isset( $promoCounts[ $promo ][ $orderID ] ) ) {
			$promoCounts[ $promo ][ $orderID ] = 0;
		}

		$promoCounts[ $promo ][ $orderID ] += 1;
	}

	// Calculate the total number of times each promo code was used
	$promoUseCounts = [];
	foreach ( $promoCounts as $promo => $orders ) {
		$promoUseCounts[ $promo ] = count( $orders );
	}

	// Sort promo codes by usage
	arsort( $promoUseCounts );

	// Calculate promo code usage statistics
	$totalOrderCount = count( $totalOrders );
	$ordersWithPromoCount = count( $ordersWithPromo );
	$percentageWithPromo = ( $totalOrderCount > 0 ) ? ( $ordersWithPromoCount / $totalOrderCount ) * 100 : 0;

	// Headers for the promo code table
	$promoHeaders = [ 
		'Promo Code' => '80%',
		'Orders' => '20%'
	];

	$promoData = [];
	foreach ( $promoUseCounts as $promo => $useCount ) {
		$promoData[] = [ 
			'Promo Code' => htmlspecialchars( $promo ),
			'Orders' => $useCount
		];
	}

	return [ 
		'ordersWithPromoCount' => $ordersWithPromoCount,
		'totalOrderCount' => $totalOrderCount,
		'percentageWithPromo' => number_format( $percentageWithPromo ),
		// Not rounding here
		'promoHeaders' => $promoHeaders,
		'promoData' => $promoData
	];
}

function idwiz_get_orders_from_purchases( $purchases ) {
	$orders = [];
	foreach ( $purchases as $purchase ) {
		if ( isset( $orders[ $purchase['orderId'] ] ) ) {
			$orders[ $purchase['orderId'] ][] = $purchase;
		} else {
			$orders[ $purchase['orderId'] ] = [ $purchase ];
		}
	}

	return $orders;
}

function get_idwiz_revenue( $startDate, $endDate, $campaignTypes = [ 'Triggered', 'Blast' ], $wizCampaignIds = null, $useGa = false ) {


	if ( ! is_array( $wizCampaignIds ) || empty( $wizCampaignIds ) ) {
		$checkCampaignArgs = [ 'type' => $campaignTypes, 'fields' => 'id' ];
		$wizCampaigns = get_idwiz_campaigns( $checkCampaignArgs );
		$wizCampaignIds = array_column( $wizCampaigns, 'id' );
	}

	$totalRevenue = 0;

	if ( $useGa ) {
		$allChannelPurchases = get_idwiz_ga_data( [ 'startDate' => $startDate, 'endDate' => $endDate ] );
		$purchases = array_filter( $allChannelPurchases, fn( $purchase ) => in_array( $purchase['campaignId'], $wizCampaignIds ) );
		if ( ! $purchases ) {
			return 0;
		}
		$revenue = array_sum( array_column( $purchases, 'revenue' ) );
	} else {
		$purchaseArgs = [ 'startAt_start' => $startDate, 'startAt_end' => $endDate, 'campaignIds' => $wizCampaignIds, 'fields' => 'id,campaignId,purchaseDate,total' ];
		$purchases = get_idwiz_purchases( $purchaseArgs );

		if ( ! $purchases ) {
			return 0;
		}

		$uniqueIds = [];

		$revenue = 0;
		//error_log(print_r($purchases, true));

		foreach ( $purchases as $purchase ) {
			if ( in_array( $purchase['id'], $uniqueIds ) ) {
				continue;
			}

			if ( ! isset( $purchase['campaignId'] ) ) {
				continue;
			}

			$wizCampaign = get_idwiz_campaign( $purchase['campaignId'] );

			if ( ! $wizCampaign ) {
				continue;
			}

			if ( isset( $campaignTypes ) && ! in_array( $wizCampaign['type'], $campaignTypes ) ) {
				continue;
			}

			$revenue += $purchase['total'];
			$uniqueIds[] = $purchase['id'];
		}
	}

	$totalRevenue += $revenue;

	return $totalRevenue;
}





function get_idwiz_header_tabs( $tabs, $currentActiveItem ) {
	echo '<div id="header-tabs">';
	foreach ( $tabs as $tab ) {
		$title = $tab['title'];
		$view = $tab['view'];
		$isActive = ( $currentActiveItem == $view ) ? 'active' : '';
		$url = add_query_arg( [ 'view' => $view, 'wizMonth' => false, 'wizYear' => false ] );
		echo "<a href=\"{$url}\" class=\"campaign-tab {$isActive}\">{$title}</a>";
	}
	echo '</div>';
}




function handle_experiment_winner_toggle() {

	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_experiments';


	// Security checks and validation
	if ( ! check_ajax_referer( 'wiz-metrics', 'security', false ) ) {
		error_log( 'Nonce check failed' );
		wp_send_json_error( 'Nonce check failed' );
		return;
	}

	$action = $_POST['actionType'];
	$templateId = intval( $_POST['templateId'] );
	$experimentId = intval( $_POST['experimentId'] );

	if ( ! $templateId || ! $experimentId ) {
		error_log( 'Invalid templateId or experimentId' );
		wp_send_json_error( 'Invalid templateId or experimentId' );
		return;
	}

	if ( $action == 'add-winner' ) {

		// Clear existing winners for the same experimentId
		$result = $wpdb->update(
			$table_name,
			array( 'wizWinner' => null ),
			array( 'experimentId' => $experimentId )
		);

		if ( $result === false ) {
			error_log( "Database error while clearing winners: " . $wpdb->last_error );
			wp_send_json_error( "Database error while clearing winners: " . $wpdb->last_error );
			return;
		}

		// Set new winner
		$result = $wpdb->update(
			$table_name,
			array( 'wizWinner' => 1 ),
			array( 'templateId' => $templateId )
		);

		if ( $result === false ) {
			error_log( "Database error while setting new winner: " . $wpdb->last_error );
			wp_send_json_error( "Database error while setting new winner: " . $wpdb->last_error );
			return;
		}

	} elseif ( $action == 'remove-winner' ) {

		// Remove winner
		$result = $wpdb->update(
			$table_name,
			array( 'wizWinner' => null ),
			array( 'templateId' => $templateId )
		);

		if ( $result === false ) {
			error_log( "Database error while removing winner: " . $wpdb->last_error );
			wp_send_json_error( "Database error while removing winner: " . $wpdb->last_error );
			return;
		}

	} else {
		error_log( 'Invalid action: ' . $action );
		wp_send_json_error( 'Invalid action' );
		return;
	}

	wp_send_json_success( 'Action completed successfully' );
}

add_action( 'wp_ajax_handle_experiment_winner_toggle', 'handle_experiment_winner_toggle' );



add_action( 'wp_ajax_save_experiment_notes', 'save_experiment_notes' );

function save_experiment_notes() {
	// Security checks and validation
	if ( ! check_ajax_referer( 'wiz-metrics', 'security', false ) ) {
		error_log( 'Nonce check failed' );
		wp_send_json_error( 'Nonce check failed' );
		return;
	}

	// Get the experiment notes and ID
	$experimentId = isset( $_POST['experimentId'] ) ? sanitize_text_field( $_POST['experimentId'] ) : '';

	$allowed_tags = array(
		'br' => array(),
		// Add other tags if you wish to allow them
	);
	$experimentNotes = isset( $_POST['experimentNotes'] ) ? wp_kses( $_POST['experimentNotes'], $allowed_tags ) : '';

	// Database update logic
	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_experiments';

	// Update experimentNotes for all records with the same experiment ID
	$result = $wpdb->update(
		$table_name,
		array( 'experimentNotes' => $experimentNotes ),
		array( 'experimentId' => (int) $experimentId )
	);

	if ( $wpdb->last_error ) {
		error_log( "Database error: " . $wpdb->last_error );
		wp_send_json_error( 'Database error: ' . $wpdb->last_error );
		return;
	}

	if ( $result !== false ) {
		if ( $result > 0 ) {
			wp_send_json_success( 'Data saved successfully' );
		} else {
			wp_send_json_error( 'No data was updated, the new value may be the same as the existing value' );
		}
	} else {
		wp_send_json_error( 'An error occurred while updating the database' );
	}
}







function transfigure_purchases_by_product( $purchases ) {
	$data = [];
	$products = array();
	$productRevenue = array();
	$productTopics = array();

	foreach ( $purchases as $purchase ) {
		$product = $purchase['shoppingCartItems_name'];

		if ( ! isset( $products[ $product ] ) ) {
			$products[ $product ] = 0;
			$productRevenue[ $product ] = 0;
			$productTopics[ $product ] = str_replace( ',', ', ', $purchase['shoppingCartItems_categories'] ); // Add spaces after commas
		}

		$products[ $product ]++;
		$productRevenue[ $product ] += $purchase['shoppingCartItems_price'];
	}

	// Sort products by the number of purchases in descending order
	arsort( $products );

	// Prepare the data for the table
	foreach ( $products as $productName => $purchaseCount ) {
		$data[] = [ 
			'Product' => $productName,
			'Topics' => $productTopics[ $productName ],
			'Purchases' => $purchaseCount,
			'Revenue' => '$' . number_format( $productRevenue[ $productName ], 2 )
		];
	}

	return $data;
}




/**
 * Retrieves and calculates metric rates for the given campaigns and date range.
 * 
 * @param array $campaignIds Array of campaign IDs to include. If empty, gets all Blast and Triggered campaigns. 
 * @param string $startDate Start date for metrics and purchases, in YYYY-MM-DD format. Default is 30 days ago.
 * @param string $endDate End date for metrics and purchases, in YYYY-MM-DD format. Default is today.
 * @param array $campaignTypes Array of campaign types to include. Default is ['Blast', 'Triggered'].
 * @param string $purchaseMode Purchase attribution mode. 'campaignsInDate' or 'allPurchasesInDate'. Default is 'campaignsInDate'.
 * @return array Array of metrics with calculated rates.
 */
function get_idwiz_metric_rates( $campaignIds = [], $startDate = null, $endDate = null, $campaignTypes = [ 'Blast', 'Triggered' ], $purchaseMode = 'campaignsInDate' ) {


	$startDate = $startDate ?? '2021-11-01';
	$endDate = $endDate ?? date( 'Y-m-d' );

	// Determine campaign IDs for Blast and Triggered campaigns
	if ( empty( $campaignIds ) ) {
		$blastCampaigns = get_idwiz_campaigns( [ 'type' => 'Blast', 'fields' => 'id', 'startAt_start' => $startDate, 'startAt_end' => $endDate ] );
		$blastCampaignIds = array_column( $blastCampaigns, 'id' );

		$triggeredCampaigns = get_idwiz_campaigns( [ 'type' => 'Triggered', 'fields' => 'id' ] );
		$triggeredCampaignIds = array_column( $triggeredCampaigns, 'id' );

		$allIncludedIds = array_merge( $blastCampaignIds, $triggeredCampaignIds );
	} else {
		$blastCampaignIds = $triggeredCampaignIds = $allIncludedIds = [];
		foreach ( $campaignIds as $campaignId ) {
			$campaign = get_idwiz_campaign( $campaignId );
			if ( $campaign['type'] == 'Blast' ) {
				$blastCampaignIds[] = $campaignId;
			} else {
				$triggeredCampaignIds[] = $campaignId;
			}
			$allIncludedIds[] = $campaignId;
		}
	}
	// Retrieve metrics for Blast and (optional) Triggered campaigns
	$blastMetrics = in_array( 'Blast', $campaignTypes ) && ! empty( $blastCampaignIds ) ? get_idwiz_metrics( [ 'campaignIds' => $blastCampaignIds ] ) : [];
	$triggeredMetrics = in_array( 'Triggered', $campaignTypes ) && ! empty( $triggeredCampaignIds ) ? get_triggered_campaign_metrics( $triggeredCampaignIds, $startDate, $endDate ) : [];
	$purchaseArgs = [];


	$purchaseArgs = [ 
		'startAt_start' => $startDate,
		'startAt_end' => $endDate,
		'fields' => 'accountNumber,OrderId' // limit fields for faster query
	];

	// Set attribution mode
	$currentUser = wp_get_current_user();
	$currentUserId = $currentUser->ID;
	$userAttMode = get_user_meta( $currentUserId, 'purchase_attribution_mode', true );
	// default mode is campaign-id, which gets no extra parameters here
	if ( $userAttMode == 'broad-channel-match' ) {
		$purchaseArgs['shoppingCartItems_utmMedium'] = [ 'email', '' ];
	} elseif ( $userAttMode == 'email-channel-match' ) {
		$purchaseArgs['shoppingCartItems_utmMedium'] = [ 'email' ];
	}

	// If the mode is set to getting purchases only for specific campaigns, we pass the campaignIds
	if ( $purchaseMode == 'campaignsInDate' ) {
		if ( in_array( 'Triggered', $campaignTypes ) ) {
			$purchaseArgs['campaignIds'] = $allIncludedIds;
			$purchaseArgs['campaignIds'] = $allIncludedIds;
		} else {
			$purchaseArgs['campaignIds'] = $blastCampaignIds;
		}
	} else {
		// If the mode is set to getting purchases between dates (without regard to campaign) we don't pass campaignIds
	}


	$purchases = get_idwiz_purchases( $purchaseArgs );

	//$uniquePurchasers = array_unique(array_column($purchases, 'accountNumber'));
	//$totalOrders = array_unique(array_column($purchases, 'OrderId'));

	// Initialize variables for summable metrics
	$totalSends = $totalOpens = $totalClicks = $totalUnsubscribes = $totalDeliveries = $totalPurchases = $totalComplaints = $totalRevenue = 0;

	// Purchases and revenue using our custom functions
	$totalPurchases = is_array( $purchases ) ? count( $purchases ) : 0;

	$purchaseCampaigns = $purchaseArgs['campaignIds'] ?? null;
	$totalRevenue = get_idwiz_revenue( $startDate, $endDate, $campaignTypes, $purchaseCampaigns );

	$gaRevenue = get_idwiz_revenue( $startDate, $endDate, $campaignTypes, $purchaseCampaigns, true );


	// Process Blast metrics
	foreach ( $blastMetrics as $blastMetricSet ) {
		$totalSends += $blastMetricSet['uniqueEmailSends'] ?? 0;
		$totalOpens += $blastMetricSet['uniqueEmailOpens'] ?? 0;
		$totalClicks += $blastMetricSet['uniqueEmailClicks'] ?? 0;
		$totalUnsubscribes += $blastMetricSet['uniqueUnsubscribes'] ?? 0;
		$totalComplaints += $blastMetricSet['totalComplaints'] ?? 0;
		$totalDeliveries += $blastMetricSet['uniqueEmailsDelivered'] ?? 0;
	}

	// Add Triggered campaign metrics, if applicable
	if ( ! empty( $triggeredMetrics ) ) {
		$totalSends += $triggeredMetrics['uniqueEmailSends'] ?? 0;
		$totalOpens += $triggeredMetrics['uniqueEmailOpens'] ?? 0;
		$totalClicks += $triggeredMetrics['uniqueEmailClicks'] ?? 0;
		$totalUnsubscribes += $triggeredMetrics['uniqueUnsubscribes'] ?? 0;
		$totalComplaints += $triggeredMetrics['totalComplaints'] ?? 0;
		$totalDeliveries += $triggeredMetrics['uniqueEmailsDelivered'] ?? 0;
	}

	// Calculate and return all metrics
	return [ 
		'uniqueEmailSends' => $totalSends,
		'uniqueEmailOpens' => $totalOpens,
		'uniqueEmailClicks' => $totalClicks,
		'uniqueUnsubscribes' => $totalUnsubscribes,
		'totalComplaints' => $totalUnsubscribes,
		'uniqueEmailsDelivered' => $totalDeliveries,
		'uniquePurchases' => $totalPurchases,
		'wizDeliveryRate' => ( $totalSends > 0 ) ? ( $totalDeliveries / $totalSends ) * 100 : 0,
		'wizOpenRate' => ( $totalOpens > 0 && $totalSends > 0 ) ? ( $totalOpens / $totalSends ) * 100 : 0,
		'wizCtr' => ( $totalClicks > 0 ) ? ( $totalClicks / $totalSends ) * 100 : 0,
		'wizCto' => ( $totalOpens > 0 ) ? ( $totalClicks / $totalOpens ) * 100 : 0,
		'wizCvr' => ( $totalPurchases > 0 && $totalDeliveries > 0 ) ? ( $totalPurchases / $totalDeliveries ) * 100 : 0,
		'wizAov' => ( $totalPurchases > 0 && $totalRevenue > 0 ) ? ( $totalRevenue / $totalPurchases ) : 0,
		'wizUnsubRate' => ( $totalSends > 0 ) ? ( $totalUnsubscribes / $totalSends ) * 100 : 0,
		'wizCompRate' => ( $totalSends > 0 ) ? ( $totalComplaints / $totalSends ) * 100 : 0,
		'revenue' => $totalRevenue,
		'gaRevenue' => $gaRevenue
	];
}

function get_triggered_campaign_metrics( $campaignIds = [], $startDate = null, $endDate = null ) {

	if ( ! $startDate ) {
		$startDate = '2021-11-01';
	}
	if ( ! $endDate ) {
		$endDate = date( 'Y-m-d' );
	}

	$purchasesOptions = [ 
		'startAt_start' => $startDate,
		'startAt_end' => $endDate,
	];

	if ( ! empty( $campaignIds ) ) {
		$purchasesOptions['campaignIds'] = $campaignIds;
	}
	$allPurchases = get_idwiz_purchases( $purchasesOptions );

	// Prepare arguments for triggered database queries
	$campaignDataArgs = [ 
		'campaignIds' => $campaignIds,
		'startAt_start' => $startDate,
		'startAt_end' => $endDate,
		'fields' => 'campaignId' //just getting one field since all we're doing is counting values
	];

	// Fetch base data for each metric and map it to the same structure as the Blast campaigns
	$metrics = [];
	$databases = [ 
		'uniqueEmailSends' => 'idemailwiz_triggered_sends',
		'uniqueEmailOpens' => 'idemailwiz_triggered_opens',
		'uniqueEmailClicks' => 'idemailwiz_triggered_clicks',
		'uniqueUnsubscribes' => 'idemailwiz_triggered_unsubscribes',
		'totalComplaints' => 'idemailwiz_triggered_complaints',
		'emailSendSkips' => 'idemailwiz_triggered_sendskips',
		'emailBounces' => 'idemailwiz_triggered_bounces'
	];

	foreach ( $databases as $metricKey => $database ) {


		$metric_data = get_idemailwiz_triggered_data( $database, $campaignDataArgs );
		$metric_count = count( $metric_data );

		$metrics[ $metricKey ] = $metric_count;
	}

	$metrics['uniqueEmailsDelivered'] = $metrics['uniqueEmailSends'] - $metrics['emailSendSkips'] - $metrics['emailBounces'];

	// Calculate rate metrics
	$metrics['wizDeliveryRate'] = $metrics['uniqueEmailsDelivered'] > 0 ? ( $metrics['uniqueEmailsDelivered'] / $metrics['uniqueEmailSends'] ) * 100 : 'N/A';
	$metrics['wizOpenRate'] = $metrics['uniqueEmailOpens'] > 0 && $metrics['uniqueEmailSends'] > 0 ? ( $metrics['uniqueEmailOpens'] / $metrics['uniqueEmailSends'] ) * 100 : 'N/A';
	$metrics['wizCtr'] = $metrics['uniqueEmailClicks'] > 0 ? ( $metrics['uniqueEmailClicks'] / $metrics['uniqueEmailSends'] ) * 100 : 'N/A';
	$metrics['wizCto'] = $metrics['uniqueEmailClicks'] > 0 && $metrics['uniqueEmailOpens'] > 0 ? ( $metrics['uniqueEmailClicks'] / $metrics['uniqueEmailOpens'] ) * 100 : 'N/A';
	$metrics['wizUnsubRate'] = $metrics['uniqueUnsubscribes'] > 0 && $metrics['uniqueEmailSends'] > 0 ? ( $metrics['uniqueUnsubscribes'] / $metrics['uniqueEmailSends'] ) * 100 : 'N/A';

	$metrics['uniquePurchases'] = count( $allPurchases );
	$metrics['revenue'] = array_sum( array_column( $allPurchases, 'total' ) );

	$metrics['gaRevenue'] = get_idwiz_revenue( $startDate, $endDate, [ 'Triggered' ], null, true );

	$metrics['wizCvr'] = $metrics['uniquePurchases'] > 0 && $metrics['uniqueEmailsDelivered'] > 0 ? $metrics['uniquePurchases'] / $metrics['uniqueEmailsDelivered'] * 100 : 0;
	$metrics['wizAov'] = $metrics['revenue'] > 0 && $metrics['uniquePurchases'] > 0 ? $metrics['revenue'] / $metrics['uniquePurchases'] : 0;

	return $metrics;
}


function parse_idwiz_metric_rate( $rate ) {
	return floatval( str_replace( [ '%', ',', '$' ], '', $rate ) );
}

function formatRollupMetric( $value, $format, $includeDifSign = false ) {
	$formattedValue = '';
	$sign = ( $value >= 0 ) ? '+' : '-';

	switch ( $format ) {
		case 'money':
			$formattedValue = ( $includeDifSign ? $sign : '' ) . '$' . number_format( abs( $value ), 0 );
			break;
		case 'perc':
			$formattedValue = ( $includeDifSign ? $sign : '' ) . number_format( abs( $value ), 2 ) . '%';
			break;
		case 'num':
			$formattedValue = ( $includeDifSign ? $sign : '' ) . number_format( abs( $value ), 0 );
			break;
		default:
			$formattedValue = $value;
	}

	return $formattedValue;
}

function idemailwiz_update_user_attribution_setting() {

	if ( ! check_ajax_referer( 'id-general', 'security', false ) ) {
		error_log( 'Nonce check failed' );
		wp_send_json_error( 'Nonce check failed' );
		return;
	}

	$field = $_POST['field'] ?? null;
	$newValue = $_POST['value'] ?? null;

	$currentUser = wp_get_current_user();
	$currentUserId = $currentUser->ID;
	if ( $field && $newValue ) {
		$updateAttribution = update_user_meta( $currentUserId, $field, $newValue );
	}

	wp_send_json_success( $updateAttribution );
	wp_die();
}

add_action( 'wp_ajax_idemailwiz_update_user_attribution_setting', 'idemailwiz_update_user_attribution_setting' );

function idwiz_generate_dynamic_rollup() {

	//error_log(print_r($_POST, true));

	if ( ! check_ajax_referer( 'id-general', 'security', false ) ) {
		if ( ! check_ajax_referer( 'data-tables', 'security', false ) ) {
			error_log( 'Nonce check failed' );
			wp_send_json_error( 'Nonce check failed' );
			return;
		}
	}

	if ( isset( $_POST['campaignIds'] ) ) {
		$startDate = isset( $_POST['startDate'] ) ? $_POST['startDate'] : '2021-11-01';
		$endDate = isset( $_POST['endDate'] ) ? $_POST['endDate'] : date( 'Y-m-d' );
		$metricRates = get_idwiz_metric_rates( $_POST['campaignIds'], $startDate, $endDate );

		$rollupElementId = isset( $_POST['rollupElementId'] ) ? $_POST['rollupElementId'] : '';

		$include = isset( $_POST['includeMetrics'] ) ? $_POST['includeMetrics'] : [];
		$exclude = isset( $_POST['excludeMetrics'] ) ? $_POST['excludeMetrics'] : [];

		echo get_idwiz_rollup_row( $metricRates, $rollupElementId, $include, $exclude );
	}
	wp_die();
}

add_action( 'wp_ajax_idwiz_generate_dynamic_rollup', 'idwiz_generate_dynamic_rollup' );


function get_idwiz_rollup_row( $metricRates, $elementId = '', $include = [], $exclude = [] ) {
	$defaultRollupFields = array(
		'uniqueEmailSends' => array(
			'label' => 'Sends',
			'format' => 'num',
			'value' => $metricRates['uniqueEmailSends']
		),
		'uniqueEmailsDelivered' => array(
			'label' => 'Delivered',
			'format' => 'num',
			'value' => $metricRates['uniqueEmailsDelivered']
		),
		'wizDeliveryRate' => array(
			'label' => 'Delivery',
			'format' => 'perc',
			'value' => $metricRates['wizDeliveryRate']
		),
		'uniqueEmailOpens' => array(
			'label' => 'Opens',
			'format' => 'num',
			'value' => $metricRates['uniqueEmailOpens']
		),
		'wizOpenRate' => array(
			'label' => 'Open Rate',
			'format' => 'perc',
			'value' => $metricRates['wizOpenRate']
		),
		'uniqueEmailClicks' => array(
			'label' => 'Clicks',
			'format' => 'num',
			'value' => $metricRates['uniqueEmailClicks']
		),
		'wizCtr' => array(
			'label' => 'CTR',
			'format' => 'perc',
			'value' => $metricRates['wizCtr']
		),
		'wizCto' => array(
			'label' => 'CTO',
			'format' => 'perc',
			'value' => $metricRates['wizCto']
		),
		'uniquePurchases' => array(
			'label' => 'Purch.',
			'format' => 'num',
			'value' => $metricRates['uniquePurchases']
		),
		'revenue' => array(
			'label' => 'Dir. Rev.',
			'format' => 'money',
			'value' => $metricRates['revenue']
		),
		'gaRevenue' => array(
			'label' => 'GA Rev.',
			'format' => 'money',
			'value' => $metricRates['gaRevenue']
		),
		'wizCvr' => array(
			'label' => 'CVR',
			'format' => 'perc',
			'value' => $metricRates['wizCvr']
		),
		'wizAov' => array(
			'label' => 'AOV',
			'format' => 'money',
			'value' => $metricRates['wizAov']
		),
		'uniqueUnsubscribes' => array(
			'label' => 'Unsubs',
			'format' => 'num',
			'value' => $metricRates['uniqueUnsubscribes']
		),
		'wizUnsubRate' => array(
			'label' => 'Unsub. Rate',
			'format' => 'perc',
			'value' => $metricRates['wizUnsubRate']
		),
		'totalComplaints' => array(
			'label' => 'Comp.',
			'format' => 'num',
			'value' => $metricRates['totalComplaints']
		),
		'wizCompRate' => array(
			'label' => 'Comp. Rate',
			'format' => 'perc',
			'value' => $metricRates['wizCompRate']
		),
	);

	$rollupFields = [];

	if ( ! empty( $include ) && is_array( $include ) ) {
		foreach ( $include as $rollupFieldKey ) {
			if ( isset( $defaultRollupFields[ $rollupFieldKey ] ) ) {
				$rollupFields[ $rollupFieldKey ] = $defaultRollupFields[ $rollupFieldKey ];
			}
		}
	} elseif ( ! empty( $exclude ) && is_array( $exclude ) ) {
		foreach ( $defaultRollupFields as $rollupFieldKey => $rollupField ) {
			if ( ! in_array( $rollupFieldKey, $exclude ) ) {
				$rollupFields[ $rollupFieldKey ] = $rollupField;
			}
		}
	} else {
		$rollupFields = $defaultRollupFields;
	}


	$html = '';
	$html .= '<div class="rollup_summary_wrapper" id="' . $elementId . '">';
	foreach ( $rollupFields as $metric ) {
		$formattedValue = formatRollupMetric( $metric['value'], $metric['format'] );
		$html .= '<div class="metric-item">';
		$html .= "<span class='metric-label'>{$metric['label']}</span>";
		$html .= "<span class='metric-value'>{$formattedValue}</span>";
		$html .= '</div>'; // End of metric-item
	}
	$html .= '</div>';

	return $html;
}










class RawHtml {
	private $html;

	public function __construct( $html ) {
		$this->html = $html;
	}

	public function __toString() {
		return $this->html;
	}
}


function group_first_and_repeat_purchases( $passedPurchases ) {
	// Get unique account numbers from passed purchases
	$accountNumbers = array_unique( array_column( $passedPurchases, 'accountNumber' ) );

	// Fetch all purchases for these accounts
	$allCustomerPurchases = get_idwiz_purchases( [ 
		'fields' => [ 'accountNumber', 'purchaseDate' ],
		'accountNumber' => $accountNumbers
	] );

	// Determine the first purchase date for each account
	$firstPurchaseDateByAccount = [];
	foreach ( $allCustomerPurchases as $purchase ) {
		$accountId = $purchase['accountNumber'];
		$purchaseDate = $purchase['purchaseDate'];

		if ( ! isset( $firstPurchaseDateByAccount[ $accountId ] ) || $purchaseDate < $firstPurchaseDateByAccount[ $accountId ] ) {
			$firstPurchaseDateByAccount[ $accountId ] = $purchaseDate;
		}
	}

	// Deduplicate purchases by orderId
	$uniqueOrders = [];
	foreach ( $passedPurchases as $purchase ) {
		$uniqueOrders[ $purchase['orderId'] ] = $purchase;
	}

	// Count new and returning orders
	$newOrdersCount = 0;
	$returningOrdersCount = 0;
	foreach ( $uniqueOrders as $orderId => $purchase ) {
		$accountId = $purchase['accountNumber'];
		$purchaseDate = $purchase['purchaseDate'];

		if ( isset( $firstPurchaseDateByAccount[ $accountId ] ) && $purchaseDate == $firstPurchaseDateByAccount[ $accountId ] ) {
			$newOrdersCount++;
		} else {
			$returningOrdersCount++;
		}
	}

	return [ 
		'new' => $newOrdersCount,
		'returning' => $returningOrdersCount
	];
}


function return_new_and_returning_customers( $purchases ) {
	// $results = group_first_and_repeat_purchases($purchases);
	// return $results['counts'];
	return;
}


function get_orders_grouped_by_customers() {
	global $wpdb;

	$batch_size = 25000; // Define a reasonable batch size. You can adjust this based on your server's capabilities.
	$offset = 0;

	$grouped_orders = [];

	while ( true ) {
		$query = $wpdb->prepare( "SELECT accountNumber, orderId, purchaseDate, cohort_value as division FROM {$wpdb->prefix}idemailwiz_cohorts WHERE cohort_type = 'division' ORDER BY accountNumber, purchaseDate ASC LIMIT %d OFFSET %d", $batch_size, $offset );
		$results = $wpdb->get_results( $query, ARRAY_A );

		// If no results, break out of the loop
		if ( empty( $results ) ) {
			break;
		}

		foreach ( $results as $row ) {
			$grouped_orders[ $row['accountNumber'] ][] = $row;
		}

		$offset += $batch_size; // Increase the offset for the next batch
	}

	return $grouped_orders;
}
function get_campaigns_with_most_returning_customers( $campaigns ) {
	$campaignsCount = [];

	foreach ( $campaigns as $campaign ) {
		$campaignId = $campaign['id'];
		$purchasesForCampaign = get_idwiz_purchases( [ 'campaignIds' => [ $campaignId ], 'fields' => 'campaignId,orderId,accountNumber,purchaseDate' ] );

		$customerCounts = return_new_and_returning_customers( $purchasesForCampaign );

		$campaignsCount[ $campaignId ] = $customerCounts['returning'];
	}

	// Sort campaigns by number of returning customers in descending order
	arsort( $campaignsCount );

	return $campaignsCount;
}


function get_campaigns_by_open_rate( $campaigns ) {
	$openRates = [];

	foreach ( $campaigns as $campaign ) {
		$campaignMetrics = get_idwiz_metric( $campaign['id'] );
		if ( $campaignMetrics['wizOpenRate'] != 0 ) {
			$openRates[ $campaign['id'] ] = $campaignMetrics['wizOpenRate'];
		}
	}

	// Sort by open rate in descending order
	arsort( $openRates );

	$sortedCampaigns = [];
	foreach ( $openRates as $campaignId => $openRate ) {
		foreach ( $campaigns as $campaign ) {
			if ( $campaign['id'] == $campaignId ) {
				$sortedCampaigns[] = $campaign;
				break;
			}
		}
	}

	return $sortedCampaigns;
}

function get_campaigns_by_ctr( $campaigns ) {
	$ctrs = [];

	foreach ( $campaigns as $campaign ) {
		$campaignMetrics = get_idwiz_metric( $campaign['id'] );
		$ctrs[ $campaign['id'] ] = floatval( $campaignMetrics['wizCtr'] ); // Convert to float
	}

	// Sort by open rate in descending order
	arsort( $ctrs );

	$sortedCampaigns = [];
	foreach ( $ctrs as $campaignId => $ctr ) {
		foreach ( $campaigns as $campaign ) {
			if ( $campaign['id'] == $campaignId ) {
				$sortedCampaigns[] = $campaign;
				break;
			}
		}
	}

	return $sortedCampaigns;
}

function get_campaigns_by_cto( $campaigns ) {
	$ctos = [];

	foreach ( $campaigns as $campaign ) {
		$campaignMetrics = get_idwiz_metric( $campaign['id'] );
		$ctos[ $campaign['id'] ] = floatval( $campaignMetrics['wizCto'] ); // Convert to float
	}

	// Sort by open rate in descending order
	arsort( $ctos );

	$sortedCampaigns = [];
	foreach ( $ctos as $campaignId => $cto ) {
		foreach ( $campaigns as $campaign ) {
			if ( $campaign['id'] == $campaignId ) {
				$sortedCampaigns[] = $campaign;
				break;
			}
		}
	}

	return $sortedCampaigns;
}

function wiz_truncate_string( $string, $length ) {
	if ( strlen( $string ) > $length ) {
		return substr( $string, 0, $length - 3 ) . '...';
	}
	return $string;
}



function get_second_purchases_within_week( $purchaseMonth, $purchaseMonthDay, $purchaseWindowDays, $divisions ) {
	$all_orders = get_orders_grouped_by_customers();
	$second_purchases = [];

	$specified_date = new DateTime();
	$specified_date->setDate( $specified_date->format( "Y" ), $purchaseMonth, $purchaseMonthDay );
	$week_start = ( clone $specified_date )->modify( 'this week' );
	$purchase_window_end = ( clone $week_start )->modify( "+$purchaseWindowDays days" );

	foreach ( $all_orders as $accountNumber => $orders ) {
		$qualifying_purchase_date = null;

		foreach ( $orders as $order ) {
			$purchase_date = new DateTime( $order['purchaseDate'] );

			if (
				! $qualifying_purchase_date &&
				$purchase_date->format( 'z' ) >= $specified_date->format( 'z' ) &&
				$purchase_date->format( 'z' ) <= $purchase_window_end->format( 'z' ) &&
				in_array( $order['division'], $divisions )
			) {
				$qualifying_purchase_date = $purchase_date;
				continue;
			}

			if ( $qualifying_purchase_date ) {
				$end_of_qualifying_year = ( clone $qualifying_purchase_date )->setDate( $qualifying_purchase_date->format( "Y" ), 12, 31 );
				$days_until_end_of_year = $qualifying_purchase_date->diff( $end_of_qualifying_year )->days;
				$days_from_start_of_next_year = ( new DateTime( $order['purchaseDate'] ) )->format( 'z' );
				$days_since_qualifying_order = $days_until_end_of_year + $days_from_start_of_next_year;

				$is_leap_year = ( $qualifying_purchase_date->format( 'L' ) == 1 && $qualifying_purchase_date->format( 'm' ) <= 2 ) ? true : false;
				$days_limit = $is_leap_year ? 366 : 365;
				$days_limit = $days_limit - (int) $purchaseWindowDays;

				if ( $days_since_qualifying_order <= $days_limit ) {
					$order['day_of_year'] = $days_since_qualifying_order;
					$second_purchases[] = $order;
				}
			}
		}
	}

	return $second_purchases;
}


function wiz_notifications() {
	// Insert notifications wrapper into the footer
	echo '<div class="wizNotifs" aria-live="assertive" aria-atomic="true"></div>';
}
add_action( 'wp_footer', 'wiz_notifications' );



if ( ! function_exists( 'wfu_after_file_loaded_handler' ) ) {
	/** Function syntax
	 *  The function takes two parameters, $changable_data and $additional_data.
	 *  - $changable_data is an array that can be modified by the filter and
	 *    contains the items:
	 *    > error_message: initially it is set to an empty value, if the handler
	 *      sets a non-empty value then upload of the file will be cancelled
	 *      showing this error message
	 *    > admin_message: initially it is set to an empty value, if the handler
	 *      sets a non-empty value then this value will be shown to
	 *      administrators if adminmessages attribute has been activated,
	 *      provided that error_message is also set. You can use it to display
	 *      more information about the error, visible only to admins.
	 *  - $additional_data is an array with additional data to be used by the
	 *    filter (but cannot be modified) as follows:
	 *    > file_unique_id: this id is unique for each individual file upload
	 *      and can be used to identify each separate upload
	 *    > file_path: the full path of the uploaded file
	 *    > shortcode_id: this is the id of the plugin, as set using uploadid
	 *      attribute; it can be used to apply this filter only to a specific
	 *      instance of the plugin (if it is used in more than one pages or
	 *      posts)
	 *  The function must return the final $changable_data. */
	function wfu_after_file_loaded_handler( $changable_data, $additional_data ) {
		global $wpdb;
		$templateId = $additional_data['shortcode_id'];

		$filePath = $additional_data['file_path'];

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}idemailwiz_templates SET heatmapFile = %s WHERE templateId = %d",
				$filePath,
				$templateId
			)
		);

		return $changable_data;
	}
	add_filter( 'wfu_after_file_loaded', 'wfu_after_file_loaded_handler', 10, 2 );
}


/*
	This filter is executed after the upload process for each individual file has
	finished, in order to allow additional tasks to be executed and define custom
	javascript code to run in clientâ  s browser. 
*/
if ( ! function_exists( 'wfu_after_file_upload_handler' ) ) {
	/** Function syntax
	 *  The function takes two parameters, $changable_data and $additional_data.
	 *  - $changable_data is an array that can be modified by the filter and
	 *    contains the items:
	 *    > ret_value: not used for the moment, it exists for future additions
	 *    > js_script: javascript code to be executed on the client's browser
	 *      after each file is uploaded
	 *  - $additional_data is an array with additional data to be used by the
	 *    filter (but cannot be modified) as follows:
	 *    > shortcode_id: this is the id of the plugin, as set using uploadid
	 *      attribute; it can be used to apply this filter only to a specific
	 *      instance of the plugin (if it is used in more than one pages or
	 *      posts)
	 *    > file_unique_id: this id is unique for each individual file upload
	 *      and can be used to identify each separate upload
	 *    > upload_result: it is the result of the upload process, taking the
	 *      following values:
	 *        success: the upload was successful
	 *        warning: the upload was successful but with warning messages
	 *        error: the upload failed
	 *    > error_message: contains warning or error messages generated during
	 *      the upload process
	 *    > admin_messages: contains detailed error messages for administrators
	 *      generated during the upload process
	 *  The function must return the final $changable_data. */
	function wfu_after_file_upload_handler( $changable_data, $additional_data ) {
		$changable_data['js_script'] = "location.reload()";
		return $changable_data;
	}
	add_filter( 'wfu_after_file_upload', 'wfu_after_file_upload_handler', 10, 2 );
}

/*
	This filter runs right before the uploaded file starts to be uploaded in order
	to make modifications of its filename.
*/
if ( ! function_exists( 'wfu_before_file_upload_handler' ) ) {
	/** Function syntax
	 *  The function takes two parameters, $file_path and $file_unique_id.
	 *  - $file_path is the filename of the uploaded file (after all internal
	 *    checks have been applied) and can be modified by the filter.
	 *  - $file_unique_id is is unique for each individual file upload and can
	 *    be used to identify each separate upload.
	 *  The function must return the final $file_path.
	 *  If additional data are required (such as user id or userdata) you can
	 *  get them by implementing the previous filter wfu_before_file_check and
	 *  link both filters by $file_unique_id parameter. Please note that no
	 *  filename validity checks will be performed after the filter. The filter
	 *  must ensure that filename is valid. */
	function wfu_before_file_upload_handler( $file_path, $file_unique_id ) {
		// Extract the directory part of the file path
		$directory = dirname( $file_path );

		// Create the new file name
		$new_file_name = "heatmap_" . $file_unique_id . "_" . time() . ".csv";

		// Concatenate the directory with the new file name
		$new_file_path = $directory . DIRECTORY_SEPARATOR . $new_file_name;

		return $new_file_path;
	}

	add_filter( 'wfu_before_file_upload', 'wfu_before_file_upload_handler', 10, 2 );
}

add_action( 'wp_ajax_idemailwiz_remove_heatmap', 'idemailwiz_remove_heatmap' );
function idemailwiz_remove_heatmap() {
	global $wpdb;
	$templateId = $_POST['templateId'];
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->prefix}idemailwiz_templates SET heatmapFile = NULL WHERE templateId = %d",
			$templateId
		)
	);
	wp_send_json_success( true );
}


add_filter( 'cron_schedules', 'idemailwiz_add_five_minutes_cron_schedule' );
function idemailwiz_add_five_minutes_cron_schedule( $schedules ) {
	$schedules['every_five_minutes'] = array(
		'interval' => 5 * 60, // 5 minutes in seconds
		'display' => esc_html__( 'Every Five Minutes' )
	);
	return $schedules;
}


// Schedule the event if it's not already scheduled
if ( ! wp_next_scheduled( 'idemailwiz_custom_transient_cleanup' ) ) {
	wp_schedule_event( time(), 'every_five_minutes', 'idemailwiz_custom_transient_cleanup' );
}

// Add the action hook
add_action( 'idemailwiz_custom_transient_cleanup', 'delete_expired_transients' );


function get_idwiz_courses() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_courses';
	$query = "SELECT * FROM {$table_name}";
	$courses = $wpdb->get_results( $query );
	if ( empty( $courses ) ) {
		return new WP_Error( 'no_courses', __( 'No courses found', 'text-domain' ) );
	}
	return $courses;
}
function get_course_details_by_id( $course_id ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_courses';

	// Sanitize the course ID to prevent SQL injection
	$course_id = sanitize_text_field( $course_id );

	// Prepare the query to get a specific course by ID
	$query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %s", $course_id );

	// Execute the query
	$course = $wpdb->get_row( $query );

	if ( is_null( $course ) ) {
		return new WP_Error( 'no_course', __( 'Course not found', 'text-domain' ) );
	}

	return $course;
}

add_action( 'wp_ajax_id_get_courses_options', 'id_get_courses_options_handler' );
function id_get_courses_options_handler() {

	if ( ! check_ajax_referer( 'id-general', 'security', false ) ) {
		error_log( 'Nonce check failed' );
		wp_send_json_error( 'Nonce check failed' );
		return;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_courses';
	$division = isset( $_POST['division'] ) ? sanitize_text_field( $_POST['division'] ) : '';
	$term = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';

	// Building the query
	$query = "SELECT id, name FROM {$table_name} WHERE name LIKE %s";
	$params = array( '%' . $wpdb->esc_like( $term ) . '%' );

	// Add division filter if provided
	if ( ! empty( $division ) ) {
		$query .= " AND division LIKE %s";
		$params[] = '%' . $wpdb->esc_like( $division ) . '%';
	}

	$courses = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

	$results = array();
	foreach ( $courses as $course ) {
		$results[] = array(
			'id' => $course->id,
			'text' => $course->id . ' | ' . $course->name
		);
	}

	wp_send_json_success( $results );
}



add_action( 'wp_ajax_id_add_course_to_rec', 'id_add_course_to_rec_handler' );
function id_add_course_to_rec_handler() {

	if ( ! check_ajax_referer( 'id-general', 'security', false ) ) {
		error_log( 'Nonce check failed' );
		wp_send_json_error( 'Nonce check failed' );
		return;
	}

	$course_id = isset( $_POST['course_id'] ) ? sanitize_text_field( $_POST['course_id'] ) : '';
	$rec_type = isset( $_POST['rec_type'] ) ? sanitize_text_field( $_POST['rec_type'] ) : '';
	$selected_course = isset( $_POST['selected_course'] ) ? sanitize_text_field( $_POST['selected_course'] ) : '';

	if ( empty( $course_id ) || empty( $rec_type ) || empty( $selected_course ) ) {
		wp_send_json_error( 'Missing data' );
		return;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_courses';

	// Fetch the current recommendations
	$course = $wpdb->get_row( $wpdb->prepare( "SELECT course_recs FROM {$table_name} WHERE id = %s", $course_id ) );

	if ( null === $course ) {
		wp_send_json_error( 'Course not found' );
		return;
	}

	// Ensure $course_recs is an array
	$course_recs = maybe_unserialize( $course->course_recs );
	if ( ! is_array( $course_recs ) ) {
		$course_recs = []; // Initialize as an empty array if it's not an array
	}

	// Check if the specific rec_type is an array, initialize if not
	if ( ! isset( $course_recs[ $rec_type ] ) || ! is_array( $course_recs[ $rec_type ] ) ) {
		$course_recs[ $rec_type ] = [];
	}

	// Add the selected course
	$course_recs[ $rec_type ][] = $selected_course;

	// Update the course recommendations
	$updated = $wpdb->update( $table_name, [ 'course_recs' => maybe_serialize( $course_recs ) ], [ 'id' => $course_id ] );

	if ( false === $updated ) {
		wp_send_json_error( 'Database update failed' );
	} else {
		wp_send_json_success( 'Course added successfully' );
	}
}

add_action( 'wp_ajax_id_remove_course_from_rec', 'id_remove_course_from_rec_handler' );
function id_remove_course_from_rec_handler() {
	// Check nonce
	if ( ! check_ajax_referer( 'id-general', 'security', false ) ) {
		error_log( 'Nonce check failed' );
		wp_send_json_error( 'Nonce check failed' );
		return;
	}

	$course_id = sanitize_text_field( $_POST['course_id'] );
	$rec_type = sanitize_text_field( $_POST['rec_type'] );
	$recd_course_id = sanitize_text_field( $_POST['recd_course_id'] );

	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_courses';

	// Fetch and modify the course recommendations
	$course = $wpdb->get_row( $wpdb->prepare( "SELECT course_recs FROM {$table_name} WHERE id = %s", $course_id ) );
	if ( null === $course ) {
		wp_send_json_error( 'Course not found' );
		return;
	}

	$course_recs = maybe_unserialize( $course->course_recs );
	if ( isset( $course_recs[ $rec_type ] ) ) {
		$course_recs[ $rec_type ] = array_diff( $course_recs[ $rec_type ], [ $recd_course_id ] );
	}

	// Update the database
	$updated = $wpdb->update( $table_name, [ 'course_recs' => maybe_serialize( $course_recs ) ], [ 'id' => $course_id ] );
	if ( false === $updated ) {
		wp_send_json_error( 'Database update failed' );
	} else {
		wp_send_json_success( 'Course removed successfully' );
	}
}

function idemailwiz_ajax_save_item_update() {
	// Check for nonce and security
	if ( ! check_ajax_referer( 'id-general', 'security', false ) ) {
		wp_send_json_error( 'Invalid nonce' );
		return;
	}

	// Fetch data from POST
	$itemID = $_POST['itemId'];
	$updateType = $_POST['updateType'];

	$updateContent = $_POST['updateContent'];

	// Validate that the new title is not empty
	if ( empty( $updateContent ) ) {
		wp_send_json_error( 'The title/content cannot be empty' );
		return;
	}

	// Start the post data array with the ID
	$post_data = array(
		'ID' => $itemID,
	);
	if ( $updateType == 'title' ) {
		$post_data['post_title'] = $updateContent;
	}

	if ( $updateType == 'content' ) {
		$post_data['post_content'] = $updateContent;
	}

	$update_status = wp_update_post( $post_data, true );

	// Check if the update was successful
	if ( is_wp_error( $update_status ) ) {
		$errors = $update_status->get_error_messages();
		wp_send_json_error( 'Failed to update the item with ID ' . $itemID . '. Errors: ' . print_r( $errors, true ) );
	} else {
		wp_send_json_success( 'Item updated successfully' );
	}

}
add_action( 'wp_ajax_idemailwiz_ajax_save_item_update', 'idemailwiz_ajax_save_item_update' );


function generate_all_template_images() {

	global $wpdb;

	$templates_without_images = $wpdb->get_results(
		"SELECT * FROM {$wpdb->prefix}idemailwiz_templates WHERE templateImage IS NULL"

	);

	foreach ( $templates_without_images as $template ) {

		$template_id = $template->templateId;

		$image = idemailwiz_generate_image_from_template( $template_id );

		$wpdb->update(
			"{$wpdb->prefix}idemailwiz_templates",
			[ 'templateImage' => $image ],
			[ 'templateId' => $template_id ],
			[ '%s' ],
			[ '%d' ]
		);

	}

}


// https://hcti.io image generation
function idemailwiz_generate_image_from_template( $templateId ) {

	$template = get_idwiz_template( $templateId );

	if ( $template['messageMedium'] == 'Email' ) {
		// wrap template in 800px div and limit the image generation to that div
		$html = '<div class="toImageFrame" style="width: 800px;">';
		$html .= $template['html'];
		$html .= '</div>';
	} else {
		$html = '<div class="toImageFrame" style="width: 800px; text-align: center;">';
		$html .= '<img src="' . $template['imageUrl'] . '" style="width: 90%; display: block; margin: 0 auto 40px auto;" />';
		$html .= '<p style="font-size: 36px; width: 90%; margin: 0 auto; padding-bottom: 40px;">' . $template['message'] . '</p>';
		$html .= '</div>';

	}


	$data = array( 'html' => $html, 'selector' => '.toImageFrame', 'device_scale' => 1, 'format' => 'jpg' );

	$ch = curl_init();

	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );

	curl_setopt( $ch, CURLOPT_URL, "https://hcti.io/v1/image" );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

	curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $data ) );

	curl_setopt( $ch, CURLOPT_POST, 1 );
	// Retrieve your user_id and api_key from https://htmlcsstoimage.com/dashboard
	curl_setopt( $ch, CURLOPT_USERPWD, "a69a8a1d-ac76-4b76-a980-0459175c366a" . ":" . "80c47e1e-ec29-4d9b-8d43-9be67166f465" );

	$headers = array();
	$headers[] = "Content-Type: application/x-www-form-urlencoded";
	curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

	$result = curl_exec( $ch );
	if ( curl_errno( $ch ) ) {
		echo 'Error:' . curl_error( $ch );
	}
	curl_close( $ch );
	$res = json_decode( $result, true );

	if ( isset( $res['url'] ) ) {
		return $res['url'];
	} else {
		return NULL;
	}

}


add_action( 'wp_ajax_regenerate_template_preview', 'regenerate_template_preview_handler' );

function regenerate_template_preview_handler() {
	global $wpdb; // Make sure to include global $wpdb
	check_ajax_referer( 'id-general', 'security' ); // Check nonce for security

	$templateIds = isset( $_POST['templateIds'] ) ? $_POST['templateIds'] : array();
	$imageUrls = array();
	if ( empty( $templateIds ) ) {
		wp_send_json_error( 'No template IDs provided' );
	}
	foreach ( $templateIds as $templateId ) {
		$image = idemailwiz_generate_image_from_template( $templateId );
		if ( $image ) {
			// Update the database with the new image URL
			$wpdb->update(
				"{$wpdb->prefix}idemailwiz_templates",
				[ 'templateImage' => $image ], // New image URL
				[ 'templateId' => $templateId ], // Where condition
				[ '%s' ], // Format of the new value
				[ '%d' ]  // Format of the where condition
			);
			$imageUrls[ $templateId ] = $image;
		}
	}

	wp_send_json_success( $imageUrls ); // Send back the array of URLs
}




function get_template_preview( $template ) {
	ob_start();
	?>
	<div class="template-image-wrapper" data-templateid="<?php echo $template['templateId']; ?>">
		<div class='wiztemplate-image-spinner hide'><i class='fa-solid fa-spin fa-spinner fa-3x'></i></div>
		<?php
		if ( $template['templateImage'] ) {
			$imageSize = @getimagesize( $template['templateImage'] );

			if ( $imageSize !== false ) { ?>
				<img src=<?php echo $template['templateImage']; ?> />
			<?php } else {
				echo '<div class="template-preview-missing-message"><em>Template preview image missing or invalid. Click below to regenerate.</em><br/><br/>';
				echo '<button title="Regenerate Preview" class="wiz-button green regenerate-template-preview"
              data-templateid="' . $template['templateId'] . '"><i
                  class="fa-solid fa-arrows-rotate"></i>&nbsp;Regenerate Preview</button></div>';
			}
		} else {
			echo '<div class="template-preview-missing-message"><em>No template preview available.<br/><br/></em>';
			echo '<button title="Generate Template Preview" class="wiz-button green regenerate-template-preview"
          data-templateid="' . $template['templateId'] . '"><i
              class="fa-solid fa-arrows-rotate"></i>&nbsp;Generate Preview</button></div>';

		} ?>

	</div>
	<?php
	return ob_get_clean();
}

function idemailwiz_get_campaigns_for_select() {
	// Check for nonce and security
	if ( ! check_ajax_referer( 'initiatives', 'security', false ) ) {
		if ( ! check_ajax_referer( 'id-general', 'security', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
			return;
		}
	}

	$campaignArgs = array(
		'sortBy' => 'startAt',
		'sort' => 'DESC',
	);

	if ( isset( $_POST['type'] ) ) {
		$campaignArgs['type'] = $_POST['type'];
	}

	$all_campaigns = get_idwiz_campaigns( $campaignArgs );
	$search = isset( $_POST['q'] ) ? $_POST['q'] : '';
	$exclude_ids = isset( $_POST['exclude'] ) ? $_POST['exclude'] : array(); // Get the exclude parameter

	$filtered_campaigns = array_filter( $all_campaigns, function ($campaign) use ($search, $exclude_ids) {
		return ( $search === '' || strpos( strtolower( trim( $campaign['name'] ) ), strtolower( trim( $search ) ) ) !== false )
			&& ! in_array( $campaign['id'], $exclude_ids ); // Exclude campaigns with specified IDs
	} );


	$data = array_map( function ($campaign) {
		return array( 'id' => $campaign['id'], 'text' => $campaign['name'] );
	}, $filtered_campaigns );

	echo json_encode( array_values( $data ) );
	wp_die();
}
add_action( 'wp_ajax_idemailwiz_get_campaigns_for_select', 'idemailwiz_get_campaigns_for_select' );



