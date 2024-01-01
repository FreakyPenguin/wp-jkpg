var jkpg_active = null;

function jkpg_inactive() {
  if (jkpg_active != null) {
    // set previous one inactive
    jQuery('#popover-' + jkpg_active).removeClass('popover-image-active');
    jkpg_active = null;
  }
}

function jkpg_set_active(new_id) {
  jQuery('#popover-' + new_id).addClass('popover-image-active');
  jkpg_active = new_id;
  
}

function jkpg_switch_active(new_id) {
  jkpg_inactive();
  jkpg_set_active(new_id);
}

function jpkg_prev() {
  if (jkpg_active == null)
    return;
  var previd = jQuery('#popover-' + jkpg_active).data('prev-id');
  if (previd != '') {
    window.location.hash = '#pic-' + previd;
  }
}

function jpkg_next() {
  if (jkpg_active == null)
    return;
  var nextid = jQuery('#popover-' + jkpg_active).data('next-id');
  if (nextid != '') {
    window.location.hash = '#pic-' + nextid;
  }
}

function jkpg_hash_change() {
  var hash = jQuery(location).attr('hash').slice(1);
  if (hash.startsWith('pic-')) {
    console.log("hash change:" + hash.slice(4));
    jkpg_switch_active(hash.slice(4));
  } else {
    console.log("hash change: deactivate");
    jkpg_inactive();
  }
}

jQuery(window).on('hashchange', function(e){
  jkpg_hash_change();
});

jQuery(window).ready(function() {
  jkpg_hash_change();
});

jQuery(document).keydown(function(e){
  if (e.which == 27) {
    jkpg_inactive();
    window.location.hash = '';
  } else if (e.which == 37) { 
     jpkg_prev();
  } else if (e.which == 39) {
    jpkg_next();
  }
});
