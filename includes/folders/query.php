<?php
/**
 * Query modifications for the template post-type archive and
 * idemailwiz_folder term archive pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'pre_get_posts', 'id_pre_get_posts' );
function id_pre_get_posts( $query ) {
	$idwiz_q = $query->is_main_query() ? (string) $query->get( 'idwiz_q' ) : '';

	if ( $query->is_main_query() && '' !== $idwiz_q ) {
		// Template search view (/templates/search/{q}/). Reuses the archive
		// layout but scopes the query to idemailwiz_template + search string.
		$posts_per_page = get_option( 'posts_per_page' );
		$paged          = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

		$query->set( 'post_type', 'idemailwiz_template' );
		$query->set( 's', $idwiz_q );
		$query->set( 'posts_per_page', $posts_per_page );
		$query->set( 'paged', $paged );
		$query->set( 'orderby', 'modified' );
		$query->set( 'order', 'DESC' );

	} elseif ( $query->is_post_type_archive( 'idemailwiz_template' ) && $query->is_main_query() ) {
		$posts_per_page = get_option( 'posts_per_page' );
		$paged          = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

		$query->set( 'posts_per_page', $posts_per_page );
		$query->set( 'paged', $paged );
		$query->set( 'orderby', 'modified' );
		$query->set( 'order', 'DESC' );

	} elseif ( $query->is_tax( 'idemailwiz_folder' ) && $query->is_main_query() ) {
		$posts_per_page = get_option( 'posts_per_page' );
		$paged          = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

		$query->set( 'posts_per_page', $posts_per_page );
		$query->set( 'paged', $paged );

		$term = $query->get_queried_object();

		// Exclude posts that only live in child terms so they only appear once
		// at the deepest folder level.
		$children     = get_terms(
			array(
				'taxonomy' => 'idemailwiz_folder',
				'parent'   => $term->term_id,
			)
		);
		$children_ids = array();
		foreach ( $children as $child ) {
			$children_ids[] = $child->term_id;
		}
		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy' => 'idemailwiz_folder',
					'terms'    => $children_ids,
					'field'    => 'term_id',
					'operator' => 'NOT IN',
				),
			)
		);

		$queried_object = get_queried_object();
		$options        = get_option( 'idemailwiz_settings' );
		$trashTerm      = (int) $options['folder_trash'];

		if ( $queried_object->term_id == (int) $trashTerm ) {
			$query->set( 'post_status', array( 'trash' ) );
			error_log( 'Setting post_status to trash' );
		}

		// Surface the current user's favorites first inside this folder.
		$current_user_id    = get_current_user_id();
		$favorite_templates = get_user_meta( $current_user_id, 'favorite_templates', true );
		$favorite_templates = is_array( $favorite_templates ) ? $favorite_templates : array();

		$favorite_templates = array_filter(
			$favorite_templates,
			function ( $post_id ) use ( $term ) {
				$post_terms = wp_get_post_terms( $post_id, 'idemailwiz_folder', array( 'fields' => 'ids' ) );
				return in_array( $term->term_id, $post_terms );
			}
		);

		$term_posts = get_posts(
			array(
				'post_type'      => 'idemailwiz_template',
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'idemailwiz_folder',
						'terms'    => $term->term_id,
					),
				),
				'post__not_in'   => $favorite_templates,
				'post_status'    => array( 'publish', 'trash' ),
			)
		);

		usort(
			$term_posts,
			function ( $a, $b ) {
				return strtotime( $b->post_modified ) - strtotime( $a->post_modified );
			}
		);

		$all_posts = array_merge( wp_list_pluck( $term_posts, 'ID' ), array_values( $favorite_templates ) );

		$query->set( 'post__in', $all_posts );
		$query->set( 'orderby', 'post__in' );
	}
}
