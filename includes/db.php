<?php

$jkpg_db_version = '0.0.14';

function jkpg_db_install() {
	global $wpdb;
	global $jkpg_db_version;
	
	$charset_collate = $wpdb->get_charset_collate();
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  $table_name_albums = $wpdb->prefix . 'jkpg_albums';
	$sql = "CREATE TABLE $table_name_albums (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
    adobe_id char(32) NOT NULL,
    set_id char(32),
		created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
    updated  datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		title text NOT NULL,
    description text NOT NULL,
    synchronized boolean DEFAULT 0 NOT NULL,
    piclist_fetched boolean DEFAULT 0 NOT NULL,
    deleted boolean DEFAULT 0 NOT NULL,
		PRIMARY KEY  (id),
    KEY adobe_id (adobe_id)
	) $charset_collate;";
	dbDelta( $sql );

  $table_name_sets = $wpdb->prefix . 'jkpg_sets';
	$sql = "CREATE TABLE $table_name_sets (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
    adobe_id char(32) NOT NULL,
    parent_id char(32),
		created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
    updated  datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		title text NOT NULL,
    description text NOT NULL,
    synchronized boolean DEFAULT 0 NOT NULL,
    deleted boolean DEFAULT 0 NOT NULL,
		PRIMARY KEY  (id),
    KEY adobe_id (adobe_id)
	) $charset_collate;";
	dbDelta( $sql );

  $table_name_pics = $wpdb->prefix . 'jkpg_pictures';
	$sql = "CREATE TABLE $table_name_pics (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
    adobe_id char(32) NOT NULL,
    width mediumint(9) DEFAULT 0 NOT NULL,
    height mediumint(9) DEFAULT 0 NOT NULL,
		created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
    updated  datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
    captured  datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		title text NOT NULL,
    description text NOT NULL,
    synchronized boolean DEFAULT 0 NOT NULL,
    requested boolean DEFAULT 0 NOT NULL,
    retrieved boolean DEFAULT 0 NOT NULL,
    readied boolean DEFAULT 0 NOT NULL,
    deleted boolean DEFAULT 0 NOT NULL,
    sizes tinytext DEFAULT '' NOT NULL,
		PRIMARY KEY  (id),
    KEY adobe_id (adobe_id)
	) $charset_collate;";
	dbDelta( $sql );

  $table_name_p2a = $wpdb->prefix . 'jkpg_p2a';
	$sql = "CREATE TABLE $table_name_p2a (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    picture mediumint(9) NOT NULL,
    album mediumint(9) NOT NULL,
    ord varchar(1024) DEFAULT '' NOT NULL,
    cover boolean DEFAULT 0 NOT NULL,
    PRIMARY KEY  (id),
    KEY picture (picture),
    KEY album (album),
    KEY cover (cover),
	) $charset_collate;";
	dbDelta( $sql );

  $table_name_log = $wpdb->prefix . 'jkpg_log';
	$sql = "CREATE TABLE $table_name_log (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    message text NOT NULL,
    level tinyint DEFAULT 0 NOT NULL,
    ts timestamp NOT NULL,
    seen boolean DEFAULT 0 NOT NULL,
    dismissed boolean DEFAULT 0 NOT NULL,
    PRIMARY KEY  (id)
    KEY level (level)
	) $charset_collate;";
	dbDelta( $sql );

  if ( !get_site_option('jkpg_db_version') )
	  add_option( 'jkpg_db_version', $jkpg_db_version );
  else
    update_option( 'jkpg_db_version', $jkpg_db_version );
}

function jkpg_update_db_check() {
    global $jkpg_db_version;
    if ( get_site_option( 'jkpg_db_version' ) != $jkpg_db_version ) {
        jkpg_db_install();
    }
}
add_action( 'plugins_loaded', 'jkpg_update_db_check' );

function jkpg_db_set_get_adobe($adobe_id) {
  global $wpdb;
  return $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}jkpg_sets WHERE adobe_id = '$adobe_id'" );
}

function jkpg_db_sets_get() {
  global $wpdb;
  return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}jkpg_sets WHERE deleted = 0" );
}

function jkpg_db_sets_get_in($parent_id) {
  global $wpdb;
  return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}jkpg_sets WHERE parent_id = '$parent_id' AND deleted = 0" );
}

function jkpg_db_set_update($id, $parent_id, $updated, $title)
{
  global $wpdb;
  $wpdb->query(
    $wpdb->prepare(
       "UPDATE {$wpdb->prefix}jkpg_sets SET
          parent_id = %s, updated = %s, title = %s, deleted = 0
        WHERE id = %d",
       $parent_id, $updated, $title, $id
    )
  );
}

function jkpg_db_set_insert($adobe_id, $parent_id, $created, $updated, $title) {
  global $wpdb;
  $wpdb->query(
    $wpdb->prepare(
       "INSERT INTO {$wpdb->prefix}jkpg_sets
       ( adobe_id, parent_id, created, updated, title )
       VALUES ( %s, %s, %s, %s, %s )",
       $adobe_id, $parent_id, $created, $updated, $title
    )
  );
}

function jkpg_db_set_deleted($id) {
  global $wpdb;
  $wpdb->query(
    "UPDATE {$wpdb->prefix}jkpg_sets SET deleted = 1 WHERE id = $id"
  );
}


function jkpg_db_album_get($id) {
  global $wpdb;
  return $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}jkpg_albums WHERE id = '$id'" );
}

function jkpg_db_album_get_adobe($adobe_id) {
  global $wpdb;
  return $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}jkpg_albums WHERE adobe_id = '$adobe_id'" );
}

function jkpg_db_albums_get() {
  global $wpdb;
  return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}jkpg_albums WHERE deleted = 0" );
}

function jkpg_db_albums_get_in($parent_id) {
  global $wpdb;
  return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}jkpg_albums WHERE set_id = '$parent_id' and deleted = 0" );
}

function jkpg_db_album_update($id, $set_id, $updated, $title, $desc, $sync,
                              $piclist_fetched)
{
  global $wpdb;
  $wpdb->query(
    $wpdb->prepare(
       "UPDATE {$wpdb->prefix}jkpg_albums SET
          set_id = %s, updated = %s, title = %s, description = %s,
          synchronized = %d, piclist_fetched = %d, deleted = 0
        WHERE id = %d",
       $set_id, $updated, $title, $desc, $sync, $piclist_fetched, $id
    )
  );
}

function jkpg_db_album_insert($adobe_id, $set_id, $created, $updated, $title,
                              $desc) {
  global $wpdb;
  $wpdb->query(
    $wpdb->prepare(
       "INSERT INTO {$wpdb->prefix}jkpg_albums
       ( adobe_id, set_id, created, updated, title, description )
       VALUES ( %s, %s, %s, %s, %s, %s )",
       $adobe_id, $set_id, $created, $updated, $title, $desc
    )
  );
}

function jkpg_db_album_deleted($id) {
  global $wpdb;
  $wpdb->query(
    "UPDATE {$wpdb->prefix}jkpg_albums SET deleted = 1 WHERE id = $id"
  );
}

function jkpg_db_album_setflag($id, $flagname, $val) {
  global $wpdb;
  $wpdb->query(
    "UPDATE {$wpdb->prefix}jkpg_albums SET $flagname = $val WHERE id = $id"
  );
}


function jkpg_db_pic_get($id) {
  global $wpdb;
  return $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}jkpg_pictures WHERE id = '$id'" );
}

function jkpg_db_pic_get_adobe($adobe_id) {
  global $wpdb;
  return $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}jkpg_pictures WHERE adobe_id = '$adobe_id'" );
}

function jkpg_db_pic_update($id, $updated, $captured, $title, $desc, $sync)
{
  global $wpdb;
  $wpdb->query(
    $wpdb->prepare(
       "UPDATE {$wpdb->prefix}jkpg_pictures SET
          updated = %s, captured = %s, title = %s, description = %s, synchronized = %d,
          deleted = 0
        WHERE id = %d",
       $updated, $captured, $title, $desc, $sync, $id
    )
  );
}

function jkpg_db_pic_insert($adobe_id, $created, $updated, $captured, $title, $desc) {
  global $wpdb;
  $wpdb->query(
    $wpdb->prepare(
       "INSERT INTO {$wpdb->prefix}jkpg_pictures
       ( adobe_id, created, updated, captured, title, description )
       VALUES ( %s, %s, %s, %s, %s, %s )",
       $adobe_id, $created, $updated, $captured, $title, $desc
    )
  );
}

function jkpg_db_pic_deleted($id) {
  global $wpdb;
  $wpdb->query(
    "UPDATE {$wpdb->prefix}jkpg_pictures SET deleted = 1 WHERE id = $id"
  );
}

function jkpg_db_pic_setflag($id, $flagname, $val) {
  global $wpdb;
  $wpdb->query(
    "UPDATE {$wpdb->prefix}jkpg_pictures SET $flagname = $val WHERE id = $id"
  );
}

function jkpg_db_pic_setsizes($id, $sizes) {
  global $wpdb;
  $wpdb->query(
    "UPDATE {$wpdb->prefix}jkpg_pictures SET sizes = '$sizes' WHERE id = $id"
  );
}


function jkpg_db_p2a_get($pic_id, $album_id) {
  global $wpdb;
  return $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}jkpg_p2a WHERE picture = $pic_id AND album = $album_id" );
}

function jkpg_db_p2a_insert($pic_id, $album_id, $ord, $cover) {
  global $wpdb;
  $wpdb->query(
    $wpdb->prepare(
       "INSERT INTO {$wpdb->prefix}jkpg_p2a
       ( picture, album, ord, cover ) VALUES ( %d, %d, %s, %d )",
       $pic_id, $album_id, $ord, $cover
    )
  );
}

function jkpg_db_p2a_update($pic_id, $album_id, $ord, $cover) {
  global $wpdb;
  $wpdb->query(
    $wpdb->prepare(
       "UPDATE {$wpdb->prefix}jkpg_p2a
       SET ord = %s, cover = %d
       WHERE picture = %d AND album = %d",
       $ord, $cover, $pic_id, $album_id
    )
  );
}

function jkpg_db_p2a_cover($album_id) {
  global $wpdb;
  $pid = $wpdb->get_var(
    $wpdb->prepare(
       "SELECT picture FROM {$wpdb->prefix}jkpg_p2a
       WHERE album = %d AND cover = 1
       LIMIT 1",
       $album_id
    )
  );
  if ($pid)
    return $pid;
  return jkpg_db_p2a_pic_ids($album_id)[0];
}

function jkpg_db_p2a_pic_ids($album_id) {
  global $wpdb;
  $explictly_ordered =  $wpdb->get_col(
    $wpdb->prepare(
       "SELECT picture FROM {$wpdb->prefix}jkpg_p2a
       WHERE album = %d AND ord <> ''
       ORDER BY ord ASC",
       $album_id
    )
  );

  $implictly_ordered =  $wpdb->get_col(
    $wpdb->prepare(
       "SELECT picture FROM {$wpdb->prefix}jkpg_p2a
       INNER JOIN {$wpdb->prefix}jkpg_pictures
          ON picture = {$wpdb->prefix}jkpg_pictures.id
       WHERE album = %d AND ord = ''
       ORDER BY captured DESC",
       $album_id
    )
  );

  return array_merge($implictly_ordered, $explictly_ordered);
}

function jkpg_db_p2a_delete($pic_id, $album_id) {
  global $wpdb;
  return $wpdb->get_col(
    $wpdb->prepare(
       "DELETE FROM {$wpdb->prefix}jkpg_p2a
       WHERE picture = %d AND album = %d", $pic_id, $album_id
    )
  );
}


function jkpg_db_log_insert($level, $msg) {
  global $wpdb;
  $wpdb->query(
    $wpdb->prepare(
       "INSERT INTO {$wpdb->prefix}jkpg_log
       ( message, level ) VALUES ( %s, %d )",
       $msg, $level
    )
  );
}

function jkpg_db_log_get() {
  global $wpdb;
  return $wpdb->get_results(
    $wpdb->prepare(
       "SELECT * FROM {$wpdb->prefix}jkpg_log
       WHERE dismissed = 0
       ORDER BY level DESC, ts DESC"
    )
  );
}

function jkpg_db_log_clear_upto($id) {
  global $wpdb;
  $wpdb->query(
    $wpdb->prepare(
       "UPDATE {$wpdb->prefix}jkpg_log
       SET dismissed = 1
       WHERE $id <= %d AND dismissed = 0",
       $id
    )
  );
}