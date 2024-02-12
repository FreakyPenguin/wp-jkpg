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