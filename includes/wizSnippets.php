<?php
add_action( 'wp_ajax_save_wizSnippet_content', 'save_wizSnippet_content' );

function save_wizSnippet_content() {
	check_ajax_referer( 'wizSnippets', 'security' );

	if ( ! isset( $_POST['post_id'], $_POST['content'], $_POST['css'] ) ) {
		wp_send_json_error( 'Data is missing from the update request!' );
	}

	$post_id = $_POST['post_id'];
	$content = $_POST['content'];
	$css = $_POST['css'];

	// Check if the content and CSS are different from the existing values
	$is_content_different = $content !== get_post_meta( $post_id, 'snippet_content', true );
	$is_css_different = $css !== get_post_meta( $post_id, 'snippet_css', true );

	$snippetResult = $is_content_different ? update_post_meta( $post_id, 'snippet_content', $content ) : true;
	$cssResult = $is_css_different ? update_post_meta( $post_id, 'snippet_css', $css ) : true;

	// Check if updates were successful or not needed
	if ( $snippetResult && $cssResult ) {
		wp_send_json_success( 'Snippet and CSS saved successfully.' );
	} else {
		wp_send_json_error( 'There was an error saving the snippet or CSS.' );
	}
}


function wiz_snippet_shortcode( $atts ) {
	$atts = shortcode_atts( [ 'id' => '' ], $atts );
	$snippet_content = '';

	if ( ! empty( $atts['id'] ) ) {
		$snippet_content = get_post_meta( $atts['id'], 'snippet_content', true );
	}

	return do_shortcode( $snippet_content );
}
add_shortcode( 'wiz_snippet', 'wiz_snippet_shortcode' );

function get_snippet_css( $rows ) {
	$css = '';

	foreach ( $rows as $row ) {
        $columns = $row['columns'] ?? [];
		foreach ( $columns as $column ) {
            $chunks = $column['chunks'] ?? [];
			foreach (  $chunks as $chunk ) {
				if ( $chunk['field_type'] == 'snippet' ) {
					$snippet_id = $chunk['fields']['select_snippet'] ?? null;
					if ( $snippet_id ) {
						$snippet_css = get_post_meta( $snippet_id, 'snippet_css', true );
						if ( ! empty( $snippet_css ) ) {
							$css .= $snippet_css . "\n";
						}
					}
				}
			}
		}
	}

	return $css;
}

function idemailwiz_create_new_snippet() {
	// Check for nonce and security
	if ( ! check_ajax_referer( 'template-editor', 'security', false ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		return;
	}

	// Fetch title from POST
	$title = $_POST['postTitle'];

	// Validate that the title is not empty
	if ( empty( $title ) ) {
		wp_send_json_error( array( 'message' => 'The title cannot be empty' ) );
		return;
	}

	// Create new initiative post
	$post_id = wp_insert_post(
		array(
			'post_title' => $title,
			'post_type' => 'wysiwyg_snippet',
			'post_status' => 'publish',
		)
	);

	if ( $post_id > 0 ) {
		wp_send_json_success( array( 'message' => 'Snippet created successfully', 'post_id' => $post_id ) );
	} else {
		wp_send_json_error( array( 'message' => 'Failed to create the snippet' ) );
	}
}
add_action( 'wp_ajax_idemailwiz_create_new_snippet', 'idemailwiz_create_new_snippet' );

function idemailwiz_delete_snippets() {
	// Check for nonce and security
	if ( ! check_ajax_referer( 'wizSnippets', 'security', false ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		return;
	}

	// Fetch selected IDs from POST
	$selectedIds = $_POST['selectedIds'];

	foreach ( $selectedIds as $post_id ) {
		wp_delete_post( $post_id, true ); // Set second parameter to false if you don't want to force delete
	}

	wp_send_json_success( array( 'message' => 'Snippets deleted successfully' ) );
}
add_action( 'wp_ajax_idemailwiz_delete_snippets', 'idemailwiz_delete_snippets' );