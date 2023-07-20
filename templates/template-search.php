<?php get_header(); ?>

<div class="templateFolder">
<div class="folderList">
<div id="addNewFolder"><i title="Add new folder" class="fa-solid fa-circle-plus"></i></div>
  <?php 
  // Get the current category ID
  $current_category_id = 's';
  get_folder_list(); 
  ?>
</div>

<div class="templateTable">
<h1>Search results for "<?php echo $_GET['s']; ?>"</h1>
<table>


  <tbody>
 
 <?php if ( have_posts() ) { ?>
	<thead>
    <tr>
      <th></th>
      <th>Template Name</th>
      <th>Created</th>
      <th>Last Updated</th>
      <th><center>Actions</center></th>
      <th><?php if (get_post_status(get_the_ID()) != 'trash') { ?><center>Sync</center><?php } ?></th>
    </tr>
  </thead>
<?php while ( have_posts() ) { the_post(); 
 // Get the post data
    $post_title = get_the_title();
	$post_link = get_permalink();
	$post_date = date( 'm/d/Y', strtotime( get_the_date() ) );
	$post_author = get_the_author();
	$post_modified = date( 'm/d/Y', strtotime( get_the_modified_date() ) );
	$post_modified_by_id = get_post_field( 'post_modified_by', get_the_ID() );
	$post_modified_by = get_userdata( $post_modified_by_id );
	$post_modified_author = ! empty( $post_modified_by ) ? $post_modified_by->display_name : $post_author;
		$trashedClass = '';
	if (get_post_status(get_the_ID()) == 'trash') {
		$trashedClass = 'trashed';
	}
	
	  ?>
    <tr id="template-<?php echo get_the_ID(); ?>" class="<?php if (is_user_favorite(get_the_ID(), 'template')) { echo 'favorite';} ?> <?php echo $trashedClass; ?>" data-foldertitle="<?php echo get_the_title(); ?>">
	  <?php if (is_user_favorite(get_the_ID(), 'template')) {
		  $starClass = 'fa-solid';
	  } else {
		  $starClass = 'fa-regular';
	  }
	  
	  
	  ?>
      <td valign="center" align="center"><i class="fa-regular fa-file-lines"></i></td>
      <td valign="center"><a href="<?php echo esc_url( $post_link ); ?>"><?php echo esc_html( $post_title ); ?></a></td>
      <td valign="center"><?php echo esc_html( $post_date ); ?> by <?php echo esc_html( $post_author ); ?></td>
      <td valign="center"><?php echo esc_html( $post_modified ); ?> by <?php echo esc_html( $post_modified_author ); ?></td>
      <td class="templateActions" valign="center" align="center" style="white-space:nowrap;">
	  <?php echo '<a title="Favorite Folder"> <i class="'.$starClass.' fa-star addRemoveFavorite favFolder" data-objectid="'.get_the_ID().'"></i></a>&nbsp;&nbsp;'; ?>
		<?php if (get_post_status(get_the_ID()) != 'trash') { ?>
        <i class="fa fa-copy duplicate-template" data-postid="<?php echo get_the_ID(); ?>"></i>&nbsp;&nbsp;&nbsp;<i class="fa fa-trash delete-template" data-postid="<?php echo get_the_ID(); ?>"></i>
		<?php } else { ?>
		<i title="Restore from trash" class="fa fa-trash-arrow-up restore-template" data-postid="<?php echo get_the_ID(); ?>"></i>
		<?php } ?>
      </td>
	  <td valign="center" align="center">
	  <?php
	  if (get_post_status(get_the_ID()) != 'trash') {
		$itTemplateId = get_post_meta(get_the_ID(),'itTemplateId',true);
		
		if ($itTemplateId == true) {
			echo '<a href="https://app.iterable.com/templates/editor?templateId='.$itTemplateId.'"><strong>'.$itTemplateId.'</strong></a>';
		} else {
			echo ' — ';
		}
	  }
	 ?>
	  </td>
    </tr>
<?php } ?>
  </tbody>
</table>
 <?php
  // Display pagination links
  global $wp_query;

  $total_pages = $wp_query->max_num_pages;
  $current_page = max( 1, get_query_var( 'paged' ) );

  echo '<div class="pagination">';
  echo paginate_links( array(
    'base' => get_pagenum_link( 1 ) . '%_%',
    'format' => 'page/%#%',
    'current' => $current_page,
    'total' => $total_pages,
  ) );
  echo '</div>';
  ?>
<?php } else {
		echo '</tbody></table><br/><em>No templates found...</em>';
	} ?>
</div>
</div>

<?php get_footer(); ?>