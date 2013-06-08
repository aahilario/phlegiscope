function initialize_dossier_triggers() {
  enable_proxied_links('human-element-dossier-trigger',{
    beforeSend : (function() {
      jQuery('#doctitle').html("Dossier loader triggered. Please wait.");
      display_wait_notification();
    }),
    complete : (function(jqueryXHR, textStatus) {
      remove_wait_notification();
    }),
    success : (function(data, httpstatus, jqueryXHR) {
      var response = data.error ? data.message : data.linkset;
      var markup = data.markup;
      var responseheader = data.responseheader;
      var referrer = data.referrer;
      var url = data.url;
      var contenttype = data.contenttype ? data.contenttype : '';
      var linkset = data.linkset ? data.linkset : null;
      jQuery('#doctitle').html("Legiscope");

      if ( /^text\/html/.test(contenttype) ) {
        if ( typeof linkset != 'null' ) {
          replace_contentof('linkset', linkset);
          initialize_linkset_clickevents(jQuery('ul[id=house-bills-by-rep]'),'li');
        }
        replace_contentof('original', data.original);
        initialize_remote_links(); 
      }
      replace_contentof('currenturl',
        jQuery(document.createElement('a'))
        .attr('href', url)
        .attr('target','blank')
        .html(url)
      );

      /*
       * Javascript fragment to trigger cycling through dossier entries
      setTimeout(function(){
      jQuery('div[class*=float-left]').first().find('a[class*=trigger]').removeClass('trigger').click();
      },5000);
      */
    })
  });
}

function update_representatives_avatars() {
  jQuery('img[class*=representative-avatar][src=""]').first().each(function(){
    var avatar_id = jQuery(this).attr('id').replace(/^image-/,'imagesrc-');
    if ( jQuery(this).attr('src').length > 0 && !/^data:/.test(jQuery(this).attr('src')) ) {
      setTimeout((function(){update_representatives_avatars();}),100);
      return;
    }
    var no_replace = jQuery('input[id='+avatar_id+']').hasClass('no-replace');
    var alt_name = no_replace ? (jQuery(this).attr('id')+'-alt') : jQuery(this).attr('id'); 
    var member_uuid = jQuery(this).attr('id').replace(/^image-/,'');
    var avatar_url = jQuery('input[id='+avatar_id+']').val();
    jQuery(this).attr('id', alt_name);
    jQuery.ajax({
      type     : 'POST',
      url      : '/seek/',
      data     : { url : avatar_url, modifier : jQuery('#spider').prop('checked'), cache : jQuery('#cache').prop('checked'), member_uuid : member_uuid, no_replace : no_replace, fr : true },
      cache    : false,
      dataType : 'json',
      async    : true,
      beforeSend : (function() {
        display_wait_notification();
      }),
      complete : (function(jqueryXHR, textStatus) {
        remove_wait_notification();
      }),
      success  : (function(data, httpstatus, jqueryXHR) {
        var altmarkup = data.altmarkup ? data.altmarkup : null;
        var total_image_width = 0;
        jQuery('img[id='+alt_name+']').attr('src', altmarkup);
        if ( !no_replace ) {
          jQuery("div[class=dossier-strip]").find("img").each(function(){
            total_image_width += (jQuery(this).outerWidth() + 4);
          });
          jQuery("div[class=dossier-strip]").width(total_image_width);
        }
        setTimeout((function(){update_representatives_avatars();}),10);
      })
    });
  });
}
