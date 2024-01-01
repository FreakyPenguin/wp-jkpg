<?php

function jkpg_init_post() {
  register_post_type('jkpg', array(
      'publicly_queryable' => true,
      'exclude_from_search' => false,
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
