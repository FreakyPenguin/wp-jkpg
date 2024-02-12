<?php

function jkpg_init_post() {
  register_post_type('jkpg', array(
      'public' => true,
      'publicly_queryable' => true,
      'exclude_from_search' => false,
      'has_archive' => 'gallery',
      'labels' => array(
        'archives' => 'Albums'
      )
  ));
}
add_action( 'init', 'jkpg_init_post' );

function jkpg_post_template( $single_template ) {
	global $post;

	if ( $post->post_type == 'jkpg' ) {
		$single_template = dirname( __FILE__ ) . '/../templates/single-jkpg.php';
	}

	return $single_template;
}
add_filter( 'single_template', 'jkpg_post_template' );

function jkpg_archive_template( $archive_template ) {
	global $post;

	if ( is_post_type_archive('jkpg') ) {
		$archive_template = dirname( __FILE__ ) . '/../templates/archive-jkpg.php';
	}

	return $archive_template;
}
add_filter( 'archive_template', 'jkpg_archive_template' );

function jkpg_disable_pagination( $query ) {
  if( get_post_type() == 'jkpg' ) {
    $query->set( 'nopaging' , true );
  }
}
add_action( 'pre_get_posts', 'jkpg_disable_pagination' );

function jkpg_page_template( $page_template ){
    if ( get_page_template_slug() == 'template-jkpg-home.php' ) {
        $page_template = dirname( __FILE__ ) . '/../templates/template-jkpg-home.php';
    }
    return $page_template;
}
add_filter( 'page_template', 'jkpg_page_template' );

function jkpg_add_template_to_select( $post_templates, $wp_theme, $post, $post_type ) {
    $post_templates['template-jkpg-home.php'] = 'JKPG Home';
    return $post_templates;
}
add_filter( 'theme_page_templates', 'jkpg_add_template_to_select', 10, 4 );

function jkpg_add_meta_box()
{
    add_meta_box(
        'jkpg-meta-box', // id, used as the html id att
        'JKPG Attributes', // meta box title, like "Page Attributes"
        'jkpg_meta_box_cb', // callback function, spits out the content
        'page', // post type or page. We'll add this to pages only
        'side', // context (where on the screen
        'low' // priority, where should this go in the context?
    );
}
add_action( 'add_meta_boxes', 'jkpg_add_meta_box' );

function jkpg_meta_box_cb( $post )
{
    if (get_post_meta(get_the_ID(), '_wp_page_template', true)
        != 'template-jkpg-home.php')
      return;

    $options = get_option( 'jkpg_options' );
    $rootset = $options['jkpg_setting_adobe_rootset'];

    $cur_alb = get_post_meta( $post->ID, 'jpkg_page_album', 1 );
    ?>
    <p class='post-attributes-label-wrapper'>
      <label class='post-attributes-label' for='jpkg_album'>
        <select name='jpkg_page_album' id='jpkg_page_album'>
          <option value=''></option>
          <?php
          foreach (jkpg_collect_albums($rootset) as $alb) {
            $sel = $cur_alb == $alb->id ? 'selected' : '';
            echo "<option value='{$alb->id}' $sel>{$alb->title}</option>\n";
          }
          ?>
        </select>Album
      </label>
    </p> <?
}

function jkpg_save_post_page( $post_id ) {
  if (isset( $_POST['jpkg_page_album'] ))
    update_post_meta( $post_id, 'jpkg_page_album', $_POST['jpkg_page_album'] );
}
add_action( 'save_post_page', 'jkpg_save_post_page' );