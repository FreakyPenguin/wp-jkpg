<?php

require_once(dirname( __FILE__ ) . '/../pel/autoload.php');

use lsolesen\pel\PelExif;
use lsolesen\pel\PelTiff;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelEntryAscii;
use lsolesen\pel\PelEntryCopyright;
use lsolesen\pel\PelTag;

function jkpg_adobe_date_to_db($d) {
  $datetime = new DateTime($d);
  return $datetime->format('Y-m-d H:i:s');
}

function jkpg_mgmt_adobe_client() {
  $options = get_option( 'jkpg_options' );
  $lrc = new JKPGAdobeLRClient(
    $options['jkpg_setting_adobe_clientid'],
    $options['jkpg_setting_
    adobe_token']);
  return $lrc;
}

function jkpg_mgmt_sync_albums() {
  $lrc = jkpg_mgmt_adobe_client();
  $cat = $lrc->get_catalog();
  $albums_req = $lrc->get_all_albums($cat->id);

  foreach ($albums_req as $alb) {
    if ($alb->type != 'album') {
      echo "Non-album entry {$alb->id} of type {$alb->type}, ignoring<br/>";
      continue;
    }

    $parent_id = null;
    if (isset($alb->payload->parent))
      $parent_id = $alb->payload->parent->id;

    if ($alb->subtype == 'collection') {
      $la = jkpg_db_album_get_adobe($alb->id);
      if ($la) {
        $ud = jkpg_adobe_date_to_db($alb->updated);

        /* if album has been updated, mark as not synchronized */
        if ($ud != $la->updated)
          $la->synchronized = 0;

        jkpg_db_album_update($la->id, $parent_id, $ud, $alb->payload->name,
          '', $la->synchronized);
      } else {
        /* TODO: caption? */
        jkpg_db_album_insert($alb->id, $parent_id,
          jkpg_adobe_date_to_db($alb->created),
          jkpg_adobe_date_to_db($alb->updated), $alb->payload->name, '');

      }
    } else if ($alb->subtype == 'collection_set') {
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
      echo "Unknown subtype {$alb->subtype} for {$alb->id}, ignoring<br/>";
      continue;
    }
  }
}

function jkpg_mgmt_sync_album($adobe_id) {
  $lrc = jkpg_mgmt_adobe_client();
  $cat = $lrc->get_catalog();
  $assets = $lrc->get_all_assets($cat->id, $adobe_id);

  $alb = jkpg_db_album_get_adobe($adobe_id);

  foreach ($assets as $a) {
    // just skip non-images for now
    if ($a->subtype != 'image')
      continue;

    $pic = jkpg_db_pic_get_adobe($a->id);
    if ($pic) {
      echo "Updating {$a->id}<br/>";
      $ud = jkpg_adobe_date_to_db($a->updated);

      // if album has been updated, mark as not synchronized
      if ($ud != $pic->updated)
        $pic->synchronized = 0;

      // TODO: title, description?
      jkpg_db_pic_update($a->id, $ud, '', '', $pic->synchronized);
    } else {
      echo "Inserting {$a->id}<br/>";
      // TODO: title, description?
      jkpg_db_pic_insert($a->id, jkpg_adobe_date_to_db($a->created),
        jkpg_adobe_date_to_db($a->updated), '', '');

      $pic = jkpg_db_pic_get_adobe($a->id);
    }

    $p2a = jkpg_db_p2a_get($pic->id, $alb->id);
    if (!$p2a) {
      jkpg_db_p2a_insert($pic->id, $alb->id);
    }
  }
}

function jkpg_mgmt_req_renditions($adobe_id) {
  $lrc = jkpg_mgmt_adobe_client();
  $cat = $lrc->get_catalog();

  $alb = jkpg_db_album_get_adobe($adobe_id);
  $picids = jkpg_db_p2a_pic_ids($alb->id);

  print_r($picids);

  foreach ($picids as $id) {
    $pic = jkpg_db_pic_get($id);
    if ($pic->requested)
      continue;
    echo "requesting {$pic->id}<br/>";
    print_r($lrc->request_rendition($cat->id, $pic->adobe_id));
    jkpg_db_pic_setflag($pic->id, 'requested', 1);
    echo "<br/>";
  } 
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
    if (!$pic->retrieved)
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
  }
  
}

function jkpg_mgmt_create_post($adobe_id) {
  $alb = jkpg_db_album_get_adobe($adobe_id);
  $posts = get_posts(array(
      'post_type' => 'jkpg',
      'meta_query' => array(
        array(
          'key' => 'jkpg_album',
          'value' => $alb->id,
        )
      ),
  ));

  if ($posts) {
    echo "Found post: " . $posts[0]->id . "<br>";
  } else {
    $pid = wp_insert_post(array(
        'post_type' => 'jkpg',
        'post_date' => $alb->created,
        'post_title' => $alb->title,
        'post_status' => 'publish',
        'meta_input' => array(
          'jkpg_album' => $alb->id,
        ),
    ));
    echo "Created " . $pid . "<br>";
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
    echo "<li>Album: {$alb->title}</li>\n";
  }
  echo "</ul>\n";
}

function jkpg_mgmt_page_html() {
  $options = get_option( 'jkpg_options' );

  try {
    if (isset($_REQUEST['sync_albums'])) {
      jkpg_mgmt_sync_albums();
    } else if (isset($_REQUEST['sync_album'])) {
      jkpg_mgmt_sync_album($_REQUEST['sync_album']);
    } else if (isset($_REQUEST['req_renditions'])) {
      jkpg_mgmt_req_renditions($_REQUEST['req_renditions']);
    } else if (isset($_REQUEST['fetch_renditions'])) {
      jkpg_mgmt_fetch_renditions($_REQUEST['fetch_renditions']);
    } else if (isset($_REQUEST['prepare_renditions'])) {
      jkpg_mgmt_prepare_renditions($_REQUEST['prepare_renditions']);
    } else if (isset($_REQUEST['create_post'])) {
      jkpg_mgmt_create_post($_REQUEST['create_post']);
    }
  } catch (Exception $e) {
    echo "Error: " . $e->getMessage();
  }

  ?>
  <div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
  <?php jkpg_mgmt_album_tree(''); ?>
  </div>
  <?php
}