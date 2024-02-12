<?php
/*
 * Plugin Name: Justine's Photo Galery
 * Version:     0.0.1
 * Author:      Antoine Kaufmann
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

 define( 'JKPG_DIR_PATH', plugin_dir_path( __FILE__ ) );

require_once dirname( __FILE__ ) .'/includes/adobe-api.php';
require_once dirname( __FILE__ ) .'/includes/db.php';
require_once dirname( __FILE__ ) .'/includes/post.php';
require_once dirname( __FILE__ ) .'/includes/settings.php';
require_once dirname( __FILE__ ) .'/includes/mgmt.php';


/**
 * add jkpg css
 */
function jkpg_register_assets() {
  wp_enqueue_style( 'jkpgStylesheet',
    plugin_dir_url(__FILE__) . 'assets/css/main.css');
  wp_enqueue_script( 'jkpgStylesheet',
    plugin_dir_url(__FILE__) . 'assets/js/single.js', array( 'jquery' ));
}
add_action( 'init', 'jkpg_register_assets' );

function jkpg_register_bgproc() {
  global $jkpg_bgproc;
  $jkpg_bgproc = new JKPGBgProcess();
}
add_action( 'init', 'jkpg_register_bgproc' );


/**
 *  * Activate the plugin.
 *   */
function jkpg_activate() {
  // Trigger our function that registers the custom post type plugin.
  //jkpg_setup_post_type();
  // Clear the permalinks after the post type has been registered.
  //flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'jkpg_activate' );

/**
 * Deactivation hook.
 */
function jkpg_deactivate() {
  // Unregister the post type, so the rules are no longer in memory.
  //unregister_post_type( 'book' );
  // Clear the permalinks to remove our post type's rules from the database.
  //flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'jkpg_deactivate' );

/**
 * Uninstall hook.
 */
function jkpg_uninstall() {
  // Unregister the post type, so the rules are no longer in memory.
  //unregister_post_type( 'book' );
  // Clear the permalinks to remove our post type's rules from the database.
  //flush_rewrite_rules();
}
register_uninstall_hook( __FILE__, 'jkpg_uninstall' );

function jkpg_options_page() {
    $hookname = add_menu_page(
        'Justine\'s Photo Gallery',
        'JK Gallery',
        'manage_options',
        'jkpg',
        'jkpg_mgmt_page_html',
        '', // icon
        20
    );

    add_submenu_page(
        'jkpg',
        'Justine\'s Photo Gallery - Settings',
        'Settings',
        'manage_options',
        'jkpg_settings',
        'jkpg_options_page_html'
    );
}
add_action( 'admin_menu', 'jkpg_options_page' );



