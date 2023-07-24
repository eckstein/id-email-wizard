<?php
// Alter the main query on the category archive page
function id_pre_get_posts( $query ) {
    // Check if the current page is a post type archive
    if ( $query->is_post_type_archive('idemailwiz_template') && $query->is_main_query() ) {
        // Add pagination
        // Set the number of posts to display per page
        $posts_per_page = get_option( 'posts_per_page' );

        // Get the current page number
        $paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

        // Set the number of posts to display per page and the current page for the query
        $query->set( 'posts_per_page', $posts_per_page );
        $query->set( 'paged', $paged );

        // Modify the orderby and order parameters to sort by the modified date
        $query->set( 'orderby', 'modified' );
        $query->set( 'order', 'DESC' );
    }
    // Check if the current page is a taxonomy term archive
    elseif ( $query->is_tax('idemailwiz_folder') && $query->is_main_query() ) {
        // Add pagination
        // Set the number of posts to display per page
        $posts_per_page = get_option( 'posts_per_page' );

        // Get the current page number
        $paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

        // Set the number of posts to display per page and the current page for the query
        $query->set( 'posts_per_page', $posts_per_page );
        $query->set( 'paged', $paged );

        // Get the current term
		$term = $query->get_queried_object();

		// Exclude posts in child terms
		$children = get_terms( array( 
			'taxonomy' => 'idemailwiz_folder', 
			'parent'   => $term->term_id 
		) );
		$children_ids = array();
		foreach ( $children as $child ) {
			$children_ids[] = $child->term_id;
		}
		$query->set( 'tax_query', array(
			array(
				'taxonomy' => 'idemailwiz_folder',
				'terms'    => $children_ids,
				'field'    => 'term_id',
				'operator' => 'NOT IN',
			),
		));
		
		//include trashed posts, but only on the trash term page
		$queried_object = get_queried_object();
		$trashTerm = get_option('templatefolderstrash');
		if (!is_wp_error($trashTerm)) {
			if ($queried_object->term_id === (int) $trashTerm) {
				$query->set('post_status', array('trash'));
			}
		}

        // Get the current user ID
        $current_user_id = get_current_user_id();

        // Get an array of post IDs that the current user has marked as favorite templates
        $favorite_templates = get_user_meta( $current_user_id, 'favorite_templates', true );
        $favorite_templates = is_array($favorite_templates) ? $favorite_templates : [];

        // Filter the favorite templates array to contain only those post IDs 
        // which are associated exactly with the current term.
        $favorite_templates = array_filter($favorite_templates, function($post_id) use ($term) {
            $post_terms = wp_get_post_terms($post_id, 'idemailwiz_folder', array('fields' => 'ids')); // Get IDs of terms of the post
            return in_array($term->term_id, $post_terms); // Check if current term id is in the post's terms
        });
		
		//Get the posts for the term/folder we're in
        $term_posts = get_posts( array(
			'post_type' => 'idemailwiz_template',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'idemailwiz_folder',
                    'terms'    => $term->term_id,
                ),
            ),
            'post__not_in' => $favorite_templates,
            'post_status' => array('publish','trash'),
        ) );
        // Sort the term posts by modified date
        usort( $term_posts, function( $a, $b ) {
            return strtotime( $b->post_modified ) - strtotime( $a->post_modified );
        } );

        // Merge the filtered favorite templates array with the reversed term posts array
        $all_posts = array_merge( wp_list_pluck( $term_posts, 'ID' ), array_values($favorite_templates) );

        // Modify the orderby and order parameters to sort by the modified post__in parameter
        $query->set( 'post__in', $all_posts );
        $query->set( 'orderby', 'post__in' );
    }
}
add_action( 'pre_get_posts', 'id_pre_get_posts' );