function initialize_dossier_triggers() {
  enable_proxied_links('human-element-dossier-trigger',{
    beforeSend : (function() {
      $('#doctitle').html("Dossier loader triggered. Please wait.");
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
      $('#doctitle').html("Legiscope");

      if ( /^text\/html/.test(contenttype) ) {
        if ( typeof linkset != 'null' ) {
          replace_contentof('linkset', linkset);
          initialize_linkset_clickevents($('ul[id=house-bills-by-rep]'),'li');
        }
        replace_contentof('original', data.original);
        initialize_remote_links(); 
      }
      replace_contentof('currenturl',
        $(document.createElement('a'))
        .attr('href', url)
        .attr('target','blank')
        .html(url)
      );

      /*
       * Javascript fragment to trigger cycling through dossier entries
      setTimeout(function(){
      $('div[class*=float-left]').first().find('a[class*=trigger]').removeClass('trigger').click();
      },5000);
      */
    })
  });
}

function update_representatives_avatars() {
  $('img[class*=representative-avatar][src=""]').first().each(function(){
    var avatar_id = $(this).attr('id').replace(/^image-/,'imagesrc-');
    if ( $(this).attr('src').length > 0 ) {
      setTimeout((function(){update_representatives_avatars();}),100);
      return;
    }
    var no_replace = $('input[id='+avatar_id+']').hasClass('no-replace');
    var alt_name = no_replace ? ($(this).attr('id')+'-alt') : $(this).attr('id'); 
    var member_uuid = $(this).attr('id').replace(/^image-/,'');
    var avatar_url = $('input[id='+avatar_id+']').val();
    $(this).attr('id', alt_name);
    $.ajax({
      type     : 'POST',
      url      : '/seek/',
      data     : { url : avatar_url, cache : $('#cache').prop('checked'), member_uuid : member_uuid, no_replace : no_replace, fr : true },
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
        $('img[id='+alt_name+']').attr('src', altmarkup);
        if ( !no_replace ) {
          $("div[class=dossier-strip]").find("img").each(function(){
            total_image_width += ($(this).outerWidth() + 4);
          });
          $("div[class=dossier-strip]").width(total_image_width);
        }
        setTimeout((function(){update_representatives_avatars();}),10);
      })
    });
  });
}

