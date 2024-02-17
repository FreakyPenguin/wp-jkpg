<?php

require_once(dirname( __FILE__ ) . '/../pel/autoload.php');
require_once(dirname( __FILE__ ) . '/../wp-background-processing/wp-background-processing.php');

use lsolesen\pel\PelExif;
use lsolesen\pel\PelTiff;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelEntryAscii;
use lsolesen\pel\PelEntryCopyright;
use lsolesen\pel\PelTag;

function jkpg_sync_error($msg) {
  jkpg_db_log_insert(3, $msg);
}

function jkpg_sync_warn($msg) {
  jkpg_db_log_insert(2, $msg);
}

function jkpg_sync_notice($msg) {
  $options = get_option( 'jkpg_options' );
  jkpg_db_log_insert(1, $msg);
}

function jkpg_adobe_date_to_db($d) {
  $datetime = new DateTime($d);
  return $datetime->format('Y-m-d H:i:s');
}

function jkpg_mgmt_adobe_client() {
  $options = get_option( 'jkpg_options' );
  $lrc = new JKPGAdobeLRClient(
    $options['jkpg_setting_adobe_clientid'],
    $options['jkpg_setting_adobe_token']);
  return $lrc;
}

function jkpg_mgmt_sync_albums() {
  $lrc = jkpg_mgmt_adobe_client();
  $cat = $lrc->get_catalog();
  $albums_req = $lrc->get_all_albums($cat->id);

  $alb_ids = array();
  $set_ids = array();

  foreach ($albums_req as $alb) {
    if ($alb->type != 'album') {
      jkpg_sync_warn("Non-album entry {$alb->id} of type {$alb->type}, ignoring");
      continue;
    }

    $parent_id = null;
    if (isset($alb->payload->parent))
      $parent_id = $alb->payload->parent->id;

    if ($alb->subtype == 'collection') {
      $alb_ids[] = $alb->id;

      $la = jkpg_db_album_get_adobe($alb->id);
      if ($la) {
        $ud = jkpg_adobe_date_to_db($alb->updated);

        /* if album has been updated, mark as not synchronized */
        if ($ud != $la->updated) {
          $la->synchronized = 0;
          $la->piclist_fetched = 0;
        }

        jkpg_db_album_update($la->id, $parent_id, $ud, $alb->payload->name,
          '', $la->synchronized, $la->piclist_fetched);
      } else {
        /* TODO: caption? */
        jkpg_db_album_insert($alb->id, $parent_id,
          jkpg_adobe_date_to_db($alb->created),
          jkpg_adobe_date_to_db($alb->updated), $alb->payload->name, '');

      }
    } else if ($alb->subtype == 'collection_set') {
      $set_ids[] = $alb->id;

      $la = jkpg_db_set_get_adobe($alb->id);
      if ($la) {
        jkpg_db_set_update($la->id, $parent_id,
          jkpg_adobe_date_to_db($alb->updated),
          $alb->payload->name);
      } else {
        jkpg_db_set_insert($alb->id, $parent_id,
          jkpg_adobe_date_to_db($alb->created),
          jkpg_adobe_date_to_db($alb->updated), $alb->payload->name);
      }
    } else {
      jkpg_sync_warn("Unknown subtype {$alb->subtype} for {$alb->id}, ignoring");
      continue;
    }
  }

  // now check for deleted sets & albums
  $sets = jkpg_db_sets_get();
  foreach ($sets as $set) {
    if (!in_array($set->adobe_id, $set_ids)) {
      jkpg_db_set_deleted($set->id);
    }
  }
  $albums = jkpg_db_albums_get();
  foreach ($albums as $alb) {
    if (!in_array($alb->adobe_id, $alb_ids)) {
      jkpg_db_album_deleted($alb->id);
    }
  }

  jkpg_sync_notice('Synchronized catalog successfully.');
}

function jkpg_mgmt_sync_album($adobe_id) {
  $alb = jkpg_db_album_get_adobe($adobe_id);
  if ($alb->piclist_fetched) {
    jkpg_sync_notice("Skipped Fetching Album $adobe_id Pic list, already up to date.");
    return;
  }

  $lrc = jkpg_mgmt_adobe_client();
  $cat = $lrc->get_catalog();
  $assets = $lrc->get_all_assets($cat->id, $adobe_id);

  $pic_ids = array();

  foreach ($assets as $a) {
    // just skip non-images for now
    if ($a->subtype != 'image')
      continue;

    $pic = jkpg_db_pic_get_adobe($a->id);
    if ($pic) {
      //echo "Updating {$a->id}<br/>";
      $ud = jkpg_adobe_date_to_db($a->updated);

      // if album has been updated, mark as not synchronized
      if ($ud != $pic->updated)
        $pic->synchronized = 0;

      // TODO: title, description?
      jkpg_db_pic_update($pic->id, $ud,
        jkpg_adobe_date_to_db($a->payload->captureDate), '', '',
        $pic->synchronized);
    } else {
      //echo "Inserting {$a->id}<br/>";
      // TODO: title, description?
      jkpg_db_pic_insert($a->id, jkpg_adobe_date_to_db($a->created),
        jkpg_adobe_date_to_db($a->updated),
        jkpg_adobe_date_to_db($a->payload->captureDate), '', '');

      $pic = jkpg_db_pic_get_adobe($a->id);
    }
    $pic_ids[] = $pic->id;

    $p2a = jkpg_db_p2a_get($pic->id, $alb->id);
    if (!$p2a)
      jkpg_db_p2a_insert($pic->id, $alb->id, $a->ord, $a->cover);
    else
      jkpg_db_p2a_update($pic->id, $alb->id, $a->ord, $a->cover);
  }

  // remove deleted pics
  $pics = jkpg_db_p2a_pic_ids($alb->id);
  foreach ($pics as $picid) {
    if (!in_array($picid, $pic_ids)) {
      jkpg_db_p2a_delete($picid, $alb->id);
    }
  }

  jkpg_db_album_setflag($alb->id, 'piclist_fetched', 1);
  jkpg_sync_notice("Synchronized Album $adobe_id.");
}

function jkpg_mgmt_req_renditions($adobe_id) {
  $lrc = jkpg_mgmt_adobe_client();
  $cat = $lrc->get_catalog();

  $alb = jkpg_db_album_get_adobe($adobe_id);
  $picids = jkpg_db_p2a_pic_ids($alb->id);

  foreach ($picids as $id) {
    $pic = jkpg_db_pic_get($id);
    if ($pic->requested)
      continue;
    $x = $lrc->request_rendition($cat->id, $pic->adobe_id);
    jkpg_db_pic_setflag($pic->id, 'requested', 1);
  } 

  jkpg_sync_notice("Requested Renditions in Album $adobe_id.");
}

function jkpg_renditions_dir() {
  return JKPG_DIR_PATH . 'raw_renditions/';
}

function jkpg_raw_rendition_fn($adobe_id) {
  return jkpg_renditions_dir() . $adobe_id . ".jpg";
}

function jkpg_pic_path($adobe_id, $size) {
  $upload_dir = wp_upload_dir();
  return "{$upload_dir['basedir']}/jkpg/$adobe_id-$size.jpg";
}

function jkpg_watermark_path($adobe_id, $size) {
  $upload_dir = wp_upload_dir();
  return "{$upload_dir['basedir']}/jkpg/$adobe_id-$size.jpg";
}

function jkpg_mgmt_fetch_renditions($adobe_id) {
  $lrc = jkpg_mgmt_adobe_client();
  $cat = $lrc->get_catalog();

  $alb = jkpg_db_album_get_adobe($adobe_id);
  $picids = jkpg_db_p2a_pic_ids($alb->id);
  $dir = jkpg_renditions_dir();
  foreach ($picids as $id) {
    $pic = jkpg_db_pic_get($id);
    if ($pic->retrieved)
      continue;

    $data = $lrc->get_rendition($cat->id, $pic->adobe_id);
    file_put_contents(jkpg_raw_rendition_fn($pic->adobe_id), $data);

    jkpg_db_pic_setflag($pic->id, 'retrieved', 1);
  }

  jkpg_sync_notice("Fetched Renditions in Album $adobe_id.");
}

function jkpg_mgmt_prepare_renditions($adobe_id) {
  $alb = jkpg_db_album_get_adobe($adobe_id);
  $picids = jkpg_db_p2a_pic_ids($alb->id);

  $options = get_option( 'jkpg_options' );
  $sizes = explode(",", $options['jkpg_setting_pictures_sizes']);

  $upload_dir = wp_upload_dir();
  $watermark = new Imagick();
  $watermark->setBackgroundColor(new ImagickPixel('transparent'));
  $watermark->readImage($upload_dir['basedir'] . '/' . $options['jkpg_setting_pictures_wmfile']);
  $wmgeom = $watermark->getImageGeometry();

  $dir = jkpg_renditions_dir();
  foreach ($picids as $id) {
    $pic = jkpg_db_pic_get($id);
    if (!$pic->retrieved || $pic->readied)
      continue;

    // delete old files (note these may have different sizes of settings or
    // rendition changed)
    $oldfiles = glob(jkpg_pic_path($pic->adobe_id, '*'));
    if ($oldfiles) {
      foreach ($oldfiles as $of)
        unlink($of);
    }

    // Load rendition
    $rendition = new Imagick();
    $rendition->readImage(jkpg_raw_rendition_fn($pic->adobe_id));
    $geom = $rendition->getImageGeometry();
    $maxdim = max($geom['width'], $geom['height']);

    // prepare appropriately sized watermark
    $wminstance = clone $watermark;
    $wmW = round(($options['jkpg_setting_pictures_wmwidth'] / 100) * $geom['width']);
    $wmH = round(($options['jkpg_setting_pictures_wmheight'] / 100) * $geom['height']);
    $wminstance->resizeImage($wmW, $wmH, imagick::FILTER_LANCZOS, 0.9, true);
    $wmiG = $wminstance->getImageGeometry();
    $wmW = $wmiG['width'];
    $wmH = $wmiG['height'];

    // add watermark
    $xfrac = $options['jkpg_setting_pictures_wmx'] / 100;
    $yfrac = $options['jkpg_setting_pictures_wmy'] / 100;
    $wmX = round($xfrac * ($geom['width'] - $wmW));
    $wmY = round($yfrac * ($geom['height'] - $wmH));
    $rendition->compositeImage($wminstance, imagick::COMPOSITE_BLEND, $wmX, $wmY);

    // make sure original size is included
    $oursizes = $sizes;
    $oursizes[] = $maxdim;
    $oursizes = array_unique($oursizes);

    $generated_sizes = [];
    foreach ($oursizes as $sz) {
      // avoid upscaling if image is smaller than requested size
      if ($geom['width'] < $sz && $geom['height'] < $sz)
        continue;

      $newpic = clone $rendition;
      // scale down if both are larger (avoids scaling for already correct size)
      if ($geom['width'] > $sz && $geom['height'] > $sz)
        $newpic->resizeImage($sz,$sz, imagick::FILTER_LANCZOS, 0.9, true);

      // write out generated image
      $newgeom = $newpic->getImageGeometry();
      $gensz = $newgeom['width'] . "x" . $newgeom['height'];
      $fn = jkpg_pic_path($pic->adobe_id, $gensz);
      $newpic->writeImage($fn);

      // clear possible old exif, add chosen fields
      $jpeg = new PelJpeg($fn);
      $jpeg->clearExif();
      $exif = new PelExif();
      $tiff = new PelTiff();
      $ifd0 = new PelIfd(PelIfd::IFD0);
      $tiff->setIfd($ifd0);
      $exif->setTiff($tiff);
      $jpeg->setExif($exif);

      $entry = new PelEntryAscii(PelTag::ARTIST, $options['jkpg_setting_pictures_artist']);
      $ifd0->addEntry($entry);
      $entry = new PelEntryCopyright($options['jkpg_setting_pictures_copyright'], $options['jkpg_setting_pictures_copyright']);
      $ifd0->addEntry($entry);
      $jpeg->saveFile($fn);

      $generated_sizes[] = $gensz;
    }

    jkpg_db_pic_setsizes($pic->id, implode(",", $generated_sizes));
    jkpg_db_pic_setflag($pic->id, 'readied', 1);
  }

  jkpg_db_album_setflag($alb->id, 'synchronized', 1);
  jkpg_sync_notice("Prepared Renditions in Album $adobe_id.");
}

function jkpg_mgmt_album_post($id) {
  $posts = get_posts(array(
    'post_type' => 'jkpg',
    'post_status' => 'publish',
    'meta_query' => array(
      array(
        'key' => 'jkpg_album',
        'value' => $id,
      )
    ),
  ));
  if ($posts)
    return $posts[0];
  return null;
}

function jkpg_mgmt_create_post($adobe_id) {
  $alb = jkpg_db_album_get_adobe($adobe_id);
  if (!$alb) {
    echo "jkpg_mgmt_create_post bald album: $adobe_id<br>\n";
    return;
  }

  $post = jkpg_mgmt_album_post($alb->id);
  if (!$post) {
    $pid = wp_insert_post(array(
        'post_type' => 'jkpg',
        'post_date' => $alb->created,
        'post_title' => $alb->title,
        'post_status' => 'publish',
        'meta_input' => array(
          'jkpg_album' => $alb->id,
        ),
    ));
  } else {
    $pid = wp_update_post(array(
      'ID' => $post->ID,
      'post_date' => $alb->created,
      'post_title' => $alb->title,
      'post_status' => 'publish',
      'meta_input' => array(
        'jkpg_album' => $alb->id,
      ),
    ));
  }

}

function jkpg_mgmt_enqueue_sync_albums($parent_id, $recursive) {
  global $jkpg_bgproc;

  jkpg_sync_notice("Enqueuing albums in $parent_id rec=$recursive");
  foreach (jkpg_db_albums_get_in($parent_id) as $alb) {
    if ($alb->synchronized) {
      jkpg_sync_notice("Skipping {$alb->adobe_id}");
      continue;
    }
    $op = array('op' => 'sync_album', 'adobe_id' => $alb->adobe_id,
      'recursive' => $recursive);
    $jkpg_bgproc->push_to_queue($op);
  }

  foreach (jkpg_db_sets_get_in($parent_id) as $set) {
    jkpg_mgmt_enqueue_sync_albums($set->adobe_id, $recursive);
  }
}

function jkpg_collect_albums($parent_id) {
  $albs = [];
  foreach (jkpg_db_albums_get_in($parent_id) as $alb) {
    $albs[] = $alb;
  }
  foreach (jkpg_db_sets_get_in($parent_id) as $set) {
    $albs = array_merge($albs, jkpg_collect_albums($set->adobe_id));
  }
  return $albs;
}

function jkpg_mgmt_sync_posts($parent_id) {
  $albs = jkpg_collect_albums($parent_id);
  $alb_ids = array();

  // first update posts for current/new albums
  foreach ($albs as $alb) {
    $alb_ids[] = $alb->id;
    jkpg_mgmt_create_post($alb->adobe_id);
  }

  // then delete posts for old albums
  $posts = get_posts(array(
    'numberposts' => -1,
    'post_type' => 'jkpg',
    'post_status' => 'publish'
  ));
  foreach ($posts as $p) {
    $alb = get_post_meta($p->ID, 'jkpg_album', true);

    if (!in_array($alb, $alb_ids) || empty(jkpg_db_p2a_pic_ids($alb))) {
      wp_update_post(array(
        'ID' => $p->ID,
        'post_status' => 'trash',
      ));
    }
  }

  jkpg_sync_notice("Refreshed posts.");
}


class JKPGBgProcess extends WP_Background_Process {

	/**
	 * @var string
	 */
	protected $prefix = 'jkpg';

	/**
	 * @var string
	 */
	protected $action = 'sync_bg';

	/**
	 * Perform task with queued item.
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over.
	 *
	 * @return mixed
	 */
	protected function task( $op ) {
    try {
      $rec = isset($op['recursive']) && $op['recursive'];
      jkpg_sync_notice("Running {$op['op']} rec=$rec");
      if ($op['op'] == 'sync_catalog') {
        jkpg_mgmt_sync_albums();

        if ($rec) {
          $options = get_option( 'jkpg_options' );
          jkpg_mgmt_enqueue_sync_albums(
            $options['jkpg_setting_adobe_rootset'], true);
        }
      } else if ($op['op'] == 'sync_album') {
        jkpg_mgmt_sync_album($op['adobe_id']);

        if ($rec) {
          $op = array('op' => 'request_renditions',
            'adobe_id' => $op['adobe_id'], 'recursive' => true);
          $this->push_to_queue($op);
        }
      } else if ($op['op'] == 'request_renditions') {
        jkpg_mgmt_req_renditions($op['adobe_id']);

        if ($rec) {
          $op = array('op' => 'fetch_renditions',
            'adobe_id' => $op['adobe_id'], 'recursive' => true);
          $this->push_to_queue($op);
        }
      } else if ($op['op'] == 'fetch_renditions') {
        jkpg_mgmt_fetch_renditions($op['adobe_id']);

        if ($rec) {
          $op = array('op' => 'prepare_renditions',
            'adobe_id' => $op['adobe_id'], 'recursive' => true);
          $this->push_to_queue($op);
        }
      } else if ($op['op'] == 'prepare_renditions') {
        jkpg_mgmt_prepare_renditions($op['adobe_id']);
      } else {
        jkpg_sync_error("Unsupported bg operation: {$op['op']}");
      }

      $this->save();
    } catch (Exception $e) {
      jkpg_sync_error("Error: " . $e->getMessage());
    }
		return false;
	}

	/**
	 * Complete processing.
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();

		// Show notice to user or perform some other arbitrary task...
	}

}


function jkpg_mgmt_album_tree($parent_id) {
  echo "<ul>\n";
  foreach (jkpg_db_sets_get_in($parent_id) as $set) {
    echo "<li>Set: {$set->title}\n";
    jkpg_mgmt_album_tree($set->adobe_id);
    echo "</li>\n";
  }
  foreach (jkpg_db_albums_get_in($parent_id) as $alb) {
    echo "<li>Album: {$alb->title}";
    $post = jkpg_mgmt_album_post($alb->id);
    if ($post) {
      echo " <a href='" . get_permalink($post->ID) . "'>(post)</a>";
    }
    echo "</li>\n";
  }
  echo "</ul>\n";
}

function jkpg_mgmt_page_html() {
  global $jkpg_bgproc;

  try {
    if (isset($_REQUEST['sync_all'])) {
      if (!$jkpg_bgproc->is_queued()) {
        $op = array('op' => 'sync_catalog', 'recursive' => true);
        $jkpg_bgproc->push_to_queue($op);
        $jkpg_bgproc->save()->dispatch();
      }
    } else if (isset($_REQUEST['sync_posts'])) {
      $options = get_option( 'jkpg_options' );
      jkpg_mgmt_sync_posts($options['jkpg_setting_adobe_rootset']);
    } else if (isset($_REQUEST['pause_bg'])) {
      $jkpg_bgproc->pause();
    } else if (isset($_REQUEST['cancel_bg'])) {
      $jkpg_bgproc->cancel();
    } else if (isset($_REQUEST['clear_log'])) {
      jkpg_db_log_clear_upto($_REQUEST['clear_log']);
    }
  } catch (Exception $e) {
    echo "Error: " . $e->getMessage();
  }

  ?>
  <div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <div class="messages">
    <?php
    $logs = jkpg_db_log_get();
    $max_id = 0;
    foreach ($logs as $log) {
        $max_id = max($max_id, $log->id);
        switch ($log->level) {
          case 3: $class = 'error'; break;
          case 2: $class = 'warning'; break;
          case 1: $class = 'success'; break;
          default: $class = null;
        }

        if (!$class)
          continue;

        echo "<div class='msg msg-$class'>" . esc_html($log->message) .
          " <span class='timestamp'>({$log->ts})</span></div>\n";
    }
    ?>
    </div>
    <?php
    if ($max_id != 0) {
      echo "<p><a href='admin.php?page=jkpg&clear_log=$max_id'>Clear Messages</a></p>";
    }
    if ($jkpg_bgproc->is_queued()) {
      echo "<p>Synchronization is ongoing.</p>";
      echo "<p><a href='admin.php?page=jkpg&cancel_bg=1'>Cancel Sync</a></p>";
    } else {
      echo "<p><a href='admin.php?page=jkpg&sync_all=1'>Adobe Sync</a></p>";
      echo "<p><a href='admin.php?page=jkpg&sync_posts=1'>Update Posts</a></p>";
    }

    $options = get_option( 'jkpg_options' );
    $root = isset($options['jkpg_setting_adobe_rootset']) ?
      $options['jkpg_setting_adobe_rootset'] : '';
    jkpg_mgmt_album_tree($root);
    ?>
  </div>
  <?php
}