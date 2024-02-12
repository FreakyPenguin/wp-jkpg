<?php
get_header();

$pics = [];
$imgbase = '/wp-content/uploads/jkpg/';

$args = array(
	'post_type' => 'jkpg',
);
$loop = new WP_Query($args);
?>
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
    <div class="jkpg-grid grid" data-masonry='{ "itemSelector": ".grid-item", "columnWidth": 280 }'>
    <?php
    while ( $loop->have_posts() ) : $loop->the_post();
      $aid = get_post_meta(get_the_ID(), 'jkpg_album', true);
      $alb = jkpg_db_album_get($aid);
      $pids = jkpg_db_p2a_pic_ids($aid);
      $pic = jkpg_db_pic_get($pids[0]);
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
        <a href="<?php echo get_permalink(get_the_ID()); ?>">
          <span class="title"><?php echo esc_html($alb->title); ?></span>
          <img src="<?php echo $fn; ?>"
            width="<?php echo $w; ?>"
            height="<?php echo $h; ?>"
            title="<?php echo esc_attr($alb->title); ?>"
            alt="<?php echo esc_attr($alb->title); ?>">
        </a>
      </div>
      <?php
		endwhile; // end of the loop.
    ?>
    </div>

		</main><!-- #main -->
	</div><!-- #primary -->

  <script src="https://unpkg.com/masonry-layout@4/dist/masonry.pkgd.min.js"></script>
  <script>
    // init Masonry
    var $grid = $('.grid').masonry({
      itemSelector: '.grid-item',
      columnWidth: 280,
    });
    // layout Masonry after each image loads
    $grid.imagesLoaded().progress( function() {
      $grid.masonry('layout');
    });
  </script>
<?php get_footer(); ?>