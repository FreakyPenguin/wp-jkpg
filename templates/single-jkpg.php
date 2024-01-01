<?php
get_header();

$pics = [];
$imgbase = '/wp-content/uploads/jkpg/';
?>
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
		<?php
    while ( have_posts() ) : the_post();
      $aid = get_post_meta(get_the_ID(), 'jkpg_album', true);
      //echo "Post: {$post->id} Album: $aid<br>";
      $alb = jkpg_db_album_get($aid);
      $pids = jkpg_db_p2a_pic_ids($aid);
      ?>
      <h1><?php echo $alb->title; ?></h1>
      <div class="jkpg-grid grid" data-masonry='{ "itemSelector": ".grid-item", "columnWidth": 200 }'>
        <?php
        foreach ($pids as $pid) {
          $pic = jkpg_db_pic_get($pid);
          if (!$pic->sizes)
            continue;
          $pics[] = $pic;
          $sizes = explode(",", $pic->sizes);
          natsort($sizes);
          $sz = $sizes[array_key_first($sizes)];
          $fn = $imgbase . $pic->adobe_id . '-' . $sz . '.jpg';
          $szp = explode("x", $sz);
          $w = $szp[0];
          $h = $szp[1];
          ?>
          <div class="grid-item">
            <a href="#pic-<?php echo $pic->id; ?>">
              <img src="<?php echo $fn; ?>"
                width="<?php echo $w; ?>" height="<?php echo $h; ?>">
            </a>
          </div>
          <?php
        }
        ?>
      </div>
      <?php
		endwhile; // end of the loop.
    ?>

		</main><!-- #main -->
	</div><!-- #primary -->

  <div id="popovers">
  <?php
  $npics = count($pics);
  for ($i = 0; $i < $npics; $i++) {
    $pic = $pics[$i];
    if ($i != 0) {
      $ppic = $pics[$i - 1];
      $prev_id = $ppic->id;
    } else {
      $prev_iq = '';
    }
    if ($i != $npics - 1) {
      $npic = $pics[$i + 1];
      $next_id = $npic->id;
    } else {
      $next_id = '';
    }
    ?>
    <div id="popover-<?php echo $pic->id; ?>" 
      class="popover-image"
      data-prev-id="<?php echo $prev_id; ?>"
      data-next-id="<?php echo $next_id; ?>">
      <a class="cancellink" href="#">x</a>
    <?php
    if ($prev_id != '') {
      ?>
      <a class="prevlink" href="#pic-<?php echo $prev_id; ?>">&lt;</a>
      <?php
    }
    if ($next_id != '') {
      ?>
      <a class="nextlink" href="#pic-<?php echo $next_id; ?>">&gt;</a>
      <?php
    }

    $srcs = [];
    $sizes = explode(",", $pic->sizes);
    natsort($sizes);
    foreach ($sizes as $sz) {
      $fn = $imgbase . $pic->adobe_id . '-' . $sz . '.jpg';
      $szp = explode("x", $sz);
      $w = $szp[0];
      $h = $szp[1];

      $srcs[] = "$fn ${w}w";
    }
    $srcset = implode(", ", $srcs);

    echo "<img srcset=\"$srcset\" src=\"$fn\" width=\"$w\" height=\"$h\">";
    ?>
    </div>
    <?php
  }
  ?>
  </div>

  <script src="https://unpkg.com/masonry-layout@4/dist/masonry.pkgd.min.js"></script>
  <script>
    // init Masonry
    var $grid = $('.grid').masonry({
      itemSelector: '.grid-item',
      columnWidth: 300,
    });
    // layout Masonry after each image loads
    $grid.imagesLoaded().progress( function() {
      $grid.masonry('layout');
    });
  </script>
<?php get_footer(); ?>